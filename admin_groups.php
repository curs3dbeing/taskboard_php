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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление группами</title>
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
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #4a90e2;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .group-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .group-card p {
            color: #6c757d;
            margin: 10px 0;
            font-size: 14px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .group-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #6c757d;
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
            margin-top: 15px;
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
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .owner-info {
            font-weight: 600;
            color: #2c3e50;
            word-break: break-word;
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
            .groups-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .group-card {
                padding: 15px;
            }
            .group-meta-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .group-meta-item span:last-child {
                text-align: left;
            }
            .group-actions {
                flex-direction: column;
            }
            .group-actions .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .groups-grid {
                gap: 10px;
            }
            .group-card {
                padding: 12px;
            }
            .group-card h3 {
                font-size: 16px;
            }
            .group-card p {
                font-size: 12px;
            }
            .group-meta {
                font-size: 11px;
            }
            .btn-small {
                padding: 5px 8px;
                font-size: 12px;
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
            <h1>Управление группами</h1>
            <button class="burger-menu" onclick="toggleMobileMenu()" aria-label="Меню">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="user-info">
                <div class="admin-nav">
                    <a href="dashboard.php" class="btn btn-secondary">Мои задачи</a>
                    <a href="groups.php" class="btn btn-secondary">Группы</a>
                    <a href="admin_users.php" class="btn btn-secondary">Управление пользователями</a>
                </div>
                <span>Администратор: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary">Выйти</a>
            </div>
            <div class="mobile-menu" id="mobileMenu">
                <a href="dashboard.php" class="btn btn-secondary">Мои задачи</a>
                <a href="groups.php" class="btn btn-secondary">Группы</a>
                <a href="admin_users.php" class="btn btn-secondary">Управление пользователями</a>
                <span>Администратор: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary">Выйти</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="admin-container">
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
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>

