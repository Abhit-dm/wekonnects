<?php
// actions/delete_pending_user.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    
    if ($user_id) {
        try {
            $pdo->beginTransaction();
            
            // Delete associated business details
            $stmt1 = $pdo->prepare("DELETE FROM businesses WHERE user_id = ?");
            $stmt1->execute([$user_id]);
            
            // Delete associated group assignments
            $stmt2 = $pdo->prepare("DELETE FROM group_members WHERE user_id = ?");
            $stmt2->execute([$user_id]);
            
            // Finally delete the user account
            $stmt3 = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt3->execute([$user_id]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Pending application permanently deleted.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_msg'] = "Database Error: Could not delete application.";
        }
    }
}
header("Location: ../sa_pending_renewals.php");
exit;
?>