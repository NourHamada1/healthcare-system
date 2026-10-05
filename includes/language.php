<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lang = $_SESSION['lang'] ?? 'ar';

$translations_file = __DIR__ . "/../translations/{$lang}.json";
$translations = [];

if (file_exists($translations_file)) {
    $data = json_decode(file_get_contents($translations_file), true);
    $translations = $data[$lang . '_translations'] ?? $data ?? [];
} else {
    // Fallback to arabic if file not found
    $fallback_file = __DIR__ . "/../translations/ar.json";
    if (file_exists($fallback_file)) {
        $data = json_decode(file_get_contents($fallback_file), true);
        $translations = $data['ar_translations'] ?? $data ?? [];
    }
}

if (!function_exists('__')) {
    function __($key) {
        global $translations;
        return $translations[$key] ?? $key;
    }
}
?>