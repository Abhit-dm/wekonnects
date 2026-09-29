<?php
// actions/sa_save_category.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $category_name = trim(strip_tags($_POST['category_name']));
            $status = $_POST['status'];

            $stmt = $pdo->prepare("INSERT INTO business_categories (category_name, status) VALUES (?, ?)");
            $stmt->execute([$category_name, $status]);
            $_SESSION['success_msg'] = "Category added successfully!";
            
        } elseif ($action === 'toggle') {
            $category_id = intval($_POST['category_id']);
            $new_status = ($_POST['current_status'] === 'Active') ? 'Inactive' : 'Active';

            $stmt = $pdo->prepare("UPDATE business_categories SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $category_id]);
            $_SESSION['success_msg'] = "Category status updated!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
}

header("Location: ../sa_manage_categories.php");
exit;
?>