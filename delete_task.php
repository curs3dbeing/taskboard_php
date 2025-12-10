<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
$user_id = getCurrentUserId();

$task_id = $_GET['id'] ?? null;

if (!$task_id) {
    header('Location: dashboard.php?message=' . urlencode('Неверный ID задачи.'));
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$task_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: dashboard.php?message=' . urlencode('Задача успешно удалена!'));
    } else {
        header('Location: dashboard.php?message=' . urlencode('Задача не найдена или у вас нет прав.'));
    }
} catch (PDOException $e) {
    header('Location: dashboard.php?message=' . urlencode('Произошла ошибка. Пожалуйста, попробуйте еще раз.'));
}
exit;

