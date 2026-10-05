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
    <title><?php echo __('login'); ?> - <?php echo __(strtolower($user_type)); ?></title>
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
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            text-align: center;
            color: white;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
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
            <div class="col-md-6 col-lg-5">
                <div class="login-card">
                    <div class="login-header">
                        <i class="bi bi-shield-lock display-4 mb-3"></i>
                        <h2 class="mb-2"><?php echo __('welcome_back'); ?></h2>
                        <p class="mb-0 opacity-75"><?php echo __('login_as'); ?> <?php echo __(strtolower($user_type)); ?></p>
                    </div>
                    
                    <div class="p-5">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-4">
                            <i class="bi bi-arrow-left"></i> <?php echo __('back_to_home'); ?>
                        </a>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="../controllers/auth_handler.php">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-person-badge me-2"></i><?php echo __('national_id'); ?>
                                </label>
                                <input type="text" class="form-control form-control-lg" 
                                       name="national_id" placeholder="<?php echo __('enter_national_id'); ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-lock me-2"></i><?php echo __('password'); ?>
                                </label>
                                <input type="password" class="form-control form-control-lg" 
                                       name="password" placeholder="<?php echo __('enter_password'); ?>" required>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-login btn-lg text-white">
                                    <i class="bi bi-box-arrow-in-right me-2"></i><?php echo __('login'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center">
                            <p class="text-muted mb-0">
                                <?php echo __('dont_have_account'); ?> 
                                <a href="register.php?type=<?php echo $user_type; ?>" class="text-decoration-none fw-bold">
                                    <?php echo __('register_here'); ?>
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
