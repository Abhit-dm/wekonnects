<?php
// sa_master_directory.php
session_start();
require_once 'config/database.php';

// Set timezone to ensure correct 9:30 AM logic
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'all';
$chapter_filter = filter_input(INPUT_GET, 'chapter_id', FILTER_SANITIZE_NUMBER_INT) ?? 'all';
$category_filter = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'all';

try {
    $categories = $pdo->query("SELECT category_name FROM business_categories ORDER BY category_name ASC")->fetchAll();
    $chapters = $pdo->query("SELECT id, group_name FROM groups ORDER BY group_name ASC")->fetchAll();

    // STRICT 9:30 AM RESET LOGIC
    $chapter_cycle_starts = [];
    $stmtAllMtgs = $pdo->query("SELECT group_id, meeting_date FROM chapter_meetings WHERE meeting_type = 'Meeting' AND meeting_date <= CURDATE() ORDER BY meeting_date DESC");
    $all_past_mtgs = $stmtAllMtgs->fetchAll();
    
    foreach ($all_past_mtgs as $m) {
        $g_id = $m['group_id'];
        if (!isset($chapter_cycle_starts[$g_id])) {
            $mtg_end_time = strtotime($m['meeting_date'] . ' 09:30:00');
            if (time() >= $mtg_end_time) {
                $chapter_cycle_starts[$g_id] = $m['meeting_date'];
            }
        }
    }
    $default_cycle_start = date('Y-m-d', strtotime('-15 days'));

    // The Master Query (LEFT JOINS ensure Pending/Inactive aren't dropped)
    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status as user_status, u.system_role, u.profile_photo,
               b.company_name, b.business_category_applied,
               g.id as group_id, g.group_name, gm.leadership_role,
               att.total_mtg, att.present_count
        FROM users u
        LEFT JOIN businesses b ON u.id = b.user_id
        LEFT JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
        LEFT JOIN groups g ON gm.group_id = g.id
        LEFT JOIN (
            SELECT a.user_id, cm.group_id,
                   COUNT(a.id) as total_mtg,
                   SUM(CASE WHEN a.attendance_status IN ('Present', 'Late', 'Substitute') THEN 1 ELSE 0 END) as present_count
            FROM attendance a
            JOIN chapter_meetings cm ON a.meeting_id = cm.id
            WHERE cm.meeting_type <> 'Daily Status'
            GROUP BY a.user_id, cm.group_id
        ) att ON u.id = att.user_id AND gm.group_id = att.group_id
        WHERE 1=1
    ";
    
    $params = [];

    if ($status_filter !== 'all') { $sql .= " AND u.status = ?"; $params[] = $status_filter; }
    if ($chapter_filter !== 'all') { $sql .= " AND g.id = ?"; $params[] = $chapter_filter; }
    if ($category_filter !== 'all') { $sql .= " AND b.business_category_applied = ?"; $params[] = $category_filter; }

    if (!empty($search)) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR b.company_name LIKE ?)";
        $search_term = "%{$search}%";
        array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_users = $stmt->fetchAll();

    // Calculate Points & Attendance %
    foreach ($raw_users as &$u) {
        $grp_id = $u['group_id'];
        $cycle_start = $chapter_cycle_starts[$grp_id] ?? $default_cycle_start;
        
        $u['att_perc'] = ($u['total_mtg'] > 0) ? round(($u['present_count'] / $u['total_mtg']) * 100) : 100;

        $stmtPts = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? AND a.attendance_status IN ('Present', 'Late') AND cm.group_id = ? AND cm.meeting_type <> 'Daily Status' AND cm.meeting_date >= ?) * 1 +
                (SELECT COALESCE(SUM(a.early_bird), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? AND cm.group_id = ? AND cm.meeting_date >= ?) * 1 +
                (SELECT COALESCE(SUM(a.status_update), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? AND cm.group_id = ? AND cm.meeting_date >= ?) * 1 +
                (SELECT COALESCE(SUM(a.best_30_sec), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? AND cm.group_id = ? AND cm.meeting_date >= ?) * 1 +
                (SELECT COALESCE(SUM(a.presentation_8_min), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? AND cm.group_id = ? AND cm.meeting_date >= ?) * 5 +
                (SELECT COALESCE(SUM(a.som), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? AND cm.group_id = ? AND cm.meeting_date >= ?) * 10 +
                (SELECT COALESCE(SUM(a.mtp), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? AND cm.group_id = ? AND cm.meeting_date >= ?) * 25 +
                (SELECT COUNT(*) FROM slips WHERE initiator_member_id = ? AND slip_type = '121' AND COALESCE(NULLIF(link_given_date, ''), DATE(date_logged)) >= ?) * 1 +
                (SELECT COUNT(*) FROM slips WHERE initiator_member_id = ? AND slip_type = 'REFERRAL' AND referral_type = 'INSIDE' AND COALESCE(NULLIF(link_given_date, ''), DATE(date_logged)) >= ?) * 2 +
                (SELECT COUNT(*) FROM slips WHERE initiator_member_id = ? AND slip_type = 'REFERRAL' AND referral_type = 'OUTSIDE' AND COALESCE(NULLIF(link_given_date, ''), DATE(date_logged)) >= ?) * 4 +
                (SELECT COUNT(*) FROM visitors WHERE invited_by = ? AND chapter_id = ? AND status = 'Joined' AND attended = 1 AND DATE(visit_date) >= ?) * 25
            as total_points
        ");
        $stmtPts->execute([
            $u['id'], $grp_id, $cycle_start, $u['id'], $grp_id, $cycle_start,
            $u['id'], $grp_id, $cycle_start, $u['id'], $grp_id, $cycle_start,
            $u['id'], $grp_id, $cycle_start, $u['id'], $grp_id, $cycle_start,
            $u['id'], $grp_id, $cycle_start, $u['id'], $cycle_start,
            $u['id'], $cycle_start, $u['id'], $cycle_start,
            $u['id'], $grp_id, $cycle_start
        ]);
        
        $u['total_points'] = (int)$stmtPts->fetchColumn();
    }
    unset($u);

    // Group & Rank (Only applied if they have an active chapter, else default to 'Unranked')
    $chapter_grouping = [];
    foreach ($raw_users as $u) { 
        if ($u['group_id']) { $chapter_grouping[$u['group_id']][] = $u; }
    }

    $all_users = [];
    foreach ($raw_users as $u) {
        if ($u['group_id']) {
            $grp_members = $chapter_grouping[$u['group_id']];
            usort($grp_members, function($a, $b) { return $b['total_points'] <=> $a['total_points']; });
            $rank = 1;
            foreach ($grp_members as $m) {
                if ($m['id'] == $u['id']) {
                    if ($m['total_points'] == 0) {
                        $u['tier'] = 'Star'; $u['tier_class'] = 'badge-star'; $u['tier_icon'] = 'fa-star';
                    } elseif ($rank <= 3) { 
                        $u['tier'] = 'Super Star'; $u['tier_class'] = 'badge-super-star'; $u['tier_icon'] = 'fa-crown';
                    } else { 
                        $u['tier'] = 'Rising Star'; $u['tier_class'] = 'badge-rising-star'; $u['tier_icon'] = 'fa-arrow-trend-up';
                    }
                    break;
                }
                $rank++;
            }
        } else {
            $u['tier'] = 'Unranked'; $u['tier_class'] = 'badge-star'; $u['tier_icon'] = 'fa-minus';
        }
        $all_users[] = $u;
    }
    
    // Final Global Sort (Descending by Points)
    usort($all_users, function($a, $b) { return $b['total_points'] <=> $a['total_points']; });

} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global Directory | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #334155; --text-muted: #94a3b8; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; display: flex; min-height: 100vh;}
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
        
        .filter-bar { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px; display: flex; gap: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); flex-wrap: wrap;}
        .form-group { flex: 1; min-width: 200px; margin: 0;}
        .filter-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-family: 'Inter'; font-size: 14px;}
        .filter-input:focus { border-color: var(--sa-gold); box-shadow: 0 0 0 3px rgba(251,191,36,0.1); }
        .btn-search { background: var(--sa-dark); color: var(--sa-gold); border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; height: 44px; display: inline-flex; align-items: center; gap: 8px;}
        .btn-search:hover { background: #1e293b; }

        .table-container { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f1f5f9; padding: 15px; font-size: 11px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; white-space: nowrap;}
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid #e2e8f0; vertical-align: top; color: #334155;}
        
        .photo-wrapper { position: relative; display: inline-block; margin-right: 15px; vertical-align: top;}
        .member-photo { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--sa-gold); background: var(--sa-dark);}
        .points-pill { position: absolute; bottom: -5px; left: 50%; transform: translateX(-50%); background: var(--sa-dark); color: white; font-size: 9px; font-weight: 800; padding: 2px 6px; border-radius: 10px; border: 1px solid white; white-space: nowrap;}

        .badge-status { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid;}
        .status-Active { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
        .status-Inactive { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .status-Pending { background: #fffbeb; color: #d97706; border-color: #fcd34d; }

        .badge-tier { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid #e2e8f0;}
        .badge-super-star { background: #fffbeb; color: #d97706; border-color: #fcd34d; }
        .badge-rising-star { background: #f0fdfa; color: #0284c7; border-color: #bae6fd; }
        .badge-star { background: #f8fafc; color: #475569; border-color: #cbd5e1; }

        .att-badge { font-weight: 800; }
        .att-good { color: #10b981; background: #ecfdf5; border-color: #a7f3d0; }
        .att-warn { color: #f59e0b; background: #fffbeb; border-color: #fcd34d; }
        .att-poor { color: #ef4444; background: #fef2f2; border-color: #fecaca; }

        .btn-action { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; text-decoration: none; margin-bottom: 5px; cursor: pointer; border: 1px solid #cbd5e1; color: #475569; transition: 0.2s;}
        .btn-action:hover { background: #f1f5f9; }
        .btn-login-as { background: var(--sa-dark); color: white; border-color: var(--sa-dark);}
        .btn-login-as:hover { background: var(--sa-gold); color: var(--sa-dark); border-color: var(--sa-gold);}

        .ts-control { padding: 12px !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; background: #fff !important; font-family: 'Inter' !important; font-size: 14px !important;}
        .ts-control.focus { border-color: var(--sa-gold) !important; box-shadow: 0 0 0 3px rgba(251,191,36,0.1) !important;}
        .ts-dropdown { border-radius: 8px !important; border: 1px solid #cbd5e1 !important;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Global Master Directory</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Gamified search and management for every account in the WE KONNECTS ecosystem.</p>
        </div>
        <i class="fa-solid fa-users-viewfinder" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <form method="GET" class="filter-bar">
        <div class="form-group" style="flex: 2;">
            <input type="text" name="search" class="filter-input" placeholder="Search by name, email, phone, or company..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="form-group">
            <select name="chapter_id" class="search-dropdown">
                <option value="all">All Chapters</option>
                <?php foreach($chapters as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php if($chapter_filter == $c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['group_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <select name="category" class="search-dropdown">
                <option value="all">All Categories</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['category_name']); ?>" <?php if($category_filter == $c['category_name']) echo 'selected'; ?>><?php echo htmlspecialchars($c['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 0.5;">
            <select name="status" class="filter-input">
                <option value="all">All Statuses</option>
                <option value="Active" <?php if($status_filter == 'Active') echo 'selected'; ?>>Active</option>
                <option value="Pending_Setup" <?php if($status_filter == 'Pending_Setup') echo 'selected'; ?>>Pending</option>
                <option value="Locked" <?php if($status_filter == 'Locked') echo 'selected'; ?>>Inactive / Archived</option>
            </select>
        </div>
        <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            <a href="sa_master_directory.php" style="padding: 12px; color: #64748b; text-decoration: none; font-weight: 600;">Clear</a>
        </div>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Member Details</th>
                    <th>Chapter & Classification</th>
                    <th>Metrics (Points & Att.)</th>
                    <th>Account Status</th>
                    <th style="text-align: right; width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($all_users)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">No users match your global search.</td></tr>
                <?php else: ?>
                    <?php foreach($all_users as $u): 
                        $att_color_class = 'att-good';
                        if ($u['att_perc'] < 85) $att_color_class = 'att-warn';
                        if ($u['att_perc'] < 70) $att_color_class = 'att-poor';
                        $status_class = 'status-Active';
                        $status_label = $u['user_status'];
                        if ($u['user_status'] === 'Pending_Setup') { $status_class = 'status-Pending'; $status_label = 'Pending'; }
                        if ($u['user_status'] === 'Locked') { $status_class = 'status-Inactive'; $status_label = 'Inactive / Archived'; }
                    ?>
                        <tr>
                            <td>
                                <div class="photo-wrapper">
                                    <img src="assets/uploads/profiles/<?php echo htmlspecialchars($u['profile_photo'] ?? 'default.png'); ?>" class="member-photo" onerror="this.src='https://via.placeholder.com/150/00204a/ff6b00?text=<?php echo substr($u['first_name'],0,1); ?>'">
                                    <div class="points-pill"><?php echo $u['total_points']; ?> pts</div>
                                </div>
                                <div style="display: inline-block;">
                                    <strong style="font-size: 15px; color: #0f172a; display:block; margin-bottom: 2px;">
                                        <a href="member_performance_sheet.php?id=<?php echo $u['id']; ?>" target="_blank" style="color: #0f172a; text-decoration: none;" title="View Performance Sheet"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?> <i class="fa-solid fa-up-right-from-square" style="font-size:10px; color:#cbd5e1;"></i></a>
                                    </strong>
                                    <span style="font-size: 12px; color: #3b82f6;"><i class="fa-solid fa-envelope" style="width:14px;"></i> <?php echo htmlspecialchars($u['email']); ?></span><br>
                                    <span style="font-size: 12px; color: #64748b;"><i class="fa-solid fa-phone" style="width:14px;"></i> <?php echo htmlspecialchars($u['phone']); ?></span>
                                </div>
                            </td>
                            <td>
                                <strong style="color: var(--sa-dark);"><i class="fa-solid fa-sitemap"></i> <?php echo htmlspecialchars($u['group_name'] ?? 'Unassigned'); ?></strong><br>
                                <span style="font-size: 12px; color: #64748b;"><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($u['company_name'] ?? 'No Business Linked'); ?></span><br>
                                <span style="font-size: 11px; color: #94a3b8;"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($u['business_category_applied'] ?? 'Uncategorized'); ?></span>
                            </td>
                            <td>
                                <div style="margin-bottom: 5px;">
                                    <span class="badge-tier <?php echo $u['tier_class']; ?>"><i class="fa-solid <?php echo $u['tier_icon']; ?>"></i> <?php echo $u['tier']; ?></span>
                                    <span class="badge-tier <?php echo $att_color_class; ?> att-badge"><i class="fa-solid fa-calendar-check"></i> <?php echo $u['att_perc']; ?>%</span>
                                </div>
                                <?php if($u['system_role'] === 'SUPER_ADMIN'): ?>
                                    <span style="color: var(--sa-gold); font-weight: 800; font-size: 10px;"><i class="fa-solid fa-bolt"></i> SUPER ADMIN</span>
                                <?php elseif($u['leadership_role'] === 'Coordinator'): ?>
                                    <span style="color: #b45309; font-weight: 700; font-size: 10px;"><i class="fa-solid fa-crown"></i> COORDINATOR</span>
                                <?php else: ?>
                                    <span style="color: #64748b; font-size: 10px; text-transform: uppercase;"><?php echo htmlspecialchars($u['system_role']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label); ?></span>
                            </td>
                            <td style="text-align: right;">
                                <?php if($u['id'] !== $_SESSION['user_id'] && $u['user_status'] === 'Active'): ?>
                                    <form action="actions/impersonate.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn-action btn-login-as" title="Log in as this user"><i class="fa-solid fa-right-to-bracket"></i> Login As</button>
                                    </form>
                                <?php endif; ?>
                                <a href="edit_member.php?id=<?php echo $u['id']; ?>&amp;return_to=sa_master_directory.php" class="btn-action"><i class="fa-solid fa-pen"></i> Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.search-dropdown').forEach((el) => {
            new TomSelect(el, { create: false, sortField: { field: "text", direction: "asc" } });
        });
    });
</script>

</body>
</html>