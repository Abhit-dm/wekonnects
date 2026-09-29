<?php
// actions/update_leadership.php
session_start();
require_once '../config/database.php';

// Security check
if (!isset($_SESSION['logged_in']) || ($_SESSION['system_role'] !== 'SUPER_ADMIN' && $_SESSION['system_role'] !== 'FRANCHISE_OWNER')) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_group_data = $_POST['user_group_data'] ?? '';
    $new_role = filter_input(INPUT_POST, 'new_role', FILTER_SANITIZE_STRING);

    if (!empty($user_group_data) && in_array($new_role, ['Coordinator', 'Member'])) {
        
        // We combined user_id and group_id in the select value like "15|2"
        $parts = explode('|', $user_group_data);
        if (count($parts) == 2) {
            $user_id = intval($parts[0]);
            $group_id = intval($parts[1]);

            try {
                $stmt = $pdo->prepare("UPDATE group_members SET leadership_role = ? WHERE user_id = ? AND group_id = ?");
                $stmt->execute([$new_role, $user_id, $group_id]);
                
                $_SESSION['success_msg'] = "Leadership role successfully updated to $new_role!";
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "System Error: Could not update role.";
            }
        }
    }
}

header("Location: ../manage_chapters.php");
exit;
?>