-- Run once after importing the existing database dump.
-- This preserves existing registrations and renames the old Kalyanam count column.

ALTER TABLE `karthika_registrations`
    CHANGE COLUMN `kalyanam_couple_count` `satyanarayana_vratam_couple_count` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    MODIFY COLUMN `payment_screenshot` VARCHAR(64) NOT NULL DEFAULT '',
    ADD COLUMN `whatsapp_status` VARCHAR(24) NOT NULL DEFAULT 'Pending' AFTER `payment_status`,
    ADD COLUMN `whatsapp_sent_at` DATETIME DEFAULT NULL AFTER `whatsapp_status`,
    ADD COLUMN `location_status` VARCHAR(24) NOT NULL DEFAULT 'Pending' AFTER `whatsapp_sent_at`,
    ADD COLUMN `location_shared_at` DATETIME DEFAULT NULL AFTER `location_status`,
    ADD COLUMN `interest_stalls` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `payment_status`,
    ADD COLUMN `interest_advertisement` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `interest_stalls`,
    ADD COLUMN `interest_sponsorship` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `interest_advertisement`,
    ADD COLUMN `interest_details` VARCHAR(500) NOT NULL DEFAULT '' AFTER `interest_sponsorship`;

-- Confirm the migrated columns before deploying the matching application changes.
SHOW COLUMNS FROM `karthika_registrations`;
