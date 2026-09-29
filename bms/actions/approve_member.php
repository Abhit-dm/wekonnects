<?php
// actions/approve_member.php
session_start();
require_once '../config/database.php';
require_once '../includes/mailer.php'; 

// Security: Admins only
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Captured Edited Info
    $first_name = trim(strip_tags($_POST['first_name']));
    $last_name = trim(strip_tags($_POST['last_name']));
    $phone = trim(strip_tags($_POST['phone']));
    $company_name = trim(strip_tags($_POST['company_name']));
    $category_name = trim(strip_tags($_POST['category']));

    if ($user_id && $group_id && !empty($category_name)) {
        try {
            if ($_SESSION['system_role'] === 'FRANCHISE_OWNER') {
                $stmtOwner = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND franchise_owner_id = ? AND status = 'Active'");
                $stmtOwner->execute([$group_id, $_SESSION['user_id']]);
                if (!$stmtOwner->fetchColumn()) {
                    throw new RuntimeException('You can only assign applicants to chapters owned by your franchise.');
                }
            } else {
                $stmtGroup = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND status = 'Active'");
                $stmtGroup->execute([$group_id]);
                if (!$stmtGroup->fetchColumn()) {
                    throw new RuntimeException('Select an active chapter.');
                }
            }

            $pdo->beginTransaction();

            // 1. Update User Record & Set to Active
            $stmtU = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, status = 'Active' WHERE id = ?");
            $stmtU->execute([$first_name, $last_name, $phone, $user_id]);

            // 2. Update Business Record
            $stmtB = $pdo->prepare("UPDATE businesses SET company_name = ?, business_category_applied = ? WHERE user_id = ?");
            $stmtB->execute([$company_name, $category_name, $user_id]);

            // 3. FIX FOR FOREIGN KEY CONSTRAINT: Get or Create the Category ID
            $stmtCatCheck = $pdo->prepare("SELECT id FROM business_categories WHERE category_name = ?");
            $stmtCatCheck->execute([$category_name]);
            $cat_id = $stmtCatCheck->fetchColumn();

            // If category doesn't exist, create it
            if (!$cat_id) {
                $stmtInsCat = $pdo->prepare("INSERT INTO business_categories (category_name) VALUES (?)");
                $stmtInsCat->execute([$category_name]);
                $cat_id = $pdo->lastInsertId();
            }

            // 4. Handle Chapter Assignment with the Category ID included
            $joining_date = date('Y-m-d');
            
            $stmtCheck = $pdo->prepare("SELECT id FROM group_members WHERE user_id = ?");
            $stmtCheck->execute([$user_id]);
            
            if ($stmtCheck->fetch()) {
                // Update existing row
                $stmtG = $pdo->prepare("UPDATE group_members SET group_id = ?, membership_status = 'Active', leadership_role = 'Member', joining_date = ?, business_category_id = ? WHERE user_id = ?");
                $stmtG->execute([$group_id, $joining_date, $cat_id, $user_id]);
            } else {
                // Insert new row
                $stmtG = $pdo->prepare("INSERT INTO group_members (user_id, group_id, membership_status, leadership_role, joining_date, business_category_id) VALUES (?, ?, 'Active', 'Member', ?, ?)");
                $stmtG->execute([$user_id, $group_id, $joining_date, $cat_id]);
            }

            $pdo->commit();
            $_SESSION['success_msg'] = "Member approved and added to chapter successfully!";
            
            // Send Welcome Email
            $stmtEmail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmtEmail->execute([$user_id]);
            $userEmail = $stmtEmail->fetchColumn();
            
            if ($userEmail) {
                $subject = "Your WE KONNECTS Application is Approved!";
                $body = "Congratulations $first_name!<br><br>Your application has been approved and you have been officially assigned to your chapter.<br><br><a href='https://official.wekonnects.com/login.php' class='btn'>Login to Dashboard</a>";
                sendWeKonnectsEmail($userEmail, $first_name, $subject, $body);
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "Database Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = "Missing required fields. Please ensure a chapter and category are selected.";
    }
}

header("Location: ../pending_applications.php");
exit;
?>