<?php
// sa_pending_renewals.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

header("Location: admin_renewals.php");
exit;

try {
    // Fetch users who are marked as Pending (awaiting renewal/activation approval)
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status,
               g.group_name, f.first_name as franchise_first, f.last_name as franchise_last
        FROM users u
        LEFT JOIN group_members gm ON u.id = gm.user_id
        LEFT JOIN groups g ON gm.group_id = g.id
        LEFT JOIN users f ON g.franchise_owner_id = f.id
        WHERE u.status = 'Pending_Setup'
        ORDER BY u.id ASC
    ");
    $pending_users = $stmt->fetchAll();
} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Applications | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; min-height: 100vh;}
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden;}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f1f5f9; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color);}
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); color: #334155;}
        .btn-approve { background: #10b981; color: white; padding: 8px 15px; border-radius: 6px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;}
        .btn-approve:hover { opacity: 0.9; }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="super_admin.php" style="color: #64748b; text-decoration: none; display: inline-block; margin-bottom: 15px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back</a>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #059669; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #10b981;"><i class="fa-solid fa-check"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>

    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Pending Applications</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Review, approve, or delete pending member applications.</p>
        </div>
        <i class="fa-solid fa-user-clock" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Member Details</th>
                    <th>Requested Chapter</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pending_users)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: #64748b;">No pending applications at this time.</td></tr>
                <?php else: ?>
                    <?php foreach($pending_users as $u): ?>
                        <tr>
                            <td>
                                <strong style="color: #0f172a; display:block;"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong>
                                <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($u['email']); ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($u['phone']); ?></span>
                            </td>
                            <td><strong style="color: var(--sa-dark);"><?php echo htmlspecialchars($u['group_name'] ?? 'Unassigned'); ?></strong></td>
                            <td><span style="background: #fffbeb; color: #d97706; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid #fcd34d;">Pending Review</span></td>
                            <td style="text-align: right; display: flex; justify-content: flex-end; gap: 8px;">
                                <a href="edit_member.php?id=<?php echo $u['id']; ?>&amp;return_to=sa_pending_renewals.php" class="btn-approve" style="background:#3b82f6;"><i class="fa-solid fa-pen"></i> Review</a>
                                <form action="actions/delete_pending_user.php" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this application?');">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn-approve" style="background:#ef4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                                </form>
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