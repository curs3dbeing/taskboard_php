<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных.']);
    exit;
}

$user_id = getCurrentUserId();
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$member_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if (!$group_id || !$member_user_id) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры.']);
    exit;
}

try {

    $stmt = $pdo->prepare("SELECT owner_id FROM user_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Группа не найдена.']);
        exit;
    }
    
    if ($group['owner_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'У вас нет прав для удаления участников из этой группы.']);
        exit;
    }
    

    $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $member_user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Пользователь успешно удален из группы.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден в группе.']);
    }
} catch (PDOException $e) {
    error_log("Remove member error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Произошла ошибка при удалении пользователя.']);
}
exit;

