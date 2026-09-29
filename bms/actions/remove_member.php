<?php
// actions/remove_member.php
session_start();
require_once '../config/database.php';

// Security: Admins only
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

$remove_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($remove_id) {
    try {
        $pdo->beginTransaction();
        
        $stmtG = $pdo->prepare("UPDATE group_members SET membership_status = 'Dropped', business_category_id = NULL WHERE user_id = ? AND membership_status = 'Active'");
        $stmtG->execute([$remove_id]);

        $stmtU = $pdo->prepare("UPDATE users SET status = 'Locked' WHERE id = ?");
        $stmtU->execute([$remove_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Member archived and removed from active chapter membership.";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "System Error: Could not remove member.";
    }
}

header("Location: ../directory.php");
exit;
?>