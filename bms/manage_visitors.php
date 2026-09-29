<?php
// manage_visitors.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$system_role = $_SESSION['system_role'] ?? 'MEMBER';
$is_franchise_owner = ($system_role === 'FRANCHISE_OWNER');

$stmtVerify = $pdo->prepare("SELECT gm.group_id FROM group_members gm WHERE gm.user_id = ? AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active'");
$stmtVerify->execute([$user_id]);
$coord = $stmtVerify->fetch();

$is_admin = ($system_role === 'SUPER_ADMIN' || $system_role === 'FRANCHISE_OWNER');

if (!$coord && !$is_admin) { 
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Access Denied</h2><p>Restricted to Coordinators and Admins.</p></div>"); 
}

$group_id = $coord ? $coord['group_id'] : null;

$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$group_filter = filter_input(INPUT_GET, 'group_id', FILTER_SANITIZE_NUMBER_INT) ?? 'all';

$stmtCols = $pdo->query("SHOW COLUMNS FROM visitors");
$visitor_cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
$cat_col = "''";
if (in_array('business_category', $visitor_cols)) {
    $cat_col = 'v.business_category';
} elseif (in_array('category', $visitor_cols)) {
    $cat_col = 'v.category';
}

$chapters_js = [];
if ($is_admin) {
    if ($is_franchise_owner) {
        $stmtChaps = $pdo->prepare("SELECT id, group_name FROM groups WHERE status = 'Active' AND franchise_owner_id = ? ORDER BY group_name ASC");
        $stmtChaps->execute([$user_id]);
        $chaps = $stmtChaps->fetchAll();
    } else {
        $chaps = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();
    }
    $available_group_ids = array_map('intval', array_column($chaps, 'id'));
    if ($is_franchise_owner && $group_filter !== 'all' && !in_array((int)$group_filter, $available_group_ids, true)) {
        $group_filter = 'all';
    }
    
    $filled = $pdo->query("
        SELECT gm.group_id, b.business_category_applied 
        FROM group_members gm 
        JOIN businesses b ON gm.user_id = b.user_id 
        WHERE gm.membership_status = 'Active' AND b.business_category_applied IS NOT NULL AND b.business_category_applied != ''
    ")->fetchAll();
    
    $cat_map = [];
    foreach ($filled as $f) {
        $cat_map[$f['group_id']][] = strtolower(trim($f['business_category_applied']));
    }
    
    foreach ($chaps as $c) {
        $chapters_js[] = [
            'id' => $c['id'],
            'name' => $c['group_name'],
            'filled' => $cat_map[$c['id']] ?? []
        ];
    }
}

$query = "SELECT v.*, $cat_col as v_category,
          u1.first_name as inviter_first, u1.last_name as inviter_last,
          g.group_name as chapter_name
          FROM visitors v
          LEFT JOIN users u1 ON v.invited_by = u1.id
          LEFT JOIN groups g ON v.chapter_id = g.id
          WHERE 1=1";
$params = [];

if (!$is_admin && $group_id) {
    $query .= " AND v.chapter_id = ?";
    $params[] = $group_id;
} elseif ($is_admin && $group_filter !== 'all') {
    $query .= " AND v.chapter_id = ?";
    $params[] = $group_filter;
} elseif ($is_franchise_owner) {
    $query .= " AND g.franchise_owner_id = ?";
    $params[] = $user_id;
}

if (!empty($search)) {
    $query .= " AND (v.visitor_name LIKE ? OR v.company_name LIKE ? OR v.phone LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term; $params[] = $search_term; $params[] = $search_term;
}

$query .= " GROUP BY v.id ORDER BY v.visit_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$visitors = $stmt->fetchAll();

$pipeline = ['Pending' => [], 'Joined' => [], 'Declined' => []];

foreach ($visitors as $v) {
    $status = $v['status'];
    if (isset($pipeline[$status])) {
        $pipeline[$status][] = $v;
    } else {
        $pipeline['Pending'][] = $v;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Visitor Pipeline | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --brand-blue: #00204a; --brand-orange: #ff6b00; --bg-color: #f8fafc; --card-bg: #ffffff; --border: #e2e8f0; --text-main: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-color); color: var(--text-main); margin: 0; padding-bottom: 50px; overflow-x: hidden;}
        
        .portal-container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 15px; box-sizing: border-box; }
        
        .header-box { background: var(--brand-blue); padding: 25px 20px; border-radius: 16px; margin-bottom: 20px; color: white; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8px 20px rgba(0,32,74,0.15); position: relative; overflow: hidden;}
        .header-box::after { content: ''; position: absolute; right: -20px; bottom: -20px; width: 120px; height: 120px; background: var(--brand-orange); border-radius: 50%; filter: blur(50px); opacity: 0.3; z-index: 1;}
        .header-content { position: relative; z-index: 2;}
        .header-box h1 { margin: 0 0 5px 0; font-size: 20px; font-weight: 800;}
        .header-box p { margin: 0; color: #94a3b8; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;}
        
        .filter-bar { background: var(--card-bg); padding: 15px; border-radius: 12px; border: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .filter-input, .filter-select { flex: 1; min-width: 200px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: 'Inter'; box-sizing: border-box; background: #f8fafc;}
        .filter-actions { display: flex; gap: 10px; flex: 1; min-width: 200px;}
        .btn-search { flex: 2; background: var(--brand-blue); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .btn-clear { flex: 1; background: #f1f5f9; color: var(--text-muted); border: 1px solid #cbd5e1; padding: 12px; border-radius: 8px; font-weight: 600; text-align: center; text-decoration: none; display: flex; justify-content: center; align-items: center;}
        
        .kanban-board { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 20px; align-items: flex-start; scrollbar-width: thin; }
        .kanban-col { flex: 0 0 320px; background: #f1f5f9; border-radius: 12px; padding: 15px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 12px; min-height: 400px;}
        .kanban-col-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; font-weight: 800; color: var(--brand-blue); text-transform: uppercase; font-size: 13px; border-bottom: 2px solid #cbd5e1; padding-bottom: 10px;}
        .kanban-count { background: #cbd5e1; color: var(--brand-blue); padding: 2px 8px; border-radius: 12px; font-size: 11px;}

        .col-pending { border-top: 4px solid #f59e0b; }
        .col-joined { border-top: 4px solid #10b981; }
        .col-declined { border-top: 4px solid #ef4444; }

        .visitor-card { background: var(--card-bg); border-radius: 10px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; flex-direction: column;}
        
        .vc-header { padding: 12px; border-bottom: 1px dashed #cbd5e1; }
        .vc-name { margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: var(--brand-blue); }
        .vc-company { margin: 0; font-size: 11px; color: var(--text-muted); display: flex; align-items: center; gap: 5px;}
        
        .vc-body { padding: 12px; font-size: 12px; color: var(--text-main); display: flex; flex-direction: column; gap: 6px;}
        .vc-detail { display: flex; align-items: flex-start; gap: 8px;}
        .vc-detail i { width: 14px; color: var(--text-muted); margin-top: 2px;}
        .vc-remarks { background: #f8fafc; padding: 8px; border-radius: 6px; border-left: 2px solid #cbd5e1; font-size: 11px; color: #475569; margin-top: 4px;}

        .vc-actions { padding: 10px; background: #f8fafc; border-top: 1px solid var(--border); display: grid; grid-template-columns: 1fr 1fr; gap: 6px;}
        .btn-action { background: white; color: var(--brand-blue); border: 1px solid #cbd5e1; padding: 8px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; text-align: center; display: flex; justify-content: center; align-items: center; gap: 4px;}
        .btn-delete { background: #fef2f2; color: #ef4444; border-color: #fecaca; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,32,74,0.9); z-index: 1000; align-items: flex-end; justify-content: center; box-sizing: border-box;}
        .modal-box { background: white; padding: 25px 20px 40px 20px; border-radius: 20px 20px 0 0; width: 100%; max-width: 600px; position: relative; max-height: 85vh; overflow-y: auto; animation: slideUp 0.3s ease-out;}
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--brand-blue); text-transform: uppercase;}
        .solid-input { width: 100%; padding: 14px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; color: var(--text-main); font-family: 'Inter', sans-serif; font-size: 14px; box-sizing: border-box; outline: none;}
        .solid-input optgroup { font-weight: 700; color: var(--brand-blue); background: #e2e8f0;}
    </style>
</head>
<body>

<div class="portal-container">
            <a href="head_table.php" style="color: var(--text-muted); text-decoration: none; margin-bottom: 15px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Portal</a>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; margin-bottom: 15px; display:flex; align-items:center; gap:8px; font-size: 13px;"><i class="fa-solid fa-check-circle" style="font-size:16px;"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px; border-radius: 8px; margin-bottom: 15px; display:flex; align-items:center; gap:8px; font-size: 13px;"><i class="fa-solid fa-triangle-exclamation" style="font-size:16px;"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <div class="header-box">
        <div class="header-content">
            <p>Visitor Pipeline</p>
            <h1>Kanban CRM</h1>
        </div>
        <i class="fa-solid fa-users-viewfinder" style="font-size: 40px; color: var(--brand-orange); position: relative; z-index: 2;"></i>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="manage_visitors.php" class="filter-bar">
        <input type="text" name="search" class="filter-input" placeholder="Search name, company, phone..." value="<?php echo htmlspecialchars($search); ?>">
        
        <?php if ($is_admin): ?>
        <select name="group_id" class="filter-select">
            <option value="all">All Chapters</option>
            <?php foreach ($chaps as $grp): ?>
                <option value="<?php echo $grp['id']; ?>" <?php if($group_filter == $grp['id']) echo 'selected'; ?>><?php echo htmlspecialchars($grp['group_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        
        <div class="filter-actions">
            <button type="submit" class="btn-search"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="manage_visitors.php" class="btn-clear"><i class="fa-solid fa-xmark"></i> Clear</a>
        </div>
    </form>

    <!-- KANBAN BOARD -->
    <div class="kanban-board">
        
        <!-- PENDING PIPELINE -->
        <div class="kanban-col col-pending">
            <div class="kanban-col-header">
                <span><i class="fa-solid fa-hourglass-half" style="color: #f59e0b;"></i> Pipeline</span>
                <span class="kanban-count"><?php echo count($pipeline['Pending']); ?></span>
            </div>
            <?php if(empty($pipeline['Pending'])) echo "<p style='font-size:12px; color:#94a3b8; text-align:center;'>No pending visitors.</p>"; ?>
            <?php foreach ($pipeline['Pending'] as $vis): $display_cat = !empty($vis['v_category']) ? $vis['v_category'] : 'Uncategorized'; ?>
                <div class="visitor-card">
                    <div class="vc-header">
                        <h3 class="vc-name"><?php echo htmlspecialchars($vis['visitor_name']); ?></h3>
                        <p class="vc-company"><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($vis['company_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="vc-body">
                        <div class="vc-detail"><i class="fa-solid fa-phone"></i><span style="color: #3b82f6; font-weight: 600;"><?php echo htmlspecialchars($vis['phone'] ?? 'N/A'); ?></span></div>
                        <div class="vc-detail"><i class="fa-solid fa-tags"></i><span><?php echo htmlspecialchars($display_cat); ?></span></div>
                        <div class="vc-detail"><i class="fa-solid fa-user-tag"></i><span>By: <?php echo htmlspecialchars(trim(($vis['inviter_first'] ?? '') . ' ' . ($vis['inviter_last'] ?? '')) ?: 'Public Website'); ?></span></div>
                        <?php if (!empty($vis['remarks'])): ?><div class="vc-remarks"><strong>Notes:</strong> <?php echo htmlspecialchars($vis['remarks']); ?></div><?php endif; ?>
                    </div>
                    <div class="vc-actions">
                        <button onclick="openStatusModal(<?php echo $vis['id']; ?>, '<?php echo $vis['status']; ?>', <?php echo (int)$vis['attended']; ?>)" class="btn-action" style="grid-column: 1 / -1;"><i class="fa-solid fa-arrows-rotate"></i> Change Status</button>
                        
                        <?php if ($is_admin): ?>
                            <button onclick="openEditVisitorModal(<?php echo $vis['id']; ?>, '<?php echo addslashes(htmlspecialchars($vis['visitor_name'])); ?>', '<?php echo addslashes(htmlspecialchars($vis['company_name'])); ?>', '<?php echo addslashes(htmlspecialchars($vis['phone'])); ?>')" class="btn-action" style="background:#eff6ff; color:#3b82f6; border-color:#bfdbfe;"><i class="fa-solid fa-pen"></i> Edit</button>
                            <form action="actions/delete_visitor.php" method="POST" onsubmit="return confirm('Permanently delete visitor?');"><input type="hidden" name="visitor_id" value="<?php echo $vis['id']; ?>"><button type="submit" class="btn-action btn-delete" style="width: 100%;"><i class="fa-solid fa-trash-can"></i> Delete</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- JOINED -->
        <div class="kanban-col col-joined">
            <div class="kanban-col-header">
                <span><i class="fa-solid fa-check-circle" style="color: #10b981;"></i> Joined</span>
                <span class="kanban-count"><?php echo count($pipeline['Joined']); ?></span>
            </div>
            <?php if(empty($pipeline['Joined'])) echo "<p style='font-size:12px; color:#94a3b8; text-align:center;'>No joined visitors.</p>"; ?>
            <?php foreach ($pipeline['Joined'] as $vis): $display_cat = !empty($vis['v_category']) ? $vis['v_category'] : 'Uncategorized'; ?>
                <div class="visitor-card">
                    <div class="vc-header">
                        <h3 class="vc-name"><?php echo htmlspecialchars($vis['visitor_name']); ?></h3>
                        <p class="vc-company"><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($vis['company_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="vc-actions">
                        <button onclick="openStatusModal(<?php echo $vis['id']; ?>, '<?php echo $vis['status']; ?>', <?php echo (int)$vis['attended']; ?>)" class="btn-action" style="grid-column: 1 / -1;"><i class="fa-solid fa-arrows-rotate"></i> Change Status</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- DECLINED -->
        <div class="kanban-col col-declined">
            <div class="kanban-col-header">
                <span><i class="fa-solid fa-xmark-circle" style="color: #ef4444;"></i> Declined</span>
                <span class="kanban-count"><?php echo count($pipeline['Declined']); ?></span>
            </div>
            <?php if(empty($pipeline['Declined'])) echo "<p style='font-size:12px; color:#94a3b8; text-align:center;'>No declined visitors.</p>"; ?>
            <?php foreach ($pipeline['Declined'] as $vis): $display_cat = !empty($vis['v_category']) ? $vis['v_category'] : 'Uncategorized'; ?>
                <div class="visitor-card">
                    <div class="vc-header">
                        <h3 class="vc-name"><?php echo htmlspecialchars($vis['visitor_name']); ?></h3>
                        <p class="vc-company"><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($vis['company_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="vc-actions">
                        <button onclick="openStatusModal(<?php echo $vis['id']; ?>, '<?php echo $vis['status']; ?>', <?php echo (int)$vis['attended']; ?>)" class="btn-action" style="grid-column: 1 / -1;"><i class="fa-solid fa-arrows-rotate"></i> Restore Status</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Bottom Slide-Up Modals -->
<div class="modal-overlay" id="modalStatus">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalStatus')"></i>
        <h3 style="margin-top:0; color:var(--brand-blue); font-size:18px; border-bottom: 1px solid var(--border); padding-bottom: 10px;"><i class="fa-solid fa-arrows-rotate" style="color:#3b82f6;"></i> Update Status</h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:20px;">Confirm a visitor as joined after verified meeting attendance. The inviter receives 25 points.</p>
        <form action="actions/update_visitor_status.php" method="POST">
            <input type="hidden" name="visitor_id" id="statusVisitorId">
            <div class="form-group">
                <label>New Status</label>
                <select name="status" id="statusSelect" class="solid-input" required>
                    <option value="Pending">Pending (Pipeline)</option>
                    <option value="Joined">Joined (25 Points)</option>
                    <option value="Declined">Declined</option>
                </select>
            </div>
            <button type="submit" style="background:var(--brand-orange); color:white; border:none; padding:15px; width:100%; border-radius:8px; font-weight:700; cursor:pointer; margin-top:10px; font-size: 15px;">Save Status</button>
        </form>
    </div>
</div>

<?php if ($is_admin): ?>
<!-- NEW EDIT VISITOR MODAL FOR FOs -->
<div class="modal-overlay" id="modalEditVisitor">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalEditVisitor')"></i>
        <h3 style="margin-top:0; color:var(--brand-blue); font-size:18px; border-bottom: 1px solid var(--border); padding-bottom: 10px;"><i class="fa-solid fa-pen" style="color:#3b82f6;"></i> Edit Visitor Details</h3>
        <form action="actions/edit_visitor.php" method="POST">
            <input type="hidden" name="visitor_id" id="editVisitorId">
            <div class="form-group">
                <label>Visitor Name</label>
                <input type="text" name="visitor_name" id="editVisName" class="solid-input" required>
            </div>
            <div class="form-group">
                <label>Company / Business</label>
                <input type="text" name="company_name" id="editVisCompany" class="solid-input" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" id="editVisPhone" class="solid-input" required>
            </div>
            <button type="submit" style="background:#3b82f6; color:white; border:none; padding:15px; width:100%; border-radius:8px; font-weight:700; cursor:pointer; margin-top:10px; font-size: 15px;">Save Changes</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    function openStatusModal(id, currentStatus, attended) {
        document.getElementById('statusVisitorId').value = id;
        document.getElementById('statusSelect').value = currentStatus;
        document.querySelector('#statusSelect option[value="Joined"]').disabled = !attended;
        document.getElementById('modalStatus').style.display = 'flex';
    }
    
    function openEditVisitorModal(id, name, company, phone) {
        document.getElementById('editVisitorId').value = id;
        document.getElementById('editVisName').value = name;
        document.getElementById('editVisCompany').value = company;
        document.getElementById('editVisPhone').value = phone;
        document.getElementById('modalEditVisitor').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
</script>

</body>
</html>