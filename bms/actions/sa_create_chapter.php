<?php
// actions/sa_create_chapter.php
session_start();
require_once '../config/database.php';

// Strict Super Admin Check
if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_name = trim(strip_tags($_POST['group_name']));
    $franchise_owner_id = !empty($_POST['franchise_owner_id']) ? intval($_POST['franchise_owner_id']) : NULL;
    
    try {
        // Create the chapter
        $stmt = $pdo->prepare("INSERT INTO groups (group_name, franchise_owner_id, status) VALUES (?, ?, 'Active')");
        $stmt->execute([$group_name, $franchise_owner_id]);
        
        $_SESSION['success_msg'] = "Success! The chapter '$group_name' has been launched.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
}

header("Location: ../sa_manage_chapters.php");
exit;
?>