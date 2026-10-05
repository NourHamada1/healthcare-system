<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php?type=patient");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Fetch full history
// Grouping patient triage info with prescription info if it exists
$history_query = "SELECT 
                    p.id as visit_id, 
                    p.created_at as visit_date, 
                    p.current_conditions, 
                    p.suggested_department,
                    p.suggested_doctor as triaged_doctor,
                    pr.medication, 
                    pr.notes as doctor_notes,
                    doc_u.full_name as treating_doctor
                  FROM patients p 
                  LEFT JOIN prescriptions pr ON p.id = pr.patient_id 
                  LEFT JOIN users doc_u ON pr.doctor_id = doc_u.id
                  WHERE p.user_id = :user_id 
                  ORDER BY p.created_at DESC";

$stmt = $db->prepare($history_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$full_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GHC - <?php echo __('view_full_history'); ?></title>
    <?php if (($_SESSION['lang'] ?? 'ar') === 'ar'): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .history-card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 4px solid #667eea; }
        .history-card .date-badge { background: #e3e8fc; color: #667eea; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-heart-pulse-fill me-2"></i>GHC - <?php echo __('view_full_history'); ?>
            </span>
            <div class="d-flex align-items-center">
                <?php if (($_SESSION['lang'] ?? 'ar') === 'en'): ?>
                    <a href="../../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm me-2">عربي</a>
                <?php else: ?>
                    <a href="../../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm me-2">English</a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-return-left me-1"></i> <?php echo __('dashboard' ?? 'Dashboard'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0"><i class="bi bi-journal-medical text-primary me-2"></i><?php echo __('view_full_history'); ?></h2>
            <span class="text-muted"><i class="bi bi-file-earmark-medical me-1"></i> Total Visits: <?php echo count($full_history); ?></span>
        </div>

        <?php if (empty($full_history)): ?>
            <div class="text-center py-5 bg-white rounded shadow-sm">
                <i class="bi bi-inbox text-muted display-1"></i>
                <p class="text-muted mt-3">No medical history found.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($full_history as $visit): ?>
                <div class="col-12">
                    <div class="card history-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1 text-primary">
                                        <i class="bi bi-hospital me-2"></i><?php echo __($visit['suggested_department'] ?? 'General'); ?>
                                    </h5>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-person-badge me-1"></i>Doctor: 
                                        <strong><?php echo htmlspecialchars($visit['treating_doctor'] ?: $visit['triaged_doctor']); ?></strong>
                                    </p>
                                </div>
                                <span class="date-badge">
                                    <i class="bi bi-calendar3 me-1"></i><?php echo date('F j, Y, g:i a', strtotime($visit['visit_date'])); ?>
                                </span>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <h6 class="fw-bold"><i class="bi bi-exclamation-circle text-warning me-2"></i>Reported Symptoms</h6>
                                    <p class="bg-light p-3 rounded border mb-0" style="min-height: 80px;">
                                        <?php echo nl2br(htmlspecialchars($visit['current_conditions'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold"><i class="bi bi-capsule text-success me-2"></i>Prescription & Notes</h6>
                                    <?php if ($visit['medication']): ?>
                                        <div class="bg-light p-3 rounded border mb-0" style="min-height: 80px;">
                                            <p class="mb-2 text-success fw-bold"><?php echo nl2br(htmlspecialchars($visit['medication'])); ?></p>
                                            <?php if ($visit['doctor_notes']): ?>
                                                <small class="text-muted d-block border-top pt-2 mt-2">
                                                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($visit['doctor_notes'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light p-3 rounded border text-muted d-flex align-items-center justify-content-center" style="min-height: 80px;">
                                            <i class="bi bi-hourglass-split me-2"></i> Pending / No prescription
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
