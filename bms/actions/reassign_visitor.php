<?php
// actions/reassign_visitor.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['logged_in'])) {
    $visitor_id = filter_input(INPUT_POST, 'visitor_id', FILTER_SANITIZE_NUMBER_INT);
    $new_group_id = filter_input(INPUT_POST, 'new_group_id', FILTER_SANITIZE_NUMBER_INT);
    $sys_role = $_SESSION['system_role'] ?? 'MEMBER';
    
    // Strict Security: Only Admins/Franchise Owners can reassign members across chapters
    if ($sys_role === 'SUPER_ADMIN' || $sys_role === 'FRANCHISE_OWNER') {
        if ($visitor_id && $new_group_id) {
            try {
                // Find the Coordinator of the Target Chapter to assign the visitor to
                $stmtCoord = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active' LIMIT 1");
                $stmtCoord->execute([$new_group_id]);
                $coord = $stmtCoord->fetch();
                
                // If there is no coordinator set, fallback to any active member in that chapter
                if (!$coord) {
                    $stmtFallback = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND membership_status = 'Active' LIMIT 1");
                    $stmtFallback->execute([$new_group_id]);
                    $coord = $stmtFallback->fetch();
                }

                if ($coord) {
                    $new_member_id = $coord['user_id'];
                    
                    // Update BOTH invited_by (for points) and assigned_to (for follow up)
                    $stmt = $pdo->prepare("UPDATE visitors SET invited_by = ?, assigned_to = ? WHERE id = ?");
                    $stmt->execute([$new_member_id, $new_member_id, $visitor_id]);
                    $_SESSION['success_msg'] = "Visitor successfully transferred to the new chapter!";
                } else {
                    $_SESSION['error_msg'] = "Transfer Failed: The target chapter has no active members to assign the visitor to.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_msg'] = "Access Denied: You do not have permission to move visitors.";
    }
}
header("Location: ../manage_visitors.php");
exit;
?>