<?php
// actions/update_visitor_status.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $visitor_id = filter_input(INPUT_POST, 'visitor_id', FILTER_SANITIZE_NUMBER_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($visitor_id && in_array($status, ['Pending', 'Attended', 'Joined', 'Declined'], true)) {
        try {
            $stmtVisitor = $pdo->prepare("SELECT chapter_id, attended FROM visitors WHERE id = ?");
            $stmtVisitor->execute([$visitor_id]);
            $visitor = $stmtVisitor->fetch();

            $role = $_SESSION['system_role'] ?? 'MEMBER';
            $has_global_access = in_array($role, ['SUPER_ADMIN', 'EMP_VISITOR_MANAGER'], true);
            $has_chapter_access = false;

            if ($visitor && $has_global_access) {
                $has_chapter_access = true;
            } elseif ($visitor && $role === 'FRANCHISE_OWNER') {
                $stmtOwner = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND franchise_owner_id = ?");
                $stmtOwner->execute([$visitor['chapter_id'], $_SESSION['user_id']]);
                $has_chapter_access = (bool)$stmtOwner->fetchColumn();
            } elseif ($visitor) {
                $stmtRole = $pdo->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? AND leadership_role IN ('Coordinator', 'Attendance') AND membership_status = 'Active'");
                $stmtRole->execute([$_SESSION['user_id'], $visitor['chapter_id']]);
                $has_chapter_access = (bool)$stmtRole->fetchColumn();
            }

            if (!$has_chapter_access) {
                throw new RuntimeException('You cannot update visitors in this chapter.');
            }
            if ($status === 'Joined' && (int)$visitor['attended'] !== 1) {
                throw new RuntimeException('Mark the visitor present at roll call before confirming they joined.');
            }

            $stmt = $pdo->prepare("UPDATE visitors SET status = ?, attended = CASE WHEN ? = 'Attended' THEN 1 ELSE attended END WHERE id = ?");
            $stmt->execute([$status, $status, $visitor_id]);
            $_SESSION['success_msg'] = "Visitor status updated to '{$status}'.";
        } catch (Throwable $e) {
            $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "Database Error: " . $e->getMessage();
        }
    }
}
header("Location: ../manage_visitors.php");
exit;
?>