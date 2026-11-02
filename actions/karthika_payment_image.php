<?php
$backend_handler = __DIR__ . '/../bms/actions/karthika_payment_image.php';
if (is_file($backend_handler)) {
    require_once $backend_handler;
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
exit('Payment proof page not found.');
