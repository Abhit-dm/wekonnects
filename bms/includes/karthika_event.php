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
            payment_status VARCHAR(24) NOT NULL DEFAULT 'Pending',
            interest_stalls TINYINT UNSIGNED NOT NULL DEFAULT 0,
            interest_advertisement TINYINT UNSIGNED NOT NULL DEFAULT 0,
            interest_sponsorship TINYINT UNSIGNED NOT NULL DEFAULT 0,
            interest_details VARCHAR(500) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_karthika_created_at (created_at),
            INDEX idx_karthika_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function karthika_private_upload_directory()
{
    $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'wekonnects_private_uploads' . DIRECTORY_SEPARATOR . 'karthika';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the private payment upload directory.');
    }

    return $directory;
}
