<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
$user_id = getCurrentUserId();

$task_id = $_POST['task_id'] ?? null;
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 2;
$group_id = isset($_POST['group_id']) && $_POST['group_id'] ? (int)$_POST['group_id'] : null;

if ($priority < 1 || $priority > 3) {
    $priority = 2;
}

if (empty($name)) {
    $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
    header('Location: ' . $redirect . '?message=' . urlencode('Введите название задачи.'));
    exit;
}

if (strlen($name) > 35) {
    $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
    header('Location: ' . $redirect . '?message=' . urlencode('Название задачи не должно превышать 35 символов.'));
    exit;
}

if (strlen($description) > 100) {
    $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
    header('Location: ' . $redirect . '?message=' . urlencode('Описание не должно превышать 100 символов.'));
    exit;
}

$name = mb_substr($name, 0, 35);
$description = mb_substr($description, 0, 100);

try {

    if ($group_id) {
        $stmt = $pdo->prepare("SELECT owner_id FROM user_groups WHERE id = ?");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        
        if (!$group) {
            header('Location: groups.php?message=' . urlencode('Группа не найдена.'));
            exit;
        }
        

        $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        $isMember = $stmt->fetch();
        
        if ($group['owner_id'] != $user_id && !$isMember) {
            header('Location: groups.php?message=' . urlencode('У вас нет прав для создания задач в этой группе.'));
            exit;
        }
    }
    
    if ($task_id) {

        if ($group_id) {

            $stmt = $pdo->prepare("SELECT t.user_id, t.group_id FROM tasks t WHERE t.id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            if (!$task || $task['user_id'] != $user_id) {
                $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
                header('Location: ' . $redirect . '?message=' . urlencode('У вас нет прав для редактирования этой задачи.'));
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE tasks SET name = ?, description = ?, priority = ? WHERE id = ? AND user_id = ? AND group_id = ?");
            $stmt->execute([$name, $description, $priority, $task_id, $user_id, $group_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE tasks SET name = ?, description = ?, priority = ? WHERE id = ? AND user_id = ? AND group_id IS NULL");
            $stmt->execute([$name, $description, $priority, $task_id, $user_id]);
        }
        
        if ($stmt->rowCount() > 0) {
            $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
            header('Location: ' . $redirect . '?message=' . urlencode('Задание успешно обновлено!'));
        } else {
            $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
            header('Location: ' . $redirect . '?message=' . urlencode('Задание не найдено или у вас нет прав.'));
        }
    } else {
        // Проверка на дубликаты: если задача с таким же названием была создана менее 5 секунд назад
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE user_id = ? AND name = ? AND group_id " . ($group_id ? "= ?" : "IS NULL") . " AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        if ($group_id) {
            $stmt->execute([$user_id, $name, $group_id]);
        } else {
            $stmt->execute([$user_id, $name]);
        }
        
        if ($stmt->fetch()) {
            // Дубликат найден, не создаем новую задачу
            $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
            header('Location: ' . $redirect . '?message=' . urlencode('Задача уже была создана.'));
        } else {
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, name, description, priority, group_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $description, $priority, $group_id]);
            $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
            header('Location: ' . $redirect . '?message=' . urlencode('Задание успешно создано!'));
        }
    }
} catch (PDOException $e) {
    error_log("Save task error: " . $e->getMessage());
    $redirect = $group_id ? "group_dashboard.php?group_id=$group_id" : 'dashboard.php';
    header('Location: ' . $redirect . '?message=' . urlencode('Произошла ошибка, попробуйте еще раз.'));
}
exit;

