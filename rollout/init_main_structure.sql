-- Multi-Table Export
-- Database: starlight_guardians_nu
-- Generated: 2025-11-29 01:44:35
-- Tables with structure: 159
-- Tables with data: 0

-- Export order: Tables first, then Views

-- ========================================================
-- TABLES
-- ========================================================

-- --------------------------------------------------------
-- Table: `angles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `angles`;

CREATE TABLE `angles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animas`;

CREATE TABLE `animas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `traits` text DEFAULT NULL,
  `abilities` text DEFAULT NULL,
  `quirks` text DEFAULT NULL,
  `relationship_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_character_animas_character` (`character_id`),
  KEY `idx_character_animas_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `artifacts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `artifacts`;

CREATE TABLE `artifacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('inactive','active','corrupted','purified') NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artifacts_name` (`name`),
  KEY `idx_artifacts_type` (`type`),
  KEY `idx_artifacts_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_assets`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_assets`;

CREATE TABLE `audio_assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `type` enum('voice','music','sfx') NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audio_name` (`name`),
  KEY `idx_audio_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `backgrounds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `backgrounds`;

CREATE TABLE `backgrounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backgrounds_name` (`name`),
  KEY `idx_backgrounds_type` (`type`),
  KEY `idx_backgrounds_location` (`location_id`),
  CONSTRAINT `fk_backgrounds_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `camera_angles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `camera_angles`;

CREATE TABLE `camera_angles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `camera_perspectives`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `camera_perspectives`;

CREATE TABLE `camera_perspectives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `characters`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `characters`;

CREATE TABLE `characters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `role` varchar(100) DEFAULT NULL,
  `age_background` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `desc_abbr` varchar(255) NOT NULL DEFAULT '',
  `motivations` text DEFAULT NULL,
  `hooks_arc_potential` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_characters_name` (`name`),
  KEY `idx_characters_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=762 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `character_poses`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `character_poses`;

CREATE TABLE `character_poses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text NOT NULL,
  `character_id` int(11) NOT NULL,
  `pose_id` int(11) NOT NULL,
  `angle_id` int(11) NOT NULL,
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_character_pose_angle` (`character_id`,`pose_id`,`angle_id`),
  KEY `character_id` (`character_id`),
  KEY `pose_id` (`pose_id`),
  KEY `angle_id` (`angle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `chat_message`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chat_message`;

CREATE TABLE `chat_message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `role` varchar(10) NOT NULL,
  `content` longtext NOT NULL,
  `token_count` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11340 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `chat_session`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chat_session`;

CREATE TABLE `chat_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(36) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `model` varchar(50) DEFAULT 'openai',
  `type` varchar(32) NOT NULL DEFAULT 'standard',
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_chat_session_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=10274 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `chat_summary`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chat_summary`;

CREATE TABLE `chat_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `summary` longtext NOT NULL,
  `tokens` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `composites`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `composites`;

CREATE TABLE `composites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `composite_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `composite_frames`;

CREATE TABLE `composite_frames` (
  `composite_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`composite_id`,`frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `content_elements`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `content_elements`;

CREATE TABLE `content_elements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `html` mediumtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  CONSTRAINT `content_elements_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `controlnet_maps`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `controlnet_maps`;

CREATE TABLE `controlnet_maps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=452 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `design_axes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `design_axes`;

CREATE TABLE `design_axes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `axis_name` varchar(128) NOT NULL,
  `axis_group` varchar(64) DEFAULT 'visual_style',
  `category` varchar(128) DEFAULT NULL,
  `pole_left` varchar(128) NOT NULL,
  `pole_right` varchar(128) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_axis_group` (`axis_group`),
  KEY `idx_group_category` (`axis_group`,`category`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
-- Table: `dict_dictionaries`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `dict_dictionaries`;

CREATE TABLE `dict_dictionaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `source_author` varchar(255) DEFAULT NULL COMMENT 'e.g., Henry Miller',
  `source_title` varchar(255) DEFAULT NULL COMMENT 'e.g., Tropic of Cancer',
  `language_code` varchar(10) DEFAULT 'en' COMMENT 'ISO language code',
  `total_lemmas` int(11) DEFAULT 0 COMMENT 'Cached count',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `sort_order_index` (`sort_order`),
  KEY `language_code` (`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `dict_lemmas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `dict_lemmas`;

CREATE TABLE `dict_lemmas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lemma` varchar(255) NOT NULL COMMENT 'Base form of the word',
  `language_code` varchar(10) DEFAULT 'en',
  `pos` varchar(50) DEFAULT NULL COMMENT 'Part of speech: noun, verb, adj, etc.',
  `frequency` int(11) DEFAULT 1 COMMENT 'Global frequency count',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `lemma_language` (`lemma`,`language_code`),
  KEY `lemma_index` (`lemma`),
  KEY `frequency_index` (`frequency`)
) ENGINE=InnoDB AUTO_INCREMENT=26070 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `dict_lemma_2_dictionary`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `dict_lemma_2_dictionary`;

CREATE TABLE `dict_lemma_2_dictionary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dictionary_id` int(11) NOT NULL,
  `lemma_id` int(11) NOT NULL,
  `frequency_in_dict` int(11) DEFAULT 1 COMMENT 'How often this lemma appears in this dictionary',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dict_lemma_unique` (`dictionary_id`,`lemma_id`),
  KEY `dictionary_id` (`dictionary_id`),
  KEY `lemma_id` (`lemma_id`),
  CONSTRAINT `fk_dict_id` FOREIGN KEY (`dictionary_id`) REFERENCES `dict_dictionaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lemma_id` FOREIGN KEY (`lemma_id`) REFERENCES `dict_lemmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39940 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `dict_source_files`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `dict_source_files`;

CREATE TABLE `dict_source_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dictionary_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(512) NOT NULL COMMENT 'Relative path from doc root',
  `file_type` enum('txt','pdf') NOT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `parse_status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `parse_started_at` timestamp NULL DEFAULT NULL,
  `parse_completed_at` timestamp NULL DEFAULT NULL,
  `lemmas_extracted` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dictionary_id` (`dictionary_id`),
  KEY `parse_status` (`parse_status`),
  CONSTRAINT `fk_source_dict_id` FOREIGN KEY (`dictionary_id`) REFERENCES `dict_dictionaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `export_flags`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `export_flags`;

CREATE TABLE `export_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `ready_for_export` tinyint(1) NOT NULL DEFAULT 0,
  `export_type` enum('script','art','audio','full_package') NOT NULL,
  `last_exported_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_export_scene_part` (`scene_part_id`),
  KEY `idx_export_ready` (`ready_for_export`),
  KEY `idx_export_type` (`export_type`),
  CONSTRAINT `fk_export_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `feedback_notes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `feedback_notes`;

CREATE TABLE `feedback_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` varchar(100) NOT NULL,
  `scene_part_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `action_required` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feedback_source` (`source`),
  KEY `idx_feedback_status` (`resolved_status`),
  KEY `idx_feedback_scene_part` (`scene_part_id`),
  CONSTRAINT `fk_feedback_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames`;

CREATE TABLE `frames` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `prompt` text NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `style` text DEFAULT NULL,
  `style_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `state_id` int(11) DEFAULT NULL,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7022 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_animas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_animas`;

CREATE TABLE `frames_2_animas` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `anima_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_artifacts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_artifacts`;

CREATE TABLE `frames_2_artifacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_frame_artifact_from` (`from_id`),
  KEY `idx_frame_artifact_to` (`to_id`),
  CONSTRAINT `fk_frames_artifacts_artifact` FOREIGN KEY (`to_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_backgrounds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_backgrounds`;

CREATE TABLE `frames_2_backgrounds` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `background_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_characters`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_characters`;

CREATE TABLE `frames_2_characters` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `character_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_character_poses`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_character_poses`;

CREATE TABLE `frames_2_character_poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `from_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_composites`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_composites`;

CREATE TABLE `frames_2_composites` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `composite_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_controlnet_maps`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_controlnet_maps`;

CREATE TABLE `frames_2_controlnet_maps` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `to_id_idx` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
-- Table: `frames_2_generatives`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_generatives`;

CREATE TABLE `frames_2_generatives` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_frame_generative_from` (`from_id`),
  KEY `idx_frame_generative_to` (`to_id`),
  CONSTRAINT `fk_frames_generatives_generative` FOREIGN KEY (`to_id`) REFERENCES `generatives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_locations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_locations`;

CREATE TABLE `frames_2_locations` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `location_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_pastebin`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_pastebin`;

CREATE TABLE `frames_2_pastebin` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `location_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_prompt_matrix_blueprints`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_prompt_matrix_blueprints`;

CREATE TABLE `frames_2_prompt_matrix_blueprints` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `prompt_matrix_blueprint_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_scene_parts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_scene_parts`;

CREATE TABLE `frames_2_scene_parts` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `to_id` (`to_id`),
  CONSTRAINT `frames_2_scene_parts_ibfk_2` FOREIGN KEY (`to_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_sketches`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_sketches`;

CREATE TABLE `frames_2_sketches` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_frame_sketch_from` (`from_id`),
  KEY `idx_frame_sketch_to` (`to_id`),
  CONSTRAINT `fk_frames_sketches_sketch` FOREIGN KEY (`to_id`) REFERENCES `sketches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_spawns`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_spawns`;

CREATE TABLE `frames_2_spawns` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `seed_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_vehicles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_vehicles`;

CREATE TABLE `frames_2_vehicles` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_from_id` (`from_id`),
  KEY `idx_to_id` (`to_id`),
  CONSTRAINT `fk_frames_2_vehicles_to` FOREIGN KEY (`to_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_chains`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_chains`;

CREATE TABLE `frames_chains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `frame_id` int(11) NOT NULL COMMENT 'The frame that is part of this chain step',
  `parent_frame_id` int(11) DEFAULT NULL COMMENT 'Previous frame in the chain (NULL if first in chain)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rolled_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = this chain step has been rolled back',
  PRIMARY KEY (`id`),
  KEY `idx_frame_id` (`frame_id`),
  KEY `idx_parent_frame_id` (`parent_frame_id`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_failed`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_failed`;

CREATE TABLE `frames_failed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prompt` text DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `style` varchar(255) DEFAULT NULL,
  `style_id` int(11) DEFAULT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `img2img_entity` varchar(50) DEFAULT NULL,
  `img2img_id` int(11) DEFAULT NULL,
  `img2img_filename` varchar(255) DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_trashcan`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_trashcan`;

CREATE TABLE `frames_trashcan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_frame_id` int(11) NOT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `prompt` text NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `style` text DEFAULT NULL,
  `style_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `img2img_entity` varchar(64) DEFAULT NULL,
  `img2img_id` int(11) DEFAULT NULL,
  `img2img_filename` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frame_counter`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frame_counter`;

CREATE TABLE `frame_counter` (
  `id` int(11) NOT NULL DEFAULT 1,
  `next_frame` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `generated_phrase_maps`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generated_phrase_maps`;

CREATE TABLE `generated_phrase_maps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_hash` varchar(64) NOT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `model_name` varchar(128) NOT NULL,
  `prompt` text DEFAULT NULL,
  `phrase_map_json` longtext NOT NULL,
  `raw_model_response` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `usage_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_profile_hash_model` (`profile_hash`,`model_name`),
  KEY `idx_profile_id` (`profile_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
-- Table: `generatives`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generatives`;

CREATE TABLE `generatives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'raw or random prompt text',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `generator_config`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generator_config`;

CREATE TABLE `generator_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL = public generator, int = private generator owned by user',
  `title` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL DEFAULT 'openai',
  `system_role` text NOT NULL,
  `instructions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`instructions`)),
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`parameters`)),
  `output_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`output_schema`)),
  `examples` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`examples`)),
  `oracle_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Configuration for the Bloom Oracle creative seeding' CHECK (json_valid(`oracle_config`)),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = private (only owner), 1 = public (all users)',
  `list_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Order for drag-and-drop listing',
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_id` (`config_id`),
  KEY `user_id` (`user_id`),
  KEY `active` (`active`),
  KEY `idx_user_active` (`user_id`,`active`),
  KEY `idx_public` (`user_id`),
  KEY `idx_is_public` (`is_public`,`active`),
  KEY `idx_list_order` (`list_order`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Generator configurations. user_id=NULL indicates public/system generators accessible to all users.';

-- --------------------------------------------------------
-- Table: `generator_config_display_area`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generator_config_display_area`;

CREATE TABLE `generator_config_display_area` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_key` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_area_key` (`area_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurable display areas for generators';

-- --------------------------------------------------------
-- Table: `generator_config_to_display_area`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generator_config_to_display_area`;

CREATE TABLE `generator_config_to_display_area` (
  `generator_config_id` int(11) NOT NULL,
  `display_area_id` int(11) NOT NULL,
  PRIMARY KEY (`generator_config_id`,`display_area_id`),
  KEY `idx_generator_config_id` (`generator_config_id`),
  KEY `idx_display_area_id` (`display_area_id`),
  CONSTRAINT `fk_display_area` FOREIGN KEY (`display_area_id`) REFERENCES `generator_config_display_area` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_generator_config` FOREIGN KEY (`generator_config_id`) REFERENCES `generator_config` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `image_edits`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `image_edits`;

CREATE TABLE `image_edits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chain_id` int(11) DEFAULT NULL,
  `parent_frame_id` int(11) NOT NULL,
  `derived_frame_id` int(11) DEFAULT NULL,
  `derived_filename` varchar(255) DEFAULT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `tool` varchar(64) DEFAULT NULL,
  `mode` varchar(32) DEFAULT NULL,
  `coords_json` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `applied` tinyint(1) NOT NULL DEFAULT 0,
  `applied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parent_frame` (`parent_frame_id`),
  KEY `idx_chain` (`chain_id`),
  KEY `idx_derived_frame` (`derived_frame_id`),
  KEY `idx_map_run` (`map_run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `image_stash`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `image_stash`;

CREATE TABLE `image_stash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_path` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `interactions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `interactions`;

CREATE TABLE `interactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `interaction_group` varchar(50) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `example_prompt` text NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `interaction_group` (`interaction_group`),
  KEY `category` (`category`),
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `interaction_audio`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `interaction_audio`;

CREATE TABLE `interaction_audio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `interaction_id` int(11) NOT NULL,
  `audio_asset_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ia_unique` (`interaction_id`,`audio_asset_id`),
  KEY `fk_ia_audio` (`audio_asset_id`),
  CONSTRAINT `fk_ia_audio` FOREIGN KEY (`audio_asset_id`) REFERENCES `audio_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ia_interaction` FOREIGN KEY (`interaction_id`) REFERENCES `interactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `lightings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `lightings`;

CREATE TABLE `lightings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `angle_id` int(11) DEFAULT NULL,
  `intensity` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `angle_id` (`angle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `locations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `locations`;

CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `coordinates` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locations_name` (`name`),
  KEY `idx_locations_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=1055 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `locations_abstract`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `locations_abstract`;

CREATE TABLE `locations_abstract` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `coordinates` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locations_name` (`name`),
  KEY `idx_locations_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=594 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `logs`;

CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` varchar(10) NOT NULL,
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`message`)),
  `log_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
-- Table: `map_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `map_runs`;

CREATE TABLE `map_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` text DEFAULT NULL,
  `parent_map_run_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_map_run_idx` (`parent_map_run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1412 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `meta_entities`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `meta_entities`;

CREATE TABLE `meta_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'BASE TABLE' COMMENT 'BASE TABLE, VIEW, etc.',
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `pages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `pages`;

CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `href` varchar(2048) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `pastebin`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `pastebin`;

CREATE TABLE `pastebin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `order` int(11) DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `visibility` enum('public','private','link','hidden') DEFAULT 'private',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `url_token` char(64) NOT NULL COMMENT 'Unique token for API access',
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_token` (`url_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_visibility` (`visibility`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
-- Table: `perspectives`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `perspectives`;

CREATE TABLE `perspectives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `angle` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_perspectives_scene_part` (`scene_part_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `playlist_videos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `playlist_videos`;

CREATE TABLE `playlist_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `playlist_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `added_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_playlist_video` (`playlist_id`,`video_id`),
  KEY `idx_playlist` (`playlist_id`),
  KEY `idx_video` (`video_id`),
  KEY `idx_sort` (`sort_order`),
  CONSTRAINT `fk_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `video_playlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `poses`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `poses`;

CREATE TABLE `poses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `posts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `posts`;

CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `post_type` enum('image_grid','image_swiper','video_playlist','youtube_playlist') NOT NULL,
  `preview_image_url` varchar(512) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`media_items`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `sort_order_index` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `production_status`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `production_status`;

CREATE TABLE `production_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `stage` enum('draft','review','approved','locked') NOT NULL DEFAULT 'draft',
  `assigned_to` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_prodstatus_scene_part` (`scene_part_id`),
  KEY `idx_prodstatus_stage` (`stage`),
  KEY `idx_prodstatus_assignee` (`assigned_to`),
  CONSTRAINT `fk_prodstatus_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `prompt_additions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prompt_additions`;

CREATE TABLE `prompt_additions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `slot` int(11) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'display order inside slot',
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_slot_order` (`slot`,`order`),
  KEY `idx_active_slot` (`active`,`slot`),
  KEY `idx_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `prompt_globals`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prompt_globals`;

CREATE TABLE `prompt_globals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prompt_globals_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `prompt_ideations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prompt_ideations`;

CREATE TABLE `prompt_ideations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `prompt_matrix`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prompt_matrix`;

CREATE TABLE `prompt_matrix` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `additions_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Immutable snapshot: [{slot, addition_id|null, text}]' CHECK (json_valid(`additions_snapshot`)),
  `additions_count` int(11) DEFAULT NULL,
  `total_combinations` bigint(20) unsigned DEFAULT NULL,
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_map_run` (`active_map_run_id`),
  KEY `idx_state` (`state_id_active`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `prompt_matrix_additions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prompt_matrix_additions`;

CREATE TABLE `prompt_matrix_additions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `matrix_id` int(10) unsigned NOT NULL,
  `addition_id` int(10) unsigned DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `slot` int(11) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_matrix_slot` (`matrix_id`,`slot`),
  KEY `idx_addition_id` (`addition_id`)
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `prompt_matrix_blueprints`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prompt_matrix_blueprints`;

CREATE TABLE `prompt_matrix_blueprints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `matrix_id` int(10) unsigned NOT NULL,
  `matrix_additions_id` int(10) unsigned DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'raw or random prompt text',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sketches_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=498 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scenes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scenes`;

CREATE TABLE `scenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `planet` varchar(100) DEFAULT NULL,
  `sequence` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `arc_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scenes_sequence` (`sequence`),
  KEY `fk_scene_arc` (`arc_id`),
  CONSTRAINT `fk_scene_arc` FOREIGN KEY (`arc_id`) REFERENCES `story_arcs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scene_parts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scene_parts`;

CREATE TABLE `scene_parts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `sequence` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scene_parts_scene` (`scene_id`),
  KEY `idx_scene_parts_scene_seq` (`scene_id`,`sequence`),
  CONSTRAINT `fk_scene_parts_scene` FOREIGN KEY (`scene_id`) REFERENCES `scenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scene_part_animas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scene_part_animas`;

CREATE TABLE `scene_part_animas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `character_anima_id` int(11) NOT NULL,
  `action_type` enum('misfire','assist','comic_beat','strategic_move') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_span_unique` (`scene_part_id`,`character_anima_id`,`action_type`),
  KEY `idx_span_scene_part` (`scene_part_id`),
  KEY `idx_span_character_anima` (`character_anima_id`),
  CONSTRAINT `fk_span_character_anima` FOREIGN KEY (`character_anima_id`) REFERENCES `animas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_span_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scene_part_artifacts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scene_part_artifacts`;

CREATE TABLE `scene_part_artifacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `artifact_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_spa_unique` (`scene_part_id`,`artifact_id`),
  KEY `idx_spa_artifact` (`artifact_id`),
  KEY `idx_spa_scene_part` (`scene_part_id`),
  CONSTRAINT `fk_spa_artifact` FOREIGN KEY (`artifact_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_spa_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scene_part_backgrounds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scene_part_backgrounds`;

CREATE TABLE `scene_part_backgrounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perspective_id` int(11) NOT NULL,
  `background_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_spb_unique` (`perspective_id`,`background_id`),
  KEY `idx_spb_background` (`background_id`),
  KEY `idx_spb_perspective` (`perspective_id`),
  CONSTRAINT `fk_spb_background` FOREIGN KEY (`background_id`) REFERENCES `backgrounds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_spb_perspective` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scene_part_characters`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scene_part_characters`;

CREATE TABLE `scene_part_characters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `character_id` int(11) NOT NULL,
  `role_in_part` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_spc_unique` (`scene_part_id`,`character_id`),
  KEY `idx_spc_char` (`character_id`),
  KEY `idx_spc_scene_part` (`scene_part_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scene_part_tags`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scene_part_tags`;

CREATE TABLE `scene_part_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_spt_unique` (`scene_part_id`,`tag_id`),
  KEY `idx_spt_tag` (`tag_id`),
  KEY `idx_spt_scene_part` (`scene_part_id`),
  CONSTRAINT `fk_spt_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_spt_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scene_part_versions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scene_part_versions`;

CREATE TABLE `scene_part_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_spv_unique` (`scene_part_id`,`version_number`),
  KEY `idx_spv_scene_part` (`scene_part_id`),
  KEY `idx_spv_version` (`version_number`),
  CONSTRAINT `fk_spv_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scheduled_tasks`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scheduled_tasks`;

CREATE TABLE `scheduled_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `script_path` varchar(512) DEFAULT NULL,
  `args` text DEFAULT NULL,
  `schedule_time` time DEFAULT NULL,
  `schedule_interval` int(11) DEFAULT NULL,
  `schedule_dow` varchar(13) DEFAULT '0,1,2,3,4,5,6',
  `last_run` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `max_concurrent_runs` int(11) DEFAULT 1 COMMENT 'Maximum concurrent executions allowed',
  `lock_timeout_minutes` int(11) DEFAULT 60 COMMENT 'How long before a lock expires',
  `require_lock` tinyint(1) DEFAULT 1 COMMENT 'Whether this task requires mutex locking',
  `lock_scope` enum('global','entity','none') DEFAULT 'global' COMMENT 'Scope of the lock: global (task-wide), entity (per-entity), none',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `run_now` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_active_time` (`active`,`schedule_time`),
  KEY `idx_active_interval` (`active`,`schedule_interval`),
  KEY `idx_last_run` (`last_run`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `scheduler_heartbeat`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scheduler_heartbeat`;

CREATE TABLE `scheduler_heartbeat` (
  `id` tinyint(4) NOT NULL,
  `last_seen` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `seeds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `seeds`;

CREATE TABLE `seeds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seeds_value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `shot_types`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `shot_types`;

CREATE TABLE `shot_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `sketches`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketches`;

CREATE TABLE `sketches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'raw or random prompt text',
  `mood` text DEFAULT NULL COMMENT 'optional mood description, e.g. whimsical, dark, peaceful',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sketches_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=318 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_templates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_templates`;

CREATE TABLE `sketch_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `core_idea` varchar(255) NOT NULL,
  `shot_type` varchar(50) NOT NULL,
  `camera_angle` varchar(50) NOT NULL,
  `perspective` varchar(50) NOT NULL,
  `entity_slots` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`entity_slots`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`tags`)),
  `example_prompt` text NOT NULL,
  `entity_type` varchar(50) NOT NULL DEFAULT 'sketches',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`),
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `spawns`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `spawns`;

CREATE TABLE `spawns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `type` varchar(50) DEFAULT NULL,
  `spawn_type_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_seeds_type` (`type`),
  KEY `idx_spawn_type_id` (`spawn_type_id`),
  CONSTRAINT `fk_spawns_spawn_type` FOREIGN KEY (`spawn_type_id`) REFERENCES `spawn_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=651 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `spawn_types`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `spawn_types`;

CREATE TABLE `spawn_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT 'Machine-readable identifier',
  `label` varchar(100) NOT NULL COMMENT 'Human-readable name',
  `description` text DEFAULT NULL,
  `gallery_view` varchar(100) DEFAULT 'v_gallery_spawns' COMMENT 'View name for this type',
  `upload_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `batch_import_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `states`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `states`;

CREATE TABLE `states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_states_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `storyboards`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `storyboards`;

CREATE TABLE `storyboards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `directory` varchar(255) NOT NULL COMMENT 'Relative path like /storyboards/storyboard001',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `directory` (`directory`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `storyboard_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `storyboard_frames`;

CREATE TABLE `storyboard_frames` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `storyboard_id` int(11) NOT NULL,
  `frame_id` int(11) DEFAULT NULL COMMENT 'Reference to frames table, NULL if standalone',
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Full relative path /storyboards/storyboard001/frame0000001.png',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_copied` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if physically copied to storyboard dir',
  `original_filename` varchar(255) DEFAULT NULL COMMENT 'Original filename before copy/rename',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_storyboard` (`storyboard_id`),
  KEY `idx_frame` (`frame_id`),
  KEY `idx_sort` (`storyboard_id`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `story_arcs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `story_arcs`;

CREATE TABLE `story_arcs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `themes` text DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `tone` varchar(255) DEFAULT NULL,
  `story_beats` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('planned','in_progress','completed') DEFAULT 'planned',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `styles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `styles`;

CREATE TABLE `styles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `color_tone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_styles_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `style_profiles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `style_profiles`;

CREATE TABLE `style_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `axis_group` varchar(64) DEFAULT 'visual_style',
  `filename` varchar(255) DEFAULT NULL,
  `json_payload` longtext DEFAULT NULL,
  `convert_result` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `idx_axis_group` (`axis_group`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
-- Table: `style_profile_axes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `style_profile_axes`;

CREATE TABLE `style_profile_axes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL,
  `axis_id` int(10) unsigned NOT NULL,
  `value` tinyint(3) unsigned NOT NULL DEFAULT 50,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_profile_axis` (`profile_id`,`axis_id`),
  KEY `profile_id` (`profile_id`),
  KEY `axis_id` (`axis_id`),
  CONSTRAINT `fk_spa_axis` FOREIGN KEY (`axis_id`) REFERENCES `design_axes` (`id`),
  CONSTRAINT `fk_spa_profile` FOREIGN KEY (`profile_id`) REFERENCES `style_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=404 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------
-- Table: `style_profile_config`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `style_profile_config`;

CREATE TABLE `style_profile_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(64) NOT NULL,
  `config_value` varchar(64) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tags`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags`;

CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `tags2poses`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags2poses`;

CREATE TABLE `tags2poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `tags_2_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags_2_frames`;

CREATE TABLE `tags_2_frames` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Frame ID',
  UNIQUE KEY `uq_tags_2_frames` (`from_id`,`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `task_execution_stats`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `task_execution_stats`;

CREATE TABLE `task_execution_stats` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_runs` int(11) DEFAULT 0,
  `successful_runs` int(11) DEFAULT 0,
  `failed_runs` int(11) DEFAULT 0,
  `stale_runs` int(11) DEFAULT 0,
  `avg_duration_seconds` decimal(10,2) DEFAULT NULL,
  `max_duration_seconds` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_date` (`task_id`,`date`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_execution_stats_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=252 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `task_locks`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `task_locks`;

CREATE TABLE `task_locks` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `lock_key` varchar(255) NOT NULL COMMENT 'Unique identifier for this lock (e.g., task_id:entity_type:entity_id)',
  `acquired_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_renewed` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `run_id` bigint(20) DEFAULT NULL COMMENT 'Reference to task_runs.id',
  `pid` int(11) DEFAULT NULL,
  `hostname` varchar(255) DEFAULT NULL,
  `owner_token` char(36) NOT NULL DEFAULT '',
  `status` enum('active','expired','released') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lock_key` (`lock_key`),
  KEY `task_id` (`task_id`),
  KEY `run_id` (`run_id`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_task_locks_owner_token` (`owner_token`),
  CONSTRAINT `task_locks_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_locks_ibfk_2` FOREIGN KEY (`run_id`) REFERENCES `task_runs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6548 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `task_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `task_runs`;

CREATE TABLE `task_runs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `pid` int(11) DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `exit_code` int(11) DEFAULT NULL,
  `stdout_log` varchar(1024) DEFAULT NULL,
  `stderr_log` varchar(1024) DEFAULT NULL,
  `bytes_out` bigint(20) DEFAULT 0,
  `bytes_err` bigint(20) DEFAULT 0,
  `status` enum('pending','running','completed','failed','stale','cancelled') DEFAULT 'pending',
  `lock_id` bigint(20) DEFAULT NULL COMMENT 'Reference to task_locks.id',
  `lock_owner_token` char(36) DEFAULT NULL,
  `entity_type` varchar(191) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `idx_status` (`status`),
  KEY `lock_id` (`lock_id`),
  KEY `idx_task_runs_status_pid` (`status`,`pid`),
  CONSTRAINT `task_runs_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_runs_ibfk_2` FOREIGN KEY (`lock_id`) REFERENCES `task_locks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `user`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user`;

CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `google_id` varchar(50) DEFAULT NULL,
  `google_email` varchar(255) DEFAULT NULL,
  `google_name` varchar(100) DEFAULT NULL,
  `google_given_name` varchar(100) DEFAULT NULL,
  `google_family_name` varchar(100) DEFAULT NULL,
  `google_picture` varchar(255) DEFAULT NULL,
  `google_picture_blob` longblob DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `google_id_unique` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `vehicles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `vehicles`;

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `type` varchar(50) DEFAULT NULL COMMENT 'Land, Air, Water, Space, etc.',
  `description` text DEFAULT NULL,
  `status` enum('inactive','active','damaged','decommissioned') NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicles_name` (`name`),
  KEY `idx_vehicles_type` (`type`),
  KEY `idx_vehicles_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `videos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `videos`;

CREATE TABLE `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `duration` int(11) DEFAULT 0,
  `type` varchar(50) DEFAULT 'video/mp4',
  `file_size` bigint(20) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `video_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `video_categories`;

CREATE TABLE `video_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `video_playlists`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `video_playlists`;

CREATE TABLE `video_playlists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `weather_conditions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `weather_conditions`;

CREATE TABLE `weather_conditions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Short name of the weather condition (e.g. Sunny, Stormy, Foggy)',
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for UI/menus',
  `description` text DEFAULT NULL COMMENT 'Optional details (e.g. light rain at dusk, heavy snowstorm)',
  `intensity` varchar(50) DEFAULT NULL COMMENT 'Optional intensity scale (e.g. light, moderate, heavy)',
  `time_of_day_hint` varchar(50) DEFAULT NULL COMMENT 'Optional hint like morning, dusk, night',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_weather_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================================
-- VIEWS (created after tables)
-- ========================================================

-- --------------------------------------------------------
-- View: `v_anima_activity`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_anima_activity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_anima_activity` AS select `s`.`id` AS `scene_id`,`s`.`sequence` AS `scene_sequence`,`sp`.`id` AS `scene_part_id`,`sp`.`sequence` AS `part_sequence`,`a`.`id` AS `character_anima_id`,`ch`.`name` AS `character_name`,`a`.`name` AS `anima_name`,`span`.`action_type` AS `action_type`,`span`.`notes` AS `notes` from ((((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_animas` `span` on(`span`.`scene_part_id` = `sp`.`id`)) join `animas` `a` on(`a`.`id` = `span`.`character_anima_id`)) join `characters` `ch` on(`ch`.`id` = `a`.`character_id`)) order by `s`.`sequence`,`sp`.`sequence`,`ch`.`name`,`a`.`name`;

-- --------------------------------------------------------
-- View: `v_artifact_usage`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_artifact_usage`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_artifact_usage` AS select `s`.`id` AS `scene_id`,`s`.`sequence` AS `scene_sequence`,`sp`.`id` AS `scene_part_id`,`sp`.`sequence` AS `part_sequence`,`a`.`id` AS `artifact_id`,`a`.`name` AS `artifact_name`,`a`.`type` AS `artifact_type`,`a`.`status` AS `artifact_status`,`spa`.`notes` AS `notes` from (((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_artifacts` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) join `artifacts` `a` on(`a`.`id` = `spa`.`artifact_id`)) order by `s`.`sequence`,`sp`.`sequence`,`a`.`name`;

-- --------------------------------------------------------
-- View: `v_character_pose_angle_combinations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_character_pose_angle_combinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_character_pose_angle_combinations` AS select `c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`p`.`id` AS `pose_id`,`p`.`name` AS `pose_name`,`a`.`id` AS `angle_id`,`a`.`name` AS `angle_name`,concat(`c`.`name`,' (',`c`.`description`,') - ',`p`.`name`,' (',`p`.`description`,') - ',`a`.`name`,' (',`a`.`description`,')') AS `description` from ((`characters` `c` join `poses` `p`) join `angles` `a`);

-- --------------------------------------------------------
-- View: `v_export_ready`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_export_ready`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_export_ready` AS select `s`.`id` AS `scene_id`,`s`.`title` AS `scene_title`,`sp`.`id` AS `scene_part_id`,`ef`.`export_type` AS `export_type`,`ef`.`last_exported_at` AS `last_exported_at` from ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `export_flags` `ef` on(`ef`.`scene_part_id` = `sp`.`id`)) where `ef`.`ready_for_export` = 1;

-- --------------------------------------------------------
-- View: `v_gallery_animas`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_animas` AS select `f`.`id` AS `frame_id`,`a`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`a`.`id` AS `anima_id`,`a`.`name` AS `anima_name`,`a`.`traits` AS `traits`,`a`.`abilities` AS `abilities`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`c`.`role` AS `character_role`,'animas' AS `entity_type` from ((((`frames` `f` join `frames_2_animas` `m` on(`m`.`from_id` = `f`.`id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) left join `characters` `c` on(`c`.`id` = `a`.`character_id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_artifacts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_artifacts` AS select `f`.`id` AS `frame_id`,`a`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`a`.`id` AS `artifact_id`,`a`.`name` AS `artifact_name`,`a`.`type` AS `artifact_type`,`a`.`status` AS `artifact_status` from (((`frames` `f` join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `a` on(`m`.`to_id` = `a`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_backgrounds`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_backgrounds` AS select `f`.`id` AS `frame_id`,`b`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`b`.`id` AS `background_id`,`b`.`name` AS `background_name`,`b`.`type` AS `background_type`,`l`.`id` AS `location_id`,`l`.`name` AS `location_name`,'backgrounds' AS `entity_type` from ((((`frames` `f` join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`m`.`to_id` = `b`.`id`)) left join `locations` `l` on(`b`.`location_id` = `l`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_characters`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_characters` AS select `f`.`id` AS `frame_id`,`f`.`map_run_id` AS `map_run_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`c`.`role` AS `character_role`,'characters' AS `entity_type` from (((`frames` `f` join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_character_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_character_poses` AS select `f`.`id` AS `frame_id`,`cp`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`cp`.`description` AS `prompt`,`s`.`name` AS `style`,`cp`.`id` AS `character_pose_id`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`cp`.`pose_id` AS `pose_id`,`p`.`name` AS `pose_name`,`cp`.`angle_id` AS `angle_id`,`a`.`name` AS `angle_name` from ((((((`frames` `f` join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`m`.`to_id` = `cp`.`id`)) join `characters` `c` on(`cp`.`character_id` = `c`.`id`)) join `poses` `p` on(`cp`.`pose_id` = `p`.`id`)) join `angles` `a` on(`cp`.`angle_id` = `a`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 and `f`.`map_run_id` = `cp`.`active_map_run_id` order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_composites`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_composites` AS select `f`.`id` AS `frame_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `composite_id`,`c`.`name` AS `composite_name` from (((`frames` `f` join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_controlnet_maps`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_controlnet_maps` AS select `f`.`id` AS `frame_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `map_id`,`c`.`name` AS `map_name` from ((`frames` `f` join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`m`.`to_id` = `c`.`id`)) where `f`.`map_run_id` = `c`.`active_map_run_id` order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_generatives`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_generatives` AS select `f`.`id` AS `frame_id`,`g`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`g`.`id` AS `generative_id`,`g`.`name` AS `name`,`g`.`description` AS `description` from (((`frames` `f` join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`m`.`to_id` = `g`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_locations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_locations` AS select `f`.`id` AS `frame_id`,`l`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`l`.`id` AS `location_id`,`l`.`name` AS `location_name`,`l`.`type` AS `location_type`,'locations' AS `entity_type` from (((`frames` `f` join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`m`.`to_id` = `l`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_prompt_matrix_blueprints`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_prompt_matrix_blueprints` AS select `f`.`id` AS `frame_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`f`.`map_run_id` AS `map_run_id`,`b`.`id` AS `entity_id`,`b`.`name` AS `blueprint_name`,`b`.`entity_type` AS `blueprint_entity_type`,`b`.`entity_id` AS `blueprint_entity_id`,`b`.`description` AS `blueprint_description`,`b`.`matrix_id` AS `blueprint_matrix_id`,`b`.`matrix_additions_id` AS `blueprint_matrix_additions_id`,`b`.`active_map_run_id` AS `blueprint_active_map_run_id`,`b`.`state_id_active` AS `blueprint_state_id_active`,`b`.`regenerate_images` AS `blueprint_regenerate_images`,`b`.`img2img` AS `blueprint_img2img`,`b`.`cnmap` AS `blueprint_cnmap` from (((`frames` `f` join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`m`.`to_id` = `b`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1;

-- --------------------------------------------------------
-- View: `v_gallery_scene_parts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_scene_parts` AS select `f`.`id` AS `frame_id`,`sp`.`scene_part_id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`style` AS `style`,`sp`.`scene_part_id` AS `scene_part_id`,`sp`.`name` AS `scene_part_name`,`sp`.`description` AS `scene_part_description`,`sp`.`characters` AS `characters`,`sp`.`animas` AS `animas`,`sp`.`artifacts` AS `artifacts`,`sp`.`backgrounds` AS `backgrounds`,`sp`.`prompt` AS `prompt` from (((`frames` `f` join `frames_2_scene_parts` `m` on(`f`.`id` = `m`.`from_id`)) join (select `sp`.`id` AS `scene_part_id`,`sp`.`name` AS `name`,`sp`.`description` AS `description`,`sp`.`regenerate_images` AS `regenerate_images`,`sp`.`active_map_run_id` AS `active_map_run_id`,substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`,substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`,substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`,substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`,concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt` from ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) group by `sp`.`id`) `sp` on(`m`.`to_id` = `sp`.`scene_part_id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 and `f`.`map_run_id` = `sp`.`active_map_run_id` order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_sketches`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_sketches` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `sketch_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`s`.`mood` AS `mood` from (((`frames` `f` join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`m`.`to_id` = `s`.`id`)) join `styles` `st` on(`f`.`style_id` = `st`.`id`)) where `st`.`visible` = 1 order by `st`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,coalesce(`st`.`code`,`s`.`type`) AS `type`,`st`.`label` AS `type_label`,`st`.`id` AS `spawn_type_id` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) left join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns_location`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns_location`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_location` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'location' order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns_prop`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns_prop`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_prop` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'prop' order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns_reference`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns_reference`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_reference` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'reference' order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns_texture`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns_texture`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_texture` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'texture' order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_vehicles`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_vehicles` AS select `f`.`id` AS `frame_id`,`v`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`v`.`id` AS `vehicle_id`,`v`.`name` AS `vehicle_name`,`v`.`type` AS `vehicle_type`,`v`.`status` AS `vehicle_status` from (((`frames` `f` join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`m`.`to_id` = `v`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_wall_of_images`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_wall_of_images`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_wall_of_images` AS select 'animas' AS `entity_type`,`v_gallery_animas`.`frame_id` AS `frame_id`,`v_gallery_animas`.`entity_id` AS `entity_id`,`v_gallery_animas`.`filename` AS `filename`,`v_gallery_animas`.`prompt` AS `prompt`,`v_gallery_animas`.`anima_name` AS `entity_name` from `v_gallery_animas` union all select 'artifacts' AS `entity_type`,`v_gallery_artifacts`.`frame_id` AS `frame_id`,`v_gallery_artifacts`.`entity_id` AS `entity_id`,`v_gallery_artifacts`.`filename` AS `filename`,`v_gallery_artifacts`.`prompt` AS `prompt`,`v_gallery_artifacts`.`artifact_name` AS `entity_name` from `v_gallery_artifacts` union all select 'backgrounds' AS `entity_type`,`v_gallery_backgrounds`.`frame_id` AS `frame_id`,`v_gallery_backgrounds`.`entity_id` AS `entity_id`,`v_gallery_backgrounds`.`filename` AS `filename`,`v_gallery_backgrounds`.`prompt` AS `prompt`,`v_gallery_backgrounds`.`background_name` AS `entity_name` from `v_gallery_backgrounds` union all select 'characters' AS `entity_type`,`v_gallery_characters`.`frame_id` AS `frame_id`,`v_gallery_characters`.`entity_id` AS `entity_id`,`v_gallery_characters`.`filename` AS `filename`,`v_gallery_characters`.`prompt` AS `prompt`,`v_gallery_characters`.`character_name` AS `entity_name` from `v_gallery_characters` union all select 'composites' AS `entity_type`,`v_gallery_composites`.`frame_id` AS `frame_id`,`v_gallery_composites`.`entity_id` AS `entity_id`,`v_gallery_composites`.`filename` AS `filename`,`v_gallery_composites`.`prompt` AS `prompt`,`v_gallery_composites`.`composite_name` AS `entity_name` from `v_gallery_composites` union all select 'generatives' AS `entity_type`,`v_gallery_generatives`.`frame_id` AS `frame_id`,`v_gallery_generatives`.`entity_id` AS `entity_id`,`v_gallery_generatives`.`filename` AS `filename`,`v_gallery_generatives`.`prompt` AS `prompt`,`v_gallery_generatives`.`name` AS `entity_name` from `v_gallery_generatives` union all select 'locations' AS `entity_type`,`v_gallery_locations`.`frame_id` AS `frame_id`,`v_gallery_locations`.`entity_id` AS `entity_id`,`v_gallery_locations`.`filename` AS `filename`,`v_gallery_locations`.`prompt` AS `prompt`,`v_gallery_locations`.`location_name` AS `entity_name` from `v_gallery_locations` union all select 'sketches' AS `entity_type`,`v_gallery_sketches`.`frame_id` AS `frame_id`,`v_gallery_sketches`.`entity_id` AS `entity_id`,`v_gallery_sketches`.`filename` AS `filename`,`v_gallery_sketches`.`prompt` AS `prompt`,`v_gallery_sketches`.`name` AS `entity_name` from `v_gallery_sketches` union all select 'vehicles' AS `entity_type`,`v_gallery_vehicles`.`frame_id` AS `frame_id`,`v_gallery_vehicles`.`entity_id` AS `entity_id`,`v_gallery_vehicles`.`filename` AS `filename`,`v_gallery_vehicles`.`prompt` AS `prompt`,`v_gallery_vehicles`.`vehicle_name` AS `entity_name` from `v_gallery_vehicles`;

-- --------------------------------------------------------
-- View: `v_map_runs_animas`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_animas` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `a`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_animas` `m` on(`f`.`id` = `m`.`from_id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'animas';

-- --------------------------------------------------------
-- View: `v_map_runs_artifacts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_artifacts` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `ar`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `ar` on(`ar`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'artifacts';

-- --------------------------------------------------------
-- View: `v_map_runs_backgrounds`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_backgrounds` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `b`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`b`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'backgrounds';

-- --------------------------------------------------------
-- View: `v_map_runs_characters`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_characters` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'characters';

-- --------------------------------------------------------
-- View: `v_map_runs_character_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_character_poses` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `cp`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`cp`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'character_poses';

-- --------------------------------------------------------
-- View: `v_map_runs_composites`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_composites` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'composites';

-- --------------------------------------------------------
-- View: `v_map_runs_controlnet_maps`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_controlnet_maps` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'controlnet_maps';

-- --------------------------------------------------------
-- View: `v_map_runs_generatives`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_generatives` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `g`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`g`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'generatives';

-- --------------------------------------------------------
-- View: `v_map_runs_locations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_locations` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `l`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`l`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'locations';

-- --------------------------------------------------------
-- View: `v_map_runs_prompt_matrix_blueprints`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_prompt_matrix_blueprints` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `b`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`b`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'prompt_matrix_blueprints';

-- --------------------------------------------------------
-- View: `v_map_runs_scene_parts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_scene_parts` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`f2sp`.`to_id` AS `entity_id`,case when `mr`.`id` = `sp`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_scene_parts` `f2sp` on(`f2sp`.`from_id` = `f`.`id`)) join `scene_parts` `sp` on(`sp`.`id` = `f2sp`.`to_id`)) where `mr`.`entity_type` = 'scene_parts';

-- --------------------------------------------------------
-- View: `v_map_runs_sketches`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_sketches` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `s`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`s`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'sketches';

-- --------------------------------------------------------
-- View: `v_map_runs_vehicles`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_vehicles` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `v`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`v`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'vehicles';

-- --------------------------------------------------------
-- View: `v_prompts_animas`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_animas` AS select `a`.`id` AS `id`,`a`.`regenerate_images` AS `regenerate_images`,coalesce(`a`.`description`,'') AS `prompt` from `animas` `a`;

-- --------------------------------------------------------
-- View: `v_prompts_artifacts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_artifacts` AS select `ar`.`id` AS `id`,`ar`.`regenerate_images` AS `regenerate_images`,coalesce(`ar`.`description`,'') AS `prompt` from `artifacts` `ar`;

-- --------------------------------------------------------
-- View: `v_prompts_backgrounds`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_backgrounds` AS select `b`.`id` AS `id`,`b`.`regenerate_images` AS `regenerate_images`,coalesce(`b`.`description`,'') AS `prompt` from `backgrounds` `b`;

-- --------------------------------------------------------
-- View: `v_prompts_characters`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_characters` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,coalesce(`c`.`description`,'') AS `prompt` from `characters` `c`;

-- --------------------------------------------------------
-- View: `v_prompts_character_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_character_poses` AS select `cp`.`id` AS `id`,`cp`.`regenerate_images` AS `regenerate_images`,concat('((',`c`.`name`,': ',`c`.`description`,')) ','(Pose: ',`p`.`description`,') ','(Angle: ',`a`.`description`,')') AS `prompt` from (((`character_poses` `cp` join `characters` `c` on(`cp`.`character_id` = `c`.`id`)) join `poses` `p` on(`cp`.`pose_id` = `p`.`id`)) join `angles` `a` on(`cp`.`angle_id` = `a`.`id`));

-- --------------------------------------------------------
-- View: `v_prompts_composites`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_composites` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,coalesce(`c`.`description`,'') AS `prompt` from `composites` `c`;

-- --------------------------------------------------------
-- View: `v_prompts_controlnet_maps`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_controlnet_maps` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,concat_ws(', ',`c`.`name`,coalesce(`c`.`description`,'')) AS `prompt` from `controlnet_maps` `c`;

-- --------------------------------------------------------
-- View: `v_prompts_generatives`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_generatives` AS select `g`.`id` AS `id`,`g`.`regenerate_images` AS `regenerate_images`,concat_ws(', ',`g`.`name`,coalesce(`g`.`description`,'')) AS `prompt` from `generatives` `g`;

-- --------------------------------------------------------
-- View: `v_prompts_locations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_locations` AS select `l`.`id` AS `id`,`l`.`regenerate_images` AS `regenerate_images`,coalesce(`l`.`description`,'') AS `prompt` from `locations` `l`;

-- --------------------------------------------------------
-- View: `v_prompts_prompt_matrix_blueprints`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_prompt_matrix_blueprints` AS select `prompt_matrix_blueprints`.`id` AS `id`,`prompt_matrix_blueprints`.`regenerate_images` AS `regenerate_images`,coalesce(`prompt_matrix_blueprints`.`description`,'') AS `prompt` from `prompt_matrix_blueprints`;

-- --------------------------------------------------------
-- View: `v_prompts_scene_parts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_scene_parts` AS select `sp`.`id` AS `scene_part_id`,`sp`.`id` AS `id`,`sp`.`scene_id` AS `scene_id`,`sp`.`name` AS `name`,`sp`.`description` AS `description`,substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`,substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`,substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`,substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`,concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt`,`sp`.`regenerate_images` AS `regenerate_images` from ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) group by `sp`.`id`;

-- --------------------------------------------------------
-- View: `v_prompts_sketches`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_sketches` AS select `s`.`id` AS `id`,`s`.`regenerate_images` AS `regenerate_images`,coalesce(`s`.`description`,'') AS `prompt` from `sketches` `s`;

-- --------------------------------------------------------
-- View: `v_prompts_vehicles`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_vehicles` AS select `v`.`id` AS `id`,`v`.`regenerate_images` AS `regenerate_images`,coalesce(`v`.`description`,'') AS `prompt` from `vehicles` `v`;

-- --------------------------------------------------------
-- View: `v_scenes_under_review`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_scenes_under_review`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_scenes_under_review` AS select `s`.`id` AS `scene_id`,`s`.`title` AS `scene_title`,`sp`.`id` AS `scene_part_id`,`ps`.`stage` AS `stage`,`ps`.`assigned_to` AS `assigned_to`,`ps`.`updated_at` AS `updated_at` from ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `production_status` `ps` on(`ps`.`scene_part_id` = `sp`.`id`)) where `ps`.`stage` = 'review';

-- --------------------------------------------------------
-- View: `v_scene_part_full`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_scene_part_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_scene_part_full` AS select `sp`.`id` AS `scene_part_id`,`sp`.`name` AS `scene_part_name`,`sp`.`description` AS `scene_part_description`,`p`.`angle` AS `perspective_angle`,`p`.`description` AS `perspective_notes`,`b`.`name` AS `background_name`,`b`.`description` AS `background_description`,group_concat(distinct `a`.`name` separator ', ') AS `animas_in_scene`,group_concat(distinct concat(`a`.`name`,': ',`a`.`traits`,'; ',`a`.`abilities`) separator ' | ') AS `animas_details` from (((((`scene_parts` `sp` join `perspectives` `p` on(`p`.`scene_part_id` = `sp`.`id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `p`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) group by `sp`.`id`,`p`.`id`,`b`.`id` order by `sp`.`sequence`,`p`.`id`;

-- --------------------------------------------------------
-- View: `v_styles_helper`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_styles_helper`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_styles_helper` AS select `s`.`id` AS `id`,0 AS `regenerate_images`,concat('(',coalesce(`s`.`description`,''),')','(',(select `prompt_globals`.`description` from `prompt_globals` where `prompt_globals`.`id` = 1),')') AS `prompt` from `styles` `s` where `s`.`active` = 1 order by `s`.`order`;

