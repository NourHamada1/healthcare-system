<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacist') {
    header("Location: ../login.php?type=pharmacist");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();

$institution_filter = $_GET['institution_type'] ?? '';
$where_clause = "";
$params = [];

if (!empty($institution_filter)) {
    $where_clause = "WHERE pay.institution_type = :inst";
    $params[':inst'] = $institution_filter;
}

// Ensure we only show completed payments (sales)
if (empty($where_clause)) {
    $where_clause = "WHERE pay.status = 'completed'";
} else {
    $where_clause .= " AND pay.status = 'completed'";
}

$query = "
    SELECT 
        pay.amount,
        pay.institution_type,
        pay.paid_at,
        pr.medication as doctor_prescribed_medication,
        pr.notes as doctor_notes,
        doc_u.full_name as doctor_name,
        pat_u.full_name as patient_name,
        dm.quantity,
        m.name as dispensed_medication_name,
        pharma_u.full_name as pharmacist_name
    FROM payments pay
    JOIN prescriptions pr ON pay.prescription_id = pr.id
    JOIN patients pat ON pr.patient_id = pat.id
    JOIN users pat_u ON pat.user_id = pat_u.id
    JOIN users doc_u ON pr.doctor_id = doc_u.id
    LEFT JOIN dispensed_medications dm ON pr.id = dm.prescription_id
    LEFT JOIN medications m ON dm.medication_id = m.id
    LEFT JOIN users pharma_u ON dm.dispensed_by = pharma_u.id
    $where_clause
    ORDER BY pay.paid_at DESC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('sales'); ?> - <?php echo __('pharmacist'); ?></title>
    <?php if (($_SESSION['lang'] ?? 'ar') === 'ar'): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #fff8f0; font-family: 'Segoe UI', sans-serif; }
        .navbar-pharmacy { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .doctor-notes {
            background-color: #f8f9fa;
            border-right: 4px solid #17a2b8;
            padding: 10px;
            font-size: 0.9rem;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-pharmacy navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-cart-check me-2"></i><?php echo __('sales'); ?> - <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div>
                <a href="dashboard.php" class="btn btn-light me-2">
                    <i class="bi bi-house me-1"></i><?php echo __('back_to_dashboard'); ?>
                </a>
                <?php if (($_SESSION['lang'] ?? 'ar') === 'en'): ?>
                    <a href="../../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm me-2">عربي</a>
                <?php else: ?>
                    <a href="../../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm me-2">English</a>
                <?php endif; ?>
                <a href="../../controllers/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i><?php echo __('logout'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row align-items-center">
                    <div class="col-md-auto fw-bold">
                        <i class="bi bi-funnel me-1"></i> <?php echo __('filter_by_institution'); ?>:
                    </div>
                    <div class="col-md-4">
                        <select name="institution_type" class="form-select" onchange="this.form.submit()">
                            <option value=""><?php echo __('all_institutions'); ?></option>
                            <option value="charity" <?php echo $institution_filter === 'charity' ? 'selected' : ''; ?>>Charity</option>
                            <option value="government" <?php echo $institution_filter === 'government' ? 'selected' : ''; ?>>Government</option>
                            <option value="private" <?php echo $institution_filter === 'private' ? 'selected' : ''; ?>>Private</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-table me-2"></i><?php echo __('sales'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo __('medication_name'); ?></th>
                                <th><?php echo __('quantity'); ?></th>
                                <th><?php echo __('price'); ?> (ILS)</th>
                                <th><?php echo __('institution_type'); ?></th>
                                <th>التاريخ والوقت / Date & Time</th>
                                <th>الصيدلي / Pharmacist</th>
                                <th>المريض / Patient</th>
                                <th style="width: 30%;">ملاحظات الطبيب / Doctor Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($sales)): ?>
                                <tr><td colspan="8" class="text-center text-muted">No sales found.</td></tr>
                            <?php else: ?>
                                <?php foreach($sales as $sale): 
                                    $med_name = $sale['dispensed_medication_name'] ?? $sale['doctor_prescribed_medication'];
                                    $quantity = $sale['quantity'] ?? 1;
                                    $pharma_name = $sale['pharmacist_name'] ?? 'N/A';
                                    $date_time = date('Y-m-d h:i A', strtotime($sale['paid_at']));
                                ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($med_name); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $quantity; ?></span></td>
                                        <td class="fw-bold">₪<?php echo number_format($sale['amount'], 2); ?></td>
                                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst($sale['institution_type'])); ?></span></td>
                                        <td><?php echo $date_time; ?></td>
                                        <td><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($pharma_name); ?></td>
                                        <td><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($sale['patient_name']); ?></td>
                                        <td>
                                            <div class="doctor-notes">
                                                <strong>د. <?php echo htmlspecialchars($sale['doctor_name']); ?>:</strong><br>
                                                <em>"<?php echo htmlspecialchars($sale['doctor_prescribed_medication']); ?>"</em>
                                                <?php if(!empty($sale['doctor_notes'])): ?>
                                                    <br><small class="text-muted"><?php echo nl2br(htmlspecialchars($sale['doctor_notes'])); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
