<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed. Please check the configuration.");
}

$user_id = getCurrentUserId();
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    header('Location: groups.php?message=' . urlencode('Неверный ID группы.'));
    exit;
}


$stmt = $pdo->prepare("SELECT g.*, 
                       CASE WHEN g.owner_id = ? THEN 1 ELSE 0 END as is_owner,
                       CASE WHEN EXISTS(SELECT 1 FROM group_members WHERE group_id = g.id AND user_id = ?) THEN 1 ELSE 0 END as is_member
                       FROM user_groups g 
                       WHERE g.id = ?");
$stmt->execute([$user_id, $user_id, $group_id]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: groups.php?message=' . urlencode('Группа не найдена.'));
    exit;
}

if (!$group['is_owner'] && !$group['is_member']) {
    header('Location: groups.php?message=' . urlencode('У вас нет доступа к этой группе.'));
    exit;
}

$stmt = $pdo->prepare("SELECT u.id, u.username, u.email 
                       FROM group_members gm 
                       INNER JOIN users u ON gm.user_id = u.id 
                       WHERE gm.group_id = ? 
                       ORDER BY u.username");
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();


$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
$stmt->execute([$group['owner_id']]);
$owner = $stmt->fetch();
$allMembers = array_merge([$owner], $members);

$stmt = $pdo->prepare("SELECT t.*, u.username as creator_name 
                       FROM tasks t 
                       INNER JOIN users u ON t.user_id = u.id 
                       WHERE t.group_id = ? AND t.completed = 0 
                       ORDER BY t.priority ASC, t.created_at DESC");
$stmt->execute([$group_id]);
$tasks = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT t.*, u.username as creator_name 
                       FROM tasks t 
                       INNER JOIN users u ON t.user_id = u.id 
                       WHERE t.group_id = ? AND t.completed = 1 
                       ORDER BY t.updated_at DESC");
$stmt->execute([$group_id]);
$completedTasks = $stmt->fetchAll();

function getPriorityName($priority) {
    switch($priority) {
        case 1: return 'Критическая';
        case 2: return 'Средней важности';
        case 3: return 'По мере возможности';
        default: return 'Средней важности';
    }
}

function getPriorityClass($priority) {
    switch($priority) {
        case 1: return 'priority-critical';
        case 2: return 'priority-medium';
        case 3: return 'priority-low';
        default: return 'priority-medium';
    }
}

$message = '';
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['name']); ?> - Задачи группы</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><?php echo htmlspecialchars($group['name']); ?></h1>
            <div class="user-info">
                <a href="dashboard.php" class="btn btn-secondary">Мои задачи</a>
                <a href="groups.php" class="btn btn-secondary">Группы</a>
                <?php if (!$group['is_owner'] && $group['is_member']): ?>
                    <a href="leave_group.php?group_id=<?php echo $group_id; ?>" class="btn btn-secondary" onclick="return confirm('Вы уверены, что хотите покинуть группу \'<?php echo htmlspecialchars(addslashes($group['name'])); ?>\'?')">Покинуть группу</a>
                <?php endif; ?>
                <span>Здравствуйте, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="logout.php" class="btn btn-secondary">Выйти</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($group['description']): ?>
            <div class="alert alert-info" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars($group['description']); ?>
            </div>
        <?php endif; ?>

        <div class="task-section">
            <div class="task-header">
                <h2>Задачи группы</h2>
                <button class="btn btn-primary" onclick="openTaskModal()">+ Новая задача</button>
            </div>

            <!-- Вкладки -->
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('active')" id="tab-active">
                    Активные задачи <span class="tab-count">(<?php echo count($tasks); ?>)</span>
                </button>
                <button class="tab-btn" onclick="switchTab('completed')" id="tab-completed">
                    Выполненные <span class="tab-count">(<?php echo count($completedTasks); ?>)</span>
                </button>
            </div>

            <!-- Активные задачи -->
            <div class="tab-content active" id="content-active">
                <div class="tasks-grid" id="tasksGrid">
                    <?php if (empty($tasks)): ?>
                        <div class="empty-state">
                            <p>В группе пока нет активных задач. Создайте первую задачу!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-card <?php echo getPriorityClass($task['priority']); ?>" data-task-id="<?php echo $task['id']; ?>">
                                <div class="task-header-card">
                                    <div class="task-title-section">
                                        <h3><?php echo htmlspecialchars($task['name']); ?></h3>
                                        <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                            <?php echo getPriorityName($task['priority']); ?>
                                        </span>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn-icon" onclick="toggleTask(<?php echo $task['id']; ?>, 1)" title="Отметить выполненной">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                <path d="M13 4L6 11L3 8"/>
                                            </svg>
                                        </button>
                                        <?php if ($task['user_id'] == $user_id): ?>
                                            <button class="btn-icon" onclick="editTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars(addslashes($task['name'])); ?>', '<?php echo htmlspecialchars(addslashes($task['description'] ?? '')); ?>', <?php echo $task['priority']; ?>)" title="Редактировать">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                    <path d="M11.5 2.5a2.121 2.121 0 0 1 3 3L6.5 13.5l-4 1 1-4L11.5 2.5z"/>
                                                </svg>
                                            </button>
                                            <button class="btn-icon" onclick="deleteTask(<?php echo $task['id']; ?>)" title="Удалить">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                    <path d="M3 6h10M6 6v6m4-6v6M5 6l1-3h4l1 3M5 6h6"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="task-description"><?php echo nl2br(htmlspecialchars($task['description'] ?? '')); ?></p>
                                <div class="task-footer">
                                    <small>Создана: <?php echo date('d.m.Y H:i', strtotime($task['created_at'])); ?> пользователем <strong><?php echo htmlspecialchars($task['creator_name']); ?></strong></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Выполненные задачи -->
            <div class="tab-content" id="content-completed">
                <div class="tasks-grid" id="completedTasksGrid">
                    <?php if (empty($completedTasks)): ?>
                        <div class="empty-state">
                            <p>Нет выполненных задач.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($completedTasks as $task): ?>
                            <div class="task-card task-completed <?php echo getPriorityClass($task['priority']); ?>" data-task-id="<?php echo $task['id']; ?>">
                                <div class="task-header-card">
                                    <div class="task-title-section">
                                        <h3><?php echo htmlspecialchars($task['name']); ?></h3>
                                        <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                            <?php echo getPriorityName($task['priority']); ?>
                                        </span>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn-icon" onclick="toggleTask(<?php echo $task['id']; ?>, 0)" title="Вернуть в активные">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                <path d="M11 5L8 8L5 5M8 11V8"/>
                                            </svg>
                                        </button>
                                        <?php if ($task['user_id'] == $user_id): ?>
                                            <button class="btn-icon" onclick="deleteTask(<?php echo $task['id']; ?>)" title="Удалить">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                    <path d="M3 6h10M6 6v6m4-6v6M5 6l1-3h4l1 3M5 6h6"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="task-description"><?php echo nl2br(htmlspecialchars($task['description'] ?? '')); ?></p>
                                <div class="task-footer">
                                    <small>Выполнена: <?php echo date('d.m.Y H:i', strtotime($task['updated_at'])); ?> пользователем <strong><?php echo htmlspecialchars($task['creator_name']); ?></strong></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Новая задача</h2>
                <button class="btn-icon" onclick="closeTaskModal()">&times;</button>
            </div>
            <form id="taskForm" method="POST" action="save_task.php">
                <input type="hidden" id="taskId" name="task_id" value="">
                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                <div class="form-group">
                    <label for="taskName">Название задачи</label>
                    <input type="text" id="taskName" name="name" required maxlength="35">
                </div>
                <div class="form-group">
                    <label for="taskDescription">Описание</label>
                    <textarea id="taskDescription" name="description" rows="4" maxlength="100"></textarea>
                </div>
                <div class="form-group">
                    <label for="taskPriority">Важность</label>
                    <select id="taskPriority" name="priority" required>
                        <option value="1">Критическая</option>
                        <option value="2" selected>Средней важности</option>
                        <option value="3">По мере возможности</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            document.getElementById('tab-' + tab).classList.add('active');
            document.getElementById('content-' + tab).classList.add('active');
        }

        function openTaskModal(taskId = null, taskName = '', taskDescription = '', priority = 2) {
            const modal = document.getElementById('taskModal');
            const form = document.getElementById('taskForm');
            const modalTitle = document.getElementById('modalTitle');
            
            if (taskId) {
                modalTitle.textContent = 'Редактировать задачу';
                document.getElementById('taskId').value = taskId;
                document.getElementById('taskName').value = taskName;
                document.getElementById('taskDescription').value = taskDescription;
                document.getElementById('taskPriority').value = priority;
            } else {
                modalTitle.textContent = 'Новая задача';
                form.reset();
                document.getElementById('taskId').value = '';
                document.getElementById('taskPriority').value = 2;
            }
            
            modal.classList.add('show');
        }

        function closeTaskModal() {
            const modal = document.getElementById('taskModal');
            const form = document.getElementById('taskForm');
            modal.classList.remove('show');
            form.reset();
        }

        function editTask(id, name, description, priority) {
            openTaskModal(id, name, description, priority);
        }

        function toggleTask(taskId, completed) {
            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('completed', completed);
            formData.append('group_id', <?php echo $group_id; ?>);

            fetch('toggle_task.php', {
                method: 'POST',
                body: formData
            })
            .then(() => {
                window.location.href = 'group_dashboard.php?group_id=<?php echo $group_id; ?>&message=' + encodeURIComponent(completed ? 'Задача отмечена как выполненная!' : 'Задача возвращена в активные!');
            })
            .catch(error => {
                alert('Ошибка: ' + error);
            });
        }

        function deleteTask(taskId) {
            if (!confirm('Вы уверены, что хотите удалить эту задачу?')) {
                return;
            }

            window.location.href = 'delete_task.php?id=' + taskId + '&group_id=<?php echo $group_id; ?>';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('taskModal');
            if (event.target == modal) {
                closeTaskModal();
            }
        }
    </script>
</body>
</html>

