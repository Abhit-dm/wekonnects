<?php
// actions/create_meeting.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_NUMBER_INT);
    $meeting_date = filter_input(INPUT_POST, 'meeting_date', FILTER_SANITIZE_STRING);
    $meeting_type = filter_input(INPUT_POST, 'meeting_type', FILTER_SANITIZE_STRING);
    $venue = trim(strip_tags($_POST['venue']));
    
    // Securely capture and encode the array of feature speakers
    $feature_speakers = null;
    if (isset($_POST['feature_speakers']) && is_array($_POST['feature_speakers'])) {
        // Sanitize each ID in the array just to be safe
        $sanitized_speakers = array_map('intval', $_POST['feature_speakers']);
        $feature_speakers = json_encode($sanitized_speakers);
    }

    if ($group_id && $meeting_date && $meeting_type) {
        try {
            // Verify Head Table Status
            $stmtVerify = $pdo->prepare("SELECT id FROM group_members WHERE user_id = ? AND group_id = ? AND leadership_role = 'Coordinator'");
            $stmtVerify->execute([$user_id, $group_id]);
            if (!$stmtVerify->fetch()) die("Unauthorized access.");

            $stmt = $pdo->prepare("INSERT INTO chapter_meetings (group_id, meeting_date, meeting_type, venue, feature_speakers, status) VALUES (?, ?, ?, ?, ?, 'Scheduled')");
            $stmt->execute([$group_id, $meeting_date, $meeting_type, $venue, $feature_speakers]);
            
            $_SESSION['success_msg'] = "Successfully scheduled a new " . htmlspecialchars($meeting_type) . "!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "System Error: Could not schedule event. Error: " . $e->getMessage();
        }
    }
}
header("Location: ../head_table.php");
exit;
?>