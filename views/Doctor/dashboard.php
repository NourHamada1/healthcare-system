<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: ../login.php?type=doctor");
    exit();
}

// FIXED PATHS - Use absolute path from project root
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Patient.php';
require_once __DIR__ . '/../../models/Queue.php';
require_once __DIR__ . '/../../models/Prescription.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/language.php';


$database = new Database();
$db = $database->getConnection();
$patient_model = new Patient($db);
$queue_model = new Queue($db);
$prescription_model = new Prescription($db);

$queue = $queue_model->getAllWaitingPatients();
$next_patient = !empty($queue) ? $queue[0] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_consultation'])) {
    $prescription_model->patient_id = $_POST['patient_id'];
    $prescription_model->doctor_id = $_SESSION['user_id'];
    $prescription_model->medication = htmlspecialchars($_POST['medication']);
    $prescription_model->notes = htmlspecialchars($_POST['notes']);
    $prescription_model->consultation_duration = intval($_POST['duration']);
    
    if ($prescription_model->create()) {
        $patient_model->updateStatus($_POST['patient_id'], 'completed');
        header("Location: dashboard.php?success=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'ar'; ?>" dir="<?php echo ($_SESSION['lang'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('doctor_dashboard'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f8f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-doctor { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .patient-card { background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); border-left: 5px solid #00acc1; }
        .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 10px; margin-bottom: 15px; }
        .ai-suggestion { background: #d1ecf1; border-left: 4px solid #0dcaf0; padding: 15px; border-radius: 10px; margin-bottom: 15px; }
        .btn-complete { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; color: white; padding: 15px 30px; font-size: 1.1rem; border-radius: 10px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-doctor navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-hospital me-2"></i><?php echo __('doctor_dashboard'); ?> - د. <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div>
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
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?php echo __('consultation_completed_sent'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-people display-4 text-success mb-3"></i>
                        <h5 class="text-muted"><?php echo __('patients_waiting'); ?></h5>
                        <h2 class="display-4 fw-bold text-success"><?php echo count($queue); ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <?php if ($next_patient): ?>
                <div class="card patient-card">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-person-fill me-2"></i><?php echo __('current_patient'); ?>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">
                                    <i class="bi bi-person-badge me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($next_patient['full_name']); ?>
                                </h5>
                                <table class="table table-sm">
                                    <tr>
                                        <td class="fw-bold"><i class="bi bi-card-text me-2"></i><?php echo __('national_id'); ?>:</td>
                                        <td><?php echo htmlspecialchars($next_patient['national_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold"><i class="bi bi-calendar me-2"></i><?php echo __('age'); ?>:</td>
                                        <td><?php echo $next_patient['age']; ?> <?php echo __('years' ?? 'سنوات'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold"><i class="bi bi-gender-ambiguous me-2"></i><?php echo __('gender'); ?>:</td>
                                        <td><?php echo __($next_patient['gender']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <p class="mb-2">
                                        <i class="bi bi-shield-check me-2"></i>
                                        <strong><?php echo __('chronic'); ?>:</strong> <?php echo $next_patient['is_chronic'] ? __('yes') : __('no'); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="bi bi-heart-pulse me-2"></i>
                                        <strong><?php echo __('pregnant'); ?>:</strong> <?php echo $next_patient['is_pregnant'] ? __('yes') : __('no'); ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong><?php echo __('critical'); ?>:</strong> <?php echo $next_patient['is_critical'] ? __('yes') : __('no'); ?>
                                    </p>
                                </div>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-flag me-1"></i><?php echo __('priority'); ?>: <?php echo $next_patient['priority']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-bold text-primary">
                                <i class="bi bi-clipboard2-pulse me-2"></i><?php echo __('current_symptoms'); ?>:
                            </h6>
                            <div class="alert alert-light border">
                                <?php echo nl2br(htmlspecialchars($next_patient['current_conditions'])); ?>
                            </div>
                        </div>

                        <?php if ($next_patient['other_diseases']): ?>
                        <div class="mb-3">
                            <h6 class="fw-bold text-danger">
                                <i class="bi bi-file-medical me-2"></i><?php echo __('other_diseases'); ?>:
                            </h6>
                            <div class="alert alert-warning border">
                                <?php echo nl2br(htmlspecialchars($next_patient['other_diseases'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="ai-suggestion">
                            <h6 class="fw-bold">
                                <i class="bi bi-robot me-2"></i><?php echo __('ai_treatment_suggestion'); ?>:
                            </h6>
                            <p class="mb-0">
                                <?php echo getMedicationSuggestion($next_patient['current_conditions']); ?>
                            </p>
                        </div>

                        <hr>
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="patient_id" value="<?php echo $next_patient['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-capsule me-2"></i><?php echo __('prescribed_medication'); ?> *
                                </label>
                                <textarea class="form-control" name="medication" rows="3" required 
                                          placeholder="<?php echo __('medication_placeholder'); ?>"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-journal-medical me-2"></i><?php echo __('doctors_notes'); ?>
                                </label>
                                <textarea class="form-control" name="notes" rows="4" 
                                          placeholder="<?php echo __('notes_placeholder'); ?>"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="bi bi-clock me-2"></i><?php echo __('consultation_duration'); ?>
                                    </label>
                                    <input type="number" class="form-control" name="duration" value="15" min="5" max="120" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="complete_consultation" class="btn btn-complete btn-lg">
                                    <i class="bi bi-check-circle me-2"></i><?php echo __('complete_and_send'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted mb-4"></i>
                        <h4 class="text-muted"><?php echo __('no_patients_queue'); ?></h4>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (count($queue) > 1): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul me-2"></i><?php echo __('upcoming_patients'); ?> (<?php echo count($queue) - 1; ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo __('name'); ?></th>
                                    <th><?php echo __('age'); ?></th>
                                    <th><?php echo __('priority'); ?></th>
                                    <th><?php echo __('condition'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($queue, 1, 5) as $patient): 
                                    $prio_class = '';
                                    $badge_class = 'bg-secondary';
                                    switch($patient['priority']) {
                                        case 1: $prio_class = 'table-danger'; $badge_class = 'bg-danger'; break;
                                        case 2: $prio_class = 'table-warning'; $badge_class = 'bg-warning text-dark'; break;
                                        case 3: $prio_class = 'table-warning'; $badge_class = 'bg-warning text-dark'; break;
                                        case 4: $prio_class = 'table-info'; $badge_class = 'bg-info text-dark'; break;
                                        case 5: $prio_class = 'table-primary'; $badge_class = 'bg-primary'; break;
                                    }
                                ?>
                                <tr class="<?php echo $prio_class; ?>">
                                    <td class="fw-bold"><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                    <td><?php echo $patient['age']; ?></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $patient['priority']; ?></span></td>
                                    <td><?php echo substr($patient['current_conditions'], 0, 50) . '...'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh queue every 30 seconds, but ONLY if the doctor is not typing
        setInterval(function() {
            let medInput = document.querySelector('textarea[name="medication"]');
            let notesInput = document.querySelector('textarea[name="notes"]');
            
            // If the inputs don't exist (no active patient) or both are completely empty, it's safe to refresh
            let canRefresh = (!medInput && !notesInput) || 
                             (medInput && medInput.value.trim() === '' && notesInput && notesInput.value.trim() === '');
            
            if (canRefresh) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>