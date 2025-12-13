<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных.']);
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID пользователя.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, description, priority, completed, created_at, updated_at 
                           FROM tasks 
                           WHERE user_id = ? 
                           ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'tasks' => $tasks]);
} catch (PDOException $e) {
    error_log("Get user tasks error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка при получении задач пользователя.']);
}

