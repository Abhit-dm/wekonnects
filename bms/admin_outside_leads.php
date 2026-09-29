<?php
// admin_outside_leads.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

try {
    // Fetch all chapters for the filter dropdown
    $chapters = $pdo->query("SELECT id, group_name FROM groups WHERE status = 'Active' ORDER BY group_name ASC")->fetchAll();
    
    // Get Filter Values
    $active_chapter_id = isset($_GET['chapter_id']) ? $_GET['chapter_id'] : 'all';
    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // Default to current month if no dates are selected
    if (!$start_date) $start_date = date('Y-m-01');
    if (!$end_date) $end_date = date('Y-m-t');

    // Base Query
    $sql = "
        SELECT s.*, 
               u1.first_name as giver_first, u1.last_name as giver_last,
               u2.first_name as rec_first, u2.last_name as rec_last,
               g.group_name
        FROM slips s
        JOIN users u1 ON s.initiator_member_id = u1.id
        JOIN users u2 ON s.receiver_member_id = u2.id
        JOIN group_members gm ON u1.id = gm.user_id
        JOIN groups g ON gm.group_id = g.id
        WHERE s.slip_type = 'REFERRAL' AND s.referral_type = 'OUTSIDE'
    ";

    $params = [];

    // 1. Apply Chapter Filter
    if ($active_chapter_id !== 'all') {
        $sql .= " AND gm.group_id = ?";
        $params[] = $active_chapter_id;
    }

    // 2. Apply Date Filter
    if ($start_date && $end_date) {
        $sql .= " AND s.date_logged >= ? AND s.date_logged <= ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }

    // 3. Apply Search Filter (Lead Name, Phone, or Member Name)
    if (!empty($search)) {
        $sql .= " AND (s.outside_ref_name LIKE ? OR s.outside_ref_phone LIKE ? OR u1.first_name LIKE ? OR u1.last_name LIKE ?)";
        $search_term = "%{$search}%";
        array_push($params, $search_term, $search_term, $search_term, $search_term);
    }

    $sql .= " ORDER BY s.date_logged DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

} catch (PDOException $e) { 
    die("Database Error: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outside Leads CRM | WE KONNECTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f4f7f6; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        .sidebar { width: 260px; background-color: var(--dark-blue); color: #fff; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; top:0; left:0;}
        .sidebar-brand { padding: 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 18px;}
        .nav-item { padding: 15px 24px; display: flex; align-items: center; gap: 15px; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background-color: rgba(255, 107, 0, 0.1); color: #ff6b00; border-right: 4px solid #ff6b00; }
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,32,74,0.15);}
        
        /* Elegant Filter Bar */
        .filter-bar { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; box-shadow: 0 4px 6px rgba(0,0,0,0.02); flex-wrap: wrap;}
        .form-group { margin: 0; flex: 1; min-width: 160px;}
        .form-group label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-input { width: 100%; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; outline: none; background: #f8fafc; color: #0f172a;}
        .filter-input:focus { border-color: var(--primary-orange); background: white;}
        .btn-search { background: var(--primary-orange); color: white; border: none; padding: 11px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; height: 42px;}
        .btn-search:hover { background: #e66000; box-shadow: 0 4px 10px rgba(255,107,0,0.2); }
        .btn-clear { display: inline-flex; align-items: center; height: 42px; padding: 0 15px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 14px; transition: 0.2s;}
        .btn-clear:hover { color: var(--dark-blue); }

        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 11px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); letter-spacing: 0.5px;}
        td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); vertical-align: top; color: #0f172a;}
        tr:hover { background: #f8fafc; }
        
        .btn-call { background: #10b981; color: white; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; transition: 0.2s; text-align: center;}
        .btn-call:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(16,185,129,0.3);}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">

    <div class="header-box">
        <div>
            <h1 style="margin:0; font-size: 24px;">Outside Leads CRM</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.8;">Track and invite people who were referred by your members.</p>
        </div>
        <i class="fa-solid fa-address-book" style="font-size: 35px; color: var(--primary-orange);"></i>
    </div>

    <form action="admin_outside_leads.php" method="GET" class="filter-bar">
        <div class="form-group" style="flex: 2; min-width: 250px;">
            <label><i class="fa-solid fa-magnifying-glass" style="color: var(--primary-orange);"></i> Search</label>
            <input type="text" name="search" class="filter-input" placeholder="Lead name, phone, or member name..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div class="form-group">
            <label>Chapter</label>
            <select name="chapter_id" class="filter-input" style="cursor: pointer;">
                <option value="all">All Chapters</option>
                <?php foreach ($chapters as $chap): ?>
                    <option value="<?php echo $chap['id']; ?>" <?php if($active_chapter_id == $chap['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($chap['group_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label><i class="fa-regular fa-calendar"></i> Start Date</label>
            <input type="date" name="start_date" class="filter-input" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        
        <div class="form-group">
            <label><i class="fa-regular fa-calendar-check"></i> End Date</label>
            <input type="date" name="end_date" class="filter-input" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        
        <div style="margin: 0; display: flex; align-items: flex-end; gap: 5px;">
            <button type="submit" class="btn-search">Filter</button>
            <a href="admin_outside_leads.php" class="btn-clear">Clear</a>
        </div>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date Logged</th>
                    <th>Lead Details (Outside Person)</th>
                    <th>Referral Details</th>
                    <th style="width: 140px; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 50px; color: #64748b;">
                            <i class="fa-solid fa-folder-open" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5; display:block;"></i>
                            No outside leads match your current filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $row): ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <strong style="color: var(--dark-blue);"><?php echo date('M d, Y', strtotime($row['date_logged'])); ?></strong><br>
                                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($row['group_name']); ?></span>
                            </td>
                            
                            <td>
                                <strong style="font-size: 16px; color: #0f172a; display:block; margin-bottom:4px;"><?php echo htmlspecialchars($row['outside_ref_name']); ?></strong>
                                <span style="color: #3b82f6; font-weight: 600; background: #eff6ff; padding: 4px 8px; border-radius: 6px; display: inline-block;"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($row['outside_ref_phone']); ?></span>
                            </td>

                            <td>
                                <span style="color: #64748b; font-size: 12px; display:block; margin-bottom:2px;"><i class="fa-solid fa-user-tag" style="width: 15px;"></i> Generated By: <strong style="color:#0f172a;"><?php echo htmlspecialchars($row['giver_first'] . ' ' . $row['giver_last']); ?></strong></span>
                                <span style="color: #64748b; font-size: 12px; display:block; margin-bottom: 8px;"><i class="fa-solid fa-handshake" style="width: 15px;"></i> Given To: <strong style="color:#0f172a;"><?php echo htmlspecialchars($row['rec_first'] . ' ' . $row['rec_last']); ?></strong></span>
                                
                                <?php if (!empty($row['topics_discussed'])): ?>
                                    <div style="background: #f8fafc; padding: 10px; border-radius: 8px; font-size: 12px; color: #334155; border-left: 3px solid #cbd5e1; line-height: 1.4;">
                                        <i class="fa-solid fa-quote-left" style="color: #94a3b8; margin-right: 5px;"></i> <?php echo htmlspecialchars($row['topics_discussed']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td style="text-align: center; vertical-align: middle;">
                                <a href="tel:<?php echo htmlspecialchars($row['outside_ref_phone']); ?>" class="btn-call"><i class="fa-solid fa-phone-volume"></i> Call Lead</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>
</body>
</html>