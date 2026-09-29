<?php
// actions/export_chapter_analytics.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); 
    exit;
}

try {
    $stmtChapters = $pdo->query("
        SELECT 
            g.group_name as 'Chapter Name',
            (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = g.id AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active' LIMIT 1) as 'Coordinator',
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND membership_status = 'Active') as 'Active Members',
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND joining_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)) as 'Joinings (Last 6M)',
            (SELECT COUNT(*) FROM slips s JOIN group_members gm ON s.initiator_member_id = gm.user_id WHERE gm.group_id = g.id AND gm.membership_status = 'Active' AND s.slip_type = 'REFERRAL' AND COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)) as 'Links Passed (Last 6M)',
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND membership_status != 'Active') as 'Total Lost/Inactive Members',
            (
                SELECT COALESCE(ROUND((SUM(CASE WHEN a.attendance_status IN ('Present', 'Late', 'Substitute') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100), 100)
                FROM attendance a 
                JOIN chapter_meetings cm ON a.meeting_id = cm.id 
                                JOIN group_members gm ON gm.user_id = a.user_id AND gm.group_id = cm.group_id AND gm.membership_status = 'Active'
                                WHERE cm.group_id = g.id AND cm.meeting_type IN ('Meeting', 'Event', 'SOM')
                                    AND cm.meeting_date >= GREATEST(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), COALESCE(NULLIF(gm.joining_date, '0000-00-00'), gm.join_date))
            ) as 'Attendance % (Last 6M)'
        FROM groups g 
        WHERE g.status = 'Active'
        ORDER BY g.group_name ASC
    ");
    $data = $stmtChapters->fetchAll(PDO::FETCH_ASSOC);

    // Generate CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=WEKONNECTS_Chapter_Analytics_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    if (count($data) > 0) {
        fputcsv($output, array_keys($data[0])); // Headers
        foreach ($data as $row) {
            // Ensure empty coordinator is printed nicely
            if (empty($row['Coordinator'])) { $row['Coordinator'] = 'None Assigned'; }
            fputcsv($output, $row);
        }
    } else {
        fputcsv($output, ['No chapter data available.']);
    }
    
    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Export Failed: " . $e->getMessage());
}
?>