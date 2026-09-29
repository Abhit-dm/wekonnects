<?php
// actions/add_category.php
session_start();
require_once '../config/database.php';

// Security check
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = trim(strip_tags($_POST['category_name']));

    if (!empty($category_name)) {
        try {
            // Check if it already exists (case-insensitive)
            $stmtCheck = $pdo->prepare("SELECT id FROM business_categories WHERE LOWER(category_name) = LOWER(?)");
            $stmtCheck->execute([$category_name]);
            
            if ($stmtCheck->rowCount() == 0) {
                // Insert new category
                $stmtInsert = $pdo->prepare("INSERT INTO business_categories (category_name) VALUES (?)");
                $stmtInsert->execute([$category_name]);
                $_SESSION['success_msg'] = "Category '$category_name' added successfully!";
            } else {
                $_SESSION['error_msg'] = "The category '$category_name' already exists.";
            }

        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "System Error: Could not add category.";
        }
    } else {
        $_SESSION['error_msg'] = "Category name cannot be empty.";
    }
}

header("Location: ../manage_categories.php");
exit;
?>