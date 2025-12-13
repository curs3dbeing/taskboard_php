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
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .admin-nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .user-info .admin-nav {
            margin-bottom: 0;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .user-info > * {
            display: flex;
            align-items: center;
        }
        .search-container {
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .search-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .search-group {
            flex: 1;
            min-width: 200px;
        }
        .search-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
        }
        .search-group input,
        .search-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        .search-group input:focus,
        .search-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .search-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .search-results {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 14px;
        }
        .users-table tbody tr.hidden {
            display: none;
        }
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .users-table {
            width: 100%;
            min-width: 800px;
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
            white-space: nowrap;
        }
        .users-table th {
            background-color: #f5f7fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .users-table tr:hover {
            background-color: #f8f9fa;
        }
        .users-table td:last-child {
            white-space: normal;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
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
            white-space: nowrap;
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
        #tasksModal .modal-content {
            max-width: 90%;
            width: 100%;
            max-width: 600px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            box-sizing: border-box;
        }
        #tasksModalBody {
            word-wrap: break-word;
            overflow-wrap: break-word;
            overflow-x: hidden;
            box-sizing: border-box;
            width: 100%;
        }
        #tasksModal .modal-header h2 {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .tasks-list {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .task-item {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .task-item:last-child {
            border-bottom: none;
        }
        .task-item > div {
            flex: 1;
            min-width: 200px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .task-item > div strong {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            display: block;
            margin-bottom: 5px;
        }
        .task-item > div small {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            display: block;
            line-height: 1.4;
        }
        .task-item button {
            flex-shrink: 0;
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .admin-container {
                padding: 0;
            }
            .admin-container .task-section {
                padding: 15px;
                margin: 0;
            }
            .admin-nav {
                flex-direction: column;
                gap: 8px;
            }
            .admin-nav .btn {
                width: 100%;
            }
            .search-container {
                padding: 15px;
            }
            .search-row {
                flex-direction: column;
                gap: 15px;
            }
            .search-group {
                min-width: 100%;
            }
            .search-actions {
                width: 100%;
            }
            .search-actions .btn {
                width: 100%;
            }
            .users-table {
                min-width: 600px;
                font-size: 14px;
            }
            .users-table th,
            .users-table td {
                padding: 8px;
                font-size: 12px;
            }
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            .action-buttons .btn {
                width: 100%;
            }
            .badge {
                font-size: 10px;
                padding: 3px 8px;
            }
            .task-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .task-item > div {
                width: 100%;
            }
            .task-item button {
                width: 100%;
            }
            #tasksModal .modal-content {
                max-width: 95%;
                margin: 10px;
            }
            #tasksModalBody {
                padding: 15px !important;
            }
        }
        
        @media (max-width: 480px) {
            .users-table {
                min-width: 500px;
                font-size: 12px;
            }
            .users-table th,
            .users-table td {
                padding: 6px;
                font-size: 11px;
            }
            .btn-small {
                padding: 5px 8px;
                font-size: 12px;
            }
            #tasksModal .modal-content {
                max-width: 98%;
                margin: 5px;
            }
            #tasksModalBody {
                padding: 10px !important;
            }
            .task-item {
                flex-direction: column;
                align-items: stretch;
            }
            .task-item > div {
                min-width: 0;
                width: 100%;
            }
            .task-item button {
                width: 100%;
                margin-top: 10px;
            }
        }
        
        @media (max-width: 768px) {
            .header .user-info {
                display: none;
            }
            .header .admin-nav {
                display: none;
            }
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
                <h2>Список всех пользователей (<span id="totalUsers"><?php echo count($users); ?></span>)</h2>
                
                <div class="search-container">
                    <div class="search-row">
                        <div class="search-group">
                            <label for="searchUsername">Поиск по логину</label>
                            <input type="text" id="searchUsername" placeholder="Введите логин..." oninput="filterUsers()">
                        </div>
                        <div class="search-group">
                            <label for="searchEmail">Поиск по почте</label>
                            <input type="text" id="searchEmail" placeholder="Введите email..." oninput="filterUsers()">
                        </div>
                        <div class="search-group">
                            <label for="searchRole">Фильтр по роли</label>
                            <select id="searchRole" onchange="filterUsers()">
                                <option value="">Все роли</option>
                                <option value="admin">Администратор</option>
                                <option value="user">Пользователь</option>
                            </select>
                        </div>
                        <div class="search-group">
                            <label for="searchStatus">Фильтр по статусу</label>
                            <select id="searchStatus" onchange="filterUsers()">
                                <option value="">Все статусы</option>
                                <option value="active">Активен</option>
                                <option value="blocked">Заблокирован</option>
                            </select>
                        </div>
                        <div class="search-actions">
                            <button type="button" class="btn btn-secondary" onclick="clearSearch()">Очистить</button>
                        </div>
                    </div>
                    <div class="search-results">
                        Найдено: <span id="filteredCount"><?php echo count($users); ?></span> из <span id="totalCount"><?php echo count($users); ?></span>
                    </div>
                </div>
                
                <div class="table-wrapper">
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
                    <tbody id="usersTableBody">
                        <?php foreach ($users as $user): ?>
                            <tr data-username="<?php echo htmlspecialchars(strtolower($user['username'])); ?>" 
                                data-email="<?php echo htmlspecialchars(strtolower($user['email'])); ?>" 
                                data-role="<?php echo htmlspecialchars($user['role']); ?>" 
                                data-status="<?php echo $user['is_blocked'] == 1 ? 'blocked' : 'active'; ?>">
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
        function filterUsers() {
            const usernameFilter = document.getElementById('searchUsername').value.toLowerCase().trim();
            const emailFilter = document.getElementById('searchEmail').value.toLowerCase().trim();
            const roleFilter = document.getElementById('searchRole').value;
            const statusFilter = document.getElementById('searchStatus').value;
            
            const rows = document.querySelectorAll('#usersTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const username = row.getAttribute('data-username') || '';
                const email = row.getAttribute('data-email') || '';
                const role = row.getAttribute('data-role') || '';
                const status = row.getAttribute('data-status') || '';
                
                const matchesUsername = !usernameFilter || username.includes(usernameFilter);
                const matchesEmail = !emailFilter || email.includes(emailFilter);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesUsername && matchesEmail && matchesRole && matchesStatus) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });
            
            document.getElementById('filteredCount').textContent = visibleCount;
        }
        
        function clearSearch() {
            document.getElementById('searchUsername').value = '';
            document.getElementById('searchEmail').value = '';
            document.getElementById('searchRole').value = '';
            document.getElementById('searchStatus').value = '';
            filterUsers();
        }
        
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
                                        <button class="btn btn-danger btn-small" onclick="deleteUserTask(${task.id}, ${userId}, ${JSON.stringify(task.name)})">Удалить</button>
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

        document.getElementById('tasksModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeTasksModal();
            }
        });
    </script>
</body>
</html>

