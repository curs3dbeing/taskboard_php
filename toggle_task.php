<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
$user_id = getCurrentUserId();

$task_id = $_POST['task_id'] ?? null;
$completed = $_POST['completed'] ?? 0;
$group_id = isset($_POST['group_id']) && $_POST['group_id'] ? (int)$_POST['group_id'] : null;

if (!$task_id) {
    $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
    header('Location: ' . $redirect . '?message=' . urlencode('Неверный ID задачи.'));
    exit;
}

try {

    $stmt = $pdo->prepare("SELECT t.*, g.owner_id as group_owner_id 
                           FROM tasks t 
                           LEFT JOIN user_groups g ON t.group_id = g.id 
                           WHERE t.id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
        header('Location: ' . $redirect . '?message=' . urlencode('Задача не найдена.'));
        exit;
    }
    

    if ($task['group_id']) {

        $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$task['group_id'], $user_id]);
        $isMember = $stmt->fetch();
        
        if ($task['group_owner_id'] != $user_id && !$isMember) {
            header('Location: groups.php?message=' . urlencode('У вас нет прав для выполнения задач в этой группе.'));
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE tasks SET completed = ? WHERE id = ? AND group_id = ?");
        $stmt->execute([$completed ? 1 : 0, $task_id, $task['group_id']]);
    } else {

        $stmt = $pdo->prepare("UPDATE tasks SET completed = ? WHERE id = ? AND user_id = ? AND group_id IS NULL");
        $stmt->execute([$completed ? 1 : 0, $task_id, $user_id]);
    }
    
    if ($stmt->rowCount() > 0) {
        $status = $completed ? 'выполнена' : 'не выполнена';
        $redirect = $task['group_id'] ? "group_dashboard.php?group_id={$task['group_id']}" : 'dashboard.php';
        header('Location: ' . $redirect . '?message=' . urlencode("Задача отмечена как {$status}!"));
    } else {
        $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
        header('Location: ' . $redirect . '?message=' . urlencode('Задача не найдена или у вас нет прав.'));
    }
} catch (PDOException $e) {
    error_log("Toggle task error: " . $e->getMessage());
    $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
    header('Location: ' . $redirect . '?message=' . urlencode('Произошла ошибка, попробуйте еще раз.'));
}
exit;

