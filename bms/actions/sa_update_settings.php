<?php
// actions/sa_update_settings.php
session_start();
require_once '../config/database.php';

// Strict Super Admin Check
if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings'])) {
    try {
        $pdo->beginTransaction();
        
        // Prepare the update statement
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");

        // Loop through all submitted settings and update them
        foreach ($_POST['settings'] as $key => $value) {
            $clean_value = trim(strip_tags($value));
            $clean_key = trim(strip_tags($key));
            
            $stmt->execute([$clean_value, $clean_key]);
        }

        $pdo->commit();
        $_SESSION['success_msg'] = "Global system variables updated successfully!";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
}

header("Location: ../sa_system_settings.php");
exit;
?>