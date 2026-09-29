<?php
// actions/update_forgotten_password.php

// Turn on error reporting to prevent blank screens
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: ../reset_password.php?token=" . $token);
        exit;
    }

    if (strlen($new_password) < 6) {
        $_SESSION['error_message'] = "Password must be at least 6 characters long.";
        header("Location: ../reset_password.php?token=" . $token);
        exit;
    }

    try {
        // Verify token one last time to be safe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // UPDATED: Using 'password_hash' column
            $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $stmtUpdate->execute([$hashed_password, $user['id']]);

            $_SESSION['success_message'] = "Your password has been successfully reset! You can now log in.";
            header("Location: ../login.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Invalid or expired token. Please request a new one.";
            header("Location: ../reset_password.php?token=" . $token);
            exit;
        }
    } catch (PDOException $e) {
        // If the database fails, it will show the error instead of a blank screen
        $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
        header("Location: ../reset_password.php?token=" . $token);
        exit;
    }
}

// Failsafe redirect
header("Location: ../login.php");
exit;
?>