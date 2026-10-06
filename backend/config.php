<?php
/**
 * Global Configuration & Security Setup
 */

// Strict error reporting in production-safe mode
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Secure Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'smm_panel');
define('DB_USER', 'root');
define('DB_PASS', '');

// App Constants
define('APP_NAME', 'SMM Panel');
define('DEFAULT_CURRENCY', '₹');
define('MIN_DEPOSIT', 10.00);
define('MAX_DEPOSIT', 100000.00);

// Global JSON Response Helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
