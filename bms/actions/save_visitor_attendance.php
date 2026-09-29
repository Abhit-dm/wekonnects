<?php
// actions/save_visitor_attendance.php
session_start();
require_once '../config/database.php';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: ../login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_id = filter_input(INPUT_POST, 'meeting_id', FILTER_SANITIZE_NUMBER_INT);
    $attended_visitors = $_POST['attended_visitors'] ?? [];

    try {
        $stmtRole = $pdo->prepare("SELECT group_id FROM group_members WHERE user_id = ? AND leadership_role IN ('Coordinator', 'Attendance') AND membership_status = 'Active' LIMIT 1");
        $stmtRole->execute([$_SESSION['user_id']]);
        $authorized_group_id = $stmtRole->fetchColumn();

        $stmtDate = $pdo->prepare("SELECT group_id, meeting_date FROM chapter_meetings WHERE id = ? AND meeting_type IN ('Meeting', 'Event', 'SOM')");
        $stmtDate->execute([$meeting_id]);
        $meeting = $stmtDate->fetch();

        if (!$meeting || !$authorized_group_id || (int)$meeting['group_id'] !== (int)$authorized_group_id) {
            throw new RuntimeException('Unauthorized meeting roll call.');
        }

        if ($meeting['meeting_date'] > date('Y-m-d')) {
            throw new RuntimeException('Visitor attendance opens on the scheduled meeting day.');
        }

        $stmtLocked = $pdo->prepare("SELECT COUNT(*) FROM chapter_meetings WHERE group_id = ? AND meeting_type IN ('Meeting', 'Event', 'SOM') AND meeting_date > ? AND status = 'Completed'");
        $stmtLocked->execute([$authorized_group_id, $meeting['meeting_date']]);
        if ($stmtLocked->fetchColumn() > 0) {
            throw new RuntimeException('This visitor roll call is locked because a later meeting is already complete.');
        }

        $attended_visitors = is_array($attended_visitors)
            ? array_values(array_unique(array_filter(array_map('intval', $attended_visitors))))
            : [];

        $pdo->beginTransaction();

        // Reset ALL visitors assigned to this date to "Not Attended"
        $stmtReset = $pdo->prepare("
            UPDATE visitors v 
            JOIN users u ON v.invited_by = u.id 
            JOIN group_members gm ON u.id = gm.user_id 
            SET v.attended = 0, v.status = CASE WHEN v.status = 'Attended' THEN 'Pending' ELSE v.status END
            WHERE DATE(v.visit_date) = ? AND v.chapter_id = ?
        ");
        $stmtReset->execute([$meeting['meeting_date'], $authorized_group_id]);

        // Mark the checked ones as "Attended"
        if (!empty($attended_visitors)) {
            $inQuery = implode(',', array_fill(0, count($attended_visitors), '?'));
            $stmtUpdate = $pdo->prepare("UPDATE visitors SET attended = 1, status = CASE WHEN status = 'Pending' THEN 'Attended' ELSE status END WHERE chapter_id = ? AND visit_date = ? AND id IN ($inQuery)");
            $stmtUpdate->execute(array_merge([$authorized_group_id, $meeting['meeting_date']], $attended_visitors));
        }

        $pdo->commit();
        $_SESSION['success_msg'] = "Visitor Roll Call saved successfully!";
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Error saving roll call.";
    }
    
    header("Location: ../attendance_manager.php?meeting_id=" . $meeting_id);
    exit;
}
?>