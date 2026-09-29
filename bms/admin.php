<?php
// admin.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}
$first_name = $_SESSION['first_name'];

try {
    if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
        $stmtMembers = $pdo->prepare("SELECT COUNT(DISTINCT gm.user_id) FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.membership_status = 'Active' AND g.franchise_owner_id = ?");
        $stmtMembers->execute([$_SESSION['user_id']]);
        $total_members = $stmtMembers->fetchColumn();

        $stmtChapters = $pdo->prepare("SELECT COUNT(*) FROM groups WHERE status = 'Active' AND franchise_owner_id = ?");
        $stmtChapters->execute([$_SESSION['user_id']]);
        $total_chapters = $stmtChapters->fetchColumn();

        $stmtDeals = $pdo->prepare("SELECT COALESCE(SUM(s.amount), 0) FROM slips s JOIN group_members gm ON s.initiator_member_id = gm.user_id JOIN groups g ON gm.group_id = g.id WHERE s.slip_type = 'TYFCB' AND gm.membership_status = 'Active' AND g.franchise_owner_id = ?");
        $stmtDeals->execute([$_SESSION['user_id']]);
        $total_revenue = $stmtDeals->fetchColumn();

        $stmtActivity = $pdo->prepare("SELECT SUM(CASE WHEN s.slip_type = '121' THEN 1 ELSE 0 END) as total_121, SUM(CASE WHEN s.slip_type = 'REFERRAL' THEN 1 ELSE 0 END) as total_refs FROM slips s JOIN group_members gm ON s.initiator_member_id = gm.user_id JOIN groups g ON gm.group_id = g.id WHERE gm.membership_status = 'Active' AND g.franchise_owner_id = ?");
        $stmtActivity->execute([$_SESSION['user_id']]);
        $activity = $stmtActivity->fetch();

        $stmtVis = $pdo->prepare("SELECT COUNT(*) FROM visitors v JOIN groups g ON v.chapter_id = g.id WHERE g.franchise_owner_id = ?");
        $stmtVis->execute([$_SESSION['user_id']]);
        $total_visitors = $stmtVis->fetchColumn();
    } else {
        $stmtMembers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM group_members WHERE membership_status = 'Active'");
        $total_members = $stmtMembers->fetchColumn();
        $total_chapters = $pdo->query("SELECT COUNT(*) FROM groups WHERE status = 'Active'")->fetchColumn();
        $stmtDeals = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM slips WHERE slip_type = 'TYFCB'");
        $total_revenue = $stmtDeals->fetchColumn();
        $activity = $pdo->query("SELECT SUM(CASE WHEN slip_type = '121' THEN 1 ELSE 0 END) as total_121, SUM(CASE WHEN slip_type = 'REFERRAL' THEN 1 ELSE 0 END) as total_refs FROM slips")->fetch();
        $total_visitors = $pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
    }

    $stmtPending = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Pending_Setup' AND system_role = 'MEMBER'");
    $total_pending = $stmtPending->fetchColumn();

    $stmtRecentPending = $pdo->query("
        SELECT u.first_name, u.last_name, u.created_at, b.company_name 
        FROM users u LEFT JOIN businesses b ON u.id = b.user_id 
        WHERE u.status IN ('Pending', 'Pending_Setup') ORDER BY u.created_at DESC LIMIT 4
    ");
    $recent_pending = $stmtRecentPending->fetchAll();

    // NEW: Fetch Pending Renewals for the Admin Dashboard Alert
    $renewal_scope = $_SESSION['system_role'] === 'FRANCHISE_OWNER' ? ' AND g.franchise_owner_id = ?' : '';
    $stmtRecentRenewals = $pdo->prepare(" 
        SELECT pr.id, u.first_name, u.last_name, g.group_name, pr.submitted_at 
        FROM pending_renewals pr 
        JOIN users u ON pr.user_id = u.id 
        JOIN groups g ON pr.group_id = g.id 
        WHERE pr.status = 'Pending' $renewal_scope
        ORDER BY pr.submitted_at DESC LIMIT 4
    ");
    $stmtRecentRenewals->execute($_SESSION['system_role'] === 'FRANCHISE_OWNER' ? [$_SESSION['user_id']] : []);
    $recent_renewals = $stmtRecentRenewals->fetchAll();

} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Dashboard | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; margin: 0; display: flex; min-height: 100vh;}
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 30px 40px; border-radius: 16px; margin-bottom: 30px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 25px rgba(0,32,74,0.1);}
        
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 20px; transition: 0.3s;}
        .kpi-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;}
        .kpi-info h4 { margin: 0 0 5px 0; font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;}
        .kpi-info h2 { margin: 0; font-size: 26px; font-weight: 800; color: var(--dark-blue);}

        .layout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start;}
        
        .dashboard-panel { background: white; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02); overflow: hidden; margin-bottom: 25px;}
        .panel-header { padding: 20px 25px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;}
        .panel-header h3 { margin: 0; font-size: 16px; color: var(--dark-blue); }
        .panel-body { padding: 25px; }

        .quick-links { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;}
        .ql-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; text-decoration: none; color: var(--dark-blue); transition: 0.2s;}
        .ql-btn:hover { background: white; border-color: var(--primary-orange); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(255,107,0,0.1);}
        .ql-btn i { font-size: 24px; color: var(--primary-orange); margin-bottom: 10px; }
        .ql-btn span { font-size: 12px; font-weight: 600; }

        .pending-list { display: flex; flex-direction: column; gap: 12px; }
        .pending-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #fff; border: 1px solid var(--border-color); border-radius: 10px; transition: 0.2s;}
        .pending-item:hover { border-color: #cbd5e1; background: #f8fafc;}
        .pending-info h4 { margin: 0 0 4px 0; font-size: 14px; color: var(--dark-blue);}
        .pending-info p { margin: 0; font-size: 12px; color: #64748b;}
        .btn-view { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none;}

        .stat-row { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px dashed var(--border-color);}
        .stat-row:last-child { border-bottom: none; padding-bottom: 0;}
        .stat-label { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; color: #475569;}
        .stat-value { font-size: 18px; font-weight: 800; color: var(--dark-blue);}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    
    <div class="header-box">
        <div>
            <h1 style="margin:0 0 5px 0; font-size: 26px;">Welcome back, <?php echo htmlspecialchars($first_name); ?></h1>
            <p style="margin:0; font-size: 14px; color: rgba(255,255,255,0.8);">Here is what's happening across the WE KONNECTS franchise today.</p>
        </div>
        <i class="fa-solid fa-chart-line" style="font-size: 45px; color: rgba(255,255,255,0.2);"></i>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;"><i class="fa-solid fa-users"></i></div>
            <div class="kpi-info">
                <h4>Active Members</h4>
                <h2><?php echo number_format($total_members); ?></h2>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #fdf4ff; color: #c026d3;"><i class="fa-solid fa-building-user"></i></div>
            <div class="kpi-info">
                <h4>Total Chapters</h4>
                <h2><?php echo number_format($total_chapters); ?></h2>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #fffbeb; color: #d97706;"><i class="fa-solid fa-user-clock"></i></div>
            <div class="kpi-info">
                <h4>Pending Apps</h4>
                <h2><?php echo number_format($total_pending); ?></h2>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #ecfdf5; color: #059669;"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="kpi-info">
                <h4>Total Revenue</h4>
                <h2><?php echo ($total_revenue >= 1000000) ? number_format($total_revenue/1000000, 2).'M' : (($total_revenue >= 1000) ? number_format($total_revenue/1000, 1).'k' : number_format($total_revenue)); ?></h2>
            </div>
        </div>
    </div>

    <div class="layout-grid">
        <div>
            <div class="quick-links">
                <a href="admin_directory.php" class="ql-btn"><i class="fa-solid fa-address-book"></i><span>Directory</span></a>
                <a href="manage_chapters.php" class="ql-btn"><i class="fa-solid fa-sitemap"></i><span>Chapters</span></a>
                <a href="admin_renewals.php" class="ql-btn"><i class="fa-solid fa-file-invoice-dollar"></i><span>Renewals</span></a>
                <a href="chapter_summary.php" class="ql-btn"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
            </div>

            <div class="dashboard-panel">
                <div class="panel-header">
                    <h3><i class="fa-solid fa-bell" style="color:var(--primary-orange); margin-right:8px;"></i> Action Required: Pending Apps</h3>
                    <a href="pending_applications.php" style="font-size:12px; color:#2563eb; font-weight:600; text-decoration:none;">View All</a>
                </div>
                <div class="panel-body">
                    <?php if (empty($recent_pending)): ?>
                        <div style="text-align:center; color:#94a3b8; padding: 20px;">
                            <i class="fa-solid fa-check-double" style="font-size:30px; margin-bottom:10px; color:#cbd5e1;"></i>
                            <p style="margin:0; font-size:14px;">You're all caught up! No pending applications.</p>
                        </div>
                    <?php else: ?>
                        <div class="pending-list">
                            <?php foreach($recent_pending as $p): ?>
                                <div class="pending-item">
                                    <div class="pending-info">
                                        <h4><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($p['company_name'] ?? 'No Company Listed'); ?> &bull; Applied: <?php echo date('M d', strtotime($p['created_at'])); ?></p>
                                    </div>
                                    <a href="pending_applications.php" class="btn-view">Review</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-panel">
                <div class="panel-header">
                    <h3><i class="fa-solid fa-clock-rotate-left" style="color:#10b981; margin-right:8px;"></i> Action Required: Pending Renewals</h3>
                    <a href="admin_renewals.php" style="font-size:12px; color:#2563eb; font-weight:600; text-decoration:none;">View All</a>
                </div>
                <div class="panel-body">
                    <?php if (empty($recent_renewals)): ?>
                        <div style="text-align:center; color:#94a3b8; padding: 20px;">
                            <i class="fa-solid fa-check-double" style="font-size:30px; margin-bottom:10px; color:#cbd5e1;"></i>
                            <p style="margin:0; font-size:14px;">All membership renewals are processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="pending-list">
                            <?php foreach($recent_renewals as $r): ?>
                                <div class="pending-item" style="border-left: 4px solid #10b981;">
                                    <div class="pending-info">
                                        <h4><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($r['group_name']); ?> &bull; Submitted: <?php echo date('M d', strtotime($r['submitted_at'])); ?></p>
                                    </div>
                                    <a href="admin_renewals.php" class="btn-view" style="color:#047857; background:#ecfdf5; border-color:#a7f3d0;">Review</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div>
            <div class="dashboard-panel">
                <div class="panel-header">
                    <h3><i class="fa-solid fa-globe" style="color:#3b82f6; margin-right:8px;"></i> Global Activity</h3>
                </div>
                <div class="panel-body">
                    <p style="margin: 0 0 20px 0; font-size: 12px; color: #64748b; line-height: 1.5;">Total networking activity logged across all chapters.</p>
                    
                    <div class="stat-row">
                        <span class="stat-label"><i class="fa-solid fa-file-invoice-dollar" style="color:#8b5cf6;"></i> Total Links Passed</span>
                        <span class="stat-value"><?php echo number_format($activity['total_refs'] ?? 0); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label"><i class="fa-solid fa-handshake" style="color:#10b981;"></i> Total 1-to-1s</span>
                        <span class="stat-value"><?php echo number_format($activity['total_121'] ?? 0); ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label"><i class="fa-solid fa-user-plus" style="color:#0ea5e9;"></i> Total Visitors</span>
                        <span class="stat-value"><?php echo number_format($total_visitors); ?></span>
                    </div>
                    
                    <div style="margin-top: 25px;">
                        <a href="directory.php" class="ql-btn" style="width: 100%; box-sizing: border-box; flex-direction: row; gap: 10px; padding: 15px;">
                            <i class="fa-solid fa-magnifying-glass" style="margin:0; font-size:16px;"></i>
                            <span style="font-size:14px;">Open Global Search</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

</body>
</html>