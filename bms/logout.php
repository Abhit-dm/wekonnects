<?php
// actions/logout.php
session_start();
require_once '../config/database.php';

// Check if a Super Admin is currently impersonating this user
if (isset($_SESSION['super_admin_id'])) {
    try {
        // Fetch the Super Admin's original details
        $sa_id = $_SESSION['super_admin_id'];
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, system_role FROM users WHERE id = ?");
        $stmt->execute([$sa_id]);
        $sa = $stmt->fetch();

        if ($sa) {
            // Restore God-Mode Session
            $_SESSION['user_id'] = $sa['id'];
            $_SESSION['first_name'] = $sa['first_name'];
            $_SESSION['last_name'] = $sa['last_name'];
            $_SESSION['system_role'] = $sa['system_role'];
            
            // Remove the impersonation flag so you don't get stuck in a loop
            unset($_SESSION['super_admin_id']);
            
            // Teleport back to the Global Directory
            header("Location: ../sa_master_directory.php");
            exit;
        }
    } catch (PDOException $e) {
        // If the database fails for any reason, let it fall through to a normal hard logout
    }
}

// NORMAL LOGOUT PROCESS (For regular users, or if you aren't impersonating anyone)
session_unset();
session_destroy();

header("Location: ../login.php");
exit;
?>