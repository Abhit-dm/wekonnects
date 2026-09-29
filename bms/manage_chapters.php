<?php
// manage_chapters.php
session_start();
require_once 'config/database.php';

// Security: Admins only
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: login.php"); exit;
}

try {
    // Fetch all Chapters and count their active members
    $stmt = $pdo->prepare("
        SELECT g.id, g.group_name, g.default_venue, g.status, g.created_at, g.start_date,
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND membership_status = 'Active') as member_count
        FROM groups g
        ORDER BY g.group_name ASC
    ");
    $stmt->execute();
    $chapters = $stmt->fetchAll();

} catch (PDOException $e) { die("Database Error."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Chapters | WE KONNECTS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-orange: #ff6b00; --dark-blue: #00204a; --bg-light: #f4f7f6; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--dark-blue); margin: 0; display: flex; min-height: 100vh;}
        
        .sidebar { width: 260px; background-color: var(--dark-blue); color: #fff; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; top:0; left:0;}
        .sidebar-brand { padding: 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold; font-size: 18px;}
        .nav-item { padding: 15px 24px; display: flex; align-items: center; gap: 15px; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background-color: rgba(255, 107, 0, 0.1); color: #ff6b00; border-right: 4px solid #ff6b00; }
        
        .main-content { margin-left: 260px; padding: 40px; width: 100%; box-sizing: border-box; }
        
        .header-box { background: linear-gradient(135deg, var(--dark-blue), #003a8c); padding: 25px; border-radius: 16px; margin-bottom: 25px; color: white; display: flex; justify-content: space-between; align-items: center;}
        
        .top-action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;}
        .search-box { position: relative; width: 300px; }
        .search-box i { position: absolute; left: 15px; top: 12px; color: #64748b; }
        .search-box input { width: 100%; padding: 10px 10px 10px 40px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box; transition: 0.3s;}
        .search-box input:focus { outline: none; border-color: var(--primary-orange); box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1); }
        
        .table-container { background: white; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8fafc; padding: 15px; font-size: 12px; color: #64748b; text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        td { padding: 15px; font-size: 14px; border-bottom: 1px solid var(--border-color); }
        tr:hover { background: #f8fafc; }
        
        .btn-primary { background: var(--primary-orange); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: 0.2s;}
        .btn-primary:hover { background: #e65c00; }
        .btn-edit { background: #3b82f6; color: white; border: none; padding: 8px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; transition: 0.2s;}
        .btn-delete { background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; transition: 0.2s;}

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,32,74,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px; box-sizing: border-box;}
        .modal-box { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 450px; position: relative; margin: auto; }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #00204a; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter'; font-size: 14px; box-sizing: border-box;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
    <?php endif; ?>

    <div class="header-box">
        <div>
            <h1 style="margin:0; font-size: 24px;">Manage Chapters</h1>
            <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.8;">Create new chapters, edit details, or set launch dates.</p>
        </div>
        <i class="fa-solid fa-building-user" style="font-size: 35px; color: var(--primary-orange);"></i>
    </div>

    <div class="top-action-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="chapterSearch" placeholder="Search chapters...">
        </div>
        <button onclick="openModal('modalAddChapter')" class="btn-primary"><i class="fa-solid fa-plus-circle"></i> Create New Chapter</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Chapter Name</th>
                    <th>Launch / Start Date</th>
                    <th>Default Venue</th>
                    <th>Active Members</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($chapters)): ?>
                    <tr id="noChapters"><td colspan="6" style="text-align: center; padding: 30px; color: #64748b;">No chapters found.</td></tr>
                <?php else: ?>
                    <?php foreach ($chapters as $ch): ?>
                        <tr class="chapter-row">
                            <td class="chapter-name"><strong><?php echo htmlspecialchars($ch['group_name']); ?></strong></td>
                            <td style="color: #64748b;"><i class="fa-solid fa-calendar"></i> <?php echo !empty($ch['start_date']) ? date('M d, Y', strtotime($ch['start_date'])) : 'Not Set'; ?></td>
                            <td><?php echo htmlspecialchars($ch['default_venue'] ?? 'Not Set'); ?></td>
                            <td><span style="background: #eff6ff; color: #2563eb; padding: 4px 8px; border-radius: 6px; font-weight: bold;"><?php echo $ch['member_count']; ?> Members</span></td>
                            <td>
                                <?php if($ch['status'] == 'Active'): ?>
                                    <span style="color: #059669; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Active</span>
                                <?php else: ?>
                                    <span style="color: #ef4444; font-weight: 600;"><i class="fa-solid fa-circle-xmark"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="openModal('modalEdit_<?php echo $ch['id']; ?>')" class="btn-edit"><i class="fa-solid fa-pen"></i> Edit</button>
                                
                                <form action="actions/update_chapter.php" method="POST" style="display:inline-block;" onsubmit="return confirm('WARNING: Are you sure you want to DELETE this chapter? This will remove all members from this group.');">
                                    <input type="hidden" name="action_type" value="delete">
                                    <input type="hidden" name="group_id" value="<?php echo $ch['id']; ?>">
                                    <button type="submit" class="btn-delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal-overlay" id="modalEdit_<?php echo $ch['id']; ?>">
                            <div class="modal-box">
                                <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalEdit_<?php echo $ch['id']; ?>')"></i>
                                <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Edit Chapter</h3>
                                
                                <form action="actions/update_chapter.php" method="POST">
                                    <input type="hidden" name="action_type" value="edit">
                                    <input type="hidden" name="group_id" value="<?php echo $ch['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label>Chapter Name</label>
                                        <input type="text" name="group_name" class="form-input" value="<?php echo htmlspecialchars($ch['group_name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Chapter Start Date</label>
                                        <input type="date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($ch['start_date'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label>Default Venue / Location</label>
                                        <input type="text" name="default_venue" class="form-input" value="<?php echo htmlspecialchars($ch['default_venue'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-input">
                                            <option value="Active" <?php if($ch['status'] == 'Active') echo 'selected'; ?>>Active</option>
                                            <option value="Inactive" <?php if($ch['status'] == 'Inactive') echo 'selected'; ?>>Inactive</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Save Changes</button>
                                </form>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php endif; ?>
                <tr id="noResultsRow" style="display:none;"><td colspan="6" style="text-align: center; padding: 30px; color: #64748b;">No matching chapters found.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="modalAddChapter">
    <div class="modal-box">
        <i class="fa-solid fa-xmark close-modal" onclick="closeModal('modalAddChapter')"></i>
        <h3 style="margin-top:0; color:var(--dark-blue); border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Create New Chapter</h3>
        
        <form action="actions/update_chapter.php" method="POST">
            <input type="hidden" name="action_type" value="add">
            
            <div class="form-group">
                <label>Chapter Name</label>
                <input type="text" name="group_name" class="form-input" placeholder="e.g. WE KONNECTS Titans" required>
            </div>

            <div class="form-group">
                <label>Chapter Start Date</label>
                <input type="date" name="start_date" class="form-input">
            </div>
            
            <div class="form-group">
                <label>Default Venue / Location</label>
                <input type="text" name="default_venue" class="form-input" placeholder="e.g. Grand Hotel, Vijayawada">
            </div>

            <div class="form-group">
                <label>Initial Status</label>
                <select name="status" class="form-input">
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Create Chapter</button>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

    document.getElementById('chapterSearch').addEventListener('keyup', function() {
        let filterText = this.value.toLowerCase().trim();
        let rows = document.querySelectorAll('.chapter-row');
        let noResults = document.getElementById('noResultsRow');
        let visibleCount = 0;

        rows.forEach(row => {
            let chapName = row.querySelector('.chapter-name').innerText.toLowerCase();
            if (chapName.includes(filterText)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noResults) {
            noResults.style.display = (visibleCount === 0) ? '' : 'none';
        }
    });
</script>
</body>
</html>