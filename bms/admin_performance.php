<?php
// admin_performance.php
session_start();
require_once 'config/database.php';

// Security check
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

// Function to format money cleanly
function format_money($amount) {
    if (!$amount) return "0";
    if ($amount >= 10000000) return number_format($amount / 10000000, 2) . ' Cr';
    if ($amount >= 100000) return number_format($amount / 100000, 2) . ' L';
    if ($amount >= 1000) return number_format($amount / 1000, 1) . ' k';
    return number_format($amount);
}

try {
    // Fetch all active chapters for the filter tabs
    if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
        $stmtChapters = $pdo->prepare("SELECT id, group_name FROM groups WHERE status = 'Active' AND franchise_owner_id = ? ORDER BY group_name ASC");
        $stmtChapters->execute([$_SESSION['user_id']]);
        $chapters = $stmtChapters->fetchAll();
    } else {
        $chapters = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();
    }
    
    // Get the selected chapter ID (Default to 'all' if not set)
    $active_chapter_id = isset($_GET['chapter_id']) ? $_GET['chapter_id'] : 'all';

    // Build the SQL query dynamically based on the filter
    $sql = "
        SELECT 
            u.id, u.first_name, u.last_name, g.group_name,
            -- 121s (Participated in)
            (SELECT COUNT(*) FROM slips WHERE slip_type = '121' AND (initiator_member_id = u.id OR receiver_member_id = u.id)) as total_121,
            
            -- Links Given
            (SELECT COUNT(*) FROM slips WHERE slip_type = 'REFERRAL' AND initiator_member_id = u.id) as links_given,
            
            -- Deals Done / Closed (member who logged the closed deal)
            (SELECT COUNT(*) FROM slips WHERE slip_type = 'TYFCB' AND initiator_member_id = u.id) as deals_done_count,
            (SELECT SUM(amount) FROM slips WHERE slip_type = 'TYFCB' AND initiator_member_id = u.id) as deals_done_value,
            
            -- Visitors
            (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND chapter_id = g.id) as visitors_invited,
            (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND chapter_id = g.id AND status = 'Joined' AND attended = 1) as visitors_converted
        FROM users u
        JOIN group_members gm ON u.id = gm.user_id
        JOIN groups g ON gm.group_id = g.id
        WHERE u.status = 'Active' AND gm.membership_status = 'Active'
    ";

    $params = [];
    if ($active_chapter_id !== 'all') {
        $sql .= " AND gm.group_id = ?";
        $params[] = $active_chapter_id;
    }
    if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
        $sql .= " AND g.franchise_owner_id = ?";
        $params[] = $_SESSION['user_id'];
    }

    $sql .= " ORDER BY g.group_name ASC, u.first_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $performance_data = $stmt->fetchAll();

} catch (PDOException $e) { 
    die("Database Error: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Performance | WE KONNECTS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f4f7f6; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,32,74,0.15);}
        
        .chapter-tabs { display: flex; gap: 10px; margin-bottom: 25px; overflow-x: auto; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);}
        .chap-tab { padding: 10px 20px; background: white; border: 1px solid var(--border-color); border-radius: 8px; color: #64748b; font-weight: 600; text-decoration: none; white-space: nowrap; transition: 0.2s;}
        .chap-tab:hover { border-color: var(--primary-orange); color: var(--primary-orange);}
        .chap-tab.active { background: var(--dark-blue); color: white; border-color: var(--dark-blue);}

        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 11px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); letter-spacing: 0.5px;}
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); vertical-align: middle; font-weight: 500;}
        tr:hover { background: #f8fafc; }
        
        .stat-badge { padding: 4px 8px; border-radius: 6px; font-size: 13px; font-weight: 700; background: #f1f5f9; color: #334155;}
        .stat-value { font-size: 14px; font-weight: 800; color: var(--primary-orange); }
        .stat-green { color: #10b981; }
        .stat-blue { color: #3b82f6; }
        .stat-purple { color: #8b5cf6; }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">

    <div class="header-box">
        <div>
            <h1 style="margin:0; font-size: 24px;">Member Overall Performance</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.8;">Track lifetime links, deals, 1-to-1s, and visitor conversions by chapter.</p>
        </div>
        <i class="fa-solid fa-chart-pie" style="font-size: 35px; color: var(--primary-orange);"></i>
    </div>

    <div class="chapter-tabs">
        <a href="?chapter_id=all" class="chap-tab <?php echo ($active_chapter_id === 'all') ? 'active' : ''; ?>">All Chapters</a>
        <?php foreach ($chapters as $chap): ?>
            <a href="?chapter_id=<?php echo $chap['id']; ?>" class="chap-tab <?php echo ($active_chapter_id == $chap['id']) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($chap['group_name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Member Details</th>
                    <th style="text-align: center;">1-to-1s</th>
                    <th style="text-align: center;">Links Given</th>
                    <th style="text-align: center;">Deals Closed (Qty)</th>
                    <th style="text-align: right;">Deal Value (₹)</th>
                    <th style="text-align: center;">Visitors Invited</th>
                    <th style="text-align: center;">Converted to Members</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($performance_data)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">No active members found for this selection.</td></tr>
                <?php else: ?>
                    <?php foreach ($performance_data as $row): ?>
                        <tr>
                            <td>
                                <strong style="font-size:15px; color: var(--dark-blue);"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong><br>
                                <span style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($row['group_name']); ?></span>
                            </td>
                            
                            <td style="text-align: center;">
                                <span class="stat-badge stat-blue"><?php echo $row['total_121'] ?? 0; ?></span>
                            </td>
                            
                            <td style="text-align: center;">
                                <span class="stat-badge stat-value"><?php echo $row['links_given'] ?? 0; ?></span>
                            </td>

                            <td style="text-align: center;">
                                <span class="stat-badge stat-green"><?php echo $row['deals_done_count'] ?? 0; ?></span>
                            </td>

                            <td style="text-align: right;">
                                <span style="font-weight: 800; color: #047857; font-size: 14px;">₹ <?php echo format_money($row['deals_done_value']); ?></span>
                            </td>

                            <td style="text-align: center;">
                                <span class="stat-badge stat-purple"><?php echo $row['visitors_invited'] ?? 0; ?></span>
                            </td>

                            <td style="text-align: center;">
                                <?php $conv = $row['visitors_converted'] ?? 0; ?>
                                <span class="stat-badge" style="<?php echo ($conv > 0) ? 'background:#ecfdf5; color:#059669;' : ''; ?>">
                                    <?php echo $conv; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>
</body>
</html>