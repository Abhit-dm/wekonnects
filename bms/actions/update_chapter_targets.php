<?php
// actions/update_chapter_targets.php
session_start();
require_once '../config/database.php';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: ../login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
    $target_members = filter_input(INPUT_POST, 'target_members', FILTER_VALIDATE_INT);
    $target_links = filter_input(INPUT_POST, 'target_links', FILTER_VALIDATE_INT);
    $target_revenue = filter_input(INPUT_POST, 'target_revenue', FILTER_VALIDATE_FLOAT);

    try {
        if (!$group_id || $target_members === false || $target_members < 1 || $target_links === false || $target_links < 0 || $target_revenue === false || $target_revenue < 0) {
            throw new RuntimeException('Enter valid, non-negative targets. Member target must be at least 1.');
        }

        $stmtAccess = $pdo->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active'");
        $stmtAccess->execute([$_SESSION['user_id'], $group_id]);
        $is_coordinator = (bool)$stmtAccess->fetchColumn();

        $is_admin = in_array($_SESSION['system_role'] ?? '', ['SUPER_ADMIN', 'FRANCHISE_OWNER'], true);
        if (!$is_coordinator && !$is_admin) {
            throw new RuntimeException('Only this chapter’s Head Table can update its targets.');
        }

        if (!$is_coordinator && ($_SESSION['system_role'] ?? '') === 'FRANCHISE_OWNER') {
            $stmtOwner = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND franchise_owner_id = ?");
            $stmtOwner->execute([$group_id, $_SESSION['user_id']]);
            if (!$stmtOwner->fetchColumn()) {
                throw new RuntimeException('You can only update targets for your franchise chapters.');
            }
        }

        $stmt = $pdo->prepare("UPDATE groups SET target_members = ?, target_links = ?, target_revenue = ? WHERE id = ?");
        $stmt->execute([$target_members, $target_links, $target_revenue, $group_id]);

        $_SESSION['success_msg'] = "Chapter targets updated successfully!";
    } catch (Throwable $e) {
        $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "Error updating targets.";
    }
}
header("Location: ../index.php");
exit;
?>