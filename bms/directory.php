<?php
// directory.php
session_start();
require_once 'config/database.php';

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['system_role'] === 'SUPER_ADMIN' || $_SESSION['system_role'] === 'FRANCHISE_OWNER');

try {
    $stmtUser = $pdo->prepare("SELECT group_id FROM group_members WHERE user_id = ? AND membership_status = 'Active' LIMIT 1");
    $stmtUser->execute([$user_id]);
    $user_group = $stmtUser->fetch();
    $user_group_id = $user_group ? $user_group['group_id'] : null;

    $categories = $pdo->query("SELECT category_name FROM business_categories ORDER BY category_name ASC")->fetchAll();
    $chapters = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();

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

    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.profile_photo, u.phone, u.email,
               b.company_name, b.business_category_applied,
               g.id as group_id, g.group_name,
               att.total_mtg, att.present_count,
               last_act.last_activity
        FROM users u
        JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
        JOIN groups g ON gm.group_id = g.id
        LEFT JOIN businesses b ON u.id = b.user_id
        LEFT JOIN (
            SELECT a.user_id, cm.group_id,
                   COUNT(a.id) as total_mtg,
                   SUM(CASE WHEN a.attendance_status IN ('Present', 'Late', 'Substitute') THEN 1 ELSE 0 END) as present_count
            FROM attendance a
            JOIN chapter_meetings cm ON a.meeting_id = cm.id
            WHERE cm.meeting_type <> 'Daily Status'
            GROUP BY a.user_id, cm.group_id
        ) att ON u.id = att.user_id AND gm.group_id = att.group_id
        LEFT JOIN (
            SELECT initiator_member_id, MAX(date_logged) as last_activity
            FROM slips
            WHERE slip_type IN ('REFERRAL', '121')
            GROUP BY initiator_member_id
        ) last_act ON u.id = last_act.initiator_member_id
    ";
    
    $stmt = $pdo->query($sql);
    $raw_members = $stmt->fetchAll();

    foreach ($raw_members as &$m) {
        $grp_id = $m['group_id'];
        $cycle_start = $chapter_cycle_starts[$grp_id] ?? $default_cycle_start;
        
        $m['att_perc'] = ($m['total_mtg'] > 0) ? round(($m['present_count'] / $m['total_mtg']) * 100) : 100;

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
            $m['id'], $grp_id, $cycle_start, $m['id'], $grp_id, $cycle_start,
            $m['id'], $grp_id, $cycle_start, $m['id'], $grp_id, $cycle_start,
            $m['id'], $grp_id, $cycle_start, $m['id'], $grp_id, $cycle_start,
            $m['id'], $grp_id, $cycle_start, $m['id'], $cycle_start,
            $m['id'], $cycle_start, $m['id'], $cycle_start,
            $m['id'], $grp_id, $cycle_start
        ]);
        
        $m['total_points'] = (int)$stmtPts->fetchColumn();
    }
    unset($m);

    $chapter_grouping = [];
    foreach ($raw_members as $m) { $chapter_grouping[$m['group_name']][] = $m; }

    $all_ranked_members = [];
    foreach ($chapter_grouping as $group_name => $grp_members) {
        usort($grp_members, function($a, $b) { return $b['total_points'] <=> $a['total_points']; });
        
        $rank = 1;
        foreach ($grp_members as $m) {
            if ($m['total_points'] == 0) {
                $m['tier'] = 'Star'; $m['tier_class'] = 'badge-star'; $m['tier_icon'] = 'fa-star';
            } elseif ($rank <= 3) { 
                $m['tier'] = 'Super Star'; $m['tier_class'] = 'badge-super-star'; $m['tier_icon'] = 'fa-crown';
            } else { 
                $m['tier'] = 'Rising Star'; $m['tier_class'] = 'badge-rising-star'; $m['tier_icon'] = 'fa-arrow-trend-up';
            }
            $all_ranked_members[] = $m;
            $rank++;
        }
    }
    usort($all_ranked_members, function($a, $b) { return $b['total_points'] <=> $a['total_points']; });

    $active_tab = $_GET['tab'] ?? ($is_admin ? 'global' : 'my_chapter');
    
    $search_keyword = strtolower(trim($_GET['keyword'] ?? ''));
    $search_name = strtolower(trim($_GET['name'] ?? ''));
    $search_company = strtolower(trim($_GET['company'] ?? ''));
    $search_category = $_GET['category'] ?? '';
    $search_chapter = $_GET['search_chapter'] ?? '';

    $filtered_members = [];
    $has_searched = false;

    if ($active_tab === 'my_chapter') {
        foreach($all_ranked_members as $m) { if ($m['group_id'] == $user_group_id) $filtered_members[] = $m; }
    } elseif ($active_tab === 'global') {
        if (!empty($search_keyword) || !empty($search_name) || !empty($search_company) || !empty($search_category) || !empty($search_chapter)) {
            $has_searched = true;
            foreach($all_ranked_members as $m) {
                $match = true;
                if (!empty($search_keyword)) {
                    $combined = strtolower($m['first_name'].' '.$m['last_name'].' '.$m['company_name'].' '.$m['business_category_applied'].' '.$m['group_name']);
                    if (strpos($combined, $search_keyword) === false) $match = false;
                }
                if (!empty($search_name) && strpos(strtolower($m['first_name'].' '.$m['last_name']), $search_name) === false) $match = false;
                if (!empty($search_company) && strpos(strtolower($m['company_name']), $search_company) === false) $match = false;
                if (!empty($search_category) && $m['business_category_applied'] !== $search_category) $match = false;
                if (!empty($search_chapter) && $m['group_id'] != $search_chapter) $match = false;
                if ($match) $filtered_members[] = $m;
            }
        } else {
            if ($is_admin) {
                $has_searched = true;
                $filtered_members = $all_ranked_members;
            }
        }
    }

} catch (PDOException $e) { 
    die("<div style='padding:40px; text-align:center;'><h2>System Error</h2><p style='color:red;'>" . $e->getMessage() . "</p></div>"); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Search & Directory | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <style>
        :root { --brand-blue: #00204a; --brand-orange: #ff6b00; --bg-color: #f8fafc; --card-bg: #ffffff; --border: #e2e8f0; --text-main: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-color); margin: 0; color: var(--text-main); padding-bottom: 100px;}
        
        <?php if($is_admin): ?>
            body { display: flex; }
            .main-content { flex-grow: 1; padding: 40px; margin-left: 260px; width: 100%; box-sizing: border-box; }
        <?php else: ?>
            .main-content { max-width: 600px; margin: 0 auto; padding: 15px; width: 100%; box-sizing: border-box;}
        <?php endif; ?>

        .tab-container { display: flex; background: var(--card-bg); border-radius: 12px; padding: 6px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid var(--border);}
        .tab-btn { flex: 1; text-align: center; padding: 12px; font-weight: 700; font-size: 13px; color: var(--text-muted); text-decoration: none; border-radius: 8px; transition: 0.3s; text-transform: uppercase; letter-spacing: 0.5px;}
        .tab-btn.active { background: var(--brand-blue); color: white; }

        .search-form-card { background: var(--card-bg); border-radius: 16px; padding: 25px 20px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid var(--border);}
        .form-group { margin-bottom: 15px; }
        .form-input { width: 100%; padding: 14px 15px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; background: #f8fafc; transition: 0.3s;}
        .form-input:focus { outline: none; border-color: var(--brand-orange); background: white; box-shadow: 0 0 0 3px rgba(255,107,0,0.1);}
        
        .btn-search { width: 100%; background: #64748b; color: white; border: none; padding: 16px; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.3s; margin-top: 10px;}
        .btn-search:hover { background: var(--brand-blue); }

        .member-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 18px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: transform 0.2s;}
        .member-card:hover { transform: translateY(-2px); border-color: #cbd5e1;}
        
        .photo-wrapper { position: relative; width: 65px; height: 65px; flex-shrink: 0;}
        .member-photo { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 2px solid var(--brand-orange); background: var(--brand-blue);}
        .points-pill { position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); background: var(--brand-blue); color: white; font-size: 9px; font-weight: 800; padding: 3px 8px; border-radius: 10px; border: 1px solid white; white-space: nowrap;}
        
        .traffic-light { position: absolute; top: 0; right: 0; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .light-green { background-color: #10b981; }
        .light-yellow { background-color: #f59e0b; }
        .light-red { background-color: #ef4444; }

        .member-info { flex-grow: 1; overflow: hidden;}
        .member-info h3 { margin: 0 0 4px 0; font-size: 16px; color: var(--brand-blue); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        .member-info p { margin: 0 0 6px 0; font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; margin-top: 2px; border: 1px solid var(--border);}
        .badge-super-star { background: #fffbeb; color: #d97706; border-color: #fcd34d; }
        .badge-rising-star { background: #f0fdfa; color: #0284c7; border-color: #bae6fd; }
        .badge-star { background: #f8fafc; color: #475569; border-color: #cbd5e1; }
        
        .att-badge { font-weight: 800; }
        .att-good { color: #10b981; background: #ecfdf5; border-color: #a7f3d0; }
        .att-warn { color: #f59e0b; background: #fffbeb; border-color: #fcd34d; }
        .att-poor { color: #ef4444; background: #fef2f2; border-color: #fecaca; }

        .action-btns { display: flex; flex-direction: column; gap: 8px;}
        .btn-call { background: #ecfdf5; color: #10b981; border: 1px solid #a7f3d0; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; text-decoration: none; transition: 0.2s;}
        .btn-profile { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; text-decoration: none; transition: 0.2s;}
        .btn-give-link { background: #fff7ed; color: var(--brand-orange); border: 1px solid #fed7aa; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; cursor: pointer; transition: 0.2s;}
        .btn-call:hover { background: #10b981; color: white;}
        .btn-profile:hover { background: #3b82f6; color: white;}
        .btn-give-link:hover { background: var(--brand-orange); color: white;}

        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); background: white; border-radius: 16px; border: 1px dashed #cbd5e1; margin-top: 20px; }
        
        .ts-control { padding: 14px 15px !important; border-radius: 10px !important; border: 1px solid #cbd5e1 !important; background: #f8fafc !important; font-family: 'Inter' !important; }
        .ts-control.focus { border-color: var(--brand-orange) !important; background: white !important; box-shadow: 0 0 0 3px rgba(255,107,0,0.1) !important;}
        .ts-dropdown { border-radius: 8px !important; border: 1px solid #cbd5e1 !important; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;}

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,20,45,0.9); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px; box-sizing: border-box;}
        .modal-box { background: white; border-radius: 16px; padding: 25px; width: 100%; max-width: 400px; color: #0f172a; position: relative;}
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #64748b;}
        .form-group label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: var(--brand-blue); text-transform: uppercase;}
        .glass-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; background: #f8fafc;}
    </style>
</head>
<body>

<?php if ($is_admin) include 'includes/sidebar.php'; ?>

<main class="main-content">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;"><i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>

    <?php if (!$is_admin): ?>
        <div class="tab-container">
            <a href="directory.php?tab=my_chapter" class="tab-btn <?php echo ($active_tab == 'my_chapter') ? 'active' : ''; ?>">My Chapter</a>
            <a href="directory.php?tab=global" class="tab-btn <?php echo ($active_tab == 'global') ? 'active' : ''; ?>">Global Search</a>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 25px;">
            <h1 style="margin:0; font-size: 26px; color: var(--brand-blue); font-weight: 800;">Global Directory</h1>
            <p style="margin: 5px 0 0 0; font-size: 14px; color: var(--text-muted);">Manage and search all members.</p>
        </div>
    <?php endif; ?>

    <?php if ($active_tab === 'global'): ?>
        <div class="search-form-card">
            <h3 style="margin: 0 0 15px 0; font-size: 14px; color: var(--brand-blue); text-transform: uppercase; letter-spacing: 0.5px;"><i class="fa-solid fa-magnifying-glass"></i> Find a Member</h3>
            <form action="directory.php" method="GET" id="advancedSearchForm">
                <input type="hidden" name="tab" value="global">
                <div class="form-group"><input type="text" name="keyword" class="form-input" placeholder="Search keyword..." value="<?php echo htmlspecialchars($search_keyword); ?>"></div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex:1;"><input type="text" name="name" class="form-input" placeholder="Name" value="<?php echo htmlspecialchars($search_name); ?>"></div>
                    <div class="form-group" style="flex:1;"><input type="text" name="company" class="form-input" placeholder="Company" value="<?php echo htmlspecialchars($search_company); ?>"></div>
                </div>
                <div class="form-group">
                    <select name="search_chapter" class="search-dropdown" placeholder="Select Chapter...">
                        <option value="">All Chapters</option>
                        <?php foreach($chapters as $c): ?><option value="<?php echo $c['id']; ?>" <?php if($search_chapter == $c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['group_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="category" class="search-dropdown" placeholder="Search Category...">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $c): ?><option value="<?php echo htmlspecialchars($c['category_name']); ?>" <?php if($search_category == $c['category_name']) echo 'selected'; ?>><?php echo htmlspecialchars($c['category_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-search" id="searchBtn">Search Directory</button>
                <?php if ($has_searched && (!empty($_GET['keyword']) || !empty($_GET['name']) || !empty($_GET['company']) || !empty($_GET['category']) || !empty($_GET['search_chapter']))): ?>
                    <a href="directory.php?tab=global" style="display:block; text-align:center; margin-top:15px; color:#ef4444; font-weight:600; text-decoration:none; font-size:13px;">Clear Search Filters</a>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>

    <div id="directoryList">
        <?php if ($active_tab === 'global' && !$has_searched): ?>
            <div class="empty-state">
                <i class="fa-solid fa-lock" style="font-size: 35px; color: #cbd5e1; margin-bottom: 12px;"></i>
                <h3 style="margin: 0 0 5px 0; color: var(--brand-blue);">Directory Secured</h3>
                <p style="margin: 0; font-size: 13px;">Use the filters above to search the global database.</p>
            </div>
        <?php elseif (empty($filtered_members)): ?>
            <div class="empty-state" style="border-color: #fca5a5;">
                <i class="fa-solid fa-magnifying-glass-minus" style="font-size: 35px; color: #fca5a5; margin-bottom: 12px;"></i>
                <h3 style="margin: 0 0 5px 0; color: #991b1b;">No Results Found</h3>
                <p style="margin: 0; font-size: 13px;">Try adjusting your search criteria.</p>
            </div>
        <?php else: ?>
            <?php if ($active_tab === 'global' && !$is_admin) echo "<h3 style='font-size: 13px; color: #64748b; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px;'>Search Results</h3>"; ?>
            
            <?php foreach ($filtered_members as $m): 
                $att_color_class = 'att-good';
                if ($m['att_perc'] < 85) $att_color_class = 'att-warn';
                if ($m['att_perc'] < 70) $att_color_class = 'att-poor';

                $days_since_act = isset($m['last_activity']) ? (strtotime('today') - strtotime($m['last_activity'])) / (60*60*24) : 999;
                $light_class = 'light-green';
                $light_title = 'Active';
                if ($days_since_act > 30) {
                    $light_class = 'light-red';
                    $light_title = 'Inactive (30+ Days without link/121)';
                } elseif ($days_since_act > 15) {
                    $light_class = 'light-yellow';
                    $light_title = 'Needs Activity (15+ Days)';
                }
            ?>
                <div class="member-card">
                    <div class="photo-wrapper" title="<?php echo $light_title; ?>">
                        <img src="assets/uploads/profiles/<?php echo htmlspecialchars($m['profile_photo'] ?? 'default.png'); ?>" class="member-photo" onerror="this.src='https://via.placeholder.com/150/00204a/ff6b00?text=<?php echo substr($m['first_name'],0,1); ?>'">
                        <div class="traffic-light <?php echo $light_class; ?>"></div>
                        <div class="points-pill"><?php echo $m['total_points']; ?> pts</div>
                    </div>
                    
                    <div class="member-info">
                        <h3><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></h3>
                        <p><i class="fa-solid fa-briefcase" style="color:#cbd5e1;"></i> <?php echo htmlspecialchars($m['company_name'] ?? 'Pending'); ?></p>
                        <p><i class="fa-solid fa-tag" style="color:#cbd5e1;"></i> <?php echo htmlspecialchars($m['business_category_applied'] ?? 'Uncategorized'); ?></p>
                        
                        <div style="margin-top: 6px;">
                            <span class="badge <?php echo $m['tier_class']; ?>"><i class="fa-solid <?php echo $m['tier_icon']; ?>"></i> <?php echo $m['tier']; ?></span>
                            <span class="badge <?php echo $att_color_class; ?> att-badge"><i class="fa-solid fa-calendar-check"></i> <?php echo $m['att_perc']; ?>%</span>
                            <?php if ($active_tab === 'global'): ?>
                                <span class="badge"><i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($m['group_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="action-btns">
                        <?php if ($m['id'] != $user_id): ?>
                            <button type="button" onclick="openGlobalLinkModal(<?php echo $m['id']; ?>, '<?php echo addslashes(htmlspecialchars($m['first_name'] . ' ' . $m['last_name'])); ?>')" class="btn-give-link" title="Pass Global Link"><i class="fa-solid fa-arrow-up-right-from-square"></i></button>
                        <?php endif; ?>
                        <a href="tel:<?php echo htmlspecialchars($m['phone']); ?>" class="btn-call"><i class="fa-solid fa-phone"></i></a>
                        <a href="public.php?id=<?php echo $m['id']; ?>" class="btn-profile"><i class="fa-solid fa-id-badge"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<div class="modal-overlay" id="modalGlobalLink">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeGlobalLinkModal()"></i>
        <h3 style="margin-top:0; color:var(--brand-blue);"><i class="fa-solid fa-globe" style="color:var(--brand-orange);"></i> Pass Global Link</h3>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Passing link to: <strong id="globalLinkRecipient" style="color:var(--brand-blue);"></strong></p>
        
        <form action="actions/submit_slip.php" method="POST">
            <input type="hidden" name="slip_type" value="REFERRAL">
            <input type="hidden" name="receiver_member_id" id="globalReceiverId">
            
            <div class="form-group">
                <label>Link Type</label>
                <select name="referral_type" class="glass-input" required onchange="toggleGlobalOutside(this.value)">
                    <option value="INSIDE">Inside (I am buying their service)</option>
                    <option value="OUTSIDE">Outside (Someone else is buying)</option>
                </select>
            </div>

            <div id="globalOutsideBox" style="display: none; background: rgba(59, 130, 246, 0.05); padding: 15px; border-radius: 8px; border: 1px dashed #3b82f6; margin-bottom: 15px;">
                <div class="form-group">
                    <label>Lead's Name</label>
                    <input type="text" name="outside_ref_name" id="global_out_name" class="glass-input" placeholder="e.g. John Doe">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Lead's Phone Number</label>
                    <input type="text" name="outside_ref_phone" id="global_out_phone" class="glass-input" placeholder="e.g. 9876543210">
                </div>
            </div>

            <div class="form-group">
                <label>Remarks / Notes</label>
                <textarea name="remarks" class="glass-input" style="height: 70px;" required placeholder="What is this link regarding?"></textarea>
            </div>

            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date_logged" class="glass-input" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <button type="submit" style="background:var(--brand-orange); color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:700; cursor:pointer;">Submit Global Link</button>
        </form>
    </div>
</div>

<?php if (!$is_admin) include 'includes/bottom_nav.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.search-dropdown').forEach((el) => {
            new TomSelect(el, { create: false, sortField: { field: "text", direction: "asc" } });
        });

        const formInputs = document.querySelectorAll('.form-input, .search-dropdown');
        const searchBtn = document.getElementById('searchBtn');

        function checkInputs() {
            let hasValue = false;
            formInputs.forEach(input => { if (input.value && input.value.trim() !== '') hasValue = true; });
            if (hasValue) { 
                searchBtn.style.background = 'var(--brand-orange)'; 
                searchBtn.style.color = 'white';
            } else { 
                searchBtn.style.background = '#64748b'; 
            }
        }
        formInputs.forEach(input => input.addEventListener('change', checkInputs));
        formInputs.forEach(input => input.addEventListener('keyup', checkInputs));
        if(searchBtn) checkInputs(); 
    });

    function openGlobalLinkModal(memberId, memberName) {
        document.getElementById('globalReceiverId').value = memberId;
        document.getElementById('globalLinkRecipient').innerText = memberName;
        document.getElementById('modalGlobalLink').style.display = 'flex';
    }

    function closeGlobalLinkModal() {
        document.getElementById('modalGlobalLink').style.display = 'none';
    }

    function toggleGlobalOutside(val) {
        const box = document.getElementById('globalOutsideBox');
        const nameInput = document.getElementById('global_out_name');
        const phoneInput = document.getElementById('global_out_phone');
        if (val === 'OUTSIDE') {
            box.style.display = 'block';
            nameInput.required = true;
            phoneInput.required = true;
        } else {
            box.style.display = 'none';
            nameInput.required = false;
            phoneInput.required = false;
            nameInput.value = '';
            phoneInput.value = '';
        }
    }
</script>

</body>
</html>