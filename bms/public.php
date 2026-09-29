<?php
// public.php
require_once 'config/database.php';

date_default_timezone_set('Asia/Kolkata');

if (isset($_GET['action']) && $_GET['action'] === 'vcard' && isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, u.phone, u.email, b.company_name FROM users u LEFT JOIN businesses b ON u.id = b.user_id WHERE u.id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if ($user) {
        header('Content-Type: text/x-vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $user['first_name'] . '_' . $user['last_name'] . '.vcf"');
        echo "BEGIN:VCARD\r\nVERSION:3.0\r\n";
        echo "N:" . $user['last_name'] . ";" . $user['first_name'] . ";;;\r\n";
        echo "FN:" . $user['first_name'] . " " . $user['last_name'] . "\r\n";
        echo "ORG:" . $user['company_name'] . "\r\n";
        echo "TEL;TYPE=CELL:" . $user['phone'] . "\r\n";
        echo "EMAIL;TYPE=WORK:" . $user['email'] . "\r\n";
        echo "END:VCARD\r\n";
        exit;
    }
}

$profile_mode = false;
$profile_data = null;
$my_tier = "Star";
$my_tier_class = "badge-star";

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $profile_id = $_GET['id'];
    $stmtUser = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.profile_photo, u.blood_group,
               b.company_name, b.business_category_applied, b.target_audience, b.ideal_referral, b.top_products, b.social_links,
               g.group_name, g.id as group_id, gm.joining_date,
               (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'REFERRAL') as total_links_given,
               (SELECT COALESCE(SUM(amount), 0) FROM slips WHERE (initiator_member_id = u.id OR receiver_member_id = u.id) AND slip_type = 'TYFCB') as total_deal_value,
               (
                   (SELECT COUNT(*) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id AND cm.meeting_type <> 'Daily Status' AND a.attendance_status IN ('Present', 'Late')) * 1 +
                   (SELECT COALESCE(SUM(a.early_bird), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id) * 1 +
                   (SELECT COALESCE(SUM(a.status_update), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id) * 1 +
                   (SELECT COALESCE(SUM(a.best_30_sec), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id) * 1 +
                   (SELECT COALESCE(SUM(a.presentation_8_min), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id) * 5 +
                   (SELECT COALESCE(SUM(a.som), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id) * 10 +
                   (SELECT COALESCE(SUM(a.mtp), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id) * 25 +
                   (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = '121') * 1 +
                   (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'REFERRAL' AND referral_type = 'INSIDE') * 2 +
                   (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'REFERRAL' AND referral_type = 'OUTSIDE') * 4 +
                   (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND chapter_id = gm.group_id AND status = 'Joined' AND attended = 1) * 25
               ) as total_points
        FROM users u
        LEFT JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
        LEFT JOIN groups g ON gm.group_id = g.id
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE u.id = ?
    ");
    $stmtUser->execute([$profile_id]);
    $profile_data = $stmtUser->fetch();
    
    if ($profile_data) {
        $profile_mode = true;
        
        $tenure_text = "New Member";
        if (!empty($profile_data['joining_date'])) {
            $join_dt = new DateTime($profile_data['joining_date']);
            $today = new DateTime();
            $years = $today->diff($join_dt)->y;
            if ($years >= 1) { $tenure_text = $years . " Year Member"; }
        }
        
        if ($profile_data['total_points'] > 500) {
            $my_tier = "Super Star";
            $my_tier_class = "badge-super-star";
        } elseif ($profile_data['total_points'] > 150) {
            $my_tier = "Rising Star";
            $my_tier_class = "badge-rising-star";
        }
    }
}

$public_members = [];
$filtered_members = [];
$has_searched = false;

if (!$profile_mode) {
    $categories = $pdo->query("SELECT category_name FROM business_categories ORDER BY category_name ASC")->fetchAll();
    $chapters = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();

    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.profile_photo,
               b.company_name, b.business_category_applied,
               g.id as group_id, g.group_name,
               (
                   COALESCE(att.present_count, 0) * 1 +
                   COALESCE(att.early_bird_sum, 0) * 1 +
                   COALESCE(att.status_update_sum, 0) * 1 +
                   COALESCE(att.best_30_sec_sum, 0) * 1 +
                   COALESCE(att.presentation_8_min_sum, 0) * 5 +
                   COALESCE(att.som_sum, 0) * 10 +
                   COALESCE(att.mtp_sum, 0) * 25 +
                   COALESCE(slips.count_121, 0) * 1 +
                   COALESCE(slips.count_ref_inside, 0) * 2 +
                   COALESCE(slips.count_ref_outside, 0) * 4 +
                   COALESCE(vis.vis_joined, 0) * 25
               ) as total_points
        FROM users u
        JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
        JOIN groups g ON gm.group_id = g.id
        LEFT JOIN businesses b ON u.id = b.user_id
        LEFT JOIN (
            SELECT a.user_id, cm.group_id,
                   SUM(CASE WHEN cm.meeting_type <> 'Daily Status' AND a.attendance_status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present_count,
                   SUM(a.early_bird) as early_bird_sum,
                   SUM(a.status_update) as status_update_sum,
                   SUM(a.best_30_sec) as best_30_sec_sum,
                   SUM(a.presentation_8_min) as presentation_8_min_sum,
                   SUM(a.som) as som_sum,
                   SUM(a.mtp) as mtp_sum
            FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id
            GROUP BY a.user_id, cm.group_id
        ) att ON u.id = att.user_id AND gm.group_id = att.group_id
        LEFT JOIN (
            SELECT initiator_member_id,
                   SUM(CASE WHEN slip_type = '121' THEN 1 ELSE 0 END) as count_121,
                   SUM(CASE WHEN slip_type = 'REFERRAL' AND referral_type = 'INSIDE' THEN 1 ELSE 0 END) as count_ref_inside,
                   SUM(CASE WHEN slip_type = 'REFERRAL' AND referral_type = 'OUTSIDE' THEN 1 ELSE 0 END) as count_ref_outside
            FROM slips GROUP BY initiator_member_id
        ) slips ON u.id = slips.initiator_member_id
        LEFT JOIN (
            SELECT invited_by, chapter_id, COUNT(*) as vis_joined
            FROM visitors WHERE status = 'Joined' AND attended = 1 GROUP BY invited_by, chapter_id
        ) vis ON u.id = vis.invited_by AND gm.group_id = vis.chapter_id
        ORDER BY total_points DESC, u.first_name ASC
    ";
    $stmtDir = $pdo->query($sql);
    $raw_members = $stmtDir->fetchAll();

    $chapter_grouping = [];
    foreach ($raw_members as $m) { $chapter_grouping[$m['group_name']][] = $m; }

    foreach ($chapter_grouping as $group_name => $grp_members) {
        $total_in_chapter = count($grp_members);
        $rank = 1;
        foreach ($grp_members as $m) {
            if ($rank <= 3 && $m['total_points'] > 0) { 
                $m['tier'] = 'Super Star'; $m['tier_class'] = 'badge-super-star'; 
            } elseif ($rank > ($total_in_chapter - 10)) { 
                $m['tier'] = 'Star'; $m['tier_class'] = 'badge-star'; 
            } else { 
                $m['tier'] = 'Rising Star'; $m['tier_class'] = 'badge-rising-star'; 
            }
            $public_members[] = $m;
            $rank++;
        }
    }
    usort($public_members, function($a, $b) { return $b['total_points'] <=> $a['total_points']; });

    $search_keyword = strtolower(trim($_GET['keyword'] ?? ''));
    $search_name = strtolower(trim($_GET['name'] ?? ''));
    $search_company = strtolower(trim($_GET['company'] ?? ''));
    $search_category = $_GET['category'] ?? '';
    $search_chapter = $_GET['search_chapter'] ?? '';

    if (!empty($search_keyword) || !empty($search_name) || !empty($search_company) || !empty($search_category) || !empty($search_chapter)) {
        $has_searched = true;
        foreach($public_members as $m) {
            $match = true;
            if (!empty($search_keyword)) {
                $combined = strtolower($m['first_name'].' '.$m['last_name'].' '.$m['company_name'].' '.$m['business_category_applied'].' '.$m['group_name']);
                if (strpos($combined, $search_keyword) === false) $match = false;
            }
            if (!empty($search_name)) {
                $fullName = strtolower($m['first_name'].' '.$m['last_name']);
                if (strpos($fullName, $search_name) === false) $match = false;
            }
            if (!empty($search_company) && strpos(strtolower($m['company_name']), $search_company) === false) $match = false;
            if (!empty($search_category) && $m['business_category_applied'] !== $search_category) $match = false;
            if (!empty($search_chapter) && $m['group_id'] != $search_chapter) $match = false;

            if ($match) $filtered_members[] = $m;
        }
    }
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $profile_mode ? htmlspecialchars($profile_data['first_name'] . ' - Digital Card') : 'WE KONNECTS Public Directory'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; }
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; margin: 0; color: var(--dark-blue); }
        
        .dir-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .dir-header { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 40px 20px 70px 20px; border-radius: 0 0 24px 24px; color: white; text-align: center; margin-bottom: -50px;}
        
        .search-form-card { background: white; border-radius: 16px; padding: 25px; margin-bottom: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; position: relative; z-index: 10;}
        .form-group { margin-bottom: 15px; }
        .form-input { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; background: #f8fafc; transition: 0.3s;}
        .form-input:focus { outline: none; border-color: var(--primary-orange); background: white; }
        
        .two-col { display: flex; gap: 10px; }
        @media (max-width: 600px) { .two-col { flex-direction: column; gap: 0; } }

        .btn-search { width: 100%; background: #64748b; color: white; border: none; padding: 15px; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.3s; margin-top: 10px;}
        .btn-search:hover { background: var(--dark-blue); }

        .empty-state { text-align: center; padding: 40px 20px; color: #64748b; background: white; border-radius: 16px; border: 1px dashed #cbd5e1; margin-top: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        
        .pub-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; text-decoration: none; color: inherit; transition: 0.2s;}
        .pub-card:hover { transform: translateY(-3px); border-color: var(--primary-orange); box-shadow: 0 8px 15px rgba(255,107,0,0.1);}
        .pub-photo { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-orange); flex-shrink: 0;}
        .pub-info { flex-grow: 1; }
        .pub-info h3 { margin: 0 0 5px 0; font-size: 16px; }
        .pub-info p { margin: 0; font-size: 12px; color: #64748b; }
        
        .pub-badge { display: inline-block; padding: 3px 8px; background: #f8fafc; color: #475569; border-radius: 6px; font-size: 10px; font-weight: 700; margin-top: 5px; border: 1px solid #e2e8f0;}
        .badge-super-star { background: #fffbeb; color: #d97706; border-color: #fcd34d; }
        .badge-rising-star { background: #f0fdfa; color: #0284c7; border-color: #bae6fd; }
        .badge-star { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }

        .ts-control { padding: 12px 15px !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; background: #f8fafc !important; font-family: 'Inter' !important; }
        .ts-control.focus { border-color: var(--primary-orange) !important; background: white !important; box-shadow: none !important;}

        .card-bg { background: linear-gradient(135deg, #001533, var(--dark-blue)); min-height: 100vh; padding: 20px 20px 50px 20px; display: flex; justify-content: center; align-items: flex-start;}
        .digital-card { background: white; width: 100%; max-width: 420px; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.4); margin-top: 20px; position: relative;}
        .card-top { background: url('https://images.unsplash.com/photo-1557683316-973673baf926?q=80&w=1000&auto=format&fit=crop') center/cover; height: 120px; position: relative; }
        .card-top::after { content: ''; position: absolute; top:0; left:0; width:100%; height:100%; background: linear-gradient(to bottom, rgba(0,32,74,0.2), var(--primary-orange)); opacity: 0.8;}
        
        .card-avatar { width: 110px; height: 110px; border-radius: 50%; border: 4px solid white; object-fit: cover; position: absolute; bottom: -55px; left: 50%; transform: translateX(-50%); background: white; z-index: 10; box-shadow: 0 4px 10px rgba(0,0,0,0.1);}
        
        .card-body { padding: 65px 25px 25px 25px; text-align: center; }
        .card-name { margin: 0 0 5px 0; font-size: 24px; font-weight: 800; color: var(--dark-blue); }
        .card-company { margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: var(--primary-orange); text-transform: uppercase; letter-spacing: 1px;}
        
        .card-badges { display: flex; gap: 8px; justify-content: center; margin-bottom: 20px; flex-wrap: wrap;}
        .card-category { display: inline-block; padding: 5px 12px; background: #f1f5f9; color: #475569; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #cbd5e1; }
        .tenure-badge { display: inline-block; padding: 5px 12px; background: #eff6ff; color: #2563eb; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #bfdbfe; }
        .badge-achievement { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; background: #fffbeb; color: #b45309; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #fde68a;}

        .action-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 30px; }
        .action-btn { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: var(--dark-blue); gap: 8px;}
        .action-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.1);}
        .action-btn span { font-size: 11px; font-weight: 600; }
        .btn-call { background: #10b981; } .btn-call:hover { background: #059669; transform: translateY(-3px);}
        .btn-wa { background: #25D366; } .btn-wa:hover { background: #128C7E; transform: translateY(-3px);}
        .btn-email { background: #3b82f6; } .btn-email:hover { background: #2563eb; transform: translateY(-3px);}
        .btn-save { background: var(--dark-blue); } .btn-save:hover { background: #000; transform: translateY(-3px);}

        .info-section { background: #f8fafc; border-radius: 16px; padding: 20px; text-align: left; margin-bottom: 20px; border: 1px solid #e2e8f0;}
        .info-title { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;}
        .info-text { margin: 0 0 15px 0; font-size: 14px; color: var(--dark-blue); line-height: 1.5; }
        .info-text:last-child { margin-bottom: 0; }

        .qr-section { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px dashed #cbd5e1;}
        .qr-img { width: 120px; height: 120px; border-radius: 12px; border: 1px solid #e2e8f0; padding: 5px; background: white;}

        .btn-share { width: 100%; padding: 15px; border-radius: 12px; background: var(--primary-orange); color: white; border: none; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s;}
        .btn-share:hover { background: #e65c00; box-shadow: 0 10px 20px rgba(255,107,0,0.2); }
    </style>
</head>
<body>

<?php if (!$profile_mode): ?>
    <div class="dir-header">
        <h1 style="margin:0; font-size:24px;">Find a Professional</h1>
        <p style="margin:5px 0 0 0; font-size:14px; opacity:0.8;">Search the WE KONNECTS Global Network.</p>
    </div>

    <div class="dir-container" id="directoryList">
        <div class="search-form-card">
            <form action="public.php" method="GET" id="advancedSearchForm">
                <div class="form-group"><input type="text" name="keyword" class="form-input" placeholder="Search Keywords..." value="<?php echo htmlspecialchars($search_keyword); ?>"></div>
                <div class="two-col">
                    <div class="form-group" style="flex:1;"><input type="text" name="name" class="form-input" placeholder="Member Name" value="<?php echo htmlspecialchars($search_name); ?>"></div>
                    <div class="form-group" style="flex:1;"><input type="text" name="company" class="form-input" placeholder="Company Name" value="<?php echo htmlspecialchars($search_company); ?>"></div>
                </div>
                <div class="form-group">
                    <select name="category" class="search-dropdown" placeholder="Search Category...">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['category_name']); ?>" <?php if($search_category == $c['category_name']) echo 'selected'; ?>><?php echo htmlspecialchars($c['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select name="search_chapter" class="search-dropdown" placeholder="Select Chapter (Optional)...">
                        <option value="">All Chapters</option>
                        <?php foreach($chapters as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php if($search_chapter == $c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['group_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-search" id="searchBtn">Search Members</button>
                <?php if ($has_searched): ?>
                    <a href="public.php" style="display:block; text-align:center; margin-top:15px; color:#ef4444; font-weight:600; text-decoration:none; font-size:13px;">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!$has_searched): ?>
            <div class="empty-state">
                <i class="fa-solid fa-lock" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"></i>
                <h3 style="margin: 0 0 5px 0; color: #00204a;">Directory Secured</h3>
                <p style="margin: 0; font-size: 14px;">Fill out the form above to securely find a verified professional.</p>
            </div>
        <?php elseif (empty($filtered_members)): ?>
            <div class="empty-state" style="border-color: #fca5a5;">
                <i class="fa-solid fa-magnifying-glass-minus" style="font-size: 40px; color: #fca5a5; margin-bottom: 15px;"></i>
                <h3 style="margin: 0 0 5px 0; color: #991b1b;">No Results Found</h3>
                <p style="margin: 0; font-size: 14px;">Try searching for a different category or name.</p>
            </div>
        <?php else: ?>
            <h3 style='font-size: 14px; color: #64748b; margin-bottom: 15px; text-transform: uppercase;'>Search Results</h3>
            <?php foreach ($filtered_members as $m): ?>
                <a href="public.php?id=<?php echo $m['id']; ?>" class="pub-card">
                    <img src="assets/uploads/profiles/<?php echo htmlspecialchars($m['profile_photo'] ?? 'default.png'); ?>" class="pub-photo" onerror="this.src='https://via.placeholder.com/150/00204a/ff6b00?text=<?php echo substr($m['first_name'],0,1); ?>'">
                    <div class="pub-info">
                        <h3><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></h3>
                        <p><strong><?php echo htmlspecialchars($m['company_name'] ?? 'Business'); ?></strong></p>
                        <div>
                            <span class="pub-badge <?php echo $m['tier_class']; ?>"><i class="fa-solid fa-star"></i> <?php echo $m['tier']; ?></span>
                            <span class="pub-badge"><?php echo htmlspecialchars($m['business_category_applied'] ?? 'Member'); ?></span>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="color: #cbd5e1;"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.search-dropdown').forEach((el) => {
                new TomSelect(el, { create: false, sortField: { field: "text", direction: "asc" } });
            });

            const formInputs = document.querySelectorAll('.form-input');
            const searchBtn = document.getElementById('searchBtn');

            function checkInputs() {
                let hasValue = false;
                formInputs.forEach(input => { if (input.value.trim() !== '') hasValue = true; });
                if (hasValue) { searchBtn.style.background = 'var(--primary-orange)'; }
                else { searchBtn.style.background = '#64748b'; }
            }

            formInputs.forEach(input => input.addEventListener('keyup', checkInputs));
            if(searchBtn) checkInputs(); 
        });
    </script>

<?php else: ?>
    <div class="card-bg">
        <div class="digital-card">
            <div class="card-top">
                <img src="assets/uploads/profiles/<?php echo htmlspecialchars($profile_data['profile_photo'] ?? 'default.png'); ?>" class="card-avatar" onerror="this.src='https://via.placeholder.com/150/00204a/ff6b00?text=<?php echo substr($profile_data['first_name'],0,1); ?>'">
            </div>
            
            <div class="card-body">
                <h1 class="card-name"><?php echo htmlspecialchars($profile_data['first_name'] . ' ' . $profile_data['last_name']); ?></h1>
                <p class="card-company"><?php echo htmlspecialchars($profile_data['company_name']); ?></p>
                
                <div class="card-badges">
                    <span class="card-category"><i class="fa-solid fa-tag" style="color:#94a3b8;"></i> <?php echo htmlspecialchars($profile_data['business_category_applied']); ?></span>
                    <span class="tenure-badge"><i class="fa-solid fa-calendar-check" style="color:#60a5fa;"></i> <?php echo $tenure_text; ?></span>
                    <span class="pub-badge <?php echo $my_tier_class; ?>" style="font-size: 11px; padding: 5px 12px; border-radius: 20px;"><i class="fa-solid fa-star"></i> <?php echo $my_tier; ?></span>
                    <?php if (($profile_data['total_deal_value'] ?? 0) >= 100000): ?>
                        <span class="badge-achievement"><i class="fa-solid fa-crown" style="color:#fbbf24;"></i> 100k+ Club</span>
                    <?php endif; ?>
                    <?php if (($profile_data['total_links_given'] ?? 0) >= 50): ?>
                        <span class="badge-achievement" style="color:#059669; background:#ecfdf5; border-color:#a7f3d0;"><i class="fa-solid fa-link" style="color:#10b981;"></i> Super Connector</span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($profile_data['blood_group'])): ?>
                    <div style="font-size: 11px; font-weight: 700; color: #ef4444; background: #fef2f2; display: inline-block; padding: 4px 10px; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 20px;">
                        <i class="fa-solid fa-droplet"></i> Blood Group: <?php echo htmlspecialchars($profile_data['blood_group']); ?>
                    </div>
                <?php endif; ?>

                <div class="action-grid">
                    <a href="tel:<?php echo htmlspecialchars($profile_data['phone']); ?>" class="action-btn">
                        <div class="action-icon btn-call"><i class="fa-solid fa-phone"></i></div>
                        <span>Call</span>
                    </a>
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $profile_data['phone']); ?>" target="_blank" class="action-btn">
                        <div class="action-icon btn-wa"><i class="fa-brands fa-whatsapp"></i></div>
                        <span>WhatsApp</span>
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($profile_data['email']); ?>" class="action-btn">
                        <div class="action-icon btn-email"><i class="fa-solid fa-envelope"></i></div>
                        <span>Email</span>
                    </a>
                    <a href="?action=vcard&id=<?php echo $profile_id; ?>" class="action-btn">
                        <div class="action-icon btn-save"><i class="fa-solid fa-user-plus"></i></div>
                        <span>Save Contact</span>
                    </a>
                </div>

                <div class="info-section">
                    <?php if (!empty($profile_data['social_links'])): ?>
                        <h4 class="info-title"><i class="fa-solid fa-link"></i> Connect</h4>
                        <p class="info-text"><a href="<?php echo htmlspecialchars($profile_data['social_links']); ?>" target="_blank" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Visit Social Profile</a></p>
                    <?php endif; ?>

                    <h4 class="info-title" style="margin-top: 15px;"><i class="fa-solid fa-box-open"></i> Top Products / Services</h4>
                    <p class="info-text"><?php echo nl2br(htmlspecialchars($profile_data['top_products'] ?? 'Contact me for details.')); ?></p>
                    
                    <h4 class="info-title" style="margin-top: 15px;"><i class="fa-solid fa-bullseye"></i> Ideal Referral</h4>
                    <p class="info-text"><?php echo nl2br(htmlspecialchars($profile_data['ideal_referral'] ?? 'Open to networking opportunities.')); ?></p>
                </div>

                <button onclick="shareProfile()" class="btn-share"><i class="fa-solid fa-share-nodes"></i> Share Profile</button>

                <div class="qr-section">
                    <p style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 10px;">Scan to Connect</p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($current_url); ?>" alt="QR Code" class="qr-img">
                </div>

                <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; font-weight: 500;">
                    Proud Member of <?php echo htmlspecialchars($profile_data['group_name']); ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($profile_data['first_name'] . " - " . $profile_data['company_name']); ?>',
                    text: 'Check out my digital business card on WE KONNECTS!',
                    url: window.location.href,
                }).catch(console.error);
            } else {
                navigator.clipboard.writeText(window.location.href);
                alert("Profile link copied to clipboard!");
            }
        }
    </script>
<?php endif; ?>

</body>
</html>