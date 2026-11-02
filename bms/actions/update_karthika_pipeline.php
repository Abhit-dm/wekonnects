<?php
session_start();

$role = $_SESSION['system_role'] ?? '';
$is_employee = strpos($role, 'EMP_') === 0;
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($role !== 'SUPER_ADMIN' && !$is_employee)) {
    http_response_code(403);
    exit('Forbidden');
}

$search = substr(trim((string)($_POST['search'] ?? '')), 0, 100);
$pipeline = (string)($_POST['pipeline'] ?? 'all');
$valid_pipelines = ['all', 'payment_pending', 'payment_confirmed', 'whatsapp_pending', 'whatsapp_sent', 'location_pending', 'location_shared'];
if (!in_array($pipeline, $valid_pipelines, true)) {
    $pipeline = 'all';
}
$page = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = (!$page || $page > 1000000) ? 1 : $page;
$redirect = '../karthika_registrations.php?' . http_build_query(['search' => $search, 'pipeline' => $pipeline, 'page' => $page]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['csrf_token'])
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    http_response_code(400);
    exit('Invalid registration update request.');
}

$registration_id = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string)($_POST['pipeline_action'] ?? '');
if ($registration_id === false || $registration_id === null) {
    $_SESSION['error_msg'] = 'Select a valid registration.';
    header('Location: ' . $redirect);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/karthika_event.php';

try {
    ensure_karthika_registrations_table($pdo);
    $stmt = $pdo->prepare('SELECT payment_status, whatsapp_status, location_status FROM karthika_registrations WHERE id = ?');
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch();

    if (!$registration) {
        $_SESSION['error_msg'] = 'Registration not found.';
    } elseif ($action === 'mark_whatsapp_sent') {
        if ($registration['payment_status'] !== 'Confirmed') {
            $_SESSION['error_msg'] = 'Confirm the payment before marking the WhatsApp message as sent.';
        } else {
            $stmt = $pdo->prepare("UPDATE karthika_registrations
                                   SET whatsapp_status = 'Sent', whatsapp_sent_at = COALESCE(whatsapp_sent_at, NOW())
                                   WHERE id = ?");
            $stmt->execute([$registration_id]);
            $_SESSION['success_msg'] = 'WhatsApp message marked as sent.';
        }
    } elseif ($action === 'mark_location_shared' || $action === 'mark_location_pending') {
        $shared = $action === 'mark_location_shared';
        $stmt = $pdo->prepare('UPDATE karthika_registrations SET location_status = ?, location_shared_at = ? WHERE id = ?');
        $stmt->execute([$shared ? 'Shared' : 'Pending', $shared ? date('Y-m-d H:i:s') : null, $registration_id]);
        $_SESSION['success_msg'] = $shared ? 'Location marked as shared.' : 'Location marked as pending.';
    } else {
        $_SESSION['error_msg'] = 'Choose a valid pipeline action.';
    }
} catch (Throwable $e) {
    error_log('Karthika registration pipeline could not be updated: ' . $e->getMessage());
    $_SESSION['error_msg'] = 'The registration could not be updated. Please try again.';
}

header('Location: ' . $redirect);
exit;
