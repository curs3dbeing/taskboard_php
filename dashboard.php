<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed. Please check the configuration.");
}

$user_id = getCurrentUserId();

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? AND completed = 0 AND group_id IS NULL ORDER BY priority ASC, created_at DESC");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();


$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? AND completed = 1 AND group_id IS NULL ORDER BY updated_at DESC");
$stmt->execute([$user_id]);
$completedTasks = $stmt->fetchAll();


$stmt = $pdo->prepare("SELECT g.* FROM user_groups g WHERE g.owner_id = ? 
                       UNION 
                       SELECT g.* FROM user_groups g 
                       INNER JOIN group_members gm ON g.id = gm.group_id 
                       WHERE gm.user_id = ? 
                       ORDER BY created_at DESC");
$stmt->execute([$user_id, $user_id]);
$userGroups = $stmt->fetchAll();


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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Таблица задач</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Таблица задач</h1>
            <div class="user-info">
                <a href="groups.php" class="btn btn-secondary">Группы</a>
                <span>Здравствуйте, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="logout.php" class="btn btn-secondary">Выйти</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="task-section">
            <div class="task-header">
                <h2>Мои задачи</h2>
                <button class="btn btn-primary" onclick="openTaskModal()">+ Новая задача</button>
            </div>

            <!-- Вкладки групп -->
            <div class="tabs">
                <button class="tab-btn active" id="tab-personal">
                    Мои задачи
                </button>
                <?php foreach ($userGroups as $group): ?>
                    <button class="tab-btn" onclick="window.location.href='group_dashboard.php?group_id=<?php echo $group['id']; ?>'" id="tab-group-<?php echo $group['id']; ?>">
                        <?php echo htmlspecialchars($group['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Вкладки для личных задач -->
            <div class="tabs" style="margin-top: 10px;">
                <button class="tab-btn active" onclick="switchPersonalTab('active')" id="personal-tab-active">
                    Активные задачи <span class="tab-count">(<?php echo count($tasks); ?>)</span>
                </button>
                <button class="tab-btn" onclick="switchPersonalTab('completed')" id="personal-tab-completed">
                    Выполненные <span class="tab-count">(<?php echo count($completedTasks); ?>)</span>
                </button>
            </div>

            <!-- Личные задачи -->
            <div class="tab-content active" id="content-personal">
                <!-- Активные задачи -->
                <div class="personal-tab-content active" id="personal-content-active">
                    <div class="tasks-grid" id="tasksGrid">
                        <?php if (empty($tasks)): ?>
                            <div class="empty-state">
                                <p>У вас еще нет активных задач. Создайте ее сейчас!</p>
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
                                        </div>
                                    </div>
                                    <p class="task-description"><?php echo nl2br(htmlspecialchars($task['description'] ?? '')); ?></p>
                                    <div class="task-footer">
                                        <small>Создана: <?php echo date('d.m.Y H:i', strtotime($task['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Выполненные задачи -->
                <div class="personal-tab-content" id="personal-content-completed">
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
                                            <button class="btn-icon" onclick="deleteTask(<?php echo $task['id']; ?>)" title="Удалить">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                    <path d="M3 6h10M6 6v6m4-6v6M5 6l1-3h4l1 3M5 6h6"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="task-description"><?php echo nl2br(htmlspecialchars($task['description'] ?? '')); ?></p>
                                    <div class="task-footer">
                                        <small>Выполнена: <?php echo date('d.m.Y H:i', strtotime($task['updated_at'])); ?></small>
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
            if (tab === 'personal') {

                document.querySelectorAll('.tab-content').forEach(content => {
                    if (content.id === 'content-personal') {
                        content.classList.add('active');
                    } else {
                        content.classList.remove('active');
                    }
                });
                

                document.querySelectorAll('.tabs .tab-btn').forEach(btn => {
                    if (btn.id === 'tab-personal') {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
            }
        }
        
        function switchPersonalTab(tab) {
            document.querySelectorAll('.personal-tab-content').forEach(content => content.classList.remove('active'));
            document.querySelectorAll('.tabs .tab-btn').forEach(btn => {
                if (btn.id.startsWith('personal-tab-')) {
                    btn.classList.remove('active');
                }
            });
            
            document.getElementById('personal-content-' + tab).classList.add('active');
            document.getElementById('personal-tab-' + tab).classList.add('active');
        }
    </script>
    <script src="script.js"></script>
</body>
</html>

