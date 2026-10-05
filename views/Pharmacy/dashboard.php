<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    header("Location: ../login.php?type=pharmacist");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Prescription.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();
$prescription_model = new Prescription($db);

$pending_prescriptions = $prescription_model->getPendingPrescriptions();

// Get all available medications for selection
$meds_query = "SELECT * FROM medications WHERE quantity_in_stock > 0 ORDER BY name ASC";
$stmt = $db->prepare($meds_query);
$stmt->execute();
$available_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get low stock alerts (الكمية بين 1 و49)
$low_stock_query = "SELECT * FROM medications WHERE quantity_in_stock > 0 AND quantity_in_stock < 50 ORDER BY quantity_in_stock ASC";
$low_stock_stmt = $db->prepare($low_stock_query);
$low_stock_stmt->execute();
$low_stock_meds = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get out of stock alerts (الكمية 0 أو سالبة)
$out_stock_query = "SELECT * FROM medications WHERE quantity_in_stock <= 0";
$out_stock_stmt = $db->prepare($out_stock_query);
$out_stock_stmt->execute();
$out_stock_meds = $out_stock_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispense'])) {
    $prescription_id = $_POST['prescription_id'];
    $patient_id = $_POST['patient_id'];
    $institution_type = $_POST['institution_type'];
    $medication_id = isset($_POST['medication_id']) ? intval($_POST['medication_id']) : null;
    $dispense_quantity = isset($_POST['dispense_quantity']) ? intval($_POST['dispense_quantity']) : 1;
    $amount = $institution_type === 'charity' ? 0 : floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    
    $db->beginTransaction();
    
    try {
        // If medication selected, reduce from inventory
        if ($medication_id && $dispense_quantity > 0) {
            // Get current stock
            $stock_query = "SELECT name, quantity_in_stock FROM medications WHERE id = :id";
            $stmt = $db->prepare($stock_query);
            $stmt->bindParam(':id', $medication_id);
            $stmt->execute();
            $med = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($med) {
                $new_quantity = $med['quantity_in_stock'] - $dispense_quantity;
                
                if ($new_quantity < 0) {
                    throw new Exception("Not enough stock! Only {$med['quantity_in_stock']} units available.");
                }
                
                // Update inventory
                $update_query = "UPDATE medications SET quantity_in_stock = :quantity WHERE id = :id";
                $stmt = $db->prepare($update_query);
                $stmt->bindParam(':quantity', $new_quantity);
                $stmt->bindParam(':id', $medication_id);
                $stmt->execute();
                
                // Record the dispensed medication
                $dispense_rec = "INSERT INTO dispensed_medications 
                                (prescription_id, medication_id, quantity, dosage_times_per_day, 
                                 dosage_instructions, dispensed_by)
                                VALUES (:prescription_id, :medication_id, :quantity, :dosage_times, 
                                        :instructions, :pharmacist_id)";
                
                $stmt = $db->prepare($dispense_rec);
                $stmt->execute([
                    ':prescription_id' => $prescription_id,
                    ':medication_id' => $medication_id,
                    ':quantity' => $dispense_quantity,
                    ':dosage_times' => intval($_POST['dosage_times'] ?? 3),
                    ':instructions' => htmlspecialchars($_POST['dosage_instructions'] ?? ''),
                    ':pharmacist_id' => $_SESSION['user_id']
                ]);
                
                // Check if low stock or out of stock
                $stock_alert = '';
                if ($new_quantity <= 0) {
                    $stock_alert = " ⚠️ WARNING: {$med['name']} is now OUT OF STOCK!";
                } elseif ($new_quantity < 50) {
                    $stock_alert = " ⚠️ LOW STOCK: {$med['name']} has only {$new_quantity} units remaining!";
                }
            }
        }
        
        // Record payment
        $payment_query = "INSERT INTO payments (prescription_id, patient_id, amount, payment_method, institution_type, status)
                         VALUES (:prescription_id, :patient_id, :amount, :payment_method, :institution_type, 'completed')";
        $stmt = $db->prepare($payment_query);
        $stmt->execute([
            ':prescription_id' => $prescription_id,
            ':patient_id' => $patient_id,
            ':amount' => $amount,
            ':payment_method' => $payment_method,
            ':institution_type' => $institution_type
        ]);
        
        // Update prescription status
        $prescription_model->updateStatus($prescription_id, 'completed');
        
        $db->commit();
        
        $success_message = "✅ Medication dispensed successfully! ";
        if ($institution_type === 'charity') {
            $success_message .= "Free service provided.";
        } else {
            $success_message .= "Payment of ₪{$amount} processed via {$payment_method}.";
        }
        if (isset($stock_alert)) {
            $success_message .= $stock_alert;
        }
        
        header("Location: dashboard.php?success=" . urlencode($success_message));
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        header("Location: dashboard.php?error=" . urlencode("❌ Error: " . $e->getMessage()));
        exit();
    }
}
?>
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ('pharmacist'); ?> <?php echo ('dashboard'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
 <style>
        body { background: #fff8f0; font-family: 'Segoe UI', sans-serif; }
        .navbar-pharmacy { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .prescription-card { border-left: 5px solid #fc4a1a; background: linear-gradient(135deg, #fff5e6 0%, #ffe6cc 100%); }
        .payment-section { background: #e8f5e9; border: 2px solid #4caf50; border-radius: 10px; padding: 20px; margin-top: 15px; }
        .btn-dispense { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); border: none; color: white; padding: 15px; font-size: 1.1rem; }
        .alert-inventory { background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%); border-left: 5px solid #ffc107; }
        .alert-critical { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-left: 5px solid #dc3545; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .stock-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .stock-out { background-color: #ffcccc; color: #cc0000; }
        .stock-low { background-color: #fff3cd; color: #856404; }
        .stock-good { background-color: #d4edda; color: #155724; }
    </style>
</head>
<body>
    
  <nav class="navbar navbar-pharmacy navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-capsule me-2"></i><?php echo __('pharmacist'); ?> <?php echo __('dashboard'); ?> - <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div>
                <a href="sales.php" class="btn btn-warning me-2 text-dark fw-bold">
                    <i class="bi bi-cart-check me-1"></i><?php echo __('sales'); ?>
                </a>
                <a href="inventory.php" class="btn btn-light me-2">
                    <i class="bi bi-box-seam me-1"></i><?php echo __('visit_warehouse'); ?>
                    <?php if (count($out_stock_meds) > 0): ?>
                        <span class="badge bg-danger"><?php echo count($out_stock_meds); ?></span>
                    <?php elseif (count($low_stock_meds) > 0): ?>
                        <span class="badge bg-warning"><?php echo count($low_stock_meds); ?></span>
                    <?php endif; ?>
                </a>
                <?php if (($_SESSION['lang'] ?? 'ar') === 'en'): ?>
                    <a href="../../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm me-2">عربي</a>
                <?php else: ?>
                    <a href="../../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm me-2">English</a>
                <?php endif; ?>
                <a href="../../controllers/logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i><?php echo __('logout'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Inventory Alerts - تم وضعها في الأعلى لضمان رؤيتها -->
        <?php if (count($out_stock_meds) > 0): ?>
            <div class="alert alert-critical alert-dismissible fade show">
                <h5 class="alert-heading">
                    <i class="bi bi-exclamation-octagon-fill me-2"></i>
                    🚨 CRITICAL: <?php echo count($out_stock_meds); ?> Medication(s) OUT OF STOCK!
                </h5>
                <ul class="mb-0">
                    <?php foreach (array_slice($out_stock_meds, 0, 5) as $med): ?>
                        <li><strong><?php echo htmlspecialchars($med['name']); ?></strong> - <?php echo $med['category']; ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($out_stock_meds) > 5): ?>
                    <p class="mb-0 mt-2"><small>+ <?php echo count($out_stock_meds) - 5; ?> more...</small></p>
                <?php endif; ?>
                <a href="inventory.php" class="btn btn-danger btn-sm mt-2">
                    <i class="bi bi-box-seam me-1"></i>Go to Inventory
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (count($low_stock_meds) > 0): ?>
            <div class="alert alert-inventory alert-dismissible fade show">
                <h6 class="alert-heading">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    ⚠️ WARNING: <?php echo count($low_stock_meds); ?> Medication(s) Running Low!
                </h6>
                <ul class="mb-0">
                    <?php foreach (array_slice($low_stock_meds, 0, 3) as $med): ?>
                        <li><strong><?php echo htmlspecialchars($med['name']); ?></strong> - Only <?php echo $med['quantity_in_stock']; ?> units left</li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_GET['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>


        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0">
                    <i class="bi bi-prescription2 me-2"></i>
                    Pending Prescriptions (<?php echo count($pending_prescriptions); ?>)
                </h4>
            </div>
        </div>

        <?php if (empty($pending_prescriptions)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted mb-4"></i>
                    <h4 class="text-muted">No Pending Prescriptions</h4>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($pending_prescriptions as $rx): ?>
            <div class="card prescription-card mb-4">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">
                                <i class="bi bi-person-fill me-2 text-primary"></i>
                                <?php echo htmlspecialchars($rx['full_name']); ?>
                            </h5>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="fw-bold">National ID:</td>
                                    <td><?php echo htmlspecialchars($rx['national_id'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Age:</td>
                                    <td><?php echo $rx['age']; ?> years</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Doctor:</td>
                                    <td>Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?></td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6 class="fw-bold"><i class="bi bi-capsule me-2"></i>Prescribed:</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($rx['medication'])); ?></p>
                            </div>
                            
                            <?php if ($rx['notes']): ?>
                            <div class="alert alert-warning">
                                <h6 class="fw-bold"><i class="bi bi-journal-medical me-2"></i>Notes:</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($rx['notes'])); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="mt-3">
                                <button type="button" id="ai_btn_<?php echo $rx['prescription_id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="getAIAlternative(<?php echo $rx['patient_id']; ?>, <?php echo htmlspecialchars(json_encode($rx['medication']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($rx['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $rx['prescription_id']; ?>)">
                                    <i class="bi bi-robot me-1"></i> Ask AI for Available Alternative
                                </button>
                                <div id="ai_spinner_<?php echo $rx['prescription_id']; ?>" class="spinner-border spinner-border-sm text-primary ms-2 d-none" role="status"></div>
                            </div>
                            <div id="ai_result_<?php echo $rx['prescription_id']; ?>" class="mt-3 d-none">
                                <div class="alert alert-success">
                                    <h6 class="fw-bold"><i class="bi bi-check-circle me-1"></i> AI Suggestion: <span class="ai-med-name"></span></h6>
                                    <p class="mb-0 small ai-reasoning"></p>
                                    <button type="button" class="btn btn-success btn-sm mt-2" onclick="useAIAlternative(<?php echo $rx['prescription_id']; ?>)">Use This Alternative</button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <hr>

                    <form method="POST" id="dispensForm<?php echo $rx['prescription_id']; ?>">
                        <input type="hidden" name="prescription_id" value="<?php echo $rx['prescription_id']; ?>">
                        <input type="hidden" name="patient_id" value="<?php echo $rx['patient_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Medication Selection from Inventory -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-box-seam me-2"></i>Select Medication from Inventory
                                    </label>
                                    <select class="form-select" name="medication_id" 
                                            id="medication_<?php echo $rx['prescription_id']; ?>"
                                            onchange="updatePrice(<?php echo $rx['prescription_id']; ?>)">
                                        <option value="">-- Select Medication --</option>
                                        <?php foreach ($available_medications as $med): ?>
                                        <option value="<?php echo $med['id']; ?>" 
                                                data-price="<?php echo $med['unit_price']; ?>"
                                                data-stock="<?php echo $med['quantity_in_stock']; ?>"
                                                data-name="<?php echo htmlspecialchars($med['name']); ?>">
                                            <?php echo htmlspecialchars($med['name']); ?> 
                                            (Stock: <?php echo $med['quantity_in_stock']; ?> units, 
                                             Price: ₪<?php echo number_format($med['unit_price'], 2); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Quantity to Dispense -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-hash me-2"></i>Quantity to Dispense
                                    </label>
                                    <input type="number" class="form-control" name="dispense_quantity" 
                                           id="quantity_<?php echo $rx['prescription_id']; ?>"
                                           value="1" min="1" 
                                           onchange="updatePrice(<?php echo $rx['prescription_id']; ?>)">
                                    <small class="text-muted" id="stock_info_<?php echo $rx['prescription_id']; ?>"></small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Dosage Times Per Day</label>
                                    <input type="number" class="form-control" name="dosage_times" value="3" min="1" max="6">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Dosage Instructions</label>
                                    <textarea class="form-control" name="dosage_instructions" rows="2" 
                                              placeholder="Take after meals..."></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="payment-section">
                                    <h6 class="fw-bold mb-3">
                                        <i class="bi bi-cash-coin me-2"></i>Payment Information
                                    </h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Institution Type</label>
                                        <select class="form-select" name="institution_type" 
                                                onchange="togglePayment(this, <?php echo $rx['prescription_id']; ?>)">
                                            <option value="charity">Charity (Free)</option>
                                            <option value="government" selected>Government</option>
                                            <option value="private">Private</option>
                                        </select>
                                    </div>
                                    
                                    <div id="payment_fields_<?php echo $rx['prescription_id']; ?>">
                                        <!-- Auto-calculated Price Display -->
                                        <div class="price-display" id="price_display_<?php echo $rx['prescription_id']; ?>">
                                            <i class="bi bi-currency-dollar me-2"></i>
                                            <span id="total_price_<?php echo $rx['prescription_id']; ?>">₪0.00</span>
                                        </div>
                                        
                                        <input type="hidden" name="amount" 
                                               id="amount_<?php echo $rx['prescription_id']; ?>" value="0">
                                        
                                        <div class="mb-3 mt-3">
                                            <label class="form-label fw-bold">Payment Method</label>
                                            <select class="form-select" name="payment_method">
                                                <option value="cash">Cash (كاش)</option>
                                                <option value="bank_palestine">Bank of Palestine</option>
                                                <option value="palPay">PalPay</option>
                                                <option value="jawalPay">JawalPay</option>
                                                <option value="visa">Visa Card</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-3">
                            <button type="submit" name="dispense" class="btn btn-dispense btn-lg">
                                <i class="bi bi-bag-check me-2"></i>Dispense Medication & Process Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updatePrice(rxId) {
            const medSelect = document.getElementById('medication_' + rxId);
            const quantityInput = document.getElementById('quantity_' + rxId);
            const priceDisplay = document.getElementById('total_price_' + rxId);
            const amountInput = document.getElementById('amount_' + rxId);
            const stockInfo = document.getElementById('stock_info_' + rxId);
            
            const selectedOption = medSelect.options[medSelect.selectedIndex];
            
            if (selectedOption.value) {
                const unitPrice = parseFloat(selectedOption.dataset.price);
                const stock = parseInt(selectedOption.dataset.stock);
                const medName = selectedOption.dataset.name;
                const quantity = parseInt(quantityInput.value) || 1;
                
                // Check if quantity exceeds stock
                if (quantity > stock) {
                    quantityInput.value = stock;
                    stockInfo.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Insufficient Stock! Asking AI for alternative...</span>`;
                    updatePrice(rxId); // Recalculate with corrected quantity
                    
                    // Auto-trigger the AI Alternative button
                    const aiBtn = document.getElementById('ai_btn_' + rxId);
                    if(aiBtn && !aiBtn.disabled) {
                        aiBtn.classList.replace('btn-outline-primary', 'btn-danger');
                        aiBtn.click();
                    }
                    return;
                }
                
                const totalPrice = unitPrice * quantity;
                
                // Update display
                priceDisplay.innerHTML = `<i class="bi bi-currency-dollar me-2"></i>₪${totalPrice.toFixed(2)}`;
                amountInput.value = totalPrice.toFixed(2);
                
                // Stock warning
                const remaining = stock - quantity;
                if (remaining <= 0) {
                    stockInfo.innerHTML = `<span class="stock-badge stock-out"><i class="bi bi-exclamation-octagon me-1"></i>WARNING: This will deplete ${medName} stock!</span>`;
                } else if (remaining < 50) {
                    stockInfo.innerHTML = `<span class="stock-badge stock-low"><i class="bi bi-exclamation-triangle me-1"></i>After dispensing: ${remaining} units remaining (LOW STOCK)</span>`;
                } else {
                    stockInfo.innerHTML = `<span class="stock-badge stock-good"><i class="bi bi-check-circle me-1"></i>After dispensing: ${remaining} units remaining</span>`;
                }
            } else {
                priceDisplay.innerHTML = '<i class="bi bi-currency-dollar me-2"></i>₪0.00';
                amountInput.value = '0';
                stockInfo.innerHTML = '';
            }
        }
        
        function togglePayment(select, id) {
            const paymentFields = document.getElementById('payment_fields_' + id);
            if (select.value === 'charity') {
                paymentFields.style.display = 'none';
                document.getElementById('amount_' + id).value = '0';
            } else {
                paymentFields.style.display = 'block';
                updatePrice(id); // Recalculate price
            }
        }

        function getAIAlternative(patientId, medication, notes, prescriptionId) {
            const spinner = document.getElementById('ai_spinner_' + prescriptionId);
            const resultDiv = document.getElementById('ai_result_' + prescriptionId);
            const btn = spinner.previousElementSibling;

            btn.disabled = true;
            spinner.classList.remove('d-none');
            resultDiv.classList.add('d-none');

            const formData = new FormData();
            formData.append('patient_id', patientId);
            formData.append('prescribed_medication', medication);
            formData.append('notes', notes);

            fetch('../../api/get_ai_alternative.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                spinner.classList.add('d-none');
                btn.disabled = false;
                
                if (data.success && data.suggestion) {
                    const nameSpan = resultDiv.querySelector('.ai-med-name');
                    const reasonP = resultDiv.querySelector('.ai-reasoning');
                    
                    nameSpan.textContent = data.suggestion.suggested_medication_name;
                    reasonP.textContent = data.suggestion.reasoning;
                    
                    // Store suggested id on the button to be used later
                    const useBtn = resultDiv.querySelector('.btn-success');
                    useBtn.dataset.medId = data.suggestion.suggested_medication_id;
                    useBtn.dataset.medName = data.suggestion.suggested_medication_name;

                    resultDiv.classList.remove('d-none');
                } else {
                    alert(data.message || 'Error communicating with AI.');
                }
            })
            .catch(err => {
                spinner.classList.add('d-none');
                btn.disabled = false;
                alert('Network error while querying AI. '+ err);
            });
        }

        function useAIAlternative(prescriptionId) {
            const resultDiv = document.getElementById('ai_result_' + prescriptionId);
            const useBtn = resultDiv.querySelector('.btn-success');
            const medId = useBtn.dataset.medId;

            // Select the AI suggested option in the select dropdown
            const nameSelect = document.getElementById('medication_' + prescriptionId);
            
            let found = false;
            Array.from(nameSelect.options).forEach(opt => {
                if (opt.value == medId) {
                    opt.selected = true;
                    found = true;
                }
            });

            if (found) {
                updatePrice(prescriptionId);
                resultDiv.classList.add('d-none');
                alert('Alternative selected successfully!');
            } else {
                alert('Medication ID not found in the dropdown list.');
            }
        }
    </script>
</body>
</html>