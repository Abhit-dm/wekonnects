<?php
// profile.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['system_role'] === 'SUPER_ADMIN' || $_SESSION['system_role'] === 'FRANCHISE_OWNER');

try {
    $stmt = $pdo->prepare("
        SELECT u.*, b.company_name, b.business_category_applied, b.target_audience, b.ideal_referral, b.top_products, gm.group_id, g.group_name 
        FROM users u 
        LEFT JOIN businesses b ON u.id = b.user_id 
        LEFT JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
        LEFT JOIN groups g ON gm.group_id = g.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) { die("Error loading profile."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container { max-width: 600px; margin: 0 auto; padding: 20px; padding-bottom: 120px; font-family: 'Inter', sans-serif;}
        .top-header { margin-bottom: 20px;}
        .top-header h2 { color: white; margin: 0; font-size: 24px;}
        
        .card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 25px; margin-bottom: 20px; color: white;}
        .profile-header { display: flex; align-items: center; gap: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 20px;}
        .profile-header img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #ff6b00;}
        
        .data-row { margin-bottom: 12px; font-size: 14px; }
        .data-label { color: rgba(255,255,255,0.5); font-size: 11px; text-transform: uppercase; font-weight: 700; margin-bottom: 4px; display: block; letter-spacing: 0.5px;}
        .data-value { color: white; font-weight: 500; }

        /* Middle Action Buttons Layout */
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .btn-action { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 700; text-decoration: none; transition: 0.2s;}
        .btn-card-view { background: rgba(251,191,36,0.1); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3);}
        .btn-card-view:hover { background: rgba(251,191,36,0.2); }
        .btn-edit-profile { background: #ff6b00; color: white; border: 1px solid #ff6b00;}
        .btn-edit-profile:hover { background: #e65c00; }
        
        /* Support & Logout Buttons CSS */
        .btn-support { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 700; text-decoration: none; background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); width: 100%; box-sizing: border-box; transition: 0.2s; margin-bottom: 15px;}
        .btn-support:hover { background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.5); color: #93c5fd;}

        .btn-logout { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 700; text-decoration: none; background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); width: 100%; box-sizing: border-box; transition: 0.2s; margin-bottom: 20px;}
        .btn-logout:hover { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.5); color: #f87171;}

        /* Bottom Nav */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; padding: 10px 0; display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; background: rgba(0, 20, 45, 0.95); backdrop-filter: blur(16px); z-index: 100; border-top: 1px solid rgba(255,255,255,0.1); box-sizing: border-box; padding-bottom: max(10px, env(safe-area-inset-bottom)); }
        .nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 10px; font-weight: 600;}
        .nav-item.active { color: #ff6b00; }
        .nav-item i { font-size: 20px; margin-bottom: 5px; }
    </style>
</head>
<body style="background: #00204a; margin: 0;">

<div class="profile-container">
    
    <div class="top-header">
        <h2>My Profile</h2>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: rgba(16,185,129,0.2); color: #34d399; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(52,211,153,0.3);"><i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="profile-header">
            <img src="assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_photo'] ?? 'default.png'); ?>" onerror="this.src='https://via.placeholder.com/150/00204a/ff6b00?text=<?php echo substr($user['first_name'],0,1); ?>'">
            <div>
                <h3 style="margin: 0 0 5px 0; font-size: 20px;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <span style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 20px; font-size: 12px; color: #fbbf24; font-weight: 600;"><?php echo htmlspecialchars($user['group_name'] ?? 'Pending Chapter'); ?></span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="data-row"><span class="data-label">Phone</span><span class="data-value"><?php echo htmlspecialchars($user['phone']); ?></span></div>
            <div class="data-row"><span class="data-label">Email</span><span class="data-value"><?php echo htmlspecialchars($user['email']); ?></span></div>
            <div class="data-row"><span class="data-label">Blood Group</span><span class="data-value"><?php echo htmlspecialchars($user['blood_group'] ?? '-'); ?></span></div>
            <div class="data-row"><span class="data-label">Birthday</span><span class="data-value"><?php echo !empty($user['dob']) ? date('M d, Y', strtotime($user['dob'])) : '-'; ?></span></div>
            <div class="data-row"><span class="data-label">Anniversary</span><span class="data-value"><?php echo !empty($user['anniversary_date']) ? date('M d, Y', strtotime($user['anniversary_date'])) : '-'; ?></span></div>
        </div>
    </div>

    <div class="action-grid">
        <a href="public.php?id=<?php echo $user_id; ?>" target="_blank" class="btn-action btn-card-view">
            <i class="fa-solid fa-id-badge"></i> View Digital Card
        </a>
        <a href="edit_my_profile.php" class="btn-action btn-edit-profile">
            <i class="fa-solid fa-pen"></i> Edit Profile
        </a>
    </div>

    <div class="card">
        <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px; color:#ff6b00;"><i class="fa-solid fa-briefcase"></i> Business Details</h3>
        <div class="data-row"><span class="data-label">Company Name</span><span class="data-value"><?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></span></div>
        <div class="data-row"><span class="data-label">Category</span><span class="data-value"><?php echo htmlspecialchars($user['business_category_applied'] ?? '-'); ?></span></div>
        <div class="data-row"><span class="data-label">Target Audience</span><span class="data-value"><?php echo nl2br(htmlspecialchars($user['target_audience'] ?? '-')); ?></span></div>
        <div class="data-row"><span class="data-label">Ideal Referral</span><span class="data-value"><?php echo nl2br(htmlspecialchars($user['ideal_referral'] ?? '-')); ?></span></div>
        <div class="data-row"><span class="data-label">Top Products / Services</span><span class="data-value"><?php echo nl2br(htmlspecialchars($user['top_products'] ?? '-')); ?></span></div>
    </div>

    <!-- SUPPORT HELPDESK BUTTON -->
    <a href="support.php" class="btn-support">
        <i class="fa-solid fa-headset"></i> Support Helpdesk
    </a>

    <!-- SECURE LOGOUT BUTTON -->
    <a href="actions/logout.php" class="btn-logout">
        <i class="fa-solid fa-right-from-bracket"></i> Secure Logout
    </a>

</div>

<?php if (!$is_admin) include 'includes/bottom_nav.php'; ?>

</body>
</html>