<?php
session_start();
require_once __DIR__ . '/../includes/language.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $type = $_SESSION['user_type'];
    $redirect_map = [
        'patient' => 'patient/dashboard.php',
        'doctor' => 'doctor/dashboard.php',
        'pharmacist' => 'pharmacy/dashboard.php'
    ];
    header("Location: " . $redirect_map[$type]);
    exit();
}

$user_type = $_GET['type'] ?? 'patient';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('register'); ?> - <?php echo __(strtolower($user_type)); ?></title>
    <?php if (($_SESSION['lang'] ?? 'ar') === 'ar'): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
            border-radius: 20px 20px 0 0;
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
        }
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
            <a href="../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm text-dark bg-white">عربي</a>
        <?php else: ?>
            <a href="../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm text-dark bg-white">English</a>
        <?php endif; ?>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="register-card">
                    <div class="register-header">
                        <i class="bi bi-person-plus display-4 mb-3"></i>
                        <h2 class="mb-2"><?php echo __('create_account'); ?></h2>
                        <p class="mb-0 opacity-75"><?php echo __('register_as'); ?> <?php echo __(strtolower($user_type)); ?></p>
                    </div>
                    
                    <div class="p-5">
                        <a href="login.php?type=<?php echo $user_type; ?>" class="btn btn-outline-secondary btn-sm mb-4">
                            <i class="bi bi-arrow-left"></i> <?php echo __('back_to_login'); ?>
                        </a>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="../controllers/auth_handler.php">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-person-badge me-2"></i><?php echo __('national_id'); ?> *
                                    </label>
                                    <input type="text" class="form-control" name="national_id" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-person me-2"></i><?php echo __('full_name'); ?> *
                                    </label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-gender-ambiguous me-2"></i><?php echo __('gender'); ?> *
                                    </label>
                                    <select class="form-select" name="gender" required>
                                        <option value=""><?php echo __('select_gender'); ?></option>
                                        <option value="male"><?php echo __('male'); ?></option>
                                        <option value="female"><?php echo __('female'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-calendar-event me-2"></i><?php echo __('date_of_birth'); ?> *
                                    </label>
                                    <input type="date" class="form-control" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-lock me-2"></i><?php echo __('password'); ?> *
                                    </label>
                                    <input type="password" class="form-control" name="password" minlength="6" required>
                                    <small class="text-muted"><?php echo __('password_min_length'); ?></small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-lock-fill me-2"></i><?php echo __('confirm_password'); ?> *
                                    </label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <?php if ($user_type === 'doctor'): ?>
                            <div class="row" id="doctor-fields">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-hospital me-2"></i><?php echo __('specialization'); ?> *
                                    </label>
                                    <select class="form-select" name="specialization" required>
                                        <option value="">-- <?php echo __('select_specialization'); ?> --</option>
                                        <option value="General Practice">General Practice</option>
                                        <option value="Cardiology">Cardiology</option>
                                        <option value="Dermatology">Dermatology</option>
                                        <option value="Orthopedics">Orthopedics</option>
                                        <option value="Pediatrics">Pediatrics</option>
                                        <option value="Neurology">Neurology</option>
                                        <option value="Ophthalmology">Ophthalmology</option>
                                        <option value="ENT">ENT</option>
                                        <option value="Psychiatry">Psychiatry</option>
                                        <option value="Surgery">Surgery</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-briefcase me-2"></i><?php echo __('years_experience'); ?> *
                                    </label>
                                    <input type="number" class="form-control" name="years_experience" min="0" max="50" required>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($user_type === 'pharmacist'): ?>
                            <div class="row" id="pharmacist-fields">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-briefcase me-2"></i><?php echo __('years_experience'); ?> *
                                    </label>
                                    <input type="number" class="form-control" name="years_experience" min="0" max="50" required>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-register btn-lg text-white">
                                    <i class="bi bi-check-circle me-2"></i><?php echo __('create_account'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">
                                <?php echo __('already_have_account'); ?> 
                                <a href="login.php?type=<?php echo $user_type; ?>" class="text-decoration-none fw-bold">
                                    <?php echo __('login_here'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>