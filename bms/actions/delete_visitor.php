
<?php
// actions/delete_visitor.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: ../login.php"); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_id = filter_input(INPUT_POST, 'visitor_id', FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    $system_role = $_SESSION['system_role'] ?? 'MEMBER';

    // Verify Coordinator or Admin access
    $stmtVerify = $pdo->prepare("SELECT gm.group_id FROM group_members gm WHERE gm.user_id = ? AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active'");
    $stmtVerify->execute([$user_id]);
    $coord = $stmtVerify->fetch();

    $is_admin = ($system_role === 'SUPER_ADMIN' || $system_role === 'FRANCHISE_OWNER');

    if (!$coord && !$is_admin) {
        $_SESSION['error_msg'] = "Access Denied: You do not have permission to delete visitors.";
        header("Location: ../manage_visitors.php");
        exit;
    }

    if ($visitor_id) {
        try {
            // Failsafe: Ensure a coordinator only deletes visitors linked to their chapter
            if (!$is_admin && $coord) {
                $group_id = $coord['group_id'];
                $checkStmt = $pdo->prepare("SELECT id FROM visitors WHERE id = ? AND invited_by IN (SELECT user_id FROM group_members WHERE group_id = ?)");
                $checkStmt->execute([$visitor_id, $group_id]);
                if (!$checkStmt->fetch()) {
                    $_SESSION['error_msg'] = "Error: This visitor does not belong to your chapter.";
                    header("Location: ../manage_visitors.php");
                    exit;
                }
            }

            // Securely delete the visitor
            $stmt = $pdo->prepare("DELETE FROM visitors WHERE id = ?");
            $stmt->execute([$visitor_id]);
            
            $_SESSION['success_msg'] = "Visitor has been successfully deleted from the database.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
        }
    }
}

header("Location: ../manage_visitors.php");
exit;
?>