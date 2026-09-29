<?php
// actions/update_my_profile.php

// Turn on error reporting to catch any hidden crashes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';

// Security check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: ../login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize basic inputs safely
    $first_name = trim(strip_tags($_POST['first_name'] ?? ''));
    $last_name = trim(strip_tags($_POST['last_name'] ?? ''));
    $phone = trim(strip_tags($_POST['phone'] ?? ''));
    $blood_group = trim(strip_tags($_POST['blood_group'] ?? ''));
    $dob = !empty($_POST['dob']) ? trim(strip_tags($_POST['dob'])) : null;
    $anniversary_date = !empty($_POST['anniversary_date']) ? trim(strip_tags($_POST['anniversary_date'])) : null;
    
    $company_name = trim(strip_tags($_POST['company_name'] ?? ''));
    $target_audience = trim(strip_tags($_POST['target_audience'] ?? ''));
    $ideal_referral = trim(strip_tags($_POST['ideal_referral'] ?? ''));
    $top_products = trim(strip_tags($_POST['top_products'] ?? ''));

    // Password fields
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        // Start transaction - everything must succeed together
        $pdo->beginTransaction();

        // 1. Handle Password Update First
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $_SESSION['error_msg'] = "Passwords do not match. Please try again.";
                header("Location: ../edit_my_profile.php");
                exit;
            }
            
            // Hash the new password securely
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // UPDATED: Using 'password_hash' column
            $stmtPwd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtPwd->execute([$hashed_password, $user_id]);
        }

        // 2. Update Core User Details
        $stmtUser = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, blood_group = ?, dob = ?, anniversary_date = ? WHERE id = ?");
        $stmtUser->execute([$first_name, $last_name, $phone, $blood_group, $dob, $anniversary_date, $user_id]);
        
        // Also update session name so the UI changes instantly
        $_SESSION['first_name'] = $first_name;

        // 3. Update Business Details
        $stmtCheckBiz = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
        $stmtCheckBiz->execute([$user_id]);
        
        if ($stmtCheckBiz->fetch()) {
            // Update existing
            $stmtBiz = $pdo->prepare("UPDATE businesses SET company_name = ?, target_audience = ?, ideal_referral = ?, top_products = ? WHERE user_id = ?");
            $stmtBiz->execute([$company_name, $target_audience, $ideal_referral, $top_products, $user_id]);
        } else {
            // Insert new if missing
            $stmtBiz = $pdo->prepare("INSERT INTO businesses (user_id, company_name, target_audience, ideal_referral, top_products) VALUES (?, ?, ?, ?, ?)");
            $stmtBiz->execute([$user_id, $company_name, $target_audience, $ideal_referral, $top_products]);
        }

        // 4. Handle Profile Photo Upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_tmp = $_FILES['profile_photo']['tmp_name'];
            
            // Check if mime_content_type exists, otherwise fallback to standard file check
            $file_type = function_exists('mime_content_type') ? mime_content_type($file_tmp) : $_FILES['profile_photo']['type'];

            if (in_array($file_type, $allowed_types)) {
                $upload_dir = '../assets/uploads/profiles/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

                $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    // Get old photo to delete it
                    $stmtOldPhoto = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
                    $stmtOldPhoto->execute([$user_id]);
                    $old_photo = $stmtOldPhoto->fetchColumn();

                    if ($old_photo && $old_photo !== 'default.png' && file_exists($upload_dir . $old_photo)) {
                        unlink($upload_dir . $old_photo);
                    }

                    // Save new photo name to DB
                    $stmtPhoto = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $stmtPhoto->execute([$new_filename, $user_id]);
                }
            } else {
                $_SESSION['error_msg'] = "Invalid image format. Only JPG, PNG, and GIF allowed.";
            }
        }

        // Commit all changes to the database
        $pdo->commit();
        
        // If an error wasn't set by the image upload, set success message
        if (!isset($_SESSION['error_msg'])) {
            $_SESSION['success_msg'] = "Profile and security settings updated successfully!";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        // This will print the EXACT reason it failed if there is a database issue
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
}

// Safely redirect back to the Edit Profile page so the user sees the success/error message
header("Location: ../edit_my_profile.php");
exit;
?>