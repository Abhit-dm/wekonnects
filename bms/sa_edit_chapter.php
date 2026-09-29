<?php
// sa_edit_chapter.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

$chapter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$chapter_id) { header("Location: sa_manage_chapters.php"); exit; }

try {
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$chapter_id]);
    $chapter = $stmt->fetch();
    if (!$chapter) { die("Chapter not found."); }

    $stmtOwners = $pdo->query("SELECT id, first_name, last_name FROM users WHERE system_role = 'FRANCHISE_OWNER' AND status = 'Active' ORDER BY first_name ASC");
    $franchisees = $stmtOwners->fetchAll();

} catch (PDOException $e) { die("Database Error."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_name = trim(strip_tags($_POST['group_name']));
    $franchise_owner_id = !empty($_POST['franchise_owner_id']) ? intval($_POST['franchise_owner_id']) : NULL;
    $status = $_POST['status'];

    try {
        $update = $pdo->prepare("UPDATE groups SET group_name = ?, franchise_owner_id = ?, status = ? WHERE id = ?");
        $update->execute([$group_name, $franchise_owner_id, $status, $chapter_id]);
        $_SESSION['success_msg'] = "Chapter details updated successfully.";
        header("Location: sa_manage_chapters.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating chapter: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Chapter | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; }
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .card { background: white; padding: 30px; border-radius: 12px; border: 1px solid var(--border-color); max-width: 500px; margin-top: 20px;}
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;}
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;}
        .btn-save { background: var(--sa-gold); color: var(--sa-dark); padding: 12px; width: 100%; border: none; border-radius: 8px; font-weight: 700; cursor: pointer;}
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <a href="sa_manage_chapters.php" style="color: #64748b; text-decoration: none; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <h1 style="color: var(--sa-dark);">Edit Chapter: <?php echo htmlspecialchars($chapter['group_name']); ?></h1>

    <?php if (isset($error)): ?><div style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div><?php endif; ?>

    <div class="card">
        <form method="POST">
            <div class="form-group">
                <label>Chapter Name</label>
                <input type="text" name="group_name" class="form-input" value="<?php echo htmlspecialchars($chapter['group_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Franchise Owner</label>
                <select name="franchise_owner_id" class="form-input">
                    <option value="">-- Unassigned (Headquarters) --</option>
                    <?php foreach($franchisees as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php if($chapter['franchise_owner_id'] == $f['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-input">
                    <option value="Active" <?php if($chapter['status'] == 'Active') echo 'selected'; ?>>Active</option>
                    <option value="Inactive" <?php if($chapter['status'] == 'Inactive') echo 'selected'; ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn-save">Update Chapter</button>
        </form>
    </div>
</main>
</body>
</html>