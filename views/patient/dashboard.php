<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php?type=patient");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Patient.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../includes/language.php';


$database = new Database();
$db = $database->getConnection();
$patient_model = new Patient($db);
$queue_model = new Queue($db);
$user_model = new User($db);

$user_id = $_SESSION['user_id'];
$user_data = $user_model->getUserById($user_id);
$has_active = $queue_model->hasActiveAppointment($user_id);
$appointment_status = null;
$queue_position = 0;

if ($has_active) {
    $appointment_status = $queue_model->getPatientStatus($user_id);
    $queue_position = $queue_model->getPatientPosition($user_id);
}

$history_query = "SELECT p.*, pr.medication, pr.notes FROM patients p 
                  LEFT JOIN prescriptions pr ON p.id = pr.patient_id 
                  WHERE p.user_id = :user_id ORDER BY p.created_at DESC LIMIT 5";
$stmt = $db->prepare($history_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$medical_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$queue_stats = $queue_model->getQueueStatistics();
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('patient_portal'); ?></title>
    <?php if (($_SESSION['lang'] ?? 'ar') === 'ar'): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fc; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); transition: all 0.3s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 5px 25px rgba(0,0,0,0.15); }
        .stat-card { padding: 1.5rem; border-left: 4px solid #667eea; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #17a2b8; }
        .status-badge { padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; }
        .status-waiting { background: #fff3cd; color: #856404; }
        .status-in-consultation { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .priority-1 { background: #dc3545; } /* Critical */
        .priority-2 { background: #fd7e14; } /* Pregnant */
        .priority-3 { background: #ffc107; } /* Elderly Chronic */
        .priority-4 { background: #ff8c00; } /* Chronic */
        .priority-5 { background: #17a2b8; } /* Children */
        .priority-6 { background: #28a745; } /* Regular */
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-heart-pulse-fill me-2"></i><?php echo __('healthcare_system'); ?> - <?php echo __('patient_portal'); ?>
            </span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($user_data['full_name']); ?>
                </span>
                <?php if (($_SESSION['lang'] ?? 'ar') === 'en'): ?>
                    <a href="../../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm me-2">عربي</a>
                <?php else: ?>
                    <a href="../../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm me-2">English</a>
                <?php endif; ?>
                <a href="../../controllers/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> <?php echo __('logout'); ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body p-4">
                        <h3 class="mb-2"><?php echo sprintf(__('welcome_back_name'), htmlspecialchars($user_data['full_name'])); ?></h3>
                        <p class="mb-0 opacity-75">
                            <i class="bi bi-calendar3 me-2"></i><?php echo date('l, F j, Y'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2"><?php echo __('your_age'); ?></h6>
                            <h3 class="mb-0"><?php echo $user_data['age']; ?> <?php echo __('years' ?? 'سنوات'); ?></h3>
                        </div>
                        <div class="fs-1 text-primary"><i class="bi bi-person-badge"></i></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2"><?php echo __('total_visits'); ?></h6>
                            <h3 class="mb-0"><?php echo count($medical_history); ?></h3>
                        </div>
                        <div class="fs-1 text-success"><i class="bi bi-file-medical"></i></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2"><?php echo __('patients_waiting'); ?></h6>
                            <h3 class="mb-0"><?php echo intval($queue_stats['total_waiting'] ?? 0); ?></h3>
                        </div>
                        <div class="fs-1 text-warning"><i class="bi bi-people"></i></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2"><?php echo __('avg_wait_time'); ?></h6>
                            <h3 class="mb-0"><?php echo round($queue_stats['avg_waiting_time'] ?? 0); ?> <?php echo __('min'); ?></h3>
                        </div>
                        <div class="fs-1 text-info"><i class="bi bi-clock-history"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <?php if ($has_active): ?>
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-calendar-check text-primary me-2"></i><?php echo __('current_appointment_status'); ?></h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <span class="status-badge status-<?php echo $appointment_status['status']; ?>">
                                    <?php echo __('status_' . $appointment_status['status'] ?? $appointment_status['status']); ?>
                                </span>
                                <p class="text-muted mt-3 mb-2">
                                    <i class="bi bi-hospital me-2"></i>
                                    <strong><?php echo __('department'); ?>:</strong> <?php echo __($appointment_status['suggested_department'] ?? 'other'); ?>
                                </p>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-person-badge me-2"></i>
                                    <strong><?php echo __('doctor'); ?>:</strong> <?php echo htmlspecialchars($appointment_status['suggested_doctor'] ?? 'N/A'); ?>
                                </p>
                                <p class="text-muted mb-0">
                                    <span class="priority-indicator priority-<?php echo $appointment_status['priority']; ?>"></span>
                                    <strong><?php echo __('priority'); ?>:</strong> <?php echo $appointment_status['priority']; ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-center">
                                <div class="display-1 text-primary mb-2"><?php echo $queue_position; ?></div>
                                <h6 class="text-muted"><?php echo __('patients_ahead_of_you'); ?></h6>
                                <p class="text-muted small"><?php echo sprintf(__('estimated_wait'), $queue_position * 15); ?></p>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i><?php echo __('notified_when_turn'); ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body text-center p-5">
                        <i class="bi bi-calendar-plus display-1 text-muted mb-4"></i>
                        <h4 class="mb-3"><?php echo __('no_active_appointment'); ?></h4>
                        <p class="text-muted mb-4"><?php echo __('book_new_appointment_to_see_doctor'); ?></p>
                        <a href="details.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-circle me-2"></i><?php echo __('book_new_appointment'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($medical_history)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-clock-history text-primary me-2"></i><?php echo __('recent_medical_history'); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($medical_history as $visit): ?>
                        <div class="card border-0 bg-light mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6><?php echo __($visit['suggested_department']); ?></h6>
                                    <span class="badge bg-secondary"><?php echo date('M d, Y', strtotime($visit['created_at'])); ?></span>
                                </div>
                                <p class="text-muted small mb-2">
                                    <strong><?php echo __('condition_label'); ?></strong> <?php echo htmlspecialchars(substr($visit['current_conditions'], 0, 100)); ?>...
                                </p>
                                <?php if ($visit['medication']): ?>
                                <p class="text-success small mb-0">
                                    <i class="bi bi-capsule me-1"></i>
                                    <strong><?php echo __('prescribed_label'); ?></strong> <?php echo htmlspecialchars(substr($visit['medication'], 0, 80)); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-lightning text-warning me-2"></i><?php echo __('quick_actions'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (!$has_active): ?>
                            <a href="details.php" class="btn btn-primary">
                                <i class="bi bi-calendar-plus me-2"></i><?php echo __('book_appointment'); ?>
                            </a>
                            <?php else: ?>
                            <a href="waiting.php" class="btn btn-info">
                                <i class="bi bi-hourglass-split me-2"></i><?php echo __('view_waiting_room'); ?>
                            </a>
                            <?php endif; ?>
                            <a href="history.php" class="btn btn-outline-primary">
                                <i class="bi bi-file-earmark-medical me-2"></i><?php echo __('view_full_history'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-info-circle text-info me-2"></i><?php echo __('priority_levels'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3"><span class="priority-indicator priority-1"></span><?php echo __('priority_1_critical'); ?></div>
                        <div class="mb-3"><span class="priority-indicator priority-2"></span><?php echo __('priority_2_pregnant'); ?></div>
                        <div class="mb-3"><span class="priority-indicator priority-3"></span><?php echo __('priority_3_elderly_chronic'); ?></div>
                        <div class="mb-3"><span class="priority-indicator priority-4"></span><?php echo __('priority_4_chronic'); ?></div>
                        <div class="mb-3"><span class="priority-indicator priority-5"></span><?php echo __('priority_5_children'); ?></div>
                        <div class="mb-0"><span class="priority-indicator priority-6"></span><?php echo __('priority_6_regular'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($has_active && $appointment_status['status'] === 'waiting'): ?>
    <script>setTimeout(function() { location.reload(); }, 30000);</script>
    <?php endif; ?>
</body>
</html>
