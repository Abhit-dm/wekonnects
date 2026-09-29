<?php
// actions/export_leaderboard.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'month';

$date_sql_links = ""; $date_sql_deals = ""; $date_sql_vis = ""; $board_title = "All-Time";

if ($filter === 'month') {
    $date_sql_links = " AND COALESCE(NULLIF(link_given_date, '0000-00-00'), date_logged) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $date_sql_deals = " AND COALESCE(NULLIF(deal_close_date, '0000-00-00'), date_logged) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $date_sql_vis = " AND visit_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    $board_title = date('F_Y'); 
} elseif ($filter === '6months') {
    $date_sql_links = " AND COALESCE(NULLIF(link_given_date, '0000-00-00'), date_logged) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    $date_sql_deals = " AND COALESCE(NULLIF(deal_close_date, '0000-00-00'), date_logged) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    $date_sql_vis = " AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    $board_title = "Past_6_Months";
} elseif ($filter === 'year') {
    $date_sql_links = " AND COALESCE(NULLIF(link_given_date, '0000-00-00'), date_logged) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $date_sql_deals = " AND COALESCE(NULLIF(deal_close_date, '0000-00-00'), date_logged) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $date_sql_vis = " AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $board_title = "Past_1_Year";
}

try {
    $stmtVerify = $pdo->prepare("SELECT group_id, g.group_name FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active'");
    $stmtVerify->execute([$user_id]);
    $coord = $stmtVerify->fetch();

    if (!$coord) die("Access Denied.");
    $group_id = $coord['group_id'];
    $safe_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $coord['group_name']);

    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(u.first_name, ' ', u.last_name) as 'Member Name',
            b.company_name as 'Company',
            (SELECT COALESCE(SUM(amount), 0) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'TYFCB' $date_sql_deals) as 'Total Revenue (Rs)',
            (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = 'REFERRAL' $date_sql_links) as 'Total Links Passed',
            (SELECT COUNT(*) FROM slips WHERE initiator_member_id = u.id AND slip_type = '121' $date_sql_links) as 'Total 1-to-1 Meetings',
            (SELECT COUNT(*) FROM visitors WHERE invited_by = u.id AND chapter_id = ? AND attended = 1 $date_sql_vis) as 'Total Verified Visitors'
        FROM group_members gm
        JOIN users u ON gm.user_id = u.id
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE gm.group_id = ? AND gm.membership_status = 'Active'
        ORDER BY 3 DESC, 4 DESC
    ");
    $stmt->execute([$group_id, $group_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$safe_name}_Leaderboard_{$board_title}.csv");
    
    $out = fopen('php://output', 'w');
    if (count($data) > 0) {
        fputcsv($out, array_keys($data[0]));
        foreach ($data as $row) fputcsv($out, $row);
    } else {
        fputcsv($out, ['No leaderboard data available.']);
    }
    fclose($out);
    exit;

} catch (PDOException $e) { die("Export Failed."); }
?>