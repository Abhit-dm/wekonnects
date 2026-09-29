<?php
// actions/transfer_member.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $new_group_id = filter_input(INPUT_POST, 'new_group_id', FILTER_SANITIZE_NUMBER_INT);

    if ($user_id && $new_group_id) {
        try {
            if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
                $stmtSource = $pdo->prepare("SELECT 1 FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND gm.membership_status = 'Active' AND g.franchise_owner_id = ? LIMIT 1");
                $stmtSource->execute([$user_id, $_SESSION['user_id']]);
                $stmtTarget = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND status = 'Active' AND franchise_owner_id = ?");
                $stmtTarget->execute([$new_group_id, $_SESSION['user_id']]);
            } else {
                $stmtSource = $pdo->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND membership_status = 'Active' LIMIT 1");
                $stmtSource->execute([$user_id]);
                $stmtTarget = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND status = 'Active'");
                $stmtTarget->execute([$new_group_id]);
            }
            if (!$stmtSource->fetchColumn() || !$stmtTarget->fetchColumn()) {
                throw new RuntimeException('Choose a current member and an accessible active chapter.');
            }

            $stmt = $pdo->prepare("UPDATE group_members SET group_id = ? WHERE user_id = ? AND membership_status = 'Active'");
            $stmt->execute([$new_group_id, $user_id]);
            
            $_SESSION['success_msg'] = "Member successfully transferred to the new chapter.";
        } catch (Throwable $e) {
            $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "Database Error: Could not transfer member.";
        }
    }
}
header("Location: ../admin_directory.php?chapter_id=" . $new_group_id);
exit;
?>