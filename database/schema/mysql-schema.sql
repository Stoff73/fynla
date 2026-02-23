/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `actuarial_life_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actuarial_life_tables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `age` tinyint unsigned NOT NULL,
  `gender` enum('male','female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `life_expectancy_years` decimal(4,2) NOT NULL,
  `probability_of_death` decimal(6,5) NOT NULL,
  `table_year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UK ONS National Life Tables',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_age_gender_year` (`age`,`gender`,`table_year`),
  KEY `idx_lookup` (`age`,`gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `asset_type` enum('property','pension','investment','business','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `liquidity` enum('liquid','semi_liquid','illiquid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'liquid',
  `is_giftable` tinyint(1) NOT NULL DEFAULT '1',
  `not_giftable_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_main_residence` tinyint(1) NOT NULL DEFAULT '0',
  `ownership_type` enum('individual','joint','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `beneficiary_designation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_iht_exempt` tinyint(1) NOT NULL DEFAULT '0',
  `exemption_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valuation_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assets_user_id_index` (`user_id`),
  CONSTRAINT `assets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_event_type_action_index` (`event_type`,`action`),
  KEY `audit_logs_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bequests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bequests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `will_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `beneficiary_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `beneficiary_user_id` bigint unsigned DEFAULT NULL,
  `beneficiary_type` enum('individual','charity','trust','organization') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `charity_registration_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bequest_type` enum('percentage','specific_amount','specific_asset','residuary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `percentage_of_estate` decimal(5,2) DEFAULT NULL,
  `specific_amount` decimal(15,2) DEFAULT NULL,
  `specific_asset_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `priority_order` int NOT NULL DEFAULT '1',
  `conditions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bequests_user_id_foreign` (`user_id`),
  KEY `bequests_beneficiary_user_id_foreign` (`beneficiary_user_id`),
  KEY `bequests_will_priority_idx` (`will_id`,`priority_order`),
  CONSTRAINT `bequests_beneficiary_user_id_foreign` FOREIGN KEY (`beneficiary_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bequests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bequests_will_id_foreign` FOREIGN KEY (`will_id`) REFERENCES `wills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_interests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_interests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `trust_id` bigint unsigned DEFAULT NULL,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Companies House registration number',
  `business_type` enum('sole_trader','partnership','limited_company','llp','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `ownership_type` enum('individual','joint','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where business is located',
  `vat_registered` tinyint(1) NOT NULL DEFAULT '0',
  `vat_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `utr_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Unique Tax Reference for Self Assessment',
  `tax_year_end` date DEFAULT NULL COMMENT 'Company financial year-end date',
  `employee_count` int unsigned NOT NULL DEFAULT '0',
  `paye_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PAYE scheme reference',
  `trading_status` enum('trading','dormant','pre_trading') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trading',
  `acquisition_date` date DEFAULT NULL COMMENT 'Date business was acquired for BADR calculation',
  `acquisition_cost` decimal(15,2) DEFAULT NULL COMMENT 'Original investment/cost basis',
  `bpr_eligible` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Business Property Relief eligible for IHT',
  `industry_sector` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_valuation` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valuation_date` date DEFAULT NULL,
  `valuation_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g., Market value, Book value, Expert valuation',
  `annual_revenue` decimal(15,2) DEFAULT NULL,
  `annual_profit` decimal(15,2) DEFAULT NULL,
  `annual_dividend_income` decimal(15,2) DEFAULT NULL COMMENT 'Dividend income received from this business',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `business_interests_user_id_index` (`user_id`),
  KEY `business_interests_household_id_index` (`household_id`),
  KEY `business_interests_trust_id_index` (`trust_id`),
  KEY `business_interests_business_type_index` (`business_type`),
  KEY `business_interests_joint_owner_id_index` (`joint_owner_id`),
  KEY `business_interests_ownership_type_idx` (`ownership_type`),
  KEY `business_interests_trading_status_idx` (`trading_status`),
  CONSTRAINT `business_interests_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `business_interests_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `business_interests_trust_id_foreign` FOREIGN KEY (`trust_id`) REFERENCES `trusts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `business_interests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `trust_id` bigint unsigned DEFAULT NULL,
  `account_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `institution_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` text COLLATE utf8mb4_unicode_ci,
  `sort_code` text COLLATE utf8mb4_unicode_ci,
  `account_type` enum('current_account','savings_account','cash_isa','fixed_term_deposit','ns_and_i','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` enum('emergency_fund','savings_goal','operating_cash','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ownership_type` enum('individual','joint','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where cash account is held',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `current_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `interest_rate` decimal(5,4) DEFAULT NULL COMMENT 'Annual interest rate as decimal',
  `rate_valid_until` date DEFAULT NULL,
  `is_isa` tinyint(1) NOT NULL DEFAULT '0',
  `isa_subscription_current_year` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_year` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g., 2024/25',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_accounts_user_id_index` (`user_id`),
  KEY `cash_accounts_household_id_index` (`household_id`),
  KEY `cash_accounts_trust_id_index` (`trust_id`),
  KEY `cash_accounts_account_type_index` (`account_type`),
  KEY `cash_accounts_is_isa_index` (`is_isa`),
  KEY `cash_accounts_ownership_type_idx` (`ownership_type`),
  KEY `cash_accounts_joint_owner_id_index` (`joint_owner_id`),
  KEY `cash_accounts_user_id_account_type_index` (`user_id`,`account_type`),
  CONSTRAINT `cash_accounts_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_accounts_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_accounts_trust_id_foreign` FOREIGN KEY (`trust_id`) REFERENCES `trusts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chattels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chattels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `joint_owner_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `trust_id` bigint unsigned DEFAULT NULL,
  `chattel_type` enum('vehicle','art','antique','jewelry','collectible','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ownership_type` enum('individual','joint','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where chattel is located',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `current_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valuation_date` date DEFAULT NULL,
  `make` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Vehicle make',
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Vehicle model',
  `year` year DEFAULT NULL COMMENT 'Vehicle year',
  `registration_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Vehicle registration',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chattels_user_id_index` (`user_id`),
  KEY `chattels_household_id_index` (`household_id`),
  KEY `chattels_trust_id_index` (`trust_id`),
  KEY `chattels_chattel_type_index` (`chattel_type`),
  KEY `chattels_joint_owner_id_index` (`joint_owner_id`),
  KEY `chattels_ownership_type_idx` (`ownership_type`),
  CONSTRAINT `chattels_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chattels_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chattels_trust_id_foreign` FOREIGN KEY (`trust_id`) REFERENCES `trusts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chattels_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `critical_illness_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `critical_illness_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `policy_type` enum('standalone','accelerated','additional') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standalone',
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sum_assured` decimal(15,2) DEFAULT NULL,
  `premium_amount` decimal(10,2) DEFAULT NULL,
  `premium_frequency` enum('monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `policy_start_date` date DEFAULT NULL,
  `policy_end_date` date DEFAULT NULL,
  `policy_term_years` int DEFAULT NULL,
  `conditions_covered` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `critical_illness_policies_user_id_index` (`user_id`),
  KEY `ci_policies_user_type_idx` (`user_id`,`policy_type`),
  CONSTRAINT `critical_illness_policies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `format` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'json',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data_exports_user_id_status_index` (`user_id`,`status`),
  KEY `data_exports_expires_at_index` (`expires_at`),
  CONSTRAINT `data_exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `db_pensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `db_pensions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `scheme_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheme_type` enum('final_salary','career_average','public_sector') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `accrued_annual_pension` decimal(15,2) DEFAULT NULL,
  `pensionable_service_years` decimal(5,2) DEFAULT NULL,
  `pensionable_salary` decimal(10,2) DEFAULT NULL,
  `normal_retirement_age` int DEFAULT NULL,
  `revaluation_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_pension_percent` decimal(5,2) DEFAULT NULL,
  `lump_sum_entitlement` decimal(15,2) DEFAULT NULL,
  `inflation_protection` enum('cpi','rpi','fixed','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `db_pensions_user_id_index` (`user_id`),
  CONSTRAINT `db_pensions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dc_pensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dc_pensions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `scheme_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheme_type` enum('workplace','sipp','personal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pension_type` enum('occupational','sipp','personal','stakeholder') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'occupational',
  `member_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_fund_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `annual_salary` decimal(10,2) DEFAULT NULL,
  `employee_contribution_percent` decimal(5,2) DEFAULT NULL,
  `employer_contribution_percent` decimal(5,2) DEFAULT NULL,
  `employer_matching_limit` decimal(5,2) DEFAULT NULL,
  `monthly_contribution_amount` decimal(10,2) DEFAULT NULL,
  `lump_sum_contribution` decimal(15,2) DEFAULT NULL,
  `investment_strategy` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_fee_percent` decimal(5,4) DEFAULT NULL,
  `retirement_age` int DEFAULT NULL,
  `expected_return_percent` decimal(5,2) DEFAULT NULL,
  `projected_value_at_retirement` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `risk_preference` enum('low','lower_medium','medium','upper_medium','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_custom_risk` tinyint(1) NOT NULL DEFAULT '0',
  `beneficiary_id` bigint unsigned DEFAULT NULL,
  `beneficiary_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_flexibly_accessed` tinyint(1) NOT NULL DEFAULT '0',
  `flexible_access_date` date DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dc_pensions_user_id_index` (`user_id`),
  KEY `dc_pensions_beneficiary_id_index` (`beneficiary_id`),
  CONSTRAINT `dc_pensions_beneficiary_id_foreign` FOREIGN KEY (`beneficiary_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dc_pensions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `disability_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disability_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `benefit_amount` decimal(10,2) NOT NULL,
  `benefit_frequency` enum('monthly','weekly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `deferred_period_weeks` int DEFAULT NULL,
  `benefit_period_months` int DEFAULT NULL,
  `premium_amount` decimal(10,2) DEFAULT NULL,
  `premium_frequency` enum('monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `occupation_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_start_date` date DEFAULT NULL,
  `policy_end_date` date DEFAULT NULL,
  `policy_term_years` int DEFAULT NULL,
  `coverage_type` enum('accident_only','accident_and_sickness') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'accident_and_sickness',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `disability_policies_user_id_index` (`user_id`),
  CONSTRAINT `disability_policies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_extraction_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_extraction_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` enum('uploaded','extraction_started','extraction_completed','extraction_failed','fields_modified','confirmed','saved_to_model','deleted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_extraction_logs_document_id_action_index` (`document_id`,`action`),
  KEY `document_extraction_logs_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `document_extraction_logs_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_extraction_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_extractions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_extractions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint unsigned NOT NULL,
  `extraction_version` int NOT NULL DEFAULT '1',
  `model_used` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'claude-3-5-sonnet',
  `input_tokens` int DEFAULT NULL,
  `output_tokens` int DEFAULT NULL,
  `raw_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extracted_fields` json NOT NULL,
  `field_confidence` json NOT NULL,
  `warnings` json DEFAULT NULL,
  `target_model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_model_id` bigint unsigned DEFAULT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT '0',
  `validation_errors` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_extractions_document_id_extraction_version_index` (`document_id`,`extraction_version`),
  CONSTRAINT `document_extractions_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL,
  `document_type` enum('pension_statement','insurance_policy','investment_statement','mortgage_statement','savings_statement','property_document','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `detected_document_subtype` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detection_confidence` decimal(5,4) DEFAULT NULL,
  `status` enum('uploaded','processing','extracted','review_pending','confirmed','failed','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'uploaded',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `processed_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_user_id_status_index` (`user_id`,`status`),
  KEY `documents_user_id_document_type_index` (`user_id`,`document_type`),
  KEY `documents_user_created_idx` (`user_id`,`created_at`),
  CONSTRAINT `documents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `efficient_frontier_calculations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `efficient_frontier_calculations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `calculation_date` date NOT NULL,
  `holdings_snapshot` json NOT NULL,
  `frontier_points` json NOT NULL,
  `tangency_portfolio` json NOT NULL,
  `min_variance_portfolio` json NOT NULL,
  `current_portfolio_position` json NOT NULL,
  `risk_free_rate` decimal(5,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `efficient_frontier_calculations_user_id_calculation_date_index` (`user_id`,`calculation_date`),
  CONSTRAINT `efficient_frontier_calculations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `code` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resend_count` int NOT NULL DEFAULT '0',
  `failed_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_verification_codes_user_id_type_code_index` (`user_id`,`type`,`code`),
  CONSTRAINT `email_verification_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `erasure_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `erasure_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `data_categories_deleted` json DEFAULT NULL,
  `processed_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `erasure_requests_user_id_status_index` (`user_id`,`status`),
  KEY `erasure_requests_status_index` (`status`),
  CONSTRAINT `erasure_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expenditure_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenditure_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `monthly_housing` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monthly_utilities` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monthly_food` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monthly_transport` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monthly_insurance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monthly_loans` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monthly_discretionary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_monthly_expenditure` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenditure_profiles_user_id_index` (`user_id`),
  CONSTRAINT `expenditure_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `factor_exposures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `factor_exposures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `holding_id` bigint unsigned DEFAULT NULL,
  `analysis_date` date NOT NULL,
  `market_beta` decimal(6,4) DEFAULT NULL,
  `alpha` decimal(6,4) DEFAULT NULL,
  `r_squared` decimal(5,4) DEFAULT NULL,
  `value_factor` decimal(6,4) DEFAULT NULL,
  `size_factor` decimal(6,4) DEFAULT NULL,
  `momentum_factor` decimal(6,4) DEFAULT NULL,
  `quality_factor` decimal(6,4) DEFAULT NULL,
  `low_vol_factor` decimal(6,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `factor_exposures_user_id_analysis_date_index` (`user_id`,`analysis_date`),
  KEY `factor_exposures_holding_id_foreign` (`holding_id`),
  CONSTRAINT `factor_exposures_holding_id_foreign` FOREIGN KEY (`holding_id`) REFERENCES `holdings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `factor_exposures_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `family_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `linked_user_id` bigint unsigned DEFAULT NULL,
  `relationship` enum('spouse','child','parent','other_dependent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other_dependent',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `national_insurance_number` text COLLATE utf8mb4_unicode_ci,
  `annual_income` decimal(15,2) DEFAULT NULL,
  `is_dependent` tinyint(1) NOT NULL DEFAULT '0',
  `education_status` enum('pre_school','primary','secondary','further_education','higher_education','graduated','not_applicable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receives_child_benefit` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `family_members_user_id_index` (`user_id`),
  KEY `family_members_household_id_index` (`household_id`),
  KEY `family_members_relationship_index` (`relationship`),
  KEY `family_members_user_relationship_idx` (`user_id`,`relationship`),
  KEY `family_members_linked_user_id_index` (`linked_user_id`),
  CONSTRAINT `family_members_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE,
  CONSTRAINT `family_members_linked_user_id_foreign` FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `family_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `gift_date` date DEFAULT NULL,
  `recipient` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gift_type` enum('pet','clt','exempt','small_gift','annual_exemption') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exempt',
  `gift_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('within_7_years','survived_7_years') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'within_7_years',
  `taper_relief_applicable` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gifts_user_id_index` (`user_id`),
  KEY `gifts_gift_date_index` (`gift_date`),
  KEY `gifts_user_gift_date_idx` (`user_id`,`gift_date`),
  CONSTRAINT `gifts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goal_contributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_contributions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `goal_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `contribution_date` date NOT NULL,
  `contribution_type` enum('manual','automatic','lump_sum','interest','adjustment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `goal_balance_after` decimal(15,2) NOT NULL,
  `streak_qualifying` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goal_contributions_goal_id_contribution_date_index` (`goal_id`,`contribution_date`),
  KEY `goal_contributions_user_id_contribution_date_index` (`user_id`,`contribution_date`),
  CONSTRAINT `goal_contributions_goal_id_foreign` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goal_contributions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goal_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_dependencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `goal_id` bigint unsigned NOT NULL,
  `depends_on_goal_id` bigint unsigned NOT NULL,
  `dependency_type` enum('blocks','funds','prerequisite') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prerequisite' COMMENT 'blocks: must complete first; funds: proceeds fund this goal; prerequisite: informational ordering',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `goal_dep_unique` (`goal_id`,`depends_on_goal_id`),
  KEY `goal_dep_reverse_idx` (`depends_on_goal_id`),
  CONSTRAINT `goal_dependencies_depends_on_goal_id_foreign` FOREIGN KEY (`depends_on_goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goal_dependencies_goal_id_foreign` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `goals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `goal_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `goal_type` enum('emergency_fund','property_purchase','home_deposit','education','retirement','wealth_accumulation','wedding','holiday','car_purchase','debt_repayment','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `custom_goal_type_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `target_amount` decimal(15,2) NOT NULL,
  `current_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `target_date` date NOT NULL,
  `start_date` date DEFAULT NULL,
  `assigned_module` enum('savings','investment','property','retirement') COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_override` tinyint(1) NOT NULL DEFAULT '0',
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `is_essential` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','paused','completed','abandoned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `monthly_contribution` decimal(12,2) DEFAULT NULL,
  `contribution_frequency` enum('weekly','monthly','quarterly','annually') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `contribution_streak` int unsigned NOT NULL DEFAULT '0',
  `longest_streak` int unsigned NOT NULL DEFAULT '0',
  `last_contribution_date` date DEFAULT NULL,
  `linked_account_ids` json DEFAULT NULL,
  `linked_savings_account_id` bigint unsigned DEFAULT NULL,
  `linked_investment_account_id` bigint unsigned DEFAULT NULL,
  `risk_preference` tinyint unsigned DEFAULT NULL,
  `use_global_risk_profile` tinyint(1) NOT NULL DEFAULT '1',
  `ownership_type` enum('individual','joint') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `show_in_projection` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_household_view` tinyint(1) NOT NULL DEFAULT '1',
  `property_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_type` enum('house','flat','bungalow','terraced','semi_detached','detached','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_first_time_buyer` tinyint(1) DEFAULT NULL,
  `estimated_property_price` decimal(15,2) DEFAULT NULL,
  `deposit_percentage` decimal(5,2) DEFAULT NULL,
  `stamp_duty_estimate` decimal(12,2) DEFAULT NULL,
  `additional_costs_estimate` decimal(12,2) DEFAULT NULL,
  `milestones` json DEFAULT NULL,
  `projection_data` json DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completion_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goals_linked_savings_account_id_foreign` (`linked_savings_account_id`),
  KEY `goals_user_id_status_index` (`user_id`,`status`),
  KEY `goals_user_id_assigned_module_index` (`user_id`,`assigned_module`),
  KEY `goals_user_id_goal_type_index` (`user_id`,`goal_type`),
  KEY `goals_joint_owner_id_status_index` (`joint_owner_id`,`status`),
  KEY `goals_linked_investment_account_id_foreign` (`linked_investment_account_id`),
  CONSTRAINT `goals_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goals_linked_investment_account_id_foreign` FOREIGN KEY (`linked_investment_account_id`) REFERENCES `investment_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goals_linked_savings_account_id_foreign` FOREIGN KEY (`linked_savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holdings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holdings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `holdable_id` bigint unsigned NOT NULL,
  `holdable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_type` enum('equity','bond','fund','etf','alternative','uk_equity','us_equity','international_equity','cash','property') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `allocation_percent` decimal(5,2) DEFAULT NULL,
  `security_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticker` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(15,6) DEFAULT NULL,
  `purchase_price` decimal(15,4) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `current_price` decimal(15,4) DEFAULT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `cost_basis` decimal(15,2) DEFAULT NULL,
  `dividend_yield` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `ocf_percent` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `holdings_asset_type_index` (`asset_type`),
  KEY `holdings_holdable_type_holdable_id_index` (`holdable_type`,`holdable_id`),
  KEY `holdings_holdable_id_type_idx` (`holdable_id`,`holdable_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `households`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `households` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `household_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `iht_calculations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iht_calculations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `user_gross_assets` decimal(15,2) NOT NULL DEFAULT '0.00',
  `spouse_gross_assets` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_gross_assets` decimal(15,2) NOT NULL DEFAULT '0.00',
  `user_total_liabilities` decimal(15,2) NOT NULL DEFAULT '0.00',
  `spouse_total_liabilities` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_liabilities` decimal(15,2) NOT NULL DEFAULT '0.00',
  `user_net_estate` decimal(15,2) NOT NULL DEFAULT '0.00',
  `spouse_net_estate` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_net_estate` decimal(15,2) NOT NULL DEFAULT '0.00',
  `nrb_available` decimal(15,2) NOT NULL DEFAULT '0.00',
  `nrb_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rnrb_available` decimal(15,2) NOT NULL DEFAULT '0.00',
  `rnrb_status` enum('full','tapered','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `rnrb_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_allowances` decimal(15,2) NOT NULL DEFAULT '0.00',
  `taxable_estate` decimal(15,2) NOT NULL DEFAULT '0.00',
  `iht_liability` decimal(15,2) NOT NULL DEFAULT '0.00',
  `effective_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `projected_gross_assets` decimal(15,2) NOT NULL DEFAULT '0.00',
  `projected_liabilities` decimal(15,2) NOT NULL DEFAULT '0.00',
  `projected_net_estate` decimal(15,2) NOT NULL DEFAULT '0.00',
  `projected_taxable_estate` decimal(15,2) NOT NULL DEFAULT '0.00',
  `projected_iht_liability` decimal(15,2) NOT NULL DEFAULT '0.00',
  `projected_cash` decimal(15,2) DEFAULT NULL,
  `projected_investments` decimal(15,2) DEFAULT NULL,
  `projected_properties` decimal(15,2) DEFAULT NULL,
  `retirement_age` smallint unsigned DEFAULT NULL,
  `result_json` json DEFAULT NULL,
  `years_to_death` smallint unsigned NOT NULL DEFAULT '0',
  `estimated_age_at_death` tinyint unsigned NOT NULL DEFAULT '0',
  `calculation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_married` tinyint(1) NOT NULL DEFAULT '0',
  `data_sharing_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `assets_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `liabilities_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `iht_calculations_user_id_calculation_date_index` (`user_id`,`calculation_date`),
  KEY `iht_calculations_assets_hash_index` (`assets_hash`),
  KEY `iht_calculations_liabilities_hash_index` (`liabilities_hash`),
  CONSTRAINT `iht_calculations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `iht_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iht_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `marital_status` enum('single','married','widowed','divorced') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_spouse` tinyint(1) NOT NULL DEFAULT '0',
  `own_home` tinyint(1) NOT NULL DEFAULT '0',
  `home_value` decimal(15,2) DEFAULT NULL,
  `nrb_transferred_from_spouse` decimal(15,2) NOT NULL DEFAULT '0.00',
  `rnrb_transferred_from_spouse` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Residence Nil Rate Band transferred from deceased spouse',
  `charitable_giving_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `iht_profiles_user_id_index` (`user_id`),
  CONSTRAINT `iht_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `income_protection_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `income_protection_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `benefit_amount` decimal(10,2) NOT NULL,
  `benefit_frequency` enum('monthly','weekly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `deferred_period_weeks` int DEFAULT NULL,
  `benefit_period_months` int DEFAULT NULL,
  `premium_amount` decimal(10,2) DEFAULT NULL,
  `premium_frequency` enum('monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `occupation_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_start_date` date DEFAULT NULL,
  `policy_end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `income_protection_policies_user_id_index` (`user_id`),
  CONSTRAINT `income_protection_policies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investment_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `account_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `trust_id` bigint unsigned DEFAULT NULL,
  `ownership_type` enum('individual','joint','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `account_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_type_other` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_registration_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_trading_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_sector` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crowdfunding_platform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `investment_date` date DEFAULT NULL,
  `investment_amount` decimal(15,2) DEFAULT NULL,
  `investment_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBP',
  `funding_round` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pre_money_valuation` decimal(15,2) DEFAULT NULL,
  `post_money_valuation` decimal(15,2) DEFAULT NULL,
  `price_per_share` decimal(12,6) DEFAULT NULL,
  `number_of_shares` int DEFAULT NULL,
  `instrument_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `share_class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_voting_rights` tinyint(1) NOT NULL DEFAULT '1',
  `has_dividend_rights` tinyint(1) NOT NULL DEFAULT '1',
  `liquidation_preference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_anti_dilution` tinyint(1) NOT NULL DEFAULT '0',
  `holding_structure` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct',
  `nominee_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_terms` text COLLATE utf8mb4_unicode_ci,
  `interest_rate` decimal(5,2) DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `tax_relief_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eis3_certificate_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hmrc_reference` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relief_claimed_date` date DEFAULT NULL,
  `relief_amount_claimed` decimal(12,2) DEFAULT NULL,
  `disposal_restriction_date` date DEFAULT NULL,
  `clawback_risk` tinyint(1) NOT NULL DEFAULT '0',
  `clawback_notes` text COLLATE utf8mb4_unicode_ci,
  `latest_valuation` decimal(15,2) DEFAULT NULL,
  `latest_valuation_date` date DEFAULT NULL,
  `current_ownership_percent` decimal(5,4) DEFAULT NULL,
  `company_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `status_notes` text COLLATE utf8mb4_unicode_ci,
  `exit_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `exit_gross_proceeds` decimal(15,2) DEFAULT NULL,
  `exit_fees` decimal(12,2) DEFAULT NULL,
  `exit_net_proceeds` decimal(15,2) DEFAULT NULL,
  `exit_moic` decimal(6,2) DEFAULT NULL,
  `loss_relief_eligible` tinyint(1) NOT NULL DEFAULT '0',
  `capital_loss_amount` decimal(15,2) DEFAULT NULL,
  `negligible_value_claim` tinyint(1) NOT NULL DEFAULT '0',
  `employer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_registration` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_ticker` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_is_listed` tinyint(1) NOT NULL DEFAULT '0',
  `parent_company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_company_country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ers_scheme_reference` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ers_registered` tinyint(1) NOT NULL DEFAULT '0',
  `grant_date` date DEFAULT NULL,
  `grant_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `units_granted` int DEFAULT NULL,
  `exercise_price` decimal(12,4) DEFAULT NULL,
  `market_value_at_grant` decimal(12,4) DEFAULT NULL,
  `share_class_scheme` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grant_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBP',
  `option_price_paid` decimal(12,2) DEFAULT NULL,
  `scheme_start_date` date DEFAULT NULL,
  `scheme_duration_months` int DEFAULT NULL,
  `vesting_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliff_date` date DEFAULT NULL,
  `cliff_percentage` int DEFAULT NULL,
  `vesting_period_months` int DEFAULT NULL,
  `vesting_frequency_months` int DEFAULT NULL,
  `has_performance_conditions` tinyint(1) NOT NULL DEFAULT '0',
  `performance_conditions_description` text COLLATE utf8mb4_unicode_ci,
  `performance_period_end` date DEFAULT NULL,
  `performance_vesting_min_percent` int DEFAULT NULL,
  `performance_vesting_max_percent` int DEFAULT NULL,
  `full_vest_date` date DEFAULT NULL,
  `accelerated_vesting_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `units_vested` int NOT NULL DEFAULT '0',
  `units_unvested` int NOT NULL DEFAULT '0',
  `units_exercised` int NOT NULL DEFAULT '0',
  `units_forfeited` int NOT NULL DEFAULT '0',
  `units_expired` int NOT NULL DEFAULT '0',
  `scheme_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `current_share_price` decimal(12,4) DEFAULT NULL,
  `share_price_date` date DEFAULT NULL,
  `exercise_window_start` date DEFAULT NULL,
  `exercise_window_end` date DEFAULT NULL,
  `last_exercise_date` date DEFAULT NULL,
  `total_exercise_proceeds` decimal(15,2) DEFAULT NULL,
  `total_exercise_cost` decimal(15,2) DEFAULT NULL,
  `exercise_history_json` text COLLATE utf8mb4_unicode_ci,
  `tax_treatment` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_readily_convertible_asset` tinyint(1) DEFAULT NULL,
  `paye_via_payroll` tinyint(1) NOT NULL DEFAULT '1',
  `income_tax_at_vest_exercise` decimal(15,2) DEFAULT NULL,
  `ni_at_vest_exercise` decimal(15,2) DEFAULT NULL,
  `csop_disqualifying_event` tinyint(1) NOT NULL DEFAULT '0',
  `csop_three_year_date` date DEFAULT NULL,
  `cost_basis_for_cgt` decimal(15,2) DEFAULT NULL,
  `saye_monthly_savings` decimal(10,2) DEFAULT NULL,
  `saye_current_savings_balance` decimal(15,2) DEFAULT NULL,
  `saye_maturity_date` date DEFAULT NULL,
  `saye_option_discount_percent` decimal(5,2) DEFAULT NULL,
  `saye_bonus_amount` decimal(12,2) DEFAULT NULL,
  `leaver_category` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_termination_exercise_days` int DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `leaver_notes` text COLLATE utf8mb4_unicode_ci,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where account is held - hidden for ISAs',
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` text COLLATE utf8mb4_unicode_ci,
  `platform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_holdings_value` decimal(15,2) DEFAULT NULL,
  `contributions_ytd` decimal(15,2) DEFAULT '0.00',
  `monthly_contribution_amount` decimal(12,2) DEFAULT NULL COMMENT 'Regular monthly contribution amount',
  `contribution_frequency` enum('monthly','quarterly','annually') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly' COMMENT 'How often regular contributions are made',
  `planned_lump_sum_amount` decimal(12,2) DEFAULT NULL COMMENT 'One-off lump sum contribution planned',
  `planned_lump_sum_date` date DEFAULT NULL COMMENT 'Date when lump sum will be contributed',
  `tax_year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_fee_percent` decimal(5,4) DEFAULT '0.0000',
  `platform_fee_amount` decimal(10,2) DEFAULT NULL COMMENT 'Fixed fee amount when fee type is fixed',
  `platform_fee_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage' COMMENT 'Whether fee is percentage or fixed amount',
  `platform_fee_frequency` enum('monthly','quarterly','annually') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annually' COMMENT 'How often the fee is charged',
  `advisor_fee_percent` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `isa_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isa_subscription_current_year` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `risk_preference` enum('low','lower_medium','medium','upper_medium','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_custom_risk` tinyint(1) NOT NULL DEFAULT '0',
  `rebalance_threshold_percent` decimal(5,2) NOT NULL DEFAULT '10.00',
  `include_in_retirement` tinyint(1) NOT NULL DEFAULT '0',
  `bond_purchase_date` date DEFAULT NULL,
  `bond_withdrawal_taken` decimal(12,2) DEFAULT NULL,
  `badr_eligible` tinyint(1) NOT NULL DEFAULT '0',
  `badr_is_employee` tinyint(1) NOT NULL DEFAULT '0',
  `badr_trading_company` tinyint(1) NOT NULL DEFAULT '0',
  `badr_5_percent_holding` tinyint(1) NOT NULL DEFAULT '0',
  `badr_held_2_years` tinyint(1) NOT NULL DEFAULT '0',
  `badr_emi_shares` tinyint(1) NOT NULL DEFAULT '0',
  `badr_lifetime_used` decimal(12,2) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_accounts_user_id_index` (`user_id`),
  KEY `investment_accounts_user_id_account_type_index` (`user_id`,`account_type`),
  KEY `investment_accounts_user_id_tax_year_index` (`user_id`,`tax_year`),
  KEY `investment_accounts_household_id_index` (`household_id`),
  KEY `investment_accounts_trust_id_index` (`trust_id`),
  KEY `investment_accounts_joint_owner_id_index` (`joint_owner_id`),
  KEY `investment_accounts_ownership_type_idx` (`ownership_type`),
  CONSTRAINT `investment_accounts_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `investment_accounts_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `investment_accounts_trust_id_foreign` FOREIGN KEY (`trust_id`) REFERENCES `trusts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `investment_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investment_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_goals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `goal_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `goal_type` enum('retirement','education','wealth','home') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_amount` decimal(15,2) NOT NULL,
  `target_date` date NOT NULL,
  `priority` enum('high','medium','low') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `is_essential` tinyint(1) NOT NULL DEFAULT '0',
  `linked_account_ids` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_goals_user_id_index` (`user_id`),
  KEY `investment_goals_user_id_goal_type_index` (`user_id`,`goal_type`),
  CONSTRAINT `investment_goals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investment_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `plan_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_data` json NOT NULL,
  `portfolio_health_score` int NOT NULL,
  `is_complete` tinyint(1) NOT NULL DEFAULT '0',
  `completeness_score` int DEFAULT NULL,
  `generated_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_plans_user_id_generated_at_index` (`user_id`,`generated_at`),
  CONSTRAINT `investment_plans_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investment_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `investment_plan_id` bigint unsigned DEFAULT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_required` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `impact_level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `potential_saving` decimal(10,2) DEFAULT NULL,
  `estimated_effort` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `dismissal_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_recommendations_investment_plan_id_foreign` (`investment_plan_id`),
  KEY `investment_recommendations_user_id_status_index` (`user_id`,`status`),
  KEY `investment_recommendations_user_id_priority_index` (`user_id`,`priority`),
  CONSTRAINT `investment_recommendations_investment_plan_id_foreign` FOREIGN KEY (`investment_plan_id`) REFERENCES `investment_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `investment_recommendations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investment_scenarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_scenarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `scenario_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `scenario_type` enum('custom','template','comparison') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `template_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parameters` json NOT NULL,
  `results` json DEFAULT NULL,
  `comparison_data` json DEFAULT NULL,
  `status` enum('draft','running','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_saved` tinyint(1) NOT NULL DEFAULT '0',
  `monte_carlo_job_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_scenarios_user_id_status_index` (`user_id`,`status`),
  KEY `investment_scenarios_user_id_is_saved_index` (`user_id`,`is_saved`),
  KEY `investment_scenarios_monte_carlo_job_id_index` (`monte_carlo_job_id`),
  CONSTRAINT `investment_scenarios_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `isa_allowance_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `isa_allowance_tracking` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tax_year` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cash_isa_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stocks_shares_isa_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `lisa_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_allowance` decimal(10,2) NOT NULL DEFAULT '20000.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `isa_allowance_tracking_user_id_tax_year_unique` (`user_id`,`tax_year`),
  KEY `isa_tracking_tax_year_idx` (`tax_year`),
  CONSTRAINT `isa_allowance_tracking_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `joint_account_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `joint_account_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `joint_owner_id` bigint unsigned NOT NULL,
  `loggable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `loggable_id` bigint unsigned NOT NULL,
  `changes` json NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'update',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `joint_account_logs_loggable_type_loggable_id_index` (`loggable_type`,`loggable_id`),
  KEY `jal_user_loggable_idx` (`user_id`,`loggable_type`,`loggable_id`),
  KEY `jal_joint_owner_loggable_idx` (`joint_owner_id`,`loggable_type`,`loggable_id`),
  KEY `jal_created_at_idx` (`created_at`),
  CONSTRAINT `joint_account_logs_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `joint_account_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `letters_to_spouse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `letters_to_spouse` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `immediate_actions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `executor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `executor_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attorney_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attorney_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financial_advisor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financial_advisor_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accountant_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accountant_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `immediate_funds_access` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `employer_hr_contact` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_benefits_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `password_manager_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `phone_plan_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bank_accounts_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `investment_accounts_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `insurance_policies_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `real_estate_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vehicles_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `valuable_items_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cryptocurrency_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `liabilities_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recurring_bills_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `estate_documents_location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `beneficiary_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `children_education_plans` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `financial_guidance` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `social_security_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `funeral_preference` enum('burial','cremation','not_specified') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_specified',
  `funeral_service_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `obituary_wishes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `additional_wishes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `additional_boxes` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `letters_to_spouse_user_id_index` (`user_id`),
  CONSTRAINT `letters_to_spouse_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `liabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `liabilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ownership_type` enum('individual','joint','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `trust_id` bigint unsigned DEFAULT NULL,
  `liability_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where liability is held',
  `liability_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_balance` decimal(15,2) DEFAULT NULL,
  `monthly_payment` decimal(10,2) DEFAULT NULL,
  `interest_rate` decimal(8,4) DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `secured_against` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_priority_debt` tinyint(1) NOT NULL DEFAULT '0',
  `mortgage_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fixed_until` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `liabilities_user_id_index` (`user_id`),
  KEY `liabilities_joint_owner_id_index` (`joint_owner_id`),
  KEY `liabilities_trust_id_index` (`trust_id`),
  CONSTRAINT `liabilities_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `liabilities_trust_id_foreign` FOREIGN KEY (`trust_id`) REFERENCES `trusts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `liabilities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `life_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `event_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` enum('inheritance','gift_received','bonus','redundancy_payment','property_sale','business_sale','pension_lump_sum','lottery_windfall','large_purchase','home_improvement','wedding','education_fees','gift_given','medical_expense','custom_income','custom_expense') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `amount` decimal(15,2) NOT NULL,
  `impact_type` enum('income','expense') COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_date` date NOT NULL,
  `certainty` enum('confirmed','likely','possible','speculative') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'likely',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_in_projection` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_household_view` tinyint(1) NOT NULL DEFAULT '1',
  `ownership_type` enum('individual','joint') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `status` enum('expected','confirmed','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'expected',
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `life_events_joint_owner_id_foreign` (`joint_owner_id`),
  KEY `life_events_user_id_status_index` (`user_id`,`status`),
  KEY `life_events_user_id_expected_date_index` (`user_id`,`expected_date`),
  KEY `life_events_user_id_impact_type_index` (`user_id`,`impact_type`),
  CONSTRAINT `life_events_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`),
  CONSTRAINT `life_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `life_insurance_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_insurance_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `policy_type` enum('term','whole_of_life','decreasing_term','family_income_benefit','level_term') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'term',
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sum_assured` decimal(15,2) DEFAULT NULL,
  `start_value` decimal(15,2) DEFAULT NULL,
  `decreasing_rate` decimal(5,4) DEFAULT NULL,
  `premium_amount` decimal(10,2) DEFAULT NULL,
  `premium_frequency` enum('monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `policy_start_date` date DEFAULT NULL,
  `policy_term_years` int DEFAULT NULL,
  `policy_end_date` date DEFAULT NULL,
  `indexation_rate` decimal(5,4) DEFAULT '0.0000',
  `in_trust` tinyint(1) NOT NULL DEFAULT '0',
  `is_mortgage_protection` tinyint(1) NOT NULL DEFAULT '0',
  `beneficiaries` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `life_insurance_policies_user_id_index` (`user_id`),
  KEY `life_policies_user_type_idx` (`user_id`,`policy_type`),
  CONSTRAINT `life_insurance_policies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `successful` tinyint(1) NOT NULL DEFAULT '0',
  `failure_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `login_attempts_email_created_at_index` (`email`,`created_at`),
  KEY `login_attempts_ip_address_created_at_index` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `monte_carlo_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monte_carlo_cache` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `results` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculated_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monte_carlo_cache_cache_key_unique` (`cache_key`),
  KEY `monte_carlo_cache_cache_key_index` (`cache_key`),
  KEY `monte_carlo_cache_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mortgages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mortgages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where mortgaged property is located',
  `user_id` bigint unsigned NOT NULL,
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `joint_owner_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lender_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mortgage_account_number` text COLLATE utf8mb4_unicode_ci,
  `mortgage_type` enum('repayment','interest_only','mixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `repayment_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of mortgage on repayment basis (0-100)',
  `interest_only_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of mortgage on interest-only basis (0-100)',
  `original_loan_amount` decimal(15,2) DEFAULT NULL,
  `outstanding_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `interest_rate` decimal(8,4) DEFAULT NULL,
  `rate_type` enum('fixed','variable','tracker','discount','mixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `fixed_rate_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of mortgage at fixed rate (0-100)',
  `variable_rate_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of mortgage at variable rate (0-100)',
  `fixed_interest_rate` decimal(5,4) DEFAULT NULL COMMENT 'Interest rate for fixed portion (annual rate as decimal)',
  `variable_interest_rate` decimal(5,4) DEFAULT NULL COMMENT 'Interest rate for variable portion (annual rate as decimal)',
  `rate_fix_end_date` date DEFAULT NULL COMMENT 'Date when fixed rate ends',
  `monthly_payment` decimal(10,2) DEFAULT NULL,
  `monthly_interest_portion` decimal(10,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `remaining_term_months` int NOT NULL DEFAULT '0',
  `ownership_type` enum('individual','joint','tenants_in_common','trust') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mortgages_property_id_index` (`property_id`),
  KEY `mortgages_user_id_index` (`user_id`),
  KEY `mortgages_mortgage_type_index` (`mortgage_type`),
  KEY `mortgages_joint_owner_id_index` (`joint_owner_id`),
  KEY `mortgages_start_date_idx` (`start_date`),
  CONSTRAINT `mortgages_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mortgages_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mortgages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `net_worth_statements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `net_worth_statements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `statement_date` date NOT NULL,
  `total_assets` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_liabilities` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_worth` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `net_worth_statements_user_id_index` (`user_id`),
  KEY `net_worth_statements_user_date_idx` (`user_id`,`statement_date`),
  CONSTRAINT `net_worth_statements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `occupation_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `occupation_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `soc_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SOC 2020 4-digit unit group code',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Job title or occupation name',
  `unit_group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOC 2020 unit group description',
  `minor_group` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOC 2020 minor group (3-digit)',
  `sub_major_group` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOC 2020 sub-major group (2-digit)',
  `major_group` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOC 2020 major group (1-digit)',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is this the primary title for the SOC code',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `occupation_codes_soc_code_index` (`soc_code`),
  KEY `occupation_codes_title_index` (`title`),
  FULLTEXT KEY `occupation_codes_title_fulltext` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_progress` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `focus_area` enum('estate','protection','retirement','investment','tax_optimisation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_data` json DEFAULT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT '0',
  `skipped` tinyint(1) NOT NULL DEFAULT '0',
  `skip_reason_shown` tinyint(1) NOT NULL DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `onboarding_progress_user_id_focus_area_index` (`user_id`,`focus_area`),
  KEY `onboarding_progress_user_id_step_name_index` (`user_id`,`step_name`),
  CONSTRAINT `onboarding_progress_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_code` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_code_resend_count` tinyint unsigned NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `mfa_verified_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_reset_sessions_token_unique` (`token`),
  KEY `password_reset_sessions_token_expires_at_index` (`token`,`expires_at`),
  KEY `password_reset_sessions_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `password_reset_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `revolut_order_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBP',
  `status` enum('pending','completed','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `revolut_payment_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_subscription_id_foreign` (`subscription_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  CONSTRAINT `payments_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pending_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_code` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `registration_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preview_persona_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_cycle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pending_registrations_email_unique` (`email`),
  KEY `pending_registrations_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `account_type` enum('profit_and_loss','cashflow','balance_sheet') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `line_item` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., Employment Income, Mortgage Payment, Cash in Bank',
  `category` enum('income','expense','asset','liability','equity','cash_inflow','cash_outflow') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personal_accounts_user_id_index` (`user_id`),
  KEY `personal_accounts_account_type_index` (`account_type`),
  KEY `personal_accounts_period_start_period_end_index` (`period_start`,`period_end`),
  CONSTRAINT `personal_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portfolio_optimizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portfolio_optimizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `optimization_date` date NOT NULL,
  `optimization_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_allocation` json NOT NULL,
  `optimal_allocation` json NOT NULL,
  `rebalancing_actions` json NOT NULL,
  `constraints_used` json NOT NULL,
  `expected_return` decimal(6,4) DEFAULT NULL,
  `expected_risk` decimal(6,4) DEFAULT NULL,
  `expected_sharpe` decimal(6,4) DEFAULT NULL,
  `improvement_vs_current` decimal(6,4) DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `portfolio_optimizations_user_id_optimization_date_index` (`user_id`,`optimization_date`),
  CONSTRAINT `portfolio_optimizations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `properties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `joint_owner_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Joint owner name - used when joint owner not in system',
  `household_id` bigint unsigned DEFAULT NULL,
  `trust_id` bigint unsigned DEFAULT NULL,
  `trust_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Trust name - used when trust not formally registered in system',
  `property_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ownership_type` enum('individual','joint','tenants_in_common','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'individual',
  `joint_ownership_type` enum('joint_tenancy','tenants_in_common') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Type of joint ownership - only applicable when ownership_type is joint',
  `tenure_type` enum('freehold','leasehold') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'freehold' COMMENT 'Property tenure type',
  `lease_remaining_years` int unsigned DEFAULT NULL COMMENT 'Remaining years on lease - only for leasehold properties',
  `lease_expiry_date` date DEFAULT NULL COMMENT 'Lease expiry date - only for leasehold properties',
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `address_line_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `current_value` decimal(15,2) DEFAULT NULL,
  `valuation_date` date DEFAULT NULL,
  `sdlt_paid` decimal(15,2) DEFAULT NULL COMMENT 'Stamp Duty Land Tax paid',
  `monthly_rental_income` decimal(10,2) DEFAULT NULL,
  `outstanding_mortgage` decimal(15,2) DEFAULT NULL,
  `mortgages_count` tinyint unsigned NOT NULL DEFAULT '0',
  `total_mortgage_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tenant_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tenant_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `managing_agent_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `managing_agent_company` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `managing_agent_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `managing_agent_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `managing_agent_fee` decimal(10,2) DEFAULT NULL COMMENT 'Management fee amount or percentage',
  `lease_start_date` date DEFAULT NULL,
  `lease_end_date` date DEFAULT NULL,
  `monthly_council_tax` decimal(10,2) DEFAULT NULL,
  `monthly_gas` decimal(10,2) DEFAULT NULL,
  `monthly_electricity` decimal(10,2) DEFAULT NULL,
  `monthly_water` decimal(10,2) DEFAULT NULL,
  `monthly_building_insurance` decimal(10,2) DEFAULT NULL,
  `monthly_contents_insurance` decimal(10,2) DEFAULT NULL,
  `monthly_service_charge` decimal(10,2) DEFAULT NULL,
  `monthly_maintenance_reserve` decimal(10,2) DEFAULT NULL,
  `other_monthly_costs` decimal(10,2) DEFAULT NULL,
  `annual_service_charge` decimal(10,2) DEFAULT NULL,
  `annual_ground_rent` decimal(10,2) DEFAULT NULL,
  `annual_insurance` decimal(10,2) DEFAULT NULL,
  `annual_maintenance_reserve` decimal(10,2) DEFAULT NULL,
  `other_annual_costs` decimal(10,2) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `properties_user_id_index` (`user_id`),
  KEY `properties_household_id_index` (`household_id`),
  KEY `properties_trust_id_index` (`trust_id`),
  KEY `properties_property_type_index` (`property_type`),
  KEY `properties_ownership_type_index` (`ownership_type`),
  KEY `properties_joint_owner_id_index` (`joint_owner_id`),
  KEY `properties_user_id_property_type_index` (`user_id`,`property_type`),
  CONSTRAINT `properties_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `properties_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `properties_trust_id_foreign` FOREIGN KEY (`trust_id`) REFERENCES `trusts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `properties_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `protection_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `protection_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `annual_income` decimal(15,2) NOT NULL,
  `monthly_expenditure` decimal(10,2) NOT NULL,
  `mortgage_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `other_debts` decimal(15,2) NOT NULL DEFAULT '0.00',
  `number_of_dependents` int NOT NULL DEFAULT '0',
  `dependents_ages` json DEFAULT NULL,
  `retirement_age` int NOT NULL DEFAULT '67',
  `occupation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smoker_status` tinyint(1) NOT NULL DEFAULT '0',
  `health_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good',
  `has_no_policies` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `protection_profiles_user_id_unique` (`user_id`),
  KEY `protection_profiles_user_id_index` (`user_id`),
  CONSTRAINT `protection_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rebalancing_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rebalancing_actions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `holding_id` bigint unsigned DEFAULT NULL,
  `investment_account_id` bigint unsigned DEFAULT NULL,
  `action_type` enum('buy','sell') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `security_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticker` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shares_to_trade` decimal(15,6) NOT NULL,
  `trade_value` decimal(15,2) NOT NULL,
  `current_price` decimal(15,4) NOT NULL,
  `current_holding` decimal(15,6) NOT NULL DEFAULT '0.000000',
  `target_value` decimal(15,2) NOT NULL,
  `target_weight` decimal(5,4) NOT NULL,
  `priority` int NOT NULL DEFAULT '5',
  `rationale` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cgt_cost_basis` decimal(15,2) DEFAULT NULL,
  `cgt_gain_or_loss` decimal(15,2) DEFAULT NULL,
  `cgt_liability` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','executed','cancelled','expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `executed_at` timestamp NULL DEFAULT NULL,
  `executed_price` decimal(15,4) DEFAULT NULL,
  `executed_shares` decimal(15,6) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rebalancing_actions_holding_id_foreign` (`holding_id`),
  KEY `rebalancing_actions_investment_account_id_foreign` (`investment_account_id`),
  KEY `rebalancing_actions_user_id_status_index` (`user_id`,`status`),
  KEY `rebalancing_actions_user_id_action_type_index` (`user_id`,`action_type`),
  KEY `rebalancing_actions_action_type_index` (`action_type`),
  KEY `rebalancing_actions_status_index` (`status`),
  CONSTRAINT `rebalancing_actions_holding_id_foreign` FOREIGN KEY (`holding_id`) REFERENCES `holdings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rebalancing_actions_investment_account_id_foreign` FOREIGN KEY (`investment_account_id`) REFERENCES `investment_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rebalancing_actions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recommendation_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recommendation_tracking` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `recommendation_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recommendation_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority_score` decimal(5,2) NOT NULL DEFAULT '50.00',
  `timeline` enum('immediate','short_term','medium_term','long_term') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium_term',
  `status` enum('pending','in_progress','completed','dismissed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recommendation_tracking_user_id_status_index` (`user_id`,`status`),
  KEY `recommendation_tracking_user_id_module_index` (`user_id`,`module`),
  KEY `recommendation_tracking_recommendation_id_index` (`recommendation_id`),
  KEY `rec_tracking_user_completed_idx` (`user_id`,`completed_at`),
  KEY `rec_tracking_timeline_idx` (`user_id`,`timeline`),
  CONSTRAINT `recommendation_tracking_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retirement_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `retirement_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `current_age` int NOT NULL,
  `target_retirement_age` int NOT NULL,
  `current_annual_salary` decimal(15,2) DEFAULT NULL,
  `target_retirement_income` decimal(15,2) DEFAULT NULL,
  `essential_expenditure` decimal(10,2) DEFAULT NULL,
  `lifestyle_expenditure` decimal(10,2) DEFAULT NULL,
  `life_expectancy` int DEFAULT NULL,
  `prior_year_unused_allowance` json DEFAULT NULL,
  `spouse_life_expectancy` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `retirement_profiles_user_id_index` (`user_id`),
  CONSTRAINT `retirement_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `risk_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `risk_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `calculation_date` date NOT NULL,
  `portfolio_value` decimal(15,2) NOT NULL,
  `var_95_1month` decimal(15,2) DEFAULT NULL,
  `cvar_95_1month` decimal(15,2) DEFAULT NULL,
  `var_99_1month` decimal(15,2) DEFAULT NULL,
  `cvar_99_1month` decimal(15,2) DEFAULT NULL,
  `max_drawdown` decimal(5,2) DEFAULT NULL,
  `current_drawdown` decimal(5,2) DEFAULT NULL,
  `sharpe_ratio` decimal(6,4) DEFAULT NULL,
  `sortino_ratio` decimal(6,4) DEFAULT NULL,
  `calmar_ratio` decimal(6,4) DEFAULT NULL,
  `information_ratio` decimal(6,4) DEFAULT NULL,
  `treynor_ratio` decimal(6,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_metrics_user_id_calculation_date_index` (`user_id`,`calculation_date`),
  CONSTRAINT `risk_metrics_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `risk_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `risk_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `risk_tolerance` enum('cautious','balanced','adventurous') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risk_level` enum('low','lower_medium','medium','upper_medium','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity_for_loss_percent` decimal(5,2) DEFAULT NULL,
  `time_horizon_years` int DEFAULT NULL,
  `knowledge_level` enum('novice','intermediate','experienced') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attitude_to_volatility` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `esg_preference` tinyint(1) NOT NULL DEFAULT '0',
  `risk_assessed_at` timestamp NULL DEFAULT NULL,
  `is_self_assessed` tinyint(1) NOT NULL DEFAULT '1',
  `factor_breakdown` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_profiles_user_id_index` (`user_id`),
  CONSTRAINT `risk_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permission` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permission_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permission_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `savings_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `account_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `joint_owner_id` bigint DEFAULT NULL,
  `beneficiary_id` bigint unsigned DEFAULT NULL,
  `beneficiary_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `beneficiary_dob` date DEFAULT NULL,
  `include_in_retirement` tinyint(1) NOT NULL DEFAULT '0',
  `ownership_type` enum('individual','joint','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `account_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `interest_rate` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `access_type` enum('immediate','notice','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'immediate',
  `notice_period_days` int DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `is_isa` tinyint(1) NOT NULL DEFAULT '0',
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where account is held - hidden when is_isa = true',
  `is_emergency_fund` tinyint(1) NOT NULL DEFAULT '0',
  `isa_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isa_subscription_year` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isa_subscription_amount` decimal(15,2) DEFAULT NULL,
  `regular_contribution_amount` decimal(12,2) DEFAULT NULL,
  `contribution_frequency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `planned_lump_sum_amount` decimal(12,2) DEFAULT NULL,
  `planned_lump_sum_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_accounts_user_id_index` (`user_id`),
  KEY `savings_accounts_ownership_type_index` (`ownership_type`),
  KEY `savings_accounts_joint_owner_id_index` (`joint_owner_id`),
  KEY `savings_accounts_institution_idx` (`institution`),
  KEY `savings_accounts_beneficiary_id_index` (`beneficiary_id`),
  KEY `savings_accounts_user_id_account_type_index` (`user_id`,`account_type`),
  CONSTRAINT `savings_accounts_beneficiary_id_foreign` FOREIGN KEY (`beneficiary_id`) REFERENCES `family_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `savings_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `savings_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_goals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `goal_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_amount` decimal(15,2) DEFAULT NULL,
  `current_saved` decimal(15,2) NOT NULL DEFAULT '0.00',
  `target_date` date DEFAULT NULL,
  `priority` enum('high','medium','low') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `linked_account_id` bigint unsigned DEFAULT NULL,
  `auto_transfer_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_goals_linked_account_id_foreign` (`linked_account_id`),
  KEY `savings_goals_user_id_index` (`user_id`),
  CONSTRAINT `savings_goals_linked_account_id_foreign` FOREIGN KEY (`linked_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `savings_goals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `savings_market_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_market_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rate_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(5,4) NOT NULL,
  `tax_year` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_from` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `savings_market_rates_rate_key_tax_year_unique` (`rate_key`,`tax_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sickness_illness_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sickness_illness_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `benefit_amount` decimal(10,2) NOT NULL,
  `benefit_frequency` enum('monthly','weekly','lump_sum') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lump_sum',
  `deferred_period_weeks` int DEFAULT NULL,
  `benefit_period_months` int DEFAULT NULL,
  `premium_amount` decimal(10,2) DEFAULT NULL,
  `premium_frequency` enum('monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `policy_start_date` date DEFAULT NULL,
  `policy_end_date` date DEFAULT NULL,
  `policy_term_years` int DEFAULT NULL,
  `conditions_covered` json DEFAULT NULL,
  `exclusions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sickness_illness_policies_user_id_index` (`user_id`),
  CONSTRAINT `sickness_illness_policies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spouse_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spouse_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `spouse_id` bigint unsigned NOT NULL,
  `status` enum('pending','accepted','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NULL DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spouse_permissions_user_id_spouse_id_unique` (`user_id`,`spouse_id`),
  UNIQUE KEY `spouse_permissions_user_spouse_unique` (`user_id`,`spouse_id`),
  KEY `spouse_permissions_status_index` (`status`),
  KEY `spouse_permissions_user_id_status_index` (`user_id`,`status`),
  KEY `spouse_permissions_spouse_id_status_index` (`spouse_id`,`status`),
  CONSTRAINT `spouse_permissions_spouse_id_foreign` FOREIGN KEY (`spouse_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spouse_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `state_pensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `state_pensions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ni_years_completed` int NOT NULL DEFAULT '0',
  `ni_years_required` int NOT NULL DEFAULT '35',
  `state_pension_forecast_annual` decimal(10,2) DEFAULT NULL,
  `state_pension_age` int DEFAULT NULL,
  `already_receiving` tinyint(1) NOT NULL DEFAULT '0',
  `ni_gaps` json DEFAULT NULL,
  `gap_fill_cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `state_pensions_user_id_index` (`user_id`),
  CONSTRAINT `state_pensions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `plan` enum('student','standard','pro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_cycle` enum('monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('trialing','active','cancelled','expired','past_due') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trialing',
  `trial_started_at` timestamp NULL DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `current_period_start` timestamp NULL DEFAULT NULL,
  `current_period_end` timestamp NULL DEFAULT NULL,
  `revolut_order_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_user_id_foreign` (`user_id`),
  CONSTRAINT `subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_configuration_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_configuration_audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_configuration_id` bigint unsigned NOT NULL,
  `changed_by_user_id` bigint unsigned DEFAULT NULL,
  `change_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `before_state` json DEFAULT NULL,
  `after_state` json NOT NULL,
  `changed_fields` json DEFAULT NULL,
  `rationale` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_configuration_audits_tax_configuration_id_index` (`tax_configuration_id`),
  KEY `tax_configuration_audits_changed_by_user_id_index` (`changed_by_user_id`),
  KEY `tax_configuration_audits_change_type_index` (`change_type`),
  KEY `tax_configuration_audits_created_at_index` (`created_at`),
  CONSTRAINT `tax_configuration_audits_changed_by_user_id_foreign` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_configuration_audits_tax_configuration_id_foreign` FOREIGN KEY (`tax_configuration_id`) REFERENCES `tax_configurations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date NOT NULL,
  `config_data` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_configurations_tax_year_unique` (`tax_year`),
  KEY `tax_configurations_tax_year_index` (`tax_year`),
  KEY `tax_configurations_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_product_reference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_product_reference` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_aspect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_product_reference_product_category_product_type_index` (`product_category`,`product_type`),
  KEY `tax_product_reference_product_type_tax_aspect_index` (`product_type`,`tax_aspect`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trial_reminder_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trial_reminder_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `days_remaining` int NOT NULL,
  `sent_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trial_reminder_log_user_id_days_remaining_unique` (`user_id`,`days_remaining`),
  CONSTRAINT `trial_reminder_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trusts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trusts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `trust_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trust_type` enum('bare','interest_in_possession','discretionary','accumulation_maintenance','life_insurance','discounted_gift','loan','mixed','settlor_interested','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `other_type_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description when trust_type is other',
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Country where trust is located',
  `trust_creation_date` date NOT NULL,
  `initial_value` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `last_valuation_date` date DEFAULT NULL,
  `discount_amount` decimal(15,2) DEFAULT NULL COMMENT 'Actuarial discount for retained income',
  `retained_income_annual` decimal(15,2) DEFAULT NULL COMMENT 'Annual income retained by settlor',
  `loan_amount` decimal(15,2) DEFAULT NULL COMMENT 'Outstanding loan balance',
  `loan_interest_bearing` tinyint(1) NOT NULL DEFAULT '0',
  `loan_interest_rate` decimal(5,4) DEFAULT NULL,
  `sum_assured` decimal(15,2) DEFAULT NULL COMMENT 'Life insurance policy sum assured',
  `annual_premium` decimal(15,2) DEFAULT NULL,
  `is_relevant_property_trust` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Subject to 10-year periodic charges',
  `last_periodic_charge_date` date DEFAULT NULL,
  `last_periodic_charge_amount` decimal(15,2) DEFAULT NULL,
  `next_tax_return_due` date DEFAULT NULL,
  `total_asset_value` decimal(15,2) DEFAULT NULL COMMENT 'Aggregated value of all assets held in trust',
  `beneficiaries` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'List of beneficiaries',
  `trustees` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'List of trustees',
  `settlor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Person who created the trust',
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Purpose of the trust',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trusts_user_id_index` (`user_id`),
  KEY `trusts_trust_type_index` (`trust_type`),
  KEY `trusts_is_relevant_property_trust_index` (`is_relevant_property_trust`),
  KEY `trusts_household_id_index` (`household_id`),
  CONSTRAINT `trusts_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trusts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `uk_life_expectancy_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `uk_life_expectancy_tables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `age` int NOT NULL,
  `gender` enum('male','female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `life_expectancy_years` decimal(5,2) NOT NULL,
  `table_version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ONS_2020_2022',
  `data_year` year NOT NULL DEFAULT '2022',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_life_expectancy_tables_age_gender_table_version_unique` (`age`,`gender`,`table_version`),
  KEY `uk_life_expectancy_tables_age_gender_index` (`age`,`gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_assumptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_assumptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `assumption_type` enum('pensions','investments','estate_planning') COLLATE utf8mb4_unicode_ci NOT NULL,
  `inflation_rate` decimal(5,2) DEFAULT NULL,
  `return_rate` decimal(5,2) DEFAULT NULL,
  `compound_periods` int DEFAULT NULL,
  `property_growth_rate` decimal(5,2) DEFAULT NULL,
  `investment_growth_method` enum('monte_carlo','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monte_carlo',
  `custom_investment_rate` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_assumptions_user_id_assumption_type_unique` (`user_id`,`assumption_type`),
  KEY `user_assumptions_user_id_index` (`user_id`),
  CONSTRAINT `user_assumptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `consent_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `consented` tinyint(1) NOT NULL DEFAULT '0',
  `consented_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_consents_user_id_consent_type_version_unique` (`user_id`,`consent_type`,`version`),
  KEY `user_consents_user_id_consent_type_index` (`user_id`,`consent_type`),
  CONSTRAINT `user_consents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token_id` bigint unsigned NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_sessions_token_id_foreign` (`token_id`),
  KEY `user_sessions_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `user_sessions_token_id_foreign` FOREIGN KEY (`token_id`) REFERENCES `personal_access_tokens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `is_preview_user` tinyint(1) NOT NULL DEFAULT '0',
  `plan` enum('free','student','standard','pro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `preview_persona_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `mfa_secret` text COLLATE utf8mb4_unicode_ci,
  `mfa_recovery_codes` json DEFAULT NULL,
  `mfa_confirmed_at` timestamp NULL DEFAULT NULL,
  `failed_login_count` int NOT NULL DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_failed_login_at` timestamp NULL DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domicile_status` enum('uk_domiciled','non_uk_domiciled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'UK residence-based domicile status',
  `country_of_birth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Country where user was born',
  `uk_arrival_date` date DEFAULT NULL COMMENT 'Date user arrived in UK (for non-UK born individuals)',
  `years_uk_resident` int DEFAULT NULL COMMENT 'Calculated: number of years UK resident',
  `deemed_domicile_date` date DEFAULT NULL COMMENT 'Date user became deemed domiciled under 15/20 year rule',
  `spouse_id` bigint unsigned DEFAULT NULL,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT '0',
  `onboarding_focus_area` enum('estate','protection','retirement','investment','tax_optimisation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_current_step` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_skipped_steps` json DEFAULT NULL,
  `onboarding_started_at` timestamp NULL DEFAULT NULL,
  `onboarding_completed_at` timestamp NULL DEFAULT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `properties_count` int unsigned NOT NULL DEFAULT '0',
  `investment_accounts_count` int unsigned NOT NULL DEFAULT '0',
  `savings_accounts_count` int unsigned NOT NULL DEFAULT '0',
  `dc_pensions_count` int unsigned NOT NULL DEFAULT '0',
  `db_pensions_count` int unsigned NOT NULL DEFAULT '0',
  `is_primary_account` tinyint(1) NOT NULL DEFAULT '1',
  `national_insurance_number` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occupation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `health_status` enum('yes','yes_previous','no_previous','no_existing','no_both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `smoking_status` enum('never','quit_recent','quit_long_ago','yes') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'never',
  `education_level` enum('secondary','a_level','undergraduate','postgraduate','professional','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `industry` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_status` enum('employed','part_time','self_employed','retired','unemployed','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `income_needs_update` tinyint(1) NOT NULL DEFAULT '0',
  `previous_employment_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_retirement_age` tinyint unsigned DEFAULT NULL,
  `retirement_date` date DEFAULT NULL,
  `annual_employment_income` decimal(15,2) DEFAULT NULL,
  `annual_self_employment_income` decimal(15,2) DEFAULT NULL,
  `annual_rental_income` decimal(15,2) DEFAULT NULL,
  `annual_dividend_income` decimal(15,2) DEFAULT NULL,
  `annual_interest_income` double NOT NULL DEFAULT '0',
  `annual_other_income` decimal(15,2) DEFAULT NULL,
  `payday_day_of_month` tinyint unsigned DEFAULT NULL,
  `annual_trust_income` decimal(15,2) DEFAULT NULL,
  `monthly_expenditure` double DEFAULT NULL,
  `annual_expenditure` double DEFAULT NULL,
  `food_groceries` double NOT NULL DEFAULT '0',
  `transport_fuel` double NOT NULL DEFAULT '0',
  `healthcare_medical` double NOT NULL DEFAULT '0',
  `insurance` double NOT NULL DEFAULT '0',
  `mobile_phones` double NOT NULL DEFAULT '0',
  `internet_tv` double NOT NULL DEFAULT '0',
  `subscriptions` double NOT NULL DEFAULT '0',
  `clothing_personal_care` double NOT NULL DEFAULT '0',
  `entertainment_dining` double NOT NULL DEFAULT '0',
  `holidays_travel` double NOT NULL DEFAULT '0',
  `pets` double NOT NULL DEFAULT '0',
  `childcare` double NOT NULL DEFAULT '0',
  `school_fees` double NOT NULL DEFAULT '0',
  `school_lunches` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Monthly school lunch costs',
  `school_extras` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Uniforms, trips, equipment etc.',
  `university_fees` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Includes residential, books and any other costs',
  `children_activities` double NOT NULL DEFAULT '0',
  `gifts_charity` double NOT NULL DEFAULT '0',
  `charitable_bequest` tinyint(1) DEFAULT NULL,
  `regular_savings` double NOT NULL DEFAULT '0',
  `other_expenditure` double NOT NULL DEFAULT '0',
  `rent` decimal(10,2) DEFAULT NULL,
  `utilities` decimal(10,2) DEFAULT NULL,
  `expenditure_entry_mode` enum('simple','category') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'category' COMMENT 'Whether user uses simple total or detailed category breakdown',
  `expenditure_sharing_mode` enum('joint','separate') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'joint' COMMENT 'For married users: joint 50/50 split or separate values',
  `liabilities_reviewed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether user has reviewed liabilities (even if zero)',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guidance_active` tinyint(1) NOT NULL DEFAULT '0',
  `guidance_completed` tinyint(1) NOT NULL DEFAULT '0',
  `info_guide_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `dashboard_widget_order` json DEFAULT NULL,
  `guidance_current_step` tinyint unsigned NOT NULL DEFAULT '0',
  `guidance_completed_steps` json DEFAULT NULL,
  `guidance_skipped_steps` json DEFAULT NULL,
  `guidance_version` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preview_persona_kept` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_spouse_id_index` (`spouse_id`),
  KEY `users_household_id_index` (`household_id`),
  KEY `preview_user_persona_idx` (`is_preview_user`,`preview_persona_id`),
  KEY `users_role_id_foreign` (`role_id`),
  CONSTRAINT `users_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_spouse_id_foreign` FOREIGN KEY (`spouse_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wills` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `has_will` tinyint(1) NOT NULL DEFAULT '0',
  `death_scenario` enum('user_only','both_simultaneous') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user_only',
  `spouse_primary_beneficiary` tinyint(1) NOT NULL DEFAULT '1',
  `spouse_bequest_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `executor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `executor_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `will_last_updated` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wills_user_id_idx` (`user_id`),
  CONSTRAINT `wills_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_reset_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_10_13_113656_create_tax_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_10_13_113806_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_10_13_131230_create_critical_illness_policies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_10_13_131230_create_income_protection_policies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_10_13_131230_create_life_insurance_policies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_10_13_131230_create_protection_profiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_10_13_132846_create_disability_policies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_10_13_132846_create_sickness_illness_policies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_10_14_075501_create_dc_pensions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_10_14_075511_create_savings_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_10_14_075513_create_net_worth_statements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_10_14_075618_create_savings_goals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_10_14_075624_create_db_pensions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_10_14_075637_create_assets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_10_14_075637_create_liabilities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_10_14_075638_create_gifts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_10_14_075638_create_iht_profiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_10_14_075652_create_expenditure_profiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_10_14_075708_create_state_pensions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_10_14_075725_create_isa_allowance_tracking_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_10_14_075746_create_retirement_profiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_10_14_091658_create_investment_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_10_14_091714_create_holdings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_10_14_091714_create_investment_goals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_10_14_091714_create_risk_profiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_10_15_070121_fix_investment_accounts_defaults',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_10_15_070221_add_isa_fields_to_investment_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_10_15_070439_fix_platform_fee_percent_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_10_15_085438_add_annual_salary_to_dc_pensions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_10_15_094650_add_additional_fields_to_liabilities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_10_15_111259_add_notes_to_gifts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_10_15_123423_create_trusts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_10_15_134915_create_recommendation_tracking_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_10_16_080205_add_allocation_percent_to_holdings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_10_16_080903_make_purchase_date_nullable_in_holdings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_10_16_080933_update_asset_type_enum_in_holdings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_10_16_081113_make_cost_basis_nullable_in_holdings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_10_17_142646_add_spouse_linking_and_role_to_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_10_17_142728_create_households_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_10_17_142742_add_foreign_keys_to_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_10_17_142756_create_family_members_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_10_17_142814_create_properties_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_10_17_142836_create_mortgages_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_10_17_142854_create_business_interests_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_10_17_142854_create_chattels_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_10_17_142855_create_cash_accounts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_10_17_142855_create_personal_accounts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_10_17_142957_add_ownership_fields_to_investment_accounts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_10_17_143014_add_additional_fields_to_trusts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_10_20_103501_add_outstanding_mortgage_to_properties_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_10_20_104118_make_property_address_fields_nullable',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_10_20_111314_add_is_emergency_fund_to_savings_accounts_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_10_21_085149_create_spouse_permissions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_10_21_085212_add_ownership_fields_to_savings_accounts_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_10_21_093110_add_must_change_password_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_10_21_100607_add_joint_ownership_to_assets_tables',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_10_21_112311_add_trust_ownership_type_to_asset_tables',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_10_21_162955_create_wills_and_bequests_tables',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_10_21_172331_create_uk_life_expectancy_tables_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_10_22_093756_add_is_admin_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_10_22_104911_add_onboarding_fields_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_10_22_104949_create_onboarding_progress_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_10_23_154600_update_assets_ownership_type_to_individual',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2025_10_25_091932_add_liquidity_fields_to_assets_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2025_10_27_083751_add_domicile_fields_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2025_10_27_090614_add_country_to_properties_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2025_10_27_090642_add_country_to_investment_accounts_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2025_10_27_090643_add_country_to_savings_accounts_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2025_10_27_090644_add_country_to_business_interests_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_10_27_090645_add_country_to_chattels_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_10_27_090647_add_country_to_cash_accounts_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_10_27_090647_add_country_to_mortgages_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2025_10_27_090648_add_country_to_liabilities_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_10_27_101245_add_expenditure_fields_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_10_28_073305_add_has_will_to_wills_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_10_28_110003_add_health_and_education_fields_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_10_28_115155_add_has_no_policies_to_protection_profiles_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_10_29_061634_create_letters_to_spouse_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_11_01_121546_create_efficient_frontier_calculations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_11_01_121547_create_factor_exposures_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_11_01_121548_create_risk_metrics_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_11_01_121549_create_portfolio_optimizations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2025_11_01_135017_create_rebalancing_actions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2025_11_01_194052_create_investment_plans_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2025_11_01_194108_create_investment_recommendations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2025_11_02_112925_create_investment_scenarios_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_11_04_103745_make_holdings_polymorphic',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_11_07_140702_update_health_and_smoking_fields_in_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2025_11_07_155504_add_yes_previous_to_health_status_enum',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2025_11_07_160346_add_detailed_expenditure_fields_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2025_11_08_080820_add_ownership_and_tenure_fields_to_properties_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2025_11_08_100336_add_monthly_costs_to_properties_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2025_11_08_102608_update_ownership_type_enum_in_properties_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_11_08_103301_add_tenant_email_to_properties_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_11_08_122852_make_mortgage_fields_nullable',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_11_08_131422_update_interest_rate_column_size_in_mortgages_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_11_08_132040_add_nsi_to_investment_accounts_account_type_enum',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2025_11_08_160710_update_interest_rate_in_liabilities_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2025_11_09_130046_add_retirement_fields_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2025_11_09_133324_change_expenditure_columns_to_double',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2025_11_10_200000_add_executor_name_and_rename_will_date',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2025_11_11_213041_create_actuarial_life_tables_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2025_11_11_213138_create_iht_calculations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2025_11_11_213929_add_projected_values_to_iht_calculations_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2025_11_12_075601_add_charitable_bequest_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2025_11_12_083427_add_decreasing_policy_fields_to_life_insurance_policies_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2025_11_12_094404_add_lump_sum_contribution_to_dc_pensions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2025_11_12_101030_add_annual_interest_income_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2025_11_12_193748_add_tenants_in_common_and_trust_to_properties_ownership_type',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_11_12_194237_make_properties_purchase_fields_nullable',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_11_13_163500_add_joint_ownership_to_mortgages_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2025_11_13_164000_add_missing_ownership_columns_to_mortgages',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2025_11_14_095112_remove_redundant_rental_fields_from_properties_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2025_11_14_103319_add_name_fields_to_family_members_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2025_11_14_120204_add_end_date_and_make_fields_optional_on_life_insurance_policies_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2025_11_14_123750_add_pension_type_to_dc_pensions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2025_11_15_093603_add_other_account_type_to_investment_accounts_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2025_11_15_095207_add_mixed_mortgage_fields_to_mortgages_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2025_11_15_100406_add_managing_agent_fields_to_properties_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2025_11_15_111744_add_part_time_to_employment_status_enum',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2025_11_15_115911_add_expenditure_modes_and_education_fields_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2025_11_15_125142_add_is_mortgage_protection_to_life_insurance_policies_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_11_15_162349_remove_part_and_part_from_mortgage_type_enum',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_11_15_170630_update_liability_type_enum_to_support_all_types',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2025_11_17_074642_add_expected_return_percent_to_dc_pensions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2025_11_22_092125_add_joint_ownership_to_liabilities_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2025_11_24_124735_make_policy_end_date_nullable_on_life_insurance_policies_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2025_11_24_141304_add_policy_end_date_to_protection_policies',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2025_11_24_144502_make_scheme_type_nullable_on_dc_pensions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2025_11_24_151629_make_protection_policy_dates_nullable',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2025_11_25_110113_create_joint_account_logs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2025_11_25_132510_make_provider_nullable_on_dc_pensions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2025_12_05_000001_create_documents_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2025_12_05_000002_create_document_extractions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2025_12_05_000003_create_document_extraction_logs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2025_12_08_130937_make_scheme_name_nullable_on_dc_pensions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2025_12_12_103752_add_guidance_columns_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2025_12_12_120000_add_database_performance_indexes',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2025_12_12_120001_add_eager_loading_optimizations',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2025_12_12_173349_add_preview_user_columns_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2025_12_14_134507_create_tax_configuration_audits_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2025_12_15_125335_add_ownership_percentage_to_mortgages_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2025_12_16_093932_create_tax_product_reference_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2025_12_16_103303_refactor_users_name_fields',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2025_12_16_103444_make_all_data_columns_nullable',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2025_12_16_152549_add_risk_level_to_risk_profiles_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2025_12_16_152550_add_risk_preference_to_investment_accounts_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2025_12_16_152552_add_risk_preference_to_dc_pensions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2025_12_18_162231_create_email_verification_codes_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2025_12_19_144610_add_settlor_to_trusts_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2025_12_19_154630_add_annual_trust_income_to_users_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2025_12_19_160530_add_already_receiving_to_state_pensions_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2025_12_19_173206_add_employer_matching_limit_to_dc_pensions_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2025_12_23_140824_add_tax_fields_to_business_interests_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2025_12_30_103416_add_advisor_fee_to_investment_accounts',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2025_12_30_110842_add_rebalance_threshold_to_investment_accounts',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2025_12_30_160326_add_account_name_to_investment_accounts',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2025_12_30_164125_add_info_guide_enabled_to_users_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2026_01_02_171718_create_pending_registrations_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2026_01_03_154132_make_risk_profile_columns_nullable',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2026_01_08_091458_make_form_fields_optional',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2026_01_10_131616_add_payday_day_of_month_to_users_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2026_01_12_115104_add_dashboard_widget_order_to_users',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2026_01_15_105903_add_other_trust_type_and_country_to_trusts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2026_01_15_111814_add_platform_fee_type_and_frequency_to_investment_accounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2026_01_16_151113_add_factor_breakdown_to_risk_profiles',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2026_01_17_092200_add_joint_owner_name_to_chattels_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2026_01_18_000001_create_goals_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2026_01_18_000002_create_goal_contributions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2026_01_18_000003_migrate_existing_goals_data',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2026_01_19_134658_create_login_attempts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2026_01_19_134659_add_mfa_fields_to_users_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2026_01_19_134700_add_lockout_fields_to_users_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2026_01_19_134700_create_user_sessions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2026_01_19_135404_create_audit_logs_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2026_01_19_140001_create_erasure_requests_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2026_01_19_140002_create_user_consents_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2026_01_19_140003_create_data_exports_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2026_01_19_140501_create_roles_permissions_tables',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2026_01_19_142149_alter_mfa_secret_column_to_text',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2026_01_21_000001_create_password_reset_sessions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2026_01_21_162226_add_beneficiary_fields_to_savings_accounts',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2026_01_21_164549_add_beneficiary_dob_to_savings_accounts',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2026_01_22_162633_add_contribution_fields_to_investment_accounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2026_01_24_091552_add_monthly_interest_portion_to_mortgages_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2026_01_24_134257_make_factor_breakdown_nullable_on_risk_profiles',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2026_01_24_160001_create_goals_table_v2',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2026_01_24_160002_create_goal_contributions_table_v2',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2026_01_26_000001_add_contribution_fields_to_savings_accounts',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2026_01_26_150000_add_joint_owner_indexes',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2026_01_28_000001_create_occupation_codes_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2026_01_28_100000_add_income_needs_update_to_users_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2026_01_28_163920_create_monte_carlo_cache_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2026_01_29_082107_add_private_investment_fields_to_investment_accounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2026_01_29_130208_add_missing_contribution_fields_to_investment_accounts',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2026_01_29_140000_add_employee_share_scheme_fields_to_investment_accounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2026_01_30_100000_add_beneficiary_to_dc_pensions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2026_01_30_120000_create_user_assumptions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2026_01_30_150000_add_include_in_retirement_to_investment_accounts',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2026_01_30_160000_add_contribution_fields_to_investment_accounts',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2026_01_31_120000_add_include_in_retirement_to_savings_accounts',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2026_01_31_135615_add_bond_fields_to_investment_accounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2026_01_31_154201_add_badr_fields_to_investment_accounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2026_01_31_200000_add_receives_child_benefit_to_family_members',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2026_02_02_095622_add_additional_boxes_to_letters_to_spouse_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2026_02_03_100001_add_charity_fields_to_bequests_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2026_02_03_100002_add_estate_planning_to_user_assumptions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2026_02_03_120001_create_life_events_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2026_02_03_120002_add_projection_fields_to_goals_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2026_02_05_120000_add_rent_and_utilities_to_users_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2026_02_05_150000_add_rnrb_transferred_to_iht_profiles_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2026_02_12_100001_create_subscriptions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2026_02_12_100002_create_payments_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2026_02_12_100003_add_plan_fields_to_users_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2026_02_12_100004_create_trial_reminder_log_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2026_02_12_100005_add_plan_fields_to_pending_registrations_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2026_02_17_120040_add_account_name_to_savings_accounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2026_02_19_120000_add_joint_owner_id_to_cash_accounts_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2026_02_19_120001_add_linked_user_id_to_family_members_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2026_02_20_000001_add_expires_at_to_pending_registrations_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2026_02_20_120000_assign_roles_to_existing_users',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2026_02_20_130000_drop_legacy_role_column_from_users',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2026_02_21_104352_add_soft_deletes_to_business_interests_and_chattels',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2026_02_21_104355_add_joint_owner_foreign_keys_to_business_interests_and_chattels',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2026_02_21_120000_add_soft_deletes_to_savings_tables',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2026_02_21_120001_create_savings_market_rates_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2026_02_21_130000_add_mpaa_fields_to_dc_pensions',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2026_02_21_130000_add_projection_columns_to_iht_calculations',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2026_02_21_130001_add_carry_forward_fields_to_retirement_profiles',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2026_02_21_130002_remove_risk_tolerance_from_retirement_profiles',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2026_02_21_140000_add_result_json_to_iht_calculations',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2026_02_21_200001_fix_payment_subscription_amount_to_decimal',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2026_02_21_200002_add_soft_deletes_to_financial_models',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2026_02_21_200003_add_joint_owner_foreign_keys_to_remaining_tables',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2026_02_21_200004_add_missing_indexes_to_financial_tables',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2026_02_21_200005_add_verification_attempt_counters',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2026_02_22_130000_widen_encrypted_columns_to_text',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2026_02_23_120001_create_goal_dependencies_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2026_02_23_120002_add_linked_investment_account_to_goals',10);
