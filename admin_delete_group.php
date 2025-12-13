<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных.']);
    exit;
}

$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID группы.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM user_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Группа не найдена.']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM user_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Группа успешно удалена.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при удалении группы.']);
    }
} catch (PDOException $e) {
    error_log("Delete group error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка при удалении группы.']);
}

