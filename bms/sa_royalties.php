<?php
// sa_royalties.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

// Set your global subscription fee for calculation purposes
$annual_fee = 10000; // Example: ₹10,000 per member
$hq_percentage = 0.80; // 80% to Headquarters
$franchise_percentage = 0.20; // 20% to Franchise Owner

try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name,
               (SELECT COUNT(gm.id) FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE g.franchise_owner_id = u.id AND gm.membership_status = 'Active') as active_members
        FROM users u
        WHERE u.system_role = 'FRANCHISE_OWNER' AND u.status = 'Active'
        ORDER BY active_members DESC
    ");
    $franchises = $stmt->fetchAll();
} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Royalties | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; min-height: 100vh;}
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden;}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f1f5f9; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color);}
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); color: #334155;}
        .amt-hq { color: #059669; font-weight: 800; }
        .amt-fran { color: #b45309; font-weight: 800; }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="super_admin.php" style="color: #64748b; text-decoration: none; display: inline-block; margin-bottom: 15px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back</a>

    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Franchise Royalties & Splits</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Estimated revenue calculations based on active member count.</p>
        </div>
        <i class="fa-solid fa-hand-holding-dollar" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 20px; display: flex; gap: 30px;">
        <div><span style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700;">Calculation Base</span><br><strong style="font-size: 18px;">₹<?php echo number_format($annual_fee); ?> / member</strong></div>
        <div><span style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700;">HQ Share</span><br><strong style="font-size: 18px; color:#059669;"><?php echo ($hq_percentage * 100); ?>%</strong></div>
        <div><span style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700;">Franchise Share</span><br><strong style="font-size: 18px; color:#b45309;"><?php echo ($franchise_percentage * 100); ?>%</strong></div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Franchise Owner</th>
                    <th>Active Members</th>
                    <th>Total Generated Volume</th>
                    <th>HQ Revenue Split</th>
                    <th>Franchisee Payout Split</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($franchises)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px;">No franchise data found.</td></tr>
                <?php else: ?>
                    <?php foreach($franchises as $f): 
                        $total_vol = $f['active_members'] * $annual_fee;
                        $hq_cut = $total_vol * $hq_percentage;
                        $fran_cut = $total_vol * $franchise_percentage;
                    ?>
                        <tr>
                            <td><strong style="color: #0f172a;"><i class="fa-solid fa-building-user" style="color:var(--sa-gold);"></i> <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?></strong></td>
                            <td><?php echo $f['active_members']; ?></td>
                            <td style="font-weight: 600;">₹<?php echo number_format($total_vol); ?></td>
                            <td class="amt-hq">₹<?php echo number_format($hq_cut); ?></td>
                            <td class="amt-fran">₹<?php echo number_format($fran_cut); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>