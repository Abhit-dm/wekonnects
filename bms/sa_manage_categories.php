<?php
// sa_manage_categories.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    // Fetch all categories
    $stmt = $pdo->query("SELECT * FROM business_categories ORDER BY category_name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) { 
    die("Database Error."); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Categories | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
        
        .layout-grid { display: grid; grid-template-columns: 300px 1fr; gap: 30px; }
        
        .card { background: white; border-radius: 12px; border: 1px solid var(--border-color); padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        .card h3 { margin-top: 0; margin-bottom: 20px; font-size: 18px; color: var(--sa-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;}
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #0f172a; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
        
        .btn-save { background: var(--sa-dark); color: var(--sa-gold); border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s;}
        .btn-save:hover { background: #1e293b; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f1f5f9; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color);}
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle;}
        
        .badge-status { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid;}
        .status-Active { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
        .status-Inactive { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid #cbd5e1; color: #475569; transition: 0.2s; background: white;}
        .btn-action:hover { background: #f1f5f9; }
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
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Business Categories</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Manage the master list of professions members can select from.</p>
        </div>
        <i class="fa-solid fa-layer-group" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <div class="layout-grid">
        <!-- Add Category Form -->
        <div class="card" style="height: fit-content;">
            <h3><i class="fa-solid fa-plus"></i> Add New Category</h3>
            <form action="actions/sa_save_category.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="category_name" class="form-input" placeholder="e.g., Real Estate Agent" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-input">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn-save">Save Category</button>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="card" style="padding: 0;">
            <table>
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($categories)): ?>
                        <tr><td colspan="3" style="text-align: center; padding: 40px; color: #64748b;">No categories added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($categories as $c): ?>
                            <tr>
                                <td><strong style="color: #0f172a;"><?php echo htmlspecialchars($c['category_name']); ?></strong></td>
                                <td>
                                    <span class="badge-status status-<?php echo $c['status']; ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <form action="actions/sa_save_category.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="category_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $c['status']; ?>">
                                        <button type="submit" class="btn-action">
                                            <?php echo $c['status'] == 'Active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>