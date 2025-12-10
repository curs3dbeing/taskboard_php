<?php
// Включаем обработку ошибок для production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Загружаем config.php без немедленного подключения к БД
require_once 'config.php';

// Проверяем подключение к БД только при необходимости
try {
    // Тестируем подключение, но не падаем если не удалось
    $testConnection = @getDBConnection();
    if (!$testConnection) {
        // Если БД не доступна, показываем информативную страницу
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Database Connection Error</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { color: #dc3545; }
            </style>
        </head>
        <body>
            <h1 class="error">Database Connection Error</h1>
            <p>The application is running, but cannot connect to the database.</p>
            <p>Please check:</p>
            <ul style="text-align: left; display: inline-block;">
                <li>MySQL service is running in Railway</li>
                <li>Database credentials in config.php</li>
                <li>Database is initialized (run init_db.php)</li>
            </ul>
            <p><a href="test.php">Run Diagnostics</a></p>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Если все ОК, продолжаем как обычно
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

