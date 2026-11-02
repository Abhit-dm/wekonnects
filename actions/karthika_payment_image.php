<?php
$backend_handler = __DIR__ . '/../bms/actions/karthika_payment_image.php';
if (is_file($backend_handler)) {
    require_once $backend_handler;
    exit;
}

session_start();

$role = $_SESSION['system_role'] ?? '';
$is_employee = strpos($role, 'EMP_') === 0;
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($role !== 'SUPER_ADMIN' && !$is_employee)) {
    http_response_code(403);
    exit('Forbidden');
}

$config_path = __DIR__ . '/../config/database.php';
$event_path = __DIR__ . '/../includes/karthika_event.php';
if (!is_file($config_path) || !is_file($event_path)) {
    error_log('Karthika payment proof handler could not locate the application configuration.');
    http_response_code(500);
    exit('Unable to load payment proof.');
}

require_once $config_path;
require_once $event_path;

$registration_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($registration_id === false || $registration_id === null) {
    http_response_code(400);
    exit('Invalid payment proof request.');
}

try {
    ensure_karthika_registrations_table($pdo);
    $stmt = $pdo->prepare('SELECT payment_screenshot, payment_screenshot_data FROM karthika_registrations WHERE id = ?');
    $stmt->execute([$registration_id]);
    $proof = $stmt->fetch();
    if (!$proof) {
        http_response_code(404);
        exit('Payment proof not found.');
    }

    $image_data = $proof['payment_screenshot_data'];
    if (is_resource($image_data)) {
        $image_data = stream_get_contents($image_data);
        if ($image_data === false) {
            throw new RuntimeException('Unable to read the stored payment proof.');
        }
    }
    if (is_string($image_data) && $image_data !== '') {
        if (!class_exists('finfo')) {
            throw new RuntimeException('The PHP fileinfo extension is required to serve payment proofs.');
        }
        $file_info = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $file_info->buffer($image_data);
        if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            http_response_code(404);
            exit('Payment proof is not a supported image.');
        }

        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . strlen($image_data));
        header('Content-Disposition: inline; filename="payment-proof-' . (int)$registration_id . '"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $image_data;
        exit;
    }

    $filename = $proof['payment_screenshot'];
    if (!$filename || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
        http_response_code(404);
        exit('Payment proof not found.');
    }

    $path = '';
    $upload_directories = [
        karthika_private_upload_directory(),
        karthika_previous_upload_directory(),
        karthika_legacy_upload_directory()
    ];
    foreach ($upload_directories as $upload_directory) {
        $candidate = $upload_directory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($candidate)) {
            $path = $candidate;
            break;
        }
    }
    if ($path === '' || !class_exists('finfo')) {
        http_response_code(404);
        exit('Payment proof file not found.');
    }
    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $file_info->file($path);
    if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(404);
        exit('Payment proof is not a supported image.');
    }

    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="payment-proof-' . (int)$registration_id . '.' . strtolower(pathinfo($filename, PATHINFO_EXTENSION)) . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Throwable $e) {
    error_log('Karthika payment proof could not be served: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load payment proof.');
}
