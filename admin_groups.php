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

$stmt = $pdo->prepare("SELECT g.*, 
                       u.username as owner_username, 
                       u.email as owner_email,
                       COUNT(DISTINCT gm.user_id) as member_count
                       FROM user_groups g
                       LEFT JOIN users u ON g.owner_id = u.id
                       LEFT JOIN group_members gm ON g.id = gm.group_id
                       GROUP BY g.id
                       ORDER BY g.created_at DESC");
$stmt->execute();
$groups = $stmt->fetchAll();

$groupStats = [];
foreach ($groups as $group) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed FROM tasks WHERE group_id = ?");
    $stmt->execute([$group['id']]);
    $stats = $stmt->fetch();
    $groupStats[$group['id']] = $stats;
}

// Get user email
$user_id = getCurrentUserId();
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userEmail = $stmt->fetchColumn();

// Get user groups for sidebar
$stmt = $pdo->prepare("SELECT g.* FROM user_groups g WHERE g.owner_id = ? 
                       UNION 
                       SELECT g.* FROM user_groups g 
                       INNER JOIN group_members gm ON g.id = gm.group_id 
                       WHERE gm.user_id = ? 
                       ORDER BY created_at DESC");
$stmt->execute([$user_id, $user_id]);
$userGroups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление группами - NotesHub</title>
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
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .group-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            word-wrap: break-word;
            overflow-wrap: break-word;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: all 0.3s ease;
        }
        .group-card:hover {
            border-color: var(--border-light);
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
            background: var(--card-hover);
        }
        .group-card h3 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .group-card p {
            color: var(--text-secondary);
            margin: 10px 0;
            font-size: 14px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            flex: 1;
        }
        .group-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-muted);
        }
        .group-meta-item {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 5px;
        }
        .group-meta-item span:last-child {
            text-align: right;
            word-break: break-word;
        }
        .group-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
            padding-top: 15px;
            flex-wrap: wrap;
        }
        .group-actions .btn {
            flex: 1;
            min-width: 120px;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }
        .btn-danger {
            background-color: var(--error-color);
            color: white;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }
        .task-section h2 {
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 24px;
        }
        .owner-info {
            font-weight: 600;
            color: var(--text-primary);
            word-break: break-word;
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .content-wrapper {
                margin-left: 0;
            }
            
            .burger-menu {
                display: flex !important;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 12px;
            }
            
            .task-section {
                padding: 0;
            }
            
            .task-section h2 {
                font-size: 20px;
                margin-bottom: 16px;
            }
            
            .groups-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .group-card {
                padding: 16px;
            }
            
            .group-card h3 {
                font-size: 17px;
            }
            
            .group-card p {
                font-size: 13px;
            }
            
            .group-meta {
                font-size: 11px;
                margin-top: 12px;
                padding-top: 12px;
            }
            
            .group-meta-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            
            .group-meta-item span:last-child {
                text-align: left;
            }
            
            .group-actions {
                flex-direction: column;
                gap: 8px;
                margin-top: 12px;
                padding-top: 12px;
            }
            
            .group-actions .btn {
                width: 100%;
                font-size: 12px;
                padding: 8px 12px;
            }
            
            .header {
                padding: 10px 12px;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .header-left {
                flex: 1;
                min-width: 0;
                order: 2;
            }
            
            .search-bar {
                max-width: 100%;
                order: 3;
                width: 100%;
            }
            
            .header-right {
                order: 1;
                width: 100%;
                justify-content: space-between;
                gap: 8px;
            }
            
            .header-right .btn {
                padding: 6px 10px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 8px;
            }
            
            .task-section h2 {
                font-size: 18px;
                margin-bottom: 12px;
            }
            
            .groups-grid {
                gap: 12px;
            }
            
            .group-card {
                padding: 14px;
            }
            
            .group-card h3 {
                font-size: 16px;
                margin-bottom: 8px;
            }
            
            .group-card p {
                font-size: 12px;
                margin: 8px 0;
            }
            
            .group-meta {
                font-size: 10px;
                margin-top: 10px;
                padding-top: 10px;
                gap: 6px;
            }
            
            .group-meta-item {
                gap: 3px;
            }
            
            .group-actions {
                gap: 6px;
                margin-top: 10px;
                padding-top: 10px;
            }
            
            .group-actions .btn {
                font-size: 11px;
                padding: 6px 10px;
            }
            
            .btn-small {
                padding: 6px 10px;
                font-size: 11px;
            }
            
            .header {
                padding: 8px 10px;
            }
            
            .header-right .btn {
                padding: 5px 8px;
                font-size: 10px;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 14px;
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
                <?php foreach ($userGroups as $g): ?>
                    <a href="group_dashboard.php?group_id=<?php echo $g['id']; ?>" class="sidebar-item">
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
                    <h3>АДМИН-ПАНЕЛЬ</h3>
                </div>
                <a href="admin_users.php" class="sidebar-item">
                    <span class="sidebar-item-text">Пользователи</span>
                    <svg class="sidebar-item-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <path d="M6 4l4 4-4 4"/>
                    </svg>
                </a>
                <a href="admin_groups.php" class="sidebar-item active">
                    <span class="sidebar-item-text">Управление группами</span>
                    <svg class="sidebar-item-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <path d="M6 4l4 4-4 4"/>
                    </svg>
                </a>
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
                    <div class="search-bar" style="max-width: 400px;">
                        <svg class="search-bar-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                            <circle cx="7" cy="7" r="4"/>
                            <path d="M10 10l3 3"/>
                        </svg>
                        <input type="text" placeholder="Поиск групп..." id="headerSearch" oninput="filterGroups()">
                    </div>
                </div>
                <div class="header-right">
                    <button class="btn btn-secondary" onclick="window.location.href='groups.php'" style="padding: 8px 16px; font-size: 14px;">
                        Группы
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
                    <h2>Все группы (<?php echo count($groups); ?>)</h2>
                
                <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <p>Групп пока нет.</p>
                    </div>
                <?php else: ?>
                    <div class="groups-grid">
                        <?php foreach ($groups as $group): ?>
                            <div class="group-card">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <?php if ($group['description']): ?>
                                    <p><?php echo htmlspecialchars($group['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="group-meta">
                                    <div class="group-meta-item">
                                        <span>Владелец:</span>
                                        <span class="owner-info"><?php echo htmlspecialchars($group['owner_username']); ?></span>
                                    </div>
                                    <div class="group-meta-item">
                                        <span>Email владельца:</span>
                                        <span><?php echo htmlspecialchars($group['owner_email']); ?></span>
                                    </div>
                                    <div class="group-meta-item">
                                        <span>Участников:</span>
                                        <span><?php echo $group['member_count']; ?></span>
                                    </div>
                                    <div class="group-meta-item">
                                        <span>Задач (всего/выполнено):</span>
                                        <span>
                                            <?php 
                                            $stats = $groupStats[$group['id']] ?? ['total' => 0, 'completed' => 0];
                                            echo ($stats['total'] ?? 0) . ' / ' . ($stats['completed'] ?? 0);
                                            ?>
                                        </span>
                                    </div>
                                    <div class="group-meta-item">
                                        <span>Создана:</span>
                                        <span><?php echo date('d.m.Y H:i', strtotime($group['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="group-actions">
                                    <a href="group_dashboard.php?group_id=<?php echo $group['id']; ?>" class="btn btn-primary btn-small">Просмотреть</a>
                                    <button class="btn btn-danger btn-small" 
                                            onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
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
        
        function filterGroups() {
            const headerSearch = document.getElementById('headerSearch');
            const searchValue = headerSearch ? headerSearch.value.toLowerCase().trim() : '';
            const cards = document.querySelectorAll('.group-card');
            
            cards.forEach(card => {
                const name = card.querySelector('h3').textContent.toLowerCase();
                const description = card.querySelector('p')?.textContent.toLowerCase() || '';
                if (!searchValue || name.includes(searchValue) || description.includes(searchValue)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
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
        
        function deleteGroup(groupId, groupName) {
            if (!confirm(`Вы уверены, что хотите удалить группу "${groupName}"?\n\nЭто действие удалит группу, всех участников и все задачи группы. Это действие нельзя отменить.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('group_id', groupId);

            fetch('admin_delete_group.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Группа успешно удалена!');
                    location.reload();
                } else {
                    alert(data.message || 'Ошибка при удалении группы');
                }
            })
            .catch(error => {
                alert('Произошла ошибка: ' + error);
            });
        }

    </script>
</body>
</html>

