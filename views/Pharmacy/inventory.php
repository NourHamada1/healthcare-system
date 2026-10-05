<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    header("Location: ../login.php?type=pharmacist");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get all medications
$query = "SELECT * FROM medications ORDER BY name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get out of stock medications
$out_of_stock = array_filter($medications, function($med) {
    return $med['quantity_in_stock'] <= 0;
});

// Get low stock medications (< 50)
$low_stock = array_filter($medications, function($med) {
    return $med['quantity_in_stock'] > 0 && $med['quantity_in_stock'] < 50;
});

// AI function to find similar medication
function findSimilarMedication($category, $exclude_id, $db) {
    $query = "SELECT * FROM medications 
              WHERE category = :category 
              AND id != :exclude_id 
              AND quantity_in_stock > 0 
              ORDER BY quantity_in_stock DESC 
              LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':exclude_id', $exclude_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ('inventory'); ?> - <?php echo ('pharmacist'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .navbar-pharmacy { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .out-of-stock { background: #fee; border-left: 5px solid #dc3545; }
        .low-stock { background: #fff3cd; border-left: 5px solid #ffc107; }
        .in-stock { background: #d4edda; border-left: 5px solid #28a745; }
        .lang-switcher { position: fixed; top: 70px; right: 20px; z-index: 1000; }
        .ai-box { background: #e3f2fd; border: 2px solid #2196f3; border-radius: 10px; padding: 15px; margin-top: 10px; }
    </style>
</head>
<body>

<?php
require_once __DIR__ . '/../../includes/language.php';
?>
    <nav class="navbar navbar-pharmacy navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-capsule me-2"></i><?php echo __('inventory'); ?> - <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div>
                <a href="sales.php" class="btn btn-warning me-2 text-dark fw-bold">
                    <i class="bi bi-cart-check me-1"></i><?php echo __('sales'); ?>
                </a>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i><?php echo __('dashboard'); ?>
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
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-boxes display-4 text-primary mb-2"></i>
                        <h5><?php echo ('inventory'); ?></h5>
                        <h2><?php echo count($medications); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-exclamation-triangle display-4 text-danger mb-2"></i>
                        <h5><?php echo ('out_of_stock'); ?></h5>
                        <h2 class="text-danger"><?php echo count($out_of_stock); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-exclamation-circle display-4 text-warning mb-2"></i>
                        <h5><?php echo ('low_stock'); ?></h5>
                        <h2 class="text-warning"><?php echo count($low_stock); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <button class="btn btn-primary btn-lg w-100" data-bs-toggle="modal" data-bs-target="#addModal">
                            <i class="bi bi-plus-circle me-2"></i><?php echo ('add_medication'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medications List -->
        <div class="card">
            <div class="card-header bg-white">
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="mb-0"><?php echo ('inventory'); ?></h4>
                    </div>
                    <div class="col-md-6">
                        <input type="text" id="searchInput" class="form-control" placeholder="<?php echo ('search'); ?>...">
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="medicationsTable">
                        <thead>
                            <tr>
                                <th><?php echo ('name'); ?></th>
                                <th>Category</th>
                                <th><?php echo ('quantity'); ?></th>
                                <th><?php echo ('price'); ?> (ILS)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medications as $med): 
                                 $stock = (int)$med['quantity_in_stock'];
                                 $display_stock = $stock < 0 ? 0 : $stock;
    
                                 // إنشاء نسخة مُعدَّلة للكمية لتجنب القيم السالبة في التعديل
                                 $medForEdit = $med;
                                 $medForEdit['quantity_in_stock'] = $display_stock;
                                ?>
                                <?php 
                                    $stock = (int)$med['quantity_in_stock'];
                                    $display_stock = $stock < 0 ? 0 : $stock;
                                ?>
                            <tr class="<?php 
                                if ($stock <= 0) echo 'out-of-stock';
                                elseif ($stock < 50) echo 'low-stock';
                                else echo 'in-stock';
                            ?>">
                                <td><strong><?php echo htmlspecialchars($med['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($med['category']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $stock <= 0 ? 'danger' : ($stock < 50 ? 'warning' : 'success'); ?>">
                                        <?php echo $display_stock; ?> units
                                    </span>
                                </td>
                                <td><?php echo number_format($med['unit_price'], 2); ?></td>
                                <td>
                                    <?php if ($stock <= 0): ?>
                                        <span class="badge bg-danger"><?php echo __('out_of_stock' ?? 'Out of stock'); ?></span>
                                    <?php elseif ($stock < 50): ?>
                                        <span class="badge bg-warning"><?php echo __('low_stock' ?? 'Low stock'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editMedication(<?php echo htmlspecialchars(json_encode($medForEdit)); ?>)">                                        <i class="bi bi-pencil"></i> <?php echo ('edit'); ?>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars($med['name']); ?>')">
                                        <i class="bi bi-trash"></i> <?php echo ('delete'); ?>
                                    </button>
                                    
                                  <?php if ($med['quantity_in_stock'] <= 0): ?>                                        <button class="btn btn-sm btn-warning" onclick="showAISuggestion(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars($med['category']); ?>')">
                                            <i class="bi bi-robot"></i> <?php echo ('need_help'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- AI Suggestion Row -->
                            <tr id="ai-row-<?php echo $med['id']; ?>" style="display: none;">
                                <td colspan="6">
                                    <div class="ai-box">
                                        <h6><i class="bi bi-robot me-2"></i><?php echo ('ai_suggestion'); ?>:</h6>
                                        <div id="ai-content-<?php echo $med['id']; ?>"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Medication Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i><?php echo ('add_medication'); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../../controllers/inventory_handler.php">
                    <input type="hidden" name="action" value="add_medication">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label"><?php echo ('name'); ?> *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Pain Relief">Pain Relief</option>
                                <option value="Antibiotic">Antibiotic</option>
                                <option value="Diabetes">Diabetes</option>
                                <option value="Hypertension">Hypertension</option>
                                <option value="Gastric">Gastric</option>
                                <option value="Respiratory">Respiratory</option>
                                <option value="Allergy">Allergy</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo ('quantity'); ?> *</label>
<input type="number" class="form-control" name="quantity" min="0" oninput="this.value = Math.max(0, this.value)" required>                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo ('price'); ?> (ILS) *</label>
                            <input type="number" class="form-control" name="unit_price" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo ('cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo ('save'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Medication Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i><?php echo ('edit'); ?> Medication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../../controllers/inventory_handler.php">
                    <input type="hidden" name="action" value="update_medication">
                    <input type="hidden" name="medication_id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label"><?php echo ('name'); ?> *</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" id="edit_category" required>
                                <option value="Pain Relief">Pain Relief</option>
                                <option value="Antibiotic">Antibiotic</option>
                                <option value="Diabetes">Diabetes</option>
                                <option value="Hypertension">Hypertension</option>
                                <option value="Gastric">Gastric</option>
                                <option value="Respiratory">Respiratory</option>
                                <option value="Allergy">Allergy</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo ('quantity'); ?> *</label>
<input type="number" class="form-control" name="quantity" id="edit_quantity" min="0" oninput="this.value = Math.max(0, this.value)" required>                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo ('price'); ?> (ILS) *</label>
                            <input type="number" class="form-control" name="unit_price" id="edit_price" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo ('cancel'); ?></button>
                        <button type="submit" class="btn btn-warning"><?php echo ('save'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#medicationsTable tbody tr:not([id^="ai-row"])');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Edit medication
        function editMedication(med) {
            document.getElementById('edit_id').value = med.id;
            document.getElementById('edit_name').value = med.name;
            document.getElementById('edit_category').value = med.category;
            document.getElementById('edit_quantity').value = med.quantity_in_stock;
            document.getElementById('edit_price').value = med.unit_price;
            document.getElementById('edit_description').value = med.description || '';
            
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }

        // Confirm delete
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"?`)) {
                window.location.href = `../../controllers/inventory_handler.php?action=delete_medication&id=${id}`;
            }
        }

        // Show AI Suggestion
        function showAISuggestion(medId, category) {
            const aiRow = document.getElementById('ai-row-' + medId);
            const aiContent = document.getElementById('ai-content-' + medId);
            
            if (aiRow.style.display === 'none') {
                aiRow.style.display = 'table-row';
                aiContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">AI is analyzing inventory...</p></div>';
                
                // Simulate AI processing
                setTimeout(() => {
                    fetch(`../../api/get_similar_medications.php?category=${category}&exclude_id=${medId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.medications && data.medications.length > 0) {
                                let html = '<p><i class="bi bi-lightbulb text-warning me-2"></i><strong><?php echo ("similar_medication"); ?>:</strong></p><ul class="list-group">';
                                data.medications.forEach(med => {
                                    html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${med.name}</strong>
                                            <br><small class="text-muted">${med.category}</small>
                                        </div>
                                        <div>
                                            <span class="badge bg-success">${med.quantity_in_stock} units</span>
                                            <span class="badge bg-primary">${med.unit_price} ILS</span>
                                        </div>
                                    </li>`;
                                });
                                html += '</ul>';
                                aiContent.innerHTML = html;
                            } else {
                                aiContent.innerHTML = '<p class="text-danger"><i class="bi bi-exclamation-circle me-2"></i>No similar medications found in stock.</p>';
                            }
                        })
                        .catch(error => {
                            aiContent.innerHTML = '<p class="text-danger">Error loading suggestions.</p>';
                        });
                }, 1500);
            } else {
                aiRow.style.display = 'none';
            }
        }
    </script>
</body>
</html>
