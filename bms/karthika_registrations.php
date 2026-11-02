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
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = (!$page || $page > 1000000) ? 1 : $page;
$page_size = 50;
$offset = ($page - 1) * $page_size;
$registrations = [];
$registration_count = 0;
$expected_total = 0;
$error_message = '';

try {
    ensure_karthika_registrations_table($pdo);

    $stmtSummary = $pdo->query('SELECT COUNT(*) AS registration_count, COALESCE(SUM(total_amount), 0) AS expected_total FROM karthika_registrations');
    $summary = $stmtSummary->fetch();
    $registration_count = (int)$summary['registration_count'];
    $expected_total = (int)$summary['expected_total'];

    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE full_name LIKE ? OR phone LIKE ?';
        $search_term = '%' . $search . '%';
        $params = [$search_term, $search_term];
    }

    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM karthika_registrations' . $where);
    $stmtCount->execute($params);
    $filtered_count = (int)$stmtCount->fetchColumn();

    $stmtRows = $pdo->prepare("
        SELECT id, full_name, phone, event_ticket_count, satyanarayana_vratam_couple_count,
               total_amount, payment_screenshot, payment_status, interest_stalls,
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
        .stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat { border: 1px solid var(--border); border-radius: 12px; background: white; padding: 20px; }
        .stat span { color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .stat strong { display: block; margin-top: 6px; color: var(--blue); font-size: 26px; }
        .filter { display: flex; gap: 10px; margin-bottom: 20px; }
        .filter input { flex: 1; min-width: 0; border: 1px solid var(--border); border-radius: 8px; padding: 12px; font: inherit; }
        .filter button, .filter a { border: 0; border-radius: 8px; padding: 12px 16px; background: var(--blue); color: white; font-weight: 700; text-decoration: none; cursor: pointer; }
        .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; background: white; }
        table { width: 100%; border-collapse: collapse; min-width: 1180px; text-align: left; }
        th, td { padding: 14px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 13px; }
        th { background: #f1f5f9; color: var(--muted); font-size: 11px; text-transform: uppercase; white-space: nowrap; }
        tr:last-child td { border-bottom: 0; }
        .phone { color: #2563eb; text-decoration: none; font-weight: 600; white-space: nowrap; }
        .proof { display: inline-flex; gap: 7px; align-items: center; border-radius: 7px; padding: 8px 10px; background: #eff6ff; color: #1d4ed8; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .status { display: inline-block; border-radius: 20px; padding: 5px 9px; background: #fff7ed; color: #c2410c; font-size: 11px; font-weight: 800; }
        .status-confirmed { background: #dcfce7; color: #166534; }
        .proof-preview { display: block; width: 72px; height: 54px; margin-bottom: 7px; border: 1px solid var(--border); border-radius: 6px; object-fit: cover; }
        .booking-action { display: inline-block; margin-top: 7px; border: 0; border-radius: 7px; padding: 8px 10px; background: #166534; color: white; font: inherit; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer; white-space: nowrap; }
        .booking-action.whatsapp { background: #15803d; }
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
            .stats { gap: 10px; }
            .stat { padding: 15px; }
            .stat strong { font-size: 21px; }
            .filter { flex-wrap: wrap; }
            .filter input { flex-basis: 100%; }
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
            <div class="stat"><span>Total registrations</span><strong><?php echo number_format($registration_count); ?></strong></div>
            <div class="stat"><span>Expected ticket total</span><strong>₹<?php echo number_format($expected_total); ?></strong></div>
        </div>

        <form class="filter" method="get">
            <input type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by name or phone">
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            <?php if ($search !== ''): ?><a href="karthika_registrations.php">Clear</a><?php endif; ?>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Submitted</th>
                        <th>Name & phone</th>
                        <th>Event tickets</th>
                        <th>Vratam couples</th>
                        <th>Total</th>
                        <th>Other interests</th>
                        <th>Payment status & action</th>
                        <th>Payment screenshot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registrations)): ?>
                        <tr><td class="empty" colspan="8">No registrations found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($registrations as $registration): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($registration['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($registration['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <a class="phone" href="tel:<?php echo htmlspecialchars($registration['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($registration['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                                </td>
                                <td><?php echo number_format((int)$registration['event_ticket_count']); ?> × ₹499</td>
                                <td><?php echo number_format((int)$registration['satyanarayana_vratam_couple_count']); ?> × ₹999</td>
                                <td><strong>₹<?php echo number_format((int)$registration['total_amount']); ?></strong></td>
                                <td>
                                    <?php if (!empty($registration['interests'])): ?>
                                        <strong><?php echo htmlspecialchars(implode(', ', $registration['interests']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if ($registration['interest_details'] !== ''): ?><br><span style="color:#64748b;"><?php echo nl2br(htmlspecialchars($registration['interest_details'], ENT_QUOTES, 'UTF-8')); ?></span><?php endif; ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <span class="status <?php echo $registration['payment_status'] === 'Confirmed' ? 'status-confirmed' : ''; ?>"><?php echo htmlspecialchars($registration['payment_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($registration['payment_status'] === 'Pending' && $registration['payment_screenshot'] !== ''): ?>
                                        <form method="post" action="actions/confirm_karthika_payment.php" onsubmit="return confirm('Confirm that you manually verified this payment?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                                            <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button class="booking-action" type="submit">Confirm payment</button>
                                        </form>
                                    <?php elseif ($registration['payment_status'] === 'Confirmed'): ?>
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
                                        $confirmation_message = sprintf(
                                            'Hi %s, we have verified your payment of ₹%s and confirmed your Karthika Festival booking for 22 November 2026. Event tickets: %d; Satyanarayana Vratam couples: %d. Thank you, WE KONNECTS.',
                                            $registration['full_name'],
                                            number_format((int)$registration['total_amount']),
                                            (int)$registration['event_ticket_count'],
                                            (int)$registration['satyanarayana_vratam_couple_count']
                                        );
                                        ?>
                                        <?php if (strlen($whatsapp_number) >= 10 && strlen($whatsapp_number) <= 15): ?>
                                            <a class="booking-action whatsapp" href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode($confirmation_message); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Send WhatsApp</a>
                                        <?php else: ?>
                                            <div style="margin-top:7px;color:#64748b;font-size:12px;">WhatsApp number unavailable</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($registration['payment_screenshot'] !== ''): ?>
                                        <a href="actions/karthika_payment_image.php?id=<?php echo (int)$registration['id']; ?>" target="_blank" rel="noopener"><img class="proof-preview" src="actions/karthika_payment_image.php?id=<?php echo (int)$registration['id']; ?>" alt="Payment proof for <?php echo htmlspecialchars($registration['full_name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"></a>
                                        <a class="proof" href="actions/karthika_payment_image.php?id=<?php echo (int)$registration['id']; ?>" target="_blank" rel="noopener"><i class="fa-regular fa-image"></i> View proof</a>
                                    <?php else: ?>Not uploaded<?php endif; ?>
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
                <?php if ($page > 1): ?><a href="?search=<?php echo rawurlencode($search); ?>&page=<?php echo $page - 1; ?>">← Previous</a><?php endif; ?>
                <?php if ($page < $page_count): ?><a style="margin-left:16px;" href="?search=<?php echo rawurlencode($search); ?>&page=<?php echo $page + 1; ?>">Next →</a><?php endif; ?>
            </span>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
