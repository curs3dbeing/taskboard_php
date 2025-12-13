<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных.']);
    exit;
}

$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;

if (!$task_id) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID задачи.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Задача успешно удалена.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Задача не найдена.']);
    }
} catch (PDOException $e) {
    error_log("Delete task error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка при удалении задачи.']);
}

