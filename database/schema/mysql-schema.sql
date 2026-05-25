/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `account_deletion_reminder_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_deletion_reminder_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `days_remaining` tinyint unsigned NOT NULL,
  `sent_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account_deletion_reminder_log_user_id_days_remaining_index` (`user_id`,`days_remaining`),
  CONSTRAINT `account_deletion_reminder_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `advisor_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `advisor_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `advisor_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `status` enum('active','inactive','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `assigned_date` date NOT NULL,
  `last_review_date` date DEFAULT NULL,
  `next_review_due` date DEFAULT NULL,
  `review_frequency_months` tinyint unsigned NOT NULL DEFAULT '12',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_advisor_client` (`advisor_id`,`client_id`),
  KEY `advisor_clients_client_id_foreign` (`client_id`),
  KEY `idx_advisor_status` (`advisor_id`,`status`),
  KEY `idx_next_review` (`next_review_due`),
  CONSTRAINT `advisor_clients_advisor_id_foreign` FOREIGN KEY (`advisor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `advisor_clients_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_abort_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_abort_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `last_tool_call` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partial_write_count` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_abort_events_conversation_id_foreign` (`conversation_id`),
  KEY `ai_abort_events_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `ai_abort_events_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_abort_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_advice_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_advice_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `query_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classification` json DEFAULT NULL,
  `kyc_status` json DEFAULT NULL,
  `recommendations` json DEFAULT NULL,
  `tools_called` json DEFAULT NULL,
  `user_data_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_advice_logs_conversation_id_foreign` (`conversation_id`),
  KEY `ai_advice_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `ai_advice_logs_user_id_query_type_index` (`user_id`,`query_type`),
  KEY `ai_advice_logs_query_type_index` (`query_type`),
  CONSTRAINT `ai_advice_logs_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_advice_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_audit_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_audit_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `tool_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `operation` enum('read','write','handoff','classify') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('dispatched','persisted','failed','stripped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `input_summary` json DEFAULT NULL,
  `result_summary` json DEFAULT NULL,
  `entity_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `prev_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signed_at` timestamp NOT NULL,
  `signature` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ai_audit_events_conversation_id_foreign` (`conversation_id`),
  KEY `ai_audit_events_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `ai_audit_events_tool_name_status_index` (`tool_name`,`status`),
  KEY `ai_audit_events_row_hash_index` (`row_hash`),
  KEY `ai_audit_events_operation_created_idx` (`operation`,`created_at`),
  CONSTRAINT `ai_audit_events_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_audit_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `model_used` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_input_tokens` int unsigned NOT NULL DEFAULT '0',
  `total_output_tokens` int unsigned NOT NULL DEFAULT '0',
  `message_count` int unsigned NOT NULL DEFAULT '0',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `persona_state` json DEFAULT NULL COMMENT 'FynPersonaOrchestrator state: current mode, pending advice question, capture context.',
  `onboarding_parked_facts` json DEFAULT NULL COMMENT 'OnboardingFactExtractor output: speculative facts extracted per user turn, consumed by OnboardingChatDirector state handlers. Writes to backing records (users.*, family_members) happen only at state commit points, not at parking.',
  `summary` text COLLATE utf8mb4_unicode_ci,
  `topics` json DEFAULT NULL,
  `entities_mentioned` json DEFAULT NULL,
  `intents_stated` json DEFAULT NULL,
  `summarised_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_conversations_user_id_status_last_message_at_index` (`user_id`,`status`,`last_message_at`),
  KEY `ai_conversations_summarised_at_index` (`summarised_at`),
  CONSTRAINT `ai_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_daily_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_daily_usage` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `usage_date` date NOT NULL,
  `tokens_used` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_daily_usage_user_id_usage_date_unique` (`user_id`,`usage_date`),
  KEY `ai_daily_usage_usage_date_index` (`usage_date`),
  CONSTRAINT `ai_daily_usage_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `role` enum('user','assistant','system','tool_result') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `persona` enum('advice','data_capture') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Which Fyn persona produced this message. Null for pre-split rows.',
  `system_prompt` longtext COLLATE utf8mb4_unicode_ci,
  `assembled_context` longtext COLLATE utf8mb4_unicode_ci,
  `tool_calls` json DEFAULT NULL,
  `tool_results` json DEFAULT NULL,
  `input_tokens` int unsigned DEFAULT NULL,
  `output_tokens` int unsigned DEFAULT NULL,
  `model_used` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  CONSTRAINT `ai_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_request_idempotency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_request_idempotency` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `key_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_uri` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `response_status` smallint unsigned NOT NULL DEFAULT '200',
  `response_payload` json DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_request_idempotency_key_hash_unique` (`key_hash`),
  KEY `ai_request_idempotency_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `ai_request_idempotency_expires_at_index` (`expires_at`),
  CONSTRAINT `ai_request_idempotency_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_event_type_action_index` (`event_type`,`action`),
  KEY `audit_logs_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `audit_logs_created_at_index` (`created_at`),
  KEY `audit_logs_event_type_created_idx` (`event_type`,`created_at`),
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
  `beneficiary_type` enum('individual','charity','trust','organization') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `charity_registration_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bequest_type` enum('percentage','specific_amount','specific_asset','residuary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `percentage_of_estate` decimal(5,2) DEFAULT NULL,
  `specific_amount` decimal(15,2) DEFAULT NULL,
  `specific_asset_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asset_id` bigint unsigned DEFAULT NULL,
  `priority_order` int NOT NULL DEFAULT '1',
  `conditions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bequests_user_id_foreign` (`user_id`),
  KEY `bequests_beneficiary_user_id_foreign` (`beneficiary_user_id`),
  KEY `bequests_will_priority_idx` (`will_id`,`priority_order`),
  KEY `idx_bequests_asset_id` (`asset_id`),
  CONSTRAINT `bequests_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
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
  `business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Companies House registration number',
  `business_type` enum('sole_trader','partnership','limited_company','llp','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
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
  `account_number` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sort_code` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `joint_owner_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `trust_id` bigint unsigned DEFAULT NULL,
  `chattel_type` enum('vehicle','art','antique','jewelry','collectible','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `client_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `advisor_client_id` bigint unsigned NOT NULL,
  `advisor_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `activity_type` enum('email','phone','meeting','letter','suitability_report','review','note') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `activity_date` datetime NOT NULL,
  `follow_up_date` date DEFAULT NULL,
  `follow_up_completed` tinyint(1) NOT NULL DEFAULT '0',
  `report_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `report_sent_date` date DEFAULT NULL,
  `report_acknowledged_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_activities_client_id_foreign` (`client_id`),
  KEY `idx_advisor_client_id` (`advisor_client_id`),
  KEY `idx_advisor_client` (`advisor_id`,`client_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_activity_date` (`activity_date`),
  KEY `idx_follow_up` (`follow_up_date`,`follow_up_completed`),
  CONSTRAINT `client_activities_advisor_client_id_foreign` FOREIGN KEY (`advisor_client_id`) REFERENCES `advisor_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_activities_advisor_id_foreign` FOREIGN KEY (`advisor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `client_activities_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `currency_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `from_ccy` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_ccy` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(18,8) NOT NULL,
  `effective_at` datetime NOT NULL,
  `source` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_rates_from_ccy_to_ccy_effective_at_index` (`from_ccy`,`to_ccy`,`effective_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `format` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'json',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data_exports_user_id_status_index` (`user_id`,`status`),
  KEY `data_exports_expires_at_index` (`expires_at`),
  CONSTRAINT `data_exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_retention_email_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_retention_email_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint unsigned NOT NULL,
  `day_number` smallint unsigned NOT NULL,
  `sent_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_retention_email_log_subscription_id_day_number_unique` (`subscription_id`,`day_number`),
  CONSTRAINT `data_retention_email_log_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE
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
  `salary_sacrifice` tinyint(1) DEFAULT NULL COMMENT 'true if pension contributions are made via salary sacrifice',
  `employer_ni_rebate_pct` decimal(5,4) DEFAULT NULL COMMENT 'Share of employer NI saving rebated back into the pension when salary sacrifice is in use (0.0000-1.0000).',
  `monthly_contribution_amount` decimal(10,2) DEFAULT NULL,
  `lump_sum_contribution` decimal(15,2) DEFAULT NULL,
  `investment_strategy` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_fee_percent` decimal(5,4) DEFAULT NULL,
  `platform_fee_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `platform_fee_amount` decimal(15,2) DEFAULT NULL,
  `platform_fee_frequency` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annually',
  `advisor_fee_percent` decimal(5,4) DEFAULT NULL,
  `retirement_age` int DEFAULT NULL,
  `expected_return_percent` decimal(5,2) DEFAULT NULL,
  `projected_value_at_retirement` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `risk_preference` enum('low','lower_medium','medium','upper_medium','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_custom_risk` tinyint(1) NOT NULL DEFAULT '0',
  `beneficiary_id` bigint unsigned DEFAULT NULL,
  `beneficiary_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `device_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `device_token` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` enum('ios','android') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_tokens_user_id_device_id_unique` (`user_id`,`device_id`),
  KEY `device_tokens_device_token_index` (`device_token`),
  CONSTRAINT `device_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `discount_code_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discount_code_usages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `discount_code_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `applied_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discount_code_usages_user_id_foreign` (`user_id`),
  KEY `discount_code_usages_payment_id_foreign` (`payment_id`),
  KEY `discount_code_usages_discount_code_id_user_id_index` (`discount_code_id`,`user_id`),
  CONSTRAINT `discount_code_usages_discount_code_id_foreign` FOREIGN KEY (`discount_code_id`) REFERENCES `discount_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discount_code_usages_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discount_code_usages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `discount_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discount_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('percentage','fixed_amount','trial_extension','lifecycle_welcome') COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` int NOT NULL,
  `max_uses` int DEFAULT NULL,
  `times_used` int NOT NULL DEFAULT '0',
  `max_uses_per_user` int NOT NULL DEFAULT '1',
  `applicable_plans` json DEFAULT NULL,
  `applicable_cycles` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discount_codes_code_unique` (`code`),
  KEY `discount_codes_created_by_foreign` (`created_by`),
  KEY `discount_codes_user_id_foreign` (`user_id`),
  CONSTRAINT `discount_codes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discount_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `keywords` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_byline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html_body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `imported_by` bigint unsigned NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_doc_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_articles_slug_unique` (`slug`),
  KEY `document_articles_imported_by_foreign` (`imported_by`),
  KEY `document_articles_status_index` (`status`),
  KEY `document_articles_published_at_index` (`published_at`),
  KEY `document_articles_original_doc_hash_index` (`original_doc_hash`),
  CONSTRAINT `document_articles_imported_by_foreign` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `challenge_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resend_count` int NOT NULL DEFAULT '0',
  `failed_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_verification_codes_user_id_type_code_index` (`user_id`,`type`,`code`),
  KEY `email_verification_codes_challenge_token_index` (`challenge_token`),
  CONSTRAINT `email_verification_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `erasure_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `erasure_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `data_categories_deleted` json DEFAULT NULL,
  `processed_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `erasure_requests_user_id_status_index` (`user_id`,`status`),
  KEY `erasure_requests_status_index` (`status`),
  CONSTRAINT `erasure_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `estate_action_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estate_action_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `scope` enum('account','portfolio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portfolio',
  `what_if_impact_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `trigger_config` json NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '100',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `estate_action_definitions_key_unique` (`key`),
  KEY `estate_action_definitions_source_index` (`source`),
  KEY `estate_action_definitions_is_enabled_index` (`is_enabled`),
  KEY `estate_action_definitions_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eval_provider_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eval_provider_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `eval_recording_session_id` bigint unsigned NOT NULL,
  `provider` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `user_message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `assistant_text` text COLLATE utf8mb4_unicode_ci,
  `tool_calls` json DEFAULT NULL,
  `sse_event_count` int unsigned NOT NULL DEFAULT '0',
  `sse_event_types` json DEFAULT NULL,
  `forbidden_hits` json DEFAULT NULL,
  `db_writes_made` json DEFAULT NULL,
  `end_state_snapshot` json DEFAULT NULL,
  `engine_trace` json DEFAULT NULL,
  `fixture_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `started_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `eval_provider_runs_conversation_id_foreign` (`conversation_id`),
  KEY `eval_runs_session_provider_idx` (`eval_recording_session_id`,`provider`),
  KEY `eval_runs_provider_model_idx` (`provider`,`model`,`started_at`),
  CONSTRAINT `eval_provider_runs_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `eval_provider_runs_eval_recording_session_id_foreign` FOREIGN KEY (`eval_recording_session_id`) REFERENCES `eval_recording_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eval_recording_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eval_recording_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scenario_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scenario_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scenario_yaml` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `preview_user_id` bigint unsigned NOT NULL,
  `persona` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_state_snapshot` json NOT NULL,
  `http_log` json DEFAULT NULL,
  `remedial_report` text COLLATE utf8mb4_unicode_ci,
  `fynla_branch` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fynla_sha` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('running','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `started_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `eval_recording_sessions_eval_user_id_foreign` (`preview_user_id`),
  KEY `eval_sessions_scenario_started_idx` (`scenario_id`,`started_at`),
  KEY `eval_sessions_status_idx` (`status`),
  KEY `eval_recording_sessions_persona_index` (`persona`),
  CONSTRAINT `eval_recording_sessions_eval_user_id_foreign` FOREIGN KEY (`preview_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  UNIQUE KEY `expenditure_profiles_user_id_unique` (`user_id`),
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
  `relationship` enum('spouse','child','parent','other_dependent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other_dependent',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `national_insurance_number` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `annual_income` decimal(15,2) DEFAULT NULL,
  `is_dependent` tinyint(1) NOT NULL DEFAULT '0',
  `education_status` enum('pre_school','primary','secondary','further_education','higher_education','graduated','not_applicable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receives_child_benefit` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
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
DROP TABLE IF EXISTS `feedback_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feedback_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `campaign` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `free_text` text COLLATE utf8mb4_unicode_ci,
  `clicked_at` timestamp NOT NULL,
  `text_submitted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedback_responses_user_id_campaign_index` (`user_id`,`campaign`),
  KEY `feedback_responses_reason_code_index` (`reason_code`),
  KEY `feedback_responses_campaign_clicked_at_index` (`campaign`,`clicked_at`),
  CONSTRAINT `feedback_responses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `gift_date` date DEFAULT NULL,
  `recipient` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gift_type` enum('pet','clt','exempt','small_gift','annual_exemption') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exempt',
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
  `contribution_type` enum('manual','automatic','lump_sum','interest','adjustment') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `dependency_type` enum('blocks','funds','prerequisite') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prerequisite' COMMENT 'blocks: must complete first; funds: proceeds fund this goal; prerequisite: informational ordering',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `goal_dep_unique` (`goal_id`,`depends_on_goal_id`),
  KEY `goal_dep_reverse_idx` (`depends_on_goal_id`),
  CONSTRAINT `goal_dependencies_depends_on_goal_id_foreign` FOREIGN KEY (`depends_on_goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goal_dependencies_goal_id_foreign` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goal_savings_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_savings_account` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `goal_id` bigint unsigned NOT NULL,
  `savings_account_id` bigint unsigned NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `priority_rank` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `goal_savings_account_goal_id_savings_account_id_unique` (`goal_id`,`savings_account_id`),
  KEY `goal_savings_account_savings_account_id_index` (`savings_account_id`),
  CONSTRAINT `goal_savings_account_goal_id_foreign` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goal_savings_account_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `goals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `goal_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `goal_type` enum('emergency_fund','property_purchase','home_deposit','education','retirement','wealth_accumulation','wedding','holiday','car_purchase','debt_repayment','custom') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_goal_type_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `target_amount` decimal(15,2) NOT NULL,
  `current_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `target_date` date NOT NULL,
  `start_date` date DEFAULT NULL,
  `assigned_module` enum('savings','investment','property','retirement') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_override` tinyint(1) NOT NULL DEFAULT '0',
  `priority` enum('critical','high','medium','low') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `is_essential` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','paused','completed','abandoned') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `monthly_contribution` decimal(12,2) DEFAULT NULL,
  `contribution_frequency` enum('weekly','monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `contribution_streak` int unsigned NOT NULL DEFAULT '0',
  `longest_streak` int unsigned NOT NULL DEFAULT '0',
  `last_contribution_date` date DEFAULT NULL,
  `linked_account_ids` json DEFAULT NULL,
  `linked_savings_account_id` bigint unsigned DEFAULT NULL,
  `linked_investment_account_id` bigint unsigned DEFAULT NULL,
  `risk_preference` tinyint unsigned DEFAULT NULL,
  `use_global_risk_profile` tinyint(1) NOT NULL DEFAULT '1',
  `ownership_type` enum('individual','joint') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `show_in_projection` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_household_view` tinyint(1) NOT NULL DEFAULT '1',
  `property_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_type` enum('house','flat','bungalow','terraced','semi_detached','detached','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_first_time_buyer` tinyint(1) DEFAULT NULL,
  `estimated_property_price` decimal(15,2) DEFAULT NULL,
  `deposit_percentage` decimal(5,2) DEFAULT NULL,
  `stamp_duty_estimate` decimal(12,2) DEFAULT NULL,
  `additional_costs_estimate` decimal(12,2) DEFAULT NULL,
  `milestones` json DEFAULT NULL,
  `projection_data` json DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completion_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `sub_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `iht_profiles_user_id_unique` (`user_id`),
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
DROP TABLE IF EXISTS `insight_article_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insight_article_revisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `article_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_blocks` json DEFAULT NULL,
  `saved_by` bigint unsigned NOT NULL,
  `saved_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `insight_article_revisions_saved_by_foreign` (`saved_by`),
  KEY `insight_article_revisions_article_id_index` (`article_id`),
  KEY `insight_article_revisions_saved_at_index` (`saved_at`),
  CONSTRAINT `insight_article_revisions_article_id_foreign` FOREIGN KEY (`article_id`) REFERENCES `insight_articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insight_article_revisions_saved_by_foreign` FOREIGN KEY (`saved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insight_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insight_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('tax','pensions','savings-isa','estate-planning','financial-planning','ai','fintech','developer','international','platform-updates') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tags` json DEFAULT NULL,
  `hero_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_image_card_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_image_thumb_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_blocks` json DEFAULT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `is_bespoke` tinyint(1) NOT NULL DEFAULT '0',
  `bespoke_component` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `author_id` bigint unsigned NOT NULL,
  `authors` json DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `insight_articles_slug_unique` (`slug`),
  KEY `insight_articles_template_id_foreign` (`template_id`),
  KEY `insight_articles_author_id_foreign` (`author_id`),
  KEY `insight_articles_status_index` (`status`),
  KEY `insight_articles_is_featured_index` (`is_featured`),
  KEY `insight_articles_published_at_index` (`published_at`),
  KEY `insight_articles_category_index` (`category`),
  KEY `insight_articles_scheduled_at_index` (`scheduled_at`),
  CONSTRAINT `insight_articles_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insight_articles_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `insight_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insight_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insight_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_blocks` json NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `insight_templates_name_unique` (`name`),
  KEY `insight_templates_created_by_foreign` (`created_by`),
  CONSTRAINT `insight_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `company_legal_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_registration_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_trading_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_sector` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crowdfunding_platform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `investment_date` date DEFAULT NULL,
  `investment_amount` decimal(15,2) DEFAULT NULL,
  `investment_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBP',
  `funding_round` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pre_money_valuation` decimal(15,2) DEFAULT NULL,
  `post_money_valuation` decimal(15,2) DEFAULT NULL,
  `price_per_share` decimal(12,6) DEFAULT NULL,
  `number_of_shares` int DEFAULT NULL,
  `instrument_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `share_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_voting_rights` tinyint(1) NOT NULL DEFAULT '1',
  `has_dividend_rights` tinyint(1) NOT NULL DEFAULT '1',
  `liquidation_preference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_anti_dilution` tinyint(1) NOT NULL DEFAULT '0',
  `holding_structure` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct',
  `nominee_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `interest_rate` decimal(5,2) DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `tax_relief_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eis3_certificate_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hmrc_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relief_claimed_date` date DEFAULT NULL,
  `relief_amount_claimed` decimal(12,2) DEFAULT NULL,
  `disposal_restriction_date` date DEFAULT NULL,
  `clawback_risk` tinyint(1) NOT NULL DEFAULT '0',
  `clawback_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `latest_valuation` decimal(15,2) DEFAULT NULL,
  `latest_valuation_date` date DEFAULT NULL,
  `current_ownership_percent` decimal(5,4) DEFAULT NULL,
  `company_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `status_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `exit_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `exit_gross_proceeds` decimal(15,2) DEFAULT NULL,
  `exit_fees` decimal(12,2) DEFAULT NULL,
  `exit_net_proceeds` decimal(15,2) DEFAULT NULL,
  `exit_moic` decimal(6,2) DEFAULT NULL,
  `loss_relief_eligible` tinyint(1) NOT NULL DEFAULT '0',
  `capital_loss_amount` decimal(15,2) DEFAULT NULL,
  `negligible_value_claim` tinyint(1) NOT NULL DEFAULT '0',
  `employer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_registration` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_ticker` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer_is_listed` tinyint(1) NOT NULL DEFAULT '0',
  `parent_company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_company_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ers_scheme_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ers_registered` tinyint(1) NOT NULL DEFAULT '0',
  `grant_date` date DEFAULT NULL,
  `grant_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `units_granted` int DEFAULT NULL,
  `exercise_price` decimal(12,4) DEFAULT NULL,
  `market_value_at_grant` decimal(12,4) DEFAULT NULL,
  `share_class_scheme` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grant_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBP',
  `option_price_paid` decimal(12,2) DEFAULT NULL,
  `scheme_start_date` date DEFAULT NULL,
  `scheme_duration_months` int DEFAULT NULL,
  `vesting_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliff_date` date DEFAULT NULL,
  `cliff_percentage` int DEFAULT NULL,
  `vesting_period_months` int DEFAULT NULL,
  `vesting_frequency_months` int DEFAULT NULL,
  `has_performance_conditions` tinyint(1) NOT NULL DEFAULT '0',
  `performance_conditions_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `scheme_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `current_share_price` decimal(12,4) DEFAULT NULL,
  `share_price_date` date DEFAULT NULL,
  `exercise_window_start` date DEFAULT NULL,
  `exercise_window_end` date DEFAULT NULL,
  `last_exercise_date` date DEFAULT NULL,
  `total_exercise_proceeds` decimal(15,2) DEFAULT NULL,
  `total_exercise_cost` decimal(15,2) DEFAULT NULL,
  `exercise_history_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tax_treatment` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `leaver_category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_termination_exercise_days` int DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `leaver_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where account is held - hidden for ISAs',
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `platform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_holdings_value` decimal(15,2) DEFAULT NULL,
  `contributions_ytd` decimal(15,2) DEFAULT '0.00',
  `monthly_contribution_amount` decimal(12,2) DEFAULT NULL COMMENT 'Regular monthly contribution amount',
  `contribution_frequency` enum('monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly' COMMENT 'How often regular contributions are made',
  `planned_lump_sum_amount` decimal(12,2) DEFAULT NULL COMMENT 'One-off lump sum contribution planned',
  `planned_lump_sum_date` date DEFAULT NULL COMMENT 'Date when lump sum will be contributed',
  `tax_year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_fee_percent` decimal(5,4) DEFAULT '0.0000',
  `platform_fee_amount` decimal(10,2) DEFAULT NULL COMMENT 'Fixed fee amount when fee type is fixed',
  `platform_fee_type` enum('percentage','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage' COMMENT 'Whether fee is percentage or fixed amount',
  `platform_fee_frequency` enum('monthly','quarterly','annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annually' COMMENT 'How often the fee is charged',
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
DROP TABLE IF EXISTS `investment_action_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_action_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `scope` enum('account','portfolio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portfolio',
  `what_if_impact_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `trigger_config` json NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '100',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `investment_action_definitions_key_unique` (`key`),
  KEY `investment_action_definitions_source_index` (`source`),
  KEY `investment_action_definitions_is_enabled_index` (`is_enabled`),
  KEY `investment_action_definitions_sort_order_index` (`sort_order`)
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
DROP TABLE IF EXISTS `invoice_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_sequences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `next_value` bigint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `subscription_id` bigint unsigned DEFAULT NULL,
  `invoice_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','issued','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issued',
  `subtotal_amount` int NOT NULL,
  `discount_amount` int NOT NULL DEFAULT '0',
  `tax_amount` int NOT NULL DEFAULT '0',
  `total_amount` int NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBP',
  `discount_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_cycle` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `next_renewal_date` date DEFAULT NULL,
  `issued_at` timestamp NOT NULL,
  `pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address` text COLLATE utf8mb4_unicode_ci,
  `billing_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_user_id_foreign` (`user_id`),
  KEY `invoices_payment_id_foreign` (`payment_id`),
  KEY `invoices_subscription_id_foreign` (`subscription_id`),
  CONSTRAINT `invoices_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `lasting_powers_of_attorney`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lasting_powers_of_attorney` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `lpa_type` enum('property_financial','health_welfare') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','completed','registered','uploaded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `source` enum('created','uploaded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'created',
  `donor_full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_date_of_birth` date DEFAULT NULL,
  `donor_address_line_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_address_line_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_address_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_address_county` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_address_postcode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attorney_decision_type` enum('jointly','jointly_and_severally','jointly_for_some') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jointly_for_some_details` text COLLATE utf8mb4_unicode_ci,
  `when_attorneys_can_act` enum('while_has_capacity','only_when_lost_capacity') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preferences` text COLLATE utf8mb4_unicode_ci,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `life_sustaining_treatment` enum('can_consent','cannot_consent') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_provider_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_provider_address` text COLLATE utf8mb4_unicode_ci,
  `certificate_provider_relationship` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_provider_known_years` int unsigned DEFAULT NULL,
  `certificate_provider_professional_details` text COLLATE utf8mb4_unicode_ci,
  `registration_date` date DEFAULT NULL,
  `opg_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_registered_with_opg` tinyint(1) NOT NULL DEFAULT '0',
  `document_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lasting_powers_of_attorney_document_id_foreign` (`document_id`),
  KEY `lasting_powers_of_attorney_user_id_lpa_type_index` (`user_id`,`lpa_type`),
  KEY `lasting_powers_of_attorney_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `lasting_powers_of_attorney_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lasting_powers_of_attorney_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  UNIQUE KEY `letters_to_spouse_user_id_unique` (`user_id`),
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
DROP TABLE IF EXISTS `life_event_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_event_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `life_event_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `allocation_type` enum('income','expense') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'income',
  `allocation_step` enum('goals','isa','pension','bond','cash') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` bigint unsigned DEFAULT NULL,
  `account_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggested_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `rationale` text COLLATE utf8mb4_unicode_ci,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `life_event_allocations_life_event_id_index` (`life_event_id`),
  KEY `life_event_allocations_user_id_life_event_id_index` (`user_id`,`life_event_id`),
  KEY `idx_life_event_allocations_account_id` (`account_id`),
  CONSTRAINT `life_event_allocations_life_event_id_foreign` FOREIGN KEY (`life_event_id`) REFERENCES `life_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `life_event_allocations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `life_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `event_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` enum('inheritance','gift_received','bonus','redundancy_payment','property_sale','business_sale','pension_lump_sum','lottery_windfall','large_purchase','home_improvement','wedding','education_fees','gift_given','medical_expense','custom_income','custom_expense','divorce','marriage','new_child','job_loss','income_change') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `amount` decimal(15,2) NOT NULL,
  `impact_type` enum('income','expense') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_date` date NOT NULL,
  `certainty` enum('confirmed','likely','possible','speculative') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'likely',
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_in_projection` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_household_view` tinyint(1) NOT NULL DEFAULT '1',
  `ownership_type` enum('individual','joint') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `status` enum('expected','confirmed','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'expected',
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `life_events_joint_owner_id_foreign` (`joint_owner_id`),
  KEY `life_events_user_id_status_index` (`user_id`,`status`),
  KEY `life_events_user_id_expected_date_index` (`user_id`,`expected_date`),
  KEY `life_events_user_id_impact_type_index` (`user_id`,`impact_type`),
  CONSTRAINT `life_events_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
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
  `joint_life` tinyint(1) NOT NULL DEFAULT '0',
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
DROP TABLE IF EXISTS `lifecycle_email_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lifecycle_email_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `campaign` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` timestamp NOT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `action_taken` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lifecycle_email_log_user_id_campaign_unique` (`user_id`,`campaign`),
  KEY `lifecycle_email_log_campaign_sent_at_index` (`campaign`,`sent_at`),
  CONSTRAINT `lifecycle_email_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `successful` tinyint(1) NOT NULL DEFAULT '0',
  `failure_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `login_attempts_email_created_at_index` (`email`,`created_at`),
  KEY `login_attempts_ip_address_created_at_index` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lpa_attorneys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lpa_attorneys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lasting_power_of_attorney_id` bigint unsigned NOT NULL,
  `attorney_type` enum('primary','replacement') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address_line_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_county` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_postcode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relationship_to_donor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lpa_attorneys_lasting_power_of_attorney_id_attorney_type_index` (`lasting_power_of_attorney_id`,`attorney_type`),
  CONSTRAINT `lpa_attorneys_lasting_power_of_attorney_id_foreign` FOREIGN KEY (`lasting_power_of_attorney_id`) REFERENCES `lasting_powers_of_attorney` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lpa_notification_persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lpa_notification_persons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lasting_power_of_attorney_id` bigint unsigned NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address_line_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_county` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_postcode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lpa_notification_persons_lasting_power_of_attorney_id_index` (`lasting_power_of_attorney_id`),
  CONSTRAINT `lpa_notification_persons_lasting_power_of_attorney_id_foreign` FOREIGN KEY (`lasting_power_of_attorney_id`) REFERENCES `lasting_powers_of_attorney` (`id`) ON DELETE CASCADE
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
  `cache_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculated_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monte_carlo_cache_cache_key_unique` (`cache_key`),
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
  `mortgage_account_number` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mortgage_type` enum('repayment','interest_only','mixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `repayment_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of mortgage on repayment basis (0-100)',
  `interest_only_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of mortgage on interest-only basis (0-100)',
  `original_loan_amount` decimal(15,2) DEFAULT NULL,
  `outstanding_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `interest_rate` decimal(8,4) DEFAULT NULL,
  `rate_type` enum('fixed','variable','tracker','discount','mixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
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
  `ownership_type` enum('individual','joint','tenants_in_common','trust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
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
DROP TABLE IF EXISTS `news_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'The Fynla Team',
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `news_articles_slug_unique` (`slug`),
  KEY `news_articles_status_published_at_index` (`status`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news_subscribers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `confirmation_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'news_hub',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `news_subscribers_email_unique` (`email`),
  UNIQUE KEY `news_subscribers_confirmation_token_unique` (`confirmation_token`),
  KEY `news_subscribers_unsubscribed_at_confirmed_at_index` (`unsubscribed_at`,`confirmed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `policy_renewals` tinyint(1) NOT NULL DEFAULT '1',
  `goal_milestones` tinyint(1) NOT NULL DEFAULT '1',
  `contribution_reminders` tinyint(1) NOT NULL DEFAULT '1',
  `market_updates` tinyint(1) NOT NULL DEFAULT '0',
  `fyn_daily_insight` tinyint(1) NOT NULL DEFAULT '1',
  `security_alerts` tinyint(1) NOT NULL DEFAULT '1',
  `payment_alerts` tinyint(1) NOT NULL DEFAULT '1',
  `mortgage_rate_alerts` tinyint(1) NOT NULL DEFAULT '1',
  `estate_alerts` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `lifecycle_empty_trialer` tinyint(1) NOT NULL DEFAULT '1',
  `lifecycle_engaged_trialer` tinyint(1) NOT NULL DEFAULT '1',
  `lifecycle_cancelled_trialer` tinyint(1) NOT NULL DEFAULT '1',
  `lifecycle_churned_subscriber` tinyint(1) NOT NULL DEFAULT '1',
  `lifecycle_lapsed_subscriber` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_preferences_user_id_unique` (`user_id`),
  CONSTRAINT `notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `occupation_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `occupation_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `soc_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SOC 2020 4-digit unit group code',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Job title or occupation name',
  `unit_group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOC 2020 unit group description',
  `minor_group` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOC 2020 minor group (3-digit)',
  `sub_major_group` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOC 2020 sub-major group (2-digit)',
  `major_group` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOC 2020 major group (1-digit)',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is this the primary title for the SOC code',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `occupation_codes_soc_code_index` (`soc_code`),
  FULLTEXT KEY `occupation_codes_title_fulltext` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_progress` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `focus_area` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_code` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_code_resend_count` tinyint unsigned NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `mfa_verified_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `revolut_order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBP',
  `status` enum('pending','completed','failed','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_cycle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upgrade_from_plan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_code_id` bigint unsigned DEFAULT NULL,
  `discount_amount` int NOT NULL DEFAULT '0',
  `invoice_id` bigint unsigned DEFAULT NULL,
  `revolut_subscription_payment` tinyint(1) NOT NULL DEFAULT '0',
  `revolut_payment_data` json DEFAULT NULL,
  `awin_order_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `awin_cks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `awin_customer_acquisition` enum('new','existing') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `awin_fired_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_subscription_id_foreign` (`subscription_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  KEY `payments_discount_code_id_foreign` (`discount_code_id`),
  KEY `payments_invoice_id_foreign` (`invoice_id`),
  KEY `payments_awin_order_ref_index` (`awin_order_ref`),
  CONSTRAINT `payments_discount_code_id_foreign` FOREIGN KEY (`discount_code_id`) REFERENCES `discount_codes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
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
  `plan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_cycle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referral_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signup_source` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pending_registrations_email_unique` (`email`),
  KEY `pending_registrations_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pension_input_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pension_input_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tax_year` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pension_input_amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pension_input_history_user_id_tax_year_unique` (`user_id`,`tax_year`),
  KEY `pension_input_history_user_id_index` (`user_id`),
  CONSTRAINT `pension_input_history_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `plan_action_funding_selections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_action_funding_selections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `plan_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_account_id` bigint unsigned NOT NULL DEFAULT '0',
  `funding_source_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `funding_source_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_funding_user_plan_category_target_unique` (`user_id`,`plan_type`,`action_category`,`target_account_id`),
  KEY `idx_plan_action_funding_selections_funding_source_id` (`funding_source_id`),
  KEY `pafs_funding_source_id_idx` (`funding_source_id`),
  KEY `pafs_funding_source_poly_idx` (`funding_source_type`,`funding_source_id`),
  CONSTRAINT `plan_action_funding_selections_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0',
  `config_data` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plan_configurations_is_active_index` (`is_active`)
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
DROP TABLE IF EXISTS `protection_action_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `protection_action_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `scope` enum('account','portfolio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portfolio',
  `what_if_impact_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `trigger_config` json NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '100',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `protection_action_definitions_key_unique` (`key`),
  KEY `protection_action_definitions_source_index` (`source`),
  KEY `protection_action_definitions_is_enabled_index` (`is_enabled`),
  KEY `protection_action_definitions_sort_order_index` (`sort_order`)
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
  `death_in_service_multiple` decimal(5,2) DEFAULT NULL,
  `group_ip_benefit_percent` decimal(5,2) DEFAULT NULL,
  `group_ip_benefit_months` int DEFAULT NULL,
  `group_ip_definition` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_ci_amount` decimal(15,2) DEFAULT NULL,
  `has_employer_pmi` tinyint(1) NOT NULL DEFAULT '0',
  `employer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `health_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good',
  `has_no_policies` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `protection_profiles_user_id_unique` (`user_id`),
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
  `recommended_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
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
DROP TABLE IF EXISTS `referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id` bigint unsigned NOT NULL,
  `referee_id` bigint unsigned DEFAULT NULL,
  `referral_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referee_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','registered','converted','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `bonus_applied` tinyint(1) NOT NULL DEFAULT '0',
  `referred_at` timestamp NOT NULL,
  `registered_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referrals_referee_id_foreign` (`referee_id`),
  KEY `referrals_referrer_id_referee_email_index` (`referrer_id`,`referee_email`),
  KEY `referrals_referral_code_index` (`referral_code`),
  CONSTRAINT `referrals_referee_id_foreign` FOREIGN KEY (`referee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referrals_referrer_id_foreign` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `renewal_reminder_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `renewal_reminder_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint unsigned NOT NULL,
  `period_end_date` date NOT NULL,
  `sent_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `renewal_reminder_log_subscription_id_period_end_date_unique` (`subscription_id`,`period_end_date`),
  CONSTRAINT `renewal_reminder_log_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retirement_action_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `retirement_action_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `scope` enum('account','portfolio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portfolio',
  `what_if_impact_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `trigger_config` json NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '100',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retirement_action_definitions_key_unique` (`key`),
  KEY `retirement_action_definitions_source_index` (`source`),
  KEY `retirement_action_definitions_is_enabled_index` (`is_enabled`),
  KEY `retirement_action_definitions_sort_order_index` (`sort_order`)
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
  `care_cost_annual` decimal(10,2) DEFAULT NULL,
  `care_start_age` int DEFAULT NULL,
  `prior_year_unused_allowance` json DEFAULT NULL,
  `spouse_life_expectancy` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retirement_profiles_user_id_unique` (`user_id`),
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
  UNIQUE KEY `risk_profiles_user_id_unique` (`user_id`),
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
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `savings_account_value_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_account_value_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `savings_account_id` bigint unsigned NOT NULL,
  `column_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(14,2) NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GBP',
  `value_gbp` decimal(14,2) DEFAULT NULL,
  `taken_at` timestamp NOT NULL,
  `trigger_reason` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ingest_source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_acct_snap_acct_col_taken_idx` (`savings_account_id`,`column_name`,`taken_at`),
  CONSTRAINT `savings_account_value_snapshots_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `savings_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `account_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `joint_owner_id` bigint unsigned DEFAULT NULL,
  `beneficiary_id` bigint unsigned DEFAULT NULL,
  `beneficiary_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `beneficiary_dob` date DEFAULT NULL,
  `include_in_retirement` tinyint(1) NOT NULL DEFAULT '0',
  `ownership_type` enum('individual','joint','trust') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `account_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `balance_gbp` decimal(12,2) DEFAULT NULL,
  `balance_gbp_calculated_at` timestamp NULL DEFAULT NULL,
  `interest_rate` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `annual_interest_projected_gbp` decimal(12,2) DEFAULT NULL,
  `annual_interest_projected_gbp_calculated_at` timestamp NULL DEFAULT NULL,
  `rate_valid_until` date DEFAULT NULL,
  `access_type` enum('immediate','notice','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'immediate',
  `notice_period_days` int DEFAULT NULL,
  `maturity_date` date DEFAULT NULL,
  `is_isa` tinyint(1) NOT NULL DEFAULT '0',
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'United Kingdom' COMMENT 'Country where account is held - hidden when is_isa = true',
  `is_emergency_fund` tinyint(1) NOT NULL DEFAULT '0',
  `isa_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isa_subscription_year` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isa_subscription_amount` decimal(15,2) DEFAULT NULL,
  `isa_allowance_used_pct` decimal(5,2) DEFAULT NULL,
  `isa_allowance_used_pct_calculated_at` timestamp NULL DEFAULT NULL,
  `regular_contribution_amount` decimal(12,2) DEFAULT NULL,
  `contribution_frequency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  CONSTRAINT `savings_accounts_joint_owner_id_foreign` FOREIGN KEY (`joint_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `savings_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `savings_action_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_action_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `scope` enum('account','portfolio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portfolio',
  `what_if_impact_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `trigger_config` json NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '100',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `savings_action_definitions_key_unique` (`key`),
  KEY `savings_action_definitions_source_index` (`source`),
  KEY `savings_action_definitions_is_enabled_index` (`is_enabled`),
  KEY `savings_action_definitions_sort_order_index` (`sort_order`)
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
  `rate_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(5,4) NOT NULL,
  `tax_year` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `state_pensions_user_id_unique` (`user_id`),
  KEY `state_pensions_user_id_index` (`user_id`),
  CONSTRAINT `state_pensions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monthly_price` int NOT NULL,
  `yearly_price` int NOT NULL,
  `launch_monthly_price` int DEFAULT NULL,
  `launch_yearly_price` int DEFAULT NULL,
  `trial_days` int NOT NULL DEFAULT '7',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `features` json DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `revolut_plan_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revolut_monthly_variation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revolut_yearly_variation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `plan` enum('student','standard','family','pro','free','tier1','tier2','tier3') COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_cycle` enum('monthly','yearly') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('trialing','active','cancelled','expired','past_due') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trialing',
  `trial_started_at` timestamp NULL DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `current_period_start` timestamp NULL DEFAULT NULL,
  `current_period_end` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_retention_starts_at` timestamp NULL DEFAULT NULL,
  `revolut_order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revolut_subscription_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revolut_plan_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revolut_plan_variation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT '1',
  `payment_method_saved` tinyint(1) NOT NULL DEFAULT '0',
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_user_id_foreign` (`user_id`),
  KEY `idx_subs_status_trial` (`status`,`trial_ends_at`),
  KEY `idx_subs_status_period` (`status`,`current_period_end`),
  KEY `idx_subs_status_cancelled` (`status`,`cancelled_at`),
  CONSTRAINT `subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_action_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_action_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('critical','high','medium','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `scope` enum('account','portfolio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portfolio',
  `what_if_impact_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `trigger_config` json NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '100',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_action_definitions_key_unique` (`key`),
  KEY `tax_action_definitions_source_index` (`source`),
  KEY `tax_action_definitions_is_enabled_index` (`is_enabled`),
  KEY `tax_action_definitions_sort_order_index` (`sort_order`)
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
DROP TABLE IF EXISTS `tax_strategy_household_inputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_strategy_household_inputs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `spouse_annual_income` decimal(12,2) DEFAULT NULL,
  `spouse_employment_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_isa_balance` decimal(12,2) DEFAULT NULL,
  `spouse_psa_band` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_unrealised_gains` decimal(12,2) DEFAULT NULL,
  `spouse_annual_dividends` decimal(12,2) DEFAULT NULL,
  `spouse_pension_input_annual` decimal(12,2) DEFAULT NULL,
  `spouse_existing_isa_balance` decimal(12,2) DEFAULT NULL,
  `spouse_existing_savings_balance` decimal(12,2) DEFAULT NULL,
  `spouse_existing_investment_balance` decimal(12,2) DEFAULT NULL,
  `spouse_existing_dividend_holdings_value` decimal(12,2) DEFAULT NULL,
  `spouse_existing_pension_balance` decimal(12,2) DEFAULT NULL COMMENT 'Non-working spouse current personal-pension pot value (single_earner_couple path).',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_strategy_household_inputs_user_id_unique` (`user_id`),
  CONSTRAINT `tax_strategy_household_inputs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tier_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tier_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tier` enum('free','tier1','tier2','tier3') COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_monthly_pence` int unsigned NOT NULL DEFAULT '0',
  `price_annual_pence` int unsigned NOT NULL DEFAULT '0',
  `revolut_plan_variation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capability_matrix` json NOT NULL,
  `count_caps` json NOT NULL,
  `document_upload_allowance` int unsigned NOT NULL DEFAULT '0',
  `document_storage_gb` decimal(8,2) DEFAULT NULL,
  `fyn_weekly_token_budget` int unsigned NOT NULL,
  `fyn_daily_hard_backstop` int unsigned NOT NULL,
  `currency_display_mode` enum('gbp_only','user_choice') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gbp_only',
  `snapshot_surfacing_window_days` int unsigned NOT NULL DEFAULT '90',
  `open_api_affordance` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tier_configurations_tier_unique` (`tier`),
  KEY `tier_configurations_updated_by_foreign` (`updated_by`),
  CONSTRAINT `tier_configurations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
  `trust_type` enum('bare','interest_in_possession','discretionary','accumulation_maintenance','life_insurance','discounted_gift','loan','mixed','settlor_interested','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `other_type_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Description when trust_type is other',
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Country where trust is located',
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
  `deleted_at` timestamp NULL DEFAULT NULL,
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
  `assumption_type` enum('pensions','investments') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inflation_rate` decimal(5,2) DEFAULT NULL,
  `return_rate` decimal(5,2) DEFAULT NULL,
  `compound_periods` int DEFAULT NULL,
  `property_growth_rate` decimal(5,2) DEFAULT NULL,
  `investment_growth_method` enum('monte_carlo','custom') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monte_carlo',
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
  `consent_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `consented` tinyint(1) NOT NULL DEFAULT '0',
  `consented_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `device_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `is_advisor` tinyint(1) NOT NULL DEFAULT '0',
  `is_preview_user` tinyint(1) NOT NULL DEFAULT '0',
  `plan` enum('free','student','standard','family','pro','tier1','tier2','tier3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `tier` enum('free','tier1','tier2','tier3') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `deletion_scheduled_for` timestamp NULL DEFAULT NULL,
  `deletion_reason` enum('user_requested','trial_expired','subscription_cancelled_grace_ended','admin_initiated','legacy_purged') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deletion_source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `purge_eligible_at` timestamp NULL DEFAULT NULL,
  `revolut_customer_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referral_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signup_source` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marriage_allowance_eligible` tinyint(1) DEFAULT NULL COMMENT 'Set true when spouse_works=no during savetax campaign onboarding',
  `household_calculation_mode` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'single | dual_earner | single_earner_couple — set by capture_spouse_work_status tool',
  `referred_by_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preview_persona_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `mfa_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mfa_recovery_codes` json DEFAULT NULL,
  `mfa_confirmed_at` timestamp NULL DEFAULT NULL,
  `failed_login_count` int NOT NULL DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_failed_login_at` timestamp NULL DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `date_of_birth` date DEFAULT NULL,
  `life_expectancy_override` int DEFAULT NULL,
  `gender` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marital_status` enum('single','married','civil_partnership','divorced','widowed') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domicile_status` enum('uk_domiciled','non_uk_domiciled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'UK residence-based domicile status',
  `country_of_birth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Country where user was born',
  `uk_arrival_date` date DEFAULT NULL COMMENT 'Date user arrived in UK (for non-UK born individuals)',
  `years_uk_resident` int DEFAULT NULL COMMENT 'Calculated: number of years UK resident',
  `deemed_domicile_date` date DEFAULT NULL COMMENT 'Date user became deemed domiciled under 15/20 year rule',
  `spouse_id` bigint unsigned DEFAULT NULL,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT '0',
  `onboarding_focus_area` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_current_step` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_skipped_steps` json DEFAULT NULL,
  `onboarding_started_at` timestamp NULL DEFAULT NULL,
  `onboarding_completed_at` timestamp NULL DEFAULT NULL,
  `onboarding_mode` enum('quick','full') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_asset_flags` json DEFAULT NULL,
  `onboarding_fyn_step` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `journey_states` json DEFAULT NULL,
  `life_stage` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `life_stage_completed_steps` json DEFAULT NULL,
  `journey_selections` json DEFAULT NULL,
  `dismissed_prompts` json DEFAULT NULL,
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
  `university` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `industry` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_status` enum('employed','full_time','part_time','self_employed','retired','unemployed','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `income_needs_update` tinyint(1) NOT NULL DEFAULT '0',
  `previous_employment_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_retirement_age` tinyint unsigned DEFAULT NULL,
  `retirement_date` date DEFAULT NULL,
  `annual_employment_income` decimal(15,2) DEFAULT NULL,
  `annual_self_employment_income` decimal(15,2) DEFAULT NULL,
  `annual_rental_income` decimal(15,2) DEFAULT NULL,
  `annual_dividend_income` decimal(15,2) DEFAULT NULL,
  `annual_interest_income` decimal(12,2) DEFAULT NULL,
  `annual_other_income` decimal(15,2) DEFAULT NULL,
  `payday_day_of_month` tinyint unsigned DEFAULT NULL,
  `annual_trust_income` decimal(15,2) DEFAULT NULL,
  `is_registered_blind` tinyint(1) NOT NULL DEFAULT '0',
  `annual_charitable_donations` decimal(15,2) DEFAULT NULL,
  `is_gift_aid` tinyint(1) NOT NULL DEFAULT '0',
  `monthly_expenditure` decimal(12,2) DEFAULT NULL,
  `annual_expenditure` decimal(12,2) DEFAULT NULL,
  `retired_budget_overrides` json DEFAULT NULL,
  `widowed_budget_overrides` json DEFAULT NULL,
  `food_groceries` decimal(10,2) NOT NULL DEFAULT '0.00',
  `transport_fuel` decimal(10,2) NOT NULL DEFAULT '0.00',
  `healthcare_medical` decimal(10,2) NOT NULL DEFAULT '0.00',
  `insurance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `mobile_phones` decimal(10,2) NOT NULL DEFAULT '0.00',
  `internet_tv` decimal(10,2) NOT NULL DEFAULT '0.00',
  `subscriptions` decimal(10,2) NOT NULL DEFAULT '0.00',
  `clothing_personal_care` decimal(10,2) NOT NULL DEFAULT '0.00',
  `entertainment_dining` decimal(10,2) NOT NULL DEFAULT '0.00',
  `holidays_travel` decimal(10,2) NOT NULL DEFAULT '0.00',
  `pets` decimal(10,2) NOT NULL DEFAULT '0.00',
  `childcare` decimal(10,2) NOT NULL DEFAULT '0.00',
  `school_fees` decimal(10,2) NOT NULL DEFAULT '0.00',
  `school_lunches` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Monthly school lunch costs',
  `school_extras` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Uniforms, trips, equipment etc.',
  `university_fees` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Includes residential, books and any other costs',
  `children_activities` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gifts_charity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `charitable_bequest` tinyint(1) DEFAULT NULL,
  `regular_savings` decimal(10,2) NOT NULL DEFAULT '0.00',
  `other_expenditure` decimal(10,2) NOT NULL DEFAULT '0.00',
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_lifecycle_test_user` tinyint(1) NOT NULL DEFAULT '0',
  `onboarding_fyn_path` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_fyn_selection` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_fyn_context` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_referral_code_unique` (`referral_code`),
  KEY `users_spouse_id_index` (`spouse_id`),
  KEY `users_household_id_index` (`household_id`),
  KEY `preview_user_persona_idx` (`is_preview_user`,`preview_persona_id`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_is_lifecycle_test_user_index` (`is_lifecycle_test_user`),
  KEY `users_signup_source_idx` (`signup_source`),
  KEY `users_deletion_scheduled_for_index` (`deletion_scheduled_for`),
  KEY `users_purge_eligible_at_index` (`purge_eligible_at`),
  KEY `users_tier_index` (`tier`),
  CONSTRAINT `users_household_id_foreign` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_spouse_id_foreign` FOREIGN KEY (`spouse_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `what_if_scenarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `what_if_scenarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scenario_type` enum('retirement','property','family','income','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `parameters` json NOT NULL,
  `affected_modules` json NOT NULL,
  `created_via` enum('ai_chat','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `ai_narrative` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `what_if_scenarios_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `what_if_scenarios_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `will_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `will_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `will_id` bigint unsigned DEFAULT NULL,
  `mirror_document_id` bigint unsigned DEFAULT NULL,
  `will_type` enum('simple','mirror') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','complete') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `testator_full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `testator_address` text COLLATE utf8mb4_unicode_ci,
  `testator_date_of_birth` date DEFAULT NULL,
  `testator_occupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `executors` json DEFAULT NULL,
  `guardians` json DEFAULT NULL,
  `specific_gifts` json DEFAULT NULL,
  `residuary_estate` json DEFAULT NULL,
  `funeral_preference` enum('burial','cremation','no_preference') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funeral_wishes_notes` text COLLATE utf8mb4_unicode_ci,
  `digital_executor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `digital_assets_instructions` text COLLATE utf8mb4_unicode_ci,
  `survivorship_days` int unsigned NOT NULL DEFAULT '28',
  `domicile_confirmed` enum('england_wales','scotland','northern_ireland','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_date` date DEFAULT NULL,
  `witnesses` json DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `last_edited_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `will_documents_will_id_foreign` (`will_id`),
  KEY `will_documents_mirror_document_id_foreign` (`mirror_document_id`),
  KEY `will_documents_user_id_index` (`user_id`),
  KEY `will_documents_status_index` (`status`),
  CONSTRAINT `will_documents_mirror_document_id_foreign` FOREIGN KEY (`mirror_document_id`) REFERENCES `will_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `will_documents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `will_documents_will_id_foreign` FOREIGN KEY (`will_id`) REFERENCES `wills` (`id`) ON DELETE SET NULL
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
  `residuary_beneficiary` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Free-text residuary beneficiary name for AI-captured wills.',
  `guardian_for_minors` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Named guardian for any minor dependants.',
  `specific_gifts` text COLLATE utf8mb4_unicode_ci COMMENT 'Free-text list of specific gifts (item, recipient). Distinct from will_documents.specific_gifts which is a separate model.',
  `executor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `executor_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `will_last_updated` date DEFAULT NULL,
  `last_reviewed_date` date DEFAULT NULL,
  `will_document_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wills_user_id_idx` (`user_id`),
  KEY `wills_will_document_id_foreign` (`will_document_id`),
  CONSTRAINT `wills_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wills_will_document_id_foreign` FOREIGN KEY (`will_document_id`) REFERENCES `will_documents` (`id`) ON DELETE SET NULL
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
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2026_02_24_100001_create_subscription_plans_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2026_02_24_100002_add_revolut_ids_to_users_and_subscriptions',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2026_02_24_100003_add_cancelled_at_to_subscriptions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2026_02_24_100004_add_cancellation_reason_to_subscriptions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2026_02_24_100005_add_data_retention_starts_at_to_subscriptions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2026_02_24_100006_create_renewal_reminder_log_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2026_02_24_100007_add_description_to_payments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2026_02_24_100008_create_data_retention_email_log_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2026_02_24_100009_add_soft_deletes_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2026_02_24_120001_create_life_event_allocations_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2026_02_24_120002_update_life_event_allocations_columns',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2026_02_25_100001_add_columns_to_payments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2026_02_27_200001_create_ai_conversations_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2026_02_27_200002_create_ai_messages_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2026_02_27_200003_add_ai_chat_enabled_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2026_03_01_100001_create_plan_configurations_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2026_03_02_072041_add_recommended_amount_to_recommendation_tracking',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2026_03_03_000001_create_retirement_action_definitions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2026_03_04_000001_create_plan_action_funding_selections_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2026_03_05_000001_create_investment_action_definitions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2026_03_05_000002_create_protection_action_definitions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2026_03_07_100806_add_progressive_onboarding_fields_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2026_03_07_120001_add_life_expectancy_override_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2026_03_07_120002_add_care_cost_fields_to_retirement_profiles_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2026_03_07_120003_add_last_reviewed_date_to_wills_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2026_03_07_150001_create_tax_action_definitions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2026_03_07_200001_add_journey_fields_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2026_03_07_200002_expand_onboarding_focus_area_enum',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2026_03_10_200001_create_device_tokens_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2026_03_10_200002_create_notification_preferences_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2026_03_10_200003_add_device_id_to_user_sessions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2026_03_10_200004_add_mortgage_rate_alerts_to_notification_preferences',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2026_03_13_200002_fix_savings_accounts_joint_owner_foreign_key',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2026_03_14_000001_add_sub_type_to_holdings_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2026_03_14_100001_create_savings_action_definitions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2026_03_14_100002_create_goal_savings_account_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2026_03_14_100003_add_employer_benefits_to_protection_profiles',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2026_03_14_100004_add_joint_life_to_life_insurance_policies',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2026_03_14_100005_migrate_goal_savings_account_links',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2026_03_14_100006_add_estate_alerts_to_notification_preferences',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2026_03_15_074247_add_challenge_token_to_email_verification_codes_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2026_03_16_100001_create_lasting_powers_of_attorney_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2026_03_16_100002_create_lpa_attorneys_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (288,'2026_03_16_100003_create_lpa_notification_persons_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2026_03_16_200001_create_will_documents_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2026_03_16_200002_add_signature_and_witness_fields_to_will_documents_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2026_03_16_300001_add_rate_valid_until_to_savings_accounts_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2026_03_17_100001_add_life_stage_fields_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2026_03_17_100001_create_estate_action_definitions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2026_03_17_200001_add_is_advisor_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2026_03_17_200002_create_advisor_clients_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2026_03_17_200003_create_client_activities_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2026_03_18_100000_add_soft_deletes_to_key_models',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2026_03_18_100001_add_student_fields_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2026_03_18_100001_add_unique_constraints_to_has_one_tables',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2026_03_18_100002_fix_indexes_and_constraints',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (301,'2026_03_19_100000_add_income_definition_fields_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2026_03_20_074942_add_budget_overrides_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2026_03_20_100000_make_enum_columns_nullable',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2026_03_21_000001_add_new_life_event_types',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2026_03_21_000002_create_what_if_scenarios_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2026_03_21_214000_change_focus_area_to_varchar',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2026_03_25_164053_add_fee_fields_to_dc_pensions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2026_03_26_103410_add_premium_frequency_to_income_protection_policies_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2026_03_30_000001_add_soft_deletes_to_payments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2026_03_30_000002_add_family_to_subscriptions_plan_enum',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2026_03_30_000003_add_launch_price_columns_to_subscription_plans_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2026_03_31_144649_add_upgrade_from_plan_to_payments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2026_04_01_150000_create_ai_advice_log_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2026_04_01_160000_add_system_prompt_to_ai_messages_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2026_04_08_100001_create_discount_codes_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2026_04_08_100002_create_discount_code_usages_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2026_04_08_100003_create_invoices_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2026_04_08_100004_create_invoice_sequences_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2026_04_08_100005_add_subscription_and_discount_fields',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2026_04_08_143318_add_billing_address_to_invoices_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2026_04_08_150001_add_referral_columns_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2026_04_08_150002_create_referrals_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2026_04_08_150003_add_referral_code_to_pending_registrations_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2026_04_09_000001_add_family_to_users_plan_enum',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2026_04_09_120000_add_full_time_to_employment_status_enum',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2026_04_14_094042_create_notifications_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2026_04_14_122231_create_lifecycle_email_log_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2026_04_14_122345_create_feedback_responses_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2026_04_14_122424_add_user_id_and_metadata_to_discount_codes',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2026_04_14_122508_add_is_lifecycle_test_user_to_users',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2026_04_14_122545_add_lifecycle_columns_to_notification_preferences',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2026_04_14_122656_add_subscriptions_indexes',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2026_04_14_123409_add_lifecycle_welcome_to_discount_codes_type_enum',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2026_04_15_090000_add_onboarding_fyn_state_to_users',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (335,'2026_04_15_091500_add_civil_partnership_to_users_marital_status',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2026_04_15_153100_add_awin_tracking_to_payments_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2026_04_17_090001_create_insight_templates_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2026_04_17_090002_create_insight_articles_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2026_04_17_090003_create_insight_article_revisions_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2026_04_18_090000_expand_insight_article_categories',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2026_04_18_100000_add_authors_to_insight_articles_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2026_04_22_000001_add_persona_to_ai_messages',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2026_04_22_000002_add_persona_state_to_ai_conversations',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2026_04_22_000004_add_will_columns',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2026_04_25_000001_clear_stale_persona_state',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (347,'2026_04_25_000010_create_ai_daily_usage_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (348,'2026_04_25_000011_create_ai_request_idempotency_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (349,'2026_04_25_000012_create_ai_abort_events_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (350,'2026_04_25_000013_create_ai_audit_events_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (351,'2026_04_27_000001_create_eval_recording_tables',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (352,'2026_04_27_000002_add_remedial_report_to_eval_recording_sessions',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (353,'2026_04_27_100001_add_persona_columns_to_eval_recording_sessions',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (354,'2026_04_27_100002_add_engine_trace_to_eval_provider_runs',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (355,'2026_04_27_120000_create_news_articles_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (356,'2026_04_28_120000_create_news_subscribers_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (357,'2026_04_29_000001_add_signup_source_to_users_and_pending_registrations',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (358,'2026_05_01_120000_create_document_articles_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (359,'2026_05_02_000001_add_conversation_index_columns',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (360,'2026_05_03_000001_add_tax_strategy_columns_to_users',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (361,'2026_05_03_000002_add_salary_sacrifice_to_dc_pensions',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (362,'2026_05_03_000003_create_tax_strategy_household_inputs_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (363,'2026_05_04_000001_add_employer_ni_rebate_pct_to_dc_pensions',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (364,'2026_05_04_000002_add_spouse_existing_pension_balance_to_tax_strategy_household_inputs',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (365,'2026_05_05_000001_create_pension_input_history_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (366,'2026_05_05_000002_add_charitable_donations_to_users',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (367,'2026_05_06_000001_drop_is_eval_user_from_users',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (368,'2026_05_06_000002_rename_eval_user_id_to_preview_user_id',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (369,'2026_05_06_000003_add_operation_created_at_index_to_ai_audit_events',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (370,'2026_05_07_000001_add_deletion_tracking_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (371,'2026_05_07_000002_fix_life_events_joint_owner_id_fk',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (372,'2026_05_07_000003_backfill_legacy_purged_users',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (373,'2026_05_07_000004_create_account_deletion_reminder_log_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (374,'2026_05_07_000005_make_scrubbed_user_columns_nullable',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (375,'2026_05_12_000001_convert_users_expenditure_columns_to_decimal',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (376,'2026_05_12_000002_add_audit_logs_event_type_created_idx',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (378,'2026_05_15_100000_add_derived_columns_to_savings_accounts',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (379,'2026_05_15_100001_create_savings_account_value_snapshots_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (380,'2026_05_18_135313_add_assembled_context_to_ai_messages_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (381,'2026_05_17_100000_create_tier_configurations_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (382,'2026_05_17_100001_add_tier_to_users_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (383,'2026_05_17_100002_add_tier_keys_to_subscriptions_plan_enum',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (384,'2026_05_22_120000_create_currency_rates_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (385,'2026_05_23_080000_drop_ai_chat_enabled_from_users_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (386,'2026_05_23_080001_add_funding_source_index_to_plan_action_funding_selections',17);
