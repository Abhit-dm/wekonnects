<?php
// actions/add_chapter.php
session_start();
require_once '../config/database.php';

// Security check
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_name = trim(strip_tags($_POST['group_name']));
    $meeting_day = filter_input(INPUT_POST, 'meeting_day', FILTER_SANITIZE_STRING);
    $meeting_time = filter_input(INPUT_POST, 'meeting_time', FILTER_SANITIZE_STRING);
    $default_venue = trim(strip_tags($_POST['default_venue']));
    
    // We assume franchise_id is 1 for the master WE KONNECTS franchise
    $franchise_id = 1; 

    if (!empty($group_name) && $meeting_day && $meeting_time && !empty($default_venue)) {
        try {
            $sql = "INSERT INTO groups (franchise_id, group_name, meeting_day, meeting_time, default_venue, status) VALUES (?, ?, ?, ?, ?, 'Active')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$franchise_id, $group_name, $meeting_day, $meeting_time, $default_venue]);
            
            $_SESSION['success_msg'] = "Chapter '$group_name' successfully launched!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "System Error: Could not launch chapter.";
        }
    }
}

header("Location: ../manage_chapters.php");
exit;
?>