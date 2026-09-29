<?php
// edit_member.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$return_targets = ['admin_directory.php', 'archived_members.php', 'sa_master_directory.php', 'pending_applications.php', 'sa_pending_renewals.php', 'sa_manage_franchises.php'];
$return_to = $_GET['return_to'] ?? ($_SESSION['system_role'] === 'SUPER_ADMIN' ? 'sa_master_directory.php' : 'admin_directory.php');
if (!in_array($return_to, $return_targets, true)) {
    $return_to = $_SESSION['system_role'] === 'SUPER_ADMIN' ? 'sa_master_directory.php' : 'admin_directory.php';
}
$return_chapter_id = ($return_to === 'admin_directory.php') ? filter_input(INPUT_GET, 'chapter_id', FILTER_SANITIZE_NUMBER_INT) : null;
$return_url = $return_to . (($return_to === 'admin_directory.php' && $return_chapter_id) ? '?chapter_id=' . $return_chapter_id : '');

if (!$member_id) {
    header("Location: " . $return_url); exit;
}

try {
    // Safely check if the invited_by column exists so the page doesn't crash before you run the SQL command
    $select_invited_by = "";
    $stmtCols = $pdo->query("SHOW COLUMNS FROM users LIKE 'invited_by'");
    if ($stmtCols->fetch()) {
        $select_invited_by = ", u.invited_by";
    }

    // Fetch EVERYTHING about the member
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.blood_group, u.dob, u.anniversary_date, u.status $select_invited_by,
               b.company_name, b.business_category_applied, b.target_audience, b.ideal_referral, b.top_products,
               gm.joining_date, gm.renewal_date, gm.group_id
        FROM users u
        LEFT JOIN group_members gm ON u.id = gm.user_id
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();

    if (!$member) { die("Member not found."); }

    if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
        $stmtAccess = $pdo->prepare("SELECT 1 FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND g.franchise_owner_id = ? LIMIT 1");
        $stmtAccess->execute([$member_id, $_SESSION['user_id']]);
        if (!$stmtAccess->fetchColumn()) die("Access denied.");
    }

    // Fetch Lists for Dropdowns
    $categories = $pdo->query("SELECT category_name FROM business_categories ORDER BY category_name ASC")->fetchAll();
    $chapters = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();
    
    // Fetch all active members to populate the 'Invited By' dropdown
    $all_members = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();

} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Member | WE KONNECTS Admin</title>
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
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;}
        @media (max-width: 900px) { .form-grid { grid-template-columns: 1fr; } }

        .form-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 20px;}
        .form-card h3 { margin-top: 0; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; color: var(--primary-orange); display: flex; align-items: center; gap: 8px;}
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #00204a; }
        .form-input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; background: #f8fafc;}
        .form-input:focus { outline: none; border-color: var(--primary-orange); background: white;}
        
        .btn-save { background: var(--primary-orange); color: white; border: none; padding: 15px; border-radius: 8px; font-weight: 700; font-size: 16px; cursor: pointer; transition: 0.2s; width: 100%; display: flex; justify-content: center; gap: 10px;}
        .btn-save:hover { background: #e65c00; }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="<?php echo htmlspecialchars($return_url); ?>" style="color: #64748b; text-decoration: none; margin-bottom: 15px; display: inline-block; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Directory</a>

    <div class="header-box">
        <div>
            <h1 style="margin:0; font-size: 24px;">Master Profile Edit</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.8;">Editing <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></p>
        </div>
        <i class="fa-solid fa-user-pen" style="font-size: 35px; color: var(--primary-orange);"></i>
    </div>

    <form action="actions/update_full_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
        <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($return_to); ?>">
        <input type="hidden" name="return_chapter_id" value="<?php echo htmlspecialchars($return_chapter_id ?? ''); ?>">
        
        <div class="form-grid">
            <div>
                <div class="form-card">
                    <h3><i class="fa-solid fa-id-card"></i> Personal Details</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>First Name</label><input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($member['first_name']); ?>" required></div>
                        <div class="form-group"><label>Last Name</label><input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($member['last_name']); ?>" required></div>
                    </div>
                    <div class="form-group"><label>Phone Number</label><input type="text" name="phone" class="form-input" value="<?php echo htmlspecialchars($member['phone']); ?>" required></div>
                    <div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($member['email']); ?>" required></div>
                    <div class="form-group"><label>Blood Group</label><input type="text" name="blood_group" class="form-input" value="<?php echo htmlspecialchars($member['blood_group'] ?? ''); ?>" placeholder="e.g., O+"></div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" class="form-input" value="<?php echo htmlspecialchars($member['dob'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Anniversary</label><input type="date" name="anniversary_date" class="form-input" value="<?php echo htmlspecialchars($member['anniversary_date'] ?? ''); ?>"></div>
                    </div>
                </div>

                <div class="form-card">
                    <h3><i class="fa-solid fa-calendar-check"></i> Membership Status</h3>
                    
                    <div class="form-group">
                        <label>Invited By (Sponsor)</label>
                        <select name="invited_by" class="form-input" style="cursor: pointer;">
                            <option value="">-- None / Unknown --</option>
                            <?php foreach ($all_members as $sponsor): ?>
                                <option value="<?php echo $sponsor['id']; ?>" <?php if(isset($member['invited_by']) && $member['invited_by'] == $sponsor['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($sponsor['first_name'] . ' ' . $sponsor['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status" class="form-input" required>
                            <option value="Active" <?php if($member['status']=='Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactive" <?php if($member['status']=='Locked') echo 'selected'; ?>>Inactive / Archived</option>
                            <option value="Pending" <?php if($member['status']=='Pending_Setup') echo 'selected'; ?>>Pending</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>Joining Date</label><input type="date" name="joining_date" class="form-input" value="<?php echo htmlspecialchars($member['joining_date'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Renewal Date</label><input type="date" name="renewal_date" class="form-input" value="<?php echo htmlspecialchars($member['renewal_date'] ?? ''); ?>"></div>
                    </div>
                </div>
            </div>

            <div>
                <div class="form-card">
                    <h3><i class="fa-solid fa-briefcase"></i> Business Profile</h3>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" class="form-input" value="<?php echo htmlspecialchars($member['company_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Official Category</label>
                        <select name="category" class="form-input" required>
                            <option value="<?php echo htmlspecialchars($member['business_category_applied']); ?>" selected><?php echo htmlspecialchars($member['business_category_applied']); ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category_name']); ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Target Audience</label>
                        <textarea name="target_audience" class="form-input" rows="2"><?php echo htmlspecialchars($member['target_audience'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Ideal Referral</label>
                        <textarea name="ideal_referral" class="form-input" rows="2"><?php echo htmlspecialchars($member['ideal_referral'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Top Products / Services</label>
                        <textarea name="top_products" class="form-input" rows="3"><?php echo htmlspecialchars($member['top_products'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save All Changes</button>
            </div>
        </div>
    </form>
</main>

</body>
</html>