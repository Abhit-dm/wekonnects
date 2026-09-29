<?php
// attendance_dashboard.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

try {
    // Verify they are the Attendance Coordinator
    $stmtVerify = $pdo->prepare("SELECT gm.group_id, g.group_name FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND gm.leadership_role = 'Attendance' AND gm.membership_status = 'Active'");
    $stmtVerify->execute([$user_id]);
    $coord = $stmtVerify->fetch();

    if (!$coord) { die("<div style='text-align:center; padding:50px;'><h2>Access Denied</h2><p>You are not assigned as an Attendance Coordinator.</p></div>"); }
    
    $group_name = $coord['group_name'];

} catch (PDOException $e) { die("System Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Attendance Coordinator Panel | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --brand-blue: #00204a; --brand-orange: #ff6b00; --bg-color: #f8fafc; --card-bg: #ffffff; --border: #e2e8f0; --text-main: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-color); color: var(--text-main); margin: 0; padding-bottom: 50px; overflow-x: hidden;}
        
        .portal-container { width: 100%; max-width: 600px; margin: 0 auto; padding: 15px; box-sizing: border-box; }
        
        .header-box { background: var(--brand-blue); padding: 25px 20px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8px 20px rgba(0,32,74,0.15); position: relative; overflow: hidden;}
        .header-box::after { content: ''; position: absolute; right: -20px; bottom: -20px; width: 100px; height: 100px; background: #3b82f6; border-radius: 50%; filter: blur(40px); opacity: 0.3; z-index: 1;}
        .header-content { position: relative; z-index: 2;}
        .header-box h1 { margin: 0 0 5px 0; font-size: 20px; font-weight: 800;}
        .header-box p { margin: 0; color: #93c5fd; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;}
        
        .action-grid { display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px; }
        .action-card { background: var(--card-bg); padding: 20px; border-radius: 16px; border: 1px solid var(--border); text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: 0.2s;}
        .action-card:active { transform: scale(0.98); }
        .action-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .action-text { flex-grow: 1; }
        .action-title { font-weight: 800; font-size: 16px; margin-bottom: 5px; color: var(--brand-blue);}
        .action-desc { font-size: 12px; color: var(--text-muted); margin: 0; line-height: 1.4;}

        .theme-blue .action-icon { background: #eff6ff; color: #3b82f6; }
        .theme-green .action-icon { background: #ecfdf5; color: #10b981; }
    </style>
</head>
<body>

<div class="portal-container">
    <a href="index.php" style="color: var(--text-muted); text-decoration: none; margin-bottom: 15px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

    <div class="header-box">
        <div class="header-content">
            <p><?php echo htmlspecialchars($group_name); ?></p>
            <h1>Attendance Coordinator</h1>
        </div>
        <i class="fa-solid fa-clipboard-user" style="font-size: 40px; color: #3b82f6; position: relative; z-index: 2;"></i>
    </div>

    <div class="action-grid">
        <a href="attendance_manager.php" class="action-card theme-blue">
            <div class="action-icon"><i class="fa-solid fa-users"></i></div>
            <div class="action-text">
                <div class="action-title">1. Meeting Attendance</div>
                <p class="action-desc">Take roll call for weekly meetings and award standard meeting points.</p>
            </div>
            <i class="fa-solid fa-chevron-right" style="color: #cbd5e1;"></i>
        </a>
        
        <a href="daily_status_portal.php" class="action-card theme-green">
            <div class="action-icon"><i class="fa-solid fa-bolt"></i></div>
            <div class="action-text">
                <div class="action-title">2. Status Points & Bulk Entry</div>
                <p class="action-desc">Award daily status points, early bird points, or use the 15-day bulk entry tool.</p>
            </div>
            <i class="fa-solid fa-chevron-right" style="color: #cbd5e1;"></i>
        </a>
    </div>
</div>

</body>
</html>