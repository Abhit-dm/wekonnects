<?php

function ensure_karthika_registrations_table(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS karthika_registrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            event_ticket_count INT UNSIGNED NOT NULL DEFAULT 0,
            satyanarayana_vratam_couple_count INT UNSIGNED NOT NULL DEFAULT 0,
            total_amount INT UNSIGNED NOT NULL,
            payment_screenshot VARCHAR(64) NOT NULL DEFAULT '',
            payment_screenshot_data MEDIUMBLOB NULL,
            payment_screenshot_mime VARCHAR(32) DEFAULT NULL,
            payment_status VARCHAR(24) NOT NULL DEFAULT 'Pending',
            whatsapp_status VARCHAR(24) NOT NULL DEFAULT 'Pending',
            whatsapp_sent_at DATETIME DEFAULT NULL,
            location_status VARCHAR(24) NOT NULL DEFAULT 'Pending',
            location_shared_at DATETIME DEFAULT NULL,
            interest_stalls TINYINT UNSIGNED NOT NULL DEFAULT 0,
            interest_advertisement TINYINT UNSIGNED NOT NULL DEFAULT 0,
            interest_sponsorship TINYINT UNSIGNED NOT NULL DEFAULT 0,
            interest_details VARCHAR(500) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_karthika_created_at (created_at),
            INDEX idx_karthika_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        'payment_screenshot_data' => 'MEDIUMBLOB NULL AFTER payment_screenshot',
        'payment_screenshot_mime' => 'VARCHAR(32) DEFAULT NULL AFTER payment_screenshot_data',
        'whatsapp_status' => "VARCHAR(24) NOT NULL DEFAULT 'Pending' AFTER payment_status",
        'whatsapp_sent_at' => 'DATETIME DEFAULT NULL AFTER whatsapp_status',
        'location_status' => "VARCHAR(24) NOT NULL DEFAULT 'Pending' AFTER whatsapp_sent_at",
        'location_shared_at' => 'DATETIME DEFAULT NULL AFTER location_status'
    ];
    $column_check = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS
                                   WHERE TABLE_SCHEMA = DATABASE()
                                     AND TABLE_NAME = 'karthika_registrations'
                                     AND COLUMN_NAME = ?
                                   LIMIT 1");

    foreach ($columns as $column => $definition) {
        $column_check->execute([$column]);
        if (!$column_check->fetchColumn()) {
            try {
                $pdo->exec("ALTER TABLE karthika_registrations ADD COLUMN `$column` $definition");
            } catch (PDOException $e) {
                if (!isset($e->errorInfo[1]) || (int)$e->errorInfo[1] !== 1060) {
                    throw $e;
                }
            }
        }
    }
}

function karthika_private_upload_directory()
{
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'karthika-private';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the private payment upload directory.');
    }

    $access_rules = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\nOptions -Indexes\n";
    $access_file = $directory . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($access_file) && file_put_contents($access_file, $access_rules, LOCK_EX) === false) {
        throw new RuntimeException('Unable to protect the payment upload directory from direct web access.');
    }

    return $directory;
}

function karthika_legacy_upload_directory()
{
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'wekonnects_private_uploads' . DIRECTORY_SEPARATOR . 'karthika';
}

function karthika_previous_upload_directory()
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'karthika-private';
}
