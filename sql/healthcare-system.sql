-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 23 مارس 2026 الساعة 16:42
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `healthcare-system`
--

DELIMITER $$
--
-- الإجراءات
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GetNextPatient` ()   BEGIN
    SELECT * FROM v_patient_queue LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetQueuePosition` (IN `p_user_id` INT)   BEGIN
    SELECT COUNT(*) + 1 as position
    FROM patients p1
    JOIN patients p2 ON p2.user_id = p_user_id AND p2.status = 'waiting'
    WHERE p1.status = 'waiting'
    AND (p1.priority < p2.priority 
         OR (p1.priority = p2.priority AND p1.created_at < p2.created_at));
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdatePatientStatus` (IN `p_patient_id` INT, IN `p_new_status` VARCHAR(50))   BEGIN
    UPDATE patients 
    SET status = p_new_status,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_patient_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- بنية الجدول `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `dispensed_medications`
--

CREATE TABLE `dispensed_medications` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `dosage_times_per_day` int(11) NOT NULL,
  `dosage_instructions` text DEFAULT NULL,
  `dispensed_by` int(11) NOT NULL COMMENT 'Pharmacist user_id',
  `dispensed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `dispensed_medications`
--


--
-- القوادح `dispensed_medications`
--
DELIMITER $$
CREATE TRIGGER `after_medication_dispensed` AFTER INSERT ON `dispensed_medications` FOR EACH ROW BEGIN
    UPDATE medications
    SET quantity_in_stock = quantity_in_stock - NEW.quantity
    WHERE id = NEW.medication_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- بنية الجدول `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `years_experience` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `previous_work` text DEFAULT NULL,
  `previous_institution` varchar(200) DEFAULT NULL,
  `certificates_path` varchar(255) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `medications`
--

CREATE TABLE `medications` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity_in_stock` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `alternative_medications` text DEFAULT NULL COMMENT 'Comma-separated list of alternative medication IDs',
  `requires_prescription` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `medications`
--


-- --------------------------------------------------------

--
-- بنية الجدول `medication_consumption_log`
--

CREATE TABLE `medication_consumption_log` (
  `id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `quantity_consumed` int(11) NOT NULL,
  `consumed_by` int(11) NOT NULL COMMENT 'Pharmacist user_id',
  `prescription_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `medication_consumption_log`
--


--
-- القوادح `medication_consumption_log`
--
DELIMITER $$
CREATE TRIGGER `before_medication_consumption` BEFORE INSERT ON `medication_consumption_log` FOR EACH ROW BEGIN
    DECLARE current_stock INT;
    
    SELECT quantity_in_stock INTO current_stock 
    FROM medications 
    WHERE id = NEW.medication_id;
    
    IF current_stock < NEW.quantity_consumed THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Insufficient stock for consumption';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- بنية الجدول `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_chronic` tinyint(1) DEFAULT 0,
  `other_diseases` text DEFAULT NULL,
  `current_conditions` text NOT NULL,
  `is_pregnant` tinyint(1) DEFAULT 0,
  `is_critical` tinyint(1) DEFAULT 0,
  `priority` int(11) NOT NULL CHECK (`priority` between 1 and 5),
  `suggested_department` varchar(100) DEFAULT NULL,
  `suggested_doctor` varchar(100) DEFAULT NULL,
  `status` enum('waiting','in_consultation','completed','cancelled') DEFAULT 'waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `patients`
--


-- --------------------------------------------------------

--
-- بنية الجدول `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank','palPay','jawalPay') NOT NULL,
  `institution_type` enum('charity','government','private') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `payments`
--


-- --------------------------------------------------------

--
-- بنية الجدول `pharmacists`
--

CREATE TABLE `pharmacists` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `years_experience` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `previous_work` text DEFAULT NULL,
  `previous_institution` varchar(200) DEFAULT NULL,
  `certificates_path` varchar(255) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `pharmacist_purchases`
--

CREATE TABLE `pharmacist_purchases` (
  `id` int(11) NOT NULL,
  `pharmacist_id` int(11) NOT NULL,
  `medication_name` varchar(200) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `purchase_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `medication` text NOT NULL,
  `notes` text DEFAULT NULL,
  `consultation_duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `status` enum('pending','dispensed','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `prescriptions`
--


-- --------------------------------------------------------

--
-- بنية الجدول `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `system_logs`
--


-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `user_type` enum('patient','doctor','pharmacist') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--


--
-- القوادح `users`
--
DELIMITER $$
CREATE TRIGGER `after_user_login` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF NEW.updated_at > OLD.updated_at THEN
        INSERT INTO system_logs (user_id, action, description)
        VALUES (NEW.id, 'LOGIN', CONCAT('User ', NEW.full_name, ' logged in'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_doctor_statistics`
-- (See below for the actual view)
--
CREATE TABLE `v_doctor_statistics` (
`doctor_id` int(11)
,`doctor_name` varchar(100)
,`specialization` varchar(100)
,`total_consultations` bigint(21)
,`avg_consultation_duration` decimal(14,4)
,`waiting_patients` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_patient_queue`
-- (See below for the actual view)
--
CREATE TABLE `v_patient_queue` (
`id` int(11)
,`user_id` int(11)
,`full_name` varchar(100)
,`age` int(11)
,`gender` enum('male','female')
,`is_chronic` tinyint(1)
,`is_pregnant` tinyint(1)
,`is_critical` tinyint(1)
,`priority` int(11)
,`current_conditions` text
,`other_diseases` text
,`suggested_department` varchar(100)
,`suggested_doctor` varchar(100)
,`status` enum('waiting','in_consultation','completed','cancelled')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pending_prescriptions`
-- (See below for the actual view)
--
CREATE TABLE `v_pending_prescriptions` (
`prescription_id` int(11)
,`medication` text
,`notes` text
,`consultation_duration` int(11)
,`patient_id` int(11)
,`patient_name` varchar(100)
,`patient_age` int(11)
,`patient_gender` enum('male','female')
,`current_conditions` text
,`doctor_name` varchar(100)
,`specialization` varchar(100)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `v_doctor_statistics`
--
DROP TABLE IF EXISTS `v_doctor_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_doctor_statistics`  AS SELECT `u`.`id` AS `doctor_id`, `u`.`full_name` AS `doctor_name`, `d`.`specialization` AS `specialization`, count(distinct `pr`.`id`) AS `total_consultations`, avg(`pr`.`consultation_duration`) AS `avg_consultation_duration`, count(distinct case when `p`.`status` = 'waiting' then `p`.`id` end) AS `waiting_patients` FROM (((`users` `u` join `doctors` `d` on(`u`.`id` = `d`.`user_id`)) left join `prescriptions` `pr` on(`u`.`id` = `pr`.`doctor_id`)) left join `patients` `p` on(`pr`.`patient_id` = `p`.`id`)) WHERE `u`.`user_type` = 'doctor' GROUP BY `u`.`id`, `u`.`full_name`, `d`.`specialization` ;

-- --------------------------------------------------------

--
-- Structure for view `v_patient_queue`
--
DROP TABLE IF EXISTS `v_patient_queue`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_patient_queue`  AS SELECT `p`.`id` AS `id`, `p`.`user_id` AS `user_id`, `u`.`full_name` AS `full_name`, TIMESTAMPDIFF(YEAR, `u`.`date_of_birth`, CURDATE()) AS `age`, `u`.`gender` AS `gender`, `p`.`is_chronic` AS `is_chronic`, `p`.`is_pregnant` AS `is_pregnant`, `p`.`is_critical` AS `is_critical`, `p`.`priority` AS `priority`, `p`.`current_conditions` AS `current_conditions`, `p`.`other_diseases` AS `other_diseases`, `p`.`suggested_department` AS `suggested_department`, `p`.`suggested_doctor` AS `suggested_doctor`, `p`.`status` AS `status`, `p`.`created_at` AS `created_at` FROM (`patients` `p` join `users` `u` on(`p`.`user_id` = `u`.`id`)) WHERE `p`.`status` = 'waiting' ORDER BY `p`.`priority` ASC, `p`.`created_at` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_pending_prescriptions`
--
DROP TABLE IF EXISTS `v_pending_prescriptions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pending_prescriptions`  AS SELECT `pr`.`id` AS `prescription_id`, `pr`.`medication` AS `medication`, `pr`.`notes` AS `notes`, `pr`.`consultation_duration` AS `consultation_duration`, `p`.`id` AS `patient_id`, `u_patient`.`full_name` AS `patient_name`, TIMESTAMPDIFF(YEAR, `u_patient`.`date_of_birth`, CURDATE()) AS `patient_age`, `u_patient`.`gender` AS `patient_gender`, `p`.`current_conditions` AS `current_conditions`, `u_doctor`.`full_name` AS `doctor_name`, `d`.`specialization` AS `specialization`, `pr`.`created_at` AS `created_at` FROM ((((`prescriptions` `pr` join `patients` `p` on(`pr`.`patient_id` = `p`.`id`)) join `users` `u_patient` on(`p`.`user_id` = `u_patient`.`id`)) join `doctors` `d` on(`pr`.`doctor_id` = `d`.`user_id`)) join `users` `u_doctor` on(`d`.`user_id` = `u_doctor`.`id`)) WHERE `pr`.`status` = 'pending' ORDER BY `pr`.`created_at` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_doctor_id` (`doctor_id`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `dispensed_medications`
--
ALTER TABLE `dispensed_medications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dispensed_by` (`dispensed_by`),
  ADD KEY `idx_prescription_id` (`prescription_id`),
  ADD KEY `idx_medication_id` (`medication_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_specialization` (`specialization`);

--
-- Indexes for table `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `medication_consumption_log`
--
ALTER TABLE `medication_consumption_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `idx_medication` (`medication_id`),
  ADD KEY `idx_consumed_by` (`consumed_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_patient_status_priority` (`status`,`priority`,`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prescription_id` (`prescription_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status_date` (`status`,`paid_at`);

--
-- Indexes for table `pharmacists`
--
ALTER TABLE `pharmacists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `pharmacist_purchases`
--
ALTER TABLE `pharmacist_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pharmacist_purchases_ibfk_1` (`pharmacist_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_doctor_id` (`doctor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_prescription_status_created` (`status`,`created_at`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `national_id` (`national_id`),
  ADD KEY `idx_national_id` (`national_id`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispensed_medications`
--
ALTER TABLE `dispensed_medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `medication_consumption_log`
--
ALTER TABLE `medication_consumption_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `pharmacists`
--
ALTER TABLE `pharmacists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pharmacist_purchases`
--
ALTER TABLE `pharmacist_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `dispensed_medications`
--
ALTER TABLE `dispensed_medications`
  ADD CONSTRAINT `dispensed_medications_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dispensed_medications_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`),
  ADD CONSTRAINT `dispensed_medications_ibfk_3` FOREIGN KEY (`dispensed_by`) REFERENCES `users` (`id`);

--
-- قيود الجداول `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `medication_consumption_log`
--
ALTER TABLE `medication_consumption_log`
  ADD CONSTRAINT `medication_consumption_log_ibfk_1` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medication_consumption_log_ibfk_2` FOREIGN KEY (`consumed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medication_consumption_log_ibfk_3` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `pharmacists`
--
ALTER TABLE `pharmacists`
  ADD CONSTRAINT `pharmacists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `pharmacist_purchases`
--
ALTER TABLE `pharmacist_purchases`
  ADD CONSTRAINT `pharmacist_purchases_ibfk_1` FOREIGN KEY (`pharmacist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
