<?php
// actions/process_renewal.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $renewal_id = filter_input(INPUT_POST, 'renewal_id', FILTER_SANITIZE_NUMBER_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($renewal_id && $action) {
        $stmtGet = $pdo->prepare("SELECT user_id, group_id FROM pending_renewals WHERE id = ?");
        $stmtGet->execute([$renewal_id]);
        $ren = $stmtGet->fetch();

        if ($ren && $_SESSION['system_role'] === 'FRANCHISE_OWNER') {
            $stmtOwner = $pdo->prepare("SELECT 1 FROM groups WHERE id = ? AND franchise_owner_id = ?");
            $stmtOwner->execute([$ren['group_id'], $_SESSION['user_id']]);
            if (!$stmtOwner->fetchColumn()) $ren = null;
        }

        if ($ren) {
            if ($action === 'approve') {
                $pdo->beginTransaction();
                
                // Fetch current renewal date and check if overdue
                $stmtMem = $pdo->prepare("SELECT renewal_date FROM group_members WHERE user_id = ? AND group_id = ?");
                $stmtMem->execute([$ren['user_id'], $ren['group_id']]);
                $mem = $stmtMem->fetch();

                // Check if they are overdue
                $is_overdue = false;
                if (!$mem || empty($mem['renewal_date']) || strtotime($mem['renewal_date']) < time()) {
                    $is_overdue = true;
                }

                $current_date = $is_overdue ? date('Y-m-d') : $mem['renewal_date'];
                $new_renewal_date = date('Y-m-d', strtotime($current_date . ' + 1 year'));

                if ($is_overdue) {
                    // Overdue: Reset joining date to today (Treated as New Member)
                    $stmtUpd = $pdo->prepare("UPDATE group_members SET renewal_date = ?, joining_date = ?, membership_status = 'Active' WHERE user_id = ? AND group_id = ?");
                    $stmtUpd->execute([$new_renewal_date, date('Y-m-d'), $ren['user_id'], $ren['group_id']]);
                } else {
                    // Active: Just extend the renewal date
                    $stmtUpd = $pdo->prepare("UPDATE group_members SET renewal_date = ?, membership_status = 'Active' WHERE user_id = ? AND group_id = ?");
                    $stmtUpd->execute([$new_renewal_date, $ren['user_id'], $ren['group_id']]);
                }

                // Update users status to Active
                $stmtUserUpd = $pdo->prepare("UPDATE users SET status = 'Active' WHERE id = ?");
                $stmtUserUpd->execute([$ren['user_id']]);

                $stmtStat = $pdo->prepare("UPDATE pending_renewals SET status = 'Approved' WHERE id = ?");
                $stmtStat->execute([$renewal_id]);
                
                $pdo->commit();
                
                $_SESSION['success_msg'] = $is_overdue ? "Approved! Since renewal was overdue, they have been treated as a new member and joining date was reset." : "Renewal Approved! Exactly 1 Year added to their active membership.";
                
            } elseif ($action === 'reject') {
                $stmtStat = $pdo->prepare("UPDATE pending_renewals SET status = 'Rejected' WHERE id = ?");
                $stmtStat->execute([$renewal_id]);
                $_SESSION['success_msg'] = "Renewal Rejected.";
            }
        }
    }
}
header("Location: ../admin_renewals.php");
exit;
?>