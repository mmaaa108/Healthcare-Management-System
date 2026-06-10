<?php
// config/ai_config.php
// IMPORTANT: Move this file outside web root in production!
// Copy from ai_config.example.php and add your real API key

define('GEMINI_API_KEY', 'your_api_key_here');

// Gemini API endpoint
define('AI_MODEL', 'gemini-2.5-flash-lite');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_MODEL . ':generateContent');
?>