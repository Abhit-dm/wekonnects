<?php
// actions/sa_update_chapter_assignments.php
session_start();
require_once '../config/database.php';

// Strict Super Admin Check
if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id = filter_input(INPUT_POST, 'owner_id', FILTER_SANITIZE_NUMBER_INT);
    $chapters = isset($_POST['assigned_chapters']) ? $_POST['assigned_chapters'] : [];

    if ($owner_id) {
        try {
            $pdo->beginTransaction();

            // STEP 1: Unassign all chapters currently owned by this Franchise Owner.
            // This ensures if you UNCHECK a box, it is properly removed from them.
            $stmtReset = $pdo->prepare("UPDATE groups SET franchise_owner_id = NULL WHERE franchise_owner_id = ?");
            $stmtReset->execute([$owner_id]);

            // STEP 2: Assign the newly checked chapters to this owner.
            if (!empty($chapters)) {
                // Create a dynamic string of question marks based on how many boxes were checked (e.g., "?, ?, ?")
                $inQuery = implode(',', array_fill(0, count($chapters), '?'));
                
                // Update query
                $stmtAssign = $pdo->prepare("UPDATE groups SET franchise_owner_id = ? WHERE id IN ($inQuery)");
                
                // Merge the owner_id with the array of chapter IDs to bind the parameters securely
                $params = array_merge([$owner_id], $chapters);
                $stmtAssign->execute($params);
            }

            $pdo->commit();
            $_SESSION['success_msg'] = "Territories updated successfully!";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_msg'] = "Database Error: Could not assign chapters. " . $e->getMessage();
        }
    }
}

// Send the Super Admin back to the Franchise list
header("Location: ../sa_manage_franchises.php");
exit;
?>