<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php?type=patient");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();
$queue_model = new Queue($db);

$user_id = $_SESSION['user_id'];
$queue_position = $queue_model->getPatientPosition($user_id);
$status = $queue_model->getPatientStatus($user_id);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('waiting_room'); ?></title>
    <?php if (($_SESSION['lang'] ?? 'ar') === 'ar'): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <meta http-equiv="refresh" content="30">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .waiting-card { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .lang-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        [dir="rtl"] .lang-switcher { right: auto; left: 20px; }
    </style>
</head>
<body>
    <div class="lang-switcher">
        <?php if (($_SESSION['lang'] ?? 'ar') === 'en'): ?>
            <a href="../../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm text-white border-white">عربي</a>
        <?php else: ?>
            <a href="../../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm text-white border-white">English</a>
        <?php endif; ?>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="waiting-card">
                    <div class="card-body text-center p-5">
                        <div class="text-success mb-4">
                            <i class="bi bi-check-circle display-1 pulse"></i>
                        </div>
                        
                        <h2 class="mb-3"><?php echo __('appointment_confirmed'); ?></h2>
                        <p class="text-muted mb-4"><?php echo __('please_wait_turn'); ?></p>
                        
                        <div class="alert alert-primary mb-4">
                            <h4 class="mb-2"><?php echo __('patients_ahead_of_you'); ?></h4>
                            <h1 class="display-1 fw-bold"><?php echo $queue_position; ?></h1>
                            <p class="mb-0"><?php echo sprintf(__('estimated_wait'), $queue_position * 15); ?></p>
                        </div>
                        
                        <?php if ($status): ?>
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <p class="mb-2"><strong><?php echo __('department'); ?>:</strong> <?php echo __($status['suggested_department']); ?></p>
                                <p class="mb-0"><strong><?php echo __('doctor'); ?>:</strong> <?php echo htmlspecialchars($status['suggested_doctor']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="mb-3"><?php echo __('priority_order'); ?></h5>
                                <ol class="text-start">
                                    <li><span class="badge bg-danger">1</span> <?php echo __('priority_1_critical'); ?></li>
                                    <li><span class="badge bg-warning text-dark">2</span> <?php echo __('priority_2_pregnant'); ?></li>
                                    <li><span class="badge bg-warning text-dark">3</span> <?php echo __('priority_3_elderly_chronic'); ?></li>
                                    <li><span class="badge bg-info text-dark">4</span> <?php echo __('priority_4_chronic'); ?></li>
                                    <li><span class="badge bg-primary">5</span> <?php echo __('priority_5_regular'); ?></li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-2"></i><?php echo __('back_to_dashboard'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>