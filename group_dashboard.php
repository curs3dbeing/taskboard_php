<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed. Please check the configuration.");
}

$user_id = getCurrentUserId();

// Get user email
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userEmail = $stmt->fetchColumn();

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

if (!$group['is_owner'] && !$group['is_member'] && !isAdmin()) {
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
    <title><?php echo htmlspecialchars($group['name']); ?> - NotesHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="main-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">N</div>
                <div class="sidebar-logo-text">
                    <h1>NotesHub</h1>
                    <p>Командные заметки</p>
                </div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <h3>ГРУППЫ</h3>
                    <button onclick="window.location.href='groups.php'" title="Управление группами">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                            <path d="M8 4v8M4 8h8"/>
                        </svg>
                    </button>
                </div>
                <a href="dashboard.php" class="sidebar-item">
                    <span class="sidebar-item-text">Все заметки</span>
                    <svg class="sidebar-item-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <path d="M6 4l4 4-4 4"/>
                    </svg>
                </a>
                <?php 
                $stmt = $pdo->prepare("SELECT g.* FROM user_groups g WHERE g.owner_id = ? 
                                       UNION 
                                       SELECT g.* FROM user_groups g 
                                       INNER JOIN group_members gm ON g.id = gm.group_id 
                                       WHERE gm.user_id = ? 
                                       ORDER BY created_at DESC");
                $stmt->execute([$user_id, $user_id]);
                $allGroups = $stmt->fetchAll();
                foreach ($allGroups as $g): 
                ?>
                    <a href="group_dashboard.php?group_id=<?php echo $g['id']; ?>" class="sidebar-item <?php echo $g['id'] == $group_id ? 'active' : ''; ?>">
                        <span class="sidebar-item-dot purple"></span>
                        <span class="sidebar-item-text"><?php echo htmlspecialchars($g['name']); ?></span>
                        <svg class="sidebar-item-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                            <path d="M6 4l4 4-4 4"/>
                        </svg>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <h3>ВАЖНОСТЬ</h3>
                </div>
                <div class="sidebar-item active" onclick="filterByPriority('all')">
                    <div class="sidebar-item-icon">O</div>
                    <span class="sidebar-item-text">Все</span>
                </div>
                <div class="sidebar-item" onclick="filterByPriority(1)">
                    <div class="sidebar-item-icon priority-icon critical">!</div>
                    <span class="sidebar-item-text">Критическая</span>
                </div>
                <div class="sidebar-item" onclick="filterByPriority(2)">
                    <div class="sidebar-item-icon priority-icon medium">i</div>
                    <span class="sidebar-item-text">Средней важности</span>
                </div>
                <div class="sidebar-item" onclick="filterByPriority(3)">
                    <div class="sidebar-item-icon priority-icon low">—</div>
                    <span class="sidebar-item-text">По мере возможности</span>
                </div>
            </div>
        </aside>
        
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        
        <!-- Main Content -->
        <div class="content-wrapper">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="burger-menu" onclick="toggleSidebar()" aria-label="Меню" style="display: none;">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="search-bar">
                        <svg class="search-bar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                            <circle cx="7" cy="7" r="4"/>
                            <path d="M10 10l3 3"/>
                        </svg>
                        <input type="text" placeholder="Поиск по заметкам, тегам..." id="searchInput" oninput="filterTasks()">
                    </div>
                </div>
                <div class="header-right">
                    <button class="btn btn-secondary" onclick="window.location.href='groups.php'" style="padding: 8px 16px; font-size: 14px;">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                            <path d="M12 4v8M4 4v8M2 2h12v12H2z"/>
                        </svg>
                        Группы
                    </button>
                    <button class="btn btn-primary" onclick="openTaskModal()" style="padding: 10px 20px; font-size: 14px;">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                            <path d="M8 4v8M4 8h8"/>
                        </svg>
                        Создать
                    </button>
                    <div style="position: relative;">
                        <div class="user-avatar" onclick="toggleUserMenu()" title="<?php echo htmlspecialchars($_SESSION['username']); ?>">
                            <?php echo strtoupper(mb_substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <!-- User Menu -->
                        <div class="mobile-menu" id="userMenu" style="display: none; position: absolute; top: calc(100% + 8px); right: 0; background: var(--card-background); border: 1px solid var(--border-color); border-radius: 8px; padding: 8px; min-width: 200px; max-width: 300px; width: auto; z-index: 1000; box-shadow: var(--shadow-lg);">
                <div style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                    <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <?php if ($userEmail): ?>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;"><?php echo htmlspecialchars($userEmail); ?></div>
                    <?php endif; ?>
                </div>
                <?php if (isAdmin()): ?>
                    <a href="admin_users.php" class="btn btn-secondary" style="width: 100%; margin: 4px 0; text-align: left; padding: 10px 12px; font-size: 14px; box-sizing: border-box;">Админ-панель</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-secondary" style="width: 100%; margin: 4px 0; text-align: left; padding: 10px 12px; font-size: 14px; box-sizing: border-box;">Выйти</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="main-content">

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($group['description']): ?>
            <div class="alert alert-info" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars($group['description']); ?>
            </div>
        <?php endif; ?>


                <div class="task-section">
                    <div class="task-header" style="margin-bottom: 24px;">
                        <h2 style="color: var(--text-primary); font-size: 24px; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($group['name']); ?></h2>
                    </div>

                    <!-- Вкладки -->
                    <div class="tabs" style="margin-bottom: 24px;">
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
                                <?php foreach ($tasks as $task): 
                                    $priorityIcon = $task['priority'] == 1 ? '!' : ($task['priority'] == 2 ? 'i' : '—');
                                    $priorityClass = $task['priority'] == 1 ? 'critical' : ($task['priority'] == 2 ? 'medium' : 'low');
                                ?>
                                    <div class="task-card" data-task-id="<?php echo $task['id']; ?>" data-priority="<?php echo $task['priority']; ?>">
                                        <div class="task-card-header">
                                            <div class="priority-icon <?php echo $priorityClass; ?>"><?php echo $priorityIcon; ?></div>
                                            <span class="priority-label"><?php echo getPriorityName($task['priority']); ?></span>
                                        </div>
                                        <div class="task-header-card">
                                            <div class="task-title-section">
                                                <h3><?php echo htmlspecialchars($task['name']); ?></h3>
                                            </div>
                                            <div class="task-actions">
                                                <button class="btn-icon" onclick="event.stopPropagation(); toggleTask(<?php echo $task['id']; ?>, 1)" title="Отметить выполненной">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                        <path d="M13 4L6 11L3 8"/>
                                                    </svg>
                                                </button>
                                                <?php if ($task['user_id'] == $user_id || isAdmin()): ?>
                                                    <?php if ($task['user_id'] == $user_id): ?>
                                                        <button class="btn-icon" onclick="event.stopPropagation(); editTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars(addslashes($task['name'])); ?>', '<?php echo htmlspecialchars(addslashes($task['description'] ?? '')); ?>', <?php echo $task['priority']; ?>)" title="Редактировать">
                                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                                <path d="M11.5 2.5a2.121 2.121 0 0 1 3 3L6.5 13.5l-4 1 1-4L11.5 2.5z"/>
                                                            </svg>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn-icon" onclick="event.stopPropagation(); return deleteTask(<?php echo $task['id']; ?>, event)" title="Удалить">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                            <path d="M3 6h10M6 6v6m4-6v6M5 6l1-3h4l1 3M5 6h6"/>
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($task['description']): ?>
                                            <p class="task-description"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                        <?php endif; ?>
                                        <div class="task-footer">
                                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                                <small>
                                                    <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($task['creator_name']); ?></span>
                                                </small>
                                                <small style="color: var(--text-muted);">
                                                    <?php echo date('d M., H:i', strtotime($task['created_at'])); ?>
                                                </small>
                                            </div>
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
                                <?php foreach ($completedTasks as $task): 
                                    $priorityIcon = $task['priority'] == 1 ? '!' : ($task['priority'] == 2 ? 'i' : '—');
                                    $priorityClass = $task['priority'] == 1 ? 'critical' : ($task['priority'] == 2 ? 'medium' : 'low');
                                ?>
                                    <div class="task-card task-completed" data-task-id="<?php echo $task['id']; ?>" data-priority="<?php echo $task['priority']; ?>">
                                        <div class="task-card-header">
                                            <div class="priority-icon <?php echo $priorityClass; ?>"><?php echo $priorityIcon; ?></div>
                                            <span class="priority-label"><?php echo getPriorityName($task['priority']); ?></span>
                                        </div>
                                        <div class="task-header-card">
                                            <div class="task-title-section">
                                                <h3 style="text-decoration: line-through; opacity: 0.6;"><?php echo htmlspecialchars($task['name']); ?></h3>
                                            </div>
                                            <div class="task-actions">
                                                <button class="btn-icon" onclick="event.stopPropagation(); toggleTask(<?php echo $task['id']; ?>, 0)" title="Вернуть в активные">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                        <path d="M11 5L8 8L5 5M8 11V8"/>
                                                    </svg>
                                                </button>
                                                <?php if ($task['user_id'] == $user_id || isAdmin()): ?>
                                                    <button class="btn-icon" onclick="event.stopPropagation(); return deleteTask(<?php echo $task['id']; ?>, event)" title="Удалить">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                            <path d="M3 6h10M6 6v6m4-6v6M5 6l1-3h4l1 3M5 6h6"/>
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($task['description']): ?>
                                            <p class="task-description" style="opacity: 0.6;"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                        <?php endif; ?>
                                        <div class="task-footer">
                                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                                <small>
                                                    <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($task['creator_name']); ?></span>
                                                </small>
                                                <small style="color: var(--text-muted);">
                                                    Выполнена: <?php echo date('d M., H:i', strtotime($task['updated_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    </div>

    <!-- Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Создать заметку</h2>
                <button class="btn-icon" onclick="closeTaskModal()" style="font-size: 24px; color: var(--text-secondary);">&times;</button>
            </div>
            <form id="taskForm" method="POST" action="save_task.php">
                <input type="hidden" id="taskId" name="task_id" value="">
                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                <div class="form-group">
                    <label for="taskName">Заголовок</label>
                    <input type="text" id="taskName" name="name" required maxlength="255" placeholder="Введите заголовок заметки...">
                </div>
                <div class="form-group">
                    <label for="taskDescription">Содержание</label>
                    <textarea id="taskDescription" name="description" rows="5" maxlength="500" placeholder="Опишите детали заметки..."></textarea>
                </div>
                <div class="form-group">
                    <label>Важность</label>
                    <div class="priority-options">
                        <div class="priority-option" data-priority="1" onclick="selectPriority(1)">
                            <div class="priority-option-icon critical">!</div>
                            <span class="priority-option-label">Критическая</span>
                        </div>
                        <div class="priority-option selected" data-priority="2" onclick="selectPriority(2)">
                            <div class="priority-option-icon medium">i</div>
                            <span class="priority-option-label">Средней важности</span>
                        </div>
                        <div class="priority-option" data-priority="3" onclick="selectPriority(3)">
                            <div class="priority-option-icon low">—</div>
                            <span class="priority-option-label">По мере возможности</span>
                        </div>
                    </div>
                    <input type="hidden" id="taskPriority" name="priority" value="2" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать заметку</button>
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
                modalTitle.textContent = 'Редактировать заметку';
                document.getElementById('taskId').value = taskId;
                document.getElementById('taskName').value = taskName;
                document.getElementById('taskDescription').value = taskDescription;
                document.getElementById('taskPriority').value = priority;
                selectPriority(priority);
            } else {
                modalTitle.textContent = 'Создать заметку';
                form.reset();
                document.getElementById('taskId').value = '';
                document.getElementById('taskPriority').value = 2;
                selectPriority(2);
            }
            
            modal.classList.add('show');
            if (document.getElementById('taskName')) document.getElementById('taskName').focus();
        }
        
        function selectPriority(priority) {
            if (typeof priority === 'string') priority = parseInt(priority);
            
            document.querySelectorAll('.priority-option').forEach(opt => opt.classList.remove('selected'));
            document.querySelectorAll('.priority-option[data-priority="' + priority + '"]').forEach(opt => opt.classList.add('selected'));
            
            const taskPriorityInput = document.getElementById('taskPriority');
            if (taskPriorityInput) {
                taskPriorityInput.value = priority;
            }
        }
        
        function filterByPriority(priority) {
            const cards = document.querySelectorAll('.task-card');
            cards.forEach(card => {
                if (priority === 'all') {
                    card.style.display = '';
                } else {
                    const cardPriority = card.getAttribute('data-priority');
                    card.style.display = cardPriority == priority ? '' : 'none';
                }
            });
            
            document.querySelectorAll('.sidebar-section:last-child .sidebar-item').forEach(item => item.classList.remove('active'));
            if (priority === 'all') {
                document.querySelectorAll('.sidebar-section:last-child .sidebar-item')[0].classList.add('active');
            } else {
                const items = document.querySelectorAll('.sidebar-section:last-child .sidebar-item');
                if (items[priority]) items[priority].classList.add('active');
            }
        }
        
        function filterTasks() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.task-card');
            cards.forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const description = card.querySelector('.task-description')?.textContent.toLowerCase() || '';
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }
        
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            const avatar = document.querySelector('.user-avatar');
            
            if (menu.style.display === 'none' || menu.style.display === '') {
                menu.style.display = 'block';
                
                // Always align right edge of menu with right edge of avatar
                menu.style.right = '0';
                menu.style.left = 'auto';
                
                // Check if menu goes off screen and adjust if needed
                setTimeout(() => {
                    const avatarRect = avatar.getBoundingClientRect();
                    const menuRect = menu.getBoundingClientRect();
                    const windowWidth = window.innerWidth;
                    
                    if (menuRect.right > windowWidth) {
                        // If menu goes off screen, shift it left
                        const overflow = menuRect.right - windowWidth;
                        menu.style.right = `-${overflow + 10}px`;
                    }
                }, 0);
            } else {
                menu.style.display = 'none';
            }
        }
        
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const userAvatar = document.querySelector('.user-avatar');
            if (userMenu && userAvatar && !userMenu.contains(event.target) && !userAvatar.contains(event.target)) {
                userMenu.style.display = 'none';
            }
        });
        
        document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);
        
        window.onclick = function(event) {
            const modal = document.getElementById('taskModal');
            if (event.target === modal) {
                closeTaskModal();
            }
        }
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeTaskModal();
            }
        });

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

        function deleteTask(taskId, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            if (!confirm('Вы уверены, что хотите удалить эту задачу?')) {
                return false;
            }

            const groupId = <?php echo $group_id; ?>;
            const url = 'delete_task.php?id=' + encodeURIComponent(taskId) + (groupId ? '&group_id=' + encodeURIComponent(groupId) : '');
            
            window.location.href = url;
            return false;
        }

        window.onclick = function(event) {
            const modal = document.getElementById('taskModal');
            if (event.target == modal) {
                closeTaskModal();
            }
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


        const taskForm = document.getElementById('taskForm');
        if (taskForm) {
            taskForm.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton && !submitButton.disabled) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Сохранение...';
                }
            });
        }
    </script>
</body>
</html>

