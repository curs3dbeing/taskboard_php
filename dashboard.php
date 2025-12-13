<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed. Please check the configuration.");
}

$user_id = getCurrentUserId();

$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userEmail = $stmt->fetchColumn();

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
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotesHub - Командные заметки</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="main-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header-mobile">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon">N</div>
                    <div class="sidebar-logo-text">
                        <h1>NotesHub</h1>
                        <p>Командные заметки</p>
                    </div>
                </div>
                <button class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Закрыть меню">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-header">
                    <h3>ГРУППЫ</h3>
                </div>
                <a href="dashboard.php" class="sidebar-item active">
                    <span class="sidebar-item-text">Все заметки</span>
                    <svg class="sidebar-item-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <path d="M6 4l4 4-4 4"/>
                    </svg>
                </a>
                <?php foreach ($userGroups as $group): ?>
                    <a href="group_dashboard.php?group_id=<?php echo $group['id']; ?>" class="sidebar-item">
                        <span class="sidebar-item-dot purple"></span>
                        <span class="sidebar-item-text"><?php echo htmlspecialchars($group['name']); ?></span>
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
                </div>
                <div class="search-bar">
                    <svg class="search-bar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <circle cx="7" cy="7" r="4"/>
                        <path d="M10 10l3 3"/>
                    </svg>
                    <input type="text" placeholder="Поиск по заметкам, тегам..." id="searchInput" oninput="filterTasks()">
                </div>
                <div class="header-right">
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
                            <a href="groups.php" class="btn btn-secondary" style="width: 100%; margin: 4px 0; text-align: left; padding: 10px 12px; font-size: 14px; box-sizing: border-box; display: flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                    <path d="M12 4v8M4 4v8M2 2h12v12H2z"/>
                                </svg>
                                Группы
                            </a>
                            <a href="groups.php" class="btn btn-secondary" style="width: 100%; margin: 4px 0; text-align: left; padding: 10px 12px; font-size: 14px; box-sizing: border-box; display: flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                    <path d="M12 4v8M4 4v8M2 2h12v12H2z"/>
                                </svg>
                                Создать группу
                            </a>
                            <?php if (isAdmin()): ?>
                                <a href="admin_users.php" class="btn btn-secondary" style="width: 100%; margin: 4px 0; text-align: left; padding: 10px 12px; font-size: 14px; box-sizing: border-box; display: flex; align-items: center; gap: 8px;">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                        <path d="M8 2v12M2 8h12"/>
                                    </svg>
                                    Админ-панель
                                </a>
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

                <div class="task-section">
                    <div class="task-header" style="margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px;">
                        <h2 style="color: var(--text-primary); font-size: 24px; font-weight: 700; margin: 0;">Мои задачи</h2>
                        <button class="btn btn-primary" onclick="openTaskModal()" style="padding: 10px 20px; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                <path d="M8 4v8M4 8h8"/>
                            </svg>
                            Создать заметку
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="tabs" style="margin-bottom: 24px;">
                        <button class="tab-btn active" onclick="switchPersonalTab('active')" id="personal-tab-active">
                            Активные задачи <span class="tab-count">(<?php echo count($tasks); ?>)</span>
                        </button>
                        <button class="tab-btn" onclick="switchPersonalTab('completed')" id="personal-tab-completed">
                            Выполненные <span class="tab-count">(<?php echo count($completedTasks); ?>)</span>
                        </button>
                    </div>

                    <!-- Active Tasks -->
                    <div class="personal-tab-content active" id="personal-content-active">
                        <div class="tasks-grid" id="tasksGrid">
                            <?php if (empty($tasks)): ?>
                                <div class="empty-state">
                                    <p>У вас еще нет активных задач. Создайте ее сейчас!</p>
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
                                                <button class="btn-icon" onclick="event.stopPropagation(); editTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars(addslashes($task['name'])); ?>', '<?php echo htmlspecialchars(addslashes($task['description'] ?? '')); ?>', <?php echo $task['priority']; ?>)" title="Редактировать">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                        <path d="M11.5 2.5a2.121 2.121 0 0 1 3 3L6.5 13.5l-4 1 1-4L11.5 2.5z"/>
                                                    </svg>
                                                </button>
                                                <button class="btn-icon" onclick="event.stopPropagation(); deleteTask(<?php echo $task['id']; ?>)" title="Удалить">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                        <path d="M3 6h10M6 6v6m4-6v6M5 6l1-3h4l1 3M5 6h6"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <?php if ($task['description']): ?>
                                            <p class="task-description"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                        <?php endif; ?>
                                        <div class="task-footer">
                                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                                <small>
                                                    <span style="color: var(--text-secondary);">Вы</span>
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
                    
                    <!-- Completed Tasks -->
                    <div class="personal-tab-content" id="personal-content-completed">
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
                                                <button class="btn-icon" onclick="event.stopPropagation(); deleteTask(<?php echo $task['id']; ?>)" title="Удалить">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                        <path d="M3 6h10M6 6v6m4-6v6M5 6l1-3h4l1 3M5 6h6"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <?php if ($task['description']): ?>
                                            <p class="task-description" style="opacity: 0.6;"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                        <?php endif; ?>
                                        <div class="task-footer">
                                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                                <small>
                                                    <span style="color: var(--text-secondary);">Вы</span>
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

    <!-- Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Создать заметку</h2>
                <button class="btn-icon" onclick="closeTaskModal()" style="font-size: 24px; color: var(--text-secondary);">&times;</button>
            </div>
            <form id="taskForm" method="POST" action="save_task.php">
                <input type="hidden" id="taskId" name="task_id" value="">
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
        
        function selectPriority(priority) {
            document.querySelectorAll('.priority-option').forEach(opt => opt.classList.remove('selected'));
            document.querySelectorAll('.priority-option[data-priority="' + priority + '"]').forEach(opt => opt.classList.add('selected'));
            document.getElementById('taskPriority').value = priority;
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
            
            // Update sidebar active state
            document.querySelectorAll('.sidebar-section:last-child .sidebar-item').forEach(item => item.classList.remove('active'));
            if (priority === 'all') {
                document.querySelectorAll('.sidebar-section:last-child .sidebar-item')[0].classList.add('active');
            } else {
                const items = document.querySelectorAll('.sidebar-section:last-child .sidebar-item');
                items[priority].classList.add('active');
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
            
            // Close sidebar when clicking outside on mobile
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const burger = document.querySelector('.burger-menu');
            if (window.innerWidth <= 1024 && sidebar && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && !burger.contains(event.target) && event.target !== sidebarOverlay) {
                    toggleSidebar();
                }
            }
        });
        

        document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);
    </script>
    <script src="script.js"></script>
</body>
</html>

