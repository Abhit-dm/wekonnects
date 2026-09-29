<?php
// actions/send_reset_link.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if ($email) {
        $stmt =$pdo->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user =$stmt->fetch();

        if ($user) {
            // Generate a secure, unique token
            $token = bin2hex(random_bytes(50));             // Token expires in 1 hour$expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

            $stmtUpdate =$pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmtUpdate->execute([$token, $expiry,$user['id']]);

            $request_host = strtolower($_SERVER['HTTP_HOST'] ?? '');
            $host_name = preg_replace('/:\\d+$/', '', $request_host);
            $local_hosts = ['localhost', '127.0.0.1'];
            $allowed_hosts = ['official.wekonnects.com', 'wekonnects.com', 'www.wekonnects.com'];

            if (in_array($host_name, $local_hosts, true)) {
                $host = $request_host;
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            } else {
                $host = in_array($host_name, $allowed_hosts, true) ? $host_name : 'official.wekonnects.com';
                $scheme = 'https';
            }

            $script_path = parse_url($_SERVER['SCRIPT_NAME'] ?? '/actions/send_reset_link.php', PHP_URL_PATH) ?: '/actions/send_reset_link.php';
            $script_dir = str_replace('\\', '/', dirname($script_path));
            $base_path = preg_replace('#/actions$#', '', $script_dir);
            $base_path = rtrim($base_path, '/');
            $reset_link = $scheme . '://' . $host . $base_path . '/reset_password.php?token=' . rawurlencode($token);

            // Email details
            $to =$email;
            $subject = "Password Reset Request | WE KONNECTS";
            $message = "Hello " . $user['first_name'] . ",\n\n";
            $message .= "You recently requested to reset your password for your WE KONNECTS account.\n\n";
            $message .= "Click the link below to set a new password:\n";
            $message .=$reset_link . "\n\n";
            $message .= "If you did not request a password reset, please ignore this email. This link will expire in 1 hour.\n\n";
            $message .= "Best regards,\nThe WE KONNECTS Team";
            
            $headers = "From: noreply@official.wekonnects.com\r\n";
            $headers .= "Reply-To: noreply@official.wekonnects.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            // Send the email
            if (mail($to, $subject,$message, $headers)) {$_SESSION['success_message'] = "A password reset link has been sent to your email.";
            } else {
                $_SESSION['error_message'] = "Failed to send email. Please ensure your server's mail system is configured.";
            }
        } else {
            $_SESSION['success_message'] = "If that email is registered, a reset link has been sent.";
        }
    } else {
        $_SESSION['error_message'] = "Please enter a valid email address.";
    }
}

header("Location: ../forgot_password.php");
exit;
?>