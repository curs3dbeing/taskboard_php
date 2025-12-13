<?php
require_once 'config.php';

$error = '';
$success = '';
$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    $error = 'Неверная ссылка для сброса пароля.';
} else {
    try {
        $pdo = getDBConnection();
        

        $stmt = $pdo->prepare("SELECT id, password_reset_expires FROM users WHERE password_reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Неверная или недействительная ссылка для сброса пароля. Пожалуйста, запросите новую ссылку.';
            $token = '';
        } elseif (empty($user['password_reset_expires']) || strtotime($user['password_reset_expires']) < time()) {
            $error = 'Ссылка для сброса пароля истекла или недействительна. Пожалуйста, запросите новую ссылку.';
            $token = ''; 
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($password) || empty($confirm_password)) {
                $error = 'Все поля должны быть заполнены.';
            } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
                $error = 'Пароль должен состоять как минимум из ' . MIN_PASSWORD_LENGTH . ' символов.';
            } elseif ($password !== $confirm_password) {
                $error = 'Пароли не совпадают.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
                $stmt->execute([$password_hash, $user['id']]);
                
                $success = 'Пароль успешно востановлен! Теперь вы можете авторизоваться используя новый пароль.';
                $token = '';
            }
        }
    } catch (PDOException $e) {
        $error = 'Произошла ошибка, попробуйте позже';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="auth-card">
            <h1>Сброс пароля</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <div class="auth-links">
                    <p><a href="login.php">Перейти к авторизации</a></p>
                </div>
            <?php elseif ($token): ?>
                <form method="POST" action="reset_password.php?token=<?php echo urlencode($token); ?>">
                    <div class="form-group">
                        <label for="password">Новый пароль</label>
                        <input type="password" id="password" name="password" required 
                               minlength="<?php echo MIN_PASSWORD_LENGTH; ?>">
                        <small>Как минимум <?php echo MIN_PASSWORD_LENGTH; ?> символов</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Подтвердите новый пароль</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Сбросить пароль</button>
                </form>
            <?php endif; ?>
            
            <?php if (!$success && $token): ?>
                <div class="auth-links">
                    <p><a href="login.php">Перейти к авторизации</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>

