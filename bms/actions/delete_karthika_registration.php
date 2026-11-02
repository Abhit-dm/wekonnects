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
    exit('Invalid registration deletion request.');
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
    $stmt = $pdo->prepare('SELECT payment_screenshot FROM karthika_registrations WHERE id = ?');
    $stmt->execute([$registration_id]);
    $filename = $stmt->fetchColumn();

    if ($filename === false) {
        $_SESSION['error_msg'] = 'Registration not found.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM karthika_registrations WHERE id = ?');
        $stmt->execute([$registration_id]);

        if ($stmt->rowCount() !== 1) {
            $_SESSION['error_msg'] = 'Registration could not be deleted. Please refresh and try again.';
        } else {
            $proof_cleanup_failed = false;
            if ($filename !== '' && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
                $paths = [
                    karthika_private_upload_directory() . DIRECTORY_SEPARATOR . $filename,
                    karthika_legacy_upload_directory() . DIRECTORY_SEPARATOR . $filename
                ];
                foreach ($paths as $path) {
                    if (is_file($path) && !unlink($path)) {
                        error_log('Karthika payment proof could not be removed for registration ' . (int)$registration_id . '.');
                        $proof_cleanup_failed = true;
                    }
                }
            }
            $_SESSION['success_msg'] = $proof_cleanup_failed
                ? 'Registration deleted, but its uploaded proof file could not be removed. Please contact support.'
                : 'Registration deleted.';
        }
    }
} catch (Throwable $e) {
    error_log('Karthika registration could not be deleted: ' . $e->getMessage());
    $_SESSION['error_msg'] = 'Registration could not be deleted. Please try again.';
}

header('Location: ' . $redirect);
exit;
