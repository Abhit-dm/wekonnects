<?php
// actions/update_meeting.php
session_start();
require_once '../config/database.php';

// Security check: Must be logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: ../login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = filter_input(INPUT_POST, 'action_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $meeting_id = filter_input(INPUT_POST, 'meeting_id', FILTER_SANITIZE_NUMBER_INT);
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);

    // 1. Verify User is actually a Coordinator for this group (Security Layer)
    $stmtVerify = $pdo->prepare("SELECT id FROM group_members WHERE user_id = ? AND group_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active'");
    $stmtVerify->execute([$user_id, $group_id]);
    
    if (!$stmtVerify->fetch()) {
        $_SESSION['error_msg'] = "Access Denied. You do not have permission to modify events for this chapter.";
        header("Location: ../head_table.php");
        exit;
    }

    try {
        if ($action_type === 'edit') {
            $meeting_type = trim(strip_tags($_POST['meeting_type']));
            $meeting_date = trim(strip_tags($_POST['meeting_date']));
            $venue = trim(strip_tags($_POST['venue']));
            
            // Securely capture and encode the array of feature speakers
            $feature_speakers = null;
            if (isset($_POST['feature_speakers']) && is_array($_POST['feature_speakers'])) {
                $sanitized_speakers = array_map('intval', $_POST['feature_speakers']);
                $feature_speakers = json_encode($sanitized_speakers);
            }

            $stmtUpdate = $pdo->prepare("UPDATE chapter_meetings SET meeting_type = ?, meeting_date = ?, venue = ?, feature_speakers = ? WHERE id = ? AND group_id = ?");
            $stmtUpdate->execute([$meeting_type, $meeting_date, $venue, $feature_speakers, $meeting_id, $group_id]);
            
            $_SESSION['success_msg'] = "Event updated successfully!";

        } elseif ($action_type === 'delete') {
            $pdo->beginTransaction();
            
            // Delete attendance records attached to this meeting first to prevent orphaned data
            $stmtDelAtt = $pdo->prepare("DELETE FROM attendance WHERE meeting_id = ?");
            $stmtDelAtt->execute([$meeting_id]);
            
            // Delete the actual meeting
            $stmtDelMtg = $pdo->prepare("DELETE FROM chapter_meetings WHERE id = ? AND group_id = ?");
            $stmtDelMtg->execute([$meeting_id, $group_id]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Event completely deleted.";
            
        } elseif ($action_type === 'complete') {
            // NEW: Mark event as completed to lock attendance
            $stmtComplete = $pdo->prepare("UPDATE chapter_meetings SET status = 'Completed' WHERE id = ? AND group_id = ?");
            $stmtComplete->execute([$meeting_id, $group_id]);
            $_SESSION['success_msg'] = "Event marked as Completed! Attendance is now locked.";
        }
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        $_SESSION['error_msg'] = "Database Error: Could not modify the event.";
    }
}

header("Location: ../head_table.php");
exit;
?>