<?php
require_once 'config.php';

// Сессия уже запущена в config.php, не нужно вызывать session_start() снова
// Проверяем, что сессия активна перед уничтожением
if (session_status() === PHP_SESSION_ACTIVE) {
    // Очищаем все данные сессии
    $_SESSION = array();
    
    // Удаляем cookie сессии, если она используется
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Уничтожаем сессию
    session_destroy();
}

// Редирект на страницу логина
header('Location: login.php');
exit;

