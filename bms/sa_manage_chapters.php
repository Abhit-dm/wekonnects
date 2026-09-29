<?php
// sa_manage_chapters.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    $stmt = $pdo->query("
        SELECT g.id, g.group_name, g.status, g.franchise_owner_id,
               u.first_name as owner_first, u.last_name as owner_last,
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND membership_status = 'Active') as member_count
        FROM groups g
        LEFT JOIN users u ON g.franchise_owner_id = u.id
        ORDER BY g.group_name ASC
    ");
    $chapters = $stmt->fetchAll();

    $stmtOwners = $pdo->query("SELECT id, first_name, last_name FROM users WHERE system_role = 'FRANCHISE_OWNER' AND status = 'Active' ORDER BY first_name ASC");
    $franchisees = $stmtOwners->fetchAll();

} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global Chapter Control | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
        
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f1f5f9; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color);}
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: #334155;}
        
        .btn-create { background: var(--sa-gold); color: var(--sa-dark); border: none; padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;}
        .btn-create:hover { background: #f59e0b; }
        
        .badge-status { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid;}
        .status-Active { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
        .status-Inactive { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

        .btn-action { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid #cbd5e1; color: #475569; transition: 0.2s;}
        .btn-action:hover { background: #f1f5f9; border-color: #94a3b8;}

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px;}
        .modal-box { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 400px; position: relative;}
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #94a3b8; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #0f172a; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="super_admin.php" style="color: #64748b; text-decoration: none; display: inline-block; margin-bottom: 15px; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Command Center</a>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #059669; border: 1px solid #10b981; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>

    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Global Chapter Control</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Create new chapters and monitor growth across all franchises.</p>
        </div>
        <button onclick="openModal('modalNewChapter')" class="btn-create"><i class="fa-solid fa-plus"></i> Launch New Chapter</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Chapter Name</th>
                    <th>Franchise Owner</th>
                    <th>Total Members</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($chapters)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">No chapters exist in the ecosystem yet.</td></tr>
                <?php else: ?>
                    <?php foreach($chapters as $c): ?>
                        <tr>
                            <td>
                                <strong style="color: #0f172a; font-size: 16px;"><i class="fa-solid fa-sitemap" style="color:var(--sa-gold);"></i> <?php echo htmlspecialchars($c['group_name']); ?></strong>
                            </td>
                            <td>
                                <?php if($c['franchise_owner_id']): ?>
                                    <span style="font-weight: 600; color: #3b82f6;"><i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($c['owner_first'] . ' ' . $c['owner_last']); ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Unassigned (Headquarters)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-size: 16px; color: var(--sa-dark);"><?php echo $c['member_count']; ?></strong> <span style="font-size: 12px; color: #64748b;">Active</span>
                            </td>
                            <td>
                                <span class="badge-status status-<?php echo $c['status']; ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                            </td>
                            <td style="text-align: right;">
                                <a href="sa_edit_chapter.php?id=<?php echo $c['id']; ?>" class="btn-action"><i class="fa-solid fa-pen"></i> Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="modalNewChapter">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalNewChapter')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px;"><i class="fa-solid fa-sitemap" style="color:var(--sa-gold);"></i> Launch New Chapter</h3>
        
        <form action="actions/sa_create_chapter.php" method="POST">
            <div class="form-group">
                <label>Chapter Name</label>
                <input type="text" name="group_name" class="form-input" placeholder="e.g., WE KONNECTS Elite" required>
            </div>
            
            <div class="form-group">
                <label>Assign to Franchise Owner (Optional)</label>
                <select name="franchise_owner_id" class="form-input">
                    <option value="">-- Keep at Headquarters --</option>
                    <?php foreach($franchisees as $f): ?>
                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" style="background:var(--sa-dark); color:var(--sa-gold); border:none; padding:12px; width:100%; border-radius:8px; font-weight:700; cursor:pointer; margin-top: 10px;">Create Chapter</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>
</body>
</html>