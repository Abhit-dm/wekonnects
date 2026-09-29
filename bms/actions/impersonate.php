<?php
// actions/impersonate.php
session_start();
require_once '../config/database.php';

// Only Super Admins can use this power
if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = filter_input(INPUT_POST, 'target_user_id', FILTER_SANITIZE_NUMBER_INT);
    
    if ($target_id) {
        try {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, system_role FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $user = $stmt->fetch();

            if ($user) {
                // Save the Super Admin ID so we can return to it later
                $_SESSION['super_admin_id'] = $_SESSION['user_id'];
                
                // Overwrite the session to become the target user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['system_role'] = $user['system_role'];
                
                header("Location: ../index.php"); // Send them to the member dashboard
                exit;
            }
        } catch (PDOException $e) {
            die("Error initializing impersonation.");
        }
    }
}
header("Location: ../sa_master_directory.php");
exit;
?>