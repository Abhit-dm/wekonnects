<?php
// sa_broadcast.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM system_broadcasts ORDER BY created_at DESC LIMIT 50");
    $broadcasts = $stmt->fetchAll();
} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global Broadcasts | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; min-height: 100vh;}
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        
        .layout-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;}
        .card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #0f172a; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
        textarea.form-input { resize: vertical; min-height: 120px; }
        
        .btn-send { background: var(--sa-gold); color: var(--sa-dark); border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: 800; font-size: 15px; cursor: pointer; transition: 0.2s;}
        .btn-send:hover { background: #f59e0b; }

        .broadcast-item { padding: 20px; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 15px; background: #f8fafc; position: relative; }
        .b-title { font-weight: 700; color: var(--sa-dark); margin: 0 0 5px 0; font-size: 16px;}
        .b-meta { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; display: flex; gap: 10px; align-items: center;}
        .b-msg { font-size: 14px; color: #334155; line-height: 1.5; margin: 0;}
        
        .btn-delete { position: absolute; top: 15px; right: 15px; background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: 0.2s; }
        .btn-delete:hover { background: #ef4444; color: white; }
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
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Global Announcements</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Push system-wide alerts to member dashboards.</p>
        </div>
        <i class="fa-solid fa-tower-broadcast" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <div class="layout-grid">
        <div class="card">
            <h3 style="margin-top:0; color:var(--sa-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">Compose Message</h3>
            <form action="actions/sa_send_broadcast.php" method="POST">
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_audience" class="form-input">
                        <option value="ALL">All Active Members</option>
                        <option value="FRANCHISE_OWNERS">Franchise Owners Only</option>
                        <option value="COORDINATORS">Chapter Coordinators Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Announcement Title</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g., National Conference 2024!" required>
                </div>
                <div class="form-group">
                    <label>Message Content</label>
                    <textarea name="message" class="form-input" placeholder="Type your announcement here..." required></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Start Date (Optional)</label>
                        <input type="date" name="start_date" class="form-input">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>End Date (Optional)</label>
                        <input type="date" name="end_date" class="form-input">
                    </div>
                </div>
                <button type="submit" class="btn-send"><i class="fa-solid fa-paper-plane"></i> Publish Announcement</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0; color:var(--sa-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">Broadcast History</h3>
            <?php if(empty($broadcasts)): ?>
                <p style="color: #64748b; font-size: 14px;">No broadcasts sent yet.</p>
            <?php else: ?>
                <?php foreach($broadcasts as $b): ?>
                    <div class="broadcast-item">
                        <form action="actions/sa_delete_broadcast.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this announcement?');">
                            <input type="hidden" name="broadcast_id" value="<?php echo $b['id']; ?>">
                            <button type="submit" class="btn-delete" title="Remove Announcement"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        
                        <h4 class="b-title"><?php echo htmlspecialchars($b['title']); ?></h4>
                        <div class="b-meta">
                            <span style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a;"><i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($b['target_audience']); ?></span>
                            <?php if (!empty($b['start_date'])): ?>
                                <span style="color: #10b981;"><i class="fa-regular fa-calendar"></i> Starts: <?php echo date('M d, Y', strtotime($b['start_date'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($b['end_date'])): ?>
                                <span style="color: #ef4444;"><i class="fa-solid fa-hourglass-end"></i> Ends: <?php echo date('M d, Y', strtotime($b['end_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="b-msg"><?php echo nl2br(htmlspecialchars($b['message'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>