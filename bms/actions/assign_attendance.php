<?php
// actions/assign_attendance.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['logged_in'])) {
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $new_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

    // Strict Security Verify
    $stmtVerify = $pdo->prepare("SELECT group_id FROM group_members WHERE user_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active'");
    $stmtVerify->execute([$_SESSION['user_id']]);
    
    if ($stmtVerify->fetch()) {
        try {
            // STEP 1: Clear the role from anyone who previously held it in this chapter
            $pdo->prepare("UPDATE group_members SET leadership_role = NULL WHERE group_id = ? AND leadership_role = 'Attendance'")->execute([$group_id]);
            
            // STEP 2: Assign the role uniquely to the new member
            $pdo->prepare("UPDATE group_members SET leadership_role = 'Attendance' WHERE group_id = ? AND user_id = ?")->execute([$group_id, $new_user_id]);
            
            $_SESSION['success_msg'] = "Attendance Coordinator successfully assigned! The previous coordinator (if any) was removed.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
        }
    }
}
header("Location: ../head_table.php");
exit;
?>