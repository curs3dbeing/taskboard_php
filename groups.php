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

$stmt = $pdo->prepare("SELECT g.*, COUNT(DISTINCT gm.user_id) as member_count 
                       FROM user_groups g 
                       LEFT JOIN group_members gm ON g.id = gm.group_id 
                       WHERE g.owner_id = ? 
                       GROUP BY g.id 
                       ORDER BY g.created_at DESC");
$stmt->execute([$user_id]);
$ownedGroups = $stmt->fetchAll();


$stmt = $pdo->prepare("SELECT g.*, COUNT(DISTINCT gm.user_id) as member_count 
                       FROM user_groups g 
                       INNER JOIN group_members gm ON g.id = gm.group_id 
                       WHERE gm.user_id = ? AND g.owner_id != ? 
                       GROUP BY g.id 
                       ORDER BY g.created_at DESC");
$stmt->execute([$user_id, $user_id]);
$memberGroups = $stmt->fetchAll();

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
    <title>Управление группами - NotesHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .groups-section {
            margin-bottom: 40px;
        }
        .groups-section h2 {
            margin-bottom: 20px;
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 700;
        }
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .group-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
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
        }
        .group-card p {
            color: var(--text-secondary);
            margin: 10px 0;
            font-size: 14px;
            flex: 1;
        }
        .group-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-muted);
        }
        .group-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
            margin-top: auto;
        }
        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }
        .btn-danger {
            background-color: var(--error-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3);
        }
        .create-group-form {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            margin-bottom: 32px;
        }
        .create-group-form h2 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .member-list {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        .member-list h4 {
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 14px;
        }
        .member-item:last-child {
            border-bottom: none;
        }
        .member-item strong {
            color: var(--text-primary);
        }
        .add-member-form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .add-member-form input {
            flex: 1;
            min-width: 0;
            background: var(--background-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        @media (max-width: 768px) {
            .group-actions {
                flex-direction: column;
                gap: 8px;
            }
            .group-actions .btn {
                width: 100%;
                box-sizing: border-box;
            }
            .add-member-form {
                flex-direction: column;
            }
            .add-member-form input {
                width: 100%;
            }
            .add-member-form .btn {
                width: 100%;
            }
            .form-row {
                flex-direction: column;
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
                    <h3>ВАЖНОСТЬ</h3>
                </div>
                <div class="sidebar-item active" onclick="window.location.href='dashboard.php'">
                    <div class="sidebar-item-icon">O</div>
                    <span class="sidebar-item-text">Все</span>
                </div>
            </div>
            
            <div class="sidebar-footer-mobile">
                <a href="logout.php" class="sidebar-item sidebar-logout">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" style="flex-shrink: 0;">
                        <path d="M6 14H3a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3M10 12l4-4-4-4M14 8H6"/>
                    </svg>
                    <span class="sidebar-item-text">Выйти</span>
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

                <!-- Форма создания группы -->
                <div class="create-group-form">
                    <h2>Создать новую группу</h2>
                <form method="POST" action="create_group.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="groupName">Название группы</label>
                            <input type="text" id="groupName" name="name" required maxlength="100" placeholder="Введите название группы">
                        </div>
                        <div class="form-group">
                            <label for="groupDescription">Описание</label>
                            <input type="text" id="groupDescription" name="description" maxlength="255" placeholder="Описание группы (необязательно)">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Создать группу</button>
                </form>
            </div>

            <div class="groups-section">
                <h2>Мои группы (<?php echo count($ownedGroups); ?>)</h2>
                <?php if (empty($ownedGroups)): ?>
                    <div class="empty-state">
                        <p>У вас пока нет созданных групп. Создайте первую группу выше!</p>
                    </div>
                <?php else: ?>
                    <div class="groups-grid">
                        <?php foreach ($ownedGroups as $group): ?>
                            <div class="group-card">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <?php if ($group['description']): ?>
                                    <p><?php echo htmlspecialchars($group['description']); ?></p>
                                <?php endif; ?>
                                <div class="group-meta">
                                    <span>Участников: <?php echo $group['member_count']; ?></span>
                                    <span>Создана: <?php echo date('d.m.Y', strtotime($group['created_at'])); ?></span>
                                </div>
                                <div class="group-actions">
                                    <a href="group_dashboard.php?group_id=<?php echo $group['id']; ?>" class="btn btn-primary btn-small">Открыть</a>
                                    <button class="btn btn-secondary btn-small" onclick="openManageMembers(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">Управление</button>
                                    <button class="btn btn-danger btn-small" onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')" title="Удалить группу">Удалить</button>
                                </div>
                                
                                <!-- Список участников -->
                                <div class="member-list" id="members-<?php echo $group['id']; ?>" style="display: none;">
                                    <h4>Участники группы</h4>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT u.id, u.username, u.email, gm.joined_at 
                                                          FROM group_members gm 
                                                          INNER JOIN users u ON gm.user_id = u.id 
                                                          WHERE gm.group_id = ? 
                                                          ORDER BY gm.joined_at DESC");
                                    $stmt->execute([$group['id']]);
                                    $members = $stmt->fetchAll();
                                    

                                    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
                                    $stmt->execute([$group['owner_id']]);
                                    $owner = $stmt->fetch();
                                    ?>
                                    <div class="member-item">
                                        <span><strong><?php echo htmlspecialchars($owner['username']); ?></strong> (владелец)</span>
                                    </div>
                                    <?php foreach ($members as $member): ?>
                                        <div class="member-item">
                                            <span><?php echo htmlspecialchars($member['username']); ?></span>
                                            <button class="btn-icon" onclick="removeMember(<?php echo $group['id']; ?>, <?php echo $member['id']; ?>, '<?php echo htmlspecialchars(addslashes($member['username'])); ?>')" title="Удалить">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor">
                                                    <path d="M3 6h10M6 6v6m4-6v6M5 6l1-3h4l1 3M5 6h6"/>
                                                </svg>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Форма добавления участника -->
                                    <div class="add-member-form">
                                        <input type="text" id="member-email-<?php echo $group['id']; ?>" placeholder="Email пользователя" required>
                                        <button type="button" class="btn btn-primary btn-small" onclick="addMember(<?php echo $group['id']; ?>)">Добавить</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Группы, в которых я участник -->
            <div class="groups-section">
                <h2>Группы, в которых я состою (<?php echo count($memberGroups); ?>)</h2>
                <?php if (empty($memberGroups)): ?>
                    <div class="empty-state">
                        <p>Вы пока не состоите ни в одной группе.</p>
                    </div>
                <?php else: ?>
                    <div class="groups-grid">
                        <?php foreach ($memberGroups as $group): ?>
                            <div class="group-card">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <?php if ($group['description']): ?>
                                    <p><?php echo htmlspecialchars($group['description']); ?></p>
                                <?php endif; ?>
                                <div class="group-meta">
                                    <span>Участников: <?php echo $group['member_count']; ?></span>
                                    <span>Создана: <?php echo date('d.m.Y', strtotime($group['created_at'])); ?></span>
                                </div>
                                <div class="group-actions">
                                    <a href="group_dashboard.php?group_id=<?php echo $group['id']; ?>" class="btn btn-primary btn-small">Открыть</a>
                                    <button class="btn btn-danger btn-small" onclick="leaveGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')" title="Покинуть группу">Покинуть</button>
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
                
                menu.style.right = '0';
                menu.style.left = 'auto';
                
                setTimeout(() => {
                    const avatarRect = avatar.getBoundingClientRect();
                    const menuRect = menu.getBoundingClientRect();
                    const windowWidth = window.innerWidth;
                    
                    if (menuRect.right > windowWidth) {
                        const overflow = menuRect.right - windowWidth;
                        menu.style.right = `-${overflow + 10}px`;
                    }
                }, 0);
            } else {
                menu.style.display = 'none';
            }
        }
        
        function filterGroups() {
            const cards = document.querySelectorAll('.group-card');
            cards.forEach(card => {
                card.style.display = '';
            });
        }
        
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const userAvatar = document.querySelector('.user-avatar');
            if (userMenu && userAvatar && !userMenu.contains(event.target) && !userAvatar.contains(event.target)) {
                userMenu.style.display = 'none';
            }
            
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
        
        function scrollToCreateGroup() {
            const form = document.querySelector('.create-group-form');
            if (form) {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                const nameInput = document.getElementById('groupName');
                if (nameInput) {
                    setTimeout(() => nameInput.focus(), 300);
                }
            }
        }
        
        function openManageMembers(groupId, groupName) {
            const memberList = document.getElementById('members-' + groupId);
            if (memberList.style.display === 'none') {
                memberList.style.display = 'block';
            } else {
                memberList.style.display = 'none';
            }
        }

        function addMember(groupId) {
            const emailInput = document.getElementById('member-email-' + groupId);
            const email = emailInput.value.trim();
            
            if (!email) {
                alert('Введите email пользователя');
                return;
            }

            const formData = new FormData();
            formData.append('group_id', groupId);
            formData.append('email', email);

            fetch('add_member.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Пользователь успешно добавлен в группу!');
                    location.reload();
                } else {
                    alert(data.message || 'Ошибка при добавлении пользователя');
                }
            })
            .catch(error => {
                alert('Произошла ошибка: ' + error);
            });
        }

        function removeMember(groupId, userId, username) {
            if (!confirm('Вы уверены, что хотите удалить ' + username + ' из группы?')) {
                return;
            }

            const formData = new FormData();
            formData.append('group_id', groupId);
            formData.append('user_id', userId);

            fetch('remove_member.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Пользователь успешно удален из группы!');
                    location.reload();
                } else {
                    alert(data.message || 'Ошибка при удалении пользователя');
                }
            })
            .catch(error => {
                alert('Произошла ошибка: ' + error);
            });
        }

        function deleteGroup(groupId, groupName) {
            if (!confirm('Вы уверены, что хотите удалить группу "' + groupName + '"?\n\nЭто действие удалит группу, всех участников и все задачи группы. Это действие нельзя отменить.')) {
                return;
            }

            window.location.href = 'delete_group.php?id=' + groupId;
        }

        function leaveGroup(groupId, groupName) {
            if (!confirm('Вы уверены, что хотите покинуть группу "' + groupName + '"?\n\nПосле выхода вы потеряете доступ к задачам этой группы.')) {
                return;
            }

            window.location.href = 'leave_group.php?group_id=' + groupId;
        }

    </script>
</body>
</html>