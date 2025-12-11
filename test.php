<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Railway PHP Diagnostic</h1>";


echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "✓ PHP работает<br><br>";


echo "<h2>2. Environment Variables</h2>";
$port = getenv('PORT');
echo "PORT: " . ($port ?: 'NOT SET') . "<br>";
echo "RAILWAY_ENVIRONMENT: " . (getenv('RAILWAY_ENVIRONMENT') ?: 'NOT SET') . "<br>";
echo "RAILWAY_PUBLIC_DOMAIN: " . (getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'NOT SET') . "<br><br>";


echo "<h2>3. File Check</h2>";
$files = ['config.php', 'index.php', 'login.php', 'dashboard.php'];
foreach ($files as $file) {
    $exists = file_exists($file);
    echo ($exists ? "✓" : "✗") . " $file " . ($exists ? "exists" : "MISSING") . "<br>";
}
echo "<br>";


echo "<h2>4. Database Connection</h2>";
try {
    require_once 'config.php';
    echo "✓ config.php загружен<br>";
    
    $pdo = getDBConnection();
    echo "✓ Подключение к базе данных успешно<br>";
    

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Таблицы в базе: " . (empty($tables) ? "НЕТ (нужно запустить init_db.php)" : implode(", ", $tables)) . "<br>";
} catch (Exception $e) {
    echo "✗ Ошибка: " . htmlspecialchars($e->getMessage()) . "<br>";
}
echo "<br>";


echo "<h2>5. PHPMailer Check</h2>";
$phpmailerPath = __DIR__ . '/PHPMailer/PHPMailer.php';
if (file_exists($phpmailerPath)) {
    echo "✓ PHPMailer найден<br>";
} else {
    echo "✗ PHPMailer НЕ найден (путь: $phpmailerPath)<br>";
    echo "  Проверьте, что папка PHPMailer загружена в репозиторий<br>";
}
echo "<br>";


echo "<h2>6. Session Check</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "✓ Сессии работают<br>";
echo "Session ID: " . session_id() . "<br><br>";


echo "<h2>7. File Permissions</h2>";
$writable = is_writable(__DIR__);
echo "Директория доступна для записи: " . ($writable ? "✓ Да" : "✗ Нет") . "<br><br>";


echo "<h2>8. Final Status</h2>";
echo "<p style='color: green; font-weight: bold;'>Если все проверки пройдены, приложение должно работать.</p>";
echo "<p>Если есть ошибки, проверьте логи в Railway Dashboard.</p>";


