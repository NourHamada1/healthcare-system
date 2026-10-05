<?php
// config/ai_config.php
// Copy this file to ai_config.php and add your API key

define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');

// Gemini API endpoint
define('AI_MODEL', 'gemini-2.5-flash-lite');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_MODEL . ':generateContent');
?>
