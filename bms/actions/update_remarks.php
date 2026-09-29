<?php
// actions/update_remarks.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_id = filter_input(INPUT_POST, 'visitor_id', FILTER_SANITIZE_NUMBER_INT);
    $remarks = trim(strip_tags($_POST['remarks']));
    $user_id = $_SESSION['user_id'];

    if ($visitor_id) {
        // Ensure they only update remarks for visitors assigned to THEM
        $stmt = $pdo->prepare("UPDATE visitors SET remarks = ? WHERE id = ? AND assigned_to = ?");
        $stmt->execute([$remarks, $visitor_id, $user_id]);
        $_SESSION['success_msg'] = "Visitor remarks updated successfully!";
    }
}
header("Location: ../index.php");
exit;
?>