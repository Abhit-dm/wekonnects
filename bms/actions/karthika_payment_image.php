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

$registration = null;
$image_data_url = '';
$error_message = '';
try {
    ensure_karthika_registrations_table($pdo);
    $proof = karthika_load_payment_proof($pdo, $registration_id);
    if ($proof === null) {
        http_response_code(404);
        $error_message = 'This registration could not be found.';
    } else {
        $registration = $proof['registration'];
        $image_data_url = 'data:' . $proof['mime_type'] . ';base64,' . base64_encode($proof['image_bytes']);
    }
} catch (Throwable $e) {
    error_log('Karthika payment proof page could not be loaded: ' . $e->getMessage());
    http_response_code(500);
    $error_message = 'Unable to load this payment proof. Check the server error log for details.';
}

$escape = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$interests = [];
if ($registration) {
    if ((int)$registration['interest_stalls'] === 1) {
        $interests[] = 'Festival stall';
    }
    if ((int)$registration['interest_advertisement'] === 1) {
        $interests[] = 'Advertisement';
    }
    if ((int)$registration['interest_sponsorship'] === 1) {
        $interests[] = 'Sponsorship';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment proof | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy:#08264b; --orange:#f47a20; --muted:#66758a; --line:#e4eaf1; }
        * { box-sizing:border-box; }
        body { margin:0; background:#f3f6fa; color:#172337; font-family:Inter,Arial,sans-serif; }
        .topbar { background:linear-gradient(120deg,#071d3b,#103c70); color:#fff; border-bottom:4px solid var(--orange); }
        .topbar-inner,.footer-inner { width:min(1080px,calc(100% - 36px)); margin:auto; }
        .topbar-inner { display:flex; align-items:center; justify-content:space-between; gap:20px; min-height:82px; }
        .brand { font-size:20px; font-weight:800; letter-spacing:.08em; }
        .brand span { color:#ff9b4c; }
        .top-note { color:#cfdbeb; font-size:12px; text-align:right; }
        main { width:min(960px,calc(100% - 28px)); margin:32px auto 42px; }
        .title-row { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; }
        h1 { margin:0 0 6px; color:var(--navy); font-size:25px; }
        .subtitle { margin:0; color:var(--muted); font-size:13px; }
        .print { border:0; border-radius:8px; padding:10px 14px; background:var(--navy); color:#fff; font-weight:700; cursor:pointer; white-space:nowrap; }
        .card { overflow:hidden; border:1px solid var(--line); border-radius:15px; background:white; box-shadow:0 10px 32px rgba(10,32,62,.07); }
        .card-heading { padding:18px 22px; background:#fbfcfe; border-bottom:1px solid var(--line); }
        .card-heading strong { display:block; color:var(--navy); font-size:17px; }
        .card-heading span { display:block; margin-top:5px; color:var(--muted); font-size:12px; }
        .details { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 24px; padding:8px 22px; }
        .detail { padding:13px 0; border-bottom:1px solid #eef2f6; }
        .detail-label { display:block; margin-bottom:5px; color:var(--muted); font-size:10px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
        .detail-value { color:#172337; font-size:14px; font-weight:600; overflow-wrap:anywhere; }
        .amount { color:#137547; font-size:18px; font-weight:800; }
        .status { display:inline-block; border-radius:20px; padding:5px 10px; background:#fff2df; color:#a65108; font-size:11px; font-weight:800; }
        .status.confirmed { background:#e1f6e8; color:#176537; }
        .proof-section { padding:20px 22px 24px; }
        .proof-section h2 { margin:0 0 12px; color:var(--navy); font-size:15px; }
        .proof-frame { display:flex; justify-content:center; align-items:center; min-height:240px; padding:14px; border:1px solid var(--line); border-radius:11px; background:#f7f9fc; }
        .proof-image { display:block; max-width:100%; max-height:72vh; object-fit:contain; }
        .proof-unavailable { color:#9f1239; font-size:13px; text-align:center; }
        .proof-unavailable[hidden] { display:none; }
        .error { border:1px solid #fecaca; border-radius:12px; padding:18px; background:#fff1f2; color:#9f1239; }
        footer { padding:21px 0; background:#081d38; color:#d5deea; }
        .footer-inner { display:flex; justify-content:space-between; gap:16px; font-size:11px; }
        .footer-inner strong { color:#fff; }
        @media(max-width:620px) {
            .topbar-inner { min-height:70px; }
            .brand { font-size:17px; }
            .top-note { max-width:150px; }
            main { margin-top:22px; }
            .title-row { align-items:center; }
            h1 { font-size:21px; }
            .details { grid-template-columns:1fr; }
            .detail { padding:11px 0; }
            .card-heading,.proof-section { padding-left:16px; padding-right:16px; }
            .footer-inner { flex-direction:column; }
        }
        @media print {
            body { background:#fff; }
            .topbar { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
            .print { display:none; }
            main { width:100%; margin:18px auto; }
            .card { box-shadow:none; }
            .proof-image { max-height:600px; }
            footer { position:fixed; right:0; bottom:0; left:0; print-color-adjust:exact; -webkit-print-color-adjust:exact; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">WE <span>KONNECTS</span></div>
        <div class="top-note">Karthika Festival · Payment verification</div>
    </div>
</header>
<main>
    <div class="title-row">
        <div>
            <h1>Payment proof</h1>
            <p class="subtitle">Registration #<?php echo (int)$registration_id; ?> · Internal verification copy</p>
        </div>
        <?php if ($registration): ?><button class="print" type="button" onclick="window.print()">Print / Save PDF</button><?php endif; ?>
    </div>

    <?php if ($error_message !== ''): ?>
        <div class="error"><?php echo $escape($error_message); ?></div>
    <?php else: ?>
        <section class="card">
            <div class="card-heading">
                <strong>Registrant and booking details</strong>
                <span>Submitted <?php echo $escape(date('d M Y, h:i A', strtotime($registration['created_at']))); ?></span>
            </div>
            <div class="details">
                <div class="detail"><span class="detail-label">Full name</span><span class="detail-value"><?php echo $escape($registration['full_name']); ?></span></div>
                <div class="detail"><span class="detail-label">Phone / WhatsApp</span><span class="detail-value"><?php echo $escape($registration['phone']); ?></span></div>
                <div class="detail"><span class="detail-label">Event tickets</span><span class="detail-value"><?php echo number_format((int)$registration['event_ticket_count']); ?> × ₹499 = ₹<?php echo number_format((int)$registration['event_ticket_count'] * 499); ?></span></div>
                <div class="detail"><span class="detail-label">Satyanarayana Vratam couples</span><span class="detail-value"><?php echo number_format((int)$registration['satyanarayana_vratam_couple_count']); ?> × ₹999 = ₹<?php echo number_format((int)$registration['satyanarayana_vratam_couple_count'] * 999); ?></span></div>
                <div class="detail"><span class="detail-label">Total payment</span><span class="detail-value amount">₹<?php echo number_format((int)$registration['total_amount']); ?></span></div>
                <div class="detail"><span class="detail-label">Payment status</span><span class="detail-value"><span class="status <?php echo $registration['payment_status'] === 'Confirmed' ? 'confirmed' : ''; ?>"><?php echo $escape($registration['payment_status']); ?></span></span></div>
                <div class="detail"><span class="detail-label">Event date</span><span class="detail-value">22 November 2026</span></div>
                <div class="detail"><span class="detail-label">Additional interests</span><span class="detail-value"><?php echo $interests ? $escape(implode(', ', $interests)) : 'None'; ?></span></div>
                <?php if ($registration['interest_details'] !== ''): ?>
                    <div class="detail"><span class="detail-label">Interest details</span><span class="detail-value"><?php echo nl2br($escape($registration['interest_details'])); ?></span></div>
                <?php endif; ?>
            </div>
            <div class="proof-section">
                <h2>Uploaded payment screenshot</h2>
                <div class="proof-frame">
                    <img class="proof-image" src="<?php echo $escape($image_data_url); ?>" alt="Payment screenshot for <?php echo $escape($registration['full_name']); ?>" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                    <p class="proof-unavailable" hidden>Screenshot could not be loaded. Confirm the proof file exists on the server and is readable by PHP.</p>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<footer>
    <div class="footer-inner">
        <span><strong>WE KONNECTS</strong> · Karthika Festival registration desk</span>
        <span>Confidential — for authorized verification only</span>
    </div>
</footer>
</body>
</html>
