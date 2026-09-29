<?php
// index.php
session_start();
require_once 'config/database.php';

// Set timezone to ensure correct 9:30 AM logic
date_default_timezone_set('Asia/Kolkata');

// Strict session check
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'];
$system_role = $_SESSION['system_role'] ?? 'MEMBER';

$cur_stats = ['cur_121' => 0, 'cur_link_given' => 0, 'cur_deals_done' => 0, 'cur_link_value' => 0, 'cur_deal_value' => 0];
$hist_stats = ['hist_121' => 0, 'hist_link_given' => 0, 'hist_deals_done' => 0, 'hist_link_value' => 0, 'hist_deal_value' => 0];
$cur_vis = 0; $cur_joined = 0; $life_joined = 0;
$hist_vis = 0; $hist_joined = 0;
$mtg_absents = 0; 
$som_attended = 0; 
$subs = 0;
$head_table = []; 
$chapter_members = []; 
$assigned_visitors = [];
$chapter_stats = ['total_members' => 0, 'chapter_revenue' => 0, 'chapter_links' => 0];

try {
    $stmtUser = $pdo->prepare("
        SELECT u.profile_photo, u.last_name, u.status as user_status,
               g.id as group_id, g.group_name, g.default_venue, g.target_members, g.target_links, g.target_revenue,
               gm.renewal_date, gm.joining_date, gm.leadership_role,
               b.company_name
        FROM users u
        LEFT JOIN group_members gm ON u.id = gm.user_id AND gm.membership_status = 'Active'
        LEFT JOIN groups g ON gm.group_id = g.id 
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE u.id = ?
    ");
    $stmtUser->execute([$user_id]);
    $user_data = $stmtUser->fetch();

    $is_expired = ($user_data['renewal_date'] && strtotime($user_data['renewal_date']) < time());
    
    if (($user_data['user_status'] !== 'Active' || $is_expired) && $system_role === 'MEMBER') {
        $msg = $is_expired ? "Your membership renewal is overdue. Please contact the Head Table to process your renewal payment." : "Your account is currently inactive or under review.";
        echo "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Account Restricted</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'><link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'></head><body style='font-family:\"Inter\", sans-serif; background:#00204a; color:white; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; padding:20px; text-align:center;'>";
        echo "<div><i class='fa-solid fa-user-lock' style='font-size:60px; color:#ef4444; margin-bottom:20px;'></i>";
        echo "<h1 style='margin:0 0 10px 0;'>Account Restricted</h1>";
        echo "<p style='color:rgba(255,255,255,0.7); font-size:15px; max-width:400px; line-height:1.5; margin:0 auto 25px auto;'>{$msg}</p>";
        echo "<a href='logout.php' style='background:#ff6b00; color:white; text-decoration:none; padding:12px 25px; border-radius:8px; font-weight:700;'>Return to Login</a></div></body></html>";
        exit;
    }

    $profile_photo = $user_data['profile_photo'] ?? 'default.png';
    $group_name = $user_data['group_name'] ?? null;
    $group_id = $user_data['group_id'] ?? null;
    
    $target_members = $user_data['target_members'] ?? 50;
    $target_links = $user_data['target_links'] ?? 250;
    $target_rev = $user_data['target_revenue'] ?? 5000000;

    $tenure_text = "New Member";
    if (!empty($user_data['joining_date'])) {
        $join_dt = new DateTime($user_data['joining_date']);
        $today = new DateTime();
        $years = $today->diff($join_dt)->y;
        if ($years >= 1) { $tenure_text = $years . " Year Member"; }
    }

    $cycle_start_date = date('Y-m-d', strtotime('-15 days')); 
    $display_cycle_start = "Last Meeting";
    
    if ($group_id) {
        try {
            $stmtLastMtg = $pdo->prepare("SELECT meeting_date FROM chapter_meetings WHERE group_id = ? AND meeting_type = 'Meeting' AND meeting_date <= CURDATE() ORDER BY meeting_date DESC");
            $stmtLastMtg->execute([$group_id]);
            $past_meetings = $stmtLastMtg->fetchAll();
            
            foreach($past_meetings as $m) {
                $mtg_end_time = strtotime($m['meeting_date'] . ' 09:30:00');
                if (time() >= $mtg_end_time) {
                    $cycle_start_date = $m['meeting_date'];
                    $display_cycle_start = date('M d', strtotime($m['meeting_date']));
                    break;
                }
            }
        } catch (Exception $e) { }

        $stmtChap = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT gm.user_id) as total_members,
                (SELECT SUM(amount) FROM slips s JOIN group_members gms ON s.initiator_member_id = gms.user_id WHERE s.slip_type = 'TYFCB' AND gms.group_id = ? AND gms.membership_status = 'Active' AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) >= ?) as chapter_revenue,
                (SELECT COUNT(*) FROM slips s JOIN group_members gms ON s.initiator_member_id = gms.user_id WHERE s.slip_type = 'REFERRAL' AND gms.group_id = ? AND gms.membership_status = 'Active' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ?) as chapter_links
            FROM group_members gm
            WHERE gm.group_id = ? AND gm.membership_status = 'Active'
        ");
        $stmtChap->execute([$group_id, $cycle_start_date, $group_id, $cycle_start_date, $group_id]);
        $chapter_stats = $stmtChap->fetch();
    }

    $sys_role = $_SESSION['system_role'] ?? 'MEMBER';
    $lead_role = $user_data['leadership_role'] ?? '';
    $audience_filter = "('ALL'";
    if ($sys_role === 'FRANCHISE_OWNER') { $audience_filter .= ", 'FRANCHISE_OWNERS'"; }
    if ($lead_role === 'Coordinator') { $audience_filter .= ", 'COORDINATORS'"; }
    $audience_filter .= ")";

    $stmtBroadcasts = $pdo->query("SELECT * FROM system_broadcasts WHERE target_audience IN $audience_filter ORDER BY created_at DESC LIMIT 3");
    $announcements = $stmtBroadcasts->fetchAll();

    $my_badge = "Star ⭐"; $my_badge_class = "badge-star"; $my_points = 0; $my_rank = 0;
    if ($group_id) {
        $sqlPoints = "
            SELECT u.id as member_id,
                (
                    (SELECT COUNT(*) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND a.attendance_status IN ('Present', 'Late') AND cm.group_id = gm.group_id AND cm.meeting_type <> 'Daily Status' AND cm.meeting_date >= ?) * 1 +
                    (SELECT COALESCE(SUM(a.early_bird), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id AND cm.meeting_date >= ?) * 1 +
                    (SELECT COALESCE(SUM(a.status_update), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id AND cm.meeting_date >= ?) * 1 +
                    (SELECT COALESCE(SUM(a.best_30_sec), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id AND cm.meeting_date >= ?) * 1 +
                    (SELECT COALESCE(SUM(a.presentation_8_min), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id AND cm.meeting_date >= ?) * 5 +
                    (SELECT COALESCE(SUM(a.som), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id AND cm.meeting_date >= ?) * 10 +
                    (SELECT COALESCE(SUM(a.mtp), 0) FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = u.id AND cm.group_id = gm.group_id AND cm.meeting_date >= ?) * 25 +
                    (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = '121' AND COALESCE(NULLIF(link_given_date, ''), DATE(date_logged)) >= ?) * 1 +
                    (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'REFERRAL' AND referral_type = 'INSIDE' AND COALESCE(NULLIF(link_given_date, ''), DATE(date_logged)) >= ?) * 2 +
                    (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'REFERRAL' AND referral_type = 'OUTSIDE' AND COALESCE(NULLIF(link_given_date, ''), DATE(date_logged)) >= ?) * 4 +
                    (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND status = 'Joined' AND attended = 1 AND DATE(visit_date) >= ?) * 25
                ) as total_points
            FROM group_members gm JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = ? AND gm.membership_status = 'Active'
            ORDER BY total_points DESC, u.first_name ASC
        ";
        
        $stmtPoints = $pdo->prepare($sqlPoints); 
        $stmtPoints->execute([
            $cycle_start_date, $cycle_start_date, $cycle_start_date, $cycle_start_date, 
            $cycle_start_date, $cycle_start_date, $cycle_start_date, $cycle_start_date, 
            $cycle_start_date, $cycle_start_date, $cycle_start_date, $group_id
        ]);
        $ranked_members = $stmtPoints->fetchAll();

        $current_rank = 1;
        foreach ($ranked_members as $rm) {
            if ($rm['total_points'] == 0) {
                $tier = "Star ⭐"; 
                $tier_class = "badge-star";
            } elseif ($current_rank <= 3) {
                $tier = "Super Star 🌟🌟🌟"; 
                $tier_class = "badge-super-star";
            } else {
                $tier = "Rising Star 🌟🌟"; 
                $tier_class = "badge-rising-star";
            }

            if ($rm['member_id'] == $user_id) { 
                $my_points = $rm['total_points']; 
                $my_rank = $current_rank; 
                $my_badge = $tier; 
                $my_badge_class = $tier_class; 
            }
            $current_rank++;
        }

        try {
            $stmtRollingAtt = $pdo->prepare("
                SELECT SUM(CASE WHEN a.attendance_status LIKE '%Absent%' THEN 1 ELSE 0 END) as mtg_abs
                FROM attendance a
                JOIN chapter_meetings cm ON a.meeting_id = cm.id
                WHERE a.user_id = ? AND cm.group_id = ? AND cm.meeting_type NOT LIKE '%SOM%' AND cm.meeting_type <> 'Daily Status' AND cm.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            ");
            $stmtRollingAtt->execute([$user_id, $group_id]);
            $mtg_absents = (int)($stmtRollingAtt->fetchColumn());

            $stmtAtt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN (a.attendance_status LIKE '%Present%' OR a.attendance_status LIKE '%Late%') AND cm.meeting_type LIKE '%SOM%' THEN 1 ELSE 0 END) as som_attended,
                    SUM(CASE WHEN a.attendance_status LIKE '%Substitute%' THEN 1 ELSE 0 END) as total_subs
                FROM attendance a
                JOIN chapter_meetings cm ON a.meeting_id = cm.id
                WHERE a.user_id = ? AND cm.group_id = ?
            ");
            $stmtAtt->execute([$user_id, $group_id]);
            $att_stats = $stmtAtt->fetch(PDO::FETCH_ASSOC);
            if ($att_stats) {
                $som_attended = (int)($att_stats['som_attended'] ?? 0);
                $subs = (int)($att_stats['total_subs'] ?? 0);
            }
        } catch (Exception $e) {}
    }

    $next_meeting = null; $next_som = null;
    if ($group_id) {
        try {
            $stmtMtg = $pdo->prepare("SELECT meeting_date, venue, meeting_type FROM chapter_meetings WHERE group_id = ? AND meeting_type IN ('Meeting', 'Event') AND status = 'Scheduled' AND meeting_date >= CURDATE() ORDER BY meeting_date ASC LIMIT 1");
            $stmtMtg->execute([$group_id]); 
            $next_meeting = $stmtMtg->fetch();
            
            $stmtSom = $pdo->prepare("SELECT meeting_date, venue FROM chapter_meetings WHERE group_id = ? AND meeting_type = 'SOM' AND status = 'Scheduled' AND meeting_date >= CURDATE() ORDER BY meeting_date ASC LIMIT 1");
            $stmtSom->execute([$group_id]); 
            $next_som = $stmtSom->fetch();
        } catch (Exception $e) { }
    }

    $safe_link_date = "COALESCE(NULLIF(link_given_date, ''), NULLIF(link_given_date, '0000-00-00'), DATE(date_logged))";
    $safe_deal_date = "COALESCE(NULLIF(deal_close_date, ''), NULLIF(deal_close_date, '0000-00-00'), DATE(date_logged))";

    try {
        $stmtCurStats = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN slip_type = '121' AND initiator_member_id = :uid AND $safe_link_date >= :dt THEN 1 ELSE 0 END) as cur_121, 
                SUM(CASE WHEN slip_type = 'REFERRAL' AND initiator_member_id = :uid AND $safe_link_date >= :dt THEN 1 ELSE 0 END) as cur_link_given, 
                SUM(CASE WHEN slip_type = 'TYFCB' AND initiator_member_id = :uid AND $safe_deal_date >= :dt THEN 1 ELSE 0 END) as cur_deals_done,
                SUM(CASE WHEN slip_type = 'TYFCB' AND receiver_member_id = :uid AND $safe_deal_date >= :dt THEN amount ELSE 0 END) as cur_link_value,
                SUM(CASE WHEN slip_type = 'TYFCB' AND initiator_member_id = :uid AND $safe_deal_date >= :dt THEN amount ELSE 0 END) as cur_deal_value
            FROM slips 
            WHERE initiator_member_id = :uid OR receiver_member_id = :uid
        ");
        $stmtCurStats->execute(['dt' => $cycle_start_date, 'uid' => $user_id]);
        $cur_fetch = $stmtCurStats->fetch();
        if ($cur_fetch) $cur_stats = $cur_fetch;
    } catch (Exception $e) { }

    $stmtCurVis = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN visit_date >= ? AND attended = 1 THEN 1 ELSE 0 END) as cur_vis,
            SUM(CASE WHEN status = 'Joined' AND attended = 1 AND visit_date >= ? THEN 1 ELSE 0 END) as cur_joined,
            SUM(CASE WHEN status = 'Joined' AND attended = 1 THEN 1 ELSE 0 END) as life_joined
        FROM visitors WHERE invited_by = ?
    ");
    $stmtCurVis->execute([$cycle_start_date, $cycle_start_date, $user_id]);
    $v_fetch = $stmtCurVis->fetch();
    if ($v_fetch) {
        $cur_vis = $v_fetch['cur_vis'] ?? 0;
        $cur_joined = $v_fetch['cur_joined'] ?? 0;
        $life_joined = $v_fetch['life_joined'] ?? 0;
    }

    $hist_filter = filter_input(INPUT_GET, 'filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'life';
    
    $hist_cond_121 = "1=1"; $hist_cond_ref = "1=1"; $hist_cond_deal = "1=1"; $vis_hist_cond = "1=1"; $perf_att_cond = "1=1";
    if ($hist_filter === '3m') {
        $hist_cond_121 = "$safe_link_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $hist_cond_ref = "$safe_link_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $hist_cond_deal = "$safe_deal_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $vis_hist_cond = "visit_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $perf_att_cond = "cm.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
    } elseif ($hist_filter === '6m') {
        $hist_cond_121 = "$safe_link_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        $hist_cond_ref = "$safe_link_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        $hist_cond_deal = "$safe_deal_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        $vis_hist_cond = "visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        $perf_att_cond = "cm.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    } elseif ($hist_filter === '1y') {
        $hist_cond_121 = "$safe_link_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $hist_cond_ref = "$safe_link_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $hist_cond_deal = "$safe_deal_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $vis_hist_cond = "visit_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $perf_att_cond = "cm.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    }

    try {
        $sqlHistStats = "
            SELECT 
                SUM(CASE WHEN slip_type = '121' AND initiator_member_id = :uid AND $hist_cond_121 THEN 1 ELSE 0 END) as hist_121, 
                SUM(CASE WHEN slip_type = 'REFERRAL' AND initiator_member_id = :uid AND $hist_cond_ref THEN 1 ELSE 0 END) as hist_link_given, 
                SUM(CASE WHEN slip_type = 'TYFCB' AND initiator_member_id = :uid AND $hist_cond_deal THEN 1 ELSE 0 END) as hist_deals_done,
                SUM(CASE WHEN slip_type = 'TYFCB' AND receiver_member_id = :uid AND $hist_cond_deal THEN amount ELSE 0 END) as hist_link_value,
                SUM(CASE WHEN slip_type = 'TYFCB' AND initiator_member_id = :uid AND $hist_cond_deal THEN amount ELSE 0 END) as hist_deal_value 
            FROM slips 
            WHERE initiator_member_id = :uid OR receiver_member_id = :uid
        ";
        $stmtHistStats = $pdo->prepare($sqlHistStats); 
        $stmtHistStats->execute(['uid' => $user_id]);
        $h_fetch = $stmtHistStats->fetch();
        if ($h_fetch) $hist_stats = $h_fetch;

        $stmtPerf = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN cm.meeting_type NOT LIKE '%SOM%' AND cm.meeting_type <> 'Daily Status' AND (a.attendance_status LIKE '%Present%' OR a.attendance_status LIKE '%Late%') THEN 1 ELSE 0 END) as mtg_present,
                SUM(CASE WHEN cm.meeting_type NOT LIKE '%SOM%' AND cm.meeting_type <> 'Daily Status' AND a.attendance_status LIKE '%Absent%' THEN 1 ELSE 0 END) as mtg_absent,
                SUM(CASE WHEN cm.meeting_type LIKE '%SOM%' AND (a.attendance_status LIKE '%Present%' OR a.attendance_status LIKE '%Late%') THEN 1 ELSE 0 END) as som_present,
                SUM(CASE WHEN cm.meeting_type LIKE '%SOM%' AND a.attendance_status LIKE '%Absent%' THEN 1 ELSE 0 END) as som_absent
            FROM attendance a
            JOIN chapter_meetings cm ON a.meeting_id = cm.id
            WHERE a.user_id = ? AND cm.group_id = ? AND $perf_att_cond
        ");
        $stmtPerf->execute([$user_id, $group_id]);
        $perf_stats = $stmtPerf->fetch();
    } catch (Exception $e) { }

    $stmtHistVis = $pdo->prepare("SELECT COUNT(*) as hist_vis, SUM(CASE WHEN status = 'Joined' THEN 1 ELSE 0 END) as hist_joined FROM visitors WHERE invited_by = ? AND attended = 1 AND $vis_hist_cond");
    $stmtHistVis->execute([$user_id]);
    $hv_fetch = $stmtHistVis->fetch();
    if ($hv_fetch) {
        $hist_vis = $hv_fetch['hist_vis'] ?? 0;
        $hist_joined = $hv_fetch['hist_joined'] ?? 0;
    }

    if ($group_id) {
        $stmtHT = $pdo->prepare("SELECT u.first_name, u.last_name, u.profile_photo FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active'");
        $stmtHT->execute([$group_id]); $head_table = $stmtHT->fetchAll();

        // ONLY LOAD LOCAL CHAPTER MEMBERS FOR NORMAL MODALS
        $stmtMembers = $pdo->prepare("SELECT u.id as user_id, u.first_name, u.last_name FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? AND u.id != ? AND gm.membership_status = 'Active' ORDER BY u.first_name ASC");
        $stmtMembers->execute([$group_id, $user_id]); $chapter_members = $stmtMembers->fetchAll();

        $stmtMyVis = $pdo->prepare("SELECT id, visitor_name, company_name, phone, remarks FROM visitors WHERE assigned_to = ? AND status != 'Joined'");
        $stmtMyVis->execute([$user_id]); $assigned_visitors = $stmtMyVis->fetchAll();
    }

    // =========================================
    // NEW: SMART NOTIFICATION ENGINE
    // =========================================
    $notifications = [];

    // 1. Links Received
    $stmtN1 = $pdo->prepare("SELECT u.first_name, u.last_name, s.date_logged, s.referral_type FROM slips s JOIN users u ON s.initiator_member_id = u.id WHERE s.receiver_member_id = ? AND s.slip_type = 'REFERRAL' ORDER BY s.date_logged DESC LIMIT 5");
    $stmtN1->execute([$user_id]);
    foreach($stmtN1->fetchAll() as $row) {
        $notifications[] = [
            'date' => strtotime($row['date_logged']),
            'title' => 'New Link Received',
            'desc' => "{$row['first_name']} {$row['last_name']} passed you an {$row['referral_type']} referral.",
            'icon' => 'fa-link', 'color' => '#3b82f6'
        ];
    }

    // 2. Deals Received
    $stmtN2 = $pdo->prepare("SELECT u.first_name, u.last_name, s.date_logged, s.amount FROM slips s JOIN users u ON s.initiator_member_id = u.id WHERE s.receiver_member_id = ? AND s.slip_type = 'TYFCB' ORDER BY s.date_logged DESC LIMIT 5");
    $stmtN2->execute([$user_id]);
    foreach($stmtN2->fetchAll() as $row) {
        $notifications[] = [
            'date' => strtotime($row['date_logged']),
            'title' => 'Deal Closed!',
            'desc' => "{$row['first_name']} {$row['last_name']} thanked you for ₹" . number_format($row['amount']) . " in business.",
            'icon' => 'fa-sack-dollar', 'color' => '#10b981'
        ];
    }

    // 3. Visitors
    $stmtN3 = $pdo->prepare("SELECT visitor_name, visit_date, status, attended FROM visitors WHERE invited_by = ? ORDER BY visit_date DESC LIMIT 5");
    $stmtN3->execute([$user_id]);
    foreach($stmtN3->fetchAll() as $row) {
        $desc = "Visitor {$row['visitor_name']} is registered for " . date('M d', strtotime($row['visit_date'])) . ".";
        $icon = 'fa-user-plus'; $color = '#8b5cf6';
        
        if ($row['status'] == 'Joined') {
            $desc = "Your visitor {$row['visitor_name']} has JOINED the chapter!";
            $color = '#10b981'; $icon = 'fa-user-check';
        } elseif ($row['attended'] == 1) {
            $desc = "Your visitor {$row['visitor_name']} attended the meeting!";
            $color = '#f59e0b'; $icon = 'fa-user-check';
        } elseif (strtotime($row['visit_date']) < time() && $row['attended'] == 0) {
            $desc = "Your visitor {$row['visitor_name']} missed their scheduled meeting.";
            $color = '#ef4444'; $icon = 'fa-user-xmark';
        }
        $notifications[] = [
            'date' => strtotime($row['visit_date']),
            'title' => 'Visitor Update',
            'desc' => $desc, 'icon' => $icon, 'color' => $color
        ];
    }

    // 4. Attendance
    $stmtN4 = $pdo->prepare("SELECT cm.meeting_date, a.attendance_status FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? ORDER BY cm.meeting_date DESC LIMIT 3");
    $stmtN4->execute([$user_id]);
    foreach($stmtN4->fetchAll() as $row) {
        $status = $row['attendance_status'];
        $color = '#cbd5e1'; $icon = 'fa-calendar-check';
        if ($status == 'Present') { $color = '#10b981'; $icon = 'fa-check'; }
        if ($status == 'Absent') { $color = '#ef4444'; $icon = 'fa-xmark'; }
        if ($status == 'Late') { $color = '#f59e0b'; $icon = 'fa-clock'; }
        if ($status == 'Substitute') { $color = '#3b82f6'; $icon = 'fa-user-astronaut'; }
        $notifications[] = [
            'date' => strtotime($row['meeting_date']),
            'title' => 'Attendance Logged',
            'desc' => "You were marked as {$status} for the meeting on " . date('M d', strtotime($row['meeting_date'])) . ".",
            'icon' => $icon, 'color' => $color
        ];
    }

    // 5. Coordinator Tasks
    if ($lead_role === 'Coordinator' && $group_id) {
        $stmtN5 = $pdo->prepare("SELECT meeting_date, meeting_type FROM chapter_meetings WHERE group_id = ? AND meeting_date <= CURDATE() AND status != 'Completed' ORDER BY meeting_date ASC LIMIT 5");
        $stmtN5->execute([$group_id]);
        foreach($stmtN5->fetchAll() as $row) {
            $notifications[] = [
                'date' => time() + 100, // Put it at the top
                'title' => 'Action Required (Coordinator)',
                'desc' => "The {$row['meeting_type']} on " . date('M d', strtotime($row['meeting_date'])) . " needs to be marked as 'Completed' to lock attendance.",
                'icon' => 'fa-triangle-exclamation', 'color' => '#ef4444'
            ];
        }
    }

    // Sort notifications dynamically
    usort($notifications, function($a, $b) { return $b['date'] <=> $a['date']; });
    $notifications = array_slice($notifications, 0, 15);

} catch (Exception $e) { 
    die("<div style='padding:20px; font-family:sans-serif;'><h3>System Error</h3><p>Could not load dashboard data.</p></div>"); 
}

// FIX: Hardcoded to the root of the official subdomain
$base_invite_link = "https://official.wekonnects.com/invite.php?ref=" . $user_id;

function format_money($amount) {
    if (!$amount) return "0";
    if ($amount >= 10000000) return number_format($amount / 10000000, 2) . ' Cr';
    if ($amount >= 100000) return number_format($amount / 100000, 2) . ' L';
    if ($amount >= 1000) return number_format($amount / 1000, 1) . ' k';
    return number_format($amount);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container { max-width: 600px; margin: 0 auto; padding: 20px; padding-bottom: 100px; font-family: 'Inter', sans-serif;}
        
        /* NOTIFICATION & HEADER CSS */
        .top-nav-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .notification-bell { position: relative; cursor: pointer; background: rgba(255,255,255,0.1); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; transition: 0.3s; border: 1px solid rgba(255,255,255,0.2);}
        .notification-bell:hover { background: rgba(255,255,255,0.2); }
        .badge-dot { position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; box-shadow: 0 0 0 2px var(--dark-blue);}

        .glass-header { display: flex; align-items: flex-start; justify-content: space-between; padding: 20px; margin-bottom: 20px; border-radius: 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(16px); flex-wrap: wrap;}
        .header-left { display: flex; gap: 15px; align-items: center; width: 100%;}
        .profile-avatar-img { width: 65px; height: 65px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-orange); background: var(--dark-blue); flex-shrink: 0; }
        .user-greeting { flex-grow: 1; }
        .user-greeting h1 { margin: 0 0 4px 0; font-size: 18px; font-weight: 700; color: var(--white); }
        .user-greeting p { margin: 0; font-size: 12px; color: rgba(255,255,255,0.7); line-height: 1.4; }
        .tier-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; margin-top: 5px; border: 1px solid rgba(255,255,255,0.2); }
        .badge-tenure { background: #3b82f6; color: white; border-color: #60a5fa; }
        .badge-super-star { background: linear-gradient(135deg, #fbbf24, #b45309); color: white; border-color: #fcd34d; }
        .badge-rising-star { background: linear-gradient(135deg, #38bdf8, #0284c7); color: white; border-color: #7dd3fc; }
        .badge-star { background: linear-gradient(135deg, #9ca3af, #4b5563); color: white; border-color: #d1d5db; }
        
        .ht-stack { display: flex; flex-direction: column; align-items: flex-end; margin-left: auto;}
        .ht-title { font-size: 9px; color: rgba(255,255,255,0.5); font-weight: 700; letter-spacing: 0.5px; margin-bottom: 5px; text-transform: uppercase; }
        .ht-avatars { display: flex; justify-content: flex-end; }
        .ht-avatars img { width: 32px; height: 32px; border-radius: 50%; border: 2px solid var(--dark-blue); margin-left: -10px; object-fit: cover; background: #fbbf24; }
        
        .attendance-inline-bar { margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; text-align: center; width: 100%; gap: 5px;}
        .attendance-stat { flex: 1; border-right: 1px solid rgba(255,255,255,0.1); }
        .attendance-stat:last-child { border-right: none; }
        .stat-label { font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px; }
        .stat-value { font-size: 14px; font-weight: 700; color: white; }
        .text-danger { color: #ef4444 !important; }

        .events-grid { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .meeting-card { flex: 1; min-width: 220px; background: rgba(0, 32, 74, 0.4); border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: 0;}
        .meeting-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;}
        .meeting-details { flex-grow: 1; }
        .meeting-details h4 { margin: 0 0 3px 0; font-size: 14px; color: var(--white); }
        .meeting-details p { margin: 0; font-size: 11px; color: rgba(255,255,255,0.6); }
        .invite-icon-btn { background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4); color: #93c5fd; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; flex-shrink: 0; font-size: 14px; }
        .invite-icon-btn:hover { background: #3b82f6; color: white; border-color: #3b82f6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.5); }

        .modern-section { margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px;}
        .section-title { font-size: 12px; color: rgba(255,255,255,0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin: 0;}
        .section-subtitle { font-size: 11px; color: var(--primary-orange); margin: 0;}
        
        .metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .metric-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 15px; display: flex; flex-direction: column; justify-content: space-between; transition: 0.3s;}
        .metric-card:hover { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.2); }
        .metric-top { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7); margin-bottom: 10px;}
        .metric-top i { color: var(--primary-orange); font-size: 14px; width: 16px; text-align: center;}
        .metric-bottom { display: flex; justify-content: space-between; align-items: flex-end; }
        .metric-val { font-size: 20px; font-weight: 800; color: var(--white); font-family: 'Inter', sans-serif;}
        .btn-plus-modern { background: rgba(255, 107, 0, 0.1); border: 1px solid rgba(255, 107, 0, 0.3); color: var(--primary-orange); width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; font-size: 12px;}
        .btn-plus-modern:hover { background: var(--primary-orange); color: white; }

        .progress-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 15px; margin-bottom: 15px;}
        .progress-header { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.8); margin-bottom: 8px;}
        .progress-bar-bg { width: 100%; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;}
        .progress-bar-fill { height: 100%; background: var(--primary-orange); border-radius: 4px; transition: width 0.5s ease-in-out;}

        /* GAMIFICATION BADGES */
        .gamified-badges { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;}
        .badge-achievement { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(217,119,6,0.2)); border: 1px solid rgba(245,158,11,0.3); border-radius: 20px; font-size: 11px; font-weight: 700; color: #fbbf24;}

        .history-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; overflow: hidden; margin-bottom: 20px;}
        .filter-tabs { display: flex; background: rgba(0,0,0,0.2); }
        .filter-tab { flex: 1; text-align: center; font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.5); text-decoration: none; text-transform: uppercase; padding: 12px 0; border-bottom: 2px solid transparent; transition: 0.3s;}
        .filter-tab:hover { color: var(--white); }
        .filter-tab.active { color: var(--primary-orange); border-bottom-color: var(--primary-orange); background: rgba(255, 107, 0, 0.05); }

        .perf-table { width: 100%; border-collapse: collapse; }
        .perf-table th { text-align: left; padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); font-size: 10px; text-transform: uppercase; background: rgba(0,0,0,0.1);}
        .perf-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: white; font-size: 13px; font-weight: 500; }
        .perf-table tr:last-child td { border-bottom: none; }
        .val-highlight { color: var(--primary-orange); font-weight: 700; font-family: 'Inter', sans-serif;}
        
        .alert-box { background: rgba(239, 68, 68, 0.15); color: #fca5a5; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.3); line-height: 1.5; font-size: 13px; display: flex; align-items: flex-start; gap: 15px;}
        .alert-icon { font-size: 20px; color: #ef4444; margin-top: 2px;}

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,20,45,0.9); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px; box-sizing: border-box; overflow-y: auto;}
        .modal-box { background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(25px); padding: 30px; border-radius: 16px; width: 100%; max-width: 400px; color: var(--white); position: relative; max-height: 90vh; overflow-y: auto;}
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: rgba(255,255,255,0.6); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: rgba(255,255,255,0.9); }
        .glass-input { width: 100%; padding: 12px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: var(--white); font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php if (!empty($_SESSION['super_admin_id'])): ?>
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:12px 14px; margin-bottom:16px; background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.45); border-radius:10px; color:#fde68a; font-size:13px;">
            <span><i class="fa-solid fa-user-shield"></i> You are viewing this account as an administrator.</span>
            <a href="logout.php" style="color:#0f172a; background:#fbbf24; text-decoration:none; padding:8px 12px; border-radius:6px; font-weight:700;">Return to Super Admin</a>
        </div>
    <?php endif; ?>

    <!-- TOP NAV WITH NOTIFICATIONS -->
    <div class="top-nav-bar">
        <h1 style="color: white; font-size: 22px; margin: 0; font-weight: 800; letter-spacing: 0.5px;">WE <span style="color: var(--primary-orange);">KONNECTS</span></h1>
        <div class="notification-bell" onclick="openModal('modalNotifications')">
            <i class="fa-solid fa-bell"></i>
            <?php if(count($notifications) > 0): ?><span class="badge-dot"></span><?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;"><i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <?php if (!empty($announcements)): ?>
    <div style="margin-bottom: 25px;">
        <h3 style="color: white; font-size: 14px; margin: 0 0 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px; text-transform: uppercase;">
            <i class="fa-solid fa-bullhorn" style="color: #ff6b00;"></i> Official Announcements
        </h3>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($announcements as $a): ?>
                <div style="background: rgba(251, 191, 36, 0.05); border-left: 3px solid #fbbf24; border-radius: 8px; padding: 12px; position: relative;">
                    <h4 style="margin: 0 0 5px 0; color: #fbbf24; font-size: 14px; padding-right: 15px;">
                        <?php echo htmlspecialchars($a['title']); ?>
                    </h4>
                    <p style="margin: 0; font-size: 12px; color: #cbd5e1; line-height: 1.5;">
                        <?php echo nl2br(htmlspecialchars($a['message'])); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="glass-header">
        <div class="header-left">
            <img src="assets/uploads/profiles/<?php echo htmlspecialchars($profile_photo); ?>" class="profile-avatar-img">
            <div class="user-greeting">
                <h1><?php echo htmlspecialchars($first_name . ' ' . ($user_data['last_name'] ?? '')); ?></h1>
                <p><?php echo htmlspecialchars($user_data['company_name'] ?? 'Profile Pending'); ?><br>
                   <span style="color: rgba(255,255,255,0.5);"><?php echo htmlspecialchars($group_name ?? 'Awaiting Assignment'); ?></span>
                </p>
                <div>
                    <span class="tier-badge <?php echo $my_badge_class; ?>" style="margin-right: 5px;"><?php echo $my_badge; ?> &nbsp;|&nbsp; Rank #<?php echo $my_rank; ?></span>
                    <span style="font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 600; text-transform: uppercase;"><?php echo $my_points; ?> Cycle Pts</span>
                </div>
            </div>
            
            <?php if(!empty($head_table)): ?>
            <div class="ht-stack">
                <span class="ht-title">Head Table</span>
                <div class="ht-avatars">
                    <?php foreach($head_table as $ht): ?>
                        <img src="assets/uploads/profiles/<?php echo htmlspecialchars($ht['profile_photo'] ?? 'default.png'); ?>" title="<?php echo htmlspecialchars($ht['first_name']); ?>" onerror="this.src='assets/uploads/profiles/default.png'">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- GAMIFICATION BADGES DISLPAY -->
        <div class="gamified-badges">
            <span class="badge-achievement"><i class="fa-solid fa-calendar-check" style="color:#60a5fa;"></i> <?php echo $tenure_text; ?></span>
            
            <!-- FIXED 100k+ CLUB LOGIC (Only checks TYFCB Given/Generated) -->
            <?php if ($hist_stats['hist_deal_value'] >= 100000): ?>
                <span class="badge-achievement"><i class="fa-solid fa-crown" style="color:#fbbf24;"></i> 100k+ Club</span>
            <?php endif; ?>
            
            <?php if ($hist_stats['hist_link_given'] >= 50): ?>
                <span class="badge-achievement" style="border-color: #34d399; color: #34d399; background: rgba(16,185,129,0.1);"><i class="fa-solid fa-link"></i> Super Connector</span>
            <?php endif; ?>
        </div>

        <div class="attendance-inline-bar">
            <div class="attendance-stat">
                <span class="stat-label">Renewal Due</span>
                <span class="stat-value"><?php echo $user_data['renewal_date'] ? date('M d, Y', strtotime($user_data['renewal_date'])) : 'Not Set'; ?></span>
            </div>
            <div class="attendance-stat">
                <span class="stat-label">Absents (6M)</span>
                <span class="stat-value <?php echo ($mtg_absents >= 3) ? 'text-danger' : ''; ?>"><?php echo $mtg_absents; ?></span>
            </div>
            <div class="attendance-stat">
                <span class="stat-label">SOM Attended</span>
                <span class="stat-value" style="color: #10b981;"><?php echo $som_attended; ?></span>
            </div>
            <div class="attendance-stat">
                <span class="stat-label">Subs Sent</span>
                <span class="stat-value"><?php echo $subs; ?></span>
            </div>
        </div>
    </div>

    <?php if ($group_id): 
        $mem_perc = min(100, (($chapter_stats['total_members'] ?? 0) / max(1, $target_members)) * 100);
        $links_perc = min(100, (($chapter_stats['chapter_links'] ?? 0) / max(1, $target_links)) * 100);
        $rev_perc = min(100, (($chapter_stats['chapter_revenue'] ?? 0) / max(1, $target_rev)) * 100);
    ?>
    <div class="modern-section">
        <div class="section-header">
            <h3 class="section-title">Chapter Targets (This Cycle)</h3>
        </div>
        <div class="progress-box">
            <div class="progress-header"><span>Active Members</span><span><?php echo ($chapter_stats['total_members'] ?? 0); ?> / <?php echo $target_members; ?></span></div>
            <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?php echo $mem_perc; ?>%; background: #3b82f6;"></div></div>
        </div>
        <div class="progress-box">
            <div class="progress-header"><span>Links Passed</span><span><?php echo ($chapter_stats['chapter_links'] ?? 0); ?> / <?php echo $target_links; ?></span></div>
            <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?php echo $links_perc; ?>%; background: #10b981;"></div></div>
        </div>
        <div class="progress-box">
            <div class="progress-header"><span>Revenue Generated (TYFCB)</span><span><?php echo format_money($chapter_stats['chapter_revenue'] ?? 0); ?> / <?php echo format_money($target_rev); ?></span></div>
            <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?php echo $rev_perc; ?>%; background: var(--primary-orange);"></div></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mtg_absents >= 3): ?>
        <div class="alert-box">
            <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
            <div>
                <strong style="color: #ef4444; font-size: 14px; display:block; margin-bottom: 3px;">Warning: Absence Limit Reached</strong>
                You currently have <strong><?php echo $mtg_absents; ?> absences</strong> within your rolling 6-month period. Please improve attendance to avoid membership suspension. 
                <?php if ($mtg_absents >= 6) echo "<br><br><strong>ACTION REQUIRED: Contact Head Table immediately.</strong>"; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($assigned_visitors)): ?>
        <div class="modern-section" style="background: rgba(59, 130, 246, 0.05); padding: 15px; border-radius: 12px; border: 1px solid rgba(59, 130, 246, 0.2);">
            <div class="section-header">
                <h3 class="section-title" style="color: #60a5fa;"><i class="fa-solid fa-bell"></i> Assigned Follow-Ups</h3>
            </div>
            <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($assigned_visitors as $av): ?>
                <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <p style="margin: 0 0 5px 0; font-weight: 600; color: var(--white);"><?php echo htmlspecialchars($av['visitor_name']); ?> <span style="font-size: 12px; font-weight: 400; color: rgba(255,255,255,0.6);"> (<?php echo htmlspecialchars($av['company_name']); ?>)</span></p>
                    <p style="margin: 0 0 10px 0; font-size: 13px; color: #38bdf8;"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($av['phone']); ?></p>
                    <form action="actions/update_remarks.php" method="POST" style="display: flex; gap: 10px;">
                        <input type="hidden" name="visitor_id" value="<?php echo $av['id']; ?>">
                        <input type="text" name="remarks" class="glass-input" style="flex: 1; padding: 10px; font-size: 12px;" placeholder="Add remarks..." value="<?php echo htmlspecialchars($av['remarks'] ?? ''); ?>">
                        <button type="submit" style="background:var(--primary-orange); color:white; border:none; padding:10px 15px; border-radius:6px; font-size:12px; font-weight:bold; cursor:pointer;">Save</button>
                    </form>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($group_id && ($next_meeting || $next_som)): ?>
    <div class="events-grid">
        <?php if ($next_meeting): $m_color = ($next_meeting['meeting_type'] == 'Event') ? '#a855f7' : 'var(--primary-orange)'; ?>
        <div class="meeting-card" style="border-left: 4px solid <?php echo $m_color; ?>;">
            <div class="meeting-icon" style="color: <?php echo $m_color; ?>;"><i class="fa-solid fa-users"></i></div>
            <div class="meeting-details">
                <h4>Next <?php echo htmlspecialchars($next_meeting['meeting_type']); ?></h4>
                <p><?php echo date('M d', strtotime($next_meeting['meeting_date'])); ?> • <?php echo htmlspecialchars($next_meeting['venue'] ?? $user_data['default_venue']); ?></p>
            </div>
            <button onclick="copyInviteLink('<?php echo $base_invite_link . "&date=" . date('Y-m-d', strtotime($next_meeting['meeting_date'])); ?>', this)" class="invite-icon-btn" title="Copy Invite Link"><i class="fa-solid fa-link"></i></button>
        </div>
        <?php endif; ?>

        <?php if ($next_som): ?>
        <div class="meeting-card" style="border-left: 4px solid #ef4444;">
            <div class="meeting-icon" style="color: #ef4444;"><i class="fa-solid fa-graduation-cap"></i></div>
            <div class="meeting-details">
                <h4>Next SOM</h4>
                <p><?php echo date('M d', strtotime($next_som['meeting_date'])); ?> • <?php echo htmlspecialchars($next_som['venue'] ?? $user_data['default_venue']); ?></p>
            </div>
            <button onclick="copyInviteLink('<?php echo $base_invite_link . "&date=" . date('Y-m-d', strtotime($next_som['meeting_date'])); ?>', this)" class="invite-icon-btn" title="Copy Invite Link"><i class="fa-solid fa-link"></i></button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="modern-section">
        <div class="section-header">
            <h3 class="section-title">Last Meeting To Till Now Links</h3>
            <p class="section-subtitle">Since <?php echo $display_cycle_start; ?></p>
        </div>
        
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-top"><i class="fa-solid fa-arrow-up-right-from-square"></i> Links Given</div>
                <div class="metric-bottom">
                    <span class="metric-val"><?php echo $cur_stats['cur_link_given']; ?></span>
                    <button class="btn-plus-modern" onclick="openModal('modalLinkGiven')"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top"><i class="fa-solid fa-handshake-simple"></i> Deals Done</div>
                <div class="metric-bottom">
                    <span class="metric-val"><?php echo $cur_stats['cur_deals_done']; ?></span>
                    <button class="btn-plus-modern" onclick="openModal('modalDealDone')"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top"><i class="fa-solid fa-sack-dollar"></i> Links Val (₹)</div>
                <div class="metric-bottom">
                    <span class="metric-val"><?php echo format_money($cur_stats['cur_link_value']); ?></span>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top"><i class="fa-solid fa-file-invoice-dollar"></i> Deals Val (₹)</div>
                <div class="metric-bottom">
                    <span class="metric-val"><?php echo format_money($cur_stats['cur_deal_value']); ?></span>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top"><i class="fa-solid fa-handshake"></i> One-to-Ones</div>
                <div class="metric-bottom">
                    <span class="metric-val"><?php echo $cur_stats['cur_121']; ?></span>
                    <button class="btn-plus-modern" onclick="openModal('modal121')"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-top"><i class="fa-solid fa-user-plus"></i> Visitors</div>
                <div class="metric-bottom">
                    <span class="metric-val"><?php echo $cur_vis; ?></span>
                    <button class="btn-plus-modern" onclick="openModal('modalVisitor')"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
            <div class="metric-card" style="grid-column: 1 / -1; background: rgba(16,185,129,0.05); border-color: rgba(16,185,129,0.2);">
                <div class="metric-top" style="color:#34d399;"><i class="fa-solid fa-user-check"></i> Members Sponsored (Joined)</div>
                <div class="metric-bottom">
                    <span class="metric-val" style="color:#10b981;"><?php echo $cur_joined; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="modern-section">
        <div class="section-header">
            <h3 class="section-title">Member Performance & History</h3>
        </div>
        
        <div class="history-card">
            <div class="filter-tabs">
                <a href="?filter=3m" class="filter-tab <?php echo ($hist_filter=='3m')?'active':''; ?>">3 Mon</a>
                <a href="?filter=6m" class="filter-tab <?php echo ($hist_filter=='6m')?'active':''; ?>">6 Mon</a>
                <a href="?filter=1y" class="filter-tab <?php echo ($hist_filter=='1y')?'active':''; ?>">1 Yr</a>
                <a href="?filter=life" class="filter-tab <?php echo ($hist_filter=='life')?'active':''; ?>">Life</a>
            </div>
            
            <table class="perf-table">
                <tr>
                    <th style="width: 70%;">Performance Metric</th>
                    <th style="text-align: right;">Result</th>
                </tr>
                <tr>
                    <td>Points Achieved <span style="font-size: 10px; color:rgba(255,255,255,0.4);">(Current Cycle)</span></td>
                    <td style="text-align: right;" class="val-highlight"><?php echo number_format($my_points); ?></td>
                </tr>
                <tr>
                    <td>Mtg Attendance <span style="font-size: 10px; color:rgba(255,255,255,0.4);">(Present/Absent)</span></td>
                    <td style="text-align: right;"><span style="color:#10b981;"><?php echo (int)($perf_stats['mtg_present']??0); ?></span> / <span style="color:#ef4444;"><?php echo (int)($perf_stats['mtg_absent']??0); ?></span></td>
                </tr>
                <tr>
                    <td>SOM Attendance <span style="font-size: 10px; color:rgba(255,255,255,0.4);">(Present/Absent)</span></td>
                    <td style="text-align: right;"><span style="color:#10b981;"><?php echo (int)($perf_stats['som_present']??0); ?></span> / <span style="color:#ef4444;"><?php echo (int)($perf_stats['som_absent']??0); ?></span></td>
                </tr>
                <tr>
                    <td>Total Links Given</td>
                    <td style="text-align: right;" class="val-highlight"><?php echo $hist_stats['hist_link_given']; ?></td>
                </tr>
                <tr>
                    <td>Total Deals Done</td>
                    <td style="text-align: right;" class="val-highlight"><?php echo $hist_stats['hist_deals_done']; ?></td>
                </tr>
                <tr>
                    <td>Total Link Value (₹)</td>
                    <td style="text-align: right;" class="val-highlight"><?php echo format_money($hist_stats['hist_link_value']); ?></td>
                </tr>
                <tr>
                    <td>Total Deal Value (₹)</td>
                    <td style="text-align: right;" class="val-highlight"><?php echo format_money($hist_stats['hist_deal_value']); ?></td>
                </tr>
                <tr>
                    <td>Total One-to-Ones</td>
                    <td style="text-align: right;" class="val-highlight"><?php echo $hist_stats['hist_121']; ?></td>
                </tr>
                <tr>
                    <td>Total Visitors Invited</td>
                    <td style="text-align: right;" class="val-highlight"><?php echo $hist_vis; ?></td>
                </tr>
                <tr>
                    <td style="color: #34d399;">Total Members Sponsored (Joined)</td>
                    <td style="text-align: right; color: #10b981;" class="val-highlight"><?php echo $hist_joined; ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php if (isset($user_data['leadership_role']) && $user_data['leadership_role'] === 'Coordinator'): ?>
        <a href="head_table.php" style="display: block; text-align:center; background:var(--primary-orange); color:white; text-decoration:none; padding:15px; border-radius:12px; font-weight:600; margin-bottom:20px;"><i class="fa-solid fa-crown" style="color: #fbbf24; margin-right: 8px;"></i> Access Head Table Portal</a>
    <?php endif; ?>
    
    <?php if (isset($user_data['leadership_role']) && $user_data['leadership_role'] === 'Attendance'): ?>
        <a href="attendance_dashboard.php" style="display: block; text-align:center; background:#3b82f6; color:white; text-decoration:none; padding:15px; border-radius:12px; font-weight:600; margin-bottom:20px; box-shadow: 0 4px 15px rgba(59,130,246,0.3);"><i class="fa-solid fa-clipboard-user" style="margin-right: 8px;"></i> Access Attendance Coordinator Panel</a>
    <?php endif; ?>
</div>

<!-- NOTIFICATION MODAL ENGINE -->
<div class="modal-overlay" id="modalNotifications">
    <div class="modal-box" style="max-height: 80vh; overflow-y: auto;">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalNotifications')"></i>
        <h3 style="margin-top:0; color:white; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
            <i class="fa-solid fa-bell" style="color:var(--brand-orange);"></i> Notifications
        </h3>
        
        <?php if (empty($notifications)): ?>
            <p style="text-align:center; color:rgba(255,255,255,0.5); font-size: 13px; margin: 20px 0;">You have no new notifications.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column;">
                <?php foreach ($notifications as $n): ?>
                    <div style="display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; color: white; background: <?php echo $n['color']; ?>;">
                            <i class="fa-solid <?php echo $n['icon']; ?>"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 3px 0; font-size: 13px; color: white; font-weight: 600;"><?php echo htmlspecialchars($n['title']); ?></h4>
                            <p style="margin: 0 0 4px 0; font-size: 12px; color: rgba(255,255,255,0.7); line-height: 1.4;"><?php echo htmlspecialchars($n['desc']); ?></p>
                            <span style="font-size: 10px; color: rgba(255,255,255,0.4);"><?php echo ($n['date'] > time()) ? 'Just Now' : date('M d, Y', $n['date']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/modals.php'; include 'includes/bottom_nav.php'; ?>

<script>
    function openModal(modalId) { 
        const m = document.getElementById(modalId); 
        if(m) m.style.display = 'flex'; 
    }
    function closeModal(modalId) { 
        const m = document.getElementById(modalId); 
        if(m) m.style.display = 'none'; 
    }
    function copyInviteLink(linkText, btnElement) {
        navigator.clipboard.writeText(linkText).then(() => {
            const originalHTML = btnElement.innerHTML;
            btnElement.innerHTML = '<i class="fa-solid fa-check"></i>';
            btnElement.style.background = '#10b981';
            btnElement.style.color = 'white';
            btnElement.style.borderColor = '#10b981';
            setTimeout(() => {
                btnElement.innerHTML = originalHTML;
                btnElement.style.background = 'rgba(59, 130, 246, 0.15)';
                btnElement.style.color = '#93c5fd';
                btnElement.style.borderColor = 'rgba(59, 130, 246, 0.4)';
            }, 2000);
        });
    }
</script>
</body>
</html>