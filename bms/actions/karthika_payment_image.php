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
    $stmt = $pdo->prepare('SELECT payment_screenshot FROM karthika_registrations WHERE id = ?');
    $stmt->execute([$registration_id]);
    $filename = $stmt->fetchColumn();

    if (!$filename || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
        http_response_code(404);
        exit('Payment proof not found.');
    }

    $path = karthika_private_upload_directory() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path) || !class_exists('finfo')) {
        http_response_code(404);
        exit('Payment proof not found.');
    }

    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $file_info->file($path);
    if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(404);
        exit('Payment proof not found.');
    }

    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="payment-proof-' . (int)$registration_id . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Exception $e) {
    error_log('Karthika payment proof could not be served: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load payment proof.');
}
