-- =====================================================
-- CA-RBAC Hospital Information System Database
-- Complete Schema with Sample Data
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `ca_rbac_his` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ca_rbac_his`;

-- =====================================================
-- TABLE: roles
-- =====================================================
CREATE TABLE `roles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `risk_modifier` INT(11) DEFAULT 0 COMMENT 'Negative values reduce risk score',
  `can_emergency_override` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` VALUES
(1,'admin','Administrator','Full system administration access',0,1,NOW()),
(2,'doctor','Doctor','Licensed medical practitioner',-10,1,NOW()),
(3,'nurse','Nurse','Registered nursing professional',0,0,NOW()),
(4,'pharmacist','Pharmacist','Licensed pharmacist',0,0,NOW()),
(5,'lab_tech','Laboratory Technician','Laboratory analysis specialist',0,0,NOW()),
(6,'surgeon','Surgeon','Surgical specialist',-15,1,NOW()),
(7,'consultant','Consultant','Senior medical consultant',-20,1,NOW());

-- =====================================================
-- TABLE: permissions
-- =====================================================
CREATE TABLE `permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `permission_name` VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `module` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_name` (`permission_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `permissions` VALUES
(1,'read_ehr','Read EHR','Read electronic health records','ehr',NOW()),
(2,'write_ehr','Write EHR','Create new EHR entries','ehr',NOW()),
(3,'update_ehr','Update EHR','Modify existing EHR entries','ehr',NOW()),
(4,'delete_ehr','Delete EHR','Delete EHR records','ehr',NOW()),
(5,'prescribe','Prescribe Medication','Issue prescriptions','pharmacy',NOW()),
(6,'upload_lab','Upload Lab Results','Upload laboratory reports','lab',NOW()),
(7,'emergency_override','Emergency Override','Trigger emergency access bypass','emergency',NOW()),
(8,'manage_users','Manage Users','Create/edit/delete users','admin',NOW()),
(9,'manage_roles','Manage Roles','Assign/revoke roles','admin',NOW()),
(10,'view_audit_logs','View Audit Logs','Access the audit log viewer','admin',NOW()),
(11,'manage_assignments','Manage Assignments','Assign patients to staff','admin',NOW()),
(12,'view_risk_monitor','View Risk Monitor','View risk monitoring dashboard','admin',NOW()),
(13,'read_prescriptions','Read Prescriptions','Read issued prescriptions','pharmacy',NOW()),
(14,'dispense_medication','Dispense Medication','Mark medications as dispensed','pharmacy',NOW()),
(15,'view_lab_reports','View Lab Reports','View laboratory reports','lab',NOW()),
(16,'perform_surgery','Perform Surgery','Access surgical records and logs','surgery',NOW()),
(17,'manage_schedules','Manage Schedules','Manage staff shift schedules','admin',NOW()),
(18,'configure_policies','Configure Policies','Modify RBAC/context policies','admin',NOW());

-- =====================================================
-- TABLE: role_permissions
-- =====================================================
CREATE TABLE `role_permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_id` INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_perm` (`role_id`,`permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES
-- Admin: all permissions
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),
-- Doctor
(2,1),(2,2),(2,3),(2,5),(2,7),(2,13),(2,15),
-- Nurse
(3,1),(3,2),(3,3),(3,15),
-- Pharmacist
(4,1),(4,13),(4,14),(4,5),
-- Lab Tech
(5,1),(5,6),(5,15),
-- Surgeon
(6,1),(6,2),(6,3),(6,5),(6,7),(6,16),(6,15),
-- Consultant
(7,1),(7,2),(7,3),(7,4),(7,5),(7,6),(7,7),(7,15),(7,16);

-- =====================================================
-- TABLE: departments
-- =====================================================
CREATE TABLE `departments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `dept_name` VARCHAR(100) NOT NULL,
  `dept_code` VARCHAR(20) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `floor` VARCHAR(20) DEFAULT NULL,
  `head_doctor` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dept_code` (`dept_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `departments` VALUES
(1,'Emergency Department','EMRG','24/7 emergency care','Ground Floor',NULL,NOW()),
(2,'General Medicine','GENMED','General medical ward','1st Floor',NULL,NOW()),
(3,'Surgery','SURG','Surgical operations and care','2nd Floor',NULL,NOW()),
(4,'Paediatrics','PAED','Children\'s health department','1st Floor',NULL,NOW()),
(5,'Pharmacy','PHARM','Hospital pharmacy','Ground Floor',NULL,NOW()),
(6,'Laboratory','LAB','Diagnostic laboratory','Basement',NULL,NOW()),
(7,'Intensive Care Unit','ICU','Critical care unit','2nd Floor',NULL,NOW()),
(8,'Cardiology','CARDIO','Heart and vascular care','3rd Floor',NULL,NOW()),
(9,'Radiology','RADIO','Imaging and diagnostics','Basement',NULL,NOW()),
(10,'Orthopaedics','ORTHO','Bone and joint care','3rd Floor',NULL,NOW());

-- =====================================================
-- TABLE: hospital_locations
-- =====================================================
CREATE TABLE `hospital_locations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `location_name` VARCHAR(100) NOT NULL,
  `ip_range` VARCHAR(100) DEFAULT NULL,
  `is_trusted` TINYINT(1) DEFAULT 0,
  `location_type` ENUM('internal','remote','vpn','unknown') DEFAULT 'unknown',
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `hospital_locations` VALUES
(1,'Main Hospital Network','192.168.1.0/24',1,'internal','Primary hospital internal network',NOW()),
(2,'ICU Station','192.168.2.0/24',1,'internal','ICU dedicated network segment',NOW()),
(3,'Admin Block','192.168.3.0/24',1,'internal','Administrative network block',NOW()),
(4,'VPN Remote Access','10.0.0.0/8',1,'vpn','Approved VPN for remote access',NOW()),
(5,'External/Unknown','0.0.0.0/0',0,'remote','Unrecognized external network',NOW());

-- =====================================================
-- TABLE: trusted_networks
-- =====================================================
CREATE TABLE `trusted_networks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `network_name` VARCHAR(100) NOT NULL,
  `ip_prefix` VARCHAR(50) NOT NULL,
  `trust_level` ENUM('high','medium','low') DEFAULT 'low',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `trusted_networks` VALUES
(1,'Hospital LAN','192.168.',high,NOW()),
(2,'Hospital VPN','10.0.','medium',NOW()),
(3,'Admin Subnet','172.16.','medium',NOW());

-- =====================================================
-- TABLE: users
-- =====================================================
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `staff_id` VARCHAR(20) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT(11) NOT NULL,
  `department_id` INT(11) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `specialization` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_locked` TINYINT(1) DEFAULT 0,
  `failed_attempts` INT(11) DEFAULT 0,
  `last_login` DATETIME DEFAULT NULL,
  `password_reset_token` VARCHAR(255) DEFAULT NULL,
  `token_expires` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  UNIQUE KEY `email` (`email`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Passwords are all: Password@123 (hashed with bcrypt)
INSERT INTO `users` (`staff_id`,`full_name`,`email`,`password_hash`,`role_id`,`department_id`,`phone`,`specialization`,`is_active`) VALUES
('ADM001','Dr. Admin Superuser','admin@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',1,3,'08012345678','System Administration',1),
('DOC001','Dr. Amara Okonkwo','amara.okonkwo@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',2,2,'08023456789','General Medicine',1),
('DOC002','Dr. Emeka Nwosu','emeka.nwosu@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',2,1,'08034567890','Emergency Medicine',1),
('NUR001','Nurse Chidinma Eze','chidinma.eze@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',3,2,'08045678901','General Nursing',1),
('NUR002','Nurse Fatima Bello','fatima.bello@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',3,7,'08056789012','ICU Nursing',1),
('PHARM001','Mr. Kunle Adeyemi','kunle.adeyemi@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',4,5,'08067890123','Clinical Pharmacy',1),
('LAB001','Mrs. Ngozi Okeke','ngozi.okeke@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',5,6,'08078901234','Clinical Biochemistry',1),
('SURG001','Dr. Babatunde Afolabi','babatunde.afolabi@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',6,3,'08089012345','Cardiovascular Surgery',1),
('CONS001','Dr. Grace Adeleke','grace.adeleke@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',7,8,'08090123456','Cardiology Consulting',1),
('DOC003','Dr. Ibrahim Musa','ibrahim.musa@hospital.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',2,4,'08001234567','Paediatrics',1);

-- =====================================================
-- TABLE: device_registry
-- =====================================================
CREATE TABLE `device_registry` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `device_fingerprint` VARCHAR(255) NOT NULL,
  `device_name` VARCHAR(150) DEFAULT NULL,
  `device_type` ENUM('desktop','laptop','tablet','mobile','unknown') DEFAULT 'unknown',
  `user_agent` TEXT DEFAULT NULL,
  `is_trusted` TINYINT(1) DEFAULT 0,
  `trust_expires` DATETIME DEFAULT NULL,
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: staff_shifts
-- =====================================================
CREATE TABLE `staff_shifts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `shift_name` VARCHAR(50) NOT NULL,
  `shift_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `department_id` INT(11) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert shift data for current and next 7 days
INSERT INTO `staff_shifts` (`user_id`,`shift_name`,`shift_date`,`start_time`,`end_time`,`department_id`) VALUES
(2,'Morning Shift',CURDATE(),'08:00:00','16:00:00',2),
(2,'Morning Shift',DATE_ADD(CURDATE(),INTERVAL 1 DAY),'08:00:00','16:00:00',2),
(3,'Night Shift',CURDATE(),'20:00:00','08:00:00',1),
(4,'Morning Shift',CURDATE(),'08:00:00','16:00:00',2),
(4,'Morning Shift',DATE_ADD(CURDATE(),INTERVAL 1 DAY),'08:00:00','16:00:00',2),
(5,'Evening Shift',CURDATE(),'14:00:00','22:00:00',7),
(6,'Morning Shift',CURDATE(),'09:00:00','17:00:00',5),
(7,'Morning Shift',CURDATE(),'08:00:00','16:00:00',6),
(8,'Morning Shift',CURDATE(),'07:00:00','15:00:00',3),
(9,'On-Call',CURDATE(),'00:00:00','23:59:00',8),
(10,'Morning Shift',CURDATE(),'08:00:00','16:00:00',4);

-- =====================================================
-- TABLE: patients
-- =====================================================
CREATE TABLE `patients` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id` VARCHAR(20) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `gender` ENUM('Male','Female','Other') NOT NULL,
  `blood_group` VARCHAR(10) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `next_of_kin` VARCHAR(150) DEFAULT NULL,
  `next_of_kin_phone` VARCHAR(20) DEFAULT NULL,
  `department_id` INT(11) DEFAULT NULL,
  `admission_status` ENUM('admitted','discharged','outpatient','emergency') DEFAULT 'outpatient',
  `sensitivity_level` ENUM('Low','Medium','High','Critical') DEFAULT 'Low',
  `is_emergency` TINYINT(1) DEFAULT 0,
  `emergency_condition` TEXT DEFAULT NULL,
  `admitted_date` DATETIME DEFAULT NULL,
  `discharged_date` DATETIME DEFAULT NULL,
  `attending_doctor` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
  FOREIGN KEY (`attending_doctor`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `patients` VALUES
(1,'PAT001','Adaeze Obi','1985-06-15','Female','O+','08012340001','adaeze@email.com','12 Wuse, Abuja',NULL,NULL,2,'admitted','Medium',0,NULL,DATE_SUB(NOW(),INTERVAL 3 DAY),NULL,2,NOW()),
(2,'PAT002','Musa Dangote','1972-02-28','Male','A+','08012340002','musa@email.com','34 Garki, Abuja',NULL,NULL,1,'emergency','Critical',1,'Acute Myocardial Infarction',NOW(),NULL,3,NOW()),
(3,'PAT003','Bukola Adetunji','1990-11-10','Female','B-','08012340003','bukola@email.com','56 Maitama, Abuja',NULL,NULL,4,'admitted','Low',0,NULL,DATE_SUB(NOW(),INTERVAL 1 DAY),NULL,10,NOW()),
(4,'PAT004','Emmanuel Okonkwo','1965-07-22','Male','AB+','08012340004','emmanuel@email.com','78 Central, Abuja',NULL,NULL,8,'admitted','High',0,NULL,DATE_SUB(NOW(),INTERVAL 5 DAY),NULL,9,NOW()),
(5,'PAT005','Halima Bello','2001-03-05','Female','O-','08012340005','halima@email.com','90 Gwarimpa, Abuja',NULL,NULL,2,'outpatient','Low',0,NULL,NULL,NULL,2,NOW()),
(6,'PAT006','Chukwuemeka Eze','1978-09-14','Male','A-','08012340006','chukwu@email.com','11 Asokoro, Abuja',NULL,NULL,3,'admitted','High',0,NULL,DATE_SUB(NOW(),INTERVAL 2 DAY),NULL,8,NOW()),
(7,'PAT007','Ngozi Abiodun','1955-12-30','Female','B+','08012340007','ngozi@email.com','23 Kubwa, Abuja',NULL,NULL,7,'admitted','Critical',1,'Septic Shock – Multi-organ failure',NOW(),NULL,2,NOW()),
(8,'PAT008','Yusuf Ibrahim','1988-05-18','Male','O+','08012340008','yusuf@email.com','45 Kuje, Abuja',NULL,NULL,6,'outpatient','Low',0,NULL,NULL,NULL,NULL,NOW()),
(9,'PAT009','Aisha Garba','2010-08-25','Female','A+','08012340009','aisha@email.com','67 Bwari, Abuja',NULL,NULL,4,'admitted','Medium',0,NULL,DATE_SUB(NOW(),INTERVAL 1 DAY),NULL,10,NOW()),
(10,'PAT010','Tunde Fashola','1980-01-19','Male','AB-','08012340010','tunde@email.com','89 Nyanya, Abuja',NULL,NULL,2,'admitted','Low',0,NULL,DATE_SUB(NOW(),INTERVAL 4 DAY),NULL,2,NOW());

-- =====================================================
-- TABLE: assignments (staff-patient assignments)
-- =====================================================
CREATE TABLE `assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `patient_id` INT(11) NOT NULL,
  `assigned_by` INT(11) DEFAULT NULL,
  `assigned_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `end_date` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `assignments` (`user_id`,`patient_id`,`assigned_by`,`is_active`) VALUES
(2,1,1,1),(2,5,1,1),(2,10,1,1),
(3,2,1,1),(3,7,1,1),
(4,3,1,1),(4,9,1,1),
(5,1,1,1),(5,7,1,1),
(6,5,1,1),(6,10,1,1),
(7,8,1,1),
(8,2,1,1),(8,6,1,1),
(9,4,1,1),(9,7,1,1),
(10,3,1,1),(10,9,1,1);

-- =====================================================
-- TABLE: patient_records (EHR)
-- =====================================================
CREATE TABLE `patient_records` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id` INT(11) NOT NULL,
  `record_type` ENUM('diagnosis','medication','allergy','treatment','lab_report','surgery_note','consultation','vitals') NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `created_by` INT(11) NOT NULL,
  `sensitivity_level` ENUM('Low','Medium','High','Critical') DEFAULT 'Low',
  `is_confidential` TINYINT(1) DEFAULT 0,
  `record_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `patient_records` (`patient_id`,`record_type`,`title`,`content`,`created_by`,`sensitivity_level`) VALUES
(1,'diagnosis','Hypertension Stage 2','Patient presents with persistent elevated blood pressure. BP readings: 160/100 mmHg. Started on Amlodipine 10mg daily.',2,'Medium'),
(1,'medication','Amlodipine 10mg Daily','Prescribed Amlodipine 10mg OD. Patient tolerating well. Monitor for peripheral oedema.',2,'Medium'),
(1,'allergy','Penicillin Allergy','Severe allergic reaction to Penicillin reported. Rash and angioedema in 2019.',2,'High'),
(2,'diagnosis','Acute Myocardial Infarction','STEMI confirmed. ECG shows ST elevation in leads II, III, aVF. Troponin elevated x5 normal. Urgent cath lab review.',3,'Critical'),
(2,'treatment','Emergency Thrombolysis','Alteplase 100mg IV infused over 90 minutes. Patient responding. ICU observation required.',3,'Critical'),
(4,'diagnosis','Chronic Heart Failure','NYHA Class III heart failure. EF: 35%. On Furosemide, Bisoprolol, and Ramipril.',9,'High'),
(4,'lab_report','BNP Level Report','BNP: 1200 pg/mL (elevated). Creatinine: 140 umol/L. eGFR: 48 mL/min/1.73m2.',7,'High'),
(6,'surgery_note','Laparoscopic Appendicectomy','Procedure completed successfully. Inflamed appendix removed. No complications. Patient stable.',8,'High'),
(7,'diagnosis','Septic Shock','Multi-organ dysfunction secondary to gram-negative sepsis. SOFA score: 12. Noradrenaline running at 0.3 mcg/kg/min.',2,'Critical'),
(7,'treatment','Broad-Spectrum Antibiotics','Piperacillin-Tazobactam 4.5g QDS IV. Gentamicin 5mg/kg OD IV. Blood cultures taken.',2,'Critical');

-- =====================================================
-- TABLE: access_requests
-- =====================================================
CREATE TABLE `access_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `patient_id` INT(11) DEFAULT NULL,
  `permission_requested` VARCHAR(100) NOT NULL,
  `request_type` ENUM('normal','emergency','override') DEFAULT 'normal',
  `decision` ENUM('granted','denied','conditional','pending') DEFAULT 'pending',
  `decision_reason` TEXT DEFAULT NULL,
  `risk_score` INT(11) DEFAULT 0,
  `risk_level` ENUM('Low','Medium','High','Critical') DEFAULT 'Low',
  `context_data` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `device_fingerprint` VARCHAR(255) DEFAULT NULL,
  `is_on_shift` TINYINT(1) DEFAULT 0,
  `is_assigned` TINYINT(1) DEFAULT 0,
  `is_trusted_network` TINYINT(1) DEFAULT 0,
  `is_emergency` TINYINT(1) DEFAULT 0,
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: audit_logs
-- =====================================================
CREATE TABLE `audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) DEFAULT NULL,
  `patient_id` INT(11) DEFAULT NULL,
  `resource` VARCHAR(200) DEFAULT NULL,
  `decision` ENUM('granted','denied','conditional','info','warning','alert') DEFAULT 'info',
  `risk_score` INT(11) DEFAULT NULL,
  `risk_level` VARCHAR(20) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `device_info` VARCHAR(255) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_decision` (`decision`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: risk_scores
-- =====================================================
CREATE TABLE `risk_scores` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `access_request_id` INT(11) DEFAULT NULL,
  `base_score` INT(11) DEFAULT 0,
  `final_score` INT(11) DEFAULT 0,
  `risk_level` ENUM('Low','Medium','High','Critical') DEFAULT 'Low',
  `factors` JSON DEFAULT NULL,
  `calculated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: emergency_access
-- =====================================================
CREATE TABLE `emergency_access` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `patient_id` INT(11) DEFAULT NULL,
  `reason` TEXT NOT NULL,
  `access_granted` TINYINT(1) DEFAULT 0,
  `granted_by` INT(11) DEFAULT NULL COMMENT 'NULL = system auto-grant',
  `expires_at` DATETIME NOT NULL,
  `is_expired` TINYINT(1) DEFAULT 0,
  `risk_score` INT(11) DEFAULT 0,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: login_sessions
-- =====================================================
CREATE TABLE `login_sessions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `session_token` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `device_fingerprint` VARCHAR(255) DEFAULT NULL,
  `is_trusted_network` TINYINT(1) DEFAULT 0,
  `login_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `logout_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: context_logs
-- =====================================================
CREATE TABLE `context_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `location_type` VARCHAR(50) DEFAULT NULL,
  `device_type` VARCHAR(50) DEFAULT NULL,
  `is_trusted_network` TINYINT(1) DEFAULT 0,
  `is_on_shift` TINYINT(1) DEFAULT 0,
  `is_after_hours` TINYINT(1) DEFAULT 0,
  `shift_info` JSON DEFAULT NULL,
  `risk_factors` JSON DEFAULT NULL,
  `captured_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- SAMPLE AUDIT LOG DATA (Historical simulation)
-- =====================================================
INSERT INTO `audit_logs` (`user_id`,`action`,`module`,`patient_id`,`decision`,`risk_score`,`risk_level`,`ip_address`,`details`,`created_at`) VALUES
(2,'LOGIN_SUCCESS','auth',NULL,'granted',10,'Low','192.168.1.45','Doctor Amara logged in from hospital network',DATE_SUB(NOW(),INTERVAL 2 HOUR)),
(3,'LOGIN_ATTEMPT','auth',NULL,'denied',55,'High','45.12.78.90','Nurse login from unrecognized external IP',DATE_SUB(NOW(),INTERVAL 3 HOUR)),
(2,'EHR_ACCESS','ehr',1,'granted',10,'Low','192.168.1.45','Doctor accessed assigned patient PAT001',DATE_SUB(NOW(),INTERVAL 1 HOUR)),
(5,'LAB_UPLOAD','lab',4,'granted',15,'Low','192.168.1.89','Lab tech uploaded BNP results for PAT004',DATE_SUB(NOW(),INTERVAL 4 HOUR)),
(8,'EMERGENCY_OVERRIDE','emergency',2,'granted',35,'Medium','192.168.2.11','Surgeon triggered emergency override for cardiac patient',DATE_SUB(NOW(),INTERVAL 5 HOUR)),
(3,'EHR_ACCESS','ehr',4,'denied',75,'High','45.12.78.90','Nurse attempted access from outside hospital - unassigned patient',DATE_SUB(NOW(),INTERVAL 3 HOUR)),
(4,'PRESCRIPTION_VIEW','pharmacy',1,'granted',20,'Low','192.168.1.78','Pharmacist viewed prescriptions for PAT001',DATE_SUB(NOW(),INTERVAL 6 HOUR)),
(9,'CONSULTATION','ehr',7,'granted',5,'Low','192.168.3.10','Consultant accessed critical patient (auto-approved)',DATE_SUB(NOW(),INTERVAL 7 HOUR)),
(2,'LOGIN_SUCCESS','auth',NULL,'granted',10,'Low','192.168.1.45','Doctor Amara re-logged in',DATE_SUB(NOW(),INTERVAL 30 MINUTE)),
(1,'USER_CREATED','admin',NULL,'info',0,'Low','192.168.3.1','Admin created new nurse account',DATE_SUB(NOW(),INTERVAL 1 DAY));

COMMIT;
