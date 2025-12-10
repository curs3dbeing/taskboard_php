<?php

// Database configuration - использует переменные окружения или значения по умолчанию
define('DB_HOST', getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'mysql.railway.internal');
define('DB_NAME', getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'railway');
define('DB_USER', getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: 'CaDCLkuDVllroHlKErMTxTqCadraZMtp');


define('SMTP_HOST', 'smtp.mail.ru');
define('SMTP_PORT', 587);
define('SMTP_USER', 'courseprojauth@mail.ru');
define('SMTP_PASS', 'kx3qsm5rxBDqmwgwLmuv');
define('SMTP_FROM_EMAIL', 'courseprojauth@mail.ru');
define('SMTP_FROM_NAME', 'Task Planner');


// Site URL - использует переменную окружения или значение по умолчанию
define('SITE_URL', getenv('SITE_URL') ?: getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'https://taskboardphp-production.up.railway.app');
define('SESSION_LIFETIME', 3600);


define('MIN_PASSWORD_LENGTH', 8);
define('MIN_USERNAME_LENGTH', 3);
define('MAX_USERNAME_LENGTH', 50);

function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function isLoggedIn() {
    return isset($_SESSION['user_id']);
}


function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}


function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}


function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


function sendPasswordResetEmail($email, $token) {
    $resetLink = SITE_URL . "/reset_password.php?token=" . urlencode($token);
    $subject = "Запрос на сброс пароля";
    $message = "Здравствуйте,\n\nВы запросили сброс пароля. Перейдите по ссылке ниже, чтобы установить новый пароль:\n\n" . $resetLink . "\n\nЭта ссылка действительна в течение 1 часа.\n\nЕсли вы не запрашивали сброс пароля, проигнорируйте это письмо.";

    if (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
        return sendEmailWithPHPMailer($email, $subject, $message);
    }

    return sendEmailWithSMTP($email, $subject, $message);
}

function sendEmailWithSMTP($to, $subject, $message) {
    try {
        // Determine if we need SSL (port 465) or STARTTLS (port 587)
        $useSSL = (SMTP_PORT == 465);
        $context = null;
        
        if ($useSSL) {
            // SSL connection for port 465
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            $smtp = stream_socket_client(
                "ssl://" . SMTP_HOST . ":" . SMTP_PORT,
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            // Regular connection for STARTTLS (port 587)
            $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
        }
        
        if (!$smtp) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }
        
        // Read server greeting
        fgets($smtp, 515);
        
        // Send EHLO
        fputs($smtp, "EHLO " . SMTP_HOST . "\r\n");
        fgets($smtp, 515);
        
        // Start TLS if not using SSL
        if (!$useSSL) {
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);
            
            if (strpos($response, "220") !== false) {
                // Enable crypto
                if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    error_log("Failed to enable TLS");
                    fclose($smtp);
                    return false;
                }
                
                // Send EHLO again after TLS
                fputs($smtp, "EHLO " . SMTP_HOST . "\r\n");
                fgets($smtp, 515);
            }
        }
        
        // Authenticate
        fputs($smtp, "AUTH LOGIN\r\n");
        fgets($smtp, 515);
        
        fputs($smtp, base64_encode(SMTP_USER) . "\r\n");
        fgets($smtp, 515);
        
        fputs($smtp, base64_encode(SMTP_PASS) . "\r\n");
        $authResponse = fgets($smtp, 515);
        
        if (strpos($authResponse, "235") === false) {
            error_log("SMTP Authentication failed: " . trim($authResponse));
            fclose($smtp);
            return false;
        }
        
        // Send email
        fputs($smtp, "MAIL FROM: <" . SMTP_FROM_EMAIL . ">\r\n");
        fgets($smtp, 515);
        
        fputs($smtp, "RCPT TO: <" . $to . ">\r\n");
        fgets($smtp, 515);
        
        fputs($smtp, "DATA\r\n");
        fgets($smtp, 515);
        
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";
        
        fputs($smtp, $headers . $message . "\r\n.\r\n");
        fgets($smtp, 515);
        
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        return true;
    } catch (Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
        return false;
    }
}

function sendEmailWithPHPMailer($to, $subject, $message) {
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
    require_once __DIR__ . '/PHPMailer/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

