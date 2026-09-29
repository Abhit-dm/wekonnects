<?php
// sa_system_settings.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY setting_group ASC, setting_label ASC");
    $settings_raw = $stmt->fetchAll();

    $settings = [];
    foreach ($settings_raw as $row) {
        // Handle terminology hot-swap on the fly without breaking db schema
        if ($row['setting_key'] === 'pt_referral') {
            $row['setting_label'] = 'Giving a Link';
        }
        $settings[$row['setting_group']][] = $row;
    }
} catch (PDOException $e) { 
    die("Database Error."); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
        
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 30px; margin-bottom: 30px;}
        .card { background: white; border-radius: 12px; border: 1px solid var(--border-color); padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        .card h3 { margin-top: 0; margin-bottom: 20px; font-size: 16px; color: var(--sa-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;}
        
        .form-group-inline { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #e2e8f0; }
        .form-group-inline:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .form-group-inline label { font-size: 14px; font-weight: 600; color: #334155; }
        .form-input-number { width: 100px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 16px; text-align: center; font-weight: 700; color: var(--sa-dark);}
        
        .btn-save { background: var(--sa-gold); color: var(--sa-dark); border: none; padding: 15px 30px; border-radius: 8px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.2s; display: block; width: 100%; box-shadow: 0 4px 10px rgba(251,191,36,0.3);}
        .btn-create { background: var(--sa-panel); color: var(--sa-gold); border: 1px solid rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer;}
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); z-index: 1000; align-items: center; justify-content: center;}
        .modal-box { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 400px; position: relative;}
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;}
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="super_admin.php" style="color: #64748b; text-decoration: none; display: inline-block; margin-bottom: 15px; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back</a>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #059669; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #10b981;"><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>

    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">System Variables</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Global configuration for point rewards and constraints.</p>
        </div>
        <button onclick="document.getElementById('modalNewVar').style.display='flex'" class="btn-create"><i class="fa-solid fa-plus"></i> Add New Variable</button>
    </div>

    <form action="actions/sa_update_settings.php" method="POST">
        <div class="settings-grid">
            <?php foreach ($settings as $group_name => $group_settings): ?>
                <div class="card">
                    <h3><i class="fa-solid fa-star" style="color: var(--sa-gold);"></i> <?php echo htmlspecialchars($group_name); ?></h3>
                    <?php foreach ($group_settings as $s): ?>
                        <div class="form-group-inline">
                            <label><?php echo htmlspecialchars($s['setting_label']); ?></label>
                            <input type="number" name="settings[<?php echo $s['setting_key']; ?>]" class="form-input-number" value="<?php echo htmlspecialchars($s['setting_value']); ?>" required>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save All Settings</button>
    </form>
</main>

<div class="modal-overlay" id="modalNewVar">
    <div class="modal-box">
        <i class="fa-solid fa-xmark" style="position:absolute; top:15px; right:20px; cursor:pointer;" onclick="this.closest('.modal-overlay').style.display='none'"></i>
        <h3 style="margin-top:0;">Add New Setting Variable</h3>
        <form action="actions/sa_add_setting.php" method="POST">
            <div class="form-group">
                <label>Setting Key (Internal Code)</label>
                <input type="text" name="setting_key" class="form-input" placeholder="e.g., pt_special_event" required>
            </div>
            <div class="form-group">
                <label>Display Label</label>
                <input type="text" name="setting_label" class="form-input" placeholder="e.g., Special Event Points" required>
            </div>
            <div class="form-group">
                <label>Point Value</label>
                <input type="number" name="setting_value" class="form-input" value="0" required>
            </div>
            <div class="form-group">
                <label>Category Group</label>
                <select name="setting_group" class="form-input">
                    <option value="Attendance & Meetings">Attendance & Meetings</option>
                    <option value="Referrals & Growth">Referrals & Growth</option>
                    <option value="General">General</option>
                </select>
            </div>
            <button type="submit" style="background:var(--sa-dark); color:var(--sa-gold); padding:12px; width:100%; border:none; border-radius:8px; font-weight:700; cursor:pointer;">Create Variable</button>
        </form>
    </div>
</div>
</body>
</html>