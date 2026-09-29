<?php
// my_activity.php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = (isset($_SESSION['system_role']) && in_array($_SESSION['system_role'], ['SUPER_ADMIN', 'FRANCHISE_OWNER'])) || isset($_SESSION['impersonator_id']);
$chapter_members = [];

try {
    $stmtGroup = $pdo->prepare("SELECT group_id FROM group_members WHERE user_id = ? AND membership_status = 'Active' LIMIT 1");
    $stmtGroup->execute([$user_id]);
    $userGroup = $stmtGroup->fetch();
    $group_id = $userGroup ? $userGroup['group_id'] : null;

    if ($group_id) {
        $stmtMembers = $pdo->prepare("
            SELECT u.id as user_id, u.first_name, u.last_name 
            FROM group_members gm 
            JOIN users u ON gm.user_id = u.id 
            WHERE gm.group_id = ? AND u.id != ? AND gm.membership_status = 'Active' 
            ORDER BY u.first_name ASC
        ");
        $stmtMembers->execute([$group_id, $user_id]);
        $chapter_members = $stmtMembers->fetchAll();
    }

    $sqlLinks = "SELECT s.*, 
                 u1.first_name as init_first, u1.last_name as init_last,
                 u2.first_name as rec_first, u2.last_name as rec_last,
                 (SELECT COUNT(*) FROM slips t WHERE t.slip_type = 'TYFCB' AND t.initiator_member_id = s.receiver_member_id AND t.receiver_member_id = s.initiator_member_id AND COALESCE(NULLIF(t.deal_close_date, '0000-00-00'), t.date_logged) >= COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged)) as deal_closed,
                 (SELECT SUM(amount) FROM slips t WHERE t.slip_type = 'TYFCB' AND t.initiator_member_id = s.receiver_member_id AND t.receiver_member_id = s.initiator_member_id AND COALESCE(NULLIF(t.deal_close_date, '0000-00-00'), t.date_logged) >= COALESCE(NULLIF(s.link_given_date, '0000-00-00'), s.date_logged)) as closed_amount
                 FROM slips s 
                 LEFT JOIN users u1 ON s.initiator_member_id = u1.id 
                 LEFT JOIN users u2 ON s.receiver_member_id = u2.id 
                 WHERE s.slip_type = 'REFERRAL' AND (s.initiator_member_id = ? OR s.receiver_member_id = ?)
                 ORDER BY s.date_logged DESC";
    $stmtLinks = $pdo->prepare($sqlLinks);
    $stmtLinks->execute([$user_id, $user_id]);
    $links = $stmtLinks->fetchAll();

    $sqlDeals = "SELECT s.*, 
                 u1.first_name as init_first, u1.last_name as init_last,
                 u2.first_name as rec_first, u2.last_name as rec_last
                 FROM slips s 
                 LEFT JOIN users u1 ON s.initiator_member_id = u1.id 
                 LEFT JOIN users u2 ON s.receiver_member_id = u2.id 
                 WHERE s.slip_type = 'TYFCB' AND (s.initiator_member_id = ? OR s.receiver_member_id = ?)
                 ORDER BY s.date_logged DESC";
    $stmtDeals = $pdo->prepare($sqlDeals);
    $stmtDeals->execute([$user_id, $user_id]);
    $deals = $stmtDeals->fetchAll();

    $total_given = 0; 
    $total_received = 0; 
    foreach ($deals as $d) {
        if ($d['initiator_member_id'] == $user_id) $total_given += $d['amount'];
        if ($d['receiver_member_id'] == $user_id) $total_received += $d['amount'];
    }

    $sql121 = "SELECT s.*, 
               u1.first_name as init_first, u1.last_name as init_last,
               u2.first_name as rec_first, u2.last_name as rec_last
               FROM slips s 
               LEFT JOIN users u1 ON s.initiator_member_id = u1.id 
               LEFT JOIN users u2 ON s.receiver_member_id = u2.id 
               WHERE s.slip_type = '121' AND (s.initiator_member_id = ? OR s.receiver_member_id = ?)
               ORDER BY s.date_logged DESC";
    $stmt121 = $pdo->prepare($sql121);
    $stmt121->execute([$user_id, $user_id]);
    $ones = $stmt121->fetchAll();

    $stmtVis = $pdo->prepare("SELECT * FROM visitors WHERE invited_by = ? ORDER BY visit_date DESC");
    $stmtVis->execute([$user_id]);
    $visitors = $stmtVis->fetchAll();

    $stmtAtt = $pdo->prepare("SELECT a.attendance_status, cm.meeting_date, cm.meeting_type FROM attendance a JOIN chapter_meetings cm ON a.meeting_id = cm.id WHERE a.user_id = ? ORDER BY cm.meeting_date DESC");
    $stmtAtt->execute([$user_id]);
    $attendance = $stmtAtt->fetchAll();

} catch (PDOException $e) { $db_error = "Error loading activity: " . $e->getMessage(); }

function format_money($amount) {
    if (!$amount) return "0";
    if ($amount >= 10000000) return number_format($amount / 10000000, 2) . ' Cr';
    if ($amount >= 100000) return number_format($amount / 100000, 2) . ' L';
    if ($amount >= 1000) return number_format($amount / 1000, 1) . ' k';
    return number_format($amount);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Full Activity | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #00204a; margin: 0; }
        .activity-container { max-width: 600px; margin: 0 auto; padding: 20px; padding-bottom: 100px; }
        .page-title { color: var(--white); font-size: 20px; font-weight: 800; margin-bottom: 20px; }

        .summary-card { background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02)); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; padding: 20px; margin-bottom: 25px; backdrop-filter: blur(10px); display: flex; justify-content: space-between; align-items: center;}
        .summary-block { text-align: center; flex: 1; }
        .summary-block.middle { border-left: 1px solid rgba(255,255,255,0.1); border-right: 1px solid rgba(255,255,255,0.1); }
        .summary-label { font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
        .summary-val { font-size: 20px; font-weight: 800; color: white; }
        .val-green { color: #10b981; }
        .val-orange { color: var(--primary-orange); }

        .tabs { display: flex; gap: 8px; margin-bottom: 25px; overflow-x: auto; padding-bottom: 10px; scrollbar-width: none; }
        .tabs::-webkit-scrollbar { display: none; }
        .tab-btn { flex: 0 0 auto; text-align: center; padding: 10px 15px; color: rgba(255,255,255,0.5); font-weight: 600; cursor: pointer; border-radius: 8px; transition: 0.3s; font-size: 13px; background: transparent; border: 1px solid transparent;}
        .tab-btn.active { background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.2); }

        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .ledger-item { background: rgba(255, 255, 255, 0.03); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 18px 15px; display: flex; justify-content: space-between; align-items: center; transition: 0.2s;}
        .ledger-item:hover { background: rgba(255,255,255,0.06); }
        .ledger-item:first-child { border-radius: 12px 12px 0 0; }
        .ledger-item:last-child { border-radius: 0 0 12px 12px; border-bottom: none;}
        .ledger-container { border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; margin-bottom: 20px; }
        
        .ledger-left { display: flex; align-items: center; gap: 15px; }
        .ledger-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;}
        .icon-in { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .icon-out { background: rgba(255, 107, 0, 0.1); color: var(--primary-orange); }
        .icon-neutral { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        
        .ledger-info h3 { margin: 0 0 4px 0; font-size: 14px; font-weight: 600; color: white; }
        .ledger-info p { margin: 0; font-size: 11px; color: rgba(255,255,255,0.5); }
        
        .ledger-right { text-align: right; }
        .ledger-amount { font-size: 16px; font-weight: 800; display: block; margin-bottom: 4px; }
        .amount-in { color: #10b981; }
        .amount-out { color: var(--primary-orange); }
        
        .status-badge { display: inline-block; font-size: 9px; font-weight: 700; padding: 3px 8px; border-radius: 12px; text-transform: uppercase; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7); }
        .badge-green { background: rgba(16, 185, 129, 0.1); color: #34d399; border-color: rgba(16, 185, 129, 0.3); }

        .btn-action-sm { background: #3b82f6; color: white; border: none; padding: 5px 10px; border-radius: 6px; font-size: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 5px; display: inline-block;}
        
        .bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: min(100vw, 640px); max-width: 640px; box-sizing: border-box; padding: 15px 8px calc(15px + env(safe-area-inset-bottom)); display: flex; justify-content: space-around; border-radius: 20px 20px 0 0; background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(16px); z-index: 100; border-top: 1px solid rgba(255,255,255,0.1);}
        .nav-item { color: rgba(255,255,255,0.6); text-decoration: none; text-align: center; font-size: 11px; font-weight: 500; transition: color 0.3s; }
        .nav-item.active { color: var(--primary-orange); }
        .nav-item i { display: block; font-size: 20px; margin-bottom: 5px; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,20,45,0.9); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px; box-sizing: border-box;}
        .modal-box { background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(25px); padding: 30px; border-radius: 16px; width: 100%; max-width: 400px; color: white; position: relative; max-height: 90vh; overflow-y: auto;}
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: rgba(255,255,255,0.6); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: rgba(255,255,255,0.9); }
        .glass-input { width: 100%; padding: 12px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: white; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
    </style>
</head>
<body>

<div class="activity-container">
    <h1 class="page-title">Activity Ledger</h1>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;"><i class="fa-solid fa-check"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <div class="summary-card">
        <div class="summary-block">
            <span class="summary-label">Revenue Given</span>
            <span class="summary-val val-orange">₹<?php echo format_money($total_given); ?></span>
        </div>
        <div class="summary-block middle">
            <span class="summary-label">Revenue Received</span>
            <span class="summary-val val-green">₹<?php echo format_money($total_received); ?></span>
        </div>
    </div>

    <div class="tabs">
        <div class="tab-btn active" onclick="switchTab('links')">Links</div>
        <div class="tab-btn" onclick="switchTab('deals')">Deals</div>
        <div class="tab-btn" onclick="switchTab('121s')">1-to-1s</div>
        <div class="tab-btn" onclick="switchTab('visitors')">Visitors</div>
        <div class="tab-btn" onclick="switchTab('attendance')">Attendance</div>
    </div>

    <div id="tab-links" class="tab-content active">
        <div style="margin-bottom: 15px; display: flex; justify-content: flex-end;">
            <button onclick="openModal('modalLinkGiven')" style="background:#3b82f6; color:white; border:none; padding:10px 15px; border-radius:8px; font-weight:600; cursor:pointer; font-size: 12px;"><i class="fa-solid fa-plus"></i> Bulk Pass Links</button>
        </div>
        
        <?php if (empty($links)): ?>
            <p style="text-align:center; color:rgba(255,255,255,0.5); margin-top:40px;">No links passed or received yet.</p>
        <?php else: ?>
            <div class="ledger-container">
            <?php foreach ($links as $link): 
                $is_received = ($link['receiver_member_id'] == $user_id);
                $other_person = $is_received ? ($link['init_first'] . ' ' . $link['init_last']) : ($link['rec_first'] . ' ' . $link['rec_last']);
                $icon_class = $is_received ? 'icon-in' : 'icon-out';
                $icon = $is_received ? 'fa-arrow-down' : 'fa-arrow-up';
            ?>
                <div class="ledger-item">
                    <div class="ledger-left">
                        <div class="ledger-icon <?php echo $icon_class; ?>"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                        <div class="ledger-info">
                            <h3><?php echo htmlspecialchars($other_person); ?></h3>
                            <p><?php echo date('M d, Y', strtotime($link['date_logged'])); ?> • <?php echo htmlspecialchars($link['referral_type'] ?? 'INSIDE'); ?></p>
                            <?php if (!empty($link['topics_discussed'])): ?>
                                <p style="font-style: italic; opacity: 0.8; margin-top: 3px;">"<?php echo htmlspecialchars($link['topics_discussed']); ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ledger-right">
                        <?php if ($is_received): ?>
                            <?php if ($link['deal_closed'] > 0): ?>
                                <span class="status-badge badge-green">Closed</span>
                                <span class="ledger-amount amount-in" style="font-size: 13px; margin-top:5px;">₹<?php echo format_money($link['closed_amount'] ?? 0); ?></span>
                            <?php else: ?>
                                <button onclick="openCloseDealModal(<?php echo $link['initiator_member_id']; ?>, '<?php echo addslashes(htmlspecialchars($other_person)); ?>')" class="btn-action-sm">Close Deal</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($link['deal_closed'] > 0): ?>
                                <span class="status-badge badge-green">Won</span>
                            <?php else: ?>
                                <span class="status-badge">Pending</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="tab-deals" class="tab-content">
        <div style="margin-bottom: 15px; display: flex; justify-content: flex-end;">
            <button onclick="openModal('modalNewDeal')" style="background:var(--primary-orange); color:white; border:none; padding:10px 15px; border-radius:8px; font-weight:600; cursor:pointer; font-size: 12px;"><i class="fa-solid fa-plus"></i> Log Standalone Deal</button>
        </div>

        <?php if (empty($deals)): ?>
            <p style="text-align:center; color:rgba(255,255,255,0.5); margin-top:20px;">No deals logged yet.</p>
        <?php else: ?>
            <div class="ledger-container">
            <?php foreach ($deals as $deal): 
                $is_closer = ($deal['initiator_member_id'] == $user_id);
                $other_person = $is_closer ? ($deal['rec_first'] . ' ' . $deal['rec_last']) : ($deal['init_first'] . ' ' . $deal['init_last']);
                $icon_class = $is_closer ? 'icon-out' : 'icon-in';
                $amount_class = $is_closer ? 'amount-out' : 'amount-in';
            ?>
                <div class="ledger-item">
                    <div class="ledger-left">
                        <div class="ledger-icon <?php echo $icon_class; ?>"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div class="ledger-info">
                            <h3><?php echo htmlspecialchars($other_person); ?></h3>
                            <p><?php echo date('M d, Y', strtotime($deal['date_logged'])); ?> • <?php echo $is_closer ? 'Thanking Them' : 'Closed By Them'; ?></p>
                        </div>
                    </div>
                    <div class="ledger-right">
                        <span class="ledger-amount <?php echo $amount_class; ?>">₹<?php echo format_money($deal['amount']); ?></span>
                        <?php if ($is_admin): ?>
                            <button onclick="openEditDealModal(<?php echo $deal['id']; ?>, <?php echo $deal['amount']; ?>)" style="background:transparent; border:none; color:#fbbf24; font-size:11px; cursor:pointer; padding:0; margin-top:5px; text-decoration:underline;">Edit</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="tab-121s" class="tab-content">
        <?php if (empty($ones)): ?>
            <p style="text-align:center; color:rgba(255,255,255,0.5); margin-top:40px;">No 1-to-1 meetings logged yet.</p>
        <?php else: ?>
            <div class="ledger-container">
            <?php foreach ($ones as $one): 
                $other_person = ($one['initiator_member_id'] == $user_id) ? ($one['rec_first'] . ' ' . $one['rec_last']) : ($one['init_first'] . ' ' . $one['init_last']);
            ?>
                <div class="ledger-item">
                    <div class="ledger-left">
                        <div class="ledger-icon icon-neutral"><i class="fa-solid fa-handshake"></i></div>
                        <div class="ledger-info">
                            <h3><?php echo htmlspecialchars($other_person); ?></h3>
                            <p><?php echo date('M d, Y', strtotime($one['date_logged'])); ?></p>
                        </div>
                    </div>
                    <div class="ledger-right">
                        <span class="status-badge badge-green"><i class="fa-solid fa-check"></i></span>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="tab-visitors" class="tab-content">
        <?php if (empty($visitors)): ?>
            <p style="text-align:center; color:rgba(255,255,255,0.5); margin-top:40px;">No visitors invited yet.</p>
        <?php else: ?>
            <div class="ledger-container">
            <?php foreach ($visitors as $vis): ?>
                <div class="ledger-item">
                    <div class="ledger-left">
                        <div class="ledger-icon" style="background: rgba(139, 92, 246, 0.1); color: #c4b5fd;"><i class="fa-solid fa-user-plus"></i></div>
                        <div class="ledger-info">
                            <h3><?php echo htmlspecialchars($vis['visitor_name']); ?></h3>
                            <p><?php echo date('M d, Y', strtotime($vis['visit_date'])); ?> • <?php echo htmlspecialchars($vis['company_name']); ?></p>
                        </div>
                    </div>
                    <div class="ledger-right">
                        <?php if($vis['status'] == 'Joined'): ?>
                            <span class="status-badge badge-green">Joined</span>
                        <?php else: ?>
                            <span class="status-badge"><?php echo htmlspecialchars($vis['status']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="tab-attendance" class="tab-content">
        <?php if (empty($attendance)): ?>
            <p style="text-align:center; color:rgba(255,255,255,0.5); margin-top:40px;">No attendance records found.</p>
        <?php else: ?>
            <div class="ledger-container">
            <?php foreach ($attendance as $att): 
                $bg = 'icon-neutral';
                if ($att['attendance_status'] == 'Present') $bg = 'icon-in';
                if ($att['attendance_status'] == 'Absent') $bg = 'icon-out';
            ?>
                <div class="ledger-item">
                    <div class="ledger-left">
                        <div class="ledger-icon <?php echo $bg; ?>"><i class="fa-solid fa-calendar-check"></i></div>
                        <div class="ledger-info">
                            <h3><?php echo date('M d, Y', strtotime($att['meeting_date'])); ?></h3>
                            <p><?php echo htmlspecialchars($att['meeting_type']); ?></p>
                        </div>
                    </div>
                    <div class="ledger-right">
                        <span class="status-badge" style="<?php if($att['attendance_status'] == 'Present') echo 'border-color:#10b981; color:#34d399;'; ?>"><?php echo htmlspecialchars($att['attendance_status']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fa-solid fa-house"></i> Home</a>
    <a href="my_activity.php" class="nav-item active"><i class="fa-solid fa-chart-line"></i> Activity</a>
    <a href="directory.php" class="nav-item"><i class="fa-solid fa-magnifying-glass"></i> Search</a>
    <a href="profile.php" class="nav-item"><i class="fa-solid fa-user"></i> Profile</a>
</div>

<!-- ========================================== -->
<!-- MODALS -->
<!-- ========================================== -->

<!-- Bulk Link Modal -->
<div class="modal-overlay" id="modalLinkGiven">
    <div class="modal-box" style="max-height: 90vh; overflow-y: auto;">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalLinkGiven')"></i>
        <h3 style="margin-top:0; color:white;"><i class="fa-solid fa-link" style="color:#3b82f6;"></i> Give Links</h3>
        
        <form action="actions/submit_slip.php" method="POST" id="bulkReferralForm">
            <input type="hidden" name="slip_type" value="REFERRAL">
            
            <div id="referralBlocksContainer">
                <div class="referral-block" style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.2); margin-bottom: 15px; position: relative;">
                    <div class="form-group">
                        <label>Give Link To (Local Chapter)</label>
                        <select name="receiver_member_id[]" class="glass-input" style="color: black; background: white;" required>
                            <option value="">-- Select Member --</option>
                            <?php foreach ($chapter_members as $m): ?>
                                <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Link Type</label>
                        <select name="referral_type[]" class="glass-input" style="color: black; background: white;" required onchange="toggleOutsideFields(this)">
                            <option value="INSIDE">Inside (I am buying their service)</option>
                            <option value="OUTSIDE">Outside (Someone else is buying)</option>
                        </select>
                    </div>

                    <div class="outsideDetailsBox" style="display: none; background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 8px; border: 1px dashed #3b82f6; margin-bottom: 15px;">
                        <p style="margin: 0 0 10px 0; font-size: 12px; color: #60a5fa; font-weight: 600;">Outside Lead Details</p>
                        <div class="form-group">
                            <label>Lead's Name</label>
                            <input type="text" name="outside_ref_name[]" class="glass-input outside_ref_name" style="color: black; background: white;" placeholder="e.g. John Doe">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Lead's Phone Number</label>
                            <input type="text" name="outside_ref_phone[]" class="glass-input outside_ref_phone" style="color: black; background: white;" placeholder="e.g. 9876543210">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Remarks / Details</label>
                        <textarea name="remarks[]" class="glass-input" style="color: black; background: white; height: 60px;" required placeholder="What is this link regarding?"></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Date</label>
                        <input type="date" name="date_logged[]" class="glass-input" style="color: black; background: white;" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>

            <button type="button" onclick="addReferralBlock()" style="background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px dashed #3b82f6; padding: 12px; width: 100%; border-radius: 8px; font-weight: 700; cursor: pointer; margin-bottom: 15px; transition: 0.2s;"><i class="fa-solid fa-plus"></i> Add Another Link</button>

            <button type="submit" style="background:#3b82f6; color:white; border:none; padding:15px; width:100%; border-radius:8px; font-weight:700; cursor:pointer;">Submit All Links</button>
        </form>
    </div>
</div>

<!-- Close Pending Deal Modal -->
<div class="modal-overlay" id="modalCloseDeal">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalCloseDeal')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;"><i class="fa-solid fa-handshake-simple" style="color:#10b981;"></i> Close Deal</h3>
        <form action="actions/submit_slip.php" method="POST" onsubmit="return preventDoubleSubmit('btnSubmitDeal')">
            <input type="hidden" name="slip_type" value="TYFCB">
            <input type="hidden" name="receiver_member_id" id="closeDealReceiverId">
            <p style="font-size:13px; color:rgba(255,255,255,0.7); margin-bottom:15px;">Sending Thank You To: <br><strong id="closeDealMemberName" style="color:white; font-size:16px;"></strong></p>
            
            <div class="form-group">
                <label>Deal Amount (₹)</label>
                <input type="number" name="amount" class="glass-input" required placeholder="e.g. 25000" min="1" style="background: white; color: black;">
            </div>
            
            <div class="form-group">
                <label>Date Closed</label>
                <input type="date" name="date_logged" class="glass-input" required value="<?php echo date('Y-m-d'); ?>" style="background: white; color: black;">
            </div>
            <button type="submit" id="btnSubmitDeal" style="background:#10b981; color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:600; cursor:pointer; margin-top:10px;">Submit Deal Closed</button>
        </form>
    </div>
</div>

<!-- Log NEW Standalone Deal Modal -->
<div class="modal-overlay" id="modalNewDeal">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalNewDeal')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px; color:white;"><i class="fa-solid fa-sack-dollar" style="color:#10b981;"></i> Log New Deal Done</h3>
        <form action="actions/submit_slip.php" method="POST" onsubmit="return preventDoubleSubmit('btnSubmitNewDeal')">
            <input type="hidden" name="slip_type" value="TYFCB">
            
            <div class="form-group">
                <label>Thanking (Local Chapter)</label>
                <select name="receiver_member_id" class="glass-input" style="color: black; background: white;" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($chapter_members as $m): ?>
                        <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Was this an Inside or Outside deal?</label>
                <select name="deal_type" class="glass-input" style="color: black; background: white;" required>
                    <option value="INSIDE">Inside Deal (They bought from me - 2 pts)</option>
                    <option value="OUTSIDE">Outside Deal (Someone else bought - 4 pts)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Deal Amount (₹)</label>
                <input type="number" name="amount" class="glass-input" required placeholder="e.g. 25000" min="1" style="background: white; color: black;">
            </div>
            <div class="form-group">
                <label>Date Closed</label>
                <input type="date" name="date_logged" class="glass-input" required value="<?php echo date('Y-m-d'); ?>" style="background: white; color: black;">
            </div>
            <button type="submit" id="btnSubmitNewDeal" style="background:#10b981; color:white; border:none; padding:12px; width:100%; border-radius:8px; font-weight:600; cursor:pointer; margin-top:10px;">Submit Deal Closed</button>
        </form>
    </div>
</div>

<?php if ($is_admin): ?>
<div class="modal-overlay" id="modalEditDeal">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalEditDeal')"></i>
        <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px; color:#fbbf24;"><i class="fa-solid fa-pen"></i> Edit Deal Amount</h3>
        <form action="actions/edit_tyfcb.php" method="POST">
            <input type="hidden" name="slip_id" id="editDealId">
            <div class="form-group">
                <label>Corrected Deal Amount (₹)</label>
                <input type="number" name="amount" id="editDealAmount" class="glass-input" required min="1" style="background: white; color: black; font-weight: bold; font-size: 18px;">
            </div>
            <button type="submit" style="background:#fbbf24; color:#00204a; border:none; padding:12px; width:100%; border-radius:8px; font-weight:700; cursor:pointer; margin-top:10px;">Save Correction</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    function openModal(modalId) {
        if(modalId === 'modalNewDeal') {
            var btn = document.getElementById('btnSubmitNewDeal');
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerHTML = 'Submit Deal Closed';
        }
        document.getElementById(modalId).style.display = 'flex';
    }

    function openCloseDealModal(receiverId, memberName) {
        document.getElementById('closeDealReceiverId').value = receiverId;
        document.getElementById('closeDealMemberName').innerText = memberName;
        document.getElementById('modalCloseDeal').style.display = 'flex';
    }

    function openEditDealModal(slipId, currentAmount) {
        document.getElementById('editDealId').value = slipId;
        document.getElementById('editDealAmount').value = currentAmount;
        document.getElementById('modalEditDeal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function preventDoubleSubmit(btnId) {
        var btn = document.getElementById(btnId);
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        return true;
    }

    function toggleOutsideFields(selectElement) {
        const block = selectElement.closest('.referral-block');
        const box = block.querySelector('.outsideDetailsBox');
        const nameInput = block.querySelector('.outside_ref_name');
        const phoneInput = block.querySelector('.outside_ref_phone');
        
        if (selectElement.value === 'OUTSIDE') {
            box.style.display = 'block';
            nameInput.required = true;
            phoneInput.required = true;
        } else {
            box.style.display = 'none';
            nameInput.required = false;
            phoneInput.required = false;
            nameInput.value = '';
            phoneInput.value = '';
        }
    }

    function addReferralBlock() {
        const container = document.getElementById('referralBlocksContainer');
        const originalBlock = container.querySelector('.referral-block');
        const newBlock = originalBlock.cloneNode(true);
        
        newBlock.querySelectorAll('input, textarea').forEach(input => {
            if(input.name !== 'date_logged[]') input.value = '';
        });
        
        newBlock.querySelector('.outsideDetailsBox').style.display = 'none';
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Remove Link';
        removeBtn.style.cssText = 'background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; margin-top: 10px; display: inline-block;';
        removeBtn.onclick = function() { newBlock.remove(); };
        newBlock.appendChild(removeBtn);

        container.appendChild(newBlock);
    }
</script>

</body>
</html>