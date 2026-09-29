<?php
// sa_manage_franchises.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    // Fetch all Franchise Owners and count their chapters
    $stmtOwners = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status,
               (SELECT COUNT(*) FROM groups WHERE franchise_owner_id = u.id) as chapter_count
        FROM users u
        WHERE u.system_role = 'FRANCHISE_OWNER'
        ORDER BY u.first_name ASC
    ");
    $owners = $stmtOwners->fetchAll();

    // Fetch all active chapters to show in the dropdowns later
    $chapters = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();

} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Franchisees | WE KONNECTS SA</title>
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
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Franchise Operations</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Manage your regional Franchise Owners and their assigned chapters.</p>
        </div>
        <button onclick="openModal('modalNewFranchise')" class="btn-create"><i class="fa-solid fa-plus"></i> Add Franchise Owner</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Franchise Owner</th>
                    <th>Contact Info</th>
                    <th>Chapters Managed</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($owners)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">No Franchise Owners created yet.</td></tr>
                <?php else: ?>
                    <?php foreach($owners as $o): ?>
                        <tr>
                            <td>
                                <strong style="color: #0f172a; font-size: 16px;"><i class="fa-solid fa-building-user" style="color:var(--sa-gold);"></i> <?php echo htmlspecialchars($o['first_name'] . ' ' . $o['last_name']); ?></strong>
                            </td>
                            <td>
                                <span style="font-size: 12px; display: block; margin-bottom: 4px;"><i class="fa-solid fa-envelope" style="color:#64748b; width:15px;"></i> <?php echo htmlspecialchars($o['email']); ?></span>
                                <span style="font-size: 12px;"><i class="fa-solid fa-phone" style="color:#64748b; width:15px;"></i> <?php echo htmlspecialchars($o['phone']); ?></span>
                            </td>
                            <td>
                                <strong style="font-size: 18px; color: var(--sa-dark);"><?php echo $o['chapter_count']; ?></strong> Chapters
                            </td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; background: <?php echo $o['status']=='Active' ? '#ecfdf5' : '#fef2f2'; ?>; color: <?php echo $o['status']=='Active' ? '#059669' : '#dc2626'; ?>; border: 1px solid <?php echo $o['status']=='Active' ? '#a7f3d0' : '#fecaca'; ?>;"><?php echo htmlspecialchars($o['status']); ?></span>
                            </td>
                            <td style="text-align: right;">
                                <a href="edit_member.php?id=<?php echo $o['id']; ?>&amp;return_to=sa_manage_franchises.php" class="btn-action"><i class="fa-solid fa-pen"></i> Edit Profile</a>
                                <a href="sa_assign_chapters.php?owner_id=<?php echo $o['id']; ?>" class="btn-action" style="background: var(--sa-dark); color: white; border-color: var(--sa-dark);"><i class="fa-solid fa-sitemap"></i> Assign Chapters</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- CREATE NEW FRANCHISE OWNER MODAL -->
<div class="modal-overlay" id="modalNewFranchise">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalNewFranchise')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px;"><i class="fa-solid fa-building-user" style="color:var(--sa-gold);"></i> Add New Franchisee</h3>
        
        <form action="actions/sa_create_franchise.php" method="POST">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Email Address (Login ID)</label>
                <input type="email" name="email" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Temporary Password</label>
                <input type="text" name="password" class="form-input" value="Welcome@123" required>
            </div>
            <button type="submit" style="background:var(--sa-dark); color:var(--sa-gold); border:none; padding:12px; width:100%; border-radius:8px; font-weight:700; cursor:pointer; margin-top: 10px;">Create Franchise Owner</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

</body>
</html>