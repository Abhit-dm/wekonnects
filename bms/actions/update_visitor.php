<?php
// actions/update_visitor.php
session_start();
require_once '../config/database.php';
require_once '../includes/mailer.php'; // Load our email engine!

if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_id = filter_input(INPUT_POST, 'visitor_id', FILTER_SANITIZE_NUMBER_INT);
    $action_type = filter_input(INPUT_POST, 'action_type', FILTER_SANITIZE_STRING);

    if ($visitor_id && $action_type) {
        try {
            $stmtVisitor = $pdo->prepare("SELECT chapter_id FROM visitors WHERE id = ?");
            $stmtVisitor->execute([$visitor_id]);
            $visitor_chapter_id = $stmtVisitor->fetchColumn();
            $role = $_SESSION['system_role'] ?? 'MEMBER';
            $authorized = false;

            if ($visitor_chapter_id && in_array($role, ['SUPER_ADMIN', 'EMP_VISITOR_MANAGER'], true)) {
                $authorized = true;
            } elseif ($visitor_chapter_id && $role === 'FRANCHISE_OWNER') {
                $stmtOwner = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND franchise_owner_id = ?");
                $stmtOwner->execute([$visitor_chapter_id, $user_id]);
                $authorized = (bool)$stmtOwner->fetchColumn();
            } elseif ($visitor_chapter_id) {
                $stmtVerify = $pdo->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active'");
                $stmtVerify->execute([$user_id, $visitor_chapter_id]);
                $authorized = (bool)$stmtVerify->fetchColumn();
            }

            if (!$authorized) die("Unauthorized access.");
            $coord = ['group_id' => $visitor_chapter_id];

            // 1. Handle Member Assignment
            if ($action_type === 'assign') {
                $assigned_to = filter_input(INPUT_POST, 'assigned_to', FILTER_SANITIZE_NUMBER_INT);
                if (empty($assigned_to)) $assigned_to = null;

                if ($assigned_to) {
                    $stmtAssignee = $pdo->prepare("SELECT id FROM group_members WHERE user_id = ? AND group_id = ? AND membership_status = 'Active'");
                    $stmtAssignee->execute([$assigned_to, $coord['group_id']]);
                    if (!$stmtAssignee->fetchColumn()) throw new RuntimeException('Select an active member from this chapter.');
                }

                $stmtUpdate = $pdo->prepare("UPDATE visitors SET assigned_to = ? WHERE id = ? AND chapter_id = ?");
                $stmtUpdate->execute([$assigned_to, $visitor_id, $coord['group_id']]);
                $_SESSION['success_msg'] = "Visitor assignment updated!";
            }

            // 2. Handle Status Update & Registration Email
            if ($action_type === 'status') {
                $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);
                
                if (in_array($new_status, ['Attended', 'Joined'], true)) {
                    $stmtCurrent = $pdo->prepare("SELECT attended FROM visitors WHERE id = ? AND chapter_id = ?");
                    $stmtCurrent->execute([$visitor_id, $coord['group_id']]);
                    $is_attended = (int)$stmtCurrent->fetchColumn() === 1;
                    if ($new_status === 'Joined' && !$is_attended) {
                        throw new RuntimeException('Mark the visitor present at roll call before confirming they joined.');
                    }

                    $stmtUpdate = $pdo->prepare("UPDATE visitors SET status = ?, attended = CASE WHEN ? = 'Attended' THEN 1 ELSE attended END WHERE id = ? AND chapter_id = ?");
                    $stmtUpdate->execute([$new_status, $new_status, $visitor_id, $coord['group_id']]);
                    $_SESSION['success_msg'] = "Visitor status updated to " . strtoupper($new_status) . "!";

                    // AUTO-EMAIL TRIGGER: If Joined, send registration link!
                    if ($new_status === 'Joined') {
                        // Get Visitor Details
                        $stmtVis = $pdo->prepare("SELECT visitor_name, email FROM visitors WHERE id = ?");
                        $stmtVis->execute([$visitor_id]);
                        $vis = $stmtVis->fetch();

                        if ($vis && !empty($vis['email'])) {
                            $reg_link = "https://official.wekonnects.com/register.php"; // Update with your actual domain
                            $subject = "Welcome to WE KONNECTS!";
                            $body = "Congratulations on taking the first step to growing your business with WE KONNECTS!<br><br>The Head Table has officially approved your application. Please click the button below to complete your digital profile and setup your account:<br><br><a href='$reg_link' class='btn'>Complete Registration</a><br><br>We look forward to seeing you at the next meeting!";
                            
                            sendWeKonnectsEmail($vis['email'], $vis['visitor_name'], $subject, $body);
                            $_SESSION['success_msg'] .= " Registration link emailed to visitor!";
                        }
                    }
                }
            }

        } catch (Throwable $e) {
            $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "System Error: Could not update visitor.";
        }
    }
}
header("Location: ../visitor_manager.php");
exit;
?>