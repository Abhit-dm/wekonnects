<?php
// actions/sa_upload_resource.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resource_file'])) {
    $title = trim(strip_tags($_POST['title']));
    $category = $_POST['category'];
    
    // Ensure this directory exists!
    $target_dir = "../assets/uploads/resources/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

    $file_ext = strtolower(pathinfo($_FILES["resource_file"]["name"], PATHINFO_EXTENSION));
    $new_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9]/", "", $title) . "." . $file_ext;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($_FILES["resource_file"]["tmp_name"], $target_file)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO system_resources (title, file_name, category) VALUES (?, ?, ?)");
            $stmt->execute([$title, $new_filename, $category]);
            $_SESSION['success_msg'] = "Document uploaded successfully!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = "Sorry, there was an error uploading your file.";
    }
}

header("Location: ../sa_resource_library.php");
exit;
?>