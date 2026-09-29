<?php
// chapter_summary.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['system_role'] === 'SUPER_ADMIN' || $_SESSION['system_role'] === 'FRANCHISE_OWNER');

try {
    $group_id = null;
    $all_groups = [];
    $is_coordinator = false;
    
    if ($is_admin) {
        if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
            $stmtGroups = $pdo->prepare("SELECT id, group_name FROM groups WHERE status = 'Active' AND franchise_owner_id = ? ORDER BY group_name ASC");
            $stmtGroups->execute([$user_id]);
            $all_groups = $stmtGroups->fetchAll();
        } else {
            $all_groups = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();
        }
        $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : ($all_groups[0]['id'] ?? null);
    } else {
        $stmtVerify = $pdo->prepare("SELECT group_id, group_name FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active'");
        $stmtVerify->execute([$user_id]);
        $coord = $stmtVerify->fetch();
        if (!$coord) die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Access Denied</h2><p>Restricted to Head Table.</p></div>");
        $group_id = $coord['group_id'];
        $all_groups[] = ['id' => $group_id, 'group_name' => $coord['group_name']];
        $is_coordinator = true;
    }

    $start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    if (!$start_date) $start_date = date('Y-m-01');
    if (!$end_date) $end_date = date('Y-m-t');

    $sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'links_given';
    $dir = filter_input(INPUT_GET, 'dir', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'desc';
    $dir_sql = (strtolower($dir) === 'asc') ? 'ASC' : 'DESC';

    $order_clause = "refs_given DESC"; 
    switch ($sort) {
        case 'name': $order_clause = "u.first_name $dir_sql"; break;
        case 'links_given': $order_clause = "refs_given $dir_sql"; break;
        case 'links_received': $order_clause = "refs_received $dir_sql"; break;
        case 'ones_done': $order_clause = "ones_done $dir_sql"; break;
        case 'visitors': $order_clause = "visitors_invited $dir_sql"; break;
        case 'revenue': $order_clause = "tyfcb_given $dir_sql"; break;
    }
    $order_clause .= ", u.first_name ASC";

    $chapter_stats = [];
    $member_stats = [];
    $attendance_stats = [];
    $health_score = 0;
    
    if ($group_id) {
        $stmtKPI = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT gm.user_id) as total_members,
                (SELECT SUM(amount) FROM slips s JOIN group_members gms ON s.initiator_member_id = gms.user_id WHERE s.slip_type = 'TYFCB' AND gms.group_id = ? AND gms.membership_status = 'Active' AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) <= ?) as total_revenue,
                (SELECT COUNT(*) FROM slips s JOIN group_members gms ON s.initiator_member_id = gms.user_id WHERE s.slip_type = 'REFERRAL' AND gms.group_id = ? AND gms.membership_status = 'Active' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as total_refs,
                (SELECT COUNT(*) FROM slips s JOIN group_members gms ON s.initiator_member_id = gms.user_id WHERE s.slip_type = '121' AND gms.group_id = ? AND gms.membership_status = 'Active' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as total_121s,
                (SELECT COUNT(*) FROM visitors v WHERE v.chapter_id = ? AND v.visit_date >= ? AND v.visit_date <= ? AND v.attended = 1) as total_visitors
            FROM group_members gm
            WHERE gm.group_id = ? AND gm.membership_status = 'Active'
        ");
        $stmtKPI->execute([$group_id, $start_date, $end_date, $group_id, $start_date, $end_date, $group_id, $start_date, $end_date, $group_id, $start_date, $end_date, $group_id]);
        $chapter_stats = $stmtKPI->fetch();

        $stmtMembers = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, b.company_name,
                (SELECT COUNT(*) FROM slips s WHERE s.initiator_member_id = u.id AND s.slip_type = 'REFERRAL' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as refs_given,
                (SELECT COUNT(*) FROM slips s WHERE s.receiver_member_id = u.id AND s.slip_type = 'REFERRAL' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as refs_received,
                (SELECT SUM(amount) FROM slips s WHERE s.initiator_member_id = u.id AND s.slip_type = 'TYFCB' AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) <= ?) as tyfcb_given,
                (SELECT COUNT(*) FROM slips s WHERE s.initiator_member_id = u.id AND s.slip_type = '121' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as ones_done,
                (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND chapter_id = ? AND visit_date >= ? AND visit_date <= ? AND attended = 1) as visitors_invited
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            LEFT JOIN businesses b ON u.id = b.user_id
            WHERE gm.group_id = ? AND gm.membership_status = 'Active'
            ORDER BY $order_clause
        ");
        $stmtMembers->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $group_id, $start_date, $end_date, $group_id]);
        $member_stats = $stmtMembers->fetchAll();

        $stmtAtt = $pdo->prepare("
            SELECT u.first_name, u.last_name, b.company_name,
                SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) as pres_count,
                SUM(CASE WHEN a.attendance_status = 'Late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN a.attendance_status = 'Substitute' THEN 1 ELSE 0 END) as sub_count
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            LEFT JOIN businesses b ON u.id = b.user_id
            LEFT JOIN chapter_meetings cm ON cm.group_id = gm.group_id AND cm.meeting_type IN ('Meeting', 'Event', 'SOM') AND cm.meeting_date >= ? AND cm.meeting_date <= ? AND cm.meeting_date >= COALESCE(NULLIF(gm.joining_date, '0000-00-00'), gm.join_date)
            LEFT JOIN attendance a ON a.meeting_id = cm.id AND a.user_id = u.id
            WHERE gm.group_id = ? AND gm.membership_status = 'Active'
            GROUP BY u.id
            ORDER BY u.first_name ASC
        ");
        $stmtAtt->execute([$start_date, $end_date, $group_id]);
        $attendance_stats = $stmtAtt->fetchAll();
        
        // --- NEW: CHAPTER HEALTH SCORE ALGORITHM ---
        $active_mems = max(1, $chapter_stats['total_members']);
        
        // 1. Attendance Rate (Target: 90%)
        $total_pres = 0; $total_abs = 0;
        foreach($attendance_stats as $a) { $total_pres += ($a['pres_count'] + $a['late_count'] + $a['sub_count']); $total_abs += $a['absent_count']; }
        $total_records = max(1, $total_pres + $total_abs);
        $att_score = ($total_pres / $total_records) * 100;
        
        // 2. Link Velocity (Target: 4 links per member per month)
        // Assume 4 weeks in a month timeframe
        $target_links = $active_mems * 4;
        $link_score = min(100, (($chapter_stats['total_refs'] ?? 0) / $target_links) * 100);
        
        // 3. Growth (Target: 1 visitor per member per month)
        $target_vis = $active_mems * 1;
        $vis_score = min(100, (($chapter_stats['total_visitors'] ?? 0) / max(1, $target_vis)) * 100);
        
        // Aggregate Health Score
        $health_score = round(($att_score * 0.5) + ($link_score * 0.3) + ($vis_score * 0.2));
    }
} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }

function sortHeader($colKey, $label, $current_sort, $current_dir, $group_id, $start_date, $end_date) {
    $is_active = ($current_sort === $colKey);
    $new_dir = ($is_active && $current_dir === 'desc') ? 'asc' : 'desc';
    $icon = '';
    if ($is_active) {
        $icon = ($current_dir === 'desc') ? '<i class="fa-solid fa-arrow-down-short-wide sort-icon"></i>' : '<i class="fa-solid fa-arrow-up-wide-short sort-icon"></i>';
    }
    $color = $is_active ? 'var(--primary-orange)' : 'inherit';
    $url = "?group_id=$group_id&start_date=$start_date&end_date=$end_date&sort=$colKey&dir=$new_dir";
    return "<a href='$url' class='sort-link' style='color: $color;'>$label $icon</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chapter Analytics | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f4f7f6; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        <?php if($is_admin): ?>
            .sidebar { width: 260px; background-color: var(--dark-blue); color: #fff; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; top:0; left:0;}
            .sidebar-brand { padding: 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 18px;}
            .nav-item { padding: 15px 24px; display: flex; align-items: center; gap: 15px; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s; }
            .nav-item:hover, .nav-item.active { background-color: rgba(255, 107, 0, 0.1); color: #ff6b00; border-right: 4px solid #ff6b00; }
            .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        <?php else: ?>
            .main-content { max-width: 1000px; margin: 0 auto; padding: 20px; width: 100%; }
        <?php endif; ?>
        
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,32,74,0.15); flex-wrap: wrap; gap: 15px;}
        
        .export-btns { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-export { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 8px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px;}
        .btn-export:hover { background: white; color: var(--dark-blue); }
        .btn-term { background: var(--primary-orange); border-color: var(--primary-orange); }
        .btn-term:hover { background: #e66000; color: white;}

        .filter-bar { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; box-shadow: 0 4px 6px rgba(0,0,0,0.02); flex-wrap: wrap;}
        .form-group { margin: 0; flex: 1; min-width: 150px;}
        .form-group label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-input { width: 100%; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; outline: none; background: #f8fafc; color: #0f172a;}
        .filter-input:focus { border-color: var(--primary-orange); }
        .btn-search { background: var(--primary-orange); color: white; border: none; padding: 11px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; height: 42px;}
        .btn-search:hover { background: #e66000; box-shadow: 0 4px 10px rgba(255,107,0,0.2); }

        .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px; }
        @media (max-width: 900px) { .kpi-grid { grid-template-columns: 1fr 1fr; } }
        .kpi-card { background: white; padding: 25px 15px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02); text-align: center;}
        .kpi-card h4 { margin: 0 0 10px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;}
        .kpi-card h2 { margin: 0; font-size: 28px; font-weight: 800; color: var(--dark-blue);}

        .health-card { background: linear-gradient(135deg, #10b981, #059669); color: white; border: none;}
        .health-card.warn { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .health-card.danger { background: linear-gradient(135deg, #ef4444, #b91c1c); }
        .health-card h4 { color: rgba(255,255,255,0.8); }
        .health-card h2 { color: white; }

        .view-tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .view-btn { background: white; border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; transition: 0.2s;}
        .view-btn.active { background: var(--dark-blue); color: white; border-color: var(--dark-blue); }

        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02); overflow-x: auto;}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); white-space: nowrap; transition: 0.2s;}
        th:hover { background: #f1f5f9; }
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); color: #0f172a;}
        tr:hover { background: #f8fafc; }
        
        .member-link { color: var(--dark-blue); text-decoration: none; font-weight: 700; transition: 0.2s;}
        .member-link:hover { color: var(--primary-orange); text-decoration: underline;}
        
        .sort-link { text-decoration: none; display: inline-flex; align-items: center; gap: 5px; width: 100%; justify-content: inherit;}
        .sort-icon { font-size: 10px; margin-left: 2px; }
    </style>
</head>
<body>

<?php if ($is_admin) include 'includes/sidebar.php'; ?>

<main class="main-content">
    <?php if (!$is_admin): ?>
        <a href="head_table.php" style="color: #64748b; text-decoration: none; margin-bottom: 15px; display: inline-block; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Head Table Portal</a>
    <?php endif; ?>

    <div class="header-box">
        <div>
            <h1 style="margin:0; font-size: 24px;">Chapter Analytics</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.8;">Custom date exports and attendance tracking.</p>
        </div>
        <div class="export-btns">
            <a href="actions/export_analytics.php?group_id=<?php echo $group_id; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>&type=performance" class="btn-export"><i class="fa-solid fa-download"></i> Performance CSV</a>
            <a href="actions/export_analytics.php?group_id=<?php echo $group_id; ?>&start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>&type=attendance" class="btn-export"><i class="fa-solid fa-user-clock"></i> Attendance CSV</a>
            <?php if ($is_coordinator || $is_admin): ?>
                <a href="actions/export_analytics.php?group_id=<?php echo $group_id; ?>&start=<?php echo date('Y-m-d', strtotime('-6 months')); ?>&end=<?php echo date('Y-m-d'); ?>&type=term" class="btn-export btn-term" title="Downloads entire 6-month term report"><i class="fa-solid fa-file-contract"></i> 6-Month Term Report</a>
            <?php endif; ?>
        </div>
    </div>

    <form action="" method="GET" class="filter-bar">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">

        <?php if ($is_admin): ?>
            <div class="form-group">
                <label>Select Chapter</label>
                <select name="group_id" class="filter-input" style="cursor: pointer;">
                    <?php foreach ($all_groups as $grp): ?>
                        <option value="<?php echo $grp['id']; ?>" <?php if($group_id == $grp['id']) echo 'selected'; ?>><?php echo htmlspecialchars($grp['group_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label><i class="fa-regular fa-calendar" style="color: var(--primary-orange);"></i> Start Date</label>
            <input type="date" name="start_date" class="filter-input" value="<?php echo htmlspecialchars($start_date); ?>" required>
        </div>
        
        <div class="form-group">
            <label><i class="fa-regular fa-calendar-check" style="color: var(--primary-orange);"></i> End Date</label>
            <input type="date" name="end_date" class="filter-input" value="<?php echo htmlspecialchars($end_date); ?>" required>
        </div>
        
        <div style="margin: 0; display: flex; align-items: flex-end;">
            <button type="submit" class="btn-search"><i class="fa-solid fa-filter"></i> Apply Filter</button>
        </div>
    </form>

    <?php if ($group_id): 
        $health_class = '';
        if ($health_score < 75) $health_class = 'warn';
        if ($health_score < 50) $health_class = 'danger';
    ?>
        <div class="kpi-grid">
            <div class="kpi-card health-card <?php echo $health_class; ?>">
                <h4>Health Score</h4>
                <h2><?php echo $health_score; ?>%</h2>
            </div>
            <div class="kpi-card" style="border-top: 4px solid #3b82f6;">
                <h4>Total Revenue</h4>
                <h2>₹<?php $rev = $chapter_stats['total_revenue'] ?? 0; echo ($rev >= 1000000) ? number_format($rev/1000000, 2).'M' : (($rev >= 1000) ? number_format($rev/1000, 1).'k' : number_format($rev)); ?></h2>
            </div>
            <div class="kpi-card" style="border-top: 4px solid #10b981;">
                <h4>Links Passed</h4>
                <h2><?php echo number_format($chapter_stats['total_refs'] ?? 0); ?></h2>
            </div>
            <div class="kpi-card" style="border-top: 4px solid #f59e0b;">
                <h4>1-to-1s Logged</h4>
                <h2><?php echo number_format($chapter_stats['total_121s'] ?? 0); ?></h2>
            </div>
            <div class="kpi-card" style="border-top: 4px solid #8b5cf6;">
                <h4>Verified Visitors</h4>
                <h2><?php echo number_format($chapter_stats['total_visitors'] ?? 0); ?></h2>
            </div>
        </div>

        <div class="view-tabs">
            <button class="view-btn active" id="btn-perf" onclick="switchView('perf')"><i class="fa-solid fa-chart-line"></i> Performance View</button>
            <button class="view-btn" id="btn-att" onclick="switchView('att')"><i class="fa-solid fa-user-clock"></i> Detailed Attendance View</button>
        </div>

        <!-- PERFORMANCE VIEW -->
        <div class="table-container" id="view-perf">
            <table>
                <thead>
                    <tr>
                        <th style="text-align: left;"><?php echo sortHeader('name', 'Member Name', $sort, strtolower($dir), $group_id, $start_date, $end_date); ?></th>
                        <th style="text-align: center; justify-content: center;"><?php echo sortHeader('links_given', 'Links Given', $sort, strtolower($dir), $group_id, $start_date, $end_date); ?></th>
                        <th style="text-align: center; justify-content: center;"><?php echo sortHeader('links_received', 'Links Rcvd', $sort, strtolower($dir), $group_id, $start_date, $end_date); ?></th>
                        <th style="text-align: center; justify-content: center;"><?php echo sortHeader('ones_done', '1-to-1s', $sort, strtolower($dir), $group_id, $start_date, $end_date); ?></th>
                        <th style="text-align: center; justify-content: center;"><?php echo sortHeader('visitors', 'Visitors', $sort, strtolower($dir), $group_id, $start_date, $end_date); ?></th>
                        <th style="text-align: right; justify-content: flex-end;"><?php echo sortHeader('revenue', 'Revenue (TYFCB)', $sort, strtolower($dir), $group_id, $start_date, $end_date); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($member_stats)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">No performance data found for the selected dates.</td></tr>
                    <?php else: ?>
                        <?php foreach ($member_stats as $m): ?>
                            <tr>
                                <td>
                                    <a href="member_performance_sheet.php?id=<?php echo $m['id']; ?>" class="member-link" target="_blank" title="View Full Performance Sheet">
                                        <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                                    </a><br>
                                    <span style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($m['company_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: <?php echo ($sort === 'links_given') ? 'var(--primary-orange)' : '#3b82f6'; ?>;"><?php echo $m['refs_given'] ?? 0; ?></td>
                                <td style="text-align: center; font-weight: 600; color: <?php echo ($sort === 'links_received') ? 'var(--primary-orange)' : '#64748b'; ?>;"><?php echo $m['refs_received'] ?? 0; ?></td>
                                <td style="text-align: center; font-weight: 700; color: <?php echo ($sort === 'ones_done') ? 'var(--primary-orange)' : '#10b981'; ?>;"><?php echo $m['ones_done'] ?? 0; ?></td>
                                <td style="text-align: center; font-weight: 700; color: <?php echo ($sort === 'visitors') ? 'var(--primary-orange)' : '#8b5cf6'; ?>;"><?php echo $m['visitors_invited'] ?? 0; ?></td>
                                <td style="text-align: right; font-weight: 800; color: <?php echo ($sort === 'revenue') ? 'var(--primary-orange)' : '#059669'; ?>;">₹<?php echo number_format($m['tyfcb_given'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ATTENDANCE VIEW -->
        <div class="table-container" id="view-att" style="display:none;">
            <table>
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th style="text-align: center;">Present</th>
                        <th style="text-align: center;">Late</th>
                        <th style="text-align: center;">Absent</th>
                        <th style="text-align: center;">Subs Sent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance_stats)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">No attendance data found for the selected dates.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendance_stats as $a): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></strong><br>
                                    <span style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($a['company_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td style="text-align: center; font-weight: 800; color: #10b981;"><?php echo $a['pres_count'] ?? 0; ?></td>
                                <td style="text-align: center; font-weight: 800; color: #f59e0b;"><?php echo $a['late_count'] ?? 0; ?></td>
                                <td style="text-align: center; font-weight: 800; color: #ef4444;"><?php echo $a['absent_count'] ?? 0; ?></td>
                                <td style="text-align: center; font-weight: 800; color: #3b82f6;"><?php echo $a['sub_count'] ?? 0; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<script>
    function switchView(view) {
        document.getElementById('view-perf').style.display = 'none';
        document.getElementById('view-att').style.display = 'none';
        document.getElementById('btn-perf').classList.remove('active');
        document.getElementById('btn-att').classList.remove('active');
        
        document.getElementById('view-' + view).style.display = 'block';
        document.getElementById('btn-' + view).classList.add('active');
    }
</script>

</body>
</html>