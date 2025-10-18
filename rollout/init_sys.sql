-- Adminer 5.3.0 MariaDB 12.0.2-MariaDB dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `code_analysis_log`;
CREATE TABLE `code_analysis_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) DEFAULT NULL,
  `chunk_index` int(11) DEFAULT NULL,
  `tokens_estimate` int(11) DEFAULT NULL,
  `response_length` int(11) DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `raw_response` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `code_analysis_log_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `code_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `code_classes`;
CREATE TABLE `code_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `class_name` varchar(255) DEFAULT NULL,
  `extends_class` varchar(255) DEFAULT NULL,
  `interfaces` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`interfaces`)),
  `methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`methods`)),
  `summary` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  CONSTRAINT `code_classes_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `code_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `code_files`;
CREATE TABLE `code_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(1024) NOT NULL,
  `file_hash` char(40) NOT NULL,
  `last_analyzed_at` datetime DEFAULT NULL,
  `chunk_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `path` (`path`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `gpt_conversations`;
CREATE TABLE `gpt_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(128) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `model` varchar(128) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `message_count` int(10) unsigned DEFAULT 0,
  `flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `external_id` (`external_id`),
  KEY `created_at` (`created_at`),
  KEY `updated_at` (`updated_at`),
  KEY `model` (`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `gpt_messages`;
CREATE TABLE `gpt_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_external_id` varchar(128) NOT NULL,
  `message_index` int(11) NOT NULL DEFAULT 0,
  `role` varchar(32) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `content_text` mediumtext DEFAULT NULL,
  `raw_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `model` varchar(128) DEFAULT NULL,
  `tokens` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_external_id` (`conversation_external_id`),
  KEY `message_index` (`message_index`),
  KEY `created_at` (`created_at`),
  FULLTEXT KEY `ft_content` (`content_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `pages_dashboard`;
CREATE TABLE `pages_dashboard` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `href` varchar(2048) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `pages_dashboard` (`id`, `name`, `level`, `parent_id`, `href`, `position`) VALUES
(999, 'IMPORT', 1, NULL, '', 0),
(1000, 'TOOLS', 1, NULL, '', 2),
(1001, 'DELETE', 1, NULL, '', 3),
(1006, 'CLONE ME', 2, 999, 'https://petersebring.com ', 0);


DROP TABLE IF EXISTS `sage_todos`;
CREATE TABLE `sage_todos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 2025-10-17 12:41:13 UTC
