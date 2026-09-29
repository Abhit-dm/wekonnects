<?php
// actions/submit_ticket.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $subject = trim(strip_tags($_POST['subject']));
    $message = trim(strip_tags($_POST['message']));

    if (empty($subject) || empty($message)) {
        $_SESSION['error_msg'] = "Please fill out all fields.";
        header("Location: ../support.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'Open')");
        $stmt->execute([$user_id, $subject, $message]);
        
        $_SESSION['success_msg'] = "Your ticket has been submitted to Headquarters successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
}

header("Location: ../support.php");
exit;
?>