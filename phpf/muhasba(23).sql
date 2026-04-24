-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 26, 2026 at 09:45 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `muhasba`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_acceptance_memorandum`
--

CREATE TABLE `audit_acceptance_memorandum` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `client_name` varchar(255) NOT NULL,
  `engagement_number` varchar(100) DEFAULT NULL,
  `financial_year` varchar(100) DEFAULT NULL,
  `commencement_date` varchar(50) DEFAULT NULL,
  `risk_assessment` varchar(50) DEFAULT 'LOW RISK',
  `auditor_name` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cdd_verifications`
--

CREATE TABLE `cdd_verifications` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `identity_verification` enum('verified','failed') DEFAULT NULL,
  `eligibility_verification` enum('verified','failed') DEFAULT NULL,
  `auditor_verification` enum('verified','failed') DEFAULT NULL,
  `economic_sector` varchar(100) DEFAULT NULL,
  `eligibility_status` enum('eligible','not_eligible','pending') DEFAULT NULL,
  `verification_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`verification_history`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entities`
--

CREATE TABLE `entities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `engagement_number` varchar(100) DEFAULT NULL,
  `application_type` enum('new','return') DEFAULT 'new',
  `application_status` enum('draft','submitted','under_review','approved','rejected') DEFAULT 'draft',
  `current_step` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `screening_completed` tinyint(1) DEFAULT 0,
  `ind_completed` tinyint(1) DEFAULT 0,
  `cdd_completed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_step1`
--

CREATE TABLE `entity_step1` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `business_registration_status` enum('Unlicensed Natural Person(s)','Free Zone Licensed','Mainland Licensed-Multiple Owners','Mainland Licensed-Sole Owner') NOT NULL,
  `company_owner_name` varchar(255) NOT NULL,
  `mainland_company_type` varchar(100) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `license_issue_date` date DEFAULT NULL,
  `license_expiry_date` date DEFAULT NULL,
  `main_activity` varchar(255) NOT NULL,
  `emirate` enum('Abu Dhabi','Dubai','Sharjah','Ajman','Umm Al Quwain','Ras Al Khaimah','Fujairah') NOT NULL,
  `address` text NOT NULL,
  `shareholders` longtext DEFAULT NULL COMMENT 'JSON array of shareholders',
  `ubos` longtext DEFAULT NULL COMMENT 'JSON array of ultimate beneficial owners',
  `management_control` varchar(255) DEFAULT NULL,
  `total_turnover` decimal(15,2) DEFAULT NULL,
  `eid_passports` longtext DEFAULT NULL COMMENT 'JSON array of EID and Passport documents [{"file_name": "", "mime_type": "", "size": 0, "base64_data": "", "uploaded_at": ""}]',
  `trade_license` longtext DEFAULT NULL COMMENT 'JSON array of trade license documents [{"file_name": "", "mime_type": "", "size": 0, "base64_data": "", "uploaded_at": ""}]',
  `authorization_letter` longtext DEFAULT NULL COMMENT 'JSON array of authorization letter documents [{"file_name": "", "mime_type": "", "size": 0, "base64_data": "", "uploaded_at": ""}]',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_step2`
--

CREATE TABLE `entity_step2` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `audit_fee_acknowledged` tinyint(1) DEFAULT 0,
  `audit_fee_amount` decimal(10,2) DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_step3`
--

CREATE TABLE `entity_step3` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `current_fy_start_date` date NOT NULL,
  `current_fy_end_date` date NOT NULL,
  `previous_fy_start_date` date DEFAULT NULL,
  `previous_fy_end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `previous_auditor_files` longtext DEFAULT NULL COMMENT 'JSON array of previous auditor documents [{"file_name": "", "mime_type": "", "size": 0, "base64_data": "", "uploaded_at": ""}]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_step4`
--

CREATE TABLE `entity_step4` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `current_year_vat_status` enum('Registered','Not Registered','Exempt','Pending') DEFAULT NULL,
  `current_year_vat_reg_number` varchar(50) DEFAULT NULL,
  `current_year_excise_tax_status` enum('Registered','Not Registered','Exempt','Pending') DEFAULT NULL,
  `current_year_corporate_tax_status` enum('Registered','Not Registered','Exempt','Pending','Not Applicable') DEFAULT NULL,
  `current_year_corporate_tax_reg_number` varchar(50) DEFAULT NULL,
  `current_year_corporate_tax_treatment` enum('General','Qualifying Free Zone Person','Exempt','Not Applicable') DEFAULT NULL,
  `current_year_small_business_relief` enum('Yes','No','Not Applicable') DEFAULT NULL,
  `current_year_reason_not_registered_ct` text DEFAULT NULL,
  `previous_year_vat_status` enum('Registered','Not Registered','Exempt','Pending') DEFAULT NULL,
  `previous_year_vat_reg_number` varchar(50) DEFAULT NULL,
  `previous_year_excise_tax_status` enum('Registered','Not Registered','Exempt','Pending') DEFAULT NULL,
  `previous_year_corporate_tax_status` enum('Registered','Not Registered','Exempt','Pending','Not Applicable') DEFAULT NULL,
  `previous_year_corporate_tax_reg_number` varchar(50) DEFAULT NULL,
  `previous_year_corporate_tax_treatment` enum('General','Qualifying Free Zone Person','Exempt','Not Applicable') DEFAULT NULL,
  `previous_year_small_business_relief` enum('Yes','No','Not Applicable') DEFAULT NULL,
  `previous_year_reason_not_registered_ct` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_step5`
--

CREATE TABLE `entity_step5` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `engagement_number` varchar(100) DEFAULT NULL,
  `terms_accepted` tinyint(1) DEFAULT 0,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `digital_signature_name` varchar(255) DEFAULT NULL,
  `digital_signature_date` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `independence_confirmations`
--

CREATE TABLE `independence_confirmations` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `engagement_number` varchar(100) DEFAULT NULL,
  `confirmation_type` enum('confirmed','conflict') NOT NULL,
  `confirmation_status` enum('pending','confirmed','conflict_declared','sent','terminated') DEFAULT 'pending',
  `confirmed_by` int(11) DEFAULT NULL COMMENT 'User ID who confirmed',
  `confirmation_text` text DEFAULT NULL,
  `signature_name` varchar(255) DEFAULT NULL,
  `signature_date` timestamp NULL DEFAULT NULL,
  `conflict_details` text DEFAULT NULL COMMENT 'Details if conflict declared',
  `status_message` text DEFAULT NULL COMMENT 'Status message shown to user',
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `client_audit` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sanctions_list`
--

CREATE TABLE `sanctions_list` (
  `id` int(11) NOT NULL,
  `english_name` varchar(255) NOT NULL,
  `arabic_name` varchar(255) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `type` enum('individual','company','organization') DEFAULT 'individual',
  `status` enum('active','inactive','pending_review') DEFAULT 'active',
  `source` varchar(100) DEFAULT NULL COMMENT 'e.g., UN, UAE, US, EU, etc.',
  `list_reference` varchar(100) DEFAULT NULL COMMENT 'Reference number from the source list',
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Reason for sanction',
  `notes` text DEFAULT NULL COMMENT 'Additional notes or comments',
  `created_by` int(11) DEFAULT NULL COMMENT 'User who added the record',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'User who verified the record',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sanctions_list_backup_20260117_150447`
--

CREATE TABLE `sanctions_list_backup_20260117_150447` (
  `id` int(11) NOT NULL,
  `english_name` varchar(255) NOT NULL,
  `arabic_name` varchar(255) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `type` enum('individual','company','organization') DEFAULT 'individual',
  `status` enum('active','inactive','pending_review') DEFAULT 'active',
  `source` varchar(100) DEFAULT NULL COMMENT 'e.g., UN, UAE, US, EU, etc.',
  `list_reference` varchar(100) DEFAULT NULL COMMENT 'Reference number from the source list',
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Reason for sanction',
  `notes` text DEFAULT NULL COMMENT 'Additional notes or comments',
  `created_by` int(11) DEFAULT NULL COMMENT 'User who added the record',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'User who verified the record',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sanctions_list_backup_20260122_175244`
--

CREATE TABLE `sanctions_list_backup_20260122_175244` (
  `id` int(11) NOT NULL,
  `english_name` varchar(255) NOT NULL,
  `arabic_name` varchar(255) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `type` enum('individual','company','organization') DEFAULT 'individual',
  `status` enum('active','inactive','pending_review') DEFAULT 'active',
  `source` varchar(100) DEFAULT NULL COMMENT 'e.g., UN, UAE, US, EU, etc.',
  `list_reference` varchar(100) DEFAULT NULL COMMENT 'Reference number from the source list',
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Reason for sanction',
  `notes` text DEFAULT NULL COMMENT 'Additional notes or comments',
  `created_by` int(11) DEFAULT NULL COMMENT 'User who added the record',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'User who verified the record',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sanctions_list_backup_20260125_210434`
--

CREATE TABLE `sanctions_list_backup_20260125_210434` (
  `id` int(11) NOT NULL,
  `english_name` varchar(255) NOT NULL,
  `arabic_name` varchar(255) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `type` enum('individual','company','organization') DEFAULT 'individual',
  `status` enum('active','inactive','pending_review') DEFAULT 'active',
  `source` varchar(100) DEFAULT NULL COMMENT 'e.g., UN, UAE, US, EU, etc.',
  `list_reference` varchar(100) DEFAULT NULL COMMENT 'Reference number from the source list',
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Reason for sanction',
  `notes` text DEFAULT NULL COMMENT 'Additional notes or comments',
  `created_by` int(11) DEFAULT NULL COMMENT 'User who added the record',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'User who verified the record',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `screening_results`
--

CREATE TABLE `screening_results` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `name_to_screen` varchar(255) NOT NULL,
  `name_type` varchar(100) DEFAULT NULL,
  `source_table` varchar(100) DEFAULT NULL,
  `ai_result` enum('confirmed','partial','no-match') DEFAULT NULL,
  `admin_result` enum('confirmed','partial','no-match') DEFAULT NULL,
  `screened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_type` enum('individual','company') DEFAULT 'individual',
  `role` enum('client','admin','auditor','staff') DEFAULT 'client',
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(6) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `mobile_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_audit_logs`
--

CREATE TABLE `user_audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_acceptance_memorandum`
--
ALTER TABLE `audit_acceptance_memorandum`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_engagement_number` (`engagement_number`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `cdd_verifications`
--
ALTER TABLE `cdd_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_id` (`entity_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `entities`
--
ALTER TABLE `entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_engagement_number` (`engagement_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_application_status` (`application_status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_entity_name` (`entity_name`);

--
-- Indexes for table `entity_step1`
--
ALTER TABLE `entity_step1`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_id` (`entity_id`),
  ADD KEY `idx_business_registration_status` (`business_registration_status`),
  ADD KEY `idx_emirate` (`emirate`);

--
-- Indexes for table `entity_step2`
--
ALTER TABLE `entity_step2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_id` (`entity_id`);

--
-- Indexes for table `entity_step3`
--
ALTER TABLE `entity_step3`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_id` (`entity_id`),
  ADD KEY `idx_current_fy_end_date` (`current_fy_end_date`);

--
-- Indexes for table `entity_step4`
--
ALTER TABLE `entity_step4`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_id` (`entity_id`),
  ADD KEY `idx_current_year_vat_status` (`current_year_vat_status`),
  ADD KEY `idx_current_year_corporate_tax_status` (`current_year_corporate_tax_status`);

--
-- Indexes for table `entity_step5`
--
ALTER TABLE `entity_step5`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_id` (`entity_id`),
  ADD UNIQUE KEY `idx_engagement_number` (`engagement_number`),
  ADD KEY `idx_terms_accepted` (`terms_accepted`),
  ADD KEY `idx_engagement_number_status` (`engagement_number`,`terms_accepted`);

--
-- Indexes for table `independence_confirmations`
--
ALTER TABLE `independence_confirmations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_engagement_number` (`engagement_number`),
  ADD KEY `idx_confirmation_type` (`confirmation_type`),
  ADD KEY `idx_confirmation_status` (`confirmation_status`),
  ADD KEY `idx_confirmed_by` (`confirmed_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_independence_sent_by` (`sent_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `sanctions_list`
--
ALTER TABLE `sanctions_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_english_name` (`english_name`),
  ADD KEY `idx_arabic_name` (`arabic_name`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_source` (`source`),
  ADD KEY `idx_effective_date` (`effective_date`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_verified_by` (`verified_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_full_search` (`english_name`,`arabic_name`,`country`);

--
-- Indexes for table `sanctions_list_backup_20260117_150447`
--
ALTER TABLE `sanctions_list_backup_20260117_150447`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_english_name` (`english_name`),
  ADD KEY `idx_arabic_name` (`arabic_name`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_source` (`source`),
  ADD KEY `idx_effective_date` (`effective_date`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_verified_by` (`verified_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_full_search` (`english_name`,`arabic_name`,`country`);

--
-- Indexes for table `sanctions_list_backup_20260122_175244`
--
ALTER TABLE `sanctions_list_backup_20260122_175244`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_english_name` (`english_name`),
  ADD KEY `idx_arabic_name` (`arabic_name`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_source` (`source`),
  ADD KEY `idx_effective_date` (`effective_date`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_verified_by` (`verified_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_full_search` (`english_name`,`arabic_name`,`country`);

--
-- Indexes for table `sanctions_list_backup_20260125_210434`
--
ALTER TABLE `sanctions_list_backup_20260125_210434`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_english_name` (`english_name`),
  ADD KEY `idx_arabic_name` (`arabic_name`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_source` (`source`),
  ADD KEY `idx_effective_date` (`effective_date`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_verified_by` (`verified_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_full_search` (`english_name`,`arabic_name`,`country`);

--
-- Indexes for table `screening_results`
--
ALTER TABLE `screening_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_name_to_screen` (`name_to_screen`),
  ADD KEY `idx_ai_result` (`ai_result`),
  ADD KEY `idx_admin_result` (`admin_result`),
  ADD KEY `idx_verified_by` (`verified_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_mobile` (`mobile`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_is_verified` (`is_verified`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `user_audit_logs`
--
ALTER TABLE `user_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_acceptance_memorandum`
--
ALTER TABLE `audit_acceptance_memorandum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cdd_verifications`
--
ALTER TABLE `cdd_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entities`
--
ALTER TABLE `entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_step1`
--
ALTER TABLE `entity_step1`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_step2`
--
ALTER TABLE `entity_step2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_step3`
--
ALTER TABLE `entity_step3`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_step4`
--
ALTER TABLE `entity_step4`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_step5`
--
ALTER TABLE `entity_step5`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `independence_confirmations`
--
ALTER TABLE `independence_confirmations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sanctions_list`
--
ALTER TABLE `sanctions_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sanctions_list_backup_20260117_150447`
--
ALTER TABLE `sanctions_list_backup_20260117_150447`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sanctions_list_backup_20260122_175244`
--
ALTER TABLE `sanctions_list_backup_20260122_175244`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sanctions_list_backup_20260125_210434`
--
ALTER TABLE `sanctions_list_backup_20260125_210434`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `screening_results`
--
ALTER TABLE `screening_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_audit_logs`
--
ALTER TABLE `user_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_acceptance_memorandum`
--
ALTER TABLE `audit_acceptance_memorandum`
  ADD CONSTRAINT `fk_memorandum_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_memorandum_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cdd_verifications`
--
ALTER TABLE `cdd_verifications`
  ADD CONSTRAINT `cdd_verifications_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cdd_verifications_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entities`
--
ALTER TABLE `entities`
  ADD CONSTRAINT `entities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_step1`
--
ALTER TABLE `entity_step1`
  ADD CONSTRAINT `entity_step1_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_step2`
--
ALTER TABLE `entity_step2`
  ADD CONSTRAINT `entity_step2_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_step3`
--
ALTER TABLE `entity_step3`
  ADD CONSTRAINT `entity_step3_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_step4`
--
ALTER TABLE `entity_step4`
  ADD CONSTRAINT `entity_step4_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_step5`
--
ALTER TABLE `entity_step5`
  ADD CONSTRAINT `entity_step5_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `independence_confirmations`
--
ALTER TABLE `independence_confirmations`
  ADD CONSTRAINT `fk_independence_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_independence_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_independence_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sanctions_list`
--
ALTER TABLE `sanctions_list`
  ADD CONSTRAINT `fk_sanctions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sanctions_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `screening_results`
--
ALTER TABLE `screening_results`
  ADD CONSTRAINT `fk_screening_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_screening_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_audit_logs`
--
ALTER TABLE `user_audit_logs`
  ADD CONSTRAINT `user_audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
