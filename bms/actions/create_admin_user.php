<?php
// actions/create_admin_user.php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize inputs
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name  = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $email      = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone      = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password   = $_POST['password']; 
    $new_role   = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $created_by = $_SESSION['user_id'];
    
    // Determine where to redirect back to
    $return_url = isset($_POST['return_url']) ? $_POST['return_url'] : '../admin.php';

    // Security check: Only SUPER_ADMIN can create other SUPER_ADMINs, FRANCHISE_OWNERs, or EMPLOYEES
    if ($_SESSION['system_role'] !== 'SUPER_ADMIN' && ($new_role === 'SUPER_ADMIN' || $new_role === 'FRANCHISE_OWNER' || strpos($new_role, 'EMP_') === 0)) {
        $_SESSION['error_msg'] = "You do not have permission to create this role type.";
        header("Location: " . $return_url);
        exit;
    }

    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Insert into the master users table
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password_hash, system_role, created_by_user_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash, $new_role, $created_by]);

        // Success
        $_SESSION['success_msg'] = "Account for {$first_name} successfully created as " . str_replace('EMP_', '', $new_role) . ".";
        header("Location: " . $return_url);
        exit;

    } catch (PDOException $e) {
        // Handle duplicate email error
        if ($e->getCode() == 23000) {
            $_SESSION['error_msg'] = "An account with that email or phone number already exists.";
        } else {
            error_log("Admin Creation Error: " . $e->getMessage());
            $_SESSION['error_msg'] = "Database error. Check error logs.";
        }
        header("Location: " . $return_url);
        exit;
    }
} else {
    header("Location: ../super_admin.php");
    exit;
}
?>