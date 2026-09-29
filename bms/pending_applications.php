<?php
// pending_applications.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

try {
    if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
        $stmtChapters = $pdo->prepare("SELECT id, group_name FROM groups WHERE status = 'Active' AND franchise_owner_id = ? ORDER BY group_name ASC");
        $stmtChapters->execute([$_SESSION['user_id']]);
        $chapters = $stmtChapters->fetchAll();
    } else {
        $chapters = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();
    }
    $categories = $pdo->query("SELECT category_name FROM business_categories ORDER BY category_name ASC")->fetchAll();

    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.created_at, u.status,
               b.company_name, b.business_category_applied 
        FROM users u 
        LEFT JOIN businesses b ON u.id = b.user_id 
        WHERE u.status = 'Pending_Setup' AND u.system_role = 'MEMBER'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $pending_users = $stmt->fetchAll();

} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Applications | WE KONNECTS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f4f7f6; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        .sidebar { width: 260px; background-color: var(--dark-blue); color: #fff; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; top:0; left:0;}
        .sidebar-brand { padding: 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 18px;}
        .nav-item { padding: 15px 24px; display: flex; align-items: center; gap: 15px; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background-color: rgba(255, 107, 0, 0.1); color: #ff6b00; border-right: 4px solid #ff6b00; }
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); }
        tr:hover { background: #f8fafc; }
        
        .btn-primary { background: var(--primary-orange); color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;}
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #fffbeb; color: #d97706; border: 1px solid #fcd34d;}

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,32,74,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px;}
        .modal-box { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; position: relative; margin: auto; }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #00204a; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .ts-control { padding: 10px !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; font-family: 'Inter' !important; font-size: 14px !important;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <div class="header-box">
        <div>
            <h1 style="margin:0; font-size: 24px;">Pending Applications</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.8;">Review, edit, and assign new members.</p>
        </div>
        <i class="fa-solid fa-user-clock" style="font-size: 35px; color: var(--primary-orange);"></i>
    </div>

    <div class="table-container">
        <table>
            <thead><tr><th>Applicant Name</th><th>Business Details</th><th>Contact Info</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($pending_users)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 30px;">No pending applications right now.</td></tr>
                <?php else: ?>
                    <?php foreach ($pending_users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br><span style="font-size: 12px; color: #64748b;">Applied: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($user['company_name'] ?? 'Not Provided'); ?></strong><br><span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($user['business_category_applied'] ?? 'None'); ?></span></td>
                            <td><div style="font-size: 13px;"><i class="fa-solid fa-phone" style="color:var(--primary-orange);"></i> <?php echo htmlspecialchars($user['phone']); ?></div><div style="font-size: 13px;"><i class="fa-solid fa-envelope" style="color:var(--primary-orange);"></i> <?php echo htmlspecialchars($user['email']); ?></div></td>
                            <td><span class="badge"><?php echo str_replace('_', ' ', $user['status']); ?></span></td>
                            <td><button onclick="openModal('modalReview_<?php echo $user['id']; ?>')" class="btn-primary">Review & Assign</button></td>
                        </tr>

                        <div class="modal-overlay" id="modalReview_<?php echo $user['id']; ?>">
                            <div class="modal-box">
                                <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalReview_<?php echo $user['id']; ?>')"></i>
                                <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Review Application</h3>
                                <form action="actions/approve_member.php" method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <div class="two-col">
                                        <div class="form-group"><label>First Name</label><input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name']); ?>" required></div>
                                        <div class="form-group"><label>Last Name</label><input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name']); ?>" required></div>
                                    </div>
                                    <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone']); ?>" required></div>
                                    <div class="form-group"><label>Company Name</label><input type="text" name="company_name" class="form-input" value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>" required></div>
                                    
                                    <div class="form-group">
                                        <label>Business Category</label>
                                        <select name="category" class="search-category" required>
                                            <option value="<?php echo htmlspecialchars($user['business_category_applied'] ?? ''); ?>" selected><?php echo htmlspecialchars($user['business_category_applied'] ?? 'Select Category'); ?></option>
                                            <?php foreach ($categories as $cat): ?><option value="<?php echo htmlspecialchars($cat['category_name']); ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 20px;">
                                        <label style="color: var(--primary-orange);"><i class="fa-solid fa-layer-group"></i> Assign to Chapter</label>
                                        <select name="group_id" class="form-input" required>
                                            <option value="">-- Select Chapter --</option>
                                            <?php foreach ($chapters as $chapter): ?><option value="<?php echo $chapter['id']; ?>"><?php echo htmlspecialchars($chapter['group_name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">Approve & Add to Chapter</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
    function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.search-category').forEach((el) => {
            new TomSelect(el, { create: true, sortField: { field: "text", direction: "asc" } });
        });
    });
</script>
</body>
</html>