<?php
// actions/save_daily_points.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: ../login.php"); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $entry_type = filter_input(INPUT_POST, 'entry_type', FILTER_SANITIZE_STRING);

    try {
        $stmtRole = $pdo->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? AND leadership_role IN ('Coordinator', 'Attendance') AND membership_status = 'Active'");
        $stmtRole->execute([$_SESSION['user_id'], $group_id]);
        if (!$group_id || !$stmtRole->fetchColumn()) {
            throw new RuntimeException('You cannot award points for this chapter.');
        }

        $stmtMembers = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND membership_status = 'Active'");
        $stmtMembers->execute([$group_id]);
        $active_member_ids = array_fill_keys($stmtMembers->fetchAll(PDO::FETCH_COLUMN), true);

        $pdo->beginTransaction();

        if ($entry_type === 'daily') {
            // SCENARIO 1: Single Date, Multiple Members
            $award_date = $_POST['award_date'];
            $awards = $_POST['awards'] ?? [];

            $parsed_award_date = DateTime::createFromFormat('!Y-m-d', $award_date);
            if (!$parsed_award_date || $parsed_award_date->format('Y-m-d') !== $award_date || !is_array($awards) || empty($awards)) {
                throw new RuntimeException('Choose a valid date and at least one member to update.');
            }

            // Create or Find a "Virtual" meeting for today to attach the points to
            $virtual_mtg_id = null;
            $stmtFind = $pdo->prepare("SELECT id FROM chapter_meetings WHERE group_id = ? AND meeting_date = ? AND meeting_type = 'Daily Status'");
            $stmtFind->execute([$group_id, $award_date]);
            $existing = $stmtFind->fetch();

            if ($existing) {
                $virtual_mtg_id = $existing['id'];
            } else {
                $stmtInsertMtg = $pdo->prepare("INSERT INTO chapter_meetings (group_id, meeting_type, meeting_date, venue, status) VALUES (?, 'Daily Status', ?, 'Virtual', 'Completed')");
                $stmtInsertMtg->execute([$group_id, $award_date]);
                $virtual_mtg_id = $pdo->lastInsertId();
            }

            // Loop through members and award points
            foreach ($awards as $user_id => $flags) {
                if (!isset($active_member_ids[$user_id]) || !is_array($flags)) continue;

                $early_bird = isset($flags['early_bird']) ? 1 : 0;
                $status_update = isset($flags['status_update']) ? 1 : 0;
                $best_30_sec = isset($flags['best_30_sec']) ? 1 : 0;

                $stmtCheck = $pdo->prepare("SELECT id, early_bird as eb, status_update as su, best_30_sec as b30 FROM attendance WHERE meeting_id = ? AND user_id = ?");
                $stmtCheck->execute([$virtual_mtg_id, $user_id]);
                $record = $stmtCheck->fetch();

                if ($record) {
                    $stmtUpdate = $pdo->prepare("UPDATE attendance SET early_bird = ?, status_update = ?, best_30_sec = ? WHERE id = ?");
                    $stmtUpdate->execute([$early_bird, $status_update, $best_30_sec, $record['id']]);
                } else {
                    $stmtInsert = $pdo->prepare("INSERT INTO attendance (meeting_id, user_id, attendance_status, early_bird, status_update, best_30_sec) VALUES (?, ?, 'Present', ?, ?, ?)");
                    $stmtInsert->execute([$virtual_mtg_id, $user_id, $early_bird, $status_update, $best_30_sec]);
                }
            }
            $_SESSION['success_msg'] = "Daily points awarded successfully for " . date('M d', strtotime($award_date)) . "!";

        } elseif ($entry_type === 'bulk') {
            // SCENARIO 2: Single Member, 15 Bulk Dates
            $target_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
            $bulk_dates = $_POST['bulk_dates'] ?? [];

            if (!$target_user_id || !isset($active_member_ids[$target_user_id]) || !is_array($bulk_dates) || empty($bulk_dates)) {
                throw new RuntimeException('Choose an active member and at least one date.');
            }

            $bulk_dates = array_values(array_unique($bulk_dates));
            foreach ($bulk_dates as $b_date) {
                $parsed_bulk_date = DateTime::createFromFormat('!Y-m-d', $b_date);
                if (!$parsed_bulk_date || $parsed_bulk_date->format('Y-m-d') !== $b_date) continue;

                // Find or Create Virtual Meeting for each specific date
                $v_mtg_id = null;
                $stmtFindB = $pdo->prepare("SELECT id FROM chapter_meetings WHERE group_id = ? AND meeting_date = ? AND meeting_type = 'Daily Status'");
                $stmtFindB->execute([$group_id, $b_date]);
                $b_existing = $stmtFindB->fetch();

                if ($b_existing) {
                    $v_mtg_id = $b_existing['id'];
                } else {
                    $stmtInsertB = $pdo->prepare("INSERT INTO chapter_meetings (group_id, meeting_type, meeting_date, venue, status) VALUES (?, 'Daily Status', ?, 'Virtual', 'Completed')");
                    $stmtInsertB->execute([$group_id, $b_date]);
                    $v_mtg_id = $pdo->lastInsertId();
                }

                // Check if they already have an attendance record for this specific date
                $stmtCheckB = $pdo->prepare("SELECT id, status_update FROM attendance WHERE meeting_id = ? AND user_id = ?");
                $stmtCheckB->execute([$v_mtg_id, $target_user_id]);
                $b_record = $stmtCheckB->fetch();

                if ($b_record) {
                    // Update only if they don't already have a status update point (preventing duplicates)
                    if ($b_record['status_update'] == 0) {
                        $stmtUpdB = $pdo->prepare("UPDATE attendance SET status_update = 1 WHERE id = ?");
                        $stmtUpdB->execute([$b_record['id']]);
                    }
                } else {
                    // Insert new record with 1 status point
                    $stmtInsB = $pdo->prepare("INSERT INTO attendance (meeting_id, user_id, attendance_status, status_update) VALUES (?, ?, 'Present', 1)");
                    $stmtInsB->execute([$v_mtg_id, $target_user_id]);
                }
            }
            $_SESSION['success_msg'] = count($bulk_dates) . "-day bulk status points saved successfully!";
        }

        $pdo->commit();
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = $e instanceof RuntimeException ? $e->getMessage() : "Database Error: " . $e->getMessage();
    }
}

header("Location: ../daily_status_portal.php");
exit;
?>