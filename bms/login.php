<?php
// login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | WE KONNECTS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#ff6b00">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-wrapper { display: flex; align-items: center; justify-content: center; height: 100vh; padding: 20px; box-sizing: border-box; }
        .login-card { width: 100%; max-width: 420px; padding: 40px; text-align: center; }
        .login-card h2 { margin: 0 0 5px 0; font-weight: 600; font-size: 24px; }
        .login-card p { margin: 0 0 30px 0; color: rgba(255,255,255,0.7); font-size: 14px; }
        .input-group { text-align: left; margin-bottom: 20px; position: relative; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.9); }
        .error-msg { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .success-msg { background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.5); color: #34d399; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="glass-panel login-card">
        
        <img src="https://wekonnects.com/logo.png" alt="WE KONNECTS" class="logo-img" style="max-width: 220px; margin-bottom: 25px;">
        
        <h2>Welcome Back</h2>
        <p>Enter your credentials to access the portal</p>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-msg"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-msg"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <form action="actions/login_action.php" method="POST">
            <div class="input-group">
                <label for="email">Corporate Email</label>
                <input type="email" id="email" name="email" class="glass-input" required placeholder="name@company.com">
            </div>
            
            <div class="input-group" style="margin-bottom: 10px;">
                <label for="passwordField">Password</label>
                <input type="password" name="password" id="passwordField" class="glass-input" placeholder="Password" required style="width: 100%; padding-right: 40px; box-sizing: border-box;">
                <i class="fa-solid fa-eye" id="togglePassword" style="position: absolute; right: 15px; top: 38px; cursor: pointer; color: #64748b;" onclick="togglePwd()"></i>
            </div>

            <div style="text-align: right; margin-bottom: 20px;">
                <a href="forgot_password.php" style="color: #ff6b00; font-size: 12px; font-weight: 600; text-decoration: none;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px;">Secure Login</button>
        </form>
    </div>
</div>
<script>
function togglePwd() {
    var pwd = document.getElementById("passwordField");
    var icon = document.getElementById("togglePassword");
    if (pwd.type === "password") {
        pwd.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        pwd.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>
</body>
</html>