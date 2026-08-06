/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admin_artist_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_artist_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `artist_id` bigint unsigned NOT NULL,
  `assignment_role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager',
  `can_view` tinyint(1) NOT NULL DEFAULT '1',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_releases` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_team` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_splits` tinyint(1) NOT NULL DEFAULT '0',
  `assigned_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_artist_assignment_unique` (`user_id`,`artist_id`),
  KEY `admin_artist_assignments_artist_id_foreign` (`artist_id`),
  KEY `admin_artist_assignments_assigned_by_foreign` (`assigned_by`),
  CONSTRAINT `admin_artist_assignments_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_artist_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_artist_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_label_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_label_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `label_id` bigint unsigned NOT NULL,
  `assignment_role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager',
  `can_view` tinyint(1) NOT NULL DEFAULT '1',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_releases` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_team` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_splits` tinyint(1) NOT NULL DEFAULT '0',
  `assigned_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_label_assignment_unique` (`user_id`,`label_id`),
  KEY `admin_label_assignments_label_id_foreign` (`label_id`),
  KEY `admin_label_assignments_assigned_by_foreign` (`assigned_by`),
  CONSTRAINT `admin_label_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_label_assignments_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_label_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `artists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `artists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `stage_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'India',
  `timezone` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Kolkata',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `profile_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `account_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `kyc_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `can_receive_splits` tinyint(1) NOT NULL DEFAULT '1',
  `can_create_releases` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `artists_public_id_unique` (`public_id`),
  UNIQUE KEY `artists_slug_unique` (`slug`),
  KEY `artists_created_by_foreign` (`created_by`),
  KEY `artists_updated_by_foreign` (`updated_by`),
  KEY `artists_label_id_account_status_index` (`label_id`,`account_status`),
  KEY `artists_user_id_account_status_index` (`user_id`,`account_status`),
  KEY `artists_email_index` (`email`),
  KEY `artists_account_status_index` (`account_status`),
  KEY `artists_kyc_status_index` (`kyc_status`),
  CONSTRAINT `artists_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `artists_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `artists_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `artists_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `request_method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_url` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `audit_logs_public_id_unique` (`public_id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_module_action_index` (`module`,`action`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `catalogue_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catalogue_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_id` bigint unsigned NOT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_artist_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_artists` json DEFAULT NULL,
  `featuring_artists` json DEFAULT NULL,
  `label_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catalog_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_genre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_genre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `artwork_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `digital_release_date` date DEFAULT NULL,
  `release_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `track_count` int unsigned NOT NULL DEFAULT '0',
  `isrc_assigned_count` int unsigned NOT NULL DEFAULT '0',
  `store_ids` json DEFAULT NULL,
  `delivery_summary` json DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT '0',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catalogue_items_public_id_unique` (`public_id`),
  UNIQUE KEY `catalogue_items_release_id_unique` (`release_id`),
  KEY `catalogue_items_artist_id_release_status_index` (`artist_id`,`release_status`),
  KEY `catalogue_items_label_id_release_status_index` (`label_id`,`release_status`),
  KEY `catalogue_items_is_visible_digital_release_date_index` (`is_visible`,`digital_release_date`),
  KEY `catalogue_items_upc_index` (`upc`),
  KEY `catalogue_items_catalog_number_index` (`catalog_number`),
  CONSTRAINT `catalogue_items_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `catalogue_sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catalogue_sync_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_id` bigint unsigned NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT '1',
  `message` text COLLATE utf8mb4_unicode_ci,
  `performed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catalogue_sync_logs_public_id_unique` (`public_id`),
  KEY `catalogue_sync_logs_release_id_created_at_index` (`release_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `catalogue_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catalogue_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `from_owner_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_owner_id` bigint unsigned DEFAULT NULL,
  `to_owner_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_owner_id` bigint unsigned DEFAULT NULL,
  `from_user_id` bigint unsigned DEFAULT NULL,
  `to_user_id` bigint unsigned DEFAULT NULL,
  `revenue_scope` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'future_only',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `transferred_by` bigint unsigned DEFAULT NULL,
  `transferred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catalogue_transfers_public_id_unique` (`public_id`),
  KEY `catalogue_transfers_entity_type_entity_id_index` (`entity_type`,`entity_id`),
  KEY `catalogue_transfers_transfer_type_index` (`transfer_type`),
  KEY `catalogue_transfers_entity_type_index` (`entity_type`),
  KEY `catalogue_transfers_entity_id_index` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contributors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contributors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'India',
  `ipi_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isni` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_role` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_receive_splits` tinyint(1) NOT NULL DEFAULT '1',
  `has_dashboard_access` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contributors_public_id_unique` (`public_id`),
  KEY `contributors_created_by_foreign` (`created_by`),
  KEY `contributors_updated_by_foreign` (`updated_by`),
  KEY `contributors_artist_id_status_index` (`artist_id`,`status`),
  KEY `contributors_user_id_status_index` (`user_id`,`status`),
  KEY `contributors_email_index` (`email`),
  KEY `contributors_ipi_number_index` (`ipi_number`),
  KEY `contributors_isni_index` (`isni`),
  KEY `contributors_primary_role_index` (`primary_role`),
  KEY `contributors_status_index` (`status`),
  CONSTRAINT `contributors_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributors_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributors_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contributors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `distribution_stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distribution_stores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `default_selected` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `distribution_stores_slug_unique` (`slug`),
  KEY `distribution_stores_is_active_sort_order_index` (`is_active`,`sort_order`),
  KEY `distribution_stores_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `identifier_assignment_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `identifier_assignment_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_id` bigint unsigned DEFAULT NULL,
  `track_id` bigint unsigned DEFAULT NULL,
  `performed_by` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier_assignment_logs_public_id_unique` (`public_id`),
  KEY `identifier_assignment_logs_identifier_type_identifier_code_index` (`identifier_type`,`identifier_code`),
  KEY `identifier_assignment_logs_release_id_index` (`release_id`),
  KEY `identifier_assignment_logs_track_id_index` (`track_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_errors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `revenue_import_id` bigint unsigned NOT NULL,
  `revenue_row_id` bigint unsigned DEFAULT NULL,
  `source_row_number` int unsigned DEFAULT NULL,
  `error_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_data` json DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT '0',
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_errors_resolved_by_foreign` (`resolved_by`),
  KEY `import_errors_import_resolved_index` (`revenue_import_id`,`is_resolved`),
  CONSTRAINT `import_errors_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `import_errors_revenue_import_id_foreign` FOREIGN KEY (`revenue_import_id`) REFERENCES `revenue_imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(18,4) NOT NULL DEFAULT '1.0000',
  `rate` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  KEY `invoice_items_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `withdrawal_id` bigint unsigned DEFAULT NULL,
  `wallet_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `invoice_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'withdrawal',
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `subtotal` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `gst_percentage` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `gst_amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `tds_percentage` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `tds_amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `total_amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `net_payable` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issued',
  `billing_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address` text COLLATE utf8mb4_unicode_ci,
  `gstin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pan` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_public_id_unique` (`public_id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_wallet_id_foreign` (`wallet_id`),
  KEY `invoices_user_id_foreign` (`user_id`),
  KEY `invoices_created_by_foreign` (`created_by`),
  KEY `invoices_updated_by_foreign` (`updated_by`),
  KEY `invoices_label_id_status_index` (`label_id`,`status`),
  KEY `invoices_withdrawal_id_status_index` (`withdrawal_id`,`status`),
  KEY `invoices_invoice_date_status_index` (`invoice_date`,`status`),
  CONSTRAINT `invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_withdrawal_id_foreign` FOREIGN KEY (`withdrawal_id`) REFERENCES `withdrawals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `isrc_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `isrc_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_code` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registrant_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_year` smallint unsigned NOT NULL,
  `designation_code` int unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `track_id` bigint unsigned DEFAULT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `isrc_codes_public_id_unique` (`public_id`),
  UNIQUE KEY `isrc_codes_code_unique` (`code`),
  KEY `isrc_codes_status_reference_year_index` (`status`,`reference_year`),
  KEY `isrc_codes_track_id_index` (`track_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `labels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_label_id` bigint unsigned DEFAULT NULL,
  `label_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'label',
  `public_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'India',
  `timezone` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Kolkata',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `payout_cycle` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `minimum_withdrawal_amount` decimal(12,2) NOT NULL DEFAULT '5000.00',
  `royalty_share_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `parent_commission_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `can_access_catalogue` tinyint(1) NOT NULL DEFAULT '1',
  `can_access_royalties` tinyint(1) NOT NULL DEFAULT '1',
  `can_access_reports` tinyint(1) NOT NULL DEFAULT '1',
  `can_access_wallet` tinyint(1) NOT NULL DEFAULT '1',
  `can_withdraw` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `labels_public_id_unique` (`public_id`),
  UNIQUE KEY `labels_slug_unique` (`slug`),
  KEY `labels_created_by_foreign` (`created_by`),
  KEY `labels_updated_by_foreign` (`updated_by`),
  KEY `labels_status_country_index` (`status`,`country`),
  KEY `labels_email_index` (`email`),
  KEY `labels_status_index` (`status`),
  KEY `labels_parent_label_id_status_index` (`parent_label_id`,`status`),
  KEY `labels_label_type_index` (`label_type`),
  KEY `labels_payout_cycle_index` (`payout_cycle`),
  KEY `labels_user_id_index` (`user_id`),
  CONSTRAINT `labels_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `labels_parent_label_id_foreign` FOREIGN KEY (`parent_label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `labels_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `panel_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `panel_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `action_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `severity` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `related_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_id` bigint unsigned DEFAULT NULL,
  `data` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `panel_notifications_public_id_unique` (`public_id`),
  KEY `panel_notifications_user_id_read_at_index` (`user_id`,`read_at`),
  KEY `panel_notifications_related_type_related_id_index` (`related_type`,`related_id`),
  CONSTRAINT `panel_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payout_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payout_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `account_holder_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_number` text COLLATE utf8mb4_unicode_ci,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ifsc_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branch_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upi_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pan_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IN',
  `kyc_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `kyc_notes` text COLLATE utf8mb4_unicode_ci,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payout_profiles_public_id_unique` (`public_id`),
  UNIQUE KEY `payout_profiles_user_id_unique` (`user_id`),
  KEY `payout_profiles_kyc_status_index` (`kyc_status`),
  CONSTRAINT `payout_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_id` bigint unsigned NOT NULL,
  `track_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `release_activity_logs_public_id_unique` (`public_id`),
  KEY `release_activity_logs_release_id_created_at_index` (`release_id`,`created_at`),
  KEY `release_activity_logs_category_action_index` (`category`,`action`),
  CONSTRAINT `release_activity_logs_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_id` bigint unsigned NOT NULL,
  `old_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `changed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `release_status_logs_public_id_unique` (`public_id`),
  KEY `release_status_logs_changed_by_foreign` (`changed_by`),
  KEY `release_status_logs_release_id_created_at_index` (`release_id`,`created_at`),
  KEY `release_status_logs_release_id_new_status_index` (`release_id`,`new_status`),
  CONSTRAINT `release_status_logs_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `release_status_logs_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `release_store_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `release_store_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `release_id` bigint unsigned NOT NULL,
  `distribution_store_id` bigint unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `store_release_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_notes` text COLLATE utf8mb4_unicode_ci,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `live_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `takedown_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_note` text COLLATE utf8mb4_unicode_ci,
  `external_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taken_down_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `release_store_delivery_unique` (`release_id`,`distribution_store_id`),
  UNIQUE KEY `release_store_deliveries_public_id_unique` (`public_id`),
  KEY `release_store_deliveries_distribution_store_id_foreign` (`distribution_store_id`),
  KEY `release_store_deliveries_updated_by_foreign` (`updated_by`),
  KEY `release_store_deliveries_status_index` (`status`),
  CONSTRAINT `release_store_deliveries_distribution_store_id_foreign` FOREIGN KEY (`distribution_store_id`) REFERENCES `distribution_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `release_store_deliveries_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `release_store_deliveries_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `releases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `catalog_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `artist_id` bigint unsigned NOT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `release_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_artist_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `primary_artists` json DEFAULT NULL,
  `featuring_artist_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `featuring_artists` json DEFAULT NULL,
  `language` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_genre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_genre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upc_is_auto_generated` tinyint(1) NOT NULL DEFAULT '0',
  `original_release_date` date DEFAULT NULL,
  `digital_release_date` date DEFAULT NULL,
  `copyright_owner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright_year` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phonographic_owner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phonographic_year` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `artwork_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `review_notes` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `stores` json DEFAULT NULL,
  `excluded_store_ids` json DEFAULT NULL,
  `territories` json DEFAULT NULL,
  `worldwide` tinyint(1) NOT NULL DEFAULT '1',
  `release_timezone` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Kolkata',
  `pre_order` tinyint(1) NOT NULL DEFAULT '0',
  `wizard_step` tinyint unsigned NOT NULL DEFAULT '1',
  `completion_percentage` tinyint unsigned NOT NULL DEFAULT '0',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `live_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` bigint unsigned DEFAULT NULL,
  `upc_assigned_at` timestamp NULL DEFAULT NULL,
  `upc_assigned_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `releases_public_id_unique` (`public_id`),
  UNIQUE KEY `releases_catalog_number_unique` (`catalog_number`),
  UNIQUE KEY `releases_upc_unique` (`upc`),
  KEY `releases_created_by_foreign` (`created_by`),
  KEY `releases_updated_by_foreign` (`updated_by`),
  KEY `releases_artist_id_status_index` (`artist_id`,`status`),
  KEY `releases_label_id_status_index` (`label_id`,`status`),
  KEY `releases_digital_release_date_status_index` (`digital_release_date`,`status`),
  KEY `releases_release_type_index` (`release_type`),
  KEY `releases_status_index` (`status`),
  KEY `releases_approved_by_foreign` (`approved_by`),
  KEY `releases_rejected_by_foreign` (`rejected_by`),
  CONSTRAINT `releases_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `releases_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `releases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `releases_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `releases_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `releases_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_rows` bigint unsigned NOT NULL DEFAULT '0',
  `imported_rows` bigint unsigned NOT NULL DEFAULT '0',
  `duplicate_rows` bigint unsigned NOT NULL DEFAULT '0',
  `failed_rows` bigint unsigned NOT NULL DEFAULT '0',
  `error_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `column_map` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_imports_public_id_unique` (`public_id`),
  KEY `report_imports_status_created_at_index` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `row_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `report_import_id` bigint unsigned DEFAULT NULL,
  `release_id` bigint unsigned DEFAULT NULL,
  `track_id` bigint unsigned DEFAULT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `track_artist` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `album_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `album_artist` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isrc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cms` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_type` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `sale_month` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `streams` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `sale_units` decimal(20,4) NOT NULL DEFAULT '0.0000',
  `label_rate` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `earnings` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `raw_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `revenue_owner_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revenue_owner_id` bigint unsigned DEFAULT NULL,
  `mapping_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unmapped',
  `mapped_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_rows_row_hash_unique` (`row_hash`),
  KEY `report_rows_artist_id_sale_month_index` (`artist_id`,`sale_month`),
  KEY `report_rows_label_id_sale_month_index` (`label_id`,`sale_month`),
  KEY `report_rows_platform_sale_month_index` (`platform`,`sale_month`),
  KEY `report_rows_isrc_index` (`isrc`),
  KEY `report_rows_upc_index` (`upc`),
  KEY `report_rows_country_code_index` (`country_code`),
  KEY `report_rows_sale_date_index` (`sale_date`),
  KEY `report_rows_revenue_owner_type_index` (`revenue_owner_type`),
  KEY `report_rows_revenue_owner_id_index` (`revenue_owner_id`),
  KEY `report_rows_mapping_status_index` (`mapping_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `revenue_agreements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `revenue_agreements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `agreement_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `beneficiary_percentage` decimal(8,4) NOT NULL DEFAULT '100.0000',
  `company_retention_percentage` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `parent_commission_percentage` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `priority` int unsigned NOT NULL DEFAULT '100',
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `revenue_agreements_public_id_unique` (`public_id`),
  KEY `revenue_agreements_created_by_foreign` (`created_by`),
  KEY `revenue_agreements_updated_by_foreign` (`updated_by`),
  KEY `revenue_agreements_subject_status_index` (`subject_type`,`subject_id`,`status`),
  KEY `revenue_agreements_effective_dates_index` (`effective_from`,`effective_to`),
  KEY `revenue_agreements_subject_type_index` (`subject_type`),
  KEY `revenue_agreements_subject_id_index` (`subject_id`),
  KEY `revenue_agreements_status_index` (`status`),
  CONSTRAINT `revenue_agreements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `revenue_agreements_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `revenue_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `revenue_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `dsp_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statement_month` date DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stored_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_rows` bigint unsigned NOT NULL DEFAULT '0',
  `matched_rows` bigint unsigned NOT NULL DEFAULT '0',
  `unmatched_rows` bigint unsigned NOT NULL DEFAULT '0',
  `duplicate_rows` bigint unsigned NOT NULL DEFAULT '0',
  `error_rows` bigint unsigned NOT NULL DEFAULT '0',
  `gross_revenue` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `net_revenue` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'uploaded',
  `failure_reason` longtext COLLATE utf8mb4_unicode_ci,
  `column_mapping` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `imported_by` bigint unsigned DEFAULT NULL,
  `processing_started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `revenue_imports_public_id_unique` (`public_id`),
  KEY `revenue_imports_imported_by_foreign` (`imported_by`),
  KEY `revenue_imports_dsp_name_index` (`dsp_name`),
  KEY `revenue_imports_statement_month_index` (`statement_month`),
  KEY `revenue_imports_file_hash_index` (`file_hash`),
  KEY `revenue_imports_status_index` (`status`),
  CONSTRAINT `revenue_imports_imported_by_foreign` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `revenue_label_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `revenue_label_mappings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `revenue_label_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `royalty_percentage` decimal(8,4) NOT NULL DEFAULT '100.0000',
  `status` enum('unmapped','mapped','ignored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unmapped',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `revenue_label_mappings_normalized_name_unique` (`normalized_name`),
  KEY `revenue_label_mappings_label_id_foreign` (`label_id`),
  KEY `revenue_label_mappings_normalized_name_index` (`normalized_name`),
  CONSTRAINT `revenue_label_mappings_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `revenue_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `revenue_matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `revenue_row_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `track_id` bigint unsigned DEFAULT NULL,
  `release_id` bigint unsigned DEFAULT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `match_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unmatched',
  `confidence` decimal(5,2) NOT NULL DEFAULT '0.00',
  `is_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `revenue_matches_revenue_row_id_foreign` (`revenue_row_id`),
  KEY `revenue_matches_track_id_foreign` (`track_id`),
  KEY `revenue_matches_release_id_foreign` (`release_id`),
  KEY `revenue_matches_artist_id_foreign` (`artist_id`),
  KEY `revenue_matches_label_id_foreign` (`label_id`),
  KEY `revenue_matches_confirmed_by_foreign` (`confirmed_by`),
  KEY `revenue_matches_match_type_index` (`match_type`),
  KEY `revenue_matches_is_confirmed_index` (`is_confirmed`),
  CONSTRAINT `revenue_matches_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `revenue_matches_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `revenue_matches_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `revenue_matches_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `revenue_matches_revenue_row_id_foreign` FOREIGN KEY (`revenue_row_id`) REFERENCES `revenue_rows` (`id`) ON DELETE CASCADE,
  CONSTRAINT `revenue_matches_track_id_foreign` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `revenue_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `revenue_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `revenue_import_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `source_row_number` bigint unsigned DEFAULT NULL,
  `isrc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upc` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `release_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `artist_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_month` date DEFAULT NULL,
  `reporting_month` date DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `streams` bigint NOT NULL DEFAULT '0',
  `quantity` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `gross_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `net_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `source_row_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `raw_data` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `revenue_rows_revenue_import_id_foreign` (`revenue_import_id`),
  KEY `revenue_rows_isrc_index` (`isrc`),
  KEY `revenue_rows_upc_index` (`upc`),
  KEY `revenue_rows_store_name_index` (`store_name`),
  KEY `revenue_rows_country_code_index` (`country_code`),
  KEY `revenue_rows_sale_month_index` (`sale_month`),
  KEY `revenue_rows_source_row_hash_index` (`source_row_hash`),
  KEY `revenue_rows_match_status_index` (`match_status`),
  KEY `revenue_rows_reporting_month_index` (`reporting_month`),
  CONSTRAINT `revenue_rows_revenue_import_id_foreign` FOREIGN KEY (`revenue_import_id`) REFERENCES `revenue_imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `royalty_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `royalty_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `royalty_statement_id` bigint unsigned NOT NULL,
  `report_row_id` bigint unsigned NOT NULL,
  `release_id` bigint unsigned DEFAULT NULL,
  `track_id` bigint unsigned DEFAULT NULL,
  `gross_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `net_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `share_percentage` decimal(8,4) NOT NULL DEFAULT '100.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `royalty_statement_report_row_unique` (`royalty_statement_id`,`report_row_id`),
  UNIQUE KEY `royalty_allocations_public_id_unique` (`public_id`),
  KEY `royalty_allocations_release_id_index` (`release_id`),
  KEY `royalty_allocations_track_id_index` (`track_id`),
  KEY `royalty_allocations_report_row_id_foreign` (`report_row_id`),
  CONSTRAINT `royalty_allocations_report_row_id_foreign` FOREIGN KEY (`report_row_id`) REFERENCES `report_rows` (`id`) ON DELETE CASCADE,
  CONSTRAINT `royalty_allocations_royalty_statement_id_foreign` FOREIGN KEY (`royalty_statement_id`) REFERENCES `royalty_statements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `royalty_ledgers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `royalty_ledgers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ledger_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `release_id` bigint unsigned DEFAULT NULL,
  `track_id` bigint unsigned DEFAULT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `revenue_label_mapping_id` bigint unsigned DEFAULT NULL,
  `statement_month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reporting_month` date DEFAULT NULL,
  `sale_month` date DEFAULT NULL,
  `store_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `territory` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `gross_amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `label_share` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `artist_share` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `split_percentage` decimal(8,4) NOT NULL DEFAULT '100.0000',
  `payable_amount` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `streams` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `royalty_ledgers_public_id_unique` (`public_id`),
  UNIQUE KEY `royalty_ledgers_ledger_number_unique` (`ledger_number`),
  KEY `royalty_ledgers_user_id_foreign` (`user_id`),
  KEY `royalty_ledgers_created_by_foreign` (`created_by`),
  KEY `royalty_ledgers_updated_by_foreign` (`updated_by`),
  KEY `royalty_ledgers_artist_id_statement_month_index` (`artist_id`,`statement_month`),
  KEY `royalty_ledgers_track_id_statement_month_index` (`track_id`,`statement_month`),
  KEY `royalty_ledgers_release_id_statement_month_index` (`release_id`,`statement_month`),
  KEY `royalty_ledgers_store_name_statement_month_index` (`store_name`,`statement_month`),
  KEY `royalty_ledgers_statement_month_index` (`statement_month`),
  KEY `royalty_ledgers_store_name_index` (`store_name`),
  KEY `royalty_ledgers_status_index` (`status`),
  KEY `royalty_ledgers_label_id_foreign` (`label_id`),
  KEY `royalty_ledgers_revenue_label_mapping_id_foreign` (`revenue_label_mapping_id`),
  KEY `royalty_ledgers_reporting_month_index` (`reporting_month`),
  KEY `royalty_ledgers_sale_month_index` (`sale_month`),
  CONSTRAINT `royalty_ledgers_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `royalty_ledgers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `royalty_ledgers_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `royalty_ledgers_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `royalty_ledgers_revenue_label_mapping_id_foreign` FOREIGN KEY (`revenue_label_mapping_id`) REFERENCES `revenue_label_mappings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `royalty_ledgers_track_id_foreign` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `royalty_ledgers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `royalty_ledgers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `royalty_statements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `royalty_statements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `statement_month` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `gross_earnings` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `commission_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `tax_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `other_deductions` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `net_payable` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `available_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `royalty_statements_public_id_unique` (`public_id`),
  UNIQUE KEY `royalty_statement_owner_month_unique` (`artist_id`,`label_id`,`statement_month`,`currency`),
  KEY `royalty_statements_status_statement_month_index` (`status`,`statement_month`),
  KEY `royalty_statements_artist_id_index` (`artist_id`),
  KEY `royalty_statements_label_id_index` (`label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `message` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT '0',
  `attachments` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_messages_user_id_foreign` (`user_id`),
  KEY `support_ticket_messages_support_ticket_id_created_at_index` (`support_ticket_id`,`created_at`),
  CONSTRAINT `support_ticket_messages_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_ticket_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `assigned_admin_id` bigint unsigned DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `priority` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `last_reply_at` timestamp NULL DEFAULT NULL,
  `last_reply_by` bigint unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_public_id_unique` (`public_id`),
  UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`),
  KEY `support_tickets_user_id_foreign` (`user_id`),
  KEY `support_tickets_status_priority_index` (`status`,`priority`),
  KEY `support_tickets_assigned_admin_id_status_index` (`assigned_admin_id`,`status`),
  CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`),
  KEY `system_settings_group_key_index` (`group`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `track_contributors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `track_contributors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `track_id` bigint unsigned NOT NULL,
  `contributor_id` bigint unsigned NOT NULL,
  `role` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credited_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `display_order` smallint unsigned NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `track_contributor_role_unique` (`track_id`,`contributor_id`,`role`),
  UNIQUE KEY `track_contributors_public_id_unique` (`public_id`),
  KEY `track_contributors_created_by_foreign` (`created_by`),
  KEY `track_contributors_updated_by_foreign` (`updated_by`),
  KEY `track_contributors_track_id_role_index` (`track_id`,`role`),
  KEY `track_contributors_contributor_id_role_index` (`contributor_id`,`role`),
  KEY `track_contributors_role_index` (`role`),
  CONSTRAINT `track_contributors_contributor_id_foreign` FOREIGN KEY (`contributor_id`) REFERENCES `contributors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `track_contributors_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `track_contributors_track_id_foreign` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `track_contributors_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `track_splits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `track_splits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `track_id` bigint unsigned NOT NULL,
  `contributor_id` bigint unsigned NOT NULL,
  `split_type` enum('master','publishing','mechanical','performance','youtube') COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `is_recoupable` tinyint(1) NOT NULL DEFAULT '0',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `track_splits_public_id_unique` (`public_id`),
  KEY `track_splits_created_by_foreign` (`created_by`),
  KEY `track_splits_updated_by_foreign` (`updated_by`),
  KEY `track_splits_track_id_split_type_index` (`track_id`,`split_type`),
  KEY `track_splits_contributor_id_split_type_index` (`contributor_id`,`split_type`),
  KEY `track_splits_split_type_index` (`split_type`),
  KEY `track_splits_status_index` (`status`),
  CONSTRAINT `track_splits_contributor_id_foreign` FOREIGN KEY (`contributor_id`) REFERENCES `contributors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `track_splits_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `track_splits_track_id_foreign` FOREIGN KEY (`track_id`) REFERENCES `tracks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `track_splits_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tracks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_id` bigint unsigned NOT NULL,
  `disc_number` smallint unsigned NOT NULL DEFAULT '1',
  `track_number` smallint unsigned NOT NULL DEFAULT '1',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_artist_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `featuring_artist_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isrc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isrc_is_auto_generated` tinyint(1) NOT NULL DEFAULT '0',
  `language` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_genre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_explicit` tinyint(1) NOT NULL DEFAULT '0',
  `is_instrumental` tinyint(1) NOT NULL DEFAULT '0',
  `contains_ai_generated_content` tinyint(1) NOT NULL DEFAULT '0',
  `duration_seconds` int unsigned DEFAULT NULL,
  `preview_start_seconds` int unsigned DEFAULT NULL,
  `lyrics` longtext COLLATE utf8mb4_unicode_ci,
  `audio_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_size_bytes` bigint unsigned DEFAULT NULL,
  `sample_rate` int unsigned DEFAULT NULL,
  `bit_depth` smallint unsigned DEFAULT NULL,
  `channels` smallint unsigned DEFAULT NULL,
  `audio_validation_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `audio_validation_errors` json DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `isrc_assigned_at` timestamp NULL DEFAULT NULL,
  `isrc_assigned_by` bigint unsigned DEFAULT NULL,
  `audio_metadata` json DEFAULT NULL,
  `audio_codec` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_sample_rate` int unsigned DEFAULT NULL,
  `audio_bit_depth` smallint unsigned DEFAULT NULL,
  `audio_channels` smallint unsigned DEFAULT NULL,
  `audio_channel_layout` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_duration_seconds` decimal(12,3) DEFAULT NULL,
  `audio_peak_db` decimal(8,3) DEFAULT NULL,
  `audio_mean_volume_db` decimal(8,3) DEFAULT NULL,
  `audio_silence_start_seconds` decimal(10,3) DEFAULT NULL,
  `audio_silence_end_seconds` decimal(10,3) DEFAULT NULL,
  `audio_validated_at` timestamp NULL DEFAULT NULL,
  `audio_validated_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracks_release_disc_track_unique` (`release_id`,`disc_number`,`track_number`),
  UNIQUE KEY `tracks_public_id_unique` (`public_id`),
  UNIQUE KEY `tracks_isrc_unique` (`isrc`),
  KEY `tracks_created_by_foreign` (`created_by`),
  KEY `tracks_updated_by_foreign` (`updated_by`),
  KEY `tracks_release_id_status_index` (`release_id`,`status`),
  KEY `tracks_isrc_status_index` (`isrc`,`status`),
  KEY `tracks_audio_validation_status_index` (`audio_validation_status`),
  KEY `tracks_status_index` (`status`),
  CONSTRAINT `tracks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tracks_release_id_foreign` FOREIGN KEY (`release_id`) REFERENCES `releases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tracks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `upc_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `upc_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `release_id` bigint unsigned DEFAULT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `upc_codes_public_id_unique` (`public_id`),
  UNIQUE KEY `upc_codes_code_unique` (`code`),
  KEY `upc_codes_status_index` (`status`),
  KEY `upc_codes_release_id_index` (`release_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_panel_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_panel_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `can_view_catalogue` tinyint(1) NOT NULL DEFAULT '0',
  `can_create_releases` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_releases` tinyint(1) NOT NULL DEFAULT '0',
  `can_view_reports` tinyint(1) NOT NULL DEFAULT '0',
  `can_view_royalties` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_wallet` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_withdrawals` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_users` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_support` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_settings` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_delivery` tinyint(1) NOT NULL DEFAULT '0',
  `can_manage_identifiers` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_panel_permissions_user_id_unique` (`user_id`),
  CONSTRAINT `user_panel_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'artist',
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `kyc_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `wallet_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `invitation_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_invited',
  `invitation_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invitation_sent_at` timestamp NULL DEFAULT NULL,
  `invitation_expires_at` timestamp NULL DEFAULT NULL,
  `invitation_accepted_at` timestamp NULL DEFAULT NULL,
  `invitation_count` int unsigned NOT NULL DEFAULT '0',
  `invitation_error` text COLLATE utf8mb4_unicode_ci,
  `password_set_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_invitation_token_unique` (`invitation_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallet_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `pending_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `available_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `withdrawn_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `lifetime_earnings` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `hold_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_accounts_public_id_unique` (`public_id`),
  UNIQUE KEY `wallet_accounts_user_id_unique` (`user_id`),
  KEY `wallet_accounts_currency_index` (`currency`),
  CONSTRAINT `wallet_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `transaction_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,8) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `balance_before` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `balance_after` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `effective_at` timestamp NULL DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_transactions_public_id_unique` (`public_id`),
  KEY `wallet_transactions_created_by_foreign` (`created_by`),
  KEY `wallet_transactions_user_id_status_index` (`user_id`,`status`),
  KEY `wallet_transactions_artist_id_status_index` (`artist_id`,`status`),
  KEY `wallet_transactions_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `wallet_transactions_currency_effective_at_index` (`currency`,`effective_at`),
  KEY `wallet_transactions_transaction_type_index` (`transaction_type`),
  KEY `wallet_transactions_direction_index` (`direction`),
  KEY `wallet_transactions_reference_type_index` (`reference_type`),
  KEY `wallet_transactions_reference_id_index` (`reference_id`),
  KEY `wallet_transactions_status_index` (`status`),
  KEY `wallet_transactions_effective_at_index` (`effective_at`),
  KEY `wallet_transactions_posted_at_index` (`posted_at`),
  KEY `wallet_transactions_wallet_id_foreign` (`wallet_id`),
  KEY `wallet_transactions_label_id_foreign` (`label_id`),
  KEY `wallet_transactions_reference_lookup` (`reference_type`,`reference_id`,`transaction_type`,`direction`),
  CONSTRAINT `wallet_transactions_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `wallet_transactions_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `available_balance` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `pending_balance` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `lifetime_credits` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `lifetime_debits` decimal(18,8) NOT NULL DEFAULT '0.00000000',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallets_public_id_unique` (`public_id`),
  UNIQUE KEY `wallets_label_currency_unique` (`label_id`,`currency`),
  UNIQUE KEY `wallets_artist_currency_unique` (`artist_id`,`currency`),
  KEY `wallets_user_id_currency_index` (`user_id`,`currency`),
  CONSTRAINT `wallets_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallets_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `withdrawal_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawal_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `wallet_account_id` bigint unsigned NOT NULL,
  `payout_profile_id` bigint unsigned NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `fee_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `tax_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `net_amount` decimal(20,8) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bank',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `request_note` text COLLATE utf8mb4_unicode_ci,
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `payment_reference` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `processed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withdrawal_requests_public_id_unique` (`public_id`),
  UNIQUE KEY `withdrawal_requests_request_number_unique` (`request_number`),
  KEY `withdrawal_requests_wallet_account_id_foreign` (`wallet_account_id`),
  KEY `withdrawal_requests_payout_profile_id_foreign` (`payout_profile_id`),
  KEY `withdrawal_requests_status_created_at_index` (`status`,`created_at`),
  KEY `withdrawal_requests_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `withdrawal_requests_payout_profile_id_foreign` FOREIGN KEY (`payout_profile_id`) REFERENCES `payout_profiles` (`id`),
  CONSTRAINT `withdrawal_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `withdrawal_requests_wallet_account_id_foreign` FOREIGN KEY (`wallet_account_id`) REFERENCES `wallet_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `withdrawal_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `artist_id` bigint unsigned DEFAULT NULL,
  `label_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(18,8) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INR',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withdrawals_public_id_unique` (`public_id`),
  UNIQUE KEY `withdrawals_withdrawal_number_unique` (`withdrawal_number`),
  KEY `withdrawals_user_id_foreign` (`user_id`),
  KEY `withdrawals_approved_by_foreign` (`approved_by`),
  KEY `withdrawals_created_by_foreign` (`created_by`),
  KEY `withdrawals_updated_by_foreign` (`updated_by`),
  KEY `withdrawals_wallet_id_status_index` (`wallet_id`,`status`),
  KEY `withdrawals_label_id_status_index` (`label_id`,`status`),
  KEY `withdrawals_artist_id_status_index` (`artist_id`,`status`),
  CONSTRAINT `withdrawals_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `withdrawals_artist_id_foreign` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `withdrawals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `withdrawals_label_id_foreign` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `withdrawals_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `withdrawals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `withdrawals_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_07_27_033315_add_role_to_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'*_add_artist_fields_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_07_27_062334_add_invitation_fields_to_users_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_07_27_101808_create_labels_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_07_27_101815_create_artists_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_07_27_101822_create_releases_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_07_27_101827_create_tracks_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_07_27_101832_create_contributors_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_07_27_101849_create_wallet_transactions_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_07_27_101856_create_royalty_ledgers_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_07_27_101902_create_release_status_logs_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_07_27_124411_create_track_contributors_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_07_27_124939_create_track_splits_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_07_27_135001_add_hierarchy_and_payout_fields_to_labels_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_07_27_144826_create_personal_access_tokens_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_07_27_163203_add_royalty_share_to_labels_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_07_28_061145_add_workflow_fields_to_release_status_logs_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_07_28_082633_add_distribution_fields_to_releases_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_07_28_082648_add_distribution_fields_to_releases_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_07_28_082719_add_distribution_fields_to_releases_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_07_28_085518_create_distribution_stores_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_07_28_085537_add_excluded_store_ids_to_releases_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_07_28_085553_add_excluded_store_ids_to_releases_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_07_28_091051_repair_distribution_stores_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_07_28_100418_repair_release_review_fields',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_07_28_100714_create_release_store_deliveries_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_07_28_114952_create_admin_label_assignments_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_07_28_114953_create_admin_artist_assignments_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_07_28_121738_create_revenue_imports_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_07_28_121739_create_revenue_rows_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_07_28_121740_create_revenue_matches_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_07_28_121741_create_import_errors_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_07_28_122644_repair_revenue_imports_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_07_28_123139_add_public_id_to_revenue_imports_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_07_29_053630_add_complete_columns_to_revenue_rows_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_07_29_054218_add_complete_columns_to_import_errors_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_07_29_055532_expand_revenue_import_text_columns',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'PASTE_NEW_MIGRATION_FILENAME_HERE',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_07_29_061047_expand_streams_column_in_revenue_rows_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_07_30_204756_repair_import_errors_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_07_30_205957_repair_revenue_processing_tables',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_07_31_082304_create_revenue_label_mappings_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_07_31_094624_add_label_mapping_columns_to_royalty_ledgers_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_07_31_105951_add_reporting_month_to_revenue_rows_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_07_31_113743_add_dual_month_and_ledger_number_to_royalty_ledgers_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_07_31_115654_create_wallets_and_withdrawals_tables',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_07_31_120103_add_unique_royalty_reference_to_wallet_transactions_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_07_31_124633_create_invoices_and_invoice_items_tables',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_08_01_000001_add_multiple_artists_to_releases_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_08_01_000002_create_v2_release_store_deliveries_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_08_01_000003_create_v2_identifier_codes_tables',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_08_01_000004_create_panel_notifications_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_08_01_000005_create_v2_catalogue_tables',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_08_01_000006_create_release_activity_logs_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_08_01_000007_create_v2_track_contributors_and_splits_tables',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_08_01_000008_add_audio_validation_metadata_to_tracks_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_08_01_000009_create_v2_report_tables',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_08_01_000010_create_v2_royalty_wallet_tables',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_08_01_000011_create_v2_withdrawal_kyc_tables',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_08_01_000012_create_v2_invoices_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_08_01_000013_create_v2_support_notification_tables',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_08_01_000014_create_v2_system_settings_audit_tables',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_08_01_000015_create_v2_user_panel_permissions_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_08_01_195329_add_v2_catalogue_ownership_engine',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_08_02_075857_create_revenue_agreements_table',50);
