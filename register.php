<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Все поля должны быть заполнены.';
    } elseif (strlen($username) < MIN_USERNAME_LENGTH || strlen($username) > MAX_USERNAME_LENGTH) {
        $error = 'Длина логина должна быть между ' . MIN_USERNAME_LENGTH . ' и ' . MAX_USERNAME_LENGTH . ' символами.';
    } elseif (!isValidEmail($email)) {
        $error = 'Введите реальный email.';
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = 'Пароль должен быть как минимум ' . MIN_PASSWORD_LENGTH . ' символов в длину.';
    } elseif ($password !== $confirm_password) {
        $error = 'Пароли не совпадают.';
    } else {
        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Такой логин или почта уже существуют.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);
                
                // Редирект на страницу авторизации после успешной регистрации
                header('Location: login.php?message=' . urlencode('Регистрация прошла успешно. Теперь вы можете авторизоваться!'));
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Во время регистрации произошла ошибка. Попробуйте позже';
        }
    }
}

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="auth-card">
            <h1>Создание аккаунта</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="register.php" id="registerForm">
                <div class="form-group">
                    <label for="username">Логин</label>
                    <input type="text" id="username" name="username" required 
                           minlength="<?php echo MIN_USERNAME_LENGTH; ?>" 
                           maxlength="<?php echo MAX_USERNAME_LENGTH; ?>"
                           value="<?php echo htmlspecialchars($username ?? ''); ?>">
                    <small>Длина <?php echo MIN_USERNAME_LENGTH; ?>-<?php echo MAX_USERNAME_LENGTH; ?> символов</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Почта</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required 
                           minlength="<?php echo MIN_PASSWORD_LENGTH; ?>">
                    <small>Как минимум <?php echo MIN_PASSWORD_LENGTH; ?> символов</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Подтвердить пароль</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Регистрация</button>
            </form>
            
            <div class="auth-links">
                <p>Уже есть аккаунт? <a href="login.php">Авторизуйтесь сейчас!</a></p>
            </div>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>

