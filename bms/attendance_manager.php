<?php
// attendance_manager.php
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

try {
    // Verify Access for Coordinator or Attendance Role
    $stmtVerify = $pdo->prepare("
        SELECT gm.group_id, g.default_venue, gm.leadership_role 
        FROM group_members gm
        JOIN groups g ON gm.group_id = g.id 
        WHERE gm.user_id = ? AND gm.leadership_role IN ('Coordinator', 'Attendance') AND gm.membership_status = 'Active'
    ");
    $stmtVerify->execute([$user_id]);
    $coord = $stmtVerify->fetch();

    if (!$coord) { die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Access Denied</h2><p>Restricted to Authorized Personnel.</p></div>"); }
    
    $group_id = $coord['group_id'];
    $user_role = $coord['leadership_role']; 
    $default_venue = $coord['default_venue'] ?? 'TBD';
    
    $back_link = ($user_role === 'Coordinator') ? 'head_table.php' : 'index.php';

    $active_meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : null;
    $members = [];
    $existing_attendance = [];
    $member_history = [];
    $meeting_visitors = [];
    $is_already_logged = false;
    $is_locked = false;
    $is_future_meeting = false;
    $is_read_only = false;
    $current_mtg_date = '';

    if ($active_meeting_id) {
        $stmtMtgDate = $pdo->prepare("SELECT meeting_date FROM chapter_meetings WHERE id = ? AND group_id = ? AND meeting_type <> 'Daily Status'");
        $stmtMtgDate->execute([$active_meeting_id, $group_id]);
        $current_mtg_date = $stmtMtgDate->fetchColumn();
        if (!$current_mtg_date) {
            die("Meeting not found for this chapter.");
        }
        $is_future_meeting = $current_mtg_date > date('Y-m-d');

        $stmtLock = $pdo->prepare("SELECT COUNT(*) FROM chapter_meetings WHERE group_id = ? AND meeting_type <> 'Daily Status' AND meeting_date > ? AND status = 'Completed'");
        $stmtLock->execute([$group_id, $current_mtg_date]);
        if ($stmtLock->fetchColumn() > 0) {
            $is_locked = true;
        }
        $is_read_only = $is_locked || $is_future_meeting;

        $stmtMembers = $pdo->prepare("
            SELECT u.id as user_id, u.first_name, u.last_name, b.company_name 
            FROM group_members gm 
            JOIN users u ON gm.user_id = u.id 
            LEFT JOIN businesses b ON u.id = b.user_id 
            WHERE gm.group_id = ? AND gm.membership_status = 'Active'
            ORDER BY u.first_name ASC
        ");
        $stmtMembers->execute([$group_id]);
        $members = $stmtMembers->fetchAll();

        $stmtAtt = $pdo->prepare("SELECT * FROM attendance WHERE meeting_id = ?");
        $stmtAtt->execute([$active_meeting_id]);
        while ($row = $stmtAtt->fetch()) {
            $existing_attendance[$row['user_id']] = $row;
            $is_already_logged = true; 
        }

        $stmtHistory = $pdo->prepare("
            SELECT user_id, SUM(presentation_8_min) as total_pres, SUM(mtp) as total_mtp 
            FROM attendance a 
            JOIN chapter_meetings cm ON a.meeting_id = cm.id 
            WHERE cm.group_id = ? AND cm.meeting_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AND cm.id != ?
            GROUP BY user_id
        ");
        $stmtHistory->execute([$group_id, $active_meeting_id]);
        while ($h = $stmtHistory->fetch()) {
            $member_history[$h['user_id']] = $h;
        }

        // NEW: Fetch visitors scheduled for this date
        $stmtVis = $pdo->prepare("
            SELECT v.id, v.visitor_name, v.company_name, v.attended, u.first_name as inviter_first, u.last_name as inviter_last
            FROM visitors v
            LEFT JOIN users u ON v.invited_by = u.id
            WHERE v.visit_date = ? AND v.chapter_id = ?
        ");
        $stmtVis->execute([$current_mtg_date, $group_id]);
        $meeting_visitors = $stmtVis->fetchAll();

    } else {
        $stmtMeetings = $pdo->prepare("SELECT * FROM chapter_meetings WHERE group_id = ? AND meeting_type <> 'Daily Status' ORDER BY meeting_date DESC LIMIT 15");
        $stmtMeetings->execute([$group_id]);
        $meetings = $stmtMeetings->fetchAll();
    }
} catch (PDOException $e) {
    die("System Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Meeting Manager | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --brand-blue: #00204a; --brand-orange: #ff6b00; --bg-color: #f8fafc; --card-bg: #ffffff; --border: #e2e8f0; --text-main: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-color); color: var(--text-main); margin: 0; padding-bottom: 80px; overflow-x: hidden;}
        
        .portal-container { width: 100%; max-width: 600px; margin: 0 auto; padding: 15px; box-sizing: border-box; }
        
        .header-box { background: var(--brand-blue); padding: 25px 20px; border-radius: 16px; margin-bottom: 20px; color: white; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8px 20px rgba(0,32,74,0.15); position: relative; overflow: hidden;}
        .header-box::after { content: ''; position: absolute; right: -20px; bottom: -20px; width: 100px; height: 100px; background: var(--brand-orange); border-radius: 50%; filter: blur(40px); opacity: 0.3; z-index: 1;}
        .header-content { position: relative; z-index: 2;}
        .header-box h1 { margin: 0; font-size: 18px; font-weight: 800;}
        
        .alert-banner { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; display: flex; gap: 12px; align-items: flex-start; line-height: 1.4; border: 1px solid;}
        .alert-warning { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
        .alert-locked { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

        .member-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 15px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .member-header { margin-bottom: 12px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 10px;}
        .member-details h4 { margin: 0 0 3px 0; font-size: 15px; color: var(--brand-blue); }
        .member-details p { margin: 0; font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;}
        
        .attendance-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px;}
        .status-radio { display: none; }
        .status-label { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; color: var(--text-muted); text-align: center; background: #f8fafc; display: flex; align-items: center; justify-content: center; gap: 5px;}
        
        .status-radio:checked + .status-label.present { background: #ecfdf5; border-color: #10b981; color: #059669; }
        .status-radio:checked + .status-label.absent { background: #fef2f2; border-color: #ef4444; color: #dc2626; }
        .status-radio:checked + .status-label.late { background: #fffbeb; border-color: #f59e0b; color: #d97706; }
        .status-radio:checked + .status-label.substitute { background: #eff6ff; border-color: #3b82f6; color: #2563eb; }
        
        .sub-input { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; display: none; margin-bottom: 10px; width: 100%; box-sizing: border-box; background: #f8fafc;}

        .points-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; display: block;}
        .points-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px dashed #cbd5e1; }
        .point-checkbox { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; color: var(--text-main); cursor: pointer; user-select: none;}
        .point-checkbox input { margin: 0; width: 16px; height: 16px; accent-color: var(--brand-orange);}

        .btn-save { background: #10b981; color: white; border: none; padding: 15px; border-radius: 12px; font-weight: 700; cursor: pointer; width: 100%; margin-top: 20px; font-size: 15px; box-shadow: 0 4px 15px rgba(16,185,129,0.3); display: flex; align-items: center; justify-content: center; gap: 8px;}
        
        .locked-mode .status-label { opacity: 0.6; cursor: not-allowed; }
        .locked-mode .point-checkbox { opacity: 0.6; cursor: not-allowed; }
        .locked-mode .sub-input { background: #e2e8f0; color: #94a3b8; pointer-events: none; }

        .list-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 15px; margin-bottom: 12px; display: flex; flex-direction: column; gap: 12px;}
        .list-header { display: flex; justify-content: space-between; align-items: flex-start;}
        .list-info h3 { margin: 0 0 4px 0; font-size: 14px; color: var(--brand-blue); display: flex; align-items: center; gap: 8px;}
        .badge-type { font-size: 9px; padding: 3px 6px; border-radius: 6px; font-weight: 700; border: 1px solid;}
        .btn-enter { background: var(--brand-blue); color: white; border: none; padding: 10px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; text-align: center; display: block;}
        .btn-locked { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; text-align: center; display: block;}
    </style>
</head>
<body>

<div class="portal-container">
    <a href="<?php echo $back_link; ?>" style="color: var(--text-muted); text-decoration: none; margin-bottom: 15px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Portal</a>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert-banner" style="background: #fef2f2; color: #991b1b; border-color: #fecaca;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px;"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert-banner" style="background: #ecfdf5; color: #065f46; border-color: #a7f3d0;">
            <i class="fa-solid fa-check-circle" style="font-size: 18px;"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>

    <?php if ($active_meeting_id): ?>
        <div class="header-box">
            <div class="header-content">
                <p><?php echo date('F j, Y', strtotime($current_mtg_date)); ?></p>
                <h1>Attendance Manager</h1>
            </div>
            <i class="fa-solid fa-clipboard-user" style="font-size: 35px; color: rgba(255,255,255,0.2); position: relative; z-index: 2;"></i>
        </div>

        <?php if ($is_locked): ?>
            <div class="alert-banner alert-locked">
                <i class="fa-solid fa-lock" style="font-size: 20px; margin-top: 2px;"></i>
                <div>
                    <strong style="display:block; margin-bottom:4px; font-size:14px;">Record Locked</strong>
                    A newer meeting has been completed. This past attendance record is permanently locked and cannot be altered.
                </div>
            </div>
        <?php elseif ($is_future_meeting): ?>
            <div class="alert-banner alert-warning">
                <i class="fa-regular fa-clock" style="font-size: 20px; margin-top: 2px;"></i>
                <div>
                    <strong style="display:block; margin-bottom:4px; font-size:14px;">Attendance Not Open Yet</strong>
                    Attendance opens on the scheduled day, <?php echo date('M j, Y', strtotime($current_mtg_date)); ?>.
                </div>
            </div>
        <?php elseif ($is_already_logged): ?>
            <div class="alert-banner alert-warning">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 20px; margin-top: 2px;"></i>
                <div>
                    <strong style="display:block; margin-bottom:4px; font-size:14px;">Warning: Overwriting Data</strong>
                    Attendance has already been taken. Any changes you submit now will overwrite the existing records.
                </div>
            </div>
        <?php endif; ?>

        <form action="actions/save_attendance.php" method="POST" class="<?php if($is_read_only) echo 'locked-mode'; ?>">
            <input type="hidden" name="meeting_id" value="<?php echo $active_meeting_id; ?>">
            
            <?php foreach ($members as $m): 
                $uid = $m['user_id'];
                $att = $existing_attendance[$uid] ?? [];
                $status = $att['attendance_status'] ?? 'Present'; 
                $sub_name = $att['substitute_name'] ?? '';
                
                $past_pres = $member_history[$uid]['total_pres'] ?? 0;
                $past_mtp = $member_history[$uid]['total_mtp'] ?? 0;
            ?>
                <div class="member-card">
                    <div class="member-header">
                        <div class="member-details">
                            <h4><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></h4>
                            <p><?php echo htmlspecialchars($m['company_name']); ?></p>
                        </div>
                    </div>
                    
                    <div class="attendance-actions">
                        <input type="radio" name="attendance[<?php echo $uid; ?>]" id="p_<?php echo $uid; ?>" value="Present" class="status-radio" <?php if($status=='Present') echo 'checked'; ?> onclick="toggleSub(<?php echo $uid; ?>, false)" <?php if($is_read_only) echo 'disabled'; ?>>
                        <label for="p_<?php echo $uid; ?>" class="status-label present"><i class="fa-solid fa-check"></i> Present</label>
                        
                        <input type="radio" name="attendance[<?php echo $uid; ?>]" id="a_<?php echo $uid; ?>" value="Absent" class="status-radio" <?php if($status=='Absent') echo 'checked'; ?> onclick="toggleSub(<?php echo $uid; ?>, false)" <?php if($is_read_only) echo 'disabled'; ?>>
                        <label for="a_<?php echo $uid; ?>" class="status-label absent"><i class="fa-solid fa-xmark"></i> Absent</label>
                        
                        <input type="radio" name="attendance[<?php echo $uid; ?>]" id="l_<?php echo $uid; ?>" value="Late" class="status-radio" <?php if($status=='Late') echo 'checked'; ?> onclick="toggleSub(<?php echo $uid; ?>, false)" <?php if($is_read_only) echo 'disabled'; ?>>
                        <label for="l_<?php echo $uid; ?>" class="status-label late"><i class="fa-regular fa-clock"></i> Late</label>
                        
                        <input type="radio" name="attendance[<?php echo $uid; ?>]" id="s_<?php echo $uid; ?>" value="Substitute" class="status-radio" <?php if($status=='Substitute') echo 'checked'; ?> onclick="toggleSub(<?php echo $uid; ?>, true)" <?php if($is_read_only) echo 'disabled'; ?>>
                        <label for="s_<?php echo $uid; ?>" class="status-label substitute"><i class="fa-solid fa-user-astronaut"></i> Sub</label>
                    </div>

                    <input type="text" name="substitute_name[<?php echo $uid; ?>]" id="sub_input_<?php echo $uid; ?>" class="sub-input" placeholder="Substitute's Full Name" value="<?php echo htmlspecialchars($sub_name); ?>" style="<?php echo ($status=='Substitute') ? 'display:block;' : ''; ?>" <?php if($is_read_only) echo 'readonly'; ?>>

                    <span class="points-label">Assign Performance Points</span>
                    <div class="points-grid">
                        <label class="point-checkbox"><input type="checkbox" name="awards[<?php echo $uid; ?>][early_bird]" value="1" <?php if(!empty($att['early_bird'])) echo 'checked'; ?> <?php if($is_read_only) echo 'disabled'; ?>> Early Bird</label>
                        <label class="point-checkbox"><input type="checkbox" name="awards[<?php echo $uid; ?>][best_30_sec]" value="1" <?php if(!empty($att['best_30_sec'])) echo 'checked'; ?> <?php if($is_read_only) echo 'disabled'; ?>> Best 30 Sec</label>
                        <label class="point-checkbox"><input type="checkbox" name="awards[<?php echo $uid; ?>][som]" value="1" <?php if(!empty($att['som'])) echo 'checked'; ?> <?php if($is_read_only) echo 'disabled'; ?>> SOM</label>
                        
                        <?php if ($past_pres == 0 || !empty($att['presentation_8_min'])): ?>
                            <label class="point-checkbox"><input type="checkbox" name="awards[<?php echo $uid; ?>][presentation_8_min]" value="1" <?php if(!empty($att['presentation_8_min'])) echo 'checked'; ?> <?php if($is_read_only) echo 'disabled'; ?>> 8 Min Pres.</label>
                        <?php endif; ?>
                        
                        <?php if ($past_mtp == 0 || !empty($att['mtp'])): ?>
                            <label class="point-checkbox"><input type="checkbox" name="awards[<?php echo $uid; ?>][mtp]" value="1" <?php if(!empty($att['mtp'])) echo 'checked'; ?> <?php if($is_read_only) echo 'disabled'; ?>> MTP</label>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$is_read_only): ?>
                <?php if ($user_role === 'Coordinator'): ?>
                    <button type="submit" class="btn-save"><i class="fa-solid fa-cloud-arrow-up"></i> Verify & Submit Roster</button>
                <?php else: ?>
                    <button type="submit" class="btn-save" style="background:var(--brand-orange); box-shadow: 0 4px 10px rgba(255,107,0,0.3);"><i class="fa-solid fa-cloud-arrow-up"></i> Submit Attendance</button>
                <?php endif; ?>
            <?php endif; ?>
        </form>

        <!-- NEW VISITOR ROLL CALL SECTION -->
        <?php if (!empty($meeting_visitors)): ?>
            <h2 style="font-size: 15px; font-weight: 800; color: var(--brand-blue); margin: 40px 0 15px 0; border-bottom: 2px solid var(--border); padding-bottom: 8px;"><i class="fa-solid fa-users-viewfinder"></i> Visitor Roll Call</h2>
            <form action="actions/save_visitor_attendance.php" method="POST" class="<?php if($is_read_only) echo 'locked-mode'; ?>" style="margin-bottom: 30px;">
                <input type="hidden" name="meeting_id" value="<?php echo $active_meeting_id; ?>">
                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                
                <?php foreach ($meeting_visitors as $v): ?>
                    <div class="member-card" style="display: flex; justify-content: space-between; align-items: center; border-left: 4px solid <?php echo $v['attended'] ? '#10b981' : '#cbd5e1'; ?>;">
                        <div>
                            <h4 style="margin: 0 0 3px 0; font-size: 15px; color: var(--brand-blue);"><?php echo htmlspecialchars($v['visitor_name']); ?></h4>
                            <p style="margin: 0; font-size: 11px; color: var(--text-muted);"><i class="fa-solid fa-user-tag"></i> Invited by: <?php echo htmlspecialchars(trim(($v['inviter_first'] ?? '') . ' ' . ($v['inviter_last'] ?? '')) ?: 'Public Website'); ?></p>
                        </div>
                        <label class="point-checkbox" style="background: <?php echo $v['attended'] ? '#ecfdf5' : '#f8fafc'; ?>; padding: 8px 12px; border-radius: 8px; border: 1px solid <?php echo $v['attended'] ? '#10b981' : '#cbd5e1'; ?>;">
                            <input type="checkbox" name="attended_visitors[]" value="<?php echo $v['id']; ?>" <?php if($v['attended']) echo 'checked'; ?> <?php if($is_read_only) echo 'disabled'; ?>>
                            Showed Up
                        </label>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$is_read_only): ?>
                    <button type="submit" class="btn-save" style="background:var(--brand-blue); box-shadow: 0 4px 10px rgba(0,32,74,0.3);"><i class="fa-solid fa-floppy-disk"></i> Save Visitor Roll Call</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <script>
            function toggleSub(userId, show) {
                const input = document.getElementById('sub_input_' + userId);
                input.style.display = show ? 'block' : 'none';
                if (!show) input.value = ''; 
            }
        </script>

    <?php else: ?>
        <div class="header-box">
            <div class="header-content">
                <p>Select Event</p>
                <h1>Meeting Rosters</h1>
            </div>
            <i class="fa-solid fa-calendar-days" style="font-size: 35px; color: rgba(255,255,255,0.2); position: relative; z-index: 2;"></i>
        </div>

        <?php if (empty($meetings)): ?>
            <p style="text-align:center; color:var(--text-muted); font-size:13px; margin-top: 40px;">No meetings scheduled yet.</p>
        <?php else: ?>
            <?php foreach ($meetings as $mtg): 
                $today = date('Y-m-d');
                $mtg_is_future = $mtg['meeting_date'] > $today;
                
                $display_status = $mtg['status'];
                $status_color = '#f59e0b';
                
                $stmtMtgLock = $pdo->prepare("SELECT COUNT(*) FROM chapter_meetings WHERE group_id = ? AND meeting_type <> 'Daily Status' AND meeting_date > ? AND status = 'Completed'");
                $stmtMtgLock->execute([$group_id, $mtg['meeting_date']]);
                $is_mtg_locked = ($stmtMtgLock->fetchColumn() > 0);

                if ($mtg['status'] === 'Completed') {
                    $display_status = 'Completed';
                    $status_color = '#10b981';
                } elseif ($mtg['status'] === 'Scheduled' && $mtg['meeting_date'] < $today) {
                    $display_status = 'Pending Action';
                    $status_color = '#ef4444'; 
                }

                $badge_class = 'badge-meeting';
                $badge_color = '#bfdbfe';
                $badge_bg = '#eff6ff';
                if ($mtg['meeting_type'] == 'Event') { $badge_class = 'badge-event'; $badge_color = '#e879f9'; $badge_bg = '#fdf4ff'; }
                if ($mtg['meeting_type'] == 'SOM') { $badge_class = 'badge-som'; $badge_color = '#fecaca'; $badge_bg = '#fef2f2'; }
            ?>
                <div class="list-card" style="border-left: 4px solid <?php echo $status_color; ?>;">
                    <div class="list-header">
                        <div class="list-info">
                            <h3><?php echo date('D, M j, Y', strtotime($mtg['meeting_date'])); ?> </h3>
                            <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
                                <span class="badge-type" style="border-color:<?php echo $badge_color; ?>; background:<?php echo $badge_bg; ?>; color:var(--text-main);"><?php echo htmlspecialchars($mtg['meeting_type']); ?></span>
                                <span style="font-size:11px; color: <?php echo $status_color; ?>; font-weight:700;"><?php echo $display_status; ?></span>
                            </div>
                        </div>
                        <?php if ($is_mtg_locked): ?>
                            <i class="fa-solid fa-lock" style="color: #cbd5e1; font-size: 14px; margin-top:4px;"></i>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($is_mtg_locked): ?>
                        <a href="attendance_manager.php?meeting_id=<?php echo $mtg['id']; ?>" class="btn-locked"><i class="fa-solid fa-eye"></i> View Read-Only</a>
                    <?php elseif ($mtg_is_future): ?>
                        <div class="btn-locked" aria-disabled="true"><i class="fa-regular fa-clock"></i> Opens on <?php echo date('M j, Y', strtotime($mtg['meeting_date'])); ?></div>
                    <?php else: ?>
                        <a href="attendance_manager.php?meeting_id=<?php echo $mtg['id']; ?>" class="btn-enter"><i class="fa-solid fa-pen-to-square"></i> Take Attendance</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>