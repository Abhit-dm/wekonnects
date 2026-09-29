<?php
// actions/update_full_profile.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $return_targets = ['admin_directory.php', 'archived_members.php', 'sa_master_directory.php', 'pending_applications.php', 'sa_pending_renewals.php', 'sa_manage_franchises.php'];
    $return_to = $_POST['return_to'] ?? ($_SESSION['system_role'] === 'SUPER_ADMIN' ? 'sa_master_directory.php' : 'admin_directory.php');
    if (!in_array($return_to, $return_targets, true)) {
        $return_to = $_SESSION['system_role'] === 'SUPER_ADMIN' ? 'sa_master_directory.php' : 'admin_directory.php';
    }
    $return_chapter_id = filter_input(INPUT_POST, 'return_chapter_id', FILTER_SANITIZE_NUMBER_INT);
    $return_url = $return_to . (($return_to === 'admin_directory.php' && $return_chapter_id) ? '?chapter_id=' . $return_chapter_id : '');
    
    // Personal
    $first_name = trim(strip_tags($_POST['first_name']));
    $last_name = trim(strip_tags($_POST['last_name']));
    $phone = trim(strip_tags($_POST['phone']));
    $email = trim(strip_tags($_POST['email']));
    $blood_group = trim(strip_tags($_POST['blood_group']));
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $anniversary_date = !empty($_POST['anniversary_date']) ? $_POST['anniversary_date'] : null;
    $status = trim(strip_tags($_POST['status']));
    $status_map = [
        'Active' => ['user' => 'Active', 'membership' => 'Active'],
        'Pending' => ['user' => 'Pending_Setup', 'membership' => 'Dropped'],
        'Inactive' => ['user' => 'Locked', 'membership' => 'Dropped']
    ];
    
    // NEW: Sponsor / Invited By
    $invited_by = !empty($_POST['invited_by']) ? filter_input(INPUT_POST, 'invited_by', FILTER_SANITIZE_NUMBER_INT) : null;

    // Business
    $company_name = trim(strip_tags($_POST['company_name']));
    $category_name = trim(strip_tags($_POST['category']));
    $target_audience = trim(strip_tags($_POST['target_audience']));
    $ideal_referral = trim(strip_tags($_POST['ideal_referral']));
    $top_products = trim(strip_tags($_POST['top_products']));

    // Membership
    $joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : null;
    $renewal_date = !empty($_POST['renewal_date']) ? $_POST['renewal_date'] : null;

    if ($user_id && isset($status_map[$status])) {
        try {
            if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
                $stmtAccess = $pdo->prepare("SELECT 1 FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND g.franchise_owner_id = ? LIMIT 1");
                $stmtAccess->execute([$user_id, $_SESSION['user_id']]);
                if (!$stmtAccess->fetchColumn()) {
                    throw new RuntimeException('You can only edit members from chapters assigned to your franchise.');
                }
            }

            $pdo->beginTransaction();

            $user_status = $status_map[$status]['user'];
            $membership_status = $status_map[$status]['membership'];

            $stmtU = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, email=?, blood_group=?, dob=?, anniversary_date=?, status=?, invited_by=? WHERE id=?");
            $stmtU->execute([$first_name, $last_name, $phone, $email, $blood_group, $dob, $anniversary_date, $user_status, $invited_by, $user_id]);

            $stmtB = $pdo->prepare("UPDATE businesses SET company_name=?, business_category_applied=?, target_audience=?, ideal_referral=?, top_products=? WHERE user_id=?");
            $stmtB->execute([$company_name, $category_name, $target_audience, $ideal_referral, $top_products, $user_id]);

            $stmtMember = $pdo->prepare("SELECT id, group_id FROM group_members WHERE user_id = ? ORDER BY CASE WHEN membership_status = 'Active' THEN 0 ELSE 1 END, id DESC LIMIT 1");
            $stmtMember->execute([$user_id]);
            $membership = $stmtMember->fetch();

            if ($membership) {
                $category_id = null;
                if ($membership_status === 'Active') {
                    $stmtCategory = $pdo->prepare("SELECT id FROM business_categories WHERE category_name = ?");
                    $stmtCategory->execute([$category_name]);
                    $category_id = $stmtCategory->fetchColumn();
                    if (!$category_id) {
                        $stmtCategory = $pdo->prepare("INSERT INTO business_categories (category_name) VALUES (?)");
                        $stmtCategory->execute([$category_name]);
                        $category_id = $pdo->lastInsertId();
                    }
                }

                $stmtG = $pdo->prepare("UPDATE group_members SET joining_date=?, renewal_date=?, membership_status=?, business_category_id=? WHERE id=?");
                $stmtG->execute([$joining_date, $renewal_date, $membership_status, $category_id, $membership['id']]);
            }

            $pdo->commit();
            $_SESSION['success_msg'] = "Member profile completely updated!";
            
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "Database Error: Could not update member. " . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = "Select a valid account status.";
    }
}
header("Location: ../" . $return_url);
exit;
?>