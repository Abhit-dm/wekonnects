<?php
// /api/slips/create.php
session_start();
require_once '../../config/database.php';

// 1. Set Headers to return JSON
header('Content-Type: application/json');

// 2. Security Check: Is the user logged in?
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// 3. Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// 4. Capture and Sanitize the incoming data
$initiator_id = $_SESSION['user_id'];
$slip_type = filter_input(INPUT_POST, 'slip_type', FILTER_SANITIZE_STRING); // '121', 'REFERRAL', 'TYFCB'
$receiver_id = filter_input(INPUT_POST, 'receiver_member_id', FILTER_SANITIZE_NUMBER_INT);
$date_logged = date('Y-m-d'); 

// Prepare variables based on slip type
$amount = null;
$topics = null;
$ref_type = null;

if ($slip_type === 'TYFCB') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
} elseif ($slip_type === '121') {
    $topics = filter_input(INPUT_POST, 'topics', FILTER_SANITIZE_STRING);
} elseif ($slip_type === 'REFERRAL') {
    $ref_type = filter_input(INPUT_POST, 'referral_type', FILTER_SANITIZE_STRING); // 'INSIDE' or 'OUTSIDE'
}

try {
    // 5. Insert into the database
    $sql = "INSERT INTO slips (slip_type, initiator_member_id, receiver_member_id, amount, topics_discussed, referral_type, date_logged) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$slip_type, $initiator_id, $receiver_id, $amount, $topics, $ref_type, $date_logged]);

    // 6. Return Success
    echo json_encode([
        'status' => 'success', 
        'message' => 'Slip successfully logged!',
        'slip_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    // Log the actual error internally, but return a clean error to the user
    error_log("Slip Insert Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to log slip. Please try again.']);
}
?>