<?php
// reset_password.php
session_start();
require_once 'config/database.php';

$token = $_GET['token'] ?? '';
$valid_token = false;

if (!empty($token)) {
    // Check if token exists and is NOT expired
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    if ($stmt->fetch()) {
        $valid_token = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Password | WE KONNECTS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-wrapper { display: flex; align-items: center; justify-content: center; height: 100vh; padding: 20px; box-sizing: border-box; }
        .login-card { width: 100%; max-width: 420px; padding: 40px; text-align: center; }
        .login-card h2 { margin: 0 0 5px 0; font-weight: 600; font-size: 24px; }
        .login-card p { margin: 0 0 30px 0; color: rgba(255,255,255,0.7); font-size: 14px; }
        .input-group { text-align: left; margin-bottom: 20px; position: relative; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.9); }
        .error-msg { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="glass-panel login-card">
        
        <?php if ($valid_token): ?>
            <i class="fa-solid fa-key" style="font-size: 40px; color: #10b981; margin-bottom: 20px;"></i>
            <h2>Create New Password</h2>
            <p>Please enter your new secure password below.</p>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-msg"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <form action="actions/update_forgotten_password.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="input-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="newPasswordField" class="glass-input" required style="width: 100%; box-sizing: border-box; padding: 12px; padding-right: 40px;">
                    <i class="fa-solid fa-eye" id="toggleNewPassword" style="position: absolute; right: 15px; top: 38px; cursor: pointer; color: #64748b;" onclick="togglePwd('newPasswordField', 'toggleNewPassword')"></i>
                </div>
                
                <div class="input-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirmPasswordField" class="glass-input" required style="width: 100%; box-sizing: border-box; padding: 12px; padding-right: 40px;">
                    <i class="fa-solid fa-eye" id="toggleConfirmPassword" style="position: absolute; right: 15px; top: 38px; cursor: pointer; color: #64748b;" onclick="togglePwd('confirmPasswordField', 'toggleConfirmPassword')"></i>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px;">Update Password</button>
            </form>
        <?php else: ?>
            <i class="fa-solid fa-circle-xmark" style="font-size: 40px; color: #ef4444; margin-bottom: 20px;"></i>
            <h2>Link Expired or Invalid</h2>
            <p>This password reset link is invalid or has expired. Please request a new one.</p>
            <a href="forgot_password.php" class="btn-primary" style="display:inline-block; text-decoration:none; padding: 12px 25px;">Request New Link</a>
        <?php endif; ?>

    </div>
</div>

<script>
function togglePwd(fieldId, iconId) {
    var pwd = document.getElementById(fieldId);
    var icon = document.getElementById(iconId);
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