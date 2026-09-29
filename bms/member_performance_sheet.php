<?php
// member_performance_sheet.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: login.php"); exit; }

$target_user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$target_user_id) { die("Invalid Member ID."); }

// Only Head Table and Admins can view this
$my_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['system_role'] === 'SUPER_ADMIN' || $_SESSION['system_role'] === 'FRANCHISE_OWNER');

$is_authorized = $is_admin;
if (!$is_admin) {
    $stmtCheck = $pdo->prepare("
        SELECT 1 FROM group_members target_gm
        JOIN group_members my_gm ON target_gm.group_id = my_gm.group_id
        WHERE target_gm.user_id = ? AND my_gm.user_id = ? AND my_gm.leadership_role = 'Coordinator'
    ");
    $stmtCheck->execute([$target_user_id, $my_id]);
    if ($stmtCheck->fetchColumn()) { $is_authorized = true; }
}

if (!$is_authorized) { die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Access Denied</h2><p>You do not have permission to view this performance sheet.</p></div>"); }

try {
    // Fetch User Profile
    $stmtProfile = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email, u.phone, u.profile_photo, 
               b.company_name, b.business_category_applied, 
               gm.joining_date, g.group_name, g.id as group_id
        FROM users u
        LEFT JOIN businesses b ON u.id = b.user_id
        LEFT JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
        LEFT JOIN groups g ON gm.group_id = g.id
        WHERE u.id = ?
    ");
    $stmtProfile->execute([$target_user_id]);
    $user = $stmtProfile->fetch();

    if (!$user) die("Member not found.");

    // Fetch Lifetime Contributions
    $stmtStats = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM slips WHERE initiator_member_id = ? AND slip_type = 'REFERRAL') as total_given_links,
            (SELECT COUNT(*) FROM slips WHERE receiver_member_id = ? AND slip_type = 'REFERRAL') as total_received_links,
            (SELECT SUM(amount) FROM slips WHERE initiator_member_id = ? AND slip_type = 'TYFCB') as total_revenue_generated,
            (SELECT COUNT(*) FROM slips WHERE initiator_member_id = ? AND slip_type = '121') as total_121s,
            (SELECT COUNT(*) FROM visitors WHERE invited_by = ? AND attended = 1) as total_visitors
    ");
    $stmtStats->execute([$target_user_id, $target_user_id, $target_user_id, $target_user_id, $target_user_id]);
    $stats = $stmtStats->fetch();

    // Fetch Attendance (Rolling 6 Months) - NOW INCLUDES LATE
    $stmtAtt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN cm.meeting_type NOT LIKE '%SOM%' AND a.attendance_status LIKE '%Present%' THEN 1 ELSE 0 END) as mtg_present,
            SUM(CASE WHEN cm.meeting_type NOT LIKE '%SOM%' AND a.attendance_status LIKE '%Late%' THEN 1 ELSE 0 END) as mtg_late,
            SUM(CASE WHEN cm.meeting_type NOT LIKE '%SOM%' AND a.attendance_status LIKE '%Absent%' THEN 1 ELSE 0 END) as mtg_absent,
            SUM(CASE WHEN a.attendance_status LIKE '%Substitute%' THEN 1 ELSE 0 END) as total_subs
        FROM attendance a
        JOIN chapter_meetings cm ON a.meeting_id = cm.id
        WHERE a.user_id = ? AND cm.group_id = ? AND cm.meeting_type <> 'Daily Status' AND cm.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    ");
    $stmtAtt->execute([$target_user_id, $user['group_id'] ?? 0]);
    $att = $stmtAtt->fetch();

} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Performance Sheet | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f8fafc; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; box-sizing: border-box;}
        
        .sheet-container { background: white; max-width: 900px; width: 100%; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,32,74,0.1); overflow: hidden; border: 1px solid var(--border-color);}
        
        .header-section { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 40px; color: white; display: flex; align-items: center; gap: 25px;}
        .profile-img { width: 100px; height: 100px; border-radius: 50%; border: 4px solid var(--primary-orange); object-fit: cover; background: white;}
        .header-info h1 { margin: 0 0 5px 0; font-size: 28px; font-weight: 800;}
        .header-info p { margin: 0 0 8px 0; font-size: 15px; color: #cbd5e1; font-weight: 500;}
        .badge { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}

        .content-section { padding: 40px; }
        
        h3.section-title { font-size: 16px; font-weight: 800; color: var(--dark-blue); margin: 0 0 20px 0; border-bottom: 2px solid var(--border-color); padding-bottom: 10px; display: flex; align-items: center; gap: 8px;}
        h3.section-title i { color: var(--primary-orange); }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px;}
        .stat-card { background: #f8fafc; border: 1px solid var(--border-color); padding: 20px; border-radius: 12px; text-align: center;}
        .stat-card h4 { margin: 0 0 8px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;}
        .stat-card h2 { margin: 0; font-size: 28px; font-weight: 800; color: var(--primary-orange);}
        .stat-card.revenue { grid-column: span 4; background: #ecfdf5; border-color: #a7f3d0;}
        .stat-card.revenue h2 { color: #059669; font-size: 36px;}

        .att-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;}
        .att-item { display: flex; flex-direction: column; justify-content: center; align-items: center; background: white; border: 1px solid var(--border-color); padding: 20px 15px; border-radius: 12px;}
        .att-item span:first-child { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px;}
        .att-item span:last-child { font-size: 24px; font-weight: 800; color: var(--dark-blue);}
        
        .att-good span:last-child { color: #10b981; }
        .att-warn span:last-child { color: #f59e0b; }
        .att-danger span:last-child { color: #ef4444; }

        .footer-note { text-align: center; padding: 20px; font-size: 12px; color: #94a3b8; background: #f8fafc; border-top: 1px dashed var(--border-color);}
        
        @media (max-width: 768px) {
            .stats-grid, .att-grid { grid-template-columns: 1fr 1fr; }
            .stat-card.revenue { grid-column: span 2; }
        }
    </style>
</head>
<body>

<div class="sheet-container">
    <div class="header-section">
        <img src="assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_photo'] ?? 'default.png'); ?>" class="profile-img" onerror="this.src='https://via.placeholder.com/150/00204a/ff6b00'">
        <div class="header-info">
            <h1><?php echo htmlspecialchars($user['first_name'] . ' ' .$user['last_name']); ?></h1>
            <p><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($user['company_name'] ?? 'N/A'); ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($user['business_category_applied'] ?? 'Uncategorized'); ?></p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                <span class="badge"><i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($user['group_name'] ?? 'No Chapter'); ?></span>
                <span class="badge"><i class="fa-solid fa-calendar-check"></i> Joined: <?php echo date('M Y', strtotime($user['joining_date'] ?? 'now')); ?></span>
            </div>
        </div>
    </div>

    <div class="content-section">
        <h3 class="section-title"><i class="fa-solid fa-trophy"></i> Lifetime Contribution Overview</h3>
        
        <div class="stats-grid">
            <div class="stat-card revenue">
                <h4>Total Revenue Generated (TYFCB)</h4>
                <h2>₹<?php echo number_format($stats['total_revenue_generated'] ?? 0); ?></h2>
            </div>
            <div class="stat-card">
                <h4>Links Given</h4>
                <h2><?php echo $stats['total_given_links'] ?? 0; ?></h2>
            </div>
            <div class="stat-card">
                <h4>1-to-1 Meetings</h4>
                <h2><?php echo $stats['total_121s'] ?? 0; ?></h2>
            </div>
            <div class="stat-card" style="grid-column: span 2;">
                <h4>Visitors Invited</h4>
                <h2><?php echo $stats['total_visitors'] ?? 0; ?></h2>
            </div>
        </div>

        <h3 class="section-title"><i class="fa-solid fa-calendar-days"></i> Rolling 6-Month Meeting Attendance</h3>
        <div class="att-grid">
            <div class="att-item att-good">
                <span>Present</span>
                <span><?php echo (int)($att['mtg_present'] ?? 0); ?></span>
            </div>
            <div class="att-item att-warn" style="background: #fffbeb; border-color: #fcd34d;">
                <span>Late</span>
                <span><?php echo (int)($att['mtg_late'] ?? 0); ?></span>
            </div>
            <div class="att-item att-danger" style="background: #fef2f2; border-color: #fecaca;">
                <span>Absents</span>
                <span><?php echo (int)($att['mtg_absent'] ?? 0); ?></span>
            </div>
            <div class="att-item">
                <span>Subs Sent</span>
                <span><?php echo (int)($att['total_subs'] ?? 0); ?></span>
            </div>
        </div>
        <?php if (($att['mtg_absent'] ?? 0) >= 3): ?>
            <p style="color: #ef4444; font-size: 13px; font-weight: 600; margin-top: 15px; background: #fef2f2; padding: 15px; border-radius: 8px; border: 1px solid #fecaca;"><i class="fa-solid fa-triangle-exclamation"></i> Warning: Member has reached 3 or more absences in the rolling 6-month period.</p>
        <?php endif; ?>
    </div>

    <div class="footer-note">
        Generated by WE KONNECTS Data Analytics &bull; Strictly Confidential
    </div>
</div>

</body>
</html>