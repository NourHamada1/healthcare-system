<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['full_name'] ?? 'User';
}

function checkUserType($required_type) {
    if (!isLoggedIn() || getUserType() !== $required_type) {
        header("Location: ../login.php?type=$required_type");
        exit();
    }
}
?>