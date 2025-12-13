<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
$user_id = getCurrentUserId();

$task_id = $_GET['id'] ?? null;
$group_id = isset($_GET['group_id']) && $_GET['group_id'] ? (int)$_GET['group_id'] : null;

if (!$task_id) {
    $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
    header('Location: ' . $redirect . '?message=' . urlencode('Неверный ID задачи.'));
    exit;
}

try {

    $stmt = $pdo->prepare("SELECT t.* FROM tasks t WHERE t.id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
        header('Location: ' . $redirect . '?message=' . urlencode('Задача не найдена.'));
        exit;
    }
    

    // Администраторы могут удалять любые задачи, обычные пользователи - только свои
    if ($task['user_id'] != $user_id && !isAdmin()) {
        $redirect = $task['group_id'] ? "group_dashboard.php?group_id={$task['group_id']}" : 'dashboard.php';
        header('Location: ' . $redirect . '?message=' . urlencode('У вас нет прав для удаления этой задачи.'));
        exit;
    }
    
    // Администраторы могут удалять любые задачи без проверки user_id
    if (isAdmin()) {
        if ($task['group_id']) {
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND group_id = ?");
            $stmt->execute([$task_id, $task['group_id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND group_id IS NULL");
            $stmt->execute([$task_id]);
        }
    } else {
        if ($task['group_id']) {
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ? AND group_id = ?");
            $stmt->execute([$task_id, $user_id, $task['group_id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ? AND group_id IS NULL");
            $stmt->execute([$task_id, $user_id]);
        }
    }
    
    if ($stmt->rowCount() > 0) {
        $redirect = $task['group_id'] ? "group_dashboard.php?group_id={$task['group_id']}" : 'dashboard.php';
        header('Location: ' . $redirect . '?message=' . urlencode('Задача успешно удалена!'));
    } else {
        $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
        header('Location: ' . $redirect . '?message=' . urlencode('Задача не найдена или у вас нет прав.'));
    }
} catch (PDOException $e) {
    error_log("Delete task error: " . $e->getMessage());
    $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
    header('Location: ' . $redirect . '?message=' . urlencode('Произошла ошибка. Пожалуйста, попробуйте еще раз.'));
}
exit;

