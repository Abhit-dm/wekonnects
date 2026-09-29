<?php
// actions/update_category.php
session_start();
require_once '../config/database.php';

// Security Check
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = filter_input(INPUT_POST, 'action_type', FILTER_SANITIZE_STRING);

    try {
        if ($action_type === 'add') {
            $category_name = trim(strip_tags($_POST['category_name']));
            
            // Prevent duplicates
            $stmtCheck = $pdo->prepare("SELECT id FROM business_categories WHERE category_name = ?");
            $stmtCheck->execute([$category_name]);
            if ($stmtCheck->fetch()) {
                $_SESSION['error_msg'] = "That category already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO business_categories (category_name) VALUES (?)");
                $stmt->execute([$category_name]);
                $_SESSION['success_msg'] = "Category added successfully!";
            }
        } 
        elseif ($action_type === 'delete') {
            $category_id = filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT);
            $stmtDel = $pdo->prepare("DELETE FROM business_categories WHERE id = ?");
            $stmtDel->execute([$category_id]);
            $_SESSION['success_msg'] = "Category deleted successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "System Error: Could not update category.";
    }
}
header("Location: ../manage_categories.php");
exit;
?>