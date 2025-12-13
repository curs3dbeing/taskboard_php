<?php

define('DB_HOST', getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'mysql.railway.internal'); #
define('DB_NAME', getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'railway');
define('DB_USER', getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: 'CaDCLkuDVllroHlKErMTxTqCadraZMtp');



define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
define('RESEND_FROM_EMAIL', getenv('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev');
define('RESEND_FROM_NAME', 'Task Planner');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', getenv('SMTP_USER_GM') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS_GM') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_USER_GM') ?: '');
define('SMTP_FROM_NAME', 'Task Planner');


define('SITE_URL', getenv('SITE_URL') ?: getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'https://taskboardphp-production.up.railway.app'); #https://taskboardphp-production.up.railway.app
define('SESSION_LIFETIME', 3600);


define('MIN_PASSWORD_LENGTH', 8);
define('MIN_USERNAME_LENGTH', 3);
define('MAX_USERNAME_LENGTH', 50);

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());

        if (php_sapi_name() === 'cli' || isset($_GET['debug'])) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        return null;
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


function getUserRole() {
    if (!isLoggedIn()) {
        return null;
    }
    

    if (isset($_SESSION['role'])) {
        return $_SESSION['role'];
    }
    

    $pdo = getDBConnection();
    if (!$pdo) {
        return 'user';
    }
    
    $user_id = getCurrentUserId();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    $role = $result ? $result['role'] : 'user';
    $_SESSION['role'] = $role; 
    
    return $role;
}


function isAdmin() {
    return getUserRole() === 'admin';
}


function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php?message=' . urlencode('У вас нет прав доступа к этой странице.'));
        exit;
    }
}


function isUserBlocked($user_id) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT is_blocked FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    return $result && $result['is_blocked'] == 1;
}


function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


function sendPasswordResetEmail($email, $token) {
    $resetLink = SITE_URL . "/reset_password.php?token=" . urlencode($token);
    $subject = "Запрос на сброс пароля";
    $message = "Здравствуйте,\n\nВы запросили сброс пароля. Перейдите по ссылке ниже, чтобы установить новый пароль:\n\n" . $resetLink . "\n\nЭта ссылка действительна в течение 1 часа.\n\nЕсли вы не запрашивали сброс пароля, проигнорируйте это письмо.";

    if (!empty(RESEND_API_KEY)) {
        return sendEmailWithResend($email, $subject, $message);
    }
    
    if (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
        return sendEmailWithPHPMailer($email, $subject, $message);
    }


    return sendEmailWithSMTP($email, $subject, $message);
}

function sendEmailWithResend($to, $subject, $message) {
    if (empty(RESEND_API_KEY)) {
        error_log("Resend API key is not set");
        return false;
    }
    
    try {
        $url = 'https://api.resend.com/emails';
        
        $data = [
            'from' => RESEND_FROM_NAME . ' <' . RESEND_FROM_EMAIL . '>',
            'to' => [$to],
            'subject' => $subject,
            'text' => $message
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . RESEND_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Resend API curl error: " . $error);
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($response, true);
            if (isset($responseData['id'])) {
                error_log("Resend email sent successfully. ID: " . $responseData['id']);
                return true;
            }
        }
        
        error_log("Resend API error. HTTP Code: $httpCode, Response: " . $response);
        return false;
        
    } catch (Exception $e) {
        error_log("Resend API exception: " . $e->getMessage());
        return false;
    }
}

function sendEmailWithSMTP($to, $subject, $message) {
    $smtp = null;
    try {
        $connectionTimeout = 10;
        $readTimeout = 5;
        

        $useSSL = (SMTP_PORT == 465);
        $context = null;
        
        if ($useSSL) {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ],
                'socket' => [
                    'tcp_nodelay' => true
                ]
            ]);
            $smtp = @stream_socket_client(
                "ssl://" . SMTP_HOST . ":" . SMTP_PORT,
                $errno,
                $errstr,
                $connectionTimeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {

            $smtp = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, $connectionTimeout);
        }
        
        if (!$smtp) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }
        

        stream_set_timeout($smtp, $readTimeout);
        

        $greeting = @fgets($smtp, 515);
        if ($greeting === false) {
            error_log("SMTP: Failed to read greeting");
            fclose($smtp);
            return false;
        }
        

        if (@fputs($smtp, "EHLO " . SMTP_HOST . "\r\n") === false) {
            error_log("SMTP: Failed to send EHLO");
            fclose($smtp);
            return false;
        }
        $ehloResponse = @fgets($smtp, 515);
        if ($ehloResponse === false) {
            error_log("SMTP: Failed to read EHLO response");
            fclose($smtp);
            return false;
        }
        

        if (!$useSSL) {
            if (@fputs($smtp, "STARTTLS\r\n") === false) {
                error_log("SMTP: Failed to send STARTTLS");
                fclose($smtp);
                return false;
            }
            $response = @fgets($smtp, 515);
            if ($response === false || strpos($response, "220") === false) {
                error_log("SMTP: STARTTLS failed or not supported");
                fclose($smtp);
                return false;
            }
            

            if (!@stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP: Failed to enable TLS");
                fclose($smtp);
                return false;
            }
            

            if (@fputs($smtp, "EHLO " . SMTP_HOST . "\r\n") === false) {
                error_log("SMTP: Failed to send EHLO after TLS");
                fclose($smtp);
                return false;
            }
            @fgets($smtp, 515);
        }
        

        if (@fputs($smtp, "AUTH LOGIN\r\n") === false) {
            error_log("SMTP: Failed to send AUTH LOGIN");
            fclose($smtp);
            return false;
        }
        @fgets($smtp, 515);
        
        if (@fputs($smtp, base64_encode(SMTP_USER) . "\r\n") === false) {
            error_log("SMTP: Failed to send username");
            fclose($smtp);
            return false;
        }
        @fgets($smtp, 515);
        
        if (@fputs($smtp, base64_encode(SMTP_PASS) . "\r\n") === false) {
            error_log("SMTP: Failed to send password");
            fclose($smtp);
            return false;
        }
        $authResponse = @fgets($smtp, 515);
        
        if ($authResponse === false || strpos($authResponse, "235") === false) {
            error_log("SMTP Authentication failed: " . trim($authResponse ?: 'No response'));
            fclose($smtp);
            return false;
        }
        

        if (@fputs($smtp, "MAIL FROM: <" . SMTP_FROM_EMAIL . ">\r\n") === false) {
            error_log("SMTP: Failed to send MAIL FROM");
            fclose($smtp);
            return false;
        }
        @fgets($smtp, 515);
        
        if (@fputs($smtp, "RCPT TO: <" . $to . ">\r\n") === false) {
            error_log("SMTP: Failed to send RCPT TO");
            fclose($smtp);
            return false;
        }
        @fgets($smtp, 515);
        
        if (@fputs($smtp, "DATA\r\n") === false) {
            error_log("SMTP: Failed to send DATA");
            fclose($smtp);
            return false;
        }
        @fgets($smtp, 515);
        
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "To: <" . $to . ">\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";
        
        if (@fputs($smtp, $headers . $message . "\r\n.\r\n") === false) {
            error_log("SMTP: Failed to send email data");
            fclose($smtp);
            return false;
        }
        @fgets($smtp, 515);
        
        @fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        return true;
    } catch (Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
        if (isset($smtp) && $smtp) {
            @fclose($smtp);
        }
        return false;
    }
}

function sendEmailWithPHPMailer($to, $subject, $message) {
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
    require_once __DIR__ . '/PHPMailer/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        

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

