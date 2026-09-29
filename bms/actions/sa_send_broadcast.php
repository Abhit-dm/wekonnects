<?php
// actions/sa_send_broadcast.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(strip_tags($_POST['title']));
    $message = trim(strip_tags($_POST['message']));
    $audience = $_POST['target_audience'];
    
    // Handle optional dates
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO system_broadcasts (title, message, target_audience, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $message, $audience, $start_date, $end_date]);
        $_SESSION['success_msg'] = "Announcement broadcasted successfully to " . str_replace('_', ' ', $audience) . "!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
}
header("Location: ../sa_broadcast.php");
exit;
?>