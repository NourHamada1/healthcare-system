-- =====================================================================
-- Migration: Replace users.age with users.date_of_birth
-- Age is now derived automatically from date_of_birth using
-- TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) so it stays accurate.
-- Safe to run once on an existing database.
-- =====================================================================

-- 1) Add the new date_of_birth column
ALTER TABLE `users`
  ADD COLUMN `date_of_birth` DATE NULL AFTER `gender`;

-- 2) Backfill existing rows from the old age value (approximate: Jan 1st)
UPDATE `users`
  SET `date_of_birth` = DATE_SUB(MAKEDATE(YEAR(CURDATE()) - `age`, 1), INTERVAL 0 DAY)
  WHERE `date_of_birth` IS NULL;

-- 3) Enforce NOT NULL now that every row has a value
ALTER TABLE `users`
  MODIFY COLUMN `date_of_birth` DATE NOT NULL;

-- 4) Recreate views that referenced the old age column so they compute it
DROP VIEW IF EXISTS `v_patient_queue`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_patient_queue` AS
SELECT
    `p`.`id` AS `id`,
    `p`.`user_id` AS `user_id`,
    `u`.`full_name` AS `full_name`,
    TIMESTAMPDIFF(YEAR, `u`.`date_of_birth`, CURDATE()) AS `age`,
    `u`.`gender` AS `gender`,
    `p`.`is_chronic` AS `is_chronic`,
    `p`.`is_pregnant` AS `is_pregnant`,
    `p`.`is_critical` AS `is_critical`,
    `p`.`priority` AS `priority`,
    `p`.`current_conditions` AS `current_conditions`,
    `p`.`other_diseases` AS `other_diseases`,
    `p`.`suggested_department` AS `suggested_department`,
    `p`.`suggested_doctor` AS `suggested_doctor`,
    `p`.`status` AS `status`,
    `p`.`created_at` AS `created_at`
FROM (`patients` `p` JOIN `users` `u` ON (`p`.`user_id` = `u`.`id`))
WHERE `p`.`status` = 'waiting'
ORDER BY `p`.`priority` ASC, `p`.`created_at` ASC;

DROP VIEW IF EXISTS `v_pending_prescriptions`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pending_prescriptions` AS
SELECT
    `pr`.`id` AS `prescription_id`,
    `pr`.`medication` AS `medication`,
    `pr`.`notes` AS `notes`,
    `pr`.`consultation_duration` AS `consultation_duration`,
    `p`.`id` AS `patient_id`,
    `u_patient`.`full_name` AS `patient_name`,
    TIMESTAMPDIFF(YEAR, `u_patient`.`date_of_birth`, CURDATE()) AS `patient_age`,
    `u_patient`.`gender` AS `patient_gender`,
    `p`.`current_conditions` AS `current_conditions`,
    `u_doctor`.`full_name` AS `doctor_name`,
    `d`.`specialization` AS `specialization`,
    `pr`.`created_at` AS `created_at`
FROM ((((`prescriptions` `pr`
    JOIN `patients` `p` ON (`pr`.`patient_id` = `p`.`id`))
    JOIN `users` `u_patient` ON (`p`.`user_id` = `u_patient`.`id`))
    JOIN `doctors` `d` ON (`pr`.`doctor_id` = `d`.`user_id`))
    JOIN `users` `u_doctor` ON (`d`.`user_id` = `u_doctor`.`id`))
WHERE `pr`.`status` = 'pending'
ORDER BY `pr`.`created_at` ASC;

-- 5) Finally drop the old age column
ALTER TABLE `users`
  DROP COLUMN `age`;
