<?php
// actions/update_role.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $chapter_id = filter_input(INPUT_POST, 'chapter_id', FILTER_SANITIZE_NUMBER_INT);
    $role = trim(strip_tags($_POST['leadership_role']));

    if ($user_id && ($role === 'Member' || $role === 'Coordinator')) {
        try {
            $stmt = $pdo->prepare("UPDATE group_members SET leadership_role = ? WHERE user_id = ?");
            $stmt->execute([$role, $user_id]);
            $_SESSION['success_msg'] = "Member role successfully updated.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database Error: Could not update role.";
        }
    }
}
header("Location: ../admin_directory.php?chapter_id=" . $chapter_id);
exit;
?>