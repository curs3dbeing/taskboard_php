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
$email = trim($_POST['email'] ?? '');

if (!$group_id) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID группы.']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Введите email пользователя.']);
    exit;
}

if (!isValidEmail($email)) {
    echo json_encode(['success' => false, 'message' => 'Неверный формат email.']);
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
        echo json_encode(['success' => false, 'message' => 'У вас нет прав для добавления участников в эту группу.']);
        exit;
    }
    

    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Пользователь с таким email не найден.']);
        exit;
    }
    

    $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Пользователь уже является участником этой группы.']);
        exit;
    }
    

    if ($group['owner_id'] == $user['id']) {
        echo json_encode(['success' => false, 'message' => 'Владелец группы не может быть добавлен как участник.']);
        exit;
    }
    

    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
    $stmt->execute([$group_id, $user['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Пользователь успешно добавлен в группу.']);
} catch (PDOException $e) {
    error_log("Add member error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Произошла ошибка при добавлении пользователя.']);
}
exit;

