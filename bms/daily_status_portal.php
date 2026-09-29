<?php
// daily_status_portal.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

try {
    // Verify they are the Attendance Coordinator or Head Table
    $stmtVerify = $pdo->prepare("SELECT gm.group_id, g.group_name FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ? AND gm.leadership_role IN ('Attendance', 'Coordinator') AND gm.membership_status = 'Active'");
    $stmtVerify->execute([$user_id]);
    $coord = $stmtVerify->fetch();

    if (!$coord) { die("<div style='text-align:center; padding:50px;'><h2>Access Denied</h2><p>You are not assigned to Head Table or Attendance duties.</p></div>"); }
    
    $group_id = $coord['group_id'];
    $group_name = $coord['group_name'];

    // Fetch members
    $stmtMembers = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, b.company_name 
        FROM group_members gm 
        JOIN users u ON gm.user_id = u.id 
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE gm.group_id = ? AND gm.membership_status = 'Active' 
        ORDER BY u.first_name ASC
    ");
    $stmtMembers->execute([$group_id]);
    $members = $stmtMembers->fetchAll();

} catch (PDOException $e) { die("System Error."); }

$selected_date = $_GET['date'] ?? date('Y-m-d');
$bulk_start_date = $_GET['bulk_start'] ?? date('Y-m-d', strtotime('-14 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Status Points Portal | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --brand-blue: #00204a; --brand-orange: #ff6b00; --bg-color: #f8fafc; --text-main: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-color); color: var(--text-main); margin: 0; padding-bottom: 80px; overflow-x: hidden;}
        
        .portal-container { width: 100%; max-width: 600px; margin: 0 auto; padding: 15px; box-sizing: border-box; }
        
        .header-box { background: var(--brand-blue); padding: 25px 20px; border-radius: 16px; margin-bottom: 20px; color: white; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8px 20px rgba(0,32,74,0.15); position: relative; overflow: hidden;}
        .header-box::after { content: ''; position: absolute; right: -20px; bottom: -20px; width: 100px; height: 100px; background: #10b981; border-radius: 50%; filter: blur(40px); opacity: 0.3; z-index: 1;}
        .header-content { position: relative; z-index: 2;}
        .header-box h1 { margin: 0; font-size: 18px; font-weight: 800;}
        
        .alert-banner { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 13px; display: flex; gap: 12px; align-items: flex-start; line-height: 1.4; border: 1px solid;}
        
        /* Tabs */
        .tab-container { display: flex; background: white; border-radius: 12px; padding: 6px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid #e2e8f0;}
        .tab-btn { flex: 1; text-align: center; padding: 12px; font-weight: 700; font-size: 13px; color: var(--text-muted); text-decoration: none; border-radius: 8px; transition: 0.3s; cursor: pointer; border: none; background: transparent;}
        .tab-btn.active { background: #10b981; color: white; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .member-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .member-header { margin-bottom: 12px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 10px;}
        .member-details h4 { margin: 0 0 3px 0; font-size: 15px; color: var(--brand-blue); }
        .member-details p { margin: 0; font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;}

        .points-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px dashed #cbd5e1; }
        .point-checkbox { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-main); cursor: pointer; user-select: none;}
        .point-checkbox input { margin: 0; width: 18px; height: 18px; accent-color: #10b981;}

        .bulk-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px; }
        .bulk-day { background: #f8fafc; padding: 12px 5px; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; cursor: pointer; transition: 0.2s;}
        .bulk-day:hover { border-color: #10b981; }
        .bulk-date-label { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block;}

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--brand-blue); }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; background: white;}

        .btn-save { background: #10b981; color: white; border: none; padding: 15px; border-radius: 12px; font-weight: 700; cursor: pointer; width: 100%; margin-top: 20px; font-size: 15px; box-shadow: 0 4px 15px rgba(16,185,129,0.3); display: flex; align-items: center; justify-content: center; gap: 8px;}
    </style>
</head>
<body>

<div class="portal-container">
    <a href="attendance_dashboard.php" style="color: var(--text-muted); text-decoration: none; margin-bottom: 15px; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert-banner" style="background: #ecfdf5; color: #065f46; border-color: #a7f3d0;">
            <i class="fa-solid fa-check-circle" style="font-size: 18px;"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>

    <div class="header-box">
        <div class="header-content">
            <p><?php echo htmlspecialchars($group_name); ?></p>
            <h1>Status Points Portal</h1>
        </div>
        <i class="fa-solid fa-bolt" style="font-size: 35px; color: rgba(255,255,255,0.2); position: relative; z-index: 2;"></i>
    </div>

    <div class="tab-container">
        <button class="tab-btn active" onclick="switchTab('daily', this)">Daily Entry</button>
        <button class="tab-btn" onclick="switchTab('bulk', this)">15-Day Bulk Entry</button>
    </div>

    <!-- TAB 1: DAILY ENTRY -->
    <div id="tab-daily" class="tab-content active">
        <div class="alert-banner" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
            <i class="fa-solid fa-circle-info" style="font-size: 20px; margin-top: 2px;"></i>
            <div>
                <strong style="display:block; margin-bottom:4px; font-size:14px;">Single Day Points</strong>
                Select a specific date and tick off points for any member.
            </div>
        </div>

        <form action="actions/save_daily_points.php" method="POST">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            <input type="hidden" name="entry_type" value="daily">
            
            <div class="form-group" style="background: white; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <label>Select Date to Award Points</label>
                <input type="date" name="award_date" class="form-input" value="<?php echo $selected_date; ?>" required>
            </div>
            
            <?php foreach ($members as $m): $uid = $m['id']; ?>
                <div class="member-card">
                    <div class="member-header">
                        <div class="member-details">
                            <h4><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></h4>
                            <p><?php echo htmlspecialchars($m['company_name'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <div class="points-grid">
                        <label class="point-checkbox"><input type="checkbox" name="awards[<?php echo $uid; ?>][status_update]" value="1"> Daily Status</label>
                        <label class="point-checkbox"><input type="checkbox" name="awards[<?php echo $uid; ?>][early_bird]" value="1"> Early Bird</label>
                        <label class="point-checkbox"><input type="checkbox" name="awards[<?php echo $uid; ?>][best_30_sec]" value="1"> Best 30 Sec</label>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-save"><i class="fa-solid fa-bolt"></i> Award Points for Date</button>
        </form>
    </div>

    <!-- TAB 2: BULK 15-DAY ENTRY -->
    <div id="tab-bulk" class="tab-content">
        <div class="alert-banner" style="background: #fffbeb; color: #b45309; border-color: #fcd34d;">
            <i class="fa-solid fa-calendar-week" style="font-size: 20px; margin-top: 2px;"></i>
            <div>
                <strong style="display:block; margin-bottom:4px; font-size:14px;">15-Day Bulk Status</strong>
                Select ONE member, and instantly tick off their "Daily Status" points for the past 15 days.
            </div>
        </div>

        <form action="actions/save_daily_points.php" method="POST">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            <input type="hidden" name="entry_type" value="bulk">
            
            <div class="form-group" style="background: white; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                <label>Select Member</label>
                <select name="user_id" class="form-input" required>
                    <option value="">-- Choose Member --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <h3 style="font-size: 14px; color: var(--brand-blue); margin-bottom: 5px;">Tick boxes for days they posted status:</h3>
            
            <div class="bulk-grid">
                <?php 
                $start = new DateTime($bulk_start_date);
                for($i=0; $i<15; $i++): 
                    $curr_date = $start->format('Y-m-d');
                    $display_date = $start->format('M d (D)');
                ?>
                    <label class="bulk-day">
                        <span class="bulk-date-label"><?php echo $display_date; ?></span>
                        <input type="checkbox" name="bulk_dates[]" value="<?php echo $curr_date; ?>" style="width: 20px; height: 20px; accent-color: #10b981; cursor: pointer;">
                    </label>
                <?php 
                $start->modify('+1 day');
                endfor; 
                ?>
            </div>

            <button type="submit" class="btn-save" style="background: var(--brand-orange); box-shadow: 0 4px 15px rgba(255,107,0,0.3);"><i class="fa-solid fa-list-check"></i> Save 15-Day Bulk Entry</button>
        </form>
    </div>
</div>

<script>
    function switchTab(tabId, btn) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
        btn.classList.add('active');
    }
</script>

</body>
</html>