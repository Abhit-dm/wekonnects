<?php
// actions/export_events.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { exit; }

$group_id = filter_input(INPUT_GET, 'group_id', FILTER_SANITIZE_NUMBER_INT);

if ($group_id) {
    $stmt = $pdo->prepare("SELECT meeting_date, meeting_type, venue, status FROM chapter_meetings WHERE group_id = ? ORDER BY meeting_date DESC");
    $stmt->execute([$group_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tell the browser to download a CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="chapter_events_export.csv"');

    // Open file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Output the Column Headings
    fputcsv($output, ['Meeting Date', 'Meeting Type', 'Venue/Location', 'Status']);

    // Output all the rows
    foreach ($events as $row) {
        // Format the date nicely before export
        $row['meeting_date'] = date('F j, Y', strtotime($row['meeting_date']));
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
?>