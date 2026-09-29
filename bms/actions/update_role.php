<?php
// actions/update_role.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $chapter_id = filter_input(INPUT_POST, 'chapter_id', FILTER_VALIDATE_INT);
    $role = $_POST['leadership_role'] ?? '';
    $officer_role = $_POST['officer_role'] ?? '';
    $allowed_officer_roles = ['President', 'Vice President', 'Secretary', 'Treasurer', 'Visitor Host Leader'];

    if ($user_id && $chapter_id && in_array($role, ['Member', 'Coordinator'], true) && ($officer_role === '' || in_array($officer_role, $allowed_officer_roles, true))) {
        try {
            if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
                $stmtAccess = $pdo->prepare("SELECT 1 FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND gm.group_id = ? AND gm.membership_status = 'Active' AND g.franchise_owner_id = ?");
                $stmtAccess->execute([$user_id, $chapter_id, $_SESSION['user_id']]);
            } else {
                $stmtAccess = $pdo->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? AND membership_status = 'Active'");
                $stmtAccess->execute([$user_id, $chapter_id]);
            }
            if (!$stmtAccess->fetchColumn()) {
                throw new RuntimeException('This member is not active in a chapter you can manage.');
            }
            if ($officer_role !== '' && $role !== 'Coordinator') {
                throw new RuntimeException('An officer title can only be assigned to a Head Table member.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE group_members SET leadership_role = ? WHERE user_id = ? AND group_id = ? AND membership_status = 'Active'");
            $stmt->execute([$role, $user_id, $chapter_id]);

            $stmtMember = $pdo->prepare("SELECT id FROM group_members WHERE user_id = ? AND group_id = ? AND membership_status = 'Active' LIMIT 1");
            $stmtMember->execute([$user_id, $chapter_id]);
            $group_member_id = $stmtMember->fetchColumn();

            $stmtCurrentRoles = $pdo->prepare("SELECT cr.id FROM term_leadership tl JOIN chapter_roles cr ON tl.role_id = cr.id WHERE tl.group_id = ? AND tl.group_member_id = ? AND cr.role_level = 'HEAD_TABLE' AND tl.end_date >= CURDATE()");
            $stmtCurrentRoles->execute([$chapter_id, $group_member_id]);
            $current_role_ids = $stmtCurrentRoles->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($current_role_ids)) {
                $role_placeholders = implode(',', array_fill(0, count($current_role_ids), '?'));
                $stmtEndMemberRoles = $pdo->prepare("UPDATE term_leadership SET end_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE group_id = ? AND group_member_id = ? AND role_id IN ($role_placeholders) AND end_date >= CURDATE()");
                $stmtEndMemberRoles->execute(array_merge([$chapter_id, $group_member_id], $current_role_ids));
            }

            if ($officer_role !== '') {
                $stmtRole = $pdo->prepare("SELECT id FROM chapter_roles WHERE role_name = ? AND role_level = 'HEAD_TABLE' LIMIT 1");
                $stmtRole->execute([$officer_role]);
                $role_id = $stmtRole->fetchColumn();
                if (!$role_id) {
                    $stmtRole = $pdo->prepare("INSERT INTO chapter_roles (role_name, role_level) VALUES (?, 'HEAD_TABLE')");
                    $stmtRole->execute([$officer_role]);
                    $role_id = $pdo->lastInsertId();
                }

                $stmtEndRole = $pdo->prepare("UPDATE term_leadership SET end_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE group_id = ? AND role_id = ? AND end_date >= CURDATE()");
                $stmtEndRole->execute([$chapter_id, $role_id]);

                $stmtAssign = $pdo->prepare("INSERT INTO term_leadership (group_id, group_member_id, role_id, start_date, end_date) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH))");
                $stmtAssign->execute([$chapter_id, $group_member_id, $role_id]);
            }

            $pdo->commit();
            $_SESSION['success_msg'] = "Member role successfully updated.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "Database Error: Could not update role.";
        }
    }
}
header("Location: ../admin_directory.php?chapter_id=" . $chapter_id);
exit;
?>