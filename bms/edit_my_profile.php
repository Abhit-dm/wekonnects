<?php
// edit_my_profile.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['system_role'] === 'SUPER_ADMIN' || $_SESSION['system_role'] === 'FRANCHISE_OWNER');

try {
    $stmt = $pdo->prepare("SELECT u.*, b.company_name, b.business_category_applied, b.target_audience, b.ideal_referral, b.top_products FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) { die("Error loading profile."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container { max-width: 600px; margin: 0 auto; padding: 20px; padding-bottom: 100px; font-family: 'Inter', sans-serif;}
        .card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 25px; margin-bottom: 20px; color: white;}
        .form-group { margin-bottom: 15px; position: relative; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: rgba(255,255,255,0.8); }
        .form-input { width: 100%; padding: 12px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: white; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
        
        input[type="file"]::file-selector-button { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 8px 12px; border-radius: 6px; cursor: pointer; margin-right: 15px;}
        
        .btn-save { background: #ff6b00; color: white; border: none; padding: 15px; border-radius: 8px; font-weight: 700; width: 100%; cursor: pointer; font-size: 16px;}
        .btn-cancel { display: block; text-align: center; color: rgba(255,255,255,0.6); text-decoration: none; margin-top: 15px; font-size: 14px; font-weight: 600;}
        
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; padding: 10px 0; display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; background: rgba(0, 20, 45, 0.95); backdrop-filter: blur(16px); z-index: 100; border-top: 1px solid rgba(255,255,255,0.1); box-sizing: border-box; padding-bottom: max(10px, env(safe-area-inset-bottom)); }
        .nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 10px; font-weight: 600;}
        .nav-item.active { color: #ff6b00; }
        .nav-item i { font-size: 20px; margin-bottom: 5px; }
    </style>
</head>
<body style="background: #00204a; margin: 0;">

<div class="profile-container">
    
    <h2 style="color: white; margin-top: 0;">Edit Information</h2>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: rgba(239,68,68,0.2); color: #fca5a5; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.3);"><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <form action="actions/update_my_profile.php" method="POST" enctype="multipart/form-data">
        
        <div class="card">
            <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;"><i class="fa-solid fa-camera" style="color:#ff6b00;"></i> Profile Picture</h3>
            <div style="display:flex; align-items:center; gap: 15px;">
                <img src="assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_photo'] ?? 'default.png'); ?>" style="width:70px; height:70px; border-radius:50%; object-fit:cover; border:2px solid #ff6b00;">
                <input type="file" name="profile_photo" class="form-input" accept="image/*">
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;"><i class="fa-solid fa-user" style="color:#ff6b00;"></i> Personal Details</h3>
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;"><label>First Name</label><input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name']); ?>" required></div>
                <div class="form-group" style="flex:1;"><label>Last Name</label><input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name']); ?>" required></div>
            </div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone']); ?>" required></div>
            <div class="form-group"><label>Blood Group</label><input type="text" name="blood_group" class="form-input" placeholder="e.g. O+" value="<?php echo htmlspecialchars($user['blood_group'] ?? ''); ?>"></div>
            
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;"><label>Date of Birth</label><input type="date" name="dob" class="form-input" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>"></div>
                <div class="form-group" style="flex:1;"><label>Anniversary</label><input type="date" name="anniversary_date" class="form-input" value="<?php echo htmlspecialchars($user['anniversary_date'] ?? ''); ?>"></div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;"><i class="fa-solid fa-briefcase" style="color:#ff6b00;"></i> Business Details</h3>
            <div class="form-group"><label>Company Name</label><input type="text" name="company_name" class="form-input" value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>" required></div>
            <div class="form-group"><label>Target Audience</label><textarea name="target_audience" class="form-input" rows="2"><?php echo htmlspecialchars($user['target_audience'] ?? ''); ?></textarea></div>
            <div class="form-group"><label>Ideal Referral</label><textarea name="ideal_referral" class="form-input" rows="2"><?php echo htmlspecialchars($user['ideal_referral'] ?? ''); ?></textarea></div>
            <div class="form-group"><label>Top Products / Services</label><textarea name="top_products" class="form-input" rows="3"><?php echo htmlspecialchars($user['top_products'] ?? ''); ?></textarea></div>
        </div>

        <div class="card">
            <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;"><i class="fa-solid fa-lock" style="color:#ff6b00;"></i> Security</h3>
            <p style="font-size:11px; color:rgba(255,255,255,0.5); margin-top:-5px; margin-bottom:15px;">Leave these blank if you do not want to change your password.</p>
            
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" id="newPasswordField" class="form-input" placeholder="Enter new password" style="padding-right: 40px;">
                <i class="fa-solid fa-eye" id="toggleNewPassword" style="position: absolute; right: 15px; top: 38px; cursor: pointer; color: #94a3b8;" onclick="togglePwd('newPasswordField', 'toggleNewPassword')"></i>
            </div>
            
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPasswordField" class="form-input" placeholder="Confirm new password" style="padding-right: 40px;">
                <i class="fa-solid fa-eye" id="toggleConfirmPassword" style="position: absolute; right: 15px; top: 38px; cursor: pointer; color: #94a3b8;" onclick="togglePwd('confirmPasswordField', 'toggleConfirmPassword')"></i>
            </div>
        </div>

        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        <a href="profile.php" class="btn-cancel">Cancel</a>
    </form>
</div>

<?php if (!$is_admin) include 'includes/bottom_nav.php'; ?>

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