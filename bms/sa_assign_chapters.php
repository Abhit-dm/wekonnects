<?php
// sa_assign_chapters.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

$owner_id = isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0;

if (!$owner_id) {
    header("Location: sa_manage_franchises.php"); exit;
}

try {
    // Fetch the specific Franchise Owner
    $stmtOwner = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? AND system_role = 'FRANCHISE_OWNER'");
    $stmtOwner->execute([$owner_id]);
    $owner = $stmtOwner->fetch();

    if (!$owner) { die("Franchise Owner not found."); }

    // Fetch ALL active chapters and see who currently owns them
    $stmtGroups = $pdo->query("
        SELECT g.id, g.group_name, g.franchise_owner_id, 
               u.first_name as owner_first, u.last_name as owner_last 
        FROM groups g 
        LEFT JOIN users u ON g.franchise_owner_id = u.id 
        WHERE g.status = 'Active' 
        ORDER BY g.group_name ASC
    ");
    $chapters = $stmtGroups->fetchAll();

} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Chapters | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
        
        .chapter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-bottom: 30px;}
        
        /* Custom Checkbox Cards */
        .chapter-card { display: flex; align-items: flex-start; gap: 15px; padding: 20px; border-radius: 12px; border: 2px solid #e2e8f0; background: white; cursor: pointer; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .chapter-card:hover { border-color: #cbd5e1; background: #f8fafc;}
        
        .chapter-card input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; margin-top: 2px; accent-color: var(--sa-gold);}
        
        /* Highlight states based on ownership */
        .card-owned { border-color: #10b981; background: #ecfdf5;}
        .card-owned:hover { border-color: #059669; background: #d1fae5;}
        .card-conflict { border-color: #fca5a5; background: #fef2f2;}
        
        .ch-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 5px 0;}
        .ch-status { font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 6px; display: inline-block;}
        
        .status-unassigned { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;}
        .status-owned { background: #10b981; color: white; border: 1px solid #059669;}
        .status-conflict { background: #ef4444; color: white; border: 1px solid #dc2626;}

        .btn-save { background: var(--sa-gold); color: var(--sa-dark); border: none; padding: 15px 30px; border-radius: 8px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.2s; display: block; width: 100%; box-shadow: 0 4px 10px rgba(251,191,36,0.3);}
        .btn-save:hover { background: #f59e0b; }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="sa_manage_franchises.php" style="color: #64748b; text-decoration: none; display: inline-block; margin-bottom: 15px; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Franchises</a>

    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Assign Territories</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Select the chapters managed by <strong style="color:white;"><?php echo htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name']); ?></strong></p>
        </div>
        <i class="fa-solid fa-map-location-dot" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <form action="actions/sa_update_chapter_assignments.php" method="POST">
        <input type="hidden" name="owner_id" value="<?php echo $owner_id; ?>">
        
        <div class="chapter-grid">
            <?php foreach($chapters as $c): 
                $is_owned = ($c['franchise_owner_id'] == $owner_id);
                $is_conflict = ($c['franchise_owner_id'] && $c['franchise_owner_id'] != $owner_id);
                
                $card_class = '';
                if ($is_owned) $card_class = 'card-owned';
                if ($is_conflict) $card_class = 'card-conflict';
            ?>
                <label class="chapter-card <?php echo $card_class; ?>">
                    <input type="checkbox" name="assigned_chapters[]" value="<?php echo $c['id']; ?>" <?php if($is_owned) echo 'checked'; ?>>
                    <div>
                        <h3 class="ch-title"><?php echo htmlspecialchars($c['group_name']); ?></h3>
                        
                        <?php if ($is_owned): ?>
                            <span class="ch-status status-owned"><i class="fa-solid fa-check"></i> Currently Assigned Here</span>
                        <?php elseif ($is_conflict): ?>
                            <span class="ch-status status-conflict"><i class="fa-solid fa-triangle-exclamation"></i> Owned by: <?php echo htmlspecialchars($c['owner_first'] . ' ' . $c['owner_last']); ?></span>
                            <div style="font-size: 11px; color: #ef4444; margin-top: 5px;">Checking this will forcefully transfer ownership.</div>
                        <?php else: ?>
                            <span class="ch-status status-unassigned">Unassigned</span>
                        <?php endif; ?>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save Chapter Assignments</button>
    </form>
</main>
</body>
</html>