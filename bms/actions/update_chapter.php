<?php
// actions/update_chapter.php
session_start();
require_once '../config/database.php';

// Security Check
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = filter_input(INPUT_POST, 'action_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    try {
        // ADD NEW CHAPTER LOGIC
        if ($action_type === 'add') {
            $group_name = trim(strip_tags($_POST['group_name']));
            $default_venue = trim(strip_tags($_POST['default_venue']));
            $status = trim(strip_tags($_POST['status']));

            if (!empty($group_name)) {
                
                // FIX FOR FOREIGN KEY CONSTRAINT:
                // We must find the default Franchise ID to attach to this new chapter
                $franchise_id = $_SESSION['franchise_id'] ?? null;
                if (!$franchise_id) {
                    $stmtF = $pdo->query("SELECT id FROM franchises LIMIT 1");
                    $franchise_id = $stmtF->fetchColumn();
                    if (!$franchise_id) $franchise_id = 1; // Absolute fallback
                }

                // Insert with the franchise_id included
                $stmt = $pdo->prepare("INSERT INTO groups (franchise_id, group_name, default_venue, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$franchise_id, $group_name, $default_venue, $status]);
                
                $_SESSION['success_msg'] = "New chapter '$group_name' created successfully!";
            } else {
                $_SESSION['error_msg'] = "Chapter Name is required.";
            }
        } 
        // EDIT EXISTING CHAPTER LOGIC
        elseif ($action_type === 'edit') {
            $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
            $group_name = trim(strip_tags($_POST['group_name']));
            $default_venue = trim(strip_tags($_POST['default_venue']));
            $status = trim(strip_tags($_POST['status']));

            if ($group_id && !empty($group_name)) {
                $stmt = $pdo->prepare("UPDATE groups SET group_name = ?, default_venue = ?, status = ? WHERE id = ?");
                $stmt->execute([$group_name, $default_venue, $status, $group_id]);
                $_SESSION['success_msg'] = "Chapter updated successfully!";
            }
        } 
        // DELETE CHAPTER LOGIC
        elseif ($action_type === 'delete') {
            $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
            
            if ($group_id) {
                $pdo->beginTransaction();
                
                // 1. Remove all members from this chapter first to prevent database crashes
                $stmtClear = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
                $stmtClear->execute([$group_id]);
                
                // 2. Delete the chapter itself
                $stmtDel = $pdo->prepare("DELETE FROM groups WHERE id = ?");
                $stmtDel->execute([$group_id]);
                
                $pdo->commit();
                $_SESSION['success_msg'] = "Chapter deleted successfully.";
            }
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        $_SESSION['error_msg'] = "System Error: Could not update chapter. " . $e->getMessage();
    }
}

header("Location: ../manage_chapters.php");
exit;
?>