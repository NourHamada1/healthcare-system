<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Log the logout action
if (isset($_SESSION['user_id'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $query = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                  VALUES (:user_id, 'LOGOUT', 'User logged out', :ip_address)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Destroy session
session_unset();
session_destroy();

// Redirect to home
header("Location: ../views/index.php");
exit();
?>