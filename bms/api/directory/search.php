<?php
// /api/directory/search.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Capture search parameters
$keyword = isset($_GET['keyword']) ? '%' . trim($_GET['keyword']) . '%' : '%';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;

try {
    // Build the query joining Users, Businesses, Groups, and Categories
    $sql = "SELECT 
                u.id as user_id, u.first_name, u.last_name, 
                b.company_name, 
                bc.category_name, 
                g.group_name, 
                gm.current_tier 
            FROM users u
            JOIN businesses b ON u.id = b.user_id
            JOIN group_members gm ON u.id = gm.user_id
            JOIN groups g ON gm.group_id = g.id
            JOIN business_categories bc ON gm.business_category_id = bc.id
            WHERE (u.first_name LIKE :keyword OR u.last_name LIKE :keyword OR b.company_name LIKE :keyword)";

    // Append category filter if the user selected one
    if ($category_id) {
        $sql .= " AND bc.id = :category_id";
    }

    // Sort Super Stars to the top
    $sql .= " ORDER BY 
                CASE gm.current_tier 
                    WHEN 'SUPER_STAR' THEN 1 
                    WHEN 'RISING_STAR' THEN 2 
                    WHEN 'STAR' THEN 3 
                    ELSE 4 
                END, u.first_name ASC 
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
    if ($category_id) {
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $results
    ]);

} catch (PDOException $e) {
    error_log("Directory Search Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Search failed.']);
}
?>