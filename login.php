<?php
require_once 'config.php';

$error = '';
$message = '';

if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Пожалуйста введите логин и пароль.';
    } else {
        try {
            $pdo = getDBConnection();
            if (!$pdo) {
                $error = 'Ошибка подключения к базе данных. Попробуйте позже.';
            } else {
                $isEmail = isValidEmail($username);
            $field = $isEmail ? 'email' : 'username';
            
            $stmt = $pdo->prepare("SELECT id, username, password_hash, is_blocked, role FROM users WHERE $field = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['is_blocked'] == 1) {
                    $error = 'Ваш аккаунт заблокирован администратором. Обратитесь к администратору для разблокировки.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'] ?? 'user';
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Неверный логин/почта или пароль.';
            }
            }
        } catch (PDOException $e) {
            $error = 'При авторизации произошла ошибка. Попробуйте еще раз.';
        } catch (Exception $e) {
            $error = 'Ошибка подключения к базе данных. Попробуйте позже.';
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
    <title>Авторизация</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="auth-card">
            <h1>Авторизация</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="username">Логин или почта</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Авторизоваться</button>
            </form>
            
            <div class="auth-links">
                <p><a href="forgot_password.php">Забыли пароль?</a></p>
                <p>Нет аккаунта? <a href="register.php">Зарегистрируйтесь!</a></p>
            </div>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>

