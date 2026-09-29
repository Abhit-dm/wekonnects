<?php
// actions/edit_visitor.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['system_role'], ['SUPER_ADMIN', 'FRANCHISE_OWNER'])) { 
    header("Location: ../login.php"); exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_id = filter_input(INPUT_POST, 'visitor_id', FILTER_SANITIZE_NUMBER_INT);
    $visitor_name = trim(strip_tags($_POST['visitor_name']));
    $company_name = trim(strip_tags($_POST['company_name']));
    $phone = trim(strip_tags($_POST['phone']));

    try {
        $stmt = $pdo->prepare("UPDATE visitors SET visitor_name = ?, company_name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$visitor_name, $company_name, $phone, $visitor_id]);
        $_SESSION['success_msg'] = "Visitor details successfully updated!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error updating visitor.";
    }
}
header("Location: ../manage_visitors.php");
exit;
?>