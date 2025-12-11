<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
if (!$pdo) {
    header('Location: groups.php?message=' . urlencode('Ошибка подключения к базе данных.'));
    exit;
}

$user_id = getCurrentUserId();
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (empty($name)) {
    header('Location: groups.php?message=' . urlencode('Введите название группы.'));
    exit;
}

if (strlen($name) > 100) {
    header('Location: groups.php?message=' . urlencode('Название группы не должно превышать 100 символов.'));
    exit;
}

if (strlen($description) > 255) {
    header('Location: groups.php?message=' . urlencode('Описание не должно превышать 255 символов.'));
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO user_groups (name, description, owner_id) VALUES (?, ?, ?)");
    $stmt->execute([$name, $description ?: null, $user_id]);
    
    header('Location: groups.php?message=' . urlencode('Группа успешно создана!'));
} catch (PDOException $e) {
    error_log("Create group error: " . $e->getMessage());
    header('Location: groups.php?message=' . urlencode('Произошла ошибка при создании группы.'));
}
exit;

