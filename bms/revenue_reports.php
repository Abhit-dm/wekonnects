<?php
// revenue_reports.php
session_start();
require_once 'config/database.php';

// Security: Admins only
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

// Fixed the deprecated PHP filter issue
$filter_type = filter_input(INPUT_GET, 'filter_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'all_time';
$filter_chapter = filter_input(INPUT_GET, 'chapter_id', FILTER_SANITIZE_NUMBER_INT) ?? '';

try {
    $chapters = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();

    // Build the WHERE clause dynamically based on filters
    $where_sql = "WHERE s.slip_type = 'TYFCB'";
    $params = [];

    if (!empty($filter_chapter)) {
        $where_sql .= " AND gm.group_id = ?";
        $params[] = $filter_chapter;
    }

    // FIX: Changed 'created_at' to 'date_logged' to match your actual database columns
    if ($filter_type == '15days') {
        $where_sql .= " AND COALESCE(s.deal_close_date, s.date_logged) >= DATE_SUB(NOW(), INTERVAL 15 DAY)";
    } elseif ($filter_type == '30days') {
        $where_sql .= " AND COALESCE(s.deal_close_date, s.date_logged) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($filter_type == 'this_year') {
        $where_sql .= " AND YEAR(COALESCE(s.deal_close_date, s.date_logged)) = YEAR(CURRENT_DATE)";
    }

    // 1. Get Total Revenue KPI
    $stmtTotal = $pdo->prepare("
        SELECT SUM(s.amount) as grand_total 
        FROM slips s 
        JOIN group_members gm ON s.initiator_member_id = gm.user_id 
        $where_sql
    ");
    $stmtTotal->execute($params);
    $grand_total = $stmtTotal->fetchColumn() ?: 0;

    // 2. Get Top Revenue Generators (Table Data)
    $stmtList = $pdo->prepare("
        SELECT u.first_name, u.last_name, b.company_name, g.group_name, SUM(s.amount) as total_generated
        FROM slips s
        JOIN users u ON s.initiator_member_id = u.id
        LEFT JOIN businesses b ON u.id = b.user_id
        JOIN group_members gm ON u.id = gm.user_id
        JOIN groups g ON gm.group_id = g.id
        $where_sql
        GROUP BY u.id
        ORDER BY total_generated DESC
    ");
    $stmtList->execute($params);
    $top_earners = $stmtList->fetchAll();

} catch (PDOException $e) { 
    // FIX: Print the EXACT error message so we know what went wrong if it fails again
    die("Database Error: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Revenue Reports | WE KONNECTS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f4f7f6; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        .sidebar { width: 260px; background-color: var(--dark-blue); color: #fff; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; top:0; left:0;}
        .sidebar-brand { padding: 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 18px;}
        .nav-item { padding: 15px 24px; display: flex; align-items: center; gap: 15px; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background-color: rgba(255, 107, 0, 0.1); color: #ff6b00; border-right: 4px solid #ff6b00; }
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 30px 40px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 25px rgba(0,32,74,0.1);}
        
        .filter-bar { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; gap: 15px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); align-items: center;}
        .form-select { padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; background: #f8fafc; min-width: 200px;}
        .btn-filter { background: var(--primary-orange); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;}

        .kpi-card { background: white; padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 30px; text-align: center;}
        .kpi-card h4 { margin: 0 0 10px 0; font-size: 14px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;}
        .kpi-card h1 { margin: 0; font-size: 48px; font-weight: 800; color: #059669;}

        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); }
        tr:hover { background: #f8fafc; }
        
        .money-badge { background: #ecfdf5; color: #059669; padding: 6px 12px; border-radius: 8px; font-weight: 700; border: 1px solid #a7f3d0;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="header-box">
        <div>
            <h1 style="margin:0; font-size: 26px;">Revenue & TYFCB Reports</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: rgba(255,255,255,0.8);">Track "Thank You For Closed Business" across the franchise.</p>
        </div>
        <i class="fa-solid fa-file-invoice-dollar" style="font-size: 45px; color: rgba(255,255,255,0.2);"></i>
    </div>

    <form class="filter-bar" method="GET" action="revenue_reports.php">
        <select name="chapter_id" class="form-select">
            <option value="">All Chapters (Franchise)</option>
            <?php foreach ($chapters as $ch): ?>
                <option value="<?php echo $ch['id']; ?>" <?php if($filter_chapter == $ch['id']) echo 'selected'; ?>><?php echo htmlspecialchars($ch['group_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="filter_type" class="form-select">
            <option value="15days" <?php if($filter_type == '15days') echo 'selected'; ?>>Last 15 Days</option>
            <option value="30days" <?php if($filter_type == '30days') echo 'selected'; ?>>Last 30 Days</option>
            <option value="this_year" <?php if($filter_type == 'this_year') echo 'selected'; ?>>Year to Date</option>
            <option value="all_time" <?php if($filter_type == 'all_time') echo 'selected'; ?>>All Time</option>
        </select>

        <button type="submit" class="btn-filter">Apply Filter</button>
    </form>

    <div class="kpi-card">
        <h4>Total Revenue Generated (Selected Period)</h4>
        <h1>₹<?php echo number_format($grand_total); ?></h1>
    </div>

    <h3 style="color: var(--dark-blue); margin-bottom: 15px;"><i class="fa-solid fa-trophy" style="color: #fbbf24;"></i> Top Revenue Generators</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Member Name</th>
                    <th>Business Details</th>
                    <th>Chapter</th>
                    <th style="text-align: right;">Total Generated</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_earners)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: #64748b;">No revenue data found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($top_earners as $earner): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($earner['first_name'] . ' ' . $earner['last_name']); ?></strong></td>
                            <td><span style="color: #64748b; font-size: 13px;"><?php echo htmlspecialchars($earner['company_name'] ?? 'N/A'); ?></span></td>
                            <td><span style="background:#f1f5f9; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:700; color:#475569; border:1px solid #cbd5e1;"><?php echo htmlspecialchars($earner['group_name']); ?></span></td>
                            <td style="text-align: right;"><span class="money-badge">₹<?php echo number_format($earner['total_generated']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>