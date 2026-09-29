<?php
// super_admin.php
session_start();
require_once 'config/database.php';

// STRICT SECURITY: Only the true Super Admin can access this command center
if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); 
    exit;
}

try {
    // GLOBAL KPI CALCULATIONS
    $stmtGlobal = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM groups WHERE status = 'Active') as total_chapters,
            (SELECT COUNT(*) FROM users WHERE status = 'Active') as total_members,
            (SELECT SUM(amount) FROM slips WHERE slip_type = 'TYFCB') as total_global_revenue,
            (SELECT COUNT(*) FROM visitors) as total_global_visitors,
            (SELECT COUNT(*) FROM users WHERE status = 'Pending_Setup' AND system_role = 'MEMBER') as pending_applications,
            (SELECT COUNT(*) FROM pending_renewals WHERE status = 'Pending') as pending_renewals
    ");
    $kpi = $stmtGlobal->fetch();

    // CHAPTER ANALYTICS (6-MONTH CYCLE)
    $stmtChapters = $pdo->query("
        SELECT 
            g.id, g.group_name,
            (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = g.id AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active' LIMIT 1) as ht_coordinator,
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND membership_status = 'Active') as active_members,
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND joining_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)) as joinings_6m,
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND membership_status != 'Active') as lost_members,
            (SELECT COUNT(*) FROM slips s JOIN group_members gm ON s.initiator_member_id = gm.user_id WHERE gm.group_id = g.id AND gm.membership_status = 'Active' AND s.slip_type = 'REFERRAL' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)) as links_6m,
            (
                SELECT COALESCE(ROUND((SUM(CASE WHEN a.attendance_status IN ('Present', 'Late', 'Substitute') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100), 100)
                FROM attendance a 
                JOIN chapter_meetings cm ON a.meeting_id = cm.id 
                WHERE cm.group_id = g.id AND cm.meeting_type <> 'Daily Status' AND cm.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            ) as att_perc
        FROM groups g 
        WHERE g.status = 'Active'
        ORDER BY g.group_name ASC
    ");
    $chapter_analytics = $stmtChapters->fetchAll();

} catch (PDOException $e) { 
    die("Database Error: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Command Center | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #334155; --text-muted: #94a3b8; }
        body { font-family: 'Inter', sans-serif; background-color: var(--sa-dark); color: white; margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 32px; width: 100%; box-sizing: border-box; }
        
        .sa-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;}
        .sa-header h1 { margin: 0; font-size: 28px; color: white; display: flex; align-items: center; gap: 12px;}
        .sa-badge { background: rgba(251, 191, 36, 0.1); color: var(--sa-gold); padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; border: 1px solid rgba(251, 191, 36, 0.3); letter-spacing: 1px; text-transform: uppercase;}

        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
        .kpi-card { background: var(--sa-panel); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); position: relative; overflow: hidden;}
        .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--sa-gold); }
        .kpi-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 8px; letter-spacing: 0.5px;}
        .kpi-value { font-size: 32px; font-weight: 800; color: white; margin: 0;}
        .kpi-icon { position: absolute; right: 20px; bottom: 20px; font-size: 40px; opacity: 0.1; color: white;}

        .module-section { margin-bottom: 40px; }
        .module-title { font-size: 16px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;}
        .module-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 16px; }
        
        .module-card { background: var(--sa-panel); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); text-decoration: none; color: white; transition: 0.3s; display: flex; flex-direction: column; gap: 15px;}
        .module-card:hover { transform: translateY(-5px); border-color: var(--sa-gold); box-shadow: 0 10px 20px rgba(0,0,0,0.5); background: #233147;}
        .module-icon { font-size: 28px; color: var(--sa-gold); }
        .module-info h3 { margin: 0 0 5px 0; font-size: 16px; font-weight: 700;}
        .module-info p { margin: 0; font-size: 13px; color: var(--text-muted); line-height: 1.4;}

        /* Analytics Table */
        .table-container { background: var(--sa-panel); border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; margin-top: 10px;}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: rgba(0,0,0,0.2); padding: 15px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color);}
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); color: white;}
        tr:last-child td { border-bottom: none; }
        .btn-export { background: #10b981; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 700; cursor: pointer; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 8px;}
        .btn-export:hover { background: #059669; }
        @media (max-width: 760px) {
            body { display: block; }
            .sidebar { position: sticky; width: 100%; height: auto; max-height: 38vh; flex-direction: row; overflow: auto; top: 0; }
            .sidebar-brand, .sidebar-heading, .sidebar-divider, .sidebar > div[style*="flex-grow"] { display: none; }
            .sidebar .nav-item { flex: 0 0 auto; padding: 12px; border-right: 0; white-space: nowrap; }
            .main-content { margin-left: 0; padding: 18px; }
            .sa-header { align-items: flex-start; gap: 14px; padding: 20px; }
            .sa-header h1 { font-size: 22px; }
            .kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 24px; }
            .kpi-card { padding: 18px; }
            .kpi-value { font-size: 25px; }
            .module-card { padding: 18px; }
            .table-container { overflow-x: auto; }
            .table-container table { min-width: 720px; }
        }
        @media (max-width: 420px) {
            .kpi-row { grid-template-columns: 1fr; }
            .sa-header { flex-direction: column; }
            .sa-header h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="sa-header">
        <h1><i class="fa-solid fa-globe"></i> System Overview</h1>
        <span class="sa-badge">Headquarters</span>
    </div>

    <div class="kpi-row">
        <div class="kpi-card">
            <i class="fa-solid fa-network-wired kpi-icon"></i>
            <div class="kpi-label">Total Active Chapters</div>
            <p class="kpi-value"><?php echo number_format($kpi['total_chapters']); ?></p>
        </div>
        <div class="kpi-card">
            <i class="fa-solid fa-users kpi-icon"></i>
            <div class="kpi-label">Total Global Members</div>
            <p class="kpi-value"><?php echo number_format($kpi['total_members']); ?></p>
        </div>
        <div class="kpi-card">
            <i class="fa-solid fa-sack-dollar kpi-icon"></i>
            <div class="kpi-label">Global System Revenue</div>
            <p class="kpi-value">₹<?php $rev = $kpi['total_global_revenue'] ?? 0; echo ($rev >= 100000) ? number_format($rev/100000, 2).' L' : number_format($rev); ?></p>
        </div>
        <div class="kpi-card">
            <i class="fa-solid fa-eye kpi-icon"></i>
            <div class="kpi-label">Total Visitors Processed</div>
            <p class="kpi-value"><?php echo number_format($kpi['total_global_visitors']); ?></p>
        </div>
        <div class="kpi-card">
            <i class="fa-solid fa-user-clock kpi-icon"></i>
            <div class="kpi-label">New Applications</div>
            <p class="kpi-value"><?php echo number_format($kpi['pending_applications']); ?></p>
        </div>
        <div class="kpi-card">
            <i class="fa-solid fa-file-invoice-dollar kpi-icon"></i>
            <div class="kpi-label">Pending Renewals</div>
            <p class="kpi-value"><?php echo number_format($kpi['pending_renewals']); ?></p>
        </div>
    </div>

    <div class="module-section">
        <h2 class="module-title">1. Franchise Operations</h2>
        <div class="module-grid">
            <a href="sa_manage_franchises.php" class="module-card">
                <i class="fa-solid fa-building-user module-icon"></i>
                <div class="module-info">
                    <h3>Franchise Owners & Regions</h3>
                    <p>Add/Edit franchise owners, define their territories, and allocate chapters.</p>
                </div>
            </a>
            <a href="sa_franchise_leaderboard.php" class="module-card">
                <i class="fa-solid fa-trophy module-icon"></i>
                <div class="module-info">
                    <h3>Franchise Leaderboard</h3>
                    <p>Compare performance, revenue generation, and member growth between franchisees.</p>
                </div>
            </a>
            <a href="sa_manage_chapters.php" class="module-card">
                <i class="fa-solid fa-sitemap module-icon"></i>
                <div class="module-info">
                    <h3>Global Chapter Control</h3>
                    <p>Create new chapters, suspend chapters, or reassign them to different owners.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="module-section">
        <h2 class="module-title">2. Financials, Subscriptions & Staff</h2>
        <div class="module-grid">
            <a href="pending_applications.php" class="module-card">
                <i class="fa-solid fa-user-clock module-icon"></i>
                <div class="module-info">
                    <h3>New Member Applications</h3>
                    <p>Review applicant details and assign each approved member to an active chapter.</p>
                </div>
            </a>
            <a href="admin_renewals.php" class="module-card">
                <i class="fa-solid fa-file-invoice-dollar module-icon"></i>
                <div class="module-info">
                    <h3>Membership Renewals</h3>
                    <p>Review pending membership renewals and payment approvals.</p>
                </div>
            </a>
            <a href="sa_royalties.php" class="module-card">
                <i class="fa-solid fa-hand-holding-dollar module-icon"></i>
                <div class="module-info">
                    <h3>Franchise Royalties</h3>
                    <p>Track commission splits and payout dues between Headquarters and Franchise Owners.</p>
                </div>
            </a>
            <!-- NEW EMPLOYEE MANAGEMENT LINK -->
            <a href="sa_manage_employees.php" class="module-card" style="border-color: #3b82f6;">
                <i class="fa-solid fa-id-badge module-icon" style="color: #3b82f6;"></i>
                <div class="module-info">
                    <h3>Employee Staff Roles</h3>
                    <p>Create staff accounts and assign specific operational permissions (e.g., Visitor Manager).</p>
                </div>
            </a>
        </div>
    </div>

    <div class="module-section">
        <h2 class="module-title">3. System Configuration & Auditing</h2>
        <div class="module-grid">
            <a href="sa_master_directory.php" class="module-card">
                <i class="fa-solid fa-users-gear module-icon"></i>
                <div class="module-info">
                    <h3>Global Master Directory</h3>
                    <p>Search, edit, or suspend any member across the entire ecosystem.</p>
                </div>
            </a>
            <a href="sa_manage_categories.php" class="module-card">
                <i class="fa-solid fa-layer-group module-icon"></i>
                <div class="module-info">
                    <h3>Business Categories</h3>
                    <p>Add, edit, or lock the master list of approved business categories.</p>
                </div>
            </a>
            <a href="sa_system_settings.php" class="module-card">
                <i class="fa-solid fa-sliders module-icon"></i>
                <div class="module-info">
                    <h3>Point & System Variables</h3>
                    <p>Adjust how many points a visitor is worth, edit attendance rules, and set constraints.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- NEW: CHAPTER ANALYTICS SECTION -->
    <div class="module-section">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px;">
            <h2 class="module-title" style="border: none; margin: 0; padding: 0;"><i class="fa-solid fa-chart-area" style="color:var(--sa-gold);"></i> 6-Month Chapter Analytics</h2>
            <a href="actions/export_chapter_analytics.php" class="btn-export"><i class="fa-solid fa-file-excel"></i> Export to CSV</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Chapter Name</th>
                        <th>Head Table (Coordinator)</th>
                        <th style="text-align:center;">Active Members</th>
                        <th style="text-align:center;">Joinings (6M)</th>
                        <th style="text-align:center;">Links Passed (6M)</th>
                        <th style="text-align:center;">Lost/Inactive</th>
                        <th style="text-align:right;">Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($chapter_analytics)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted);">No active chapters found.</td></tr>
                    <?php else: ?>
                        <?php foreach($chapter_analytics as $ca): ?>
                            <tr>
                                <td><strong style="color:var(--sa-gold);"><?php echo htmlspecialchars($ca['group_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ca['ht_coordinator'] ?? 'No Coordinator Assigned'); ?></td>
                                <td style="text-align:center; font-weight:bold;"><?php echo $ca['active_members']; ?></td>
                                <td style="text-align:center; color:#10b981; font-weight:bold;">+<?php echo $ca['joinings_6m']; ?></td>
                                <td style="text-align:center; color:#3b82f6; font-weight:bold;"><?php echo $ca['links_6m']; ?></td>
                                <td style="text-align:center; color:#ef4444; font-weight:bold;">-<?php echo $ca['lost_members']; ?></td>
                                <td style="text-align:right;">
                                    <span style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 6px; font-weight:bold; color: <?php echo ($ca['att_perc'] >= 80) ? '#10b981' : '#f59e0b'; ?>;">
                                        <?php echo $ca['att_perc']; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</body>
</html>