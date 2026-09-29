<?php
// actions/update_status.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $chapter_id = filter_input(INPUT_POST, 'chapter_id', FILTER_SANITIZE_NUMBER_INT);
    $new_status = trim(strip_tags($_POST['new_status']));

    $status_map = [
        'Active' => ['user' => 'Active', 'membership' => 'Active'],
        'Pending' => ['user' => 'Pending_Setup', 'membership' => 'Dropped'],
        'Inactive' => ['user' => 'Locked', 'membership' => 'Dropped']
    ];

    if ($user_id && isset($status_map[$new_status])) {
        try {
            if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
                $stmtAccess = $pdo->prepare("SELECT 1 FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND gm.group_id = ? AND gm.membership_status = 'Active' AND g.franchise_owner_id = ?");
                $stmtAccess->execute([$user_id, $chapter_id, $_SESSION['user_id']]);
            } else {
                $stmtAccess = $pdo->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? AND membership_status = 'Active'");
                $stmtAccess->execute([$user_id, $chapter_id]);
            }
            if (!$chapter_id || !$stmtAccess->fetchColumn()) {
                throw new RuntimeException('You cannot change membership in this chapter.');
            }

            $pdo->beginTransaction();
            
            $stmtU = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmtU->execute([$status_map[$new_status]['user'], $user_id]);
            
            $membership_status = $status_map[$new_status]['membership'];
            $category_id = null;
            if ($membership_status === 'Active') {
                $stmtBusiness = $pdo->prepare("SELECT business_category_applied FROM businesses WHERE user_id = ?");
                $stmtBusiness->execute([$user_id]);
                $category_name = $stmtBusiness->fetchColumn();
                if (!$category_name) throw new RuntimeException('Add a business category before reactivating this member.');

                $stmtCategory = $pdo->prepare("SELECT id FROM business_categories WHERE category_name = ?");
                $stmtCategory->execute([$category_name]);
                $category_id = $stmtCategory->fetchColumn();
                if (!$category_id) {
                    $stmtCategory = $pdo->prepare("INSERT INTO business_categories (category_name) VALUES (?)");
                    $stmtCategory->execute([$category_name]);
                    $category_id = $pdo->lastInsertId();
                }
            }

            $stmtG = $pdo->prepare("UPDATE group_members SET membership_status = ?, business_category_id = ? WHERE user_id = ? AND group_id = ?");
            $stmtG->execute([$membership_status, $category_id, $user_id, $chapter_id]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Member status changed to " . $new_status . " successfully.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "Database Error: Could not update status.";
        }
    }
}
header("Location: ../admin_directory.php?chapter_id=" . $chapter_id);
exit;
?>