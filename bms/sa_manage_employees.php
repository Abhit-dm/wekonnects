<?php
// sa_manage_employees.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    // Fetch all custom employee roles (ignoring standard roles)
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, email, phone, system_role, status, created_at 
        FROM users 
        WHERE system_role LIKE 'EMP_%' 
        ORDER BY created_at DESC
    ");
    $employees = $stmt->fetchAll();
} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Management | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #334155; --text-muted: #94a3b8; }
        body { font-family: 'Inter', sans-serif; background-color: var(--sa-dark); color: white; margin: 0; display: flex; min-height: 100vh;}
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-panel); padding: 25px; border-radius: 12px; margin-bottom: 25px; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;}
        
        .layout-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;}
        .card { background: var(--sa-panel); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--text-muted); text-transform: uppercase;}
        .form-input { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; background: var(--sa-dark); color: white;}
        .form-input:focus { outline: none; border-color: #3b82f6; }
        
        .btn-add { background: #3b82f6; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s;}
        .btn-add:hover { background: #2563eb; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: rgba(0,0,0,0.2); padding: 15px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color);}
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); color: white;}
        tr:last-child td { border-bottom: none; }
        
        .role-badge { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="super_admin.php" style="color: var(--text-muted); text-decoration: none; display: inline-block; margin-bottom: 15px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to HQ</a>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.3);"><i class="fa-solid fa-check"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.3);"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: white;">Employee Staff Roles</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: var(--text-muted);">Create internal staff accounts with restricted access scopes.</p>
        </div>
        <i class="fa-solid fa-id-badge" style="font-size: 35px; color: #3b82f6;"></i>
    </div>

    <div class="layout-grid">
        <div class="card">
            <h3 style="margin-top:0; color:white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">Create Staff Account</h3>
            <form action="actions/create_admin_user.php" method="POST">
                
                <!-- Tells the action file to return us back to this page -->
                <input type="hidden" name="return_url" value="../sa_manage_employees.php">
                
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="tel" name="phone" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Temporary Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Assign Permission Role</label>
                    <select name="role" class="form-input" required>
                        <option value="EMP_VISITOR_MANAGER">Global Visitor Manager (Access to Visitor DB)</option>
                        <option value="EMP_SUPPORT_AGENT">Support Agent (Access to Helpdesk)</option>
                        <option value="EMP_DATA_AUDITOR">Data Auditor (Read-Only Analytics)</option>
                    </select>
                </div>
                <button type="submit" class="btn-add"><i class="fa-solid fa-user-plus"></i> Add Employee</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0; color:white; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">Active Staff Roster</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee Details</th>
                        <th>Assigned Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($employees)): ?>
                        <tr><td colspan="3" style="text-align: center; padding: 40px; color: var(--text-muted);">No staff employees created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($employees as $e): ?>
                            <tr>
                                <td>
                                    <strong style="font-size:14px; display:block;"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></strong>
                                    <span style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($e['email']); ?> | <?php echo htmlspecialchars($e['phone']); ?></span>
                                </td>
                                <td><span class="role-badge"><?php echo str_replace('EMP_', '', htmlspecialchars($e['system_role'])); ?></span></td>
                                <td><span style="color: #10b981; font-weight:bold; font-size:12px;"><i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($e['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>