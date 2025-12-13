<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных.']);
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$action = $_POST['action'] ?? '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID пользователя.']);
    exit;
}


if ($user_id == getCurrentUserId()) {
    echo json_encode(['success' => false, 'message' => 'Вы не можете заблокировать самого себя.']);
    exit;
}


$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не найден.']);
    exit;
}

if ($user['role'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Нельзя блокировать администратора.']);
    exit;
}

try {
    $is_blocked = ($action === 'block') ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
    $stmt->execute([$is_blocked, $user_id]);
    
    $message = $is_blocked ? 'Пользователь успешно заблокирован.' : 'Пользователь успешно разблокирован.';
    echo json_encode(['success' => true, 'message' => $message]);
} catch (PDOException $e) {
    error_log("Toggle block error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка при изменении статуса пользователя.']);
}

