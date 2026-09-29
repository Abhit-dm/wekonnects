<?php
// actions/delete_meeting.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_id = filter_input(INPUT_POST, 'meeting_id', FILTER_SANITIZE_NUMBER_INT);

    try {
        // Also deletes any attendance tied to this meeting automatically
        $pdo->query("DELETE FROM attendance WHERE meeting_id = $meeting_id");
        $pdo->query("DELETE FROM chapter_meetings WHERE id = $meeting_id");
        
        $_SESSION['success_msg'] = "Meeting successfully deleted.";
    } catch (PDOException $e) {
        $_SESSION['success_msg'] = "Error deleting meeting.";
    }
}
header("Location: ../attendance_manager.php");
exit;
?>