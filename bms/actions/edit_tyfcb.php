<?php
// actions/edit_tyfcb.php
session_start();
require_once '../config/database.php';

// Check if user is an Admin (either natively or via impersonation)
$is_admin = (isset($_SESSION['system_role']) && in_array($_SESSION['system_role'], ['SUPER_ADMIN', 'FRANCHISE_OWNER'])) || isset($_SESSION['impersonator_id']);

if (!$is_admin) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slip_id = filter_input(INPUT_POST, 'slip_id', FILTER_SANITIZE_NUMBER_INT);
    $new_amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_INT);

    if ($slip_id && $new_amount > 0) {
        try {
            // Update the slip amount
            $stmt = $pdo->prepare("UPDATE slips SET amount = ? WHERE id = ? AND slip_type = 'TYFCB'");
            $stmt->execute([$new_amount, $slip_id]);
            
            $_SESSION['success_msg'] = "The Deal amount has been successfully corrected.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database Error: Could not update the slip.";
        }
    } else {
        $_SESSION['error_msg'] = "Invalid data provided.";
    }
}

// Send them back to where they were
header("Location: ../my_activity.php");
exit;
?>