<?php
// admin_directory.php
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
    $available_chapter_ids = array_map('intval', array_column($chapters, 'id'));
    $requested_chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
    $active_chapter_id = in_array($requested_chapter_id, $available_chapter_ids, true) ? $requested_chapter_id : ($chapters[0]['id'] ?? null);

    $chapter_members = [];
    if ($active_chapter_id) {
        // STRICT FILTER: Do not show users who are marked Inactive
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status as user_status, u.invited_by,
                   b.company_name, b.business_category_applied,
                   gm.joining_date, gm.renewal_date, gm.leadership_role, gm.membership_status,
                   inviter.first_name as inviter_name, inviter.last_name as inviter_last
            FROM users u
            JOIN group_members gm ON u.id = gm.user_id
            LEFT JOIN businesses b ON u.id = b.user_id
            LEFT JOIN users inviter ON u.invited_by = inviter.id
            WHERE gm.group_id = ? AND u.status = 'Active' AND gm.membership_status = 'Active'
            ORDER BY CASE WHEN gm.leadership_role = 'Coordinator' THEN 1 ELSE 2 END, u.first_name ASC
        ");
        $stmt->execute([$active_chapter_id]);
        $chapter_members = $stmt->fetchAll();
    }
} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Directory | WE KONNECTS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f8fafc; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 25px rgba(0,32,74,0.15);}
        
        .chapter-tabs { display: flex; gap: 10px; margin-bottom: 25px; overflow-x: auto; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); scrollbar-width: none;}
        .chapter-tabs::-webkit-scrollbar { display: none; }
        .chap-tab { padding: 10px 20px; background: white; border: 1px solid var(--border-color); border-radius: 8px; color: #64748b; font-weight: 600; text-decoration: none; white-space: nowrap; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .chap-tab:hover { border-color: var(--primary-orange); color: var(--primary-orange);}
        .chap-tab.active { background: var(--dark-blue); color: white; border-color: var(--dark-blue);}

        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 11px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); letter-spacing: 0.5px;}
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: #334155;}
        tr:hover { background: #f8fafc; }
        
        .action-btn { background: white; color: #475569; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 11px; cursor: pointer; text-decoration: none; display: inline-block; transition: 0.2s; margin-bottom: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .action-btn:hover { background: #f1f5f9; border-color: #94a3b8;}
        .action-btn.btn-edit:hover { background: var(--primary-orange); color: white; border-color: var(--primary-orange);}

        .badge-ht { background: #fef3c7; color: #b45309; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid #fde68a; display: inline-block; margin-top: 5px;}
        .badge-status { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid;}
        .status-active { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
        .status-pending { background: #fffbeb; color: #d97706; border-color: #fcd34d; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,32,74,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px; box-sizing: border-box;}
        .modal-box { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 400px; position: relative; margin: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.2);}
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #00204a; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; outline: none;}
        .form-input:focus { border-color: var(--primary-orange); }
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
            <h1 style="margin:0; font-size: 24px;">Franchise Control Directory</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #93c5fd;">Manage active members. Inactive members are now stored in the Archive.</p>
        </div>
        <i class="fa-solid fa-users-gear" style="font-size: 35px; color: var(--primary-orange);"></i>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <div class="chapter-tabs" style="margin-bottom: 0; border: none;">
            <?php foreach ($chapters as $chap): ?>
                <a href="?chapter_id=<?php echo $chap['id']; ?>" class="chap-tab <?php echo ($active_chapter_id == $chap['id']) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($chap['group_name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <a href="archived_members.php" style="background: white; border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 8px; color: #64748b; font-weight: 600; text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);"><i class="fa-solid fa-box-archive"></i> View Archive</a>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Member Name</th>
                    <th>Business & Category</th>
                    <th>Status</th>
                    <th>Invited By</th>
                    <th style="text-align: right;">Admin Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($chapter_members)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">No active members found in this chapter.</td></tr>
                <?php else: ?>
                    <?php foreach ($chapter_members as $m): ?>
                        <tr>
                            <td>
                                <strong style="font-size:15px; color:var(--dark-blue);"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></strong><br>
                                <?php if($m['leadership_role'] === 'Coordinator'): ?>
                                    <span class="badge-ht"><i class="fa-solid fa-crown"></i> Head Table</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--dark-blue);"><?php echo htmlspecialchars($m['company_name'] ?? 'N/A'); ?></strong><br>
                                <span style="font-size: 11px; color: #64748b;"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($m['business_category_applied']); ?></span>
                            </td>
                            <td>
                                <?php 
                                    $s_class = 'status-pending';
                                    if ($m['user_status'] == 'Active') $s_class = 'status-active';
                                ?>
                                <span class="badge-status <?php echo $s_class; ?>"><?php echo htmlspecialchars($m['user_status']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($m['inviter_name'])): ?>
                                    <span style="font-size: 12px; font-weight:600; color: #3b82f6;"><i class="fa-solid fa-user-check"></i> <?php echo htmlspecialchars($m['inviter_name'] . ' ' . $m['inviter_last']); ?></span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #94a3b8;">Direct / Organic</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; min-width: 250px;">
                                <button onclick="openDatesModal(<?php echo $m['id']; ?>, '<?php echo addslashes(htmlspecialchars($m['first_name'])); ?>', '<?php echo $m['joining_date']; ?>', '<?php echo $m['renewal_date']; ?>')" class="action-btn" style="color:#047857; border-color:#86efac;"><i class="fa-solid fa-calendar-days"></i> Dates</button>
                                
                                <button onclick="openStatusModal(<?php echo $m['id']; ?>, '<?php echo addslashes(htmlspecialchars($m['first_name'])); ?>', '<?php echo $m['user_status']; ?>')" class="action-btn" style="color:#dc2626; border-color:#fecaca;"><i class="fa-solid fa-ban"></i> Status</button>
                                <a href="edit_member.php?id=<?php echo $m['id']; ?>&amp;return_to=admin_directory.php&amp;chapter_id=<?php echo $active_chapter_id; ?>" class="action-btn btn-edit"><i class="fa-solid fa-pen"></i> Edit</a>
                                <button onclick="openTransferModal(<?php echo $m['id']; ?>, '<?php echo addslashes(htmlspecialchars($m['first_name'] . ' ' . $m['last_name'])); ?>')" class="action-btn"><i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer</button>
                                <button onclick="openRoleModal(<?php echo $m['id']; ?>, '<?php echo addslashes(htmlspecialchars($m['first_name'])); ?>', '<?php echo $m['leadership_role']; ?>')" class="action-btn" style="color:#d97706; border-color:#fcd34d;"><i class="fa-solid fa-user-shield"></i> Role</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="modalDates">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalDates')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px; color:var(--dark-blue);">Edit Member Dates</h3>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Update dates for <strong id="datesMemberName" style="color:var(--dark-blue);"></strong>. Changing the Joining Date updates their "Year Member" badge.</p>
        <form action="actions/update_member_dates.php" method="POST">
            <input type="hidden" name="user_id" id="datesUserId">
            <input type="hidden" name="chapter_id" value="<?php echo $active_chapter_id; ?>">
            <div class="form-group">
                <label>Joining Date</label>
                <input type="date" name="joining_date" id="datesJoin" class="form-input" required>
            </div>
            <div class="form-group">
                <label>Renewal Date</label>
                <input type="date" name="renewal_date" id="datesRenew" class="form-input" required>
            </div>
            <button type="submit" style="background:#047857; color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:700; cursor:pointer;">Save Dates</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalStatus">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalStatus')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px; color:var(--dark-blue);">Change Account Status</h3>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Change access level for <strong id="statusMemberName" style="color:var(--dark-blue);"></strong>. Setting status to Inactive will move them to the Archive.</p>
        <form action="actions/update_status.php" method="POST">
            <input type="hidden" name="user_id" id="statusUserId">
            <input type="hidden" name="chapter_id" value="<?php echo $active_chapter_id; ?>">
            <div class="form-group">
                <label>Account Status</label>
                <select name="new_status" id="statusSelect" class="form-input" required>
                    <option value="Active">Active (Full Access)</option>
                    <option value="Pending">Pending (Locked Out)</option>
                    <option value="Inactive">Inactive (Send to Archive & Free Seat)</option>
                </select>
            </div>
            <button type="submit" style="background:#dc2626; color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:700; cursor:pointer;">Update Status</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalTransfer">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalTransfer')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px; color:var(--dark-blue);">Transfer Member</h3>
        <form action="actions/transfer_member.php" method="POST">
            <input type="hidden" name="user_id" id="transferUserId">
            <div class="form-group"><label>Select New Chapter</label>
                <select name="new_group_id" class="form-input" required>
                    <?php foreach ($chapters as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['group_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <button type="submit" style="background:var(--primary-orange); color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:700; cursor:pointer;">Complete Transfer</button>
        </form>
    </div>
</div>
<div class="modal-overlay" id="modalRole">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalRole')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px; color:var(--dark-blue);">Change Leadership Role</h3>
        <form action="actions/update_role.php" method="POST">
            <input type="hidden" name="user_id" id="roleUserId">
            <input type="hidden" name="chapter_id" value="<?php echo $active_chapter_id; ?>">
            <div class="form-group"><label>Select Role</label>
                <select name="leadership_role" id="roleSelect" class="form-input" required>
                    <option value="Member">Standard Member</option><option value="Coordinator">Head Table (Coordinator)</option>
                </select>
            </div>
            <button type="submit" style="background:var(--dark-blue); color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:700; cursor:pointer;">Update Role</button>
        </form>
    </div>
</div>

<script>
    function openDatesModal(userId, userName, joinDate, renewDate) {
        document.getElementById('datesUserId').value = userId;
        document.getElementById('datesMemberName').innerText = userName;
        document.getElementById('datesJoin').value = joinDate;
        document.getElementById('datesRenew').value = renewDate;
        document.getElementById('modalDates').style.display = 'flex';
    }
    function openStatusModal(userId, userName, currentStatus) {
        document.getElementById('statusUserId').value = userId;
        document.getElementById('statusMemberName').innerText = userName;
        document.getElementById('statusSelect').value = currentStatus;
        document.getElementById('modalStatus').style.display = 'flex';
    }
    function openTransferModal(userId, userName) {
        document.getElementById('transferUserId').value = userId;
        document.getElementById('modalTransfer').style.display = 'flex';
    }
    function openRoleModal(userId, userName, currentRole) {
        document.getElementById('roleUserId').value = userId;
        document.getElementById('roleSelect').value = currentRole;
        document.getElementById('modalRole').style.display = 'flex';
    }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
</script>

</body>
</html>