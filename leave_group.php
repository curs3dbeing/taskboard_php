<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
if (!$pdo) {
    header('Location: groups.php?message=' . urlencode('Ошибка подключения к базе данных.'));
    exit;
}

$user_id = getCurrentUserId();
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    header('Location: groups.php?message=' . urlencode('Неверный ID группы.'));
    exit;
}

try {
    // Проверяем, является ли пользователь участником группы и не является ли владельцем
    $stmt = $pdo->prepare("SELECT g.owner_id, g.name FROM user_groups g WHERE g.id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    
    if (!$group) {
        header('Location: groups.php?message=' . urlencode('Группа не найдена.'));
        exit;
    }
    
    // Проверяем, что пользователь не является владельцем
    if ($group['owner_id'] == $user_id) {
        header('Location: groups.php?message=' . urlencode('Владелец группы не может покинуть группу. Используйте функцию удаления группы.'));
        exit;
    }
    
    // Проверяем, является ли пользователь участником группы
    $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    if (!$stmt->fetch()) {
        header('Location: groups.php?message=' . urlencode('Вы не являетесь участником этой группы.'));
        exit;
    }
    
    // Удаляем пользователя из участников группы
    $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: groups.php?message=' . urlencode('Вы успешно покинули группу "' . htmlspecialchars($group['name']) . '"!'));
    } else {
        header('Location: groups.php?message=' . urlencode('Ошибка при выходе из группы.'));
    }
} catch (PDOException $e) {
    error_log("Leave group error: " . $e->getMessage());
    header('Location: groups.php?message=' . urlencode('Произошла ошибка при выходе из группы.'));
}
exit;



