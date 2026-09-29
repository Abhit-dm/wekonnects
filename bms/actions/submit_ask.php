<?php
// actions/submit_ask.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $ask_text = trim(strip_tags($_POST['ask_text']));

    if (!empty($ask_text) && $group_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO member_asks (user_id, group_id, ask_text) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $group_id, $ask_text]);
            $_SESSION['success_msg'] = "Requirement posted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Error posting requirement.";
        }
    }
}
header("Location: ../index.php");
exit;
?>