<?php
// actions/submit_slip.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { 
    header("Location: ../login.php"); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $initiator_id = $_SESSION['user_id'];
    $slip_type = filter_input(INPUT_POST, 'slip_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    if (!$slip_type) {
        $_SESSION['error_msg'] = "Submission failed: Invalid slip type.";
        header("Location: ../index.php"); exit;
    }

    try {
        if ($slip_type === 'REFERRAL') {
            $receivers = is_array($_POST['receiver_member_id']) ? $_POST['receiver_member_id'] : [$_POST['receiver_member_id']];
            $types = is_array($_POST['referral_type']) ? $_POST['referral_type'] : [$_POST['referral_type']];
            $remarks_arr = is_array($_POST['remarks']) ? $_POST['remarks'] : [$_POST['remarks']];
            $dates = is_array($_POST['date_logged']) ? $_POST['date_logged'] : [$_POST['date_logged']];
            
            $out_names = is_array($_POST['outside_ref_name'] ?? []) ? $_POST['outside_ref_name'] : [$_POST['outside_ref_name'] ?? ''];
            $out_phones = is_array($_POST['outside_ref_phone'] ?? []) ? $_POST['outside_ref_phone'] : [$_POST['outside_ref_phone'] ?? ''];
            
            $success_count = 0;
            
            foreach ($receivers as $index => $receiver_id) {
                if (empty($receiver_id) || $initiator_id == $receiver_id) continue;

                $date_logged = trim(strip_tags($dates[$index] ?? date('Y-m-d')));
                $referral_type = filter_var($types[$index] ?? 'INSIDE', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $topics = trim(strip_tags($remarks_arr[$index] ?? ''));
                $outside_name = ($referral_type === 'OUTSIDE') ? trim(strip_tags($out_names[$index] ?? '')) : null;
                $outside_phone = ($referral_type === 'OUTSIDE') ? trim(strip_tags($out_phones[$index] ?? '')) : null;
                
                $stmtCheck = $pdo->prepare("SELECT id FROM slips WHERE initiator_member_id = ? AND receiver_member_id = ? AND slip_type = 'REFERRAL' AND date_logged = ? AND referral_type = ? AND topics_discussed = ?");
                $stmtCheck->execute([$initiator_id, $receiver_id, $date_logged, $referral_type, $topics]);
                if ($stmtCheck->fetch()) continue; 
                
                $stmt = $pdo->prepare("INSERT INTO slips (initiator_member_id, receiver_member_id, slip_type, referral_type, topics_discussed, outside_ref_name, outside_ref_phone, link_given_date, date_logged) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$initiator_id, $receiver_id, $slip_type, $referral_type, $topics, $outside_name, $outside_phone, $date_logged, $date_logged]);
                $success_count++;
            }
            
            if ($success_count > 0) $_SESSION['success_msg'] = "$success_count Link(s) successfully recorded!";
            else $_SESSION['error_msg'] = "No valid links were processed.";

        } elseif ($slip_type === 'TYFCB') {
            $receiver_id = filter_input(INPUT_POST, 'receiver_member_id', FILTER_SANITIZE_NUMBER_INT);
            $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $date_logged = trim(strip_tags($_POST['date_logged']));
            $deal_type = filter_input(INPUT_POST, 'deal_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'INSIDE'; // Default to Inside (2 pts)

            if (!in_array($deal_type, ['INSIDE', 'OUTSIDE'], true) || !is_numeric($amount) || (float)$amount <= 0) {
                $_SESSION['error_msg'] = "Please provide a valid deal type and amount.";
                header("Location: ../my_activity.php"); exit;
            }
            
            if ($initiator_id == $receiver_id) {
                $_SESSION['error_msg'] = "You cannot thank yourself for a deal.";
                header("Location: ../my_activity.php"); exit;
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM slips WHERE initiator_member_id = ? AND receiver_member_id = ? AND slip_type = 'TYFCB' AND amount = ? AND date_logged = ?");
            $stmtCheck->execute([$initiator_id, $receiver_id, $amount, $date_logged]);
            if ($stmtCheck->fetch()) {
                $_SESSION['error_msg'] = "Duplicate Entry Detected: This Deal has already been closed!";
                header("Location: ../my_activity.php"); exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO slips (initiator_member_id, receiver_member_id, slip_type, amount, deal_close_date, date_logged) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$initiator_id, $receiver_id, $slip_type, $amount, $date_logged, $date_logged]);
            
            // AUTO-GENERATE REFERRAL POINTS (Applying Inside vs Outside points)
            $stmtRefCheck = $pdo->prepare("SELECT id FROM slips WHERE initiator_member_id = ? AND receiver_member_id = ? AND slip_type = 'REFERRAL' AND COALESCE(NULLIF(link_given_date, '0000-00-00'), DATE(date_logged)) <= ? LIMIT 1");
            $stmtRefCheck->execute([$initiator_id, $receiver_id, $date_logged]);
            if (!$stmtRefCheck->fetch()) {
                $stmtAutoRef = $pdo->prepare("INSERT INTO slips (initiator_member_id, receiver_member_id, slip_type, referral_type, topics_discussed, link_given_date, date_logged) VALUES (?, ?, 'REFERRAL', ?, 'System Generated: Deal closed without prior link.', ?, ?)");
                $stmtAutoRef->execute([$initiator_id, $receiver_id, $deal_type, $date_logged, $date_logged]);
            }
            
            $_SESSION['success_msg'] = "Deal Done successfully recorded!";
            header("Location: ../my_activity.php"); exit;

        } elseif ($slip_type === '121') {
            $receiver_id = filter_input(INPUT_POST, 'receiver_member_id', FILTER_SANITIZE_NUMBER_INT);
            $date_logged = trim(strip_tags($_POST['date_logged']));
            $topics = trim(strip_tags($_POST['remarks'] ?? ''));
            
            if ($initiator_id == $receiver_id) {
                $_SESSION['error_msg'] = "You cannot log a 1-to-1 with yourself.";
                header("Location: ../index.php"); exit;
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM slips WHERE initiator_member_id = ? AND receiver_member_id = ? AND slip_type = '121' AND date_logged = ?");
            $stmtCheck->execute([$initiator_id, $receiver_id, $date_logged]);
            if ($stmtCheck->fetch()) {
                $_SESSION['error_msg'] = "You have already logged a 1-to-1 with this member today!";
                header("Location: ../index.php"); exit;
            }

            $stmt = $pdo->prepare("INSERT INTO slips (initiator_member_id, receiver_member_id, slip_type, topics_discussed, link_given_date, date_logged) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$initiator_id, $receiver_id, $slip_type, $topics, $date_logged, $date_logged]);
            $_SESSION['success_msg'] = "1-to-1 successfully logged!";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
}
header("Location: ../index.php");
exit;
?>