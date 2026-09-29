<?php
// actions/save_attendance.php
session_start();
require_once '../config/database.php';

// Strict security check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: ../login.php"); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meeting_id = filter_input(INPUT_POST, 'meeting_id', FILTER_SANITIZE_NUMBER_INT);
    $attendance = $_POST['attendance'] ?? [];
    $subs = $_POST['substitute_name'] ?? [];
    $awards = $_POST['awards'] ?? [];

    if (!$meeting_id) {
        $_SESSION['error_message'] = "Invalid Meeting ID.";
        header("Location: ../attendance_manager.php");
        exit;
    }

    try {
        $stmtRole = $pdo->prepare("SELECT group_id FROM group_members WHERE user_id = ? AND leadership_role IN ('Coordinator', 'Attendance') AND membership_status = 'Active' LIMIT 1");
        $stmtRole->execute([$_SESSION['user_id']]);
        $authorized_group_id = $stmtRole->fetchColumn();

        $stmtMeeting = $pdo->prepare("SELECT group_id, meeting_date FROM chapter_meetings WHERE id = ? AND meeting_type IN ('Meeting', 'Event', 'SOM')");
        $stmtMeeting->execute([$meeting_id]);
        $meeting = $stmtMeeting->fetch();

        if (!$authorized_group_id || !$meeting || (int)$meeting['group_id'] !== (int)$authorized_group_id) {
            throw new RuntimeException('You cannot save attendance for this chapter meeting.');
        }
        if ($meeting['meeting_date'] > date('Y-m-d')) {
            throw new RuntimeException('Attendance opens on the scheduled meeting day.');
        }
        $stmtNewerCompleted = $pdo->prepare("SELECT COUNT(*) FROM chapter_meetings WHERE group_id = ? AND meeting_type IN ('Meeting', 'Event', 'SOM') AND meeting_date > ? AND status = 'Completed'");
        $stmtNewerCompleted->execute([$authorized_group_id, $meeting['meeting_date']]);
        if ($stmtNewerCompleted->fetchColumn() > 0) {
            throw new RuntimeException('This meeting is locked because a later meeting is already complete.');
        }
        if (!is_array($attendance) || empty($attendance)) {
            throw new RuntimeException('Select attendance for at least one member before saving.');
        }

        $stmtMembers = $pdo->prepare("SELECT user_id, COALESCE(NULLIF(joining_date, '0000-00-00'), join_date) as member_start_date FROM group_members WHERE group_id = ? AND membership_status = 'Active'");
        $stmtMembers->execute([$authorized_group_id]);
        $active_members = [];
        foreach ($stmtMembers->fetchAll() as $member) {
            $active_members[$member['user_id']] = $member['member_start_date'];
        }

        $pdo->beginTransaction();
        $saved_count = 0;

        foreach ($attendance as $user_id => $status) {
            if (!isset($active_members[$user_id]) || $active_members[$user_id] > $meeting['meeting_date'] || !in_array($status, ['Present', 'Absent', 'Late', 'Substitute'], true)) {
                continue;
            }

            $sub_name = ($status === 'Substitute') ? trim(strip_tags($subs[$user_id] ?? '')) : null;
            
            // Gather all point flags
            $early_bird = isset($awards[$user_id]['early_bird']) ? 1 : 0;
            $status_update = 0; // Removed from UI
            $best_30_sec = isset($awards[$user_id]['best_30_sec']) ? 1 : 0;
            $presentation_8_min = isset($awards[$user_id]['presentation_8_min']) ? 1 : 0;
            $som = isset($awards[$user_id]['som']) ? 1 : 0;
            $mtp = isset($awards[$user_id]['mtp']) ? 1 : 0;

            // Check if the record already exists
            $stmtCheck = $pdo->prepare("SELECT id FROM attendance WHERE meeting_id = ? AND user_id = ?");
            $stmtCheck->execute([$meeting_id, $user_id]);
            
            if ($stmtCheck->fetch()) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE attendance SET attendance_status = ?, substitute_name = ?, early_bird = ?, status_update = ?, best_30_sec = ?, presentation_8_min = ?, som = ?, mtp = ? WHERE meeting_id = ? AND user_id = ?");
                $stmt->execute([$status, $sub_name, $early_bird, $status_update, $best_30_sec, $presentation_8_min, $som, $mtp, $meeting_id, $user_id]);
                $saved_count++;
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO attendance (meeting_id, user_id, attendance_status, substitute_name, early_bird, status_update, best_30_sec, presentation_8_min, som, mtp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$meeting_id, $user_id, $status, $sub_name, $early_bird, $status_update, $best_30_sec, $presentation_8_min, $som, $mtp]);
                $saved_count++;
            }
        }

        if ($saved_count === 0) {
            throw new RuntimeException('No valid active members were included in the attendance submission.');
        }

        // Mark the meeting as Complete
        $stmtComplete = $pdo->prepare("UPDATE chapter_meetings SET status = 'Completed' WHERE id = ?");
        $stmtComplete->execute([$meeting_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Attendance & Points saved successfully!";
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = $e instanceof RuntimeException ? $e->getMessage() : "Database Error: " . $e->getMessage();
    }
}

header("Location: ../attendance_manager.php");
exit;
?>