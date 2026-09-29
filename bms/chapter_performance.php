<?php
// chapter_performance.php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['month', '6months', 'year', 'all'], true) ? $_GET['filter'] : 'month';

$date_sql_links = "";
$date_sql_deals = "";
$date_sql_vis = "";
$board_title = "All-Time Performance";

if ($filter === 'month') {
    $date_sql_links = " AND COALESCE(NULLIF(link_given_date, '0000-00-00'), date_logged) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $date_sql_deals = " AND COALESCE(NULLIF(deal_close_date, '0000-00-00'), date_logged) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $date_sql_vis = " AND visit_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $board_title = date('F Y') . " Scoreboard"; 
} elseif ($filter === '6months') {
    $date_sql_links = " AND COALESCE(NULLIF(link_given_date, '0000-00-00'), date_logged) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    $date_sql_deals = " AND COALESCE(NULLIF(deal_close_date, '0000-00-00'), date_logged) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    $date_sql_vis = " AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    $board_title = "Past 6 Months Performance";
} elseif ($filter === 'year') {
    $date_sql_links = " AND COALESCE(NULLIF(link_given_date, '0000-00-00'), date_logged) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $date_sql_deals = " AND COALESCE(NULLIF(deal_close_date, '0000-00-00'), date_logged) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $date_sql_vis = " AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $board_title = "Past 1 Year Performance";
}

try {
    $stmtVerify = $pdo->prepare("SELECT group_id, g.group_name FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active'");
    $stmtVerify->execute([$user_id]);
    $coord = $stmtVerify->fetch();

    if (!$coord) die("<div style='text-align:center; padding:50px; color:white;'><h2>Access Denied</h2><p>Restricted to Coordinators.</p></div>");
    
    $group_id = $coord['group_id'];
    $group_name = $coord['group_name'];

    $sql = "
        SELECT 
            u.id, u.first_name, u.last_name, u.profile_photo,
            b.company_name,
            (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'REFERRAL' $date_sql_links) as total_links,
            (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = '121' $date_sql_links) as total_121,
            (SELECT COALESCE(SUM(amount), 0) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'TYFCB' $date_sql_deals) as total_revenue,
            (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND chapter_id = ? AND attended = 1 $date_sql_vis) as total_visitors
        FROM group_members gm
        JOIN users u ON gm.user_id = u.id
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE gm.group_id = ? AND gm.membership_status = 'Active'
        ORDER BY total_revenue DESC, total_links DESC
    ";
    
    $stmtBoard = $pdo->prepare($sql);
    $stmtBoard->execute([$group_id, $group_id]);
    $leaderboard = $stmtBoard->fetchAll();

    $chapter_revenue = 0; $chapter_links = 0; $chapter_visitors = 0;
    foreach ($leaderboard as $member) {
        $chapter_revenue += $member['total_revenue'];
        $chapter_links += $member['total_links'];
        $chapter_visitors += $member['total_visitors'];
    }

} catch (PDOException $e) { die("System Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Performance | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --white: #ffffff; }
        body { font-family: 'Inter', sans-serif; background: radial-gradient(circle at 10% 20%, rgb(0, 32, 74) 0%, rgb(0, 15, 35) 90%); color: var(--white); margin: 0; min-height: 100vh; }
        
        .portal-container { max-width: 800px; margin: 0 auto; padding: 20px; padding-bottom: 100px; }
        .back-link { color: rgba(255,255,255,0.6); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 600; transition: 0.2s; }
        .back-link:hover { color: var(--primary-orange); }

        .header-box { background: rgba(255,255,255,0.05); padding: 25px; border-radius: 16px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
        .header-text h1 { margin: 0 0 5px 0; font-size: 20px; color: var(--white); }
        .header-text p { margin: 0; font-size: 13px; color: #fbbf24; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

        .btn-export { background: #10b981; color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 700; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px;}
        .btn-export:hover { background: #059669; }

        .filter-tabs { display: flex; gap: 8px; margin-bottom: 25px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; }
        .filter-tabs::-webkit-scrollbar { display: none; }
        .filter-btn { flex: 1; min-width: 120px; text-align: center; padding: 10px; background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; white-space: nowrap; backdrop-filter: blur(5px); }
        .filter-btn:hover { border-color: var(--primary-orange); color: var(--primary-orange); }
        .filter-btn.active { background: var(--primary-orange); color: var(--white); border-color: var(--primary-orange); box-shadow: 0 4px 10px rgba(255,107,0,0.3); }

        .totals-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 30px; }
        .total-card { background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px 10px; text-align: center; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); }
        .total-card h3 { margin: 0 0 5px 0; font-size: 18px; color: var(--white); font-weight: 700; }
        .total-card p { margin: 0; font-size: 11px; color: rgba(255,255,255,0.6); text-transform: uppercase; font-weight: 600; }
        
        .leaderboard-title { font-size: 16px; font-weight: 600; color: var(--white); margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        
        .rank-card { background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 15px; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); transition: 0.2s; }
        .rank-card:hover { border-color: var(--primary-orange); background: rgba(255,255,255,0.08); }
        
        .rank-number { font-size: 18px; font-weight: 700; color: rgba(255,255,255,0.5); width: 30px; text-align: center; }
        .medal-1 { color: #fbbf24; font-size: 24px; filter: drop-shadow(0 2px 4px rgba(251, 191, 36, 0.4)); } 
        .medal-2 { color: #9ca3af; font-size: 24px; } 
        .medal-3 { color: #b45309; font-size: 24px; } 

        .member-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; background: var(--dark-blue); border: 2px solid var(--primary-orange); }
        
        .member-info { flex-grow: 1; }
        .member-info h4 { margin: 0 0 3px 0; font-size: 15px; color: var(--white); font-weight: 600; }
        .member-info p { margin: 0; font-size: 12px; color: rgba(255,255,255,0.6); }

        .member-stats { text-align: right; }
        .stat-revenue { font-size: 16px; font-weight: 700; color: #34d399; margin: 0 0 5px 0; }
        .stat-counts { font-size: 11px; color: var(--white); display: flex; gap: 10px; justify-content: flex-end; }
        .stat-counts span { background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); font-weight: 600; }
    </style>
</head>
<body>

<div class="portal-container">
    
    <a href="head_table.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Portal</a>

    <div class="header-box">
        <div class="header-text">
            <h1><i class="fa-solid fa-trophy" style="color:#fbbf24; margin-right:8px;"></i> <?php echo htmlspecialchars($group_name); ?></h1>
            <p><?php echo $board_title; ?></p>
        </div>
    </div>

    <div class="filter-tabs">
        <a href="chapter_performance.php?filter=month" class="filter-btn <?php echo ($filter == 'month') ? 'active' : ''; ?>">This Month</a>
        <a href="chapter_performance.php?filter=6months" class="filter-btn <?php echo ($filter == '6months') ? 'active' : ''; ?>">Past 6 Months</a>
        <a href="chapter_performance.php?filter=year" class="filter-btn <?php echo ($filter == 'year') ? 'active' : ''; ?>">Past 1 Year</a>
        <a href="chapter_performance.php?filter=all" class="filter-btn <?php echo ($filter == 'all') ? 'active' : ''; ?>">All Time</a>
    </div>

    <div style="display:flex; justify-content:flex-end; margin:-10px 0 25px;">
        <a href="actions/export_leaderboard.php?filter=<?php echo urlencode($filter); ?>" class="btn-export"><i class="fa-solid fa-download"></i> Export Leaderboard</a>
    </div>

    <div class="totals-grid">
        <div class="total-card">
            <h3 style="color: #34d399;">₹<?php echo number_format($chapter_revenue); ?></h3>
            <p>Deals Won</p>
        </div>
        <div class="total-card">
            <h3 style="color: var(--primary-orange);"><?php echo number_format($chapter_links); ?></h3>
            <p>Links Passed</p>
        </div>
        <div class="total-card">
            <h3 style="color: #60a5fa;"><?php echo number_format($chapter_visitors); ?></h3>
            <p>Visitors</p>
        </div>
    </div>

    <div class="leaderboard-title">
        <span><i class="fa-solid fa-ranking-star" style="color: var(--primary-orange);"></i> Member Rankings</span>
        <span style="font-size: 12px; color: rgba(255,255,255,0.6); font-weight: 400;">Ranked by Revenue</span>
    </div>

    <?php 
    $rank = 1;
    foreach ($leaderboard as $member): 
        $rank_display = $rank;
        if ($rank == 1 && $member['total_revenue'] > 0) $rank_display = '<i class="fa-solid fa-medal medal-1"></i>';
        elseif ($rank == 2 && $member['total_revenue'] > 0) $rank_display = '<i class="fa-solid fa-medal medal-2"></i>';
        elseif ($rank == 3 && $member['total_revenue'] > 0) $rank_display = '<i class="fa-solid fa-medal medal-3"></i>';
    ?>
        <div class="rank-card">
            <div class="rank-number"><?php echo $rank_display; ?></div>
            
            <img src="assets/uploads/profiles/<?php echo $member['profile_photo'] ? htmlspecialchars($member['profile_photo']) : 'default.png'; ?>" 
                 alt="Profile" class="member-avatar" 
                 onerror="this.src='https://via.placeholder.com/150/00204a/ff6b00?text=<?php echo substr($member['first_name'], 0, 1); ?>'">
            
            <div class="member-info">
                <h4><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h4>
                <p><?php echo htmlspecialchars($member['company_name']); ?></p>
            </div>

            <div class="member-stats">
                <p class="stat-revenue">₹<?php echo number_format($member['total_revenue']); ?></p>
                <div class="stat-counts">
                    <span title="Referrals"><i class="fa-solid fa-link" style="color:var(--primary-orange);"></i> <?php echo $member['total_links']; ?></span>
                    <span title="1-to-1 meetings"><i class="fa-solid fa-handshake" style="color:#a78bfa;"></i> <?php echo $member['total_121']; ?></span>
                    <span><i class="fa-solid fa-user-plus" style="color:#60a5fa;"></i> <?php echo $member['total_visitors']; ?></span>
                </div>
            </div>
        </div>
    <?php 
        $rank++;
    endforeach; 
    ?>

</div>

</body>
</html>