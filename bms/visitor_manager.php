<?php
// visitor_manager.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['system_role'] === 'SUPER_ADMIN' || $_SESSION['system_role'] === 'FRANCHISE_OWNER');

$group_id = null;
$all_groups = [];
$chapter_members = [];
$visitors = [];

try {
    if ($is_admin) {
        if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
            $stmtGroups = $pdo->prepare("SELECT id, group_name FROM groups WHERE status = 'Active' AND franchise_owner_id = ? ORDER BY group_name ASC");
            $stmtGroups->execute([$user_id]);
            $all_groups = $stmtGroups->fetchAll();
        } else {
            $all_groups = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();
        }
        $available_group_ids = array_map('intval', array_column($all_groups, 'id'));
        $requested_group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
        $group_id = in_array($requested_group_id, $available_group_ids, true) ? $requested_group_id : ($all_groups[0]['id'] ?? null);
    } else {
        $stmtVerify = $pdo->prepare("SELECT group_id FROM group_members WHERE user_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active'");
        $stmtVerify->execute([$user_id]);
        $coord = $stmtVerify->fetch();
        if (!$coord) die("<div style='text-align:center; padding:50px;'><h2>Access Denied</h2><p>Restricted to Head Table.</p></div>");
        $group_id = $coord['group_id'];
    }

    if ($group_id) {
        $stmtMembers = $pdo->prepare("SELECT u.id, u.first_name, u.last_name FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? AND gm.membership_status = 'Active' ORDER BY u.first_name");
        $stmtMembers->execute([$group_id]);
        $chapter_members = $stmtMembers->fetchAll();

        $stmtVis = $pdo->prepare("
            SELECT v.*, 
                   u.first_name as inviter_first, u.last_name as inviter_last,
                   a.first_name as assigned_first, a.last_name as assigned_last
            FROM visitors v 
            LEFT JOIN users u ON v.invited_by = u.id
            LEFT JOIN users a ON v.assigned_to = a.id
            WHERE v.chapter_id = ? 
            ORDER BY v.visit_date DESC, v.created_at DESC
        ");
        $stmtVis->execute([$group_id]);
        $visitors = $stmtVis->fetchAll();
    }
} catch (PDOException $e) { die("System Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visitor Management | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; margin: 0; display: flex; color: #00204a; min-height: 100vh;}
        .main-content { flex-grow: 1; padding: 40px; margin-left: <?php echo $is_admin ? '260px' : '0'; ?>; width: 100%; box-sizing: border-box; }
        .container { max-width: 800px; margin: 0 auto; }
        
        <?php if($is_admin): ?>
        .sidebar { width: 260px; background-color: var(--dark-blue, #00204a); color: #fff; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100;}
        .sidebar-brand { padding: 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 18px;}
        .nav-item { padding: 15px 24px; display: flex; align-items: center; gap: 15px; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background-color: rgba(255, 107, 0, 0.1); color: #ff6b00; border-right: 4px solid #ff6b00; }
        <?php endif; ?>

        .header-box { background: linear-gradient(135deg, #00204a, #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        
        .chapter-selector { background: white; padding: 15px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 15px; display: flex; gap: 15px; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        .chapter-selector select { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; flex-grow: 1;}
        .chapter-selector button { background: #ff6b00; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }

        /* Universal Search Bar */
        .search-container { position: relative; margin-bottom: 25px; }
        .search-container i { position: absolute; left: 15px; top: 15px; color: #64748b; font-size: 18px; }
        .search-input { width: 100%; padding: 15px 15px 15px 45px; border: 1px solid #cbd5e1; border-radius: 12px; font-family: 'Inter'; font-size: 16px; box-sizing: border-box; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: 0.3s;}
        .search-input:focus { outline: none; border-color: #ff6b00; box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.2); }

        .visitor-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; }
        .vis-badge { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-pending { background: #fffbeb; color: #d97706; }
        .badge-attended { background: #eff6ff; color: #2563eb; }
        .badge-joined { background: #ecfdf5; color: #059669; }
        .action-row { display: flex; gap: 10px; background: #f8fafc; padding: 15px; border-radius: 8px; align-items: center; margin-top: 15px; border: 1px solid #e2e8f0; flex-wrap: wrap;}
        .btn-status { padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; color: white;}
    </style>
</head>
<body>

<?php if ($is_admin) include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="container">
        
        <?php if (!$is_admin): ?>
            <a href="head_table.php" style="color: #64748b; text-decoration: none; margin-bottom: 15px; display: inline-block; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Head Table Portal</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
        <?php endif; ?>

        <div class="header-box">
            <div>
                <h1 style="margin:0;"><i class="fa-solid fa-users-viewfinder" style="color:#ff6b00;"></i> Visitor Pipeline</h1>
                <p style="margin:5px 0 0 0; font-size: 14px; color: rgba(255,255,255,0.7);">Track guests and monitor follow-ups.</p>
            </div>
        </div>

        <?php if ($is_admin): ?>
        <form action="" method="GET" class="chapter-selector">
            <span style="font-weight: 600; font-size: 14px;">Viewing Chapter:</span>
            <select name="group_id">
                <?php foreach ($all_groups as $grp): ?>
                    <option value="<?php echo $grp['id']; ?>" <?php if($group_id == $grp['id']) echo 'selected'; ?>><?php echo htmlspecialchars($grp['group_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Switch</button>
        </form>
        <?php endif; ?>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="visitorSearch" class="search-input" placeholder="Search visitors by Name, Status, or Inviter...">
        </div>

        <div id="visitorList">
            <?php if (empty($visitors)): ?>
                <p style="text-align:center; color:#64748b; padding: 40px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">No visitors found for this chapter.</p>
            <?php else: ?>
                <?php foreach ($visitors as $vis): 
                    $badge_class = 'badge-pending';
                    if ($vis['status'] == 'Attended') $badge_class = 'badge-attended';
                    if ($vis['status'] == 'Joined') $badge_class = 'badge-joined';
                ?>
                    <div class="visitor-card searchable-card">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 15px;">
                            <div>
                                <h3 style="margin:0; font-size: 18px;"><?php echo htmlspecialchars($vis['visitor_name']); ?></h3>
                                <p style="margin:0; font-size: 13px; color: #64748b;"><?php echo htmlspecialchars($vis['company_name']); ?> | <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($vis['email']); ?></p>
                            </div>
                            <span class="vis-badge <?php echo $badge_class; ?>"><?php echo $vis['status']; ?></span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px; color: #64748b;">
                            <div><strong>Invited By:</strong> <span style="color:#ff6b00;"><?php echo htmlspecialchars(trim(($vis['inviter_first'] ?? '') . ' ' . ($vis['inviter_last'] ?? '')) ?: 'Public Website'); ?></span></div>
                            <div><strong>Phone:</strong> <?php echo htmlspecialchars($vis['phone']); ?></div>
                        </div>

                        <?php if (!empty($vis['remarks'])): ?>
                            <div style="background: #fffbeb; padding: 10px; border-left: 3px solid #f59e0b; margin-top: 15px; font-size: 13px;">
                                <strong style="color: #b45309;">Follow-Up Remarks:</strong><br>
                                <span style="color: #00204a;"><?php echo nl2br(htmlspecialchars($vis['remarks'])); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="action-row">
                            <form action="actions/update_visitor.php" method="POST" style="margin:0; flex-grow: 1; display: flex; gap: 10px; align-items: center;">
                                <input type="hidden" name="visitor_id" value="<?php echo $vis['id']; ?>">
                                <input type="hidden" name="action_type" value="assign">
                                <span style="font-size: 12px; font-weight: 600;">Assigned To:</span>
                                <select name="assigned_to" style="padding: 6px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 12px;">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($chapter_members as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" <?php if($vis['assigned_to'] == $m['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" style="background: #cbd5e1; color: #0f172a; border: none; padding: 6px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">Save</button>
                            </form>

                            <?php if ($vis['status'] !== 'Joined'): ?>
                                <?php if ($vis['status'] == 'Pending'): ?>
                                    <form action="actions/update_visitor.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="visitor_id" value="<?php echo $vis['id']; ?>">
                                        <input type="hidden" name="action_type" value="status">
                                        <input type="hidden" name="new_status" value="Attended">
                                        <button type="submit" class="btn-status" style="background: #3b82f6;">Mark Attended</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ((int)$vis['attended'] === 1): ?>
                                    <form action="actions/update_visitor.php" method="POST" style="margin:0;" onsubmit="return confirm('Email registration link to visitor?');">
                                        <input type="hidden" name="visitor_id" value="<?php echo $vis['id']; ?>">
                                        <input type="hidden" name="action_type" value="status">
                                        <input type="hidden" name="new_status" value="Joined">
                                        <button type="submit" class="btn-status" style="background: #10b981;"><i class="fa-solid fa-paper-plane"></i> Joined & Email Link</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <p id="noVisMsg" style="display:none; text-align:center; color:#64748b; padding:20px;">No visitors found matching that search.</p>
        </div>
    </div>
</main>

<script>
document.getElementById('visitorSearch').addEventListener('keyup', function() {
    let filterText = this.value.toLowerCase();
    let cards = document.querySelectorAll('.searchable-card');
    let visibleCount = 0;

    cards.forEach(card => {
        let cardText = card.innerText.toLowerCase();
        
        if (cardText.includes(filterText)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('noVisMsg').style.display = (visibleCount === 0) ? 'block' : 'none';
});
</script>

</body>
</html>