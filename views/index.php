<?php require_once __DIR__ . '/../includes/language.php'; ?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('healthcare_system'); ?></title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card-custom {
            border: none;
            border-radius: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            background: white;
        }
        .card-custom:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .icon-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 50px;
        }
        .patient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .doctor-bg { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .pharmacy-bg { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); }
        .header-title {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 50px;
            position: relative;
        }
        .lang-switcher {
            position: absolute;
            top: 20px;
            <?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'left: 20px;' : 'right: 20px;'; ?>
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-title text-center text-white">
            <div class="lang-switcher">
                <?php if (($_SESSION['lang'] ?? 'ar') === 'en'): ?>
                    <a href="../api/change_language.php?lang=ar" class="btn btn-outline-light btn-sm">عربي</a>
                <?php else: ?>
                    <a href="../api/change_language.php?lang=en" class="btn btn-outline-light btn-sm">English</a>
                <?php endif; ?>
            </div>
            <h1 class="display-3 fw-bold mb-3">
                <i class="bi bi-hospital"></i> <?php echo __('healthcare_system'); ?>
            </h1>
            <p class="lead"><?php echo __('ai_powered_platform'); ?></p>
        </div>

        <div class="row g-4">
            <!-- Patient Card -->
            <div class="col-md-4">
                <div class="card card-custom" onclick="location.href='login.php?type=patient'">
                    <div class="card-body text-center p-5">
                        <div class="icon-circle patient-bg text-white">
                            <i class="bi bi-person-heart"></i>
                        </div>
                        <h3 class="fw-bold mb-3"><?php echo __('patient'); ?></h3>
                        <p class="text-muted mb-4"><?php echo __('patient_desc'); ?></p>
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge bg-primary"><?php echo __('ai_diagnosis'); ?></span>
                            <span class="badge bg-primary"><?php echo __('priority_queue'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Doctor Card -->
            <div class="col-md-4">
                <div class="card card-custom" onclick="location.href='login.php?type=doctor'">
                    <div class="card-body text-center p-5">
                        <div class="icon-circle doctor-bg text-white">
                            <i class="bi bi-hospital"></i>
                        </div>
                        <h3 class="fw-bold mb-3"><?php echo __('doctor'); ?></h3>
                        <p class="text-muted mb-4"><?php echo __('doctor_desc'); ?></p>
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge bg-success"><?php echo __('smart_queue'); ?></span>
                            <span class="badge bg-success"><?php echo __('ai_assistant'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pharmacist Card -->
            <div class="col-md-4">
                <div class="card card-custom" onclick="location.href='login.php?type=pharmacist'">
                    <div class="card-body text-center p-5">
                        <div class="icon-circle pharmacy-bg text-white">
                            <i class="bi bi-capsule"></i>
                        </div>
                        <h3 class="fw-bold mb-3"><?php echo __('pharmacist'); ?></h3>
                        <p class="text-muted mb-4"><?php echo __('pharmacist_desc'); ?></p>
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge bg-warning"><?php echo __('inventory'); ?></span>
                            <span class="badge bg-warning"><?php echo __('e_payment'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <p class="text-white">
                <i class="bi bi-shield-check"></i> <?php echo __('secure'); ?> • 
                <i class="bi bi-lightning-charge"></i> <?php echo __('fast'); ?> • 
                <i class="bi bi-cpu"></i> <?php echo __('ai_powered'); ?>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0ist/js/bootstrap.bundle.min.js"></script>
</body>
</html>