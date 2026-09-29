<?php
// includes/bottom_nav.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* ULTIMATE OVERRIDE: Mobile-Responsive Footer */
    .bottom-nav { 
        position: fixed !important; 
        bottom: 0 !important; 
        left: 0 !important; 
        
        /* Force physical screen width, completely ignoring the webpage width */
        width: 100vw !important; 
        max-width: 100% !important; 
        
        margin: 0 !important;
        padding: 8px 0 calc(8px + env(safe-area-inset-bottom)) 0 !important; 
        
        display: flex !important; 
        flex-direction: row !important;
        justify-content: space-evenly !important; 
        align-items: center !important; 
        
        background: rgba(0, 20, 45, 0.98) !important; 
        backdrop-filter: blur(16px) !important; 
        z-index: 2147483647 !important; /* Maximum possible z-index */
        
        border-top: 1px solid rgba(255,255,255,0.1) !important; 
        border-radius: 20px 20px 0 0 !important; 
        box-sizing: border-box !important; 
        
        /* Prevent anything from escaping the bar */
        overflow-x: hidden !important; 
    }
    
    .bottom-nav .nav-item { 
        flex: 1 1 0 !important; 
        min-width: 0 !important; /* Forces items to shrink instead of stretching */
        
        display: flex !important; 
        flex-direction: column !important; 
        align-items: center !important; 
        justify-content: center !important; 
        
        color: rgba(255,255,255,0.6) !important; 
        text-decoration: none !important; 
        font-size: 10px !important; /* Smaller font to ensure it fits narrow screens */
        font-weight: 500 !important; 
        text-align: center !important;
        padding: 0 2px !important;
        box-sizing: border-box !important;
    }
    
    .bottom-nav .nav-item.active { 
        color: var(--primary-orange, #ff6b00) !important; 
        font-weight: 700 !important; 
    }
    
    .bottom-nav .nav-item i { 
        font-size: 18px !important; 
        margin-bottom: 4px !important; 
        display: block !important;
    }

    /* Fix for the body to prevent horizontal scrolling on mobile */
    body {
        max-width: 100vw;
        overflow-x: hidden;
    }
</style>

<div class="bottom-nav">
    <a href="index.php" class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-house"></i> Home
    </a>
    <a href="my_activity.php" class="nav-item <?php echo ($current_page == 'my_activity.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-chart-line"></i> Activity
    </a>
    <a href="directory.php" class="nav-item <?php echo ($current_page == 'directory.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-magnifying-glass"></i> Search
    </a>
    <a href="profile.php" class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-user"></i> Profile
    </a>
</div>