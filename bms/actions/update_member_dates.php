<?php
// actions/update_member_dates.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $chapter_id = filter_input(INPUT_POST, 'chapter_id', FILTER_SANITIZE_NUMBER_INT);
    $joining_date = $_POST['joining_date'];
    $renewal_date = $_POST['renewal_date'];

    try {
        $stmt = $pdo->prepare("UPDATE group_members SET joining_date = ?, renewal_date = ? WHERE user_id = ? AND group_id = ?");
        $stmt->execute([$joining_date, $renewal_date, $user_id, $chapter_id]);
        $_SESSION['success_msg'] = "Member joining and renewal dates updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['error_msg'] = "Error updating dates.";
    }
}

header("Location: ../admin_directory.php?chapter_id=" . $_POST['chapter_id']);
exit;
?>