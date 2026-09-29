<?php
// actions/export_analytics.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['system_role'] === 'SUPER_ADMIN' || $_SESSION['system_role'] === 'FRANCHISE_OWNER');

$group_id = filter_input(INPUT_GET, 'group_id', FILTER_SANITIZE_NUMBER_INT);
$start_date = filter_input(INPUT_GET, 'start', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$end_date = filter_input(INPUT_GET, 'end', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$group_id || !$start_date || !$end_date || !$type) {
    die("Invalid Export Request.");
}

// Security: Verify user has access to this group
if (!$is_admin) {
    $stmtVerify = $pdo->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active'");
    $stmtVerify->execute([$user_id, $group_id]);
    if (!$stmtVerify->fetchColumn()) die("Access Denied.");
} elseif ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
    $stmtVerify = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND franchise_owner_id = ?");
    $stmtVerify->execute([$group_id, $user_id]);
    if (!$stmtVerify->fetchColumn()) die("Access Denied.");
}

// Get Chapter Name
$stmtGrp = $pdo->prepare("SELECT group_name FROM groups WHERE id = ?");
$stmtGrp->execute([$group_id]);
$group_name = $stmtGrp->fetchColumn();
$safe_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $group_name);

header('Content-Type: text/csv; charset=utf-8');

// ==========================================
// EXPORT 1: DETAILED ATTENDANCE
// ==========================================
if ($type === 'attendance') {
    header("Content-Disposition: attachment; filename={$safe_name}_Attendance_{$start_date}_to_{$end_date}.csv");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Report: Detailed Attendance']);
    fputcsv($out, ['Chapter:', $group_name]);
    fputcsv($out, ['Period:', $start_date . ' to ' . $end_date]);
    fputcsv($out, []);
    fputcsv($out, ['Member Name', 'Company', 'Present', 'Late', 'Absent', 'Substitutes Sent']);
    
    $stmtAtt = $pdo->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as member_name, b.company_name,
            SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) as pres,
            SUM(CASE WHEN a.attendance_status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) as abs,
            SUM(CASE WHEN a.attendance_status = 'Substitute' THEN 1 ELSE 0 END) as sub
        FROM group_members gm
        JOIN users u ON gm.user_id = u.id
        LEFT JOIN businesses b ON u.id = b.user_id
        LEFT JOIN chapter_meetings cm ON cm.group_id = gm.group_id AND cm.meeting_type IN ('Meeting', 'Event', 'SOM') AND cm.meeting_date >= ? AND cm.meeting_date <= ? AND cm.meeting_date >= COALESCE(NULLIF(gm.joining_date, '0000-00-00'), gm.join_date)
        LEFT JOIN attendance a ON a.meeting_id = cm.id AND a.user_id = u.id
        WHERE gm.group_id = ? AND gm.membership_status = 'Active'
        GROUP BY u.id ORDER BY u.first_name ASC
    ");
    $stmtAtt->execute([$start_date, $end_date, $group_id]);
    while ($row = $stmtAtt->fetch(PDO::FETCH_ASSOC)) { fputcsv($out, $row); }
    fclose($out);
    exit;

// ==========================================
// EXPORT 2: CUSTOM PERFORMANCE
// ==========================================
} elseif ($type === 'performance') {
    header("Content-Disposition: attachment; filename={$safe_name}_Performance_{$start_date}_to_{$end_date}.csv");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Report: Member Performance']);
    fputcsv($out, ['Chapter:', $group_name]);
    fputcsv($out, ['Period:', $start_date . ' to ' . $end_date]);
    fputcsv($out, []);
    fputcsv($out, ['Member Name', 'Company', 'Links Given', 'Links Received', '1-to-1s', 'Visitors Invited', 'Revenue Generated (TYFCB)']);
    
    $stmtPerf = $pdo->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as member_name, b.company_name,
            (SELECT COUNT(*) FROM slips s WHERE s.initiator_member_id = u.id AND s.slip_type = 'REFERRAL' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as refs_given,
            (SELECT COUNT(*) FROM slips s WHERE s.receiver_member_id = u.id AND s.slip_type = 'REFERRAL' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as refs_received,
            (SELECT COUNT(*) FROM slips s WHERE s.initiator_member_id = u.id AND s.slip_type = '121' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as ones_done,
            (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND chapter_id = ? AND visit_date >= ? AND visit_date <= ? AND attended = 1) as visitors,
            (SELECT COALESCE(SUM(amount), 0) FROM slips s WHERE s.initiator_member_id = u.id AND s.slip_type = 'TYFCB' AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) <= ?) as revenue
        FROM group_members gm
        JOIN users u ON gm.user_id = u.id
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE gm.group_id = ? AND gm.membership_status = 'Active'
        ORDER BY revenue DESC, u.first_name ASC
    ");
    $stmtPerf->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $group_id, $start_date, $end_date, $start_date, $end_date, $group_id]);
    while ($row = $stmtPerf->fetch(PDO::FETCH_ASSOC)) { fputcsv($out, $row); }
    fclose($out);
    exit;

// ==========================================
// EXPORT 3: THE 6-MONTH TERM REPORT
// ==========================================
} elseif ($type === 'term') {
    header("Content-Disposition: attachment; filename={$safe_name}_HEAD_TABLE_TERM_REPORT.csv");
    $out = fopen('php://output', 'w');
    
    // Fetch Grand Totals for the 6 Months
    $stmtKPI = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM group_members WHERE group_id = ? AND membership_status = 'Active') as total_members,
            (SELECT COUNT(*) FROM group_members WHERE group_id = ? AND joining_date >= ? AND joining_date <= ?) as new_joinings,
            (SELECT COALESCE(SUM(amount), 0) FROM slips s JOIN group_members gms ON s.initiator_member_id = gms.user_id WHERE s.slip_type = 'TYFCB' AND gms.group_id = ? AND gms.membership_status = 'Active' AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) <= ?) as total_revenue,
            (SELECT COUNT(*) FROM slips s JOIN group_members gms ON s.initiator_member_id = gms.user_id WHERE s.slip_type = 'REFERRAL' AND gms.group_id = ? AND gms.membership_status = 'Active' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as total_refs,
            (SELECT COUNT(*) FROM visitors v WHERE v.chapter_id = ? AND v.visit_date >= ? AND v.visit_date <= ? AND v.attended = 1) as total_visitors
    ");
    $stmtKPI->execute([$group_id, $group_id, $start_date, $end_date, $group_id, $start_date, $end_date, $group_id, $start_date, $end_date, $group_id, $start_date, $end_date]);
    $kpi = $stmtKPI->fetch(PDO::FETCH_ASSOC);

    fputcsv($out, ['*** OFFICIAL HEAD TABLE TERM SUMMARY ***']);
    fputcsv($out, ['Chapter:', $group_name]);
    fputcsv($out, ['Term Period:', $start_date . ' to ' . $end_date]);
    fputcsv($out, []);
    fputcsv($out, ['--- GRAND TOTALS ACHIEVED ---']);
    fputcsv($out, ['Total Revenue Generated', 'Rs. ' . $kpi['total_revenue']]);
    fputcsv($out, ['Total Links Passed', $kpi['total_refs']]);
    fputcsv($out, ['Total Verified Visitors', $kpi['total_visitors']]);
    fputcsv($out, ['New Members Added', $kpi['new_joinings']]);
    fputcsv($out, ['Current Chapter Size', $kpi['total_members']]);
    fputcsv($out, []);
    fputcsv($out, ['--- INDIVIDUAL MEMBER CONTRIBUTIONS ---']);
    fputcsv($out, ['Member Name', 'Company', 'Revenue Contributed', 'Links Given', 'Visitors Invited']);

    $stmtPerf = $pdo->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as member_name, b.company_name,
            (SELECT COALESCE(SUM(amount), 0) FROM slips s WHERE s.initiator_member_id = u.id AND s.slip_type = 'TYFCB' AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.deal_close_date, '0000-00-00'), s.date_logged) <= ?) as revenue,
            (SELECT COUNT(*) FROM slips s WHERE s.initiator_member_id = u.id AND s.slip_type = 'REFERRAL' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= ? AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) <= ?) as refs_given,
            (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND chapter_id = ? AND visit_date >= ? AND visit_date <= ? AND attended = 1) as visitors
        FROM group_members gm
        JOIN users u ON gm.user_id = u.id
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE gm.group_id = ? AND gm.membership_status = 'Active'
        ORDER BY revenue DESC, u.first_name ASC
    ");
    $stmtPerf->execute([$start_date, $end_date, $start_date, $end_date, $group_id, $start_date, $end_date, $group_id]);
    while ($row = $stmtPerf->fetch(PDO::FETCH_ASSOC)) { fputcsv($out, $row); }
    
    fclose($out);
    exit;
}
?>