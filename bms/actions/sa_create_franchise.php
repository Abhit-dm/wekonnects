<?php
// actions/sa_create_franchise.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim(strip_tags($_POST['first_name']));
    $last_name = trim(strip_tags($_POST['last_name']));
    $email = trim(strip_tags($_POST['email']));
    $phone = trim(strip_tags($_POST['phone']));
    $password = $_POST['password'];
    
    // Hash the password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Check if email already exists
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            $_SESSION['error_msg'] = "Error: A user with this email already exists.";
        } else {
            // Using password_hash as requested
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, system_role, status) VALUES (?, ?, ?, ?, ?, 'FRANCHISE_OWNER', 'Active')");
            $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password]);
            
            $_SESSION['success_msg'] = "New Franchise Owner created successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
}

header("Location: ../sa_manage_franchises.php");
exit;
?>