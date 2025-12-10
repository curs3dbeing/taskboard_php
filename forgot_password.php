<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Пожалуйста введите вашу почту.';
    } elseif (!isValidEmail($email)) {
        $error = 'Пожалуйста введите реальную почту.';
    } else {
        try {
            $pdo = getDBConnection();
            if (!$pdo) {
                $error = 'Ошибка подключения к базе данных. Попробуйте позже.';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600);
                    
                    $stmt = $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE email = ?");
                    $stmt->execute([$token, $expires, $email]);

                    // Увеличиваем время выполнения для отправки email
                    set_time_limit(60);
                    
                    // Пытаемся отправить email, но не блокируем процесс
                    $emailSent = @sendPasswordResetEmail($email, $token);
                    
                    // Всегда показываем успех для безопасности (не раскрываем, существует ли email)
                    $success = 'Если эта почта существует, ссылка для сброса пароля была отправлена на вашу почту.';
                    
                    // Логируем результат для отладки
                    if (!$emailSent) {
                        error_log("Failed to send password reset email to: $email");
                    }
                } else {
                    // Для безопасности показываем то же сообщение
                    $success = 'Если эта почта существует, ссылка для сброса пароля была отправлена на вашу почту.';
                }
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'Произошла ошибка, попробуйте еще раз.';
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'Произошла ошибка, попробуйте еще раз.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля</title>
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
            <?php endif; ?>
            
            <form method="POST" action="forgot_password.php">
                <div class="form-group">
                    <label for="email">Почта</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    <small>Введите почту и мы отправим вам ссылку для восстановления.</small>
                </div>
                
                <button type="submit" class="btn btn-primary">Отправить ссылку для сброса пароля</button>
            </form>
            
            <div class="auth-links">
                <p><a href="login.php">Вернутся к авторизации</a></p>
            </div>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>

