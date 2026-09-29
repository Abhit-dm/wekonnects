<?php
// actions/submit_renewal.php
session_start();
require_once '../config/database.php';

// Security check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: ../login.php"); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $payment_mode = filter_input(INPUT_POST, 'payment_mode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $reference_no = filter_input(INPUT_POST, 'reference_no', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $submitted_by = $_SESSION['user_id'];

    if ($user_id && $payment_mode) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pending_renewals (user_id, group_id, payment_mode, reference_no, status, submitted_by) VALUES (?, ?, ?, ?, 'Pending', ?)");
            $stmt->execute([$user_id, $group_id, $payment_mode, $reference_no, $submitted_by]);
            
            $_SESSION['success_msg'] = "Renewal payment successfully submitted to Admin for approval!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database Error. Please ensure the pending_renewals table has been created in your database.";
        }
    } else {
        $_SESSION['error_msg'] = "Submission failed: Missing required fields.";
    }
}

// Redirect back to the coordinator portal
header("Location: ../head_table.php");
exit;
?>