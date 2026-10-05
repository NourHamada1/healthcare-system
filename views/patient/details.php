<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../login.php?type=patient");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../includes/language.php';

$database = new Database();
$db = $database->getConnection();
$user_model = new User($db);

$user_id = $_SESSION['user_id'];
$user_data = $user_model->getUserById($user_id);

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('medical_information'); ?></title>
    <?php if (($_SESSION['lang'] ?? 'ar') === 'ar'): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 0; }
        .details-card { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .form-control:focus, .form-check-input:focus { border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25); }
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
                <div class="details-card">
                    <div class="card-header bg-primary text-white p-4" style="border-radius: 20px 20px 0 0;">
                        <h3 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i><?php echo __('medical_information'); ?></h3>
                        <p class="mb-0 opacity-75"><?php echo __('tell_us_health'); ?></p>
                    </div>
                    
                    <div class="card-body p-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle me-2"></i>
                                <?php echo __('appointment_booked_success'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="../../controllers/patient_handler.php">
                            <input type="hidden" name="action" value="create_appointment">
                            
                            <div class="mb-4 form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="is_chronic" name="is_chronic">
                                <label class="form-check-label fw-bold" for="is_chronic">
                                    <i class="bi bi-heart-pulse me-2"></i><?php echo __('i_have_chronic_diseases'); ?>
                                </label>
                            </div>
                            
                            <?php if ($user_data['gender'] == 'female' && $user_data['age'] >= 18 && $user_data['age'] <= 50): ?>
                            <div class="mb-4 form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="is_pregnant" name="is_pregnant">
                                <label class="form-check-label fw-bold" for="is_pregnant">
                                    <i class="bi bi-heart me-2"></i><?php echo __('i_am_pregnant'); ?>
                                </label>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-file-medical me-2"></i><?php echo __('other_diseases_if_any'); ?>
                                </label>
                                <textarea class="form-control" name="other_diseases" rows="3" 
                                          placeholder="<?php echo __('other_diseases_placeholder'); ?>"></textarea>
                                <small class="text-muted"><?php echo __('other_diseases_example'); ?></small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-thermometer me-2"></i><?php echo __('current_symptoms_conditions'); ?>
                                </label>
                                <textarea class="form-control" name="current_conditions" rows="6" required
                                          placeholder="<?php echo __('symptoms_placeholder'); ?>"></textarea>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <?php echo __('symptoms_hint'); ?>
                                </small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-robot me-2"></i>
                                <strong><?php echo __('ai_assistant'); ?>:</strong> <?php echo __('ai_assistant_statement'); ?>
                                <ul class="mb-0 mt-2">
                                    <li><?php echo __('ai_recommend_department'); ?></li>
                                    <li><?php echo __('ai_assign_doctor'); ?></li>
                                    <li><?php echo __('ai_calculate_priority'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle me-2"></i><?php echo __('book_appointment_now'); ?>
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i><?php echo __('back_to_dashboard'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>