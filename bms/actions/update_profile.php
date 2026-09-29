<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: ../login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    $first_name = trim(strip_tags($_POST['first_name']));
    $last_name = trim(strip_tags($_POST['last_name']));
    $phone = trim(strip_tags($_POST['phone']));
    $blood_group = trim(strip_tags($_POST['blood_group']));
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $anniversary = !empty($_POST['anniversary_date']) ? $_POST['anniversary_date'] : null;

    $company_name = trim(strip_tags($_POST['company_name']));
    $target_audience = trim(strip_tags($_POST['target_audience']));
    $ideal_referral = trim(strip_tags($_POST['ideal_referral']));
    $top_products = trim(strip_tags($_POST['top_products']));

    // Handle Photo Upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['profile_photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = $user_id . '_' . time() . '.' . $ext;
            $upload_path = '../assets/uploads/profiles/' . $new_filename;
            
            // Ensure folder exists
            if(!is_dir('../assets/uploads/profiles/')) mkdir('../assets/uploads/profiles/', 0777, true);
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                $stmtImg = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                $stmtImg->execute([$new_filename, $user_id]);
                $_SESSION['profile_photo'] = $new_filename; // Update session
            }
        }
    }

    try {
        $pdo->beginTransaction();
        $stmtU = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, blood_group=?, dob=?, anniversary_date=? WHERE id=?");
        $stmtU->execute([$first_name, $last_name, $phone, $blood_group, $dob, $anniversary, $user_id]);

        $stmtB = $pdo->prepare("UPDATE businesses SET company_name=?, target_audience=?, ideal_referral=?, top_products=? WHERE user_id=?");
        $stmtB->execute([$company_name, $target_audience, $ideal_referral, $top_products, $user_id]);
        
        $pdo->commit();
        $_SESSION['success_msg'] = "Profile updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
    }
}
header("Location: ../profile.php");
exit;
?>