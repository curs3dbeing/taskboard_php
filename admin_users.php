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
    <title>Управление пользователями - NotesHub</title>
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
            overflow-x: hidden;
        }
        
        body {
            overflow-x: hidden;
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
            padding: 24px;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow);
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
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
            background: var(--background-secondary);
            color: var(--text-primary);
        }
        .search-group input:focus,
        .search-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.1);
        }
        .search-group input::placeholder {
            color: var(--text-muted);
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
        .task-section h2 {
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 24px;
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
            background: var(--card-background);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
            color: var(--text-primary);
        }
        .users-table th {
            background: var(--background-secondary);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .users-table tbody tr {
            transition: background 0.2s ease;
        }
        .users-table tbody tr:hover {
            background: var(--card-hover);
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
            background: rgba(239, 68, 68, 0.2);
            color: var(--priority-critical);
            border: 1px solid var(--priority-critical);
        }
        .badge-user {
            background: rgba(107, 114, 128, 0.2);
            color: var(--text-secondary);
            border: 1px solid var(--text-secondary);
        }
        .badge-blocked {
            background: rgba(239, 68, 68, 0.2);
            color: var(--priority-critical);
            border: 1px solid var(--priority-critical);
        }
        .badge-active {
            background: rgba(16, 185, 129, 0.2);
            color: var(--priority-low);
            border: 1px solid var(--priority-low);
        }
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 140px;
        }
        .action-buttons .btn {
            width: 100%;
            text-align: center;
            box-sizing: border-box;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
            white-space: nowrap;
        }
        .btn-warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--priority-high);
            border: 1px solid var(--priority-high);
        }
        .btn-warning:hover {
            background: rgba(245, 158, 11, 0.3);
        }
        .btn-danger {
            background-color: var(--error-color);
            color: white;
        }
        .btn-danger:hover {
            background-color: #dc2626;
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
            max-height: 400px;
            overflow-y: auto;
            margin-top: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .tasks-list::-webkit-scrollbar {
            width: 8px;
        }
        .tasks-list::-webkit-scrollbar-track {
            background: var(--background-secondary);
            border-radius: 4px;
        }
        .tasks-list::-webkit-scrollbar-thumb {
            background: var(--border-light);
            border-radius: 4px;
        }
        .tasks-list::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        .task-item {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            transition: background 0.2s ease;
        }
        .task-item:hover {
            background: var(--card-hover);
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
            color: var(--text-primary);
        }
        .task-item > div strong {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-size: 14px;
        }
        .task-item > div small {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            display: block;
            line-height: 1.4;
            color: var(--text-secondary);
            font-size: 12px;
        }
        .task-item button {
            flex-shrink: 0;
            white-space: nowrap;
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
                padding: 8px;
                overflow-x: hidden;
            }
            
            .task-section {
                padding: 0;
            }
            
            .task-section h2 {
                font-size: 18px;
                margin-bottom: 12px;
            }
            
            .search-container {
                padding: 12px;
                margin-bottom: 12px;
            }
            
            .search-row {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .search-group {
                min-width: 0;
            }
            
            .search-group label {
                font-size: 11px;
                margin-bottom: 4px;
            }
            
            .search-group input,
            .search-group select {
                padding: 6px 8px;
                font-size: 12px;
            }
            
            .search-actions {
                grid-column: 1 / -1;
                width: 100%;
            }
            
            .search-actions .btn {
                width: 100%;
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .search-results {
                font-size: 11px;
                margin-top: 10px;
                padding-top: 10px;
            }
            
            .table-wrapper {
                margin: 0 -12px;
                padding: 0 12px;
            }
            
            .users-table {
                min-width: 700px;
                font-size: 11px;
            }
            
            .users-table th,
            .users-table td {
                padding: 8px 4px;
                font-size: 10px;
            }
            
            .users-table th {
                font-size: 9px;
                padding: 6px 4px;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
                min-width: 100px;
                gap: 6px;
            }
            
            .action-buttons .btn {
                width: 100%;
                font-size: 11px;
                padding: 6px 8px;
            }
            
            .header {
                padding: 8px 10px;
                flex-wrap: wrap;
                gap: 8px;
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
                gap: 6px;
            }
            
            .header-right .btn {
                padding: 6px 10px;
                font-size: 11px;
            }
            
            .badge {
                font-size: 9px;
                padding: 2px 6px;
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
                margin-top: 8px;
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
            .main-content {
                padding: 6px;
                overflow-x: hidden;
            }
            
            .task-section h2 {
                font-size: 16px;
                margin-bottom: 10px;
            }
            
            .search-container {
                padding: 10px;
                margin-bottom: 10px;
            }
            
            .search-row {
                grid-template-columns: 1fr;
                gap: 6px;
            }
            
            .search-group label {
                font-size: 10px;
                margin-bottom: 3px;
            }
            
            .search-group input,
            .search-group select {
                padding: 6px 8px;
                font-size: 11px;
            }
            
            .search-actions .btn {
                padding: 6px 10px;
                font-size: 11px;
            }
            
            .search-results {
                font-size: 10px;
                margin-top: 8px;
                padding-top: 8px;
            }
            
            .table-wrapper {
                margin: 0 -6px;
                padding: 0 6px;
            }
            
            .users-table {
                min-width: 650px;
                font-size: 10px;
            }
            
            .users-table th,
            .users-table td {
                padding: 6px 3px;
                font-size: 9px;
            }
            
            .users-table th {
                font-size: 8px;
                padding: 5px 3px;
            }
            
            .btn-small {
                padding: 5px 8px;
                font-size: 10px;
            }
            
            .action-buttons {
                min-width: 90px;
                gap: 4px;
            }
            
            .action-buttons .btn {
                font-size: 10px;
                padding: 5px 6px;
            }
            
            .badge {
                font-size: 8px;
                padding: 2px 4px;
            }
            
            .header {
                padding: 6px 8px;
            }
            
            .header-right .btn {
                padding: 4px 6px;
                font-size: 9px;
            }
            
            .user-avatar {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }
            
            .burger-menu {
                padding: 4px;
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
                padding: 10px;
            }
            
            .task-item > div {
                min-width: 0;
                width: 100%;
            }
            
            .task-item button {
                width: 100%;
                margin-top: 8px;
            }
            
            .search-bar input {
                font-size: 12px;
                padding: 6px 10px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }
            
            .main-layout {
                overflow-x: hidden;
            }
            
            .content-wrapper {
                overflow-x: hidden;
            }
        }
    </style>
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
                <a href="admin_users.php" class="sidebar-item active">
                    <span class="sidebar-item-text">Пользователи</span>
                    <svg class="sidebar-item-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                        <path d="M6 4l4 4-4 4"/>
                    </svg>
                </a>
                <a href="admin_groups.php" class="sidebar-item">
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
                        <input type="text" placeholder="Поиск пользователей..." id="headerSearch" oninput="filterUsers()">
                    </div>
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
                            <a href="dashboard.php" class="btn btn-secondary" style="width: 100%; margin: 4px 0; text-align: left; padding: 10px 12px; font-size: 14px; box-sizing: border-box; display: flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                    <path d="M2 4h12M2 8h12M2 12h12"/>
                                </svg>
                                Мои задачи
                            </a>
                            <a href="groups.php" class="btn btn-secondary" style="width: 100%; margin: 4px 0; text-align: left; padding: 10px 12px; font-size: 14px; box-sizing: border-box; display: flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                    <path d="M12 4v8M4 4v8M2 2h12v12H2z"/>
                                </svg>
                                Группы
                            </a>
                            <a href="admin_users.php" class="btn btn-secondary" style="width: 100%; margin: 4px 0; text-align: left; padding: 10px 12px; font-size: 14px; box-sizing: border-box; display: flex; align-items: center; gap: 8px; background: rgba(168, 85, 247, 0.1); border: 1px solid var(--primary-color);">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                    <path d="M8 2v12M2 8h12"/>
                                </svg>
                                Админ-панель
                            </a>
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
            </main>
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
            const sidebarCloseBtn = document.querySelector('.sidebar-close-btn');
            if (window.innerWidth <= 1024 && sidebar && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && 
                    !burger.contains(event.target) && 
                    !sidebarCloseBtn.contains(event.target) &&
                    event.target !== sidebarOverlay) {
                    toggleSidebar();
                }
            }
        });
        
        document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);
        
        function filterUsers() {
            const headerSearch = document.getElementById('headerSearch');
            const usernameFilter = (document.getElementById('searchUsername')?.value || '').toLowerCase().trim();
            const emailFilter = (document.getElementById('searchEmail')?.value || '').toLowerCase().trim();
            const roleFilter = document.getElementById('searchRole')?.value || '';
            const statusFilter = document.getElementById('searchStatus')?.value || '';
            const headerSearchValue = headerSearch ? headerSearch.value.toLowerCase().trim() : '';
            
            const rows = document.querySelectorAll('#usersTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const username = row.getAttribute('data-username') || '';
                const email = row.getAttribute('data-email') || '';
                const role = row.getAttribute('data-role') || '';
                const status = row.getAttribute('data-status') || '';
                
                const matchesHeaderSearch = !headerSearchValue || username.includes(headerSearchValue) || email.includes(headerSearchValue);
                const matchesUsername = !usernameFilter || username.includes(usernameFilter);
                const matchesEmail = !emailFilter || email.includes(emailFilter);
                const matchesRole = !roleFilter || role === roleFilter;
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesHeaderSearch && matchesUsername && matchesEmail && matchesRole && matchesStatus) {
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
                                const taskNameEscaped = escapeJsString(task.name);
                                html += `
                                    <div class="task-item">
                                        <div>
                                            <strong>${escapeHtml(task.name)}</strong>
                                            ${task.description ? '<br><small>' + escapeHtml(task.description) + '</small>' : ''}
                                            <br><small>Приоритет: ${getPriorityName(task.priority)} | 
                                            ${task.completed == 1 ? 'Выполнена' : 'Активна'} | 
                                            Создана: ${task.created_at}</small>
                                        </div>
                                        <button class="btn btn-danger btn-small" onclick="deleteUserTask(${task.id}, ${userId}, '${taskNameEscaped}')">Удалить</button>
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
        
        function escapeJsString(str) {
            if (!str) return '';
            return str.toString()
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/"/g, '\\"')
                .replace(/\n/g, '\\n')
                .replace(/\r/g, '\\r')
                .replace(/\t/g, '\\t');
        }


        document.getElementById('tasksModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeTasksModal();
            }
        });
    </script>
</body>
</html>

