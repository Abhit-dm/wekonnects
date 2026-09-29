<?php
// actions/login_action.php
session_start();
require_once '../config/database.php';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Grab the email and password from the login form
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Fetch the user from the database
    $stmt = $pdo->prepare("SELECT id, first_name, password_hash, system_role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify User exists AND Password is correct
    if ($user && password_verify($password, $user['password_hash'])) {
        
        // SECURITY: Regenerate session ID to prevent hacking
        session_regenerate_id(true);

        // Log them in by saving data to the session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['system_role'] = $user['system_role'];
        $_SESSION['logged_in'] = true;

        // Route them to the correct dashboard based on their role
        if ($user['system_role'] === 'SUPER_ADMIN') {
            header("Location: ../super_admin.php");
            exit;
        } elseif ($user['system_role'] === 'FRANCHISE_OWNER') {
            header("Location: ../admin.php");
            exit;
        } elseif ($user['system_role'] === 'EMP_VISITOR_MANAGER') {
            header("Location: ../emp_visitor_dashboard.php");
            exit;
        } else {
            header("Location: ../index.php"); 
            exit;
        }

    } else {
        // Auth failed - kick them back to login page
        $_SESSION['error_message'] = "Invalid email or password.";
        header("Location: ../login.php");
        exit;
    }
} else {
    // If someone tries to visit this file directly, send them away
    header("Location: ../login.php");
    exit;
}
?>