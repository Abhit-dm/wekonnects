<?php
// actions/remove_attendance.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['logged_in'])) {
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $target_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

    $stmtVerify = $pdo->prepare("SELECT group_id FROM group_members WHERE user_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active'");
    $stmtVerify->execute([$_SESSION['user_id']]);
    
    if ($stmtVerify->fetch()) {
        try {
            $pdo->prepare("UPDATE group_members SET leadership_role = NULL WHERE group_id = ? AND user_id = ? AND leadership_role = 'Attendance'")->execute([$group_id, $target_user_id]);
            $_SESSION['success_msg'] = "Attendance Coordinator successfully removed. Their portal access is revoked.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database Error.";
        }
    }
}
header("Location: ../head_table.php");
exit;
?>