<?php
session_start();

if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    if (in_array($lang, ['en', 'ar'])) {
        $_SESSION['lang'] = $lang;
    }
}

// Redirect back to the referring page
$redirect_url = $_SERVER['HTTP_REFERER'] ?? '../views/index.php';
header("Location: $redirect_url");
exit();
?>
