-- ===================================================
-- Coptic Church Management System (MySQL / XAMPP)
-- قاعدة بيانات نظام إدارة كنيسة قبطية أرثوذكسية
-- ===================================================

CREATE DATABASE IF NOT EXISTS `church_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `church_management`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `confessions`;
DROP TABLE IF EXISTS `visits`;
DROP TABLE IF EXISTS `priests`;
DROP TABLE IF EXISTS `servant_assignments`;
DROP TABLE IF EXISTS `service_assignments`;
DROP TABLE IF EXISTS `service_members`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `person_relationships`;
DROP TABLE IF EXISTS `family_members`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `persons`;
DROP TABLE IF EXISTS `families`;
DROP TABLE IF EXISTS `settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. جدول الأسر (Families)
CREATE TABLE `families` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `family_code` VARCHAR(50) NOT NULL UNIQUE,
  `family_name` VARCHAR(255) NOT NULL,
  `address` TEXT NULL,
  `street` VARCHAR(255) NULL,
  `branch_from` VARCHAR(255) NULL,
  `area` VARCHAR(255) NULL,
  `landmark` VARCHAR(255) NULL,
  `building_number` VARCHAR(50) NULL,
  `floor` VARCHAR(50) NULL,
  `apartment_number` VARCHAR(50) NULL,
  `gps_location` VARCHAR(255) NULL,
  `phone` VARCHAR(50) NULL,
  `special_case` VARCHAR(50) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_family_code` (`family_code`),
  INDEX `idx_family_name` (`family_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. جدول المخدومين والشعب (Persons)
CREATE TABLE `persons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `family_id` INT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) NULL,
  `last_name` VARCHAR(100) NULL,
  `gender` ENUM('male', 'female') NOT NULL,
  `date_of_birth` DATE NULL,
  `is_deceased` TINYINT(1) DEFAULT 0,
  `date_of_death` DATE NULL,
  `phone` VARCHAR(50) NULL,
  `whatsapp` VARCHAR(50) NULL,
  `second_phone` VARCHAR(50) NULL,
  `email` VARCHAR(255) NULL,
  `national_id` VARCHAR(20) NULL UNIQUE,
  `marital_status` ENUM('single', 'married', 'divorced', 'widowed') NULL,
  `care_situation` VARCHAR(100) NULL,
  `education_stage` ENUM('nursery', 'angels', 'primary_1_2', 'primary_3_4', 'primary_5_6', 'preparatory', 'secondary', 'university', 'graduate', 'mens_gathering') NULL,
  `school_year` VARCHAR(50) NULL,
  `confession_father` VARCHAR(255) NULL,
  `confession_church` VARCHAR(255) NULL,
  `weekly_leave` VARCHAR(255) NULL,
  `is_deacon` TINYINT(1) DEFAULT 0,
  `is_servant` TINYINT(1) DEFAULT 0,
  `in_prep_center` TINYINT(1) DEFAULT 0,
  `occupation` VARCHAR(255) NULL,
  `address` TEXT NULL,
  `notes` TEXT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_archived` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_person_name` (`first_name`, `last_name`),
  INDEX `idx_person_phone` (`phone`),
  INDEX `idx_person_national_id` (`national_id`),
  FOREIGN KEY (`family_id`) REFERENCES `families`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. جدول مستخدمي النظام (Users)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(255) NULL UNIQUE,
  `hashed_password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'priest', 'service_admin', 'servant') NOT NULL DEFAULT 'servant',
  `is_active` TINYINT(1) DEFAULT 1,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `person_id` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. جدول أفراد الأسرة (Family Members)
CREATE TABLE `family_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `family_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `relationship_type` ENUM('father', 'mother', 'husband', 'wife', 'son', 'daughter', 'grandfather', 'grandmother', 'grandson', 'granddaughter', 'relative', 'other') NOT NULL,
  `is_head` TINYINT(1) DEFAULT 0,
  `is_spouse` TINYINT(1) DEFAULT 0,
  `is_child` TINYINT(1) DEFAULT 0,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `is_current` TINYINT(1) DEFAULT 1,
  `notes` TEXT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`family_id`) REFERENCES `families`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. جدول علاقات القرابة المباشرة (Person Relationships)
CREATE TABLE `person_relationships` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `person_id` INT NOT NULL,
  `related_person_id` INT NOT NULL,
  `relationship_type` VARCHAR(50) NOT NULL,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `is_current` TINYINT(1) DEFAULT 1,
  `notes` TEXT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. جدول الخدمات ومدارس الأحد (Services)
CREATE TABLE `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `min_age` INT NULL,
  `max_age` INT NULL,
  `gender_restriction` ENUM('male', 'female') NULL,
  `education_stages` TEXT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. خدام وأعضاء الخدمة (Service Members)
CREATE TABLE `service_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `person_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `role` ENUM('service_admin', 'assistant_admin', 'servant', 'supervisor', 'other') NOT NULL DEFAULT 'servant',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. توزيع المخدومين على الخدمات (Service Assignments)
CREATE TABLE `service_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `person_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `assigned_by` INT NULL,
  `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(50) DEFAULT 'active',
  `notes` TEXT NULL,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. توزيع المخدومين على الخدام (Servant Assignments)
CREATE TABLE `servant_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `servant_id` INT NOT NULL,
  `person_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`servant_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. جدول الآباء الكهنة (Priests)
CREATE TABLE `priests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `person_id` INT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. جدول الافتقاد والزيارات (Visits)
CREATE TABLE `visits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `family_id` INT NULL,
  `person_id` INT NULL,
  `servant_id` INT NULL,
  `visit_date` DATETIME NOT NULL,
  `visit_type` ENUM('home_visit', 'phone_call', 'follow_up', 'emergency_visit', 'other') NOT NULL,
  `status` ENUM('pending', 'completed', 'cancelled', 'rescheduled') DEFAULT 'pending',
  `notes` TEXT NULL,
  `next_visit_date` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`family_id`) REFERENCES `families`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`servant_id`) REFERENCES `persons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. جدول الاعترافات (Confessions)
CREATE TABLE `confessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `person_id` INT NOT NULL,
  `priest_id` INT NULL,
  `confession_date` DATETIME NOT NULL,
  `next_confession_date` DATETIME NULL,
  `status` ENUM('upcoming', 'completed', 'missed', 'cancelled') DEFAULT 'completed',
  `notes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`person_id`) REFERENCES `persons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`priest_id`) REFERENCES `priests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. سجل العمليات والتدقيق (Audit Logs)
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(100) NOT NULL,
  `record_id` INT NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. جدول الإعدادات العامة (Settings)
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT NULL,
  `description` TEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- بيانات أولية واختبارية (Seed Data)
-- ===================================================

-- المستخدمين الأساسيين
INSERT INTO `users` (`username`, `email`, `hashed_password`, `full_name`, `role`, `is_active`, `must_change_password`) VALUES
('admin', 'admin@church.local', '$2y$10$2a0fgAnQANue7HtTlGmIt.Qi521/Ud0blLNlz6r27F5PANJmDwKfy', 'يوسف عاطف', 'admin', 1, 0),
('abouna_seraphim', 'seraphim@church.local', '$2y$10$C76dWgkxWSsFdEAGb6foCeLWJrR9BwLOXd15EusxayBlzCNpiO0lC', 'أبونا سارافيم', 'priest', 1, 1),
('abouna_joseph', 'joseph@church.local', '$2y$10$C76dWgkxWSsFdEAGb6foCeLWJrR9BwLOXd15EusxayBlzCNpiO0lC', 'أبونا جوزيف', 'priest', 1, 1),
('mr_nader_ezzat', 'nader@church.local', '$2y$10$C76dWgkxWSsFdEAGb6foCeLWJrR9BwLOXd15EusxayBlzCNpiO0lC', 'مستر نادر عزت', 'service_admin', 1, 1);

-- الآباء الكهنة (كنيسة العائلة المقدسة)
INSERT INTO `priests` (`id`, `display_name`, `phone`, `is_active`, `notes`) VALUES
(4, 'أبونا سارافيم', '01223456789', 1, 'كاهن كنيسة العائلة المقدسة'),
(5, 'أبونا جوزيف', '01223456780', 1, 'كاهن كنيسة العائلة المقدسة');

-- الخدمات ومدارس الأحد
INSERT INTO `services` (`id`, `name`, `description`, `min_age`, `max_age`, `gender_restriction`, `education_stages`, `is_active`) VALUES
(1, 'حضانة', 'خدمة حضانة الأطفال من 0 حتى 3 سنوات', 0, 3, NULL, 'nursery', 1),
(2, 'ملايكة', 'خدمة ملايكة للأطفال من 3 حتى 5 سنوات', 3, 5, NULL, 'angels', 1),
(3, 'أولى وثانية ابتدائي', 'خدمة الصف الأول والثاني الابتدائي', 5, 7, NULL, 'primary_1_2', 1),
(4, 'ثالثة ورابعة ابتدائي', 'خدمة الصف الثالث والرابع الابتدائي', 7, 9, NULL, 'primary_3_4', 1),
(5, 'خامسة وسادسة ابتدائي', 'خدمة الصف الخامس والسادس الابتدائي', 9, 11, NULL, 'primary_5_6', 1),
(6, 'إعدادي', 'خدمة المرحلة الإعدادية', 11, 14, NULL, 'preparatory', 1),
(7, 'ثانوي', 'خدمة المرحلة الثانوية', 14, 17, NULL, 'secondary', 1),
(8, 'جامعيين', 'خدمة طلاب الجامعة', 17, 25, NULL, 'university', 1),
(9, 'خريجين', 'خدمة الخريجين', 25, 120, NULL, 'graduate', 1),
(10, 'اجتماع الأنبا موسى للرجال', 'مواعيد رجال الكنيسة', 25, 120, 'male', 'mens_gathering,graduate', 1),
(11, 'اجتماع الأم دولاجي للأمهات', 'اجتماع الأمهات في الكنيسة', 25, 120, 'female', 'graduate', 1),
(12, 'اجتماع الأسرة والشعب العام', 'الاجتماع العام الأسبوعي لجميع العائلات', 18, 120, NULL, 'mens_gathering,graduate', 1);

-- إعدادات النظام
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('church_name', 'كنيسة العائلة المقدسة', 'اسم الكنيسة'),
('diocese', 'إيبارشية وسط القاهرة', 'الإيبارشية'),
('address', 'شارع الكنيسة الرئيسي', 'عنوان الكنيسة'),
('phone', '0223456789', 'تليفون الكنيسة'),
('confession_reminder_days', '30', 'فترة التذكير بالاعتراف بالأيام'),
('visit_reminder_days', '90', 'فترة التذكير بالافتقاد بالأيام');

-- عينات أسر (Families)
INSERT INTO `families` (`id`, `family_code`, `family_name`, `address`, `street`, `building_number`, `floor`, `apartment_number`, `phone`, `special_case`) VALUES
(1, 'FAM-001', 'أسرة عاطف جرجس حنا', 'شبرا، القاهرة', 'شارع شبرا الرئيسي', '14', '3', '6', '01221122334', 'عادية'),
(2, 'FAM-002', 'أسرة فكتور صليب فهمي', 'شبرا، القاهرة', 'شارع الترعة البولاقية', '8', '2', '3', '01005544332', 'تحتاج متابعة'),
(3, 'FAM-003', 'أسرة هاني يعقوب ميخائيل', 'الظاهر، القاهرة', 'شارع المستشفى القبطي', '21', '4', '8', '01112233445', 'عادية'),
(4, 'FAM-004', 'أسرة سمير إسحق منسى', 'روض الفرج، القاهرة', 'شارع النصر', '5', '1', '2', '01227788990', 'مسن يحتاج رعاية'),
(5, 'FAM-005', 'أسرة بيشوي كامل عزيز', 'شبرا، القاهرة', 'شارع الوجوه', '12', '5', '10', '01009988776', 'عادية');

-- عينات أفراد (Persons)
INSERT INTO `persons` (`id`, `family_id`, `first_name`, `middle_name`, `last_name`, `gender`, `date_of_birth`, `phone`, `national_id`, `marital_status`, `education_stage`, `confession_father`, `is_deacon`, `is_servant`, `occupation`) VALUES
(1, 1, 'عاطف', 'جرجس', 'حنا', 'male', '1975-04-12', '01221122334', '27504120101234', 'married', 'graduate', 'القمص بولس سمعان', 1, 0, 'مهندس مدني'),
(2, 1, 'مريم', 'فؤاد', 'صليب', 'female', '1980-08-20', '01221122335', '28008200105678', 'married', 'graduate', 'القمص بولس سمعان', 0, 1, 'معلمة لغة إنجليزية'),
(3, 1, 'ديفيد', 'عاطف', 'جرجس', 'male', '2008-06-15', '01221122336', '30806150109012', 'single', 'secondary', 'القس ميخائيل صليب', 1, 0, 'طالب ثانوي'),
(4, 1, 'سارة', 'عاطف', 'جرجس', 'female', '2012-11-05', '01221122337', '31211050103456', 'single', 'preparatory', 'القس كيرلس جرجس', 0, 0, 'طالبة إعدادي'),
(5, 2, 'فكتور', 'صليب', 'فهمي', 'male', '1968-02-18', '01005544332', '26802180101111', 'married', 'graduate', 'القس ميخائيل صليب', 0, 0, 'محاسب قانوني'),
(6, 2, 'مارينا', 'كمال', 'رياض', 'female', '1972-09-10', '01005544333', '27209100102222', 'married', 'graduate', 'القس ميخائيل صليب', 0, 0, 'صيدلانية'),
(7, 2, 'كيرلس', 'فكتور', 'صليب', 'male', '2003-03-25', '01005544334', '30303250103333', 'single', 'university', 'القس ميخائيل صليب', 1, 1, 'طالب كلية هندسة'),
(8, 3, 'هاني', 'يعقوب', 'ميخائيل', 'male', '1982-01-30', '01112233445', '28201300104444', 'married', 'graduate', 'القمص بولس سمعان', 0, 0, 'طبيب بشري'),
(9, 3, 'كرستين', 'سمير', 'وديع', 'female', '1986-05-14', '01112233446', '28605140105555', 'married', 'graduate', 'القمص بولس سمعان', 0, 0, 'طبيبة أسنان'),
(10, 3, 'مارك', 'هاني', 'يعقوب', 'male', '2018-09-12', NULL, '31809120106666', 'single', 'angels', 'القس كيرلس جرجس', 0, 0, 'حضانة');

-- ربط أفراد الأسر (Family Members)
INSERT INTO `family_members` (`family_id`, `person_id`, `relationship_type`, `is_head`, `is_spouse`, `is_child`) VALUES
(1, 1, 'father', 1, 0, 0),
(1, 2, 'mother', 0, 1, 0),
(1, 3, 'son', 0, 0, 1),
(1, 4, 'daughter', 0, 0, 1),
(2, 5, 'father', 1, 0, 0),
(2, 6, 'mother', 0, 1, 0),
(2, 7, 'son', 0, 0, 1),
(3, 8, 'father', 1, 0, 0),
(3, 9, 'mother', 0, 1, 0),
(3, 10, 'son', 0, 0, 1);

-- ربط علاقات القرابة (Person Relationships)
INSERT INTO `person_relationships` (`person_id`, `related_person_id`, `relationship_type`) VALUES
(3, 1, 'father'),
(3, 2, 'mother'),
(4, 1, 'father'),
(4, 2, 'mother'),
(1, 2, 'husband'),
(7, 5, 'father'),
(7, 6, 'mother'),
(5, 6, 'husband'),
(10, 8, 'father'),
(10, 9, 'mother'),
(8, 9, 'husband');

-- توزيع المخدومين على الخدمات (Service Assignments)
INSERT INTO `service_assignments` (`person_id`, `service_id`, `status`) VALUES
(10, 1, 'active'),
(4, 4, 'active'),
(3, 5, 'active'),
(7, 6, 'active'),
(1, 8, 'active'),
(2, 8, 'active'),
(5, 8, 'active'),
(6, 8, 'active'),
(8, 8, 'active'),
(9, 8, 'active');

-- خدام الخدمات (Service Members)
INSERT INTO `service_members` (`person_id`, `service_id`, `role`) VALUES
(2, 4, 'servant'),
(7, 2, 'servant');

-- عينات افتقاد (Visits)
INSERT INTO `visits` (`family_id`, `person_id`, `servant_id`, `visit_date`, `visit_type`, `status`, `notes`, `next_visit_date`) VALUES
(1, 1, 7, '2026-11-10 18:00:00', 'home_visit', 'completed', 'زيارة مباركة، تم الاطمئنان على ديفيد وسارة', '2027-02-10 18:00:00'),
(2, 5, 2, '2026-11-15 19:30:00', 'home_visit', 'completed', 'الأسرة بخير وتشكر الكنيسة', '2027-02-15 19:30:00'),
(3, 8, 7, '2026-11-20 17:00:00', 'phone_call', 'completed', 'اطمئنان هاتفي، اعتذار عن التأخير في الحضور', '2026-12-20 17:00:00'),
(4, NULL, 2, '2026-11-25 18:00:00', 'emergency_visit', 'pending', 'زيارة للمسن عم سمير ومتابعة احتياجاته', NULL);

-- عينات اعترافات (Confessions)
INSERT INTO `confessions` (`person_id`, `priest_id`, `confession_date`, `next_confession_date`, `status`, `notes`) VALUES
(1, 1, '2026-10-25 17:00:00', '2026-11-25 17:00:00', 'completed', 'جلسة اعتراف منتظمة'),
(2, 1, '2026-10-25 18:00:00', '2026-11-25 18:00:00', 'completed', 'جلسة اعتراف منتظمة'),
(3, 2, '2026-11-01 19:00:00', '2026-12-01 19:00:00', 'completed', 'متابعة روحية ممتازة'),
(7, 2, '2026-10-15 20:00:00', '2026-11-15 20:00:00', 'completed', 'اعتراف دوري لخادم'),
(5, 2, '2026-09-01 18:00:00', '2026-10-01 18:00:00', 'missed', 'متأخر عن ميعاد الاعتراف');
