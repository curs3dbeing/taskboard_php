<?php
require_once 'config.php';
requireAdmin();

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed. Please check the configuration.");
}

$message = '';
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}


$stmt = $pdo->prepare("SELECT id, username, email, role, is_blocked, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();


$userStats = [];
foreach ($users as $user) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed FROM tasks WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch();
    $userStats[$user['id']] = $stats;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .admin-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .users-table th {
            background-color: #f5f7fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .users-table tr:hover {
            background-color: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-admin {
            background-color: #dc3545;
            color: white;
        }
        .badge-user {
            background-color: #6c757d;
            color: white;
        }
        .badge-blocked {
            background-color: #dc3545;
            color: white;
        }
        .badge-active {
            background-color: #28a745;
            color: white;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .modal {
            display: none;
        }
        .modal.show {
            display: flex;
        }
        .tasks-list {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .task-item {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .task-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Управление пользователями</h1>
            <button class="burger-menu" onclick="toggleMobileMenu()" aria-label="Меню">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="user-info">
                <div class="admin-nav">
                    <a href="dashboard.php" class="btn btn-secondary">Мои задачи</a>
                    <a href="groups.php" class="btn btn-secondary">Группы</a>
                    <a href="admin_groups.php" class="btn btn-secondary">Управление группами</a>
                </div>
                <span>Администратор: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary">Выйти</a>
            </div>
            <div class="mobile-menu" id="mobileMenu">
                <a href="dashboard.php" class="btn btn-secondary">Мои задачи</a>
                <a href="groups.php" class="btn btn-secondary">Группы</a>
                <a href="admin_groups.php" class="btn btn-secondary">Управление группами</a>
                <span>Администратор: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary">Выйти</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="admin-container">
            <div class="task-section">
                <h2>Список всех пользователей (<?php echo count($users); ?>)</h2>
                
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Задач (всего/выполнено)</th>
                            <th>Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                        <?php echo $user['role'] === 'admin' ? 'Администратор' : 'Пользователь'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['is_blocked'] == 1 ? 'badge-blocked' : 'badge-active'; ?>">
                                        <?php echo $user['is_blocked'] == 1 ? 'Заблокирован' : 'Активен'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $stats = $userStats[$user['id']] ?? ['total' => 0, 'completed' => 0];
                                    echo ($stats['total'] ?? 0) . ' / ' . ($stats['completed'] ?? 0);
                                    ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['id'] != getCurrentUserId()): ?>
                                            <button class="btn btn-warning btn-small" 
                                                    onclick="toggleBlock(<?php echo $user['id']; ?>, <?php echo $user['is_blocked']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')">
                                                <?php echo $user['is_blocked'] == 1 ? 'Разблокировать' : 'Заблокировать'; ?>
                                            </button>
                                            <button class="btn btn-secondary btn-small" 
                                                    onclick="viewUserTasks(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')">
                                                Задачи
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 12px;">Вы</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Модальное окно для просмотра задач пользователя -->
    <div id="tasksModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="tasksModalTitle">Задачи пользователя</h2>
                <button class="btn-icon" onclick="closeTasksModal()">&times;</button>
            </div>
            <div id="tasksModalBody" style="padding: 30px;">
                <div class="tasks-list" id="tasksList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTasksModal()">Закрыть</button>
            </div>
        </div>
    </div>

    <script>
        function toggleBlock(userId, currentStatus, username) {
            const action = currentStatus == 1 ? 'разблокировать' : 'заблокировать';
            if (!confirm(`Вы уверены, что хотите ${action} пользователя "${username}"?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('action', currentStatus == 1 ? 'unblock' : 'block');

            fetch('admin_toggle_block.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || 'Ошибка при изменении статуса пользователя');
                }
            })
            .catch(error => {
                alert('Произошла ошибка: ' + error);
            });
        }

        function viewUserTasks(userId, username) {
            document.getElementById('tasksModalTitle').textContent = 'Задачи пользователя: ' + username;
            document.getElementById('tasksList').innerHTML = '<p>Загрузка...</p>';
            document.getElementById('tasksModal').classList.add('show');

            fetch('admin_get_user_tasks.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const tasksList = document.getElementById('tasksList');
                        if (data.tasks.length === 0) {
                            tasksList.innerHTML = '<p>У пользователя нет задач.</p>';
                        } else {
                            let html = '';
                            data.tasks.forEach(task => {
                                html += `
                                    <div class="task-item">
                                        <div>
                                            <strong>${escapeHtml(task.name)}</strong>
                                            ${task.description ? '<br><small>' + escapeHtml(task.description) + '</small>' : ''}
                                            <br><small>Приоритет: ${getPriorityName(task.priority)} | 
                                            ${task.completed == 1 ? 'Выполнена' : 'Активна'} | 
                                            Создана: ${task.created_at}</small>
                                        </div>
                                        <button class="btn btn-danger btn-small" onclick="deleteUserTask(${task.id}, ${userId}, '${escapeHtml(task.name)}')">Удалить</button>
                                    </div>
                                `;
                            });
                            tasksList.innerHTML = html;
                        }
                    } else {
                        document.getElementById('tasksList').innerHTML = '<p>Ошибка: ' + (data.message || 'Не удалось загрузить задачи') + '</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('tasksList').innerHTML = '<p>Ошибка загрузки: ' + error + '</p>';
                });
        }

        function deleteUserTask(taskId, userId, taskName) {
            if (!confirm(`Вы уверены, что хотите удалить задачу "${taskName}"?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('task_id', taskId);

            fetch('admin_delete_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Задача успешно удалена!');
                    viewUserTasks(userId, document.getElementById('tasksModalTitle').textContent.replace('Задачи пользователя: ', ''));
                } else {
                    alert(data.message || 'Ошибка при удалении задачи');
                }
            })
            .catch(error => {
                alert('Произошла ошибка: ' + error);
            });
        }

        function closeTasksModal() {
            document.getElementById('tasksModal').classList.remove('show');
        }

        function getPriorityName(priority) {
            const priorities = {
                1: 'Критическая',
                2: 'Средней важности',
                3: 'По мере возможности'
            };
            return priorities[priority] || 'Средней важности';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const burger = document.querySelector('.burger-menu');
            menu.classList.toggle('active');
            burger.classList.toggle('active');
        }

        // Закрытие меню при клике вне его
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('mobileMenu');
            const burger = document.querySelector('.burger-menu');
            const header = document.querySelector('.header');
            
            if (menu && burger && header) {
                if (!header.contains(event.target) && menu.classList.contains('active')) {
                    menu.classList.remove('active');
                    burger.classList.remove('active');
                }
            }
        });

        // Закрытие модального окна при клике вне его
        document.getElementById('tasksModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeTasksModal();
            }
        });
    </script>
</body>
</html>

