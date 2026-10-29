<?php
$local = is_file(__DIR__ . '/config_ai.local.php')
    ? require __DIR__ . '/config_ai.local.php'
    : [];

define('AI_API_KEY', (string) ($local['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: ''));
const SESSION_USER_KEY = 'user_id';
const AI_MODEL         = 'gemini-3.5-flash-lite';