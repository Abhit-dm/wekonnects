<?php
// admin_renewals.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

try {
    $renewal_scope = '';
    $renewal_params = [];
    if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
        $renewal_scope = ' AND g.franchise_owner_id = ?';
        $renewal_params[] = $_SESSION['user_id'];
    }

    // Fetch Pending Renewals
    $stmtPending = $pdo->prepare(" 
        SELECT pr.*, u.first_name, u.last_name, g.group_name, 
               s.first_name as sub_fname, s.last_name as sub_lname 
        FROM pending_renewals pr 
        JOIN users u ON pr.user_id = u.id 
        JOIN groups g ON pr.group_id = g.id 
        JOIN users s ON pr.submitted_by = s.id 
        WHERE pr.status = 'Pending' $renewal_scope
        ORDER BY pr.submitted_at ASC
    ");
    $stmtPending->execute($renewal_params);
    $pending = $stmtPending->fetchAll();

    // Fetch History (Approved/Rejected)
    $stmtHistory = $pdo->prepare(" 
        SELECT pr.*, u.first_name, u.last_name, g.group_name 
        FROM pending_renewals pr 
        JOIN users u ON pr.user_id = u.id 
        JOIN groups g ON pr.group_id = g.id 
        WHERE pr.status != 'Pending' $renewal_scope
        ORDER BY pr.submitted_at DESC LIMIT 50
    ");
    $stmtHistory->execute($renewal_params);
    $history = $stmtHistory->fetchAll();

} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Renewal Approvals | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f4f7f6; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 30px;}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); vertical-align: middle;}
        
        .btn { padding: 8px 15px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; border: none; }
        .btn-approve { background: #10b981; color: white; }
        .btn-reject { background: #ef4444; color: white; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>

    <div class="header-box">
        <div>
            <h1 style="margin:0; font-size: 24px;">Renewal Approvals</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.8;">Approve payments to instantly add 1 Year to memberships.</p>
        </div>
        <i class="fa-solid fa-file-invoice-dollar" style="font-size: 35px; color: var(--primary-orange);"></i>
    </div>

    <h3>Pending Approvals</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Member & Chapter</th>
                    <th>Payment Info</th>
                    <th>Submitted By</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pending)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 30px; color: #64748b;">No pending renewals.</td></tr>
                <?php else: ?>
                    <?php foreach ($pending as $p): ?>
                        <tr>
                            <td><strong style="font-size:15px;"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></strong><br><span style="color:#64748b;"><?php echo htmlspecialchars($p['group_name']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($p['payment_mode']); ?></strong><br><span style="color:#64748b;">Ref: <?php echo htmlspecialchars($p['reference_no'] ?: 'N/A'); ?></span></td>
                            <td><?php echo htmlspecialchars($p['sub_fname'] . ' ' . $p['sub_lname']); ?><br><span style="font-size:11px; color:#94a3b8;"><?php echo date('M d, Y', strtotime($p['submitted_at'])); ?></span></td>
                            <td style="text-align: right; display:flex; gap:10px; justify-content:flex-end;">
                                <form action="actions/process_renewal.php" method="POST" onsubmit="return confirm('Approve this renewal? It will add exactly 1 year to their membership.');">
                                    <input type="hidden" name="renewal_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve"><i class="fa-solid fa-check"></i> Approve</button>
                                </form>
                                <form action="actions/process_renewal.php" method="POST" onsubmit="return confirm('Reject this renewal?');">
                                    <input type="hidden" name="renewal_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject"><i class="fa-solid fa-xmark"></i> Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3>Recent History</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Chapter</th>
                    <th>Status</th>
                    <th>Date Processed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($h['first_name'] . ' ' . $h['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($h['group_name']); ?></td>
                        <td><span class="badge" style="background: <?php echo $h['status']=='Approved' ? '#dcfce7; color:#166534;' : '#fee2e2; color:#991b1b;'; ?>"><?php echo $h['status']; ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($h['submitted_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>