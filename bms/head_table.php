<?php
// head_table.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

try {
    // Verify Coordinator Access
    $stmtVerify = $pdo->prepare("
        SELECT gm.group_id, g.group_name, g.default_venue 
        FROM group_members gm
        JOIN groups g ON gm.group_id = g.id 
        WHERE gm.user_id = ? AND gm.leadership_role = 'Coordinator' AND gm.membership_status = 'Active'
    ");
    $stmtVerify->execute([$user_id]);
    $coord = $stmtVerify->fetch();

    if (!$coord) { die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Access Denied</h2><p>Restricted to Head Table.</p></div>"); }
    
    $group_id = $coord['group_id'];
    $group_name = $coord['group_name'];
    $default_venue = $coord['default_venue'] ?? '';

    $stmtMtg = $pdo->prepare("SELECT * FROM chapter_meetings WHERE group_id = ? AND meeting_type IN ('Meeting', 'Event') ORDER BY meeting_date DESC LIMIT 15");
    $stmtMtg->execute([$group_id]);
    $regular_meetings = $stmtMtg->fetchAll();

    $stmtSom = $pdo->prepare("SELECT * FROM chapter_meetings WHERE group_id = ? AND meeting_type = 'SOM' ORDER BY meeting_date DESC LIMIT 15");
    $stmtSom->execute([$group_id]);
    $soms = $stmtSom->fetchAll();
    
    $all_meetings = array_merge($regular_meetings, $soms);

    // Fetch members for speaker selection
    $stmtMembers = $pdo->prepare("
        SELECT u.id as user_id, u.first_name, u.last_name, gm.renewal_date 
        FROM group_members gm 
        JOIN users u ON gm.user_id = u.id 
        WHERE gm.group_id = ? 
        AND gm.membership_status = 'Active' 
        AND (gm.leadership_role != 'Coordinator' OR gm.leadership_role IS NULL) 
        ORDER BY u.first_name ASC
    ");
    $stmtMembers->execute([$group_id]);
    $chapter_members = $stmtMembers->fetchAll();

    $stmtAC = $pdo->prepare("SELECT u.id, u.first_name, u.last_name FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? AND gm.leadership_role = 'Attendance' AND gm.membership_status = 'Active' LIMIT 1");
    $stmtAC->execute([$group_id]);
    $current_ac = $stmtAC->fetch();

    // UPCOMING CELEBRATIONS LOGIC
    $stmtBday = $pdo->prepare("SELECT first_name, last_name, dob, DATE_FORMAT(dob, '%M %d') as display_date FROM users u JOIN group_members gm ON u.id = gm.user_id WHERE gm.group_id = ? AND gm.membership_status = 'Active' AND u.dob IS NOT NULL ORDER BY CASE WHEN DATE_FORMAT(dob, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d') THEN 0 ELSE 1 END, DATE_FORMAT(dob, '%m-%d') ASC LIMIT 10");
    $stmtBday->execute([$group_id]);
    $birthdays = $stmtBday->fetchAll();
    
    $stmtAnniv = $pdo->prepare("SELECT first_name, last_name, anniversary_date, DATE_FORMAT(anniversary_date, '%M %d') as display_date FROM users u JOIN group_members gm ON u.id = gm.user_id WHERE gm.group_id = ? AND gm.membership_status = 'Active' AND u.anniversary_date IS NOT NULL ORDER BY CASE WHEN DATE_FORMAT(anniversary_date, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d') THEN 0 ELSE 1 END, DATE_FORMAT(anniversary_date, '%m-%d') ASC LIMIT 10");
    $stmtAnniv->execute([$group_id]);
    $anniversaries = $stmtAnniv->fetchAll();
    
    $stmtRenew = $pdo->prepare("SELECT u.id as user_id, u.first_name, u.last_name, gm.renewal_date, (SELECT status FROM pending_renewals pr WHERE pr.user_id = u.id ORDER BY id DESC LIMIT 1) as payment_status FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? AND gm.membership_status = 'Active' AND gm.renewal_date IS NOT NULL ORDER BY gm.renewal_date ASC LIMIT 10");
    $stmtRenew->execute([$group_id]);
    $renewals_list = $stmtRenew->fetchAll();

    // Map IDs to Names for Displaying Speakers
    $member_map = [];
    foreach ($chapter_members as $cm) { $member_map[$cm['user_id']] = $cm['first_name'] . ' ' . $cm['last_name']; }

} catch (PDOException $e) { die("System Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Head Table Portal | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <style>
        :root { --brand-blue: #00204a; --brand-orange: #ff6b00; --bg-color: #f8fafc; --card-bg: #ffffff; --border: #e2e8f0; --text-main: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-color); color: var(--text-main); margin: 0; padding-bottom: 50px; overflow-x: hidden;}
        
        .portal-container { width: 100%; max-width: 600px; margin: 0 auto; padding: 15px; box-sizing: border-box; }
        
        .header-box { background: var(--brand-blue); padding: 25px 20px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8px 20px rgba(0,32,74,0.15); position: relative; overflow: hidden;}
        .header-box::after { content: ''; position: absolute; right: -20px; bottom: -20px; width: 120px; height: 120px; background: var(--brand-orange); border-radius: 50%; filter: blur(50px); opacity: 0.3; z-index: 1;}
        .header-content { position: relative; z-index: 2;}
        .header-box h1 { margin: 0 0 5px 0; font-size: 20px; font-weight: 800;}
        .header-box p { margin: 0; color: #94a3b8; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;}
        
        .section-title { font-size: 15px; font-weight: 800; color: var(--brand-blue); margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid var(--border); padding-bottom: 8px;}
        
        .action-grid { display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px; }
        .action-card { background: var(--card-bg); padding: 18px 15px; border-radius: 12px; border: 1px solid var(--border); text-decoration: none; color: var(--text-main); display: flex; align-items: center; gap: 15px;}
        .action-card:active { transform: scale(0.98); }
        .action-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .action-text { flex-grow: 1; }
        .action-title { font-weight: 700; font-size: 14px; margin-bottom: 3px; color: var(--brand-blue);}
        .action-desc { font-size: 11px; color: var(--text-muted); margin: 0; line-height: 1.3;}

        .theme-blue .action-icon { background: #eff6ff; color: #3b82f6; }
        .theme-green .action-icon { background: #ecfdf5; color: #10b981; }
        .theme-purple .action-icon { background: #fdf4ff; color: #c026d3; }
        .theme-yellow .action-icon { background: #fefce8; color: #ca8a04; }

        .complex-card { background: var(--card-bg); padding: 18px 15px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 12px; display: flex; flex-direction: column; gap: 12px;}
        .btn-primary { background: var(--brand-blue); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; text-align: center; width: 100%; box-sizing: border-box;}
        .btn-accent { background: var(--brand-orange); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; text-align: center; width: 100%; box-sizing: border-box;}
        
        .btn-outline-danger { background: transparent; color: #ef4444; border: 1px solid #ef4444; padding: 12px; border-radius: 8px; font-weight: 600; text-align: center; width: 100%; box-sizing: border-box; text-decoration: none;}

        .calendar-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px;}
        .meeting-card { background: white; border: 1px solid var(--border); padding: 15px; border-radius: 12px; display: flex; flex-direction: column; gap: 12px;}
        .meeting-header { display: flex; justify-content: space-between; align-items: flex-start;}
        .meeting-info h3 { margin: 0 0 5px 0; font-size: 15px; color: var(--brand-blue); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;}
        .badge-type { font-size: 10px; padding: 3px 8px; border-radius: 12px; font-weight: 700; border: 1px solid;}
        .badge-meeting { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
        .badge-som { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .badge-event { background: #fdf4ff; color: #c026d3; border-color: #e879f9; }

        .meeting-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .btn-manage { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 8px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; text-align: center;}
        .btn-edit { background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; padding: 8px; border-radius: 8px; font-size: 12px; font-weight: 600; text-align: center;}

        .completed-event { display: none; }
        .btn-toggle-completed { background: #f1f5f9; color: #475569; border: 1px dashed #cbd5e1; padding: 12px; width: 100%; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; cursor: pointer; text-align: center;}

        .celeb-list-container { display: flex; flex-direction: column; gap: 15px;}
        .celeb-col { background: white; border-radius: 12px; border: 1px solid var(--border); overflow: hidden;}
        .celeb-header { padding: 12px 15px; background: #f8fafc; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 8px;}
        .celeb-list { padding: 0 15px;}
        .celeb-item { padding: 12px 0; border-bottom: 1px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center; font-size: 13px;}
        .celeb-item:last-child { border-bottom: none;}
        .celeb-date { font-weight: 700; color: var(--brand-orange); font-size: 12px;}

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,32,74,0.9); z-index: 1000; align-items: flex-end; justify-content: center; box-sizing: border-box;}
        .modal-box { background: white; padding: 25px 20px 40px 20px; border-radius: 20px 20px 0 0; width: 100%; position: relative; max-height: 85vh; overflow-y: auto; animation: slideUp 0.3s ease-out;}
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--brand-blue); text-transform: uppercase;}
        .form-input { width: 100%; padding: 14px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; background: #f8fafc;}
        
        .ts-control { padding: 14px 12px !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; background: #f8fafc !important; font-family: 'Inter' !important; }
        .ts-control.focus { border-color: var(--brand-orange) !important; background: white !important; box-shadow: 0 0 0 3px rgba(255,107,0,0.1) !important;}
        .ts-dropdown { border-radius: 8px !important; border: 1px solid #cbd5e1 !important; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;}
    </style>
</head>
<body>

<div class="portal-container">
    <a href="index.php" style="color: var(--text-muted); text-decoration: none; margin-bottom: 15px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; margin-bottom: 15px; display:flex; align-items:center; gap:8px; font-size: 13px;"><i class="fa-solid fa-check-circle" style="font-size:16px;"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px; border-radius: 8px; margin-bottom: 15px; display:flex; align-items:center; gap:8px; font-size: 13px;"><i class="fa-solid fa-triangle-exclamation" style="font-size:16px;"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <div class="header-box">
        <div class="header-content">
            <p>Command Center</p>
            <h1><?php echo htmlspecialchars($group_name); ?></h1>
        </div>
        <i class="fa-solid fa-chess-queen" style="font-size: 40px; color: var(--brand-orange); position: relative; z-index: 2;"></i>
    </div>

    <h2 class="section-title"><i class="fa-solid fa-layer-group"></i> Core Operations</h2>
    <div class="action-grid">
        <a href="attendance_manager.php" class="action-card theme-blue">
            <div class="action-icon"><i class="fa-solid fa-clipboard-user"></i></div>
            <div class="action-text">
                <div class="action-title">Attendance Manager</div>
                <p class="action-desc">Log weekly meeting presence.</p>
            </div>
            <i class="fa-solid fa-chevron-right" style="color: #cbd5e1;"></i>
        </a>
        <a href="manage_visitors.php" class="action-card theme-purple">
            <div class="action-icon"><i class="fa-solid fa-users-viewfinder"></i></div>
            <div class="action-text">
                <div class="action-title">Visitor Pipeline</div>
                <p class="action-desc">Track guests and conversions.</p>
            </div>
            <i class="fa-solid fa-chevron-right" style="color: #cbd5e1;"></i>
        </a>
        <a href="chapter_summary.php" class="action-card theme-yellow">
            <div class="action-icon"><i class="fa-solid fa-chart-pie"></i></div>
            <div class="action-text">
                <div class="action-title">Chapter Analytics</div>
                <p class="action-desc">Review monthly growth & points.</p>
            </div>
            <i class="fa-solid fa-chevron-right" style="color: #cbd5e1;"></i>
        </a>
        <a href="daily_status_portal.php" class="action-card theme-green">
            <div class="action-icon"><i class="fa-solid fa-clipboard-check"></i></div>
            <div class="action-text">
                <div class="action-title">Daily Status Portal</div>
                <p class="action-desc">View and override member logs.</p>
            </div>
            <i class="fa-solid fa-chevron-right" style="color: #cbd5e1;"></i>
        </a>
    </div>

    <h2 class="section-title"><i class="fa-solid fa-users-gear"></i> Delegation & Finance</h2>
    <div class="complex-card">
        <div>
            <strong style="color: var(--brand-blue); font-size:14px; display:block; margin-bottom:2px;">Process Member Renewals</strong>
            <p style="margin:0; font-size:12px; color:var(--text-muted);">Submit cash/transfers for activation.</p>
        </div>
        <button onclick="openModal('modalRenewal')" class="btn-primary"><i class="fa-solid fa-file-invoice-dollar" style="margin-right:5px;"></i> Submit Payment</button>
    </div>

    <div class="complex-card">
        <div>
            <strong style="color: var(--brand-blue); font-size:14px; display:block; margin-bottom:2px;"><i class="fa-solid fa-user-check" style="color: var(--brand-orange);"></i> Attendance Coordinator</strong>
            <?php if ($current_ac): ?>
                <p style="margin:0; font-size:12px; color:#10b981; font-weight:600;">Active: <?php echo htmlspecialchars($current_ac['first_name'] . ' ' . $current_ac['last_name']); ?></p>
            <?php else: ?>
                <p style="margin:0; font-size:12px; color:var(--text-muted);">Delegate tracking to a member.</p>
            <?php endif; ?>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <button onclick="openModal('modalAssignAC')" class="btn-accent"><?php echo $current_ac ? 'Change Person' : 'Assign Role'; ?></button>
            <?php if ($current_ac): ?>
                <form action="actions/remove_attendance.php" method="POST" onsubmit="return confirm('Remove this member as Attendance Coordinator?');" style="width: 100%;">
                    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $current_ac['id']; ?>">
                    <button type="submit" class="btn-outline-danger">Remove Current Coordinator</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="section-title" style="margin-top: 25px;"><i class="fa-solid fa-calendar-days"></i> Event Management</h2>
    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <button onclick="openModal('modalNewMeeting')" class="btn-accent" style="flex: 1;"><i class="fa-solid fa-calendar-plus"></i> Schedule Event</button>
        <a href="actions/export_events.php?group_id=<?php echo $group_id; ?>" class="btn-primary" style="flex: 1; background:#f1f5f9; color:#475569; border: 1px solid #cbd5e1;"><i class="fa-solid fa-download"></i> Export CSV</a>
    </div>

    <button id="btnToggleCompleted" class="btn-toggle-completed" onclick="toggleCompletedMeetings()">
        <i class="fa-solid fa-eye"></i> Show Completed Events
    </button>

    <div class="calendar-list">
        <h3 style="font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin: 10px 0 5px 0;">Standard Meetings</h3>
        <?php if (empty($regular_meetings)): ?>
            <p style="text-align:center; color:var(--text-muted); font-size: 13px; padding: 15px; border: 1px dashed #cbd5e1; border-radius: 8px;">No meetings scheduled.</p>
        <?php else: ?>
            <?php foreach ($regular_meetings as $mtg): 
                $badge_class = ($mtg['meeting_type'] == 'Event') ? 'badge-event' : 'badge-meeting';
                $is_completed = ($mtg['status'] == 'Completed');
                $card_class = $is_completed ? 'meeting-card completed-event' : 'meeting-card';
                
                $speakers = !empty($mtg['feature_speakers']) ? json_decode($mtg['feature_speakers'], true) : [];
                $speaker_names = [];
                if (is_array($speakers)) {
                    foreach ($speakers as $sid) {
                        if (isset($member_map[$sid])) $speaker_names[] = $member_map[$sid];
                    }
                }
            ?>
                <div class="<?php echo $card_class; ?>" <?php if($is_completed) echo 'style="display:none;"'; ?>>
                    <div class="meeting-header">
                        <div class="meeting-info">
                            <h3><?php echo date('M j, Y', strtotime($mtg['meeting_date'])); ?> <span class="badge-type <?php echo $badge_class; ?>"><?php echo htmlspecialchars($mtg['meeting_type']); ?></span></h3>
                            <p style="margin: 0 0 5px 0; font-size: 12px; color: var(--text-muted);">Status: <span style="color: <?php echo $is_completed ? '#10b981' : '#f59e0b'; ?>; font-weight:600;"><?php echo $mtg['status']; ?></span></p>
                            <?php if (!empty($speaker_names)): ?>
                                <p style="margin: 0; font-size: 11px; color: var(--brand-blue); background: #eff6ff; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                    <i class="fa-solid fa-microphone"></i> <?php echo htmlspecialchars(implode(', ', $speaker_names)); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="meeting-actions">
                        <a href="attendance_manager.php?meeting_id=<?php echo $mtg['id']; ?>" class="btn-manage">Manage</a>
                        <button onclick="openModal('modalEdit_<?php echo $mtg['id']; ?>')" class="btn-edit">Edit</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin: 15px 0 5px 0;">SOM Trainings</h3>
        <?php if (empty($soms)): ?>
            <p style="text-align:center; color:var(--text-muted); font-size: 13px; padding: 15px; border: 1px dashed #cbd5e1; border-radius: 8px;">No SOMs scheduled.</p>
        <?php else: ?>
            <?php foreach ($soms as $som): 
                $is_completed = ($som['status'] == 'Completed');
                $card_class = $is_completed ? 'meeting-card completed-event' : 'meeting-card';
            ?>
                <div class="<?php echo $card_class; ?>" style="border-left: 3px solid #ef4444; <?php if($is_completed) echo 'display:none;'; ?>">
                    <div class="meeting-header">
                        <div class="meeting-info">
                            <h3><?php echo date('M j, Y', strtotime($som['meeting_date'])); ?> <span class="badge-type badge-som">SOM</span></h3>
                            <p style="margin: 0; font-size: 12px; color: var(--text-muted);">Status: <span style="color: <?php echo $is_completed ? '#10b981' : '#f59e0b'; ?>; font-weight:600;"><?php echo $som['status']; ?></span></p>
                        </div>
                    </div>
                    <div class="meeting-actions">
                        <a href="attendance_manager.php?meeting_id=<?php echo $som['id']; ?>" class="btn-manage">Manage</a>
                        <button onclick="openModal('modalEdit_<?php echo $som['id']; ?>')" class="btn-edit">Edit</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <h2 class="section-title"><i class="fa-solid fa-bell"></i> Member Alerts</h2>
    <div class="celeb-list-container">
        
        <div class="celeb-col">
            <div class="celeb-header" style="color:#ca8a04;"><i class="fa-solid fa-cake-candles"></i> Birthdays</div>
            <div class="celeb-list">
                <?php if (empty($birthdays)): ?>
                    <p style="font-size:12px; color:#94a3b8; text-align:center; padding: 10px 0;">No birthdays listed.</p>
                <?php else: ?>
                    <?php foreach($birthdays as $b): ?>
                        <div class="celeb-item"><span><?php echo htmlspecialchars($b['first_name'] . ' ' . $b['last_name']); ?></span><span class="celeb-date"><?php echo $b['display_date']; ?></span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="celeb-col">
            <div class="celeb-header" style="color:#9333ea;"><i class="fa-solid fa-champagne-glasses"></i> Anniversaries</div>
            <div class="celeb-list">
                <?php if (empty($anniversaries)): ?>
                    <p style="font-size:12px; color:#94a3b8; text-align:center; padding: 10px 0;">No anniversaries listed.</p>
                <?php else: ?>
                    <?php foreach($anniversaries as $a): ?>
                        <div class="celeb-item"><span><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></span><span class="celeb-date"><?php echo $a['display_date']; ?></span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="celeb-col">
            <div class="celeb-header" style="color:#ef4444;"><i class="fa-solid fa-file-signature"></i> Urgent Renewals</div>
            <div class="celeb-list">
                <?php if (empty($renewals_list)): ?>
                    <p style="font-size:12px; color:#94a3b8; text-align:center; padding: 10px 0;">No urgent renewals.</p>
                <?php else: ?>
                    <?php foreach($renewals_list as $r): 
                        $renew_date = strtotime($r['renewal_date']);
                        $today = strtotime('today');
                        $diff_days = ($renew_date - $today) / (60 * 60 * 24);
                        
                        if ($r['payment_status'] === 'Pending') {
                            $badge = '<span style="font-size:10px; background:#e0e7ff; color:#1d4ed8; padding:2px 6px; border-radius:4px;">Pending</span>';
                        } elseif ($diff_days < 0) {
                            $badge = '<span style="font-size:10px; background:#fef2f2; color:#dc2626; padding:2px 6px; border-radius:4px;">Overdue</span>';
                        } elseif ($diff_days <= 15) {
                            $badge = '<span style="font-size:10px; background:#fffbeb; color:#d97706; padding:2px 6px; border-radius:4px;">Due ' . ceil($diff_days) . 'd</span>';
                        } else {
                            $badge = '<span style="font-size:10px; background:#ecfdf5; color:#059669; padding:2px 6px; border-radius:4px;">Upcoming</span>';
                        }
                    ?>
                        <div class="celeb-item" style="flex-direction:column; gap:6px; align-items:flex-start;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span style="font-weight:600; color:var(--text-main);"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></span>
                                <span class="celeb-date" style="color:var(--brand-blue);"><?php echo date('M d, Y', $renew_date); ?></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                                <?php echo $badge; ?>
                                <?php if($r['payment_status'] !== 'Pending'): ?>
                                    <button type="button" onclick="openModal('modalRenewal'); document.getElementById('renewUserSelect').value='<?php echo $r['user_id']; ?>';" style="background:transparent; border:none; color:#3b82f6; font-size:12px; font-weight:bold; cursor:pointer; padding:0; text-decoration:underline;">Pay Now</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalNewMeeting">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalNewMeeting')"></i>
        <h3 style="margin-top:0; color: var(--brand-blue); border-bottom: 1px solid var(--border); padding-bottom: 15px; font-size: 18px;"><i class="fa-solid fa-calendar-plus" style="color:var(--brand-orange);"></i> Schedule Event</h3>
        <form action="actions/create_meeting.php" method="POST">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            <div class="form-group">
                <label>Event Type</label>
                <select name="meeting_type" class="form-input" required>
                    <option value="Meeting">Standard Weekly Meeting</option>
                    <option value="SOM">SOM (Support Our Member)</option>
                    <option value="Event">Special Event / Social</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Assign Feature Speakers (Optional)</label>
                <select name="feature_speakers[]" class="multi-select" multiple placeholder="Search members...">
                    <?php foreach ($chapter_members as $m): ?>
                        <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group"><label>Date</label><input type="date" name="meeting_date" class="form-input" required></div>
            <div class="form-group"><label>Venue / Location</label><input type="text" name="venue" class="form-input" value="<?php echo htmlspecialchars($default_venue); ?>" required></div>
            <button type="submit" class="btn-primary" style="margin-top: 10px;">Schedule Now</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalRenewal">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalRenewal')"></i>
        <h3 style="margin-top:0; color: var(--brand-blue); border-bottom: 1px solid var(--border); padding-bottom: 15px; font-size: 18px;"><i class="fa-solid fa-file-invoice-dollar" style="color:#10b981;"></i> Process Payment</h3>
        <form action="actions/submit_renewal.php" method="POST">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            <div class="form-group">
                <label>Select Member</label>
                <select name="user_id" id="renewUserSelect" class="form-input" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($chapter_members as $m): ?>
                        <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Mode</label>
                <select name="payment_mode" class="form-input" required>
                    <option value="Cash">Cash to Head Table</option>
                    <option value="Account Transfer">Bank Transfer / UPI</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reference / Receipt No (Optional)</label>
                <input type="text" name="reference_no" class="form-input" placeholder="e.g., UTR Number">
            </div>
            <button type="submit" class="btn-primary" style="background:#10b981; margin-top: 10px;">Send to Admin</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalAssignAC">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalAssignAC')"></i>
        <h3 style="margin-top:0; color: var(--brand-blue); border-bottom: 1px solid var(--border); padding-bottom: 15px; font-size: 18px;"><i class="fa-solid fa-user-check" style="color:var(--brand-orange);"></i> Assign AC</h3>
        <form action="actions/assign_attendance.php" method="POST">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            <div class="form-group">
                <label>Select Member</label>
                <select name="user_id" class="form-input" required>
                    <option value="">-- Choose Member --</option>
                    <?php foreach ($chapter_members as $m): ?>
                        <option value="<?php echo $m['user_id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-accent" style="margin-top: 10px;">Assign Coordinator</button>
        </form>
    </div>
</div>

<?php foreach ($all_meetings as $m): 
    $speakers = !empty($m['feature_speakers']) ? json_decode($m['feature_speakers'], true) : [];
?>
<div class="modal-overlay" id="modalEdit_<?php echo $m['id']; ?>">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalEdit_<?php echo $m['id']; ?>')"></i>
        <h3 style="margin-top:0; color: var(--brand-blue); border-bottom: 1px solid var(--border); padding-bottom: 15px; font-size: 18px;"><i class="fa-solid fa-pen-to-square" style="color:var(--text-muted);"></i> Edit Event</h3>
        
        <?php if ($m['status'] !== 'Completed'): ?>
        <form action="actions/update_meeting.php" method="POST" onsubmit="return confirm('WARNING: Mark this event as Completed? This will permanently lock attendance.');" style="margin-bottom: 15px;">
            <input type="hidden" name="action_type" value="complete">
            <input type="hidden" name="meeting_id" value="<?php echo $m['id']; ?>">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            <button type="submit" class="btn-primary" style="background:#10b981; box-shadow: 0 4px 10px rgba(16,185,129,0.2);"><i class="fa-solid fa-check-double"></i> Mark Event as Completed</button>
        </form>
        <?php endif; ?>
        
        <form action="actions/update_meeting.php" method="POST">
            <input type="hidden" name="action_type" value="edit">
            <input type="hidden" name="meeting_id" value="<?php echo $m['id']; ?>">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            
            <div class="form-group">
                <label>Event Type</label>
                <select name="meeting_type" class="form-input" required <?php if($m['status'] == 'Completed') echo 'disabled'; ?>>
                    <option value="Meeting" <?php if($m['meeting_type'] == 'Meeting') echo 'selected'; ?>>Standard Weekly Meeting</option>
                    <option value="SOM" <?php if($m['meeting_type'] == 'SOM') echo 'selected'; ?>>SOM (Support Our Member)</option>
                    <option value="Event" <?php if($m['meeting_type'] == 'Event') echo 'selected'; ?>>Special Event / Social</option>
                </select>
            </div>

            <div class="form-group">
                <label>Assign Feature Speakers (Optional)</label>
                <select name="feature_speakers[]" class="multi-select" multiple placeholder="Search members..." <?php if($m['status'] == 'Completed') echo 'disabled'; ?>>
                    <?php foreach ($chapter_members as $cm): ?>
                        <option value="<?php echo $cm['user_id']; ?>" <?php if(in_array($cm['user_id'], $speakers)) echo 'selected'; ?>><?php echo htmlspecialchars($cm['first_name'] . ' ' . $cm['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group"><label>Date</label><input type="date" name="meeting_date" class="form-input" value="<?php echo $m['meeting_date']; ?>" required <?php if($m['status'] == 'Completed') echo 'readonly'; ?>></div>
            <div class="form-group"><label>Venue / Location</label><input type="text" name="venue" class="form-input" value="<?php echo htmlspecialchars($m['venue']); ?>" required <?php if($m['status'] == 'Completed') echo 'readonly'; ?>></div>
            
            <?php if ($m['status'] !== 'Completed'): ?>
                <button type="submit" class="btn-primary" style="margin-top: 10px; margin-bottom: 15px;">Save Changes</button>
            <?php endif; ?>
        </form>
        
        <?php if ($m['status'] !== 'Completed'): ?>
        <form action="actions/update_meeting.php" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to completely delete this event? All attendance attached to it will be lost.');">
            <input type="hidden" name="action_type" value="delete">
            <input type="hidden" name="meeting_id" value="<?php echo $m['id']; ?>">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            <button type="submit" class="btn-outline-danger"><i class="fa-solid fa-trash"></i> Delete Event Forever</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.multi-select').forEach((el) => {
            new TomSelect(el, {
                plugins: ['remove_button'],
                maxItems: 3,
                placeholder: "Search to assign speaker..."
            });
        });
    });

    function openModal(modalId) { 
        document.getElementById(modalId).style.display = 'flex'; 
    }
    
    function closeModal(modalId) { 
        document.getElementById(modalId).style.display = 'none'; 
    }

    function toggleCompletedMeetings() {
        const events = document.querySelectorAll('.completed-event');
        const btn = document.getElementById('btnToggleCompleted');
        let isHidden = true;

        events.forEach(el => {
            if (el.style.display === 'none' || el.style.display === '') {
                el.style.display = 'flex';
                isHidden = false;
            } else {
                el.style.display = 'none';
                isHidden = true;
            }
        });

        if (isHidden) {
            btn.innerHTML = '<i class="fa-solid fa-eye"></i> Show Completed Events';
            btn.style.background = '#f1f5f9';
            btn.style.color = '#475569';
        } else {
            btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide Completed Events';
            btn.style.background = '#e2e8f0';
            btn.style.color = '#0f172a';
        }
    }
</script>

</body>
</html>