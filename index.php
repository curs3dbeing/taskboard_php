<?php
// Включаем обработку ошибок для production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once 'config.php';
    
    if (isLoggedIn()) {
        header('Location: dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit;
} catch (Exception $e) {
    error_log("Index.php error: " . $e->getMessage());
    http_response_code(500);
    echo "Application error. Please check the logs.";
    exit;
}

