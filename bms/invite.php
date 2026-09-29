<?php
// invite.php
session_start();
require_once 'config/database.php';

$ref_id = filter_input(INPUT_GET, 'ref', FILTER_SANITIZE_NUMBER_INT);
$prefill_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

// Ensure date format is safe (YYYY-MM-DD)
if (!empty($prefill_date) && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $prefill_date)) {
    $prefill_date = ''; 
}

$inviter_name = "a WE KONNECTS Member";
$group_id = null;

if ($ref_id) {
    // Get Inviter's details and their chapter
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, gm.group_id 
        FROM users u 
        JOIN group_members gm ON u.id = gm.user_id 
        WHERE u.id = ? AND gm.membership_status = 'Active' LIMIT 1
    ");
    $stmt->execute([$ref_id]);
    $inviter = $stmt->fetch();
    
    if ($inviter) {
        $inviter_name = $inviter['first_name'] . ' ' . $inviter['last_name'];
        $group_id = $inviter['group_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_name = trim(strip_tags($_POST['visitor_name']));
    $email = trim(strip_tags($_POST['email']));
    $company_name = trim(strip_tags($_POST['company_name']));
    $phone = trim(strip_tags($_POST['phone']));
    $visit_date = trim(strip_tags($_POST['visit_date']));

    if ($ref_id && $group_id && !empty($visitor_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO visitors (chapter_id, visitor_name, email, company_name, phone, visit_date, invited_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$group_id, $visitor_name, $email, $company_name, $phone, $visit_date, $ref_id]);
            $success = true;
        } catch (PDOException $e) {
            $error = "System error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're Invited! | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #00204a, #003a8c); color: white; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box;}
        .card { background: white; color: #00204a; padding: 30px; border-radius: 16px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); text-align: center;}
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; margin-bottom: 15px;}
        .btn-submit { background: #ff6b00; color: white; border: none; padding: 15px; border-radius: 8px; font-weight: 700; font-size: 16px; width: 100%; cursor: pointer;}
    </style>
</head>
<body>
    <div class="card">
        <?php if (isset($success)): ?>
            <h2 style="color: #059669;">Registration Complete!</h2>
            <p>We look forward to seeing you at the meeting.</p>
        <?php else: ?>
            <h2 style="margin-top: 0;">You're Invited!</h2>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 25px;">You have been invited by <strong><?php echo htmlspecialchars($inviter_name); ?></strong> to visit a WE KONNECTS meeting.</p>
            
            <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            
            <form method="POST">
                <input type="text" name="visitor_name" class="form-input" placeholder="Your Full Name" required>
                <input type="text" name="company_name" class="form-input" placeholder="Company / Business Name" required>
                <input type="tel" name="phone" class="form-input" placeholder="Phone Number" required>
                <input type="email" name="email" class="form-input" placeholder="Email Address" required>
                
                <div style="text-align:left; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Date you plan to visit:</div>
                <input type="date" name="visit_date" class="form-input" value="<?php echo htmlspecialchars($prefill_date); ?>" required>
                
                <button type="submit" class="btn-submit">Register as Guest</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>