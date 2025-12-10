<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
$user_id = getCurrentUserId();

$task_id = $_POST['task_id'] ?? null;
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 2; // По умолчанию средняя важность

// Валидация приоритета
if ($priority < 1 || $priority > 3) {
    $priority = 2;
}

if (empty($name)) {
    header('Location: dashboard.php?message=' . urlencode('Введите название задачи.'));
    exit;
}

try {
    if ($task_id) {
        $stmt = $pdo->prepare("UPDATE tasks SET name = ?, description = ?, priority = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $description, $priority, $task_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            header('Location: dashboard.php?message=' . urlencode('Задание успешно обновлено!'));
        } else {
            header('Location: dashboard.php?message=' . urlencode('Задание не найдено или у вас нет прав.'));
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO tasks (user_id, name, description, priority) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $description, $priority]);
        header('Location: dashboard.php?message=' . urlencode('Задание успешно создано!'));
    }
} catch (PDOException $e) {
    header('Location: dashboard.php?message=' . urlencode('Произошла ошибка, попробуйте еще раз.'));
}
exit;

