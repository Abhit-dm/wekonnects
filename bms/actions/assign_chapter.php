<?php
// actions/assign_chapter.php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Default dates
    $join_date = date('Y-m-d');
    $renewal_date = date('Y-m-d', strtotime('+1 year')); 

    if ($user_id && $group_id && $category_id) {
        try {
            // Attempt to insert the member into the chapter
            $sql = "INSERT INTO group_members (group_id, user_id, business_category_id, join_date, renewal_date, current_tier, membership_status) 
                    VALUES (?, ?, ?, ?, ?, 'STAR', 'Active')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$group_id, $user_id, $category_id, $join_date, $renewal_date]);

            $_SESSION['success_msg'] = "Member successfully inducted into the chapter!";
            
        } catch (PDOException $e) {
            // Check if the error is the UNIQUE KEY violation (Category already taken in this chapter)
            if ($e->getCode() == 23000) {
                $_SESSION['error_msg'] = "Assignment Failed: That Business Category is already taken by another member in this chapter.";
            } else {
                $_SESSION['error_msg'] = "System Error: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_msg'] = "Invalid data submitted.";
    }
}

header("Location: ../directory.php");
exit;
?>