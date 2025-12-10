<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
$user_id = getCurrentUserId();

$task_id = $_POST['task_id'] ?? null;
$completed = $_POST['completed'] ?? 0;

if (!$task_id) {
    header('Location: dashboard.php?message=' . urlencode('Неверный ID задачи.'));
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tasks SET completed = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$completed ? 1 : 0, $task_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        $status = $completed ? 'выполнена' : 'не выполнена';
        header('Location: dashboard.php?message=' . urlencode("Задача отмечена как {$status}!"));
    } else {
        header('Location: dashboard.php?message=' . urlencode('Задача не найдена или у вас нет прав.'));
    }
} catch (PDOException $e) {
    header('Location: dashboard.php?message=' . urlencode('Произошла ошибка, попробуйте еще раз.'));
}
exit;

