<?php
session_start();

if (empty($_SESSION['logged_in']) || ($_SESSION['system_role'] ?? '') !== 'SUPER_ADMIN') {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['csrf_token'])
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    http_response_code(400);
    exit('Invalid employee access request.');
}

$employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string)($_POST['action'] ?? '');
if ($employee_id === false || $employee_id === null || !in_array($action, ['activate', 'deactivate'], true)) {
    $_SESSION['error_msg'] = 'Select a valid employee and action.';
    header('Location: ../sa_manage_employees.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $new_status = $action === 'deactivate' ? 'Inactive' : 'Active';
    $stmt = $pdo->prepare("UPDATE users
                           SET status = ?
                           WHERE id = ? AND LEFT(system_role, 4) = 'EMP_'");
    $stmt->execute([$new_status, $employee_id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare("SELECT status FROM users WHERE id = ? AND LEFT(system_role, 4) = 'EMP_'");
        $check->execute([$employee_id]);
        $current_status = $check->fetchColumn();
        if ($current_status === false) {
            $_SESSION['error_msg'] = 'Employee account not found.';
        } elseif ($current_status !== $new_status) {
            $_SESSION['error_msg'] = 'Employee access could not be updated. Please try again.';
        } else {
            $_SESSION['success_msg'] = $new_status === 'Inactive'
                ? 'Employee access is already removed.'
                : 'Employee access is already active.';
        }
    } else {
        $_SESSION['success_msg'] = $new_status === 'Inactive'
            ? 'Employee access removed. The employee account and records were kept.'
            : 'Employee access restored.';
    }
} catch (Throwable $e) {
    error_log('Employee access could not be updated: ' . $e->getMessage());
    $_SESSION['error_msg'] = 'Employee access could not be updated. Please try again.';
}

header('Location: ../sa_manage_employees.php');
exit;
