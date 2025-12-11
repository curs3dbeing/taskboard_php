<?php
require_once 'config.php';
requireLogin();

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed. Please check the configuration.");
}

$user_id = getCurrentUserId();


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
    <title>Управление группами</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .groups-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .groups-section {
            margin-bottom: 40px;
        }
        .groups-section h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .group-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .group-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .group-card p {
            color: #666;
            margin: 10px 0;
            font-size: 14px;
        }
        .group-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #999;
        }
        .group-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }
        .create-group-form {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
            border-top: 1px solid #eee;
        }
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .member-item:last-child {
            border-bottom: none;
        }
        .add-member-form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .add-member-form input {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Управление группами</h1>
            <div class="user-info">
                <a href="dashboard.php" class="btn btn-secondary">Мои задачи</a>
                <span>Здравствуйте, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="logout.php" class="btn btn-secondary">Выйти</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="groups-container">
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
                                    <span>Участников: <?php echo $group['member_count'] + 1; ?></span>
                                    <span>Создана: <?php echo date('d.m.Y', strtotime($group['created_at'])); ?></span>
                                </div>
                                <div class="group-actions">
                                    <a href="group_dashboard.php?group_id=<?php echo $group['id']; ?>" class="btn btn-primary btn-small">Открыть</a>
                                    <button class="btn btn-secondary btn-small" onclick="openManageMembers(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">Управление</button>
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
                                    <span>Участников: <?php echo $group['member_count'] + 1; ?></span>
                                    <span>Создана: <?php echo date('d.m.Y', strtotime($group['created_at'])); ?></span>
                                </div>
                                <div class="group-actions">
                                    <a href="group_dashboard.php?group_id=<?php echo $group['id']; ?>" class="btn btn-primary btn-small">Открыть</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>

