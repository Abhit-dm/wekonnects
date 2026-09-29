<?php
// sa_franchise_leaderboard.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['system_role'] !== 'SUPER_ADMIN') {
    header("Location: login.php"); exit;
}

try {
    // Advanced God-Mode Query: Get all Franchise Owners, count their chapters, and count ALL members across those chapters
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email,
               (SELECT COUNT(*) FROM groups WHERE franchise_owner_id = u.id AND status = 'Active') as total_chapters,
               (SELECT COUNT(gm.id) 
                FROM group_members gm 
                JOIN groups g ON gm.group_id = g.id 
                WHERE g.franchise_owner_id = u.id AND gm.membership_status = 'Active') as total_members
        FROM users u
        WHERE u.system_role = 'FRANCHISE_OWNER' AND u.status = 'Active'
        ORDER BY total_members DESC, total_chapters DESC
    ");
    $leaderboard = $stmt->fetchAll();

} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Franchise Leaderboard | WE KONNECTS SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sa-gold: #fbbf24; --sa-dark: #0f172a; --sa-panel: #1e293b; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; min-height: 100vh;}
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        .sa-header { background: var(--sa-dark); padding: 25px; border-radius: 12px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
        
        .podium-container { display: flex; align-items: flex-end; justify-content: center; gap: 20px; margin-bottom: 40px; margin-top: 20px; }
        .podium-card { background: white; border-radius: 12px 12px 0 0; padding: 20px; text-align: center; border: 1px solid var(--border-color); border-bottom: none; width: 180px; box-shadow: 0 -4px 15px rgba(0,0,0,0.05);}
        .podium-card.rank-1 { height: 160px; border-top: 4px solid var(--sa-gold); background: #fffbeb;}
        .podium-card.rank-2 { height: 130px; border-top: 4px solid #cbd5e1; }
        .podium-card.rank-3 { height: 110px; border-top: 4px solid #b45309; }
        
        .podium-rank { font-size: 24px; font-weight: 800; margin-bottom: 10px; }
        .rank-1 .podium-rank { color: var(--sa-gold); }
        .rank-2 .podium-rank { color: #94a3b8; }
        .rank-3 .podium-rank { color: #b45309; }
        
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f1f5f9; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color);}
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); color: #334155;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <a href="super_admin.php" style="color: #64748b; text-decoration: none; display: inline-block; margin-bottom: 15px; font-size: 14px; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Back to Command Center</a>

    <div class="sa-header">
        <div>
            <h1 style="margin:0; font-size: 24px; color: var(--sa-gold);">Franchise Leaderboard</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; color: #cbd5e1;">Ranking franchisees by ecosystem size and overall member retention.</p>
        </div>
        <i class="fa-solid fa-trophy" style="font-size: 35px; color: var(--sa-gold);"></i>
    </div>

    <?php if (count($leaderboard) >= 3): ?>
        <div class="podium-container">
            <div class="podium-card rank-2">
                <div class="podium-rank">#2</div>
                <strong style="font-size: 14px; color: #0f172a;"><?php echo htmlspecialchars($leaderboard[1]['first_name'] . ' ' . $leaderboard[1]['last_name']); ?></strong><br>
                <span style="font-size: 12px; color: #64748b;"><?php echo $leaderboard[1]['total_members']; ?> Members</span>
            </div>
            <div class="podium-card rank-1">
                <i class="fa-solid fa-crown" style="color: var(--sa-gold); font-size: 24px; margin-bottom: 5px;"></i>
                <div class="podium-rank">#1</div>
                <strong style="font-size: 16px; color: #0f172a;"><?php echo htmlspecialchars($leaderboard[0]['first_name'] . ' ' . $leaderboard[0]['last_name']); ?></strong><br>
                <span style="font-size: 13px; font-weight: 700; color: #b45309;"><?php echo $leaderboard[0]['total_members']; ?> Members</span>
            </div>
            <div class="podium-card rank-3">
                <div class="podium-rank">#3</div>
                <strong style="font-size: 14px; color: #0f172a;"><?php echo htmlspecialchars($leaderboard[2]['first_name'] . ' ' . $leaderboard[2]['last_name']); ?></strong><br>
                <span style="font-size: 12px; color: #64748b;"><?php echo $leaderboard[2]['total_members']; ?> Members</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">Rank</th>
                    <th>Franchise Owner</th>
                    <th>Active Chapters</th>
                    <th>Total Managed Members</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($leaderboard)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: #64748b;">No active franchise data available.</td></tr>
                <?php else: ?>
                    <?php 
                    $rank = 1;
                    foreach($leaderboard as $l): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 800; color: #94a3b8; font-size: 16px;"><?php echo $rank++; ?></td>
                            <td>
                                <strong style="color: #0f172a; font-size: 15px;"><i class="fa-solid fa-building-user" style="color:var(--sa-gold);"></i> <?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name']); ?></strong>
                            </td>
                            <td>
                                <strong style="color: var(--sa-dark);"><?php echo $l['total_chapters']; ?></strong> Chapters
                            </td>
                            <td>
                                <strong style="font-size: 16px; color: #059669;"><?php echo $l['total_members']; ?></strong> Members
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