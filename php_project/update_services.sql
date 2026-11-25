USE `church_management`;

-- Clean services
DELETE FROM `services`;

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

-- Re-assign sample service assignments to match the new service IDs
UPDATE `service_assignments` SET `service_id` = 2 WHERE `person_id` = 10;
UPDATE `service_assignments` SET `service_id` = 6 WHERE `person_id` = 4;
UPDATE `service_assignments` SET `service_id` = 7 WHERE `person_id` = 3;
UPDATE `service_assignments` SET `service_id` = 8 WHERE `person_id` = 7;
UPDATE `service_assignments` SET `service_id` = 12 WHERE `person_id` IN (1, 2, 5, 6, 8, 9);
