<?php
// emp_visitor_dashboard.php
session_start();
require_once 'config/database.php';

// STRICT SECURITY: Only Visitor Managers and Super Admins can access this
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['system_role'], ['EMP_VISITOR_MANAGER', 'SUPER_ADMIN'])) {
    header("Location: login.php"); 
    exit;
}

$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'all';

try {
    // Fetch all global visitors with inviter details and chapter
    $sql = "
        SELECT v.id, v.visitor_name, v.company_name, v.phone, v.email, v.visit_date, v.status, v.remarks,
               u.first_name as inviter_first, u.last_name as inviter_last,
               g.group_name
        FROM visitors v
        LEFT JOIN users u ON v.invited_by = u.id
        LEFT JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
        LEFT JOIN groups g ON gm.group_id = g.id
        WHERE 1=1
    ";
    
    $params = [];

    if ($status_filter !== 'all') {
        $sql .= " AND v.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($search)) {
        $sql .= " AND (v.visitor_name LIKE ? OR v.company_name LIKE ? OR v.phone LIKE ? OR u.first_name LIKE ?)";
        $search_term = "%{$search}%";
        array_push($params, $search_term, $search_term, $search_term, $search_term);
    }

    $sql .= " ORDER BY v.visit_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $visitors = $stmt->fetchAll();

} catch (PDOException $e) { 
    die("Database Error: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Visitor Database | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --brand-blue: #00204a; --brand-orange: #ff6b00; --bg-light: #f8fafc; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); margin: 0; color: #0f172a; }
        
        .top-navbar { background: var(--brand-blue); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1);}
        .top-navbar h2 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: 1px; }
        .top-navbar h2 span { color: var(--brand-orange); }
        .btn-logout { background: rgba(239, 68, 68, 0.1); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s;}
        .btn-logout:hover { background: #ef4444; color: white; }

        .main-container { padding: 40px; max-width: 1400px; margin: 0 auto; }
        
        .header-box { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px;}
        .header-box h1 { margin: 0 0 5px 0; color: var(--brand-blue); font-size: 24px;}
        .header-box p { margin: 0; color: #64748b; font-size: 14px;}

        .filter-bar { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 25px; display: flex; gap: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        .filter-input { flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-family: 'Inter'; font-size: 14px;}
        .filter-input:focus { border-color: var(--brand-blue); }
        .btn-search { background: var(--brand-blue); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s;}
        .btn-search:hover { background: #003a8c; }

        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f1f5f9; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); white-space: nowrap;}
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); vertical-align: top;}
        
        .badge-status { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid;}
        .status-Joined { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
        .status-Pending { background: #f8fafc; color: #64748b; border-color: #cbd5e1; }
        .status-Declined { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        
        .contact-link { color: #3b82f6; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;}
        .contact-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="top-navbar">
    <h2>WE <span>KONNECTS</span> | Employee Portal</h2>
    <div>
        <span style="margin-right: 15px; font-size: 13px;"><i class="fa-solid fa-user-tie" style="color:var(--brand-orange);"></i> Global Visitor Manager</span>
        <a href="actions/logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<div class="main-container">
    <div class="header-box">
        <div>
            <h1>Global Visitor Database</h1>
            <p>Access and export contact details for visitors across all WE KONNECTS chapters.</p>
        </div>
        <button onclick="window.print()" class="btn-search" style="background: var(--brand-orange);"><i class="fa-solid fa-print"></i> Print / Save PDF</button>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" class="filter-input" placeholder="Search by visitor name, company, phone, or inviter..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="status" class="filter-input" style="max-width: 200px;">
            <option value="all">All Statuses</option>
            <option value="Pending" <?php if($status_filter == 'Pending') echo 'selected'; ?>>Pending</option>
            <option value="Joined" <?php if($status_filter == 'Joined') echo 'selected'; ?>>Joined</option>
            <option value="Declined" <?php if($status_filter == 'Declined') echo 'selected'; ?>>Declined</option>
        </select>
        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Filter Results</button>
        <a href="emp_visitor_dashboard.php" style="padding: 12px; color: #64748b; text-decoration: none; font-weight: 600;">Clear</a>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Visit Date</th>
                    <th>Visitor Details & Contact</th>
                    <th>Company</th>
                    <th>Invited By</th>
                    <th>Chapter</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($visitors)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">No visitors match your search.</td></tr>
                <?php else: ?>
                    <?php foreach($visitors as $v): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--brand-blue);"><?php echo date('M d, Y', strtotime($v['visit_date'])); ?></td>
                            <td>
                                <strong style="display:block; font-size: 14px; margin-bottom: 4px;"><?php echo htmlspecialchars($v['visitor_name']); ?></strong>
                                <a href="tel:<?php echo htmlspecialchars($v['phone']); ?>" class="contact-link"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($v['phone']); ?></a>
                                <?php if(!empty($v['email'])): ?>
                                    <br><a href="mailto:<?php echo htmlspecialchars($v['email']); ?>" class="contact-link" style="color: #64748b; margin-top: 3px;"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($v['email']); ?></a>
                                <?php endif; ?>
                            </td>
                            <td><i class="fa-solid fa-briefcase" style="color:#cbd5e1;"></i> <?php echo htmlspecialchars($v['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($v['inviter_first'] . ' ' . $v['inviter_last']); ?></td>
                            <td><strong style="color: var(--brand-blue);"><?php echo htmlspecialchars($v['group_name'] ?? 'Unknown'); ?></strong></td>
                            <td><span class="badge-status status-<?php echo htmlspecialchars($v['status']); ?>"><?php echo htmlspecialchars($v['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>