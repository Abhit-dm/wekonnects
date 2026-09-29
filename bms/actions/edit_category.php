<?php
// actions/edit_category.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['category_id']);
    $name = trim(strip_tags($_POST['category_name']));

    if ($id && !empty($name)) {
        try {
            $stmt = $pdo->prepare("UPDATE business_categories SET category_name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $_SESSION['success_msg'] = "Category updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Error updating category.";
        }
    }
}
header("Location: ../manage_categories.php");
exit;
?>