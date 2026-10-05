<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$user_model = new User($db);

// =================================================================
// LOGIN HANDLER
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $national_id = htmlspecialchars(strip_tags($_POST['national_id']));
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    // Attempt login
    $result = $user_model->login($national_id, $password);
    
    if (!$result) {
        header("Location: ../views/login.php?type=$user_type&error=" . urlencode("Invalid National ID or Password"));
        exit();
    }
    
    if ($result['user_type'] !== $user_type) {
        header("Location: ../views/login.php?type=$user_type&error=" . urlencode("Invalid user type. Please select correct login option."));
        exit();
    }
    
    // Set session
    $_SESSION['user_id'] = $result['id'];
    $_SESSION['user_type'] = $result['user_type'];
    $_SESSION['full_name'] = $result['full_name'];
    
    // Log action
    logAction($db, $result['id'], 'LOGIN_SUCCESS', "User logged in successfully");
    
    // Redirect to appropriate dashboard
    $redirect_map = [
        'patient' => '../views/patient/dashboard.php',
        'doctor' => '../views/doctor/dashboard.php',
        'pharmacist' => '../views/pharmacy/dashboard.php'
    ];
    
    header("Location: " . $redirect_map[$result['user_type']]);
    exit();
}

// =================================================================
// REGISTER HANDLER
// =================================================================
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $user_type = $_POST['user_type'];
    
    // Validate passwords match
    if ($_POST['password'] !== $_POST['confirm_password']) {
        header("Location: ../views/register.php?type=$user_type&error=" . urlencode("Passwords do not match"));
        exit();
    }
    
    // Validate password length
    if (strlen($_POST['password']) < 6) {
        header("Location: ../views/register.php?type=$user_type&error=" . urlencode("Password must be at least 6 characters"));
        exit();
    }

    // Validate date of birth
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $dob_timestamp = strtotime($date_of_birth);
    if (!$date_of_birth || $dob_timestamp === false || $dob_timestamp > time()) {
        header("Location: ../views/register.php?type=$user_type&error=" . urlencode("Please enter a valid date of birth"));
        exit();
    }
    
    // Check if National ID already exists
    $check_query = "SELECT COUNT(*) as count FROM users WHERE national_id = :national_id";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':national_id', $_POST['national_id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        header("Location: ../views/register.php?type=$user_type&error=" . urlencode("This National ID is already registered"));
        exit();
    }
    
    // Set user data
    $user_model->national_id = htmlspecialchars(strip_tags($_POST['national_id']));
    $user_model->password = $_POST['password']; // Will be hashed in model
    $user_model->full_name = htmlspecialchars(strip_tags($_POST['full_name']));
    $user_model->gender = $_POST['gender'];
    $user_model->date_of_birth = date('Y-m-d', $dob_timestamp);
    $user_model->user_type = $user_type;
    
    // Register user
    if ($user_model->register()) {
        // Set session
        $_SESSION['user_id'] = $user_model->id;
        $_SESSION['user_type'] = $user_model->user_type;
        $_SESSION['full_name'] = $user_model->full_name;

        // Create role-specific record
        if ($user_type === 'doctor') {
            $specialization = htmlspecialchars(strip_tags($_POST['specialization'] ?? 'General Practice'));
            $years_exp = intval($_POST['years_experience'] ?? 0);
            $doc_query = "INSERT INTO doctors (user_id, specialization, years_experience, is_approved) VALUES (:uid, :spec, :yexp, 1)";
            $doc_stmt = $db->prepare($doc_query);
            $doc_stmt->bindParam(':uid', $user_model->id);
            $doc_stmt->bindParam(':spec', $specialization);
            $doc_stmt->bindParam(':yexp', $years_exp);
            $doc_stmt->execute();
        } elseif ($user_type === 'pharmacist') {
            $years_exp = intval($_POST['years_experience'] ?? 0);
            $ph_query = "INSERT INTO pharmacists (user_id, years_experience, is_approved) VALUES (:uid, :yexp, 1)";
            $ph_stmt = $db->prepare($ph_query);
            $ph_stmt->bindParam(':uid', $user_model->id);
            $ph_stmt->bindParam(':yexp', $years_exp);
            $ph_stmt->execute();
        }

        // Log action
        logAction($db, $user_model->id, 'REGISTRATION_SUCCESS', "New user registered: $user_type");

        // Redirect based on user type
        if ($user_type === 'patient') {
            header("Location: ../views/patient/details.php");
        } elseif ($user_type === 'doctor') {
            header("Location: ../views/doctor/dashboard.php?new=1");
        } elseif ($user_type === 'pharmacist') {
            header("Location: ../views/pharmacy/dashboard.php?new=1");
        }
        exit();
    } else {
        header("Location: ../views/register.php?type=$user_type&error=" . urlencode("Registration failed. Please try again."));
        exit();
    }
}

// =================================================================
// LOGOUT HANDLER
// =================================================================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $user_id = $_SESSION['user_id'] ?? null;
    
    if ($user_id) {
        logAction($db, $user_id, 'LOGOUT', "User logged out");
    }
    
    session_unset();
    session_destroy();
    
    header("Location: ../views/index.php");
    exit();
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

// If no action specified, redirect to home
header("Location: ../views/index.php");
exit();
?>