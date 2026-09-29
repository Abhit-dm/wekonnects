<?php
// actions/update_chapter_targets.php
session_start();
require_once '../config/database.php';

// Security check: Must be Head Table or Admin
if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $target_members = filter_input(INPUT_POST, 'target_members', FILTER_SANITIZE_NUMBER_INT);
    $target_links = filter_input(INPUT_POST, 'target_links', FILTER_SANITIZE_NUMBER_INT);
    $target_revenue = filter_input(INPUT_POST, 'target_revenue', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    try {
        // Update the groups table
        $stmt = $pdo->prepare("UPDATE groups SET target_members = ?, target_links = ?, target_revenue = ? WHERE id = ?");
        $stmt->execute([$target_members, $target_links, $target_revenue, $group_id]);

        $_SESSION['success_msg'] = "Chapter targets updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error updating targets.";
    }
}
header("Location: ../index.php");
exit;
?>