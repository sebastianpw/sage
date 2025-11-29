-- Multi-Table Export
-- Database: sg_sys
-- Generated: 2025-11-29 01:57:47
-- Tables with structure: 11
-- Tables with data: 0

-- Export order: Tables first, then Views

-- ========================================================
-- TABLES
-- ========================================================

-- --------------------------------------------------------
-- Table: `code_analysis_log`
-- --------------------------------------------------------

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
) ENGINE=InnoDB AUTO_INCREMENT=781 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `code_classes`
-- --------------------------------------------------------

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
) ENGINE=InnoDB AUTO_INCREMENT=1077 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `code_files`
-- --------------------------------------------------------

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
) ENGINE=InnoDB AUTO_INCREMENT=380 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `gpt_conversations`
-- --------------------------------------------------------

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
) ENGINE=InnoDB AUTO_INCREMENT=882 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `gpt_messages`
-- --------------------------------------------------------

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
) ENGINE=InnoDB AUTO_INCREMENT=23453 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `pages_dashboard`
-- --------------------------------------------------------

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
) ENGINE=InnoDB AUTO_INCREMENT=1108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `recipes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `recipes`;

CREATE TABLE `recipes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipe_group_id` int(11) NOT NULL COMMENT 'FK to recipe_groups.id',
  `output_filename` varchar(255) NOT NULL COMMENT 'Relative path of the output file, e.g., temp/dc.txt',
  `rerun_command` text NOT NULL COMMENT 'The full CLI command to reproduce this exact recipe.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_recipes_recipe_groups_idx` (`recipe_group_id`),
  CONSTRAINT `fk_recipes_recipe_groups` FOREIGN KEY (`recipe_group_id`) REFERENCES `recipe_groups` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `recipe_groups`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `recipe_groups`;

CREATE TABLE `recipe_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'The conceptual name of the recipe.',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_UNIQUE` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `recipe_ingredients`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `recipe_ingredients`;

CREATE TABLE `recipe_ingredients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipe_id` int(11) NOT NULL,
  `snapshot_id` int(11) NOT NULL COMMENT 'FK to recipe_ingredient_snapshots.id',
  `source_filename` varchar(255) NOT NULL COMMENT 'The original relative path or db: handle.',
  `display_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_recipe_ingredients_recipes_idx` (`recipe_id`),
  KEY `fk_recipe_ingredients_snapshots_idx` (`snapshot_id`),
  CONSTRAINT `fk_recipe_ingredients_recipes` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_recipe_ingredients_snapshots` FOREIGN KEY (`snapshot_id`) REFERENCES `recipe_ingredient_snapshots` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=426 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `recipe_ingredient_snapshots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `recipe_ingredient_snapshots`;

CREATE TABLE `recipe_ingredient_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_hash` char(64) NOT NULL COMMENT 'SHA-256 hash of the content. This is our version identifier.',
  `content` longtext NOT NULL COMMENT 'The full snapshot content.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_hash_UNIQUE` (`content_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sage_todos`
-- --------------------------------------------------------

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
) ENGINE=InnoDB AUTO_INCREMENT=331 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

