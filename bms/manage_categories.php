<?php
// manage_categories.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, category_name FROM business_categories ORDER BY category_name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Categories | WE KONNECTS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f4f7f6; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        .sidebar { width: 260px; background-color: var(--dark-blue); color: #fff; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; top:0; left:0;}
        .sidebar-brand { padding: 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 18px;}
        .nav-item { padding: 15px 24px; display: flex; align-items: center; gap: 15px; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background-color: rgba(255, 107, 0, 0.1); color: #ff6b00; border-right: 4px solid #ff6b00; }
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        
        .layout-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 25px; align-items: start;}
        @media (max-width: 768px) { .layout-grid { grid-template-columns: 1fr; } .main-content{ margin-left: 0; padding: 20px;} .sidebar {display: none;} }
        
        .add-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        .table-wrapper { background: white; border-radius: 12px; border: 1px solid var(--border-color); padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .table-scroll { max-height: 500px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px;}
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); position: sticky; top: 0; z-index: 10;}
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); }
        tr:hover { background: #f8fafc; }
        
        .btn-primary { background: var(--primary-orange); color: white; border: none; padding: 10px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; width: 100%;}
        .btn-delete { background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;}
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #00204a; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
        
        .search-box { position: relative; margin-bottom: 15px; }
        .search-box i { position: absolute; left: 15px; top: 12px; color: #64748b; }
        .search-box input { width: 100%; padding: 10px 10px 10px 40px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;}
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
            <h1 style="margin:0; font-size: 24px;">Manage Categories</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.8;">Add or remove official business categories.</p>
        </div>
        <i class="fa-solid fa-tags" style="font-size: 35px; color: var(--primary-orange);"></i>
    </div>

    <div class="layout-grid">
        <div class="add-card">
            <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Add New Category</h3>
            <form action="actions/update_category.php" method="POST">
                <input type="hidden" name="action_type" value="add">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="category_name" class="form-input" required>
                </div>
                <button type="submit" class="btn-primary">Add Category</button>
            </form>
        </div>

        <div class="table-wrapper">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="catSearch" placeholder="Search categories...">
            </div>
            
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Category Name</th><th style="width: 80px; text-align:center;">Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr class="cat-row">
                                <td class="cat-name"><strong><?php echo htmlspecialchars($cat['category_name']); ?></strong></td>
                                <td style="text-align:center;">
                                    <form action="actions/update_category.php" method="POST" style="margin:0;" onsubmit="return confirm('Delete this category?');">
                                        <input type="hidden" name="action_type" value="delete">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="btn-delete"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    document.getElementById('catSearch').addEventListener('keyup', function() {
        let filterText = this.value.toLowerCase().trim();
        document.querySelectorAll('.cat-row').forEach(row => {
            let catName = row.querySelector('.cat-name').innerText.toLowerCase();
            row.style.display = catName.includes(filterText) ? '' : 'none';
        });
    });
</script>
</body>
</html>