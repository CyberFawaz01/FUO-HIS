-- =====================================================
-- CA-RBAC HIS — Update SQL (Run this on your hospital DB)
-- =====================================================

USE hospital;

-- Add password to patients table
ALTER TABLE `patients` 
ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL AFTER `email`,
ADD COLUMN `geolocation_lat` DECIMAL(10,8) DEFAULT NULL,
ADD COLUMN `geolocation_lng` DECIMAL(11,8) DEFAULT NULL,
ADD COLUMN `geolocation_address` VARCHAR(255) DEFAULT NULL;

-- Emergency declarations by patients
CREATE TABLE IF NOT EXISTS `patient_emergencies` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id` INT(11) NOT NULL,
  `symptoms` TEXT NOT NULL,
  `severity` ENUM('Mild','Moderate','Severe','Critical') DEFAULT 'Moderate',
  `status` ENUM('active','claimed','resolved') DEFAULT 'active',
  `claimed_by` INT(11) DEFAULT NULL,
  `claimed_at` DATETIME DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `resolution_notes` TEXT DEFAULT NULL,
  `geolocation_lat` DECIMAL(10,8) DEFAULT NULL,
  `geolocation_lng` DECIMAL(11,8) DEFAULT NULL,
  `geolocation_address` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`claimed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patient sessions
CREATE TABLE IF NOT EXISTS `patient_sessions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id` INT(11) NOT NULL,
  `session_token` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `geolocation_lat` DECIMAL(10,8) DEFAULT NULL,
  `geolocation_lng` DECIMAL(11,8) DEFAULT NULL,
  `geolocation_address` VARCHAR(255) DEFAULT NULL,
  `login_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Doctor emergency notifications
CREATE TABLE IF NOT EXISTS `emergency_notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `emergency_id` INT(11) NOT NULL,
  `doctor_id` INT(11) NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`emergency_id`) REFERENCES `patient_emergencies`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Set default passwords for all patients (default: password)
UPDATE `patients` SET `password_hash` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE `password_hash` IS NULL;

-- Add geolocation to users table for staff context
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `geolocation_lat` DECIMAL(10,8) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `geolocation_lng` DECIMAL(11,8) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `geolocation_address` VARCHAR(255) DEFAULT NULL;

-- Add lab reports to all patients (assign lab tech to all patients)
INSERT IGNORE INTO `assignments` (`user_id`, `patient_id`, `assigned_by`, `is_active`)
SELECT 7, p.id, 1, 1 FROM `patients` p
WHERE NOT EXISTS (SELECT 1 FROM `assignments` WHERE user_id=7 AND patient_id=p.id);
