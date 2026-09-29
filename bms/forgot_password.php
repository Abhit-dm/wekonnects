<?php
// forgot_password.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | WE KONNECTS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-wrapper { display: flex; align-items: center; justify-content: center; height: 100vh; padding: 20px; box-sizing: border-box; }
        .login-card { width: 100%; max-width: 420px; padding: 40px; text-align: center; }
        .login-card h2 { margin: 0 0 5px 0; font-weight: 600; font-size: 24px; }
        .login-card p { margin: 0 0 30px 0; color: rgba(255,255,255,0.7); font-size: 14px; line-height: 1.5; }
        .input-group { text-align: left; margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.9); }
        .error-msg { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .success-msg { background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.5); color: #34d399; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="glass-panel login-card">
        
        <i class="fa-solid fa-lock" style="font-size: 40px; color: #ff6b00; margin-bottom: 20px;"></i>
        <h2>Reset Password</h2>
        <p>Enter your registered email address and we will send you a secure link to reset your password.</p>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-msg"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-msg"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <form action="actions/send_reset_link.php" method="POST">
            <div class="input-group">
                <label>Registered Email Address</label>
                <input type="email" name="email" class="glass-input" required placeholder="name@company.com" style="width: 100%; box-sizing: border-box; padding: 12px;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; margin-bottom: 15px;">Send Reset Link</button>
            <a href="login.php" style="color: rgba(255,255,255,0.7); font-size: 13px; text-decoration: none; font-weight: 600;">Back to Login</a>
        </form>
    </div>
</div>

</body>
</html>