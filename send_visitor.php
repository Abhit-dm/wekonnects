<?php
require_once 'bms/config/database.php';

function visitor_response($success, $message)
{
    $safe_message = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $destination = $success ? 'chapters.html' : 'chapters.html';
    echo "<!doctype html><html lang='en'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Visitor Request</title></head><body><script>alert($safe_message);window.location.href='$destination';</script></body></html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: chapters.html');
    exit;
}

$visitor_name = trim(strip_tags($_POST['visitor_name'] ?? ''));
$company_name = trim(strip_tags($_POST['company_name'] ?? ''));
$phone = trim(strip_tags($_POST['phone'] ?? ''));
$chapter_id = filter_input(INPUT_POST, 'chapter_id', FILTER_VALIDATE_INT);

if ($visitor_name === '' || $company_name === '' || $phone === '' || !$chapter_id) {
    visitor_response(false, 'Please complete all required visitor details.');
}

try {
    $stmtGroup = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND status = 'Active' LIMIT 1");
    $stmtGroup->execute([$chapter_id]);
    $group_id = $stmtGroup->fetchColumn();

    if (!$group_id) {
        visitor_response(false, 'That chapter is no longer accepting visitor requests. Please refresh the chapter list.');
    }

    $stmtMeeting = $pdo->prepare("SELECT meeting_date FROM chapter_meetings WHERE group_id = ? AND meeting_type IN ('Meeting', 'Event') AND status = 'Scheduled' AND meeting_date >= CURDATE() ORDER BY meeting_date ASC LIMIT 1");
    $stmtMeeting->execute([$group_id]);
    $visit_date = $stmtMeeting->fetchColumn() ?: date('Y-m-d');

    $stmtCoordinator = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND leadership_role = 'Coordinator' AND membership_status = 'Active' LIMIT 1");
    $stmtCoordinator->execute([$group_id]);
    $assigned_to = $stmtCoordinator->fetchColumn() ?: null;

    $stmtVisitor = $pdo->prepare("INSERT INTO visitors (chapter_id, invited_by, assigned_to, visitor_name, company_name, phone, visit_date, status, attended) VALUES (?, NULL, ?, ?, ?, ?, ?, 'Pending', 0)");
    $stmtVisitor->execute([$group_id, $assigned_to, $visitor_name, $company_name, $phone, $visit_date]);

    visitor_response(true, 'Your visitor request was sent to the chapter Head Table.');
} catch (PDOException $e) {
    error_log('Public visitor request error: ' . $e->getMessage());
    visitor_response(false, 'We could not submit your request right now. Please try again later.');
}