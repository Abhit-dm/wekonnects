<?php
// actions/sa_add_setting.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = trim(strip_tags($_POST['setting_key']));
    $label = trim(strip_tags($_POST['setting_label']));
    $value = trim(strip_tags($_POST['setting_value']));
    $group = trim(strip_tags($_POST['setting_group']));

    // Ensure the key is lowercase and has no spaces
    $key = strtolower(str_replace(' ', '_', $key));

    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_label, setting_value, setting_group) VALUES (?, ?, ?, ?)");
        $stmt->execute([$key, $label, $value, $group]);
        $_SESSION['success_msg'] = "New setting variable added successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error: Could not add setting. Does the key already exist?";
    }
}
header("Location: ../sa_system_settings.php");
exit;
?>