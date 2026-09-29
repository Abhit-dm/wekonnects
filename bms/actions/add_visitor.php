<?php
// actions/log_visitor.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_name = trim(strip_tags($_POST['visitor_name']));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $company_name = trim(strip_tags($_POST['company_name']));
    $phone = trim(strip_tags($_POST['phone']));
    $visit_date = filter_input(INPUT_POST, 'visit_date', FILTER_SANITIZE_STRING);

    if ($visitor_name && $visit_date) {
        try {
            // Get the user's current chapter
            $stmtGroup = $pdo->prepare("SELECT group_id FROM group_members WHERE user_id = ? AND membership_status = 'Active'");
            $stmtGroup->execute([$user_id]);
            $group = $stmtGroup->fetch();

            if ($group) {
                $sql = "INSERT INTO visitors (chapter_id, invited_by, visitor_name, email, company_name, phone, visit_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$group['group_id'], $user_id, $visitor_name, $email, $company_name, $phone, $visit_date]);
                
                $_SESSION['success_msg'] = "Visitor successfully registered!";
            } else {
                $_SESSION['error_msg'] = "You must be in an active chapter to invite visitors.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "System Error: Could not register visitor.";
        }
    }
}
header("Location: ../index.php");
exit;
?>