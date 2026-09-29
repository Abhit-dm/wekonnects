<?php
// sa_helpdesk.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    $stmt = $pdo->query("
        SELECT t.*, u.first_name, u.last_name, u.email, u.system_role 
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        ORDER BY FIELD(t.status, 'Open', 'In Progress', 'Resolved'), t.created_at DESC
    ");
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) { die("Database Error."); }

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $new_status = $_POST['status'];
    try {
        $update = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        $update->execute([$new_status, $ticket_id]);
        $_SESSION['success_msg'] = "Ticket status updated to " . $new_status;
        header("Location: sa_helpdesk.php"); exit;
    } catch (PDOException $e) { $error = "Update failed."; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Support Helpdesk | WE KONNECTS SA</title>
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
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); vertical-align: top;}
        
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid; display: inline-block;}
        .status-Open { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .status-InProgress { background: #fffbeb; color: #d97706; border-color: #fcd34d; }
        .status-Resolved { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
        
        .status-select { padding: 6px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 600;}
        .btn-update { background: var(--sa-dark); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 600;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="super_admin.php" style="color: #64748b; text-decoration: none; display: inline-block; margin-bottom: 15px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back</a>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #059669; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #10b981;"><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>

    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Support Helpdesk</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Review and resolve network support tickets.</p>
        </div>
        <i class="fa-solid fa-headset" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th style="width: 40%;">Ticket Details</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($tickets)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px;">No support tickets exist.</td></tr>
                <?php else: ?>
                    <?php foreach($tickets as $t): 
                        $status_class = str_replace(' ', '', $t['status']); // Removes space for 'In Progress' CSS class
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></strong><br>
                                <span style="font-size: 11px; color: #64748b; text-transform: uppercase;"><?php echo htmlspecialchars($t['system_role']); ?></span>
                            </td>
                            <td>
                                <strong style="color: var(--sa-dark);"><?php echo htmlspecialchars($t['subject']); ?></strong><br>
                                <p style="font-size: 13px; color: #475569; margin: 5px 0 0 0; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($t['message'])); ?></p>
                            </td>
                            <td style="font-size: 12px; color: #64748b;">
                                <?php echo date('M d, Y', strtotime($t['created_at'])); ?>
                            </td>
                            <td><span class="badge status-<?php echo $status_class; ?>"><?php echo $t['status']; ?></span></td>
                            <td>
                                <form method="POST" style="display: flex; gap: 5px;">
                                    <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                    <select name="status" class="status-select">
                                        <option value="Open" <?php if($t['status'] == 'Open') echo 'selected'; ?>>Open</option>
                                        <option value="In Progress" <?php if($t['status'] == 'In Progress') echo 'selected'; ?>>In Progress</option>
                                        <option value="Resolved" <?php if($t['status'] == 'Resolved') echo 'selected'; ?>>Resolved</option>
                                    </select>
                                    <button type="submit" class="btn-update">Save</button>
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