<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
if (!$pdo) {
    header('Location: groups.php?message=' . urlencode('Ошибка подключения к базе данных.'));
    exit;
}

$user_id = getCurrentUserId();
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$group_id) {
    header('Location: groups.php?message=' . urlencode('Неверный ID группы.'));
    exit;
}

try {

    $stmt = $pdo->prepare("SELECT owner_id, name FROM user_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    
    if (!$group) {
        header('Location: groups.php?message=' . urlencode('Группа не найдена.'));
        exit;
    }
    
    if ($group['owner_id'] != $user_id) {
        header('Location: groups.php?message=' . urlencode('У вас нет прав для удаления этой группы.'));
        exit;
    }
    

    $stmt = $pdo->prepare("DELETE FROM user_groups WHERE id = ? AND owner_id = ?");
    $stmt->execute([$group_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: groups.php?message=' . urlencode('Группа "' . htmlspecialchars($group['name']) . '" успешно удалена!'));
    } else {
        header('Location: groups.php?message=' . urlencode('Ошибка при удалении группы.'));
    }
} catch (PDOException $e) {
    error_log("Delete group error: " . $e->getMessage());
    header('Location: groups.php?message=' . urlencode('Произошла ошибка при удалении группы.'));
}
exit;

