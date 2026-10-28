<?php
$local = require __DIR__ . '/config_ai.local.php';
define('AI_API_KEY', $local['GEMINI_API_KEY']);
const SESSION_USER_KEY = 'user_id';
const AI_MODEL         = 'gemini-3.5-flash';