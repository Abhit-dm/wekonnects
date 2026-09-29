<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$system_role = $_SESSION['system_role'] ?? 'MEMBER';
?>
<style>
    /* Intelligent Sidebar CSS */
    .sidebar { width: 260px; background-color: #00204a; color: #fff; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; top:0; left:0; overflow-y: auto;}
    .sidebar-brand { padding: 24px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: 800; font-size: 20px; letter-spacing: 1px; color: #fff;}
    .sidebar-brand span { color: #ff6b00; }
    
    .nav-item { padding: 12px 24px; display: flex; align-items: center; gap: 15px; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s; font-size: 14px;}
    .nav-item i { width: 20px; text-align: center; font-size: 16px; }
    
    /* Standard Active State */
    .nav-item:hover, .nav-item.active { background-color: rgba(255, 107, 0, 0.1); color: #ff6b00; border-right: 4px solid #ff6b00; }
    
    /* Super Admin Golden Active State */
    .nav-item.sa-item:hover, .nav-item.sa-active { background-color: rgba(251, 191, 36, 0.1); color: #fbbf24; border-right: 4px solid #fbbf24; }
    
    /* Franchise Blue Active State */
    .nav-item.fo-item:hover, .nav-item.fo-active { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border-right: 4px solid #3b82f6; }

    .sidebar-heading { padding: 20px 24px 10px 24px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; }
    .sidebar-heading.sa-heading { color: #fbbf24; }
    .sidebar-heading.fo-heading { color: #60a5fa; }
    .sidebar-divider { border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 10px 20px; }
    
    /* Custom scrollbar for sidebar */
    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
</style>

<div class="sidebar">
    <div class="sidebar-brand">WE <span>KONNECTS</span></div>
    
    <!-- 1. STRICT SUPER ADMIN HEADQUARTERS -->
    <?php if ($system_role === 'SUPER_ADMIN'): ?>
        <div class="sidebar-heading sa-heading"><i class="fa-solid fa-bolt"></i> Headquarters</div>
        
        <a href="super_admin.php" class="nav-item sa-item <?php echo ($current_page == 'super_admin.php') ? 'sa-active' : ''; ?>">
            <i class="fa-solid fa-globe"></i> Command Center
        </a>
        <a href="pending_applications.php" class="nav-item sa-item <?php echo ($current_page == 'pending_applications.php') ? 'sa-active' : ''; ?>">
            <i class="fa-solid fa-user-clock"></i> Pending Applications
        </a>
        <a href="admin_renewals.php" class="nav-item sa-item <?php echo ($current_page == 'admin_renewals.php') ? 'sa-active' : ''; ?>">
            <i class="fa-solid fa-file-circle-check"></i> Pending Renewals
        </a>
        <a href="sa_manage_franchises.php" class="nav-item sa-item <?php echo ($current_page == 'sa_manage_franchises.php' || $current_page == 'sa_assign_chapters.php') ? 'sa-active' : ''; ?>">
            <i class="fa-solid fa-building-user"></i> Franchise Ops
        </a>
        <a href="sa_master_directory.php" class="nav-item sa-item <?php echo ($current_page == 'sa_master_directory.php') ? 'sa-active' : ''; ?>">
            <i class="fa-solid fa-users-viewfinder"></i> Global Directory
        </a>
        <a href="sa_broadcast.php" class="nav-item sa-item <?php echo ($current_page == 'sa_broadcast.php') ? 'sa-active' : ''; ?>">
            <i class="fa-solid fa-bullhorn"></i> Announcements
        </a>
        <a href="sa_manage_categories.php" class="nav-item sa-item <?php echo ($current_page == 'sa_manage_categories.php') ? 'sa-active' : ''; ?>">
            <i class="fa-solid fa-layer-group"></i> Categories
        </a>
        <hr class="sidebar-divider">
    <?php endif; ?>

    <!-- 2. REGIONAL FRANCHISE CONTROL -->
    <?php if ($system_role === 'FRANCHISE_OWNER' || $system_role === 'SUPER_ADMIN'): ?>
        <div class="sidebar-heading fo-heading"><i class="fa-solid fa-building-user"></i> Franchise Hub</div>
        
        <?php if ($system_role === 'FRANCHISE_OWNER'): ?>
        <a href="admin.php" class="nav-item fo-item <?php echo ($current_page == 'admin.php') ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-chart-line"></i> Dashboard
        </a>
        <?php endif; ?>

        <a href="manage_chapters.php" class="nav-item fo-item <?php echo ($current_page == 'manage_chapters.php') ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-sitemap"></i> Manage Chapters
        </a>
        <a href="admin_directory.php" class="nav-item fo-item <?php echo ($current_page == 'admin_directory.php' || ($system_role === 'FRANCHISE_OWNER' && $current_page == 'edit_member.php')) ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-users-gear"></i> Local Directory
        </a>
        <a href="archived_members.php" class="nav-item fo-item <?php echo ($current_page == 'archived_members.php') ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-box-archive"></i> Archive & Inactive
        </a>
        <a href="pending_applications.php" class="nav-item fo-item <?php echo ($current_page == 'pending_applications.php') ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-user-clock"></i> New Applications
        </a>
        <a href="admin_renewals.php" class="nav-item fo-item <?php echo ($current_page == 'admin_renewals.php') ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-file-signature"></i> Renewals
        </a>
        <a href="chapter_summary.php" class="nav-item fo-item <?php echo ($current_page == 'chapter_summary.php' || $current_page == 'chapter_performance.php') ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-chart-pie"></i> Chapter Analytics
        </a>
        <a href="revenue_reports.php" class="nav-item fo-item <?php echo ($current_page == 'revenue_reports.php') ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i> Revenue Reports
        </a>
        <a href="support.php" class="nav-item fo-item <?php echo ($current_page == 'support.php' || $current_page == 'sa_helpdesk.php') ? 'fo-active' : ''; ?>">
            <i class="fa-solid fa-headset"></i> Support Tickets
        </a>

        <hr class="sidebar-divider">
    <?php endif; ?>

    <!-- 3. STANDARD USER AREA -->
    <div class="sidebar-heading">My Account</div>
    <a href="index.php" class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-house"></i> Dashboard
    </a>
    <a href="profile.php" class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-user"></i> My Profile
    </a>
    
    <!-- Push logout strictly to the bottom of the screen -->
    <div style="flex-grow: 1;"></div>
    <hr class="sidebar-divider">
    <a href="actions/logout.php" class="nav-item" style="color: #fca5a5; margin-bottom: 10px;">
        <i class="fa-solid fa-right-from-bracket"></i> Secure Logout
    </a>
</div>