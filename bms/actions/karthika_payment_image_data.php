<?php
session_start();

$role = $_SESSION['system_role'] ?? '';
$is_employee = strpos($role, 'EMP_') === 0;
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($role !== 'SUPER_ADMIN' && !$is_employee)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/karthika_event.php';

$registration_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($registration_id === false || $registration_id === null) {
    http_response_code(400);
    exit('Invalid payment proof request.');
}

try {
    ensure_karthika_registrations_table($pdo);
    $proof = karthika_load_payment_proof($pdo, $registration_id);
    if ($proof === null) {
        http_response_code(404);
        exit('Payment proof registration not found.');
    }

    $image_bytes = $proof['image_bytes'];
    header('Content-Type: ' . $proof['mime_type']);
    header('Content-Length: ' . strlen($image_bytes));
    header('Content-Disposition: inline; filename="payment-proof-' . (int)$registration_id . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $image_bytes;
} catch (Throwable $e) {
    error_log('Karthika payment proof image could not be served: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Unable to load payment proof image.');
}
