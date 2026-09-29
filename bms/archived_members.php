<?php
// archived_members.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

try {
    $archive_scope = '';
    $archive_params = [];
    if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
        $archive_scope = ' AND g.franchise_owner_id = ?';
        $archive_params[] = $_SESSION['user_id'];
    }

    $stmt = $pdo->prepare(" 
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, 
               b.company_name, b.business_category_applied,
               g.group_name, gm.joining_date
        FROM users u
        LEFT JOIN group_members gm ON u.id = gm.user_id
        LEFT JOIN groups g ON gm.group_id = g.id
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE (u.status = 'Locked' OR gm.membership_status = 'Dropped') $archive_scope
        ORDER BY g.group_name ASC, u.first_name ASC
    ");
    $stmt->execute($archive_params);
    $archived_members = $stmt->fetchAll();
    
} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archived Members | WE KONNECTS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --dark-blue: #00204a; --bg-light: #f8fafc; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .header-box { background: white; border: 1px solid var(--border-color); padding: 25px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 11px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); letter-spacing: 0.5px;}
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: #334155;}
        tr:hover { background: #f8fafc; }
        
        .action-btn { background: white; color: #475569; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 11px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .action-btn:hover { background: #f1f5f9; border-color: #94a3b8;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="admin_directory.php" style="color: #64748b; text-decoration: none; margin-bottom: 15px; display: inline-block; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Active Directory</a>

    <div class="header-box">
        <div>
            <h1 style="margin:0 0 5px 0; font-size: 24px; color: #0f172a;"><i class="fa-solid fa-box-archive" style="color:#64748b;"></i> Inactive Member Archive</h1>
            <p style="margin:0; font-size: 14px; color: #64748b;">Historical record of members who have left the ecosystem or been suspended.</p>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Archived Member</th>
                    <th>Business & Category</th>
                    <th>Previous Chapter</th>
                    <th>Joined Date</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($archived_members)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">No archived members found.</td></tr>
                <?php else: ?>
                    <?php foreach ($archived_members as $m): ?>
                        <tr>
                            <td>
                                <strong style="font-size:15px; color:var(--dark-blue);"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></strong><br>
                                <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($m['phone']); ?> | <?php echo htmlspecialchars($m['email']); ?></span>
                            </td>
                            <td>
                                <strong style="color:var(--dark-blue);"><?php echo htmlspecialchars($m['company_name'] ?? 'N/A'); ?></strong><br>
                                <span style="font-size: 11px; color: #64748b;"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($m['business_category_applied']); ?></span>
                            </td>
                            <td>
                                <strong style="color: #475569;"><?php echo htmlspecialchars($m['group_name'] ?? 'Unassigned'); ?></strong>
                            </td>
                            <td>
                                <span style="color: #64748b;"><i class="fa-solid fa-calendar"></i> <?php echo !empty($m['joining_date']) ? date('M d, Y', strtotime($m['joining_date'])) : 'Unknown'; ?></span>
                            </td>
                            <td style="text-align: right;">
                                <!-- Reactivation links them straight to the Edit screen so an Admin can change their status and dates back to Active -->
                                <a href="edit_member.php?id=<?php echo $m['id']; ?>&amp;return_to=archived_members.php" class="action-btn" style="color: #059669; border-color: #a7f3d0; background: #ecfdf5;"><i class="fa-solid fa-bolt"></i> Reactivate Profile</a>
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