<?php
// support.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error loading support tickets.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpdesk & Support | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --white: #ffffff; }
        body { font-family: 'Inter', sans-serif; background: var(--dark-blue); margin: 0; color: var(--white); }
        .support-container { max-width: 600px; margin: 0 auto; padding: 20px; padding-bottom: 100px; }
        
        .page-title { color: var(--primary-orange); font-size: 22px; font-weight: 700; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 10px;}
        
        .btn-create { background: var(--primary-orange); color: white; border: none; padding: 12px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 8px; width: 100%; justify-content: center; font-size: 15px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(255,107,0,0.3);}
        
        .ticket-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; margin-bottom: 15px; transition: 0.2s;}
        .ticket-card:hover { background: rgba(255,255,255,0.06); }
        
        .t-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .t-subject { font-size: 16px; font-weight: 700; color: var(--white); margin: 0 0 5px 0; }
        .t-date { font-size: 11px; color: rgba(255,255,255,0.5); }
        .t-msg { font-size: 13px; color: rgba(255,255,255,0.7); line-height: 1.5; margin: 0 0 15px 0; }
        
        .status-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; }
        .status-Open { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-InProgress { background: rgba(245, 158, 11, 0.15); color: #fcd34d; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-Resolved { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; padding: 15px 0; display: flex; justify-content: space-around; border-radius: 20px 20px 0 0; background: rgba(0, 20, 45, 0.95); backdrop-filter: blur(16px); z-index: 100; border-top: 1px solid rgba(255,255,255,0.1); padding-bottom: max(15px, env(safe-area-inset-bottom));}
        .nav-item { color: rgba(255,255,255,0.6); text-decoration: none; text-align: center; font-size: 11px; font-weight: 500; transition: color 0.3s; display: flex; flex-direction: column; align-items: center;}
        .nav-item.active { color: var(--primary-orange); }
        .nav-item i { font-size: 20px; margin-bottom: 5px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,20,45,0.9); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px; box-sizing: border-box;}
        .modal-box { background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(25px); padding: 30px; border-radius: 16px; width: 100%; max-width: 400px; color: white; position: relative;}
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: rgba(255,255,255,0.6); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: rgba(255,255,255,0.9); }
        .glass-input { width: 100%; padding: 12px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: white; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
    </style>
</head>
<body>

<div class="support-container">
    <h1 class="page-title"><i class="fa-solid fa-headset"></i> Support Helpdesk</h1>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;"><i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <button class="btn-create" onclick="document.getElementById('modalNewTicket').style.display='flex'">
        <i class="fa-solid fa-plus"></i> Create New Support Ticket
    </button>

    <div>
        <?php if (empty($tickets)): ?>
            <p style="text-align:center; color:rgba(255,255,255,0.5); margin-top:40px; font-size: 14px;">You have no active or past support tickets.</p>
        <?php else: ?>
            <?php foreach ($tickets as $t): 
                $status_class = str_replace(' ', '', $t['status']); 
            ?>
                <div class="ticket-card">
                    <div class="t-header">
                        <span class="status-pill status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($t['status']); ?></span>
                        <span class="t-date"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></span>
                    </div>
                    <h4 class="t-subject"><?php echo htmlspecialchars($t['subject']); ?></h4>
                    <p class="t-msg"><?php echo nl2br(htmlspecialchars($t['message'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fa-solid fa-house"></i> Home</a>
    <a href="my_activity.php" class="nav-item"><i class="fa-solid fa-chart-line"></i> Activity</a>
    <a href="directory.php" class="nav-item"><i class="fa-solid fa-magnifying-glass"></i> Search</a>
    <a href="profile.php" class="nav-item active"><i class="fa-solid fa-user"></i> Profile</a>
</div>

<!-- Create Ticket Modal -->
<div class="modal-overlay" id="modalNewTicket">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="document.getElementById('modalNewTicket').style.display='none'"></i>
        <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:15px;"><i class="fa-solid fa-ticket" style="color:var(--primary-orange);"></i> Submit Ticket</h3>
        
        <form action="actions/submit_ticket.php" method="POST" onsubmit="document.getElementById('btnSubmitTicket').innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i> Submitting...'; document.getElementById('btnSubmitTicket').disabled=true;">
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" class="glass-input" required placeholder="e.g. Issue with my points" maxlength="150">
            </div>

            <div class="form-group">
                <label>Message Details</label>
                <textarea name="message" class="glass-input" required placeholder="Please describe how we can help..." style="min-height: 120px; resize: vertical;"></textarea>
            </div>

            <button type="submit" id="btnSubmitTicket" style="background:var(--primary-orange); color:white; border:none; padding:14px; width:100%; border-radius:8px; font-weight:700; cursor:pointer; margin-top:10px; font-size: 14px;">Send to Headquarters</button>
        </form>
    </div>
</div>

</body>
</html>