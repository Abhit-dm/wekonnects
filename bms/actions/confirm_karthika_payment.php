<?php
session_start();

$role = $_SESSION['system_role'] ?? '';
$is_employee = strpos($role, 'EMP_') === 0;
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($role !== 'SUPER_ADMIN' && !$is_employee)) {
    http_response_code(403);
    exit('Forbidden');
}

$search = substr(trim((string)($_POST['search'] ?? '')), 0, 100);
$page = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = (!$page || $page > 1000000) ? 1 : $page;
$redirect = '../karthika_registrations.php?' . http_build_query(['search' => $search, 'page' => $page]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['csrf_token'])
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    http_response_code(400);
    exit('Invalid payment confirmation request.');
}

$registration_id = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($registration_id === false || $registration_id === null) {
    $_SESSION['error_msg'] = 'Select a valid registration.';
    header('Location: ' . $redirect);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/karthika_event.php';

try {
    ensure_karthika_registrations_table($pdo);
    $stmt = $pdo->prepare("UPDATE karthika_registrations
                           SET payment_status = 'Confirmed'
                           WHERE id = ? AND payment_status = 'Pending'
                             AND payment_screenshot <> '' AND total_amount > 0");
    $stmt->execute([$registration_id]);

    if ($stmt->rowCount() !== 1) {
        $_SESSION['error_msg'] = 'Payment was not confirmed. Check that the registration is still pending and has a payment proof.';
    } else {
        $_SESSION['success_msg'] = 'Payment confirmed. Review the booking details, then use Send WhatsApp to open the prefilled confirmation message.';
    }
} catch (Throwable $e) {
    error_log('Karthika payment could not be confirmed: ' . $e->getMessage());
    $_SESSION['error_msg'] = 'Payment could not be confirmed. Please try again.';
}

header('Location: ' . $redirect);
exit;
