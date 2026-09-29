<?php
// actions/sa_delete_broadcast.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $broadcast_id = filter_input(INPUT_POST, 'broadcast_id', FILTER_SANITIZE_NUMBER_INT);
    
    if ($broadcast_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM system_broadcasts WHERE id = ?");
            $stmt->execute([$broadcast_id]);
            $_SESSION['success_msg'] = "Announcement removed successfully.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database Error: Could not delete broadcast.";
        }
    }
}
header("Location: ../sa_broadcast.php");
exit;
?>