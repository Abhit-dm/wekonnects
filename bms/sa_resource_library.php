<?php
// sa_resource_library.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM system_resources ORDER BY uploaded_at DESC");
    $resources = $stmt->fetchAll();
} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resource Library | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; min-height: 100vh;}
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        
        .upload-card { background: white; padding: 25px; border-radius: 12px; border: 1px dashed #cbd5e1; margin-bottom: 30px;}
        .form-input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Inter'; width: 100%; box-sizing: border-box; margin-bottom: 15px;}
        .btn-upload { background: var(--sa-gold); color: var(--sa-dark); padding: 12px 20px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer;}
        
        .resource-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .resource-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; align-items: flex-start; gap: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        .r-icon { font-size: 30px; color: #ef4444; }
        .r-title { margin: 0 0 5px 0; font-size: 15px; color: #0f172a; font-weight: 700;}
        .r-cat { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; background: #f1f5f9; padding: 3px 6px; border-radius: 4px; display: inline-block; margin-bottom: 10px;}
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
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Master Resource Library</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Upload operational documents and brand assets for the network.</p>
        </div>
        <i class="fa-solid fa-folder-open" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <div class="upload-card">
        <h3 style="margin-top:0; color:var(--sa-dark);">Upload New Document</h3>
        <form action="actions/sa_upload_resource.php" method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: center;">
            <input type="text" name="title" class="form-input" placeholder="Document Title (e.g., Q3 Rulebook)" style="margin:0;" required>
            <select name="category" class="form-input" style="margin:0;">
                <option value="Rulebook">Rulebook / SOP</option>
                <option value="Training">Training Material</option>
                <option value="Branding">Branding Asset</option>
                <option value="Other">Other</option>
            </select>
            <input type="file" name="resource_file" class="form-input" style="margin:0; padding: 7px;" required>
            <button type="submit" class="btn-upload"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
        </form>
    </div>

    <div class="resource-grid">
        <?php foreach($resources as $r): ?>
            <div class="resource-card">
                <i class="fa-solid fa-file-pdf r-icon"></i>
                <div>
                    <h4 class="r-title"><?php echo htmlspecialchars($r['title']); ?></h4>
                    <span class="r-cat"><?php echo htmlspecialchars($r['category']); ?></span><br>
                    <a href="assets/uploads/resources/<?php echo htmlspecialchars($r['file_name']); ?>" target="_blank" style="font-size: 12px; color: #3b82f6; text-decoration: none; font-weight: 600;">Download File <i class="fa-solid fa-download"></i></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>