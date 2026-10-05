<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Queue.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: ../views/login.php?type=patient&error=" . urlencode("Please login first"));
    exit();
}

$database = new Database();
$db = $database->getConnection();
$patient_model = new Patient($db);
$queue_model = new Queue($db);
$user_model = new User($db);

$user_id = $_SESSION['user_id'];

// =================================================================
// CREATE APPOINTMENT HANDLER
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_appointment') {
    
    // Check if patient already has active appointment
    if ($queue_model->hasActiveAppointment($user_id)) {
        header("Location: ../views/patient/details.php?error=" . urlencode("You already have an active appointment"));
        exit();
    }
    
    // Validate required field
    if (empty($_POST['current_conditions'])) {
        header("Location: ../views/patient/details.php?error=" . urlencode("Please describe your symptoms"));
        exit();
    }
    
    // Get user data for priority calculation
    $user_data = $user_model->getUserById($user_id);
    
    if (!$user_data) {
        header("Location: ../views/patient/details.php?error=" . urlencode("User data not found"));
        exit();
    }
    
    // Set patient data
    $patient_model->user_id = $user_id;
    $patient_model->is_chronic = isset($_POST['is_chronic']) ? 1 : 0;
    $patient_model->other_diseases = htmlspecialchars(strip_tags($_POST['other_diseases'] ?? ''));
    $patient_model->current_conditions = htmlspecialchars(strip_tags($_POST['current_conditions']));
    $patient_model->is_pregnant = isset($_POST['is_pregnant']) ? 1 : 0;
    
    // Determine if critical based on keywords
    $conditions_lower = strtolower($_POST['current_conditions']);
    $critical_keywords = [
        'critical', 'severe', 'emergency', 'urgent', 'bleeding', 
        'chest pain', 'unconscious', 'heart attack', 'stroke',
        'difficulty breathing', 'can\'t breathe'
    ];
    
    $is_critical = false;
    foreach ($critical_keywords as $keyword) {
        if (strpos($conditions_lower, $keyword) !== false) {
            $is_critical = true;
            break;
        }
    }
    
    $patient_model->is_critical = $is_critical ? 1 : 0;
    
    // Calculate priority
    $priority_data = [
        'is_pregnant' => $patient_model->is_pregnant,
        'age' => $user_data['age'],
        'is_chronic' => $patient_model->is_chronic,
        'is_critical' => $patient_model->is_critical
    ];
    
    $patient_model->priority = calculatePriority($priority_data);
    
    // Get AI suggestion for department and doctor
    $suggestion = getAISuggestion($db, $_POST['current_conditions'], $_POST['other_diseases'] ?? '');
    $patient_model->suggested_department = $suggestion['department'];
    $patient_model->suggested_doctor = $suggestion['doctor'];
    
    // Create patient appointment
    if ($patient_model->create()) {
        // Log action
        logAction($db, $user_id, 'APPOINTMENT_CREATED', "Priority: {$patient_model->priority}, Department: {$patient_model->suggested_department}");
        
        // Redirect to waiting page
        header("Location: ../views/patient/waiting.php?success=1");
        exit();
    } else {
        header("Location: ../views/patient/details.php?error=" . urlencode("Failed to book appointment. Please try again."));
        exit();
    }
}

// Helper function to log actions
function logAction($db, $user_id, $action, $description) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $query = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                  VALUES (:user_id, :action, :description, :ip_address)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Logging error: " . $e->getMessage());
    }
}

// If no valid action, redirect to dashboard
header("Location: ../views/patient/dashboard.php");
exit();
?>
