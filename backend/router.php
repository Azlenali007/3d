<?php
// Built-in PHP server router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If it's a file request that exists in public folder
if ($uri !== '/' && file_exists(__DIR__ . '/../' . $uri)) {
    return false;
}

// Otherwise delegate to api.php
require __DIR__ . '/api.php';
