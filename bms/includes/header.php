<?php
// includes/header.php
$first_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
$role = isset($_SESSION['system_role']) ? $_SESSION['system_role'] : '';

// Convert role to a readable format (e.g., SUPER_ADMIN to Super Admin)
$display_role = ucwords(strtolower(str_replace('_', ' ', $role)));
?>

<header class="top-header">
    <h1 class="header-title">Command Center</h1>
    <div class="user-profile">
        <span><?php echo htmlspecialchars($first_name); ?> (<?php echo htmlspecialchars($display_role); ?>)</span>
        <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1)); ?></div>
        <a href="actions/logout.php" style="color: var(--text-gray); margin-left: 15px;" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</header>