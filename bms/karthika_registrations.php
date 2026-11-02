<?php
session_start();
require_once 'config/database.php';

$role = $_SESSION['system_role'] ?? '';
$is_employee = strpos($role, 'EMP_') === 0;
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($role !== 'SUPER_ADMIN' && !$is_employee)) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'includes/karthika_event.php';

$search = trim((string)($_GET['search'] ?? ''));
$search = substr($search, 0, 100);
$pipeline = (string)($_GET['pipeline'] ?? 'all');
$valid_pipelines = ['all', 'payment_pending', 'payment_confirmed', 'whatsapp_pending', 'whatsapp_sent', 'location_pending', 'location_shared'];
if (!in_array($pipeline, $valid_pipelines, true)) {
    $pipeline = 'all';
}
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = (!$page || $page > 1000000) ? 1 : $page;
$page_size = 50;
$offset = ($page - 1) * $page_size;
$registrations = [];
$registration_count = 0;
$expected_total = 0;
$payment_pending_count = 0;
$payment_confirmed_count = 0;
$whatsapp_sent_count = 0;
$location_pending_count = 0;
$error_message = '';

try {
    ensure_karthika_registrations_table($pdo);

    $stmtSummary = $pdo->query("SELECT COUNT(*) AS registration_count,
                                       COALESCE(SUM(total_amount), 0) AS expected_total,
                                       COALESCE(SUM(payment_status = 'Pending'), 0) AS payment_pending_count,
                                       COALESCE(SUM(payment_status = 'Confirmed'), 0) AS payment_confirmed_count,
                                       COALESCE(SUM(whatsapp_status = 'Sent'), 0) AS whatsapp_sent_count,
                                       COALESCE(SUM(location_status = 'Pending'), 0) AS location_pending_count
                                FROM karthika_registrations");
    $summary = $stmtSummary->fetch();
    $registration_count = (int)$summary['registration_count'];
    $expected_total = (int)$summary['expected_total'];
    $payment_pending_count = (int)$summary['payment_pending_count'];
    $payment_confirmed_count = (int)$summary['payment_confirmed_count'];
    $whatsapp_sent_count = (int)$summary['whatsapp_sent_count'];
    $location_pending_count = (int)$summary['location_pending_count'];

    $conditions = [];
    $params = [];
    if ($search !== '') {
        $conditions[] = '(full_name LIKE ? OR phone LIKE ?)';
        $search_term = '%' . $search . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $pipeline_conditions = [
        'payment_pending' => "payment_status = 'Pending'",
        'payment_confirmed' => "payment_status = 'Confirmed'",
        'whatsapp_pending' => "payment_status = 'Confirmed' AND whatsapp_status = 'Pending'",
        'whatsapp_sent' => "whatsapp_status = 'Sent'",
        'location_pending' => "location_status = 'Pending'",
        'location_shared' => "location_status = 'Shared'"
    ];
    if ($pipeline !== 'all') {
        $conditions[] = $pipeline_conditions[$pipeline];
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM karthika_registrations' . $where);
    $stmtCount->execute($params);
    $filtered_count = (int)$stmtCount->fetchColumn();

    $stmtRows = $pdo->prepare("
        SELECT id, full_name, phone, event_ticket_count, satyanarayana_vratam_couple_count,
               total_amount, payment_screenshot, payment_status, whatsapp_status, whatsapp_sent_at,
               location_status, location_shared_at, interest_stalls,
               interest_advertisement, interest_sponsorship, interest_details, created_at
        FROM karthika_registrations
        $where
        ORDER BY created_at DESC, id DESC
        LIMIT $page_size OFFSET $offset
    ");
    $stmtRows->execute($params);
    $registrations = $stmtRows->fetchAll();
    foreach ($registrations as &$registration) {
        $registration['interests'] = [];
        if ((int)$registration['interest_stalls'] === 1) {
            $registration['interests'][] = 'Stall';
        }
        if ((int)$registration['interest_advertisement'] === 1) {
            $registration['interests'][] = 'Advertisement';
        }
        if ((int)$registration['interest_sponsorship'] === 1) {
            $registration['interests'][] = 'Sponsorship';
        }
    }
    unset($registration);
} catch (Exception $e) {
    error_log('Karthika registrations could not be loaded: ' . $e->getMessage());
    $error_message = 'Registration data is temporarily unavailable. Please try again later.';
}

$page_count = isset($filtered_count) ? max(1, (int)ceil($filtered_count / $page_size)) : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karthika Festival Registrations | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --blue: #00204a; --orange: #ff6b00; --muted: #64748b; --border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f8fafc; color: #0f172a; font-family: Inter, sans-serif; }
        .main { margin-left: 260px; padding: 32px; }
        .page-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 24px; }
        .page-heading h1 { margin: 0 0 6px; color: var(--blue); font-size: 26px; }
        .page-heading p { margin: 0; color: var(--muted); font-size: 14px; }
        .print-button { border: 0; border-radius: 8px; padding: 11px 16px; background: var(--orange); color: white; font-weight: 700; cursor: pointer; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
        .stat { position: relative; overflow: hidden; border: 1px solid var(--border); border-radius: 14px; background: white; padding: 18px 20px; box-shadow: 0 4px 14px rgba(15,23,42,.035); }
        .stat:after { position: absolute; top: -18px; right: -14px; width: 65px; height: 65px; border-radius: 50%; background: var(--tint, #eff6ff); content: ""; opacity: .8; }
        .stat span { color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .stat strong { display: block; margin-top: 7px; color: var(--blue); font-size: 25px; }
        .filter { display: flex; gap: 10px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: 12px; background: white; padding: 12px; }
        .filter input, .filter select { min-width: 0; border: 1px solid var(--border); border-radius: 8px; padding: 11px 12px; background: white; font: inherit; }
        .filter input { flex: 1; }
        .filter button, .filter a { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 8px; padding: 11px 15px; background: var(--blue); color: white; font-weight: 700; text-decoration: none; cursor: pointer; white-space: nowrap; }
        .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 14px; background: white; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
        table { width: 100%; border-collapse: collapse; min-width: 1500px; text-align: left; }
        th, td { padding: 14px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 13px; }
        th { background: #f1f5f9; color: var(--muted); font-size: 10px; text-transform: uppercase; letter-spacing: .045em; white-space: nowrap; }
        tr:last-child td { border-bottom: 0; }
        .phone { color: #2563eb; text-decoration: none; font-weight: 600; white-space: nowrap; }
        .proof { display: inline-flex; gap: 7px; align-items: center; border-radius: 7px; padding: 8px 10px; background: #eff6ff; color: #1d4ed8; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .status { display: inline-flex; align-items: center; gap: 5px; border-radius: 20px; padding: 5px 9px; background: #fff7ed; color: #c2410c; font-size: 11px; font-weight: 800; white-space: nowrap; }
        .status-confirmed, .status-shared, .status-sent { background: #dcfce7; color: #166534; }
        .status-pending { background: #fff7ed; color: #c2410c; }
        .proof-preview { display: block; width: 112px; height: 76px; margin-bottom: 7px; border: 1px solid var(--border); border-radius: 8px; background: #f1f5f9; object-fit: cover; }
        .proof-preview[hidden] { display: none; }
        .proof-fallback { display: block; margin: 0 0 7px; color: #b91c1c; font-size: 11px; }
        .proof-fallback[hidden] { display: none; }
        .booking-action { display: inline-flex; align-items: center; gap: 6px; margin-top: 7px; border: 0; border-radius: 7px; padding: 8px 10px; background: #166534; color: white; font: inherit; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer; white-space: nowrap; }
        .booking-action.whatsapp { background: #15803d; }
        .booking-action.neutral { background: #e2e8f0; color: #334155; }
        .booking-action.danger { background: #fff1f2; color: #be123c; }
        .pipeline-cell { min-width: 145px; }
        .pipeline-note { display: block; margin-top: 5px; color: var(--muted); font-size: 11px; }
        .notice { margin-bottom: 18px; border-radius: 9px; padding: 12px 15px; font-size: 14px; }
        .notice.success { background: #dcfce7; color: #166534; }
        .notice.error { background: #fef2f2; color: #b91c1c; }
        .empty, .error { padding: 32px; text-align: center; color: var(--muted); }
        .error { margin-bottom: 20px; border-radius: 10px; background: #fef2f2; color: #b91c1c; }
        .pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 18px; color: var(--muted); font-size: 13px; }
        .pagination a { color: var(--blue); font-weight: 700; text-decoration: none; }
        @media (max-width: 760px) {
            .main { margin-left: 0; padding: 20px 14px; }
            .page-heading { flex-direction: column; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .stat { padding: 15px; }
            .stat strong { font-size: 21px; }
            .filter { flex-wrap: wrap; }
            .filter input, .filter select { flex-basis: 100%; }
        }
        @media print {
            .sidebar, .filter, .print-button, .pagination { display: none !important; }
            .main { margin: 0; padding: 0; }
            body { background: white; }
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main">
    <div class="page-heading">
        <div>
            <h1><i class="fa-solid fa-ticket" style="color:var(--orange);"></i> Karthika Festival Registrations</h1>
            <p>Registrations and submitted payment proofs for the 22 November 2026 event.</p>
        </div>
        <button class="print-button" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save PDF</button>
    </div>

    <?php if (!empty($_SESSION['success_msg'])): ?>
        <div class="notice success"><?php echo htmlspecialchars($_SESSION['success_msg'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_msg'])): ?>
        <div class="notice error"><?php echo htmlspecialchars($_SESSION['error_msg'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <?php if ($error_message !== ''): ?>
        <div class="error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php else: ?>
        <div class="stats">
            <div class="stat" style="--tint:#dbeafe;"><span>All registrations</span><strong><?php echo number_format($registration_count); ?></strong></div>
            <div class="stat" style="--tint:#ffedd5;"><span>Payment pending</span><strong><?php echo number_format($payment_pending_count); ?></strong></div>
            <div class="stat" style="--tint:#dcfce7;"><span>Payment confirmed</span><strong><?php echo number_format($payment_confirmed_count); ?></strong></div>
            <div class="stat" style="--tint:#bbf7d0;"><span>WhatsApp sent</span><strong><?php echo number_format($whatsapp_sent_count); ?></strong></div>
            <div class="stat" style="--tint:#fef3c7;"><span>Location pending</span><strong><?php echo number_format($location_pending_count); ?></strong></div>
            <div class="stat" style="--tint:#ffedd5;"><span>Expected ticket total</span><strong>₹<?php echo number_format($expected_total); ?></strong></div>
        </div>

        <form class="filter" method="get">
            <input type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by name or phone">
            <select name="pipeline" aria-label="Filter by CRM stage">
                <option value="all" <?php echo $pipeline === 'all' ? 'selected' : ''; ?>>All pipeline stages</option>
                <option value="payment_pending" <?php echo $pipeline === 'payment_pending' ? 'selected' : ''; ?>>Payment pending</option>
                <option value="payment_confirmed" <?php echo $pipeline === 'payment_confirmed' ? 'selected' : ''; ?>>Payment confirmed</option>
                <option value="whatsapp_pending" <?php echo $pipeline === 'whatsapp_pending' ? 'selected' : ''; ?>>WhatsApp pending</option>
                <option value="whatsapp_sent" <?php echo $pipeline === 'whatsapp_sent' ? 'selected' : ''; ?>>WhatsApp sent</option>
                <option value="location_pending" <?php echo $pipeline === 'location_pending' ? 'selected' : ''; ?>>Location pending</option>
                <option value="location_shared" <?php echo $pipeline === 'location_shared' ? 'selected' : ''; ?>>Location shared</option>
            </select>
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Apply</button>
            <?php if ($search !== '' || $pipeline !== 'all'): ?><a href="karthika_registrations.php">Reset</a><?php endif; ?>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Registered</th>
                        <th>Registrant</th>
                        <th>Booking</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>WhatsApp follow-up</th>
                        <th>Location</th>
                        <th>Payment proof</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registrations)): ?>
                        <tr><td class="empty" colspan="9">No registrations found for this search and pipeline stage.</td></tr>
                    <?php else: ?>
                        <?php foreach ($registrations as $registration): ?>
                            <?php
                            $whatsapp_number = preg_replace('/[^0-9]/', '', $registration['phone']);
                            if (substr($whatsapp_number, 0, 2) === '00') {
                                $whatsapp_number = substr($whatsapp_number, 2);
                            }
                            if (strlen($whatsapp_number) === 10) {
                                $whatsapp_number = '91' . $whatsapp_number;
                            } elseif (strlen($whatsapp_number) === 11 && $whatsapp_number[0] === '0') {
                                $whatsapp_number = '91' . substr($whatsapp_number, 1);
                            }
                            $venue_message = $registration['location_status'] === 'Shared'
                                ? 'Venue details have been shared with you separately.'
                                : 'Venue details will be shared with you shortly.';
                            $confirmation_message = sprintf(
                                "✨ *WE KONNECTS — BOOKING CONFIRMED* ✨\n━━━━━━━━━━━━━━━━━━\n👋 Hello *%s*!\n\n✅ Your payment is verified and your Karthika Festival booking is confirmed.\n\n🧾 *YOUR BOOKING*\n🎟️ Event tickets: %d\n🙏 Satyanarayana Vratam couples: %d\n💳 Amount received: ₹%s\n📅 Event date: 22 November 2026\n📍 Venue: %s\n\nWe look forward to celebrating with you! 🌼\n\nWarm regards,\n*WE KONNECTS*",
                                $registration['full_name'],
                                (int)$registration['event_ticket_count'],
                                (int)$registration['satyanarayana_vratam_couple_count'],
                                number_format((int)$registration['total_amount']),
                                $venue_message
                            );
                            $image_id = (int)$registration['id'];
                            $image_url = 'actions/karthika_payment_image.php?id=' . $image_id;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($registration['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($registration['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <a class="phone" href="tel:<?php echo htmlspecialchars($registration['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($registration['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php if (!empty($registration['interests'])): ?>
                                        <span class="pipeline-note"><?php echo htmlspecialchars(implode(', ', $registration['interests']), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($registration['interest_details'] !== ''): ?><span class="pipeline-note"><?php echo nl2br(htmlspecialchars($registration['interest_details'], ENT_QUOTES, 'UTF-8')); ?></span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    🎟️ <?php echo number_format((int)$registration['event_ticket_count']); ?> tickets<br>
                                    🙏 <?php echo number_format((int)$registration['satyanarayana_vratam_couple_count']); ?> vratam couples
                                </td>
                                <td><strong>₹<?php echo number_format((int)$registration['total_amount']); ?></strong></td>
                                <td class="pipeline-cell">
                                    <span class="status <?php echo $registration['payment_status'] === 'Confirmed' ? 'status-confirmed' : 'status-pending'; ?>">
                                        <?php echo $registration['payment_status'] === 'Confirmed' ? '✓ Paid' : '◷ Pending'; ?>
                                    </span>
                                    <?php if ($registration['payment_status'] === 'Pending' && $registration['payment_screenshot'] !== ''): ?>
                                        <form method="post" action="actions/confirm_karthika_payment.php" onsubmit="return confirm('Confirm that you manually verified this payment?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                                            <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="pipeline" value="<?php echo htmlspecialchars($pipeline, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button class="booking-action" type="submit">Confirm payment</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="pipeline-cell">
                                    <?php if ($registration['payment_status'] === 'Confirmed'): ?>
                                        <span class="status <?php echo $registration['whatsapp_status'] === 'Sent' ? 'status-sent' : 'status-pending'; ?>">
                                            <?php echo $registration['whatsapp_status'] === 'Sent' ? '✓ Message sent' : '◷ Not sent'; ?>
                                        </span>
                                        <?php if ($registration['whatsapp_sent_at']): ?><span class="pipeline-note"><?php echo htmlspecialchars(date('d M, h:i A', strtotime($registration['whatsapp_sent_at'])), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                        <?php if (strlen($whatsapp_number) >= 10 && strlen($whatsapp_number) <= 15): ?>
                                            <a class="booking-action whatsapp" href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode($confirmation_message); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> <?php echo $registration['whatsapp_status'] === 'Sent' ? 'Send again' : 'Open message'; ?></a>
                                            <?php if ($registration['whatsapp_status'] !== 'Sent'): ?>
                                                <form method="post" action="actions/update_karthika_pipeline.php" onsubmit="return confirm('Mark this WhatsApp confirmation as sent?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                                                    <input type="hidden" name="pipeline_action" value="mark_whatsapp_sent">
                                                    <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="pipeline" value="<?php echo htmlspecialchars($pipeline, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button class="booking-action neutral" type="submit">Mark as sent</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="pipeline-note">WhatsApp number unavailable</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="pipeline-note">Available after payment approval</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pipeline-cell">
                                    <span class="status <?php echo $registration['location_status'] === 'Shared' ? 'status-shared' : 'status-pending'; ?>">
                                        <?php echo $registration['location_status'] === 'Shared' ? '✓ Shared' : '◷ Pending'; ?>
                                    </span>
                                    <?php if ($registration['location_shared_at']): ?><span class="pipeline-note"><?php echo htmlspecialchars(date('d M, h:i A', strtotime($registration['location_shared_at'])), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                    <form method="post" action="actions/update_karthika_pipeline.php">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                                        <input type="hidden" name="pipeline_action" value="<?php echo $registration['location_status'] === 'Shared' ? 'mark_location_pending' : 'mark_location_shared'; ?>">
                                        <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="pipeline" value="<?php echo htmlspecialchars($pipeline, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="booking-action neutral" type="submit"><?php echo $registration['location_status'] === 'Shared' ? 'Undo shared' : 'Mark shared'; ?></button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($registration['payment_screenshot'] !== ''): ?>
                                        <a href="<?php echo $image_url; ?>" target="_blank" rel="noopener">
                                            <img class="proof-preview" src="<?php echo htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Payment proof for <?php echo htmlspecialchars($registration['full_name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" onerror="if (!this.dataset.fallback) { this.dataset.fallback = '1'; this.src = '../actions/karthika_payment_image.php?id=<?php echo $image_id; ?>'; } else { this.hidden = true; this.nextElementSibling.hidden = false; }">
                                            <span class="proof-fallback" hidden>Preview unavailable; use View proof</span>
                                        </a>
                                        <a class="proof" href="<?php echo $image_url; ?>" target="_blank" rel="noopener"><i class="fa-regular fa-image"></i> View proof</a>
                                    <?php else: ?>
                                        <span class="pipeline-note">Not uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="actions/delete_karthika_registration.php" onsubmit="return confirm('Permanently delete this registration and its uploaded payment proof? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                                        <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="pipeline" value="<?php echo htmlspecialchars($pipeline, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="booking-action danger" type="submit"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <span><?php echo number_format($filtered_count); ?> matching registration(s)</span>
            <span>
                <?php if ($page > 1): ?><a href="?search=<?php echo rawurlencode($search); ?>&pipeline=<?php echo rawurlencode($pipeline); ?>&page=<?php echo $page - 1; ?>">← Previous</a><?php endif; ?>
                <?php if ($page < $page_count): ?><a style="margin-left:16px;" href="?search=<?php echo rawurlencode($search); ?>&pipeline=<?php echo rawurlencode($pipeline); ?>&page=<?php echo $page + 1; ?>">Next →</a><?php endif; ?>
            </span>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
