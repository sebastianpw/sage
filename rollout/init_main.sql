-- Adminer 5.3.0 MariaDB 12.0.2-MariaDB dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `angles`;
CREATE TABLE `angles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `angles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1,	'Front',	'front view, character facing the viewer',	'2025-08-22 20:18:15',	'2025-08-23 19:57:43'),
(2,	'Back',	'back view, character facing away from the viewer',	'2025-08-22 20:18:15',	'2025-08-23 19:57:43'),
(3,	'Left Profile',	'left profile view, character facing left side',	'2025-08-22 20:18:15',	'2025-08-23 19:57:43'),
(9,	'Top',	'View from above',	'2025-08-22 20:18:15',	'2025-08-22 20:18:15');

DROP TABLE IF EXISTS `animas`;
CREATE TABLE `animas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
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
  PRIMARY KEY (`id`),
  KEY `idx_character_animas_character` (`character_id`),
  KEY `idx_character_animas_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artifacts_name` (`name`),
  KEY `idx_artifacts_type` (`type`),
  KEY `idx_artifacts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backgrounds_name` (`name`),
  KEY `idx_backgrounds_type` (`type`),
  KEY `idx_backgrounds_location` (`location_id`),
  CONSTRAINT `fk_backgrounds_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `composite_frames`;
CREATE TABLE `composite_frames` (
  `composite_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`composite_id`,`frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_animas`;
CREATE TABLE `frames_2_animas` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `anima_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_artifacts`;
CREATE TABLE `frames_2_artifacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_frame_artifact_from` (`from_id`),
  KEY `idx_frame_artifact_to` (`to_id`),
  CONSTRAINT `fk_frames_artifacts_artifact` FOREIGN KEY (`to_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_backgrounds`;
CREATE TABLE `frames_2_backgrounds` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `background_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_characters`;
CREATE TABLE `frames_2_characters` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `character_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_character_poses`;
CREATE TABLE `frames_2_character_poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `from_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_composites`;
CREATE TABLE `frames_2_composites` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `composite_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_controlnet_maps`;
CREATE TABLE `frames_2_controlnet_maps` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `to_id_idx` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


DROP TABLE IF EXISTS `frames_2_generatives`;
CREATE TABLE `frames_2_generatives` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_frame_generative_from` (`from_id`),
  KEY `idx_frame_generative_to` (`to_id`),
  CONSTRAINT `fk_frames_generatives_generative` FOREIGN KEY (`to_id`) REFERENCES `generatives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_locations`;
CREATE TABLE `frames_2_locations` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `location_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_pastebin`;
CREATE TABLE `frames_2_pastebin` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `location_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_prompt_matrix_blueprints`;
CREATE TABLE `frames_2_prompt_matrix_blueprints` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `prompt_matrix_blueprint_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_scene_parts`;
CREATE TABLE `frames_2_scene_parts` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `to_id` (`to_id`),
  CONSTRAINT `frames_2_scene_parts_ibfk_2` FOREIGN KEY (`to_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_sketches`;
CREATE TABLE `frames_2_sketches` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_frame_sketch_from` (`from_id`),
  KEY `idx_frame_sketch_to` (`to_id`),
  CONSTRAINT `fk_frames_sketches_sketch` FOREIGN KEY (`to_id`) REFERENCES `sketches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_spawns`;
CREATE TABLE `frames_2_spawns` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `seed_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frames_2_vehicles`;
CREATE TABLE `frames_2_vehicles` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_from_id` (`from_id`),
  KEY `idx_to_id` (`to_id`),
  CONSTRAINT `fk_frames_2_vehicles_to` FOREIGN KEY (`to_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `frame_counter`;
CREATE TABLE `frame_counter` (
  `id` int(11) NOT NULL DEFAULT 1,
  `next_frame` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `frame_counter` (`id`, `next_frame`) VALUES
(1,	8459);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `generator_config`;
CREATE TABLE `generator_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `model` varchar(100) NOT NULL DEFAULT 'openai',
  `system_role` text NOT NULL,
  `instructions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`instructions`)),
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`parameters`)),
  `output_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`output_schema`)),
  `examples` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`examples`)),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_id` (`config_id`),
  KEY `user_id` (`user_id`),
  KEY `active` (`active`),
  KEY `idx_user_active` (`user_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `generator_config` (`id`, `config_id`, `user_id`, `title`, `model`, `system_role`, `instructions`, `parameters`, `output_schema`, `examples`, `created_at`, `updated_at`, `active`) VALUES
(1,	'2df46a413521b0041029775c5f6926c6',	1,	'Tongue Twister Generator',	'openai',	'Zungenbrecher Oracle',	'[\"You are an expert tongue-twister generator in German and English.\",\"Generate creative, linguistically challenging twisters.\",\"CRITICAL: Return ONLY a single valid JSON object matching the output schema.\",\"If you cannot comply, return: {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"brief explanation\\\"}\"]',	'{\"mode\":{\"type\":\"string\",\"enum\":[\"easy\",\"medium\",\"extreme\"],\"default\":\"medium\",\"description\":\"Difficulty level\"},\"language\":{\"type\":\"string\",\"enum\":[\"german\",\"english\"],\"default\":\"german\"},\"firstLetter\":{\"type\":\"string\",\"pattern\":\"^[A-Za-z\\u00c4\\u00d6\\u00dc\\u00e4\\u00f6\\u00fc\\u00df]$\",\"optional\":true,\"description\":\"All words must start with this letter\"}}',	'{\"type\":\"object\",\"properties\":{\"mode\":{\"type\":\"string\"},\"language\":{\"type\":\"string\"},\"twister\":{\"type\":\"string\"},\"metadata\":{\"type\":\"object\",\"properties\":{\"wordCount\":{\"type\":\"integer\"},\"firstLetter\":{\"type\":\"string\"},\"alternatives\":{\"type\":\"array\",\"items\":{\"type\":\"string\"}}}}},\"required\":[\"mode\",\"language\",\"twister\",\"metadata\"]}',	'[{\"input\":{\"mode\":\"medium\",\"language\":\"german\",\"firstLetter\":\"S\"},\"output\":{\"mode\":\"medium\",\"language\":\"german\",\"twister\":\"Sieben saftige Schnecken schl\\u00fcrfen s\\u00fc\\u00dfe Sahne.\",\"metadata\":{\"wordCount\":7,\"firstLetter\":\"S\",\"alternatives\":[]}}}]',	'2025-10-17 09:11:04',	'2025-10-17 09:31:58',	1),
(2,	'59979fe535aebc1a5ff6ebbc5dc1d674',	1,	'Cyberpunk Scene Generator',	'groq/compound',	'Cyberpunk Scene Writer',	'[\"You are an expert anime-style cyberpunk scene writer.\",\"Generate cinematic, atmospheric scene descriptions.\",\"CRITICAL: Return ONLY a single valid JSON object matching the schema.\",\"Include 3-6 visual beats (micro-shots) per scene.\",\"If you cannot comply with schema, return: {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"brief reason\\\"}\"]',	'{\"theme\":{\"type\":\"string\",\"enum\":[\"action\",\"chase\",\"revelation\",\"quiet\",\"encounter\"],\"default\":\"action\",\"description\":\"Scene narrative purpose\"},\"style\":{\"type\":\"string\",\"enum\":[\"anime\",\"cyberpunk\",\"noir\",\"cinematic\"],\"default\":\"cyberpunk\"},\"setting\":{\"type\":\"string\",\"optional\":true,\"description\":\"Location hint (e.g., \'neon rooftop\', \'underground lab\')\"},\"length\":{\"type\":\"object\",\"properties\":{\"min\":{\"type\":\"integer\",\"default\":3},\"max\":{\"type\":\"integer\",\"default\":6}}},\"language\":{\"type\":\"string\",\"enum\":[\"english\",\"german\"],\"default\":\"english\"}}',	'{\"type\":\"object\",\"properties\":{\"theme\":{\"type\":\"string\"},\"style\":{\"type\":\"string\"},\"scene\":{\"type\":\"string\",\"description\":\"Cinematic paragraph\"},\"beats\":{\"type\":\"array\",\"items\":{\"type\":\"string\"},\"description\":\"3-6 micro-shots (2-12 words each)\"},\"metadata\":{\"type\":\"object\",\"properties\":{\"language\":{\"type\":\"string\"},\"sentenceCount\":{\"type\":\"integer\"},\"wordCount\":{\"type\":\"integer\"},\"setting\":{\"type\":\"string\"}}}},\"required\":[\"theme\",\"style\",\"scene\",\"beats\",\"metadata\"]}',	'[{\"input\":{\"theme\":\"action\",\"style\":\"cyberpunk\",\"setting\":\"neon rooftop\",\"length\":{\"min\":4,\"max\":5}},\"output\":{\"theme\":\"action\",\"style\":\"cyberpunk\",\"scene\":\"Rain-slicked neon signs flicker above as Rin vaults between rooftops. A helicopter searchlight sweeps below. She draws her blade\\u2014electric blue arc crackling in the darkness. Three corporate security drones converge. Time slows as she spins, cutting through the first with precision.\",\"beats\":[\"Helicopter searchlight sweeps streets\",\"Rin draws crackling blade\",\"Security drones converge\",\"Blade cuts through first drone\"],\"metadata\":{\"language\":\"english\",\"sentenceCount\":5,\"wordCount\":52,\"setting\":\"neon rooftop\"}}}]',	'2025-10-17 09:34:45',	'2025-10-17 10:16:19',	1),
(3,	'377ba2b06df4c4d25eef4f864024aaa8',	1,	'Social Media Post Generator',	'groq/compound',	'Social Media Manager',	'[\"You are an expert social media content creator.\",\"Write engaging, platform-optimized posts with appropriate hashtags.\",\"Match the tone to the platform and brand voice.\",\"Return ONLY valid JSON matching the output schema.\",\"If you cannot follow the schema, return: {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"why\\\"}\"]',	'{\"platform\":{\"type\":\"string\",\"enum\":[\"twitter\",\"instagram\",\"linkedin\",\"facebook\"],\"default\":\"instagram\"},\"topic\":{\"type\":\"string\",\"description\":\"Topic or message for the post\"},\"tone\":{\"type\":\"string\",\"enum\":[\"professional\",\"casual\",\"inspirational\",\"humorous\",\"educational\"],\"default\":\"casual\"},\"includeHashtags\":{\"type\":\"boolean\",\"default\":true},\"includeEmojis\":{\"type\":\"boolean\",\"default\":true},\"language\":{\"type\":\"string\",\"enum\":[\"english\",\"german\"],\"default\":\"english\"}}',	'{\"type\":\"object\",\"properties\":{\"platform\":{\"type\":\"string\"},\"post\":{\"type\":\"string\"},\"hashtags\":{\"type\":\"array\",\"items\":{\"type\":\"string\"}},\"callToAction\":{\"type\":\"string\"},\"metadata\":{\"type\":\"object\",\"properties\":{\"characterCount\":{\"type\":\"integer\"},\"wordCount\":{\"type\":\"integer\"},\"tone\":{\"type\":\"string\"},\"estimatedEngagement\":{\"type\":\"string\"}}}},\"required\":[\"platform\",\"post\",\"hashtags\",\"callToAction\",\"metadata\"]}',	'[{\"input\":{\"platform\":\"instagram\",\"topic\":\"New coffee blend launch\",\"tone\":\"casual\",\"includeHashtags\":true,\"includeEmojis\":true},\"output\":{\"platform\":\"instagram\",\"post\":\"\\u2615\\ufe0f Meet our newest obsession: Midnight Roast! We\'ve been perfecting this blend for months, and it\'s finally here. Rich, smooth, with notes of dark chocolate and caramel. Your morning routine just got a major upgrade. \\u2728\",\"hashtags\":[\"#MidnightRoast\",\"#CoffeeLovers\",\"#NewBlend\",\"#SpecialtyCoffee\",\"#CoffeeCommunity\"],\"callToAction\":\"Shop now - link in bio! Limited first batch available.\",\"metadata\":{\"characterCount\":245,\"wordCount\":45,\"tone\":\"casual\",\"estimatedEngagement\":\"high\"}}}]',	'2025-10-17 10:13:39',	'2025-10-17 10:14:43',	1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `image_stash`;
CREATE TABLE `image_stash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_path` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `interactions`;
CREATE TABLE `interactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_part_id` int(11) NOT NULL,
  `type` enum('action','reaction','dialogue') NOT NULL,
  `character_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `emotion` varchar(50) DEFAULT NULL,
  `sequence` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_interactions_scene_part` (`scene_part_id`),
  KEY `idx_interactions_character` (`character_id`),
  KEY `idx_interactions_order` (`scene_part_id`,`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locations_name` (`name`),
  KEY `idx_locations_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` varchar(10) NOT NULL,
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`message`)),
  `log_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `meta_entities` (`id`, `name`, `type`, `order`, `created_at`, `updated_at`, `regenerate_images`, `active_map_run_id`, `state_id_active`, `img2img`, `img2img_frame_id`, `img2img_prompt`) VALUES
(2,	'characters',	'BASE TABLE',	5,	'2025-08-19 12:12:16',	'2025-09-12 16:19:02',	0,	NULL,	NULL,	0,	NULL,	NULL),
(3,	'animas',	'BASE TABLE',	7,	'2025-08-19 12:12:16',	'2025-09-12 16:19:02',	0,	NULL,	NULL,	0,	NULL,	NULL),
(4,	'locations',	'BASE TABLE',	8,	'2025-08-19 12:12:16',	'2025-09-12 16:19:02',	0,	NULL,	NULL,	0,	NULL,	NULL),
(5,	'backgrounds',	'BASE TABLE',	9,	'2025-08-19 12:12:16',	'2025-09-12 16:19:02',	0,	NULL,	NULL,	0,	NULL,	NULL),
(6,	'artifacts',	'BASE TABLE',	10,	'2025-08-19 12:12:16',	'2025-09-12 16:19:02',	0,	NULL,	NULL,	0,	NULL,	NULL),
(7,	'vehicles',	'BASE TABLE',	700,	'2025-08-19 12:12:16',	'2025-09-01 17:23:25',	0,	NULL,	NULL,	0,	NULL,	NULL),
(8,	'scene_parts',	'BASE TABLE',	800,	'2025-08-19 12:12:16',	'2025-09-01 17:23:29',	0,	NULL,	NULL,	0,	NULL,	NULL),
(10,	'meta_entities',	'BASE TABLE',	3,	'2025-08-19 13:35:47',	'2025-09-12 16:19:02',	0,	NULL,	NULL,	0,	NULL,	NULL),
(19,	'character_poses',	'BASE TABLE',	6,	'2025-08-22 19:58:43',	'2025-09-12 16:19:02',	0,	NULL,	NULL,	0,	NULL,	NULL),
(20,	'generatives',	'BASE TABLE',	900,	'2025-08-24 12:27:55',	'2025-09-01 17:27:08',	0,	NULL,	NULL,	0,	NULL,	NULL),
(21,	'sketches',	'BASE TABLE',	1000,	'2025-08-24 12:28:11',	'2025-09-01 17:27:13',	0,	NULL,	NULL,	0,	NULL,	NULL),
(23,	'spawns',	'BASE TABLE',	1100,	'2025-08-24 20:28:02',	'2025-09-29 12:09:21',	0,	NULL,	NULL,	0,	NULL,	NULL),
(24,	'pastebin',	'BASE TABLE',	1,	'2025-09-04 16:19:29',	'2025-09-04 16:20:30',	0,	NULL,	NULL,	0,	NULL,	NULL),
(25,	'controlnet_maps',	'BASE TABLE',	4,	'2025-09-12 16:18:24',	'2025-09-12 16:19:02',	0,	NULL,	NULL,	0,	NULL,	NULL),
(26,	'prompt_matrix_blueprints',	'BASE TABLE',	1000,	'2025-08-24 12:28:11',	'2025-09-01 17:27:13',	0,	NULL,	NULL,	0,	NULL,	NULL),
(27,	'composites',	'BASE TABLE',	1000,	'2025-10-04 16:15:02',	'2025-10-04 16:15:02',	0,	NULL,	NULL,	0,	NULL,	NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


DELIMITER ;;

CREATE TRIGGER `pastebin_before_insert` BEFORE INSERT ON `pastebin` FOR EACH ROW
BEGIN
  IF NEW.url_token IS NULL OR NEW.url_token = '' THEN
    SET NEW.url_token = SHA2(UUID(), 256);
  END IF;
END;;

DELIMITER ;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `prompt_ideations`;
CREATE TABLE `prompt_ideations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `scheduled_tasks` (`id`, `name`, `order`, `script_path`, `args`, `schedule_time`, `schedule_interval`, `schedule_dow`, `last_run`, `active`, `description`, `max_concurrent_runs`, `lock_timeout_minutes`, `require_lock`, `lock_scope`, `created_at`, `updated_at`, `run_now`) VALUES
(10,	'gfs ⚡ Generatives # -----------------------------# Usage / Arguments# -----------------------------BASE_PROMPT=\"$1\"ENTITY_TYPE=\"$2\"ENTITY_ID=\"$3\"LIMIT=\"$4\"OFFSET=\"$5\"NO_STYLES=\"$6\"ADD_TO_PROMPT=\"$7\"',	2,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'generatives',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-15 20:55:28',	0,	'Generates frames for entity generatives',	1,	60,	1,	'entity',	'2025-08-21 03:23:13',	'2025-10-15 20:55:28',	0),
(11,	'gfs 🦸 Characters # -----------------------------# Usage / Arguments# -----------------------------BASE_PROMPT=\"$1\"ENTITY_TYPE=\"$2\"ENTITY_ID=\"$3\"LIMIT=\"$4\"OFFSET=\"$5\"NO_STYLES=\"$6\"ADD_TO_PROMPT=\"$7\"✓',	8,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'characters',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-15 20:57:11',	0,	'Generates frames for entity generatives',	1,	60,	1,	'entity',	'2025-08-21 03:23:13',	'2025-10-15 20:57:11',	0),
(12,	'gfs 🐾 Animas',	10,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'animas',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-03 08:50:53',	0,	'Generates frames for entity generatives',	1,	60,	1,	'entity',	'2025-08-21 03:23:13',	'2025-10-11 18:45:31',	0),
(13,	'gfs 🗺️ Locations',	10,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'locations',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-03 18:49:44',	0,	'Generates frames for entity generatives',	1,	60,	1,	'entity',	'2025-08-21 03:23:13',	'2025-10-11 18:31:57',	0),
(15,	'gfs 🎨 Sketches',	9,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'sketches',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-17 12:09:20',	0,	'Generates frames for entity generatives',	1,	60,	1,	'entity',	'2025-08-21 03:23:13',	'2025-10-17 12:09:20',	0),
(16,	'gfs 🏞️ Backgrounds # -----------------------------# Usage / Arguments# -----------------------------BASE_PROMPT=\"$1\"ENTITY_TYPE=\"$2\"ENTITY_ID=\"$3\"LIMIT=\"$4\"OFFSET=\"$5\"NO_STYLES=\"$6\"ADD_TO_PROMPT=\"$7\"',	9,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'backgrounds',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-03 11:38:09',	0,	'Generates frames for entity generatives',	1,	60,	1,	'entity',	'2025-08-21 03:23:13',	'2025-10-11 18:45:31',	0),
(17,	'gfs 🛸 Vehicles',	10,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'vehicles',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-03 08:42:58',	0,	'Generates frames for entity generatives',	1,	60,	1,	'entity',	'2025-08-21 03:23:13',	'2025-10-11 18:31:57',	0),
(18,	'gfs 🏺 Artifacts',	9,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'artifacts',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-03 08:36:57',	1,	NULL,	1,	60,	1,	'entity',	'2025-08-30 22:20:25',	'2025-10-11 18:31:57',	0),
(19,	'gfs 🤸 Character Poses',	7,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'character_poses',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-09-28 17:41:08',	1,	NULL,	1,	60,	1,	'entity',	'2025-08-30 22:20:56',	'2025-10-11 18:45:48',	0),
(20,	'gms Controlnet Maps',	6,	'/data/data/com.termux/files/home/www/spwbase/bash/genmaps_fromdb.sh',	'controlnet_maps',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-11 17:45:15',	1,	NULL,	1,	60,	1,	'entity',	'2025-09-17 11:13:55',	'2025-10-11 19:45:15',	0),
(22,	'sw 🌠 Toggle Stable Diffusion API: JUPYTER / pollinations.ai',	4,	'/data/data/com.termux/files/home/www/spwbase/bash/switch.sh',	'',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-09-29 23:24:50',	1,	NULL,	1,	60,	1,	'entity',	'2025-09-21 04:45:01',	'2025-10-11 18:46:03',	0),
(23,	'gfs 🌌 Prompt Matrix Blueprints',	5,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'prompt_matrix_blueprints',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-03 11:44:24',	1,	NULL,	1,	60,	1,	'entity',	'2025-09-30 01:15:24',	'2025-10-11 18:45:53',	0),
(24,	'gfs 🧩 Composites',	3,	'/data/data/com.termux/files/home/www/spwbase/bash/genframes_fromdb.sh',	'composites',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-11 18:20:54',	1,	NULL,	1,	60,	1,	'entity',	'2025-10-04 18:47:41',	'2025-10-11 20:20:54',	0),
(25,	'swenv 🎛️ Switch environments',	1,	'/data/data/com.termux/files/home/www/spwbase/bash/switchenv.sh',	'init',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-10-15 20:11:24',	1,	NULL,	1,	60,	1,	'entity',	'2025-10-08 14:26:29',	'2025-10-15 20:11:24',	0);

DROP TABLE IF EXISTS `scheduler_heartbeat`;
CREATE TABLE `scheduler_heartbeat` (
  `id` tinyint(4) NOT NULL,
  `last_seen` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `scheduler_heartbeat` (`id`, `last_seen`) VALUES
(1,	'2025-10-17 12:40:51');

DROP TABLE IF EXISTS `seeds`;
CREATE TABLE `seeds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seeds_value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  PRIMARY KEY (`id`),
  KEY `idx_seeds_type` (`type`),
  KEY `idx_spawn_type_id` (`spawn_type_id`),
  CONSTRAINT `fk_spawns_spawn_type` FOREIGN KEY (`spawn_type_id`) REFERENCES `spawn_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `spawn_types` (`id`, `code`, `label`, `description`, `gallery_view`, `upload_enabled`, `batch_import_enabled`, `sort_order`, `active`, `created_at`) VALUES
(1,	'default',	'Default Spawns',	'Standard uploaded images for img2img and presentation',	'v_gallery_spawns',	1,	1,	1,	1,	'2025-10-07 12:54:52'),
(2,	'reference',	'Reference Images',	'High-quality reference images for style matching',	'v_gallery_spawns_reference',	1,	1,	2,	1,	'2025-10-07 12:54:52'),
(3,	'texture',	'Texture Library',	'Seamless textures and patterns',	'v_gallery_spawns_texture',	1,	1,	3,	0,	'2025-10-07 12:54:52');

DROP TABLE IF EXISTS `states`;
CREATE TABLE `states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_states_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `styles` (`id`, `active`, `visible`, `name`, `order`, `description`, `keywords`, `color_tone`, `created_at`, `updated_at`) VALUES
(3,	0,	1,	'anime studio J.C. Staff style (J.C. Staff)',	1,	'Balanced, polished commercial anime; realistic proportions; works well for school-life, romance, and comedy.',	'clean lineart, balanced proportions, soft colors, polished, natural character expressions, smooth shading',	'soft, natural, vibrant',	'2025-09-01 12:54:00',	'2025-10-13 18:26:06'),
(6,	0,	1,	'anime studio Sunrise style (Sunrise)',	1,	'Epic mecha & sci-fi storytelling; large-scale environments; colorful, iconic character design.',	'detailed sci-fi architecture, bold character design, vibrant colors, dynamic composition, futuristic technology',	'vibrant, epic, futuristic',	'2025-09-01 12:54:00',	'2025-09-24 14:24:16'),
(11,	0,	1,	'anime studio CLAMP style (CLAMP)',	1,	'Elegant and stylized manga-inspired lines; fantasy and romance; ornate and flowing character designs.',	'elegant lineart, flowing hair and clothing, delicate proportions, fantasy elements, expressive eyes, intricate detail',	'delicate, flowing, pastel',	'2025-09-01 12:54:00',	'2025-10-14 11:04:31'),
(12,	0,	1,	'anime studio Kyoto Animation style (Kyoto Animation)',	1,	'Soft, polished, emotionally resonant; expressive faces and body language; detailed, vibrant backgrounds.',	'smooth cel-shading, cinematic lighting, warm atmosphere, expressive characters, lush backgrounds, consistent proportions',	'warm, soft, vibrant',	'2025-09-01 12:54:00',	'2025-10-17 12:10:07'),
(13,	1,	1,	'Realistic',	1,	'Three-point lighting with a softbox flash, creating a dramatic effect. Photo has a depth of field. Highly detailed, hyper-realistic, 8k resolution, 32k resolution, masterpiece',	NULL,	NULL,	'2025-09-08 17:27:27',	'2025-10-17 12:10:08'),
(14,	0,	1,	'anime neutral white background ',	1,	'colorful, iconic character design.',	'white canvas, white background, bold character design, vibrant colors',	'vibrant, epic',	'2025-09-01 12:54:00',	'2025-09-24 14:24:16'),
(15,	0,	1,	'counterfeit',	2,	'counterfeit style',	'',	'',	'2025-09-01 10:54:00',	'2025-09-28 11:00:35'),
(16,	0,	1,	'anything',	2,	'anything style',	'',	'',	'2025-09-01 10:54:00',	'2025-09-24 14:24:33'),
(17,	0,	1,	'maturemalemix',	2,	'maturemalemix style',	'',	'',	'2025-09-01 10:54:00',	'2025-09-24 14:24:33'),
(18,	0,	1,	'cetus',	2,	'cetus coda style',	'',	'',	'2025-09-01 10:54:00',	'2025-09-24 14:24:33'),
(19,	0,	1,	'meina mix',	2,	'meina mix style',	'',	'',	'2025-09-01 10:54:00',	'2025-09-24 14:24:33'),
(20,	0,	1,	'cominoir2',	2,	'cominoir2 style',	'',	'',	'2025-09-01 10:54:00',	'2025-09-28 14:42:33'),
(21,	0,	1,	'LCM SDXL',	2,	'',	'',	'',	'2025-09-01 10:54:00',	'2025-09-30 11:15:01'),
(22,	0,	1,	'LCM animagine xl',	2,	'',	'',	'',	'2025-09-01 10:54:00',	'2025-09-30 22:57:37'),
(23,	0,	1,	'LCM dreamshaper v7',	2,	'',	'',	'',	'2025-09-01 10:54:00',	'2025-10-08 10:58:55'),
(24,	0,	1,	'nanobanana pollinations',	2,	'',	'',	'',	'2025-10-08 10:58:40',	'2025-10-13 18:25:41');

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


DROP TABLE IF EXISTS `tags2poses`;
CREATE TABLE `tags2poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `tags_2_frames`;
CREATE TABLE `tags_2_frames` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Frame ID',
  UNIQUE KEY `uq_tags_2_frames` (`from_id`,`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicles_name` (`name`),
  KEY `idx_vehicles_type` (`type`),
  KEY `idx_vehicles_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `videos`;
CREATE TABLE `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `duration` int(11) DEFAULT 0,
  `type` varchar(50) DEFAULT 'video/mp4',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP VIEW IF EXISTS `v_anima_activity`;
CREATE TABLE `v_anima_activity` (`scene_id` int(11), `scene_sequence` int(11), `scene_part_id` int(11), `part_sequence` int(11), `character_anima_id` int(11), `character_name` varchar(100), `anima_name` varchar(255), `action_type` enum('misfire','assist','comic_beat','strategic_move'), `notes` text);


DROP VIEW IF EXISTS `v_artifact_usage`;
CREATE TABLE `v_artifact_usage` (`scene_id` int(11), `scene_sequence` int(11), `scene_part_id` int(11), `part_sequence` int(11), `artifact_id` int(11), `artifact_name` varchar(100), `artifact_type` varchar(50), `artifact_status` enum('inactive','active','corrupted','purified'), `notes` text);


DROP VIEW IF EXISTS `v_character_pose_angle_combinations`;
CREATE TABLE `v_character_pose_angle_combinations` (`character_id` int(11), `character_name` varchar(100), `pose_id` int(11), `pose_name` varchar(100), `angle_id` int(11), `angle_name` varchar(100), `description` mediumtext);


DROP VIEW IF EXISTS `v_dialogue_tts`;
CREATE TABLE `v_dialogue_tts` (`scene_id` int(11), `scene_sequence` int(11), `scene_title` varchar(255), `scene_part_id` int(11), `part_sequence` int(11), `line_sequence` int(11), `character_name` varchar(100), `emotion` varchar(50), `dialogue` text);


DROP VIEW IF EXISTS `v_export_ready`;
CREATE TABLE `v_export_ready` (`scene_id` int(11), `scene_title` varchar(255), `scene_part_id` int(11), `export_type` enum('script','art','audio','full_package'), `last_exported_at` timestamp);


DROP VIEW IF EXISTS `v_gallery_animas`;
CREATE TABLE `v_gallery_animas` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `anima_id` int(11), `anima_name` varchar(255), `traits` text, `abilities` text, `character_id` int(11), `character_name` varchar(100), `character_role` varchar(100));


DROP VIEW IF EXISTS `v_gallery_artifacts`;
CREATE TABLE `v_gallery_artifacts` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `artifact_id` int(11), `artifact_name` varchar(100), `artifact_type` varchar(50), `artifact_status` enum('inactive','active','corrupted','purified'));


DROP VIEW IF EXISTS `v_gallery_backgrounds`;
CREATE TABLE `v_gallery_backgrounds` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `background_id` int(11), `background_name` varchar(100), `background_type` varchar(50), `location_id` int(11), `location_name` varchar(100));


DROP VIEW IF EXISTS `v_gallery_characters`;
CREATE TABLE `v_gallery_characters` (`frame_id` int(11), `map_run_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `character_id` int(11), `character_name` varchar(100), `character_role` varchar(100));


DROP VIEW IF EXISTS `v_gallery_character_poses`;
CREATE TABLE `v_gallery_character_poses` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` varchar(100), `character_pose_id` int(11), `character_id` int(11), `character_name` varchar(100), `pose_id` int(11), `pose_name` varchar(100), `angle_id` int(11), `angle_name` varchar(100));


DROP VIEW IF EXISTS `v_gallery_composites`;
CREATE TABLE `v_gallery_composites` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `composite_id` int(11), `composite_name` varchar(100));


DROP VIEW IF EXISTS `v_gallery_controlnet_maps`;
CREATE TABLE `v_gallery_controlnet_maps` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `map_id` int(11), `map_name` varchar(100));


DROP VIEW IF EXISTS `v_gallery_generatives`;
CREATE TABLE `v_gallery_generatives` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `generative_id` int(11), `name` varchar(100), `description` text);


DROP VIEW IF EXISTS `v_gallery_locations`;
CREATE TABLE `v_gallery_locations` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `location_id` int(11), `location_name` varchar(100), `location_type` varchar(50));


DROP VIEW IF EXISTS `v_gallery_prompt_matrix_blueprints`;
CREATE TABLE `v_gallery_prompt_matrix_blueprints` (`frame_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `map_run_id` int(11), `entity_id` int(11), `blueprint_name` varchar(100), `blueprint_entity_type` varchar(100), `blueprint_entity_id` int(11), `blueprint_description` text, `blueprint_matrix_id` int(10) unsigned, `blueprint_matrix_additions_id` int(10) unsigned, `blueprint_active_map_run_id` int(11), `blueprint_state_id_active` int(11), `blueprint_regenerate_images` tinyint(1), `blueprint_img2img` tinyint(1), `blueprint_cnmap` tinyint(1));


DROP VIEW IF EXISTS `v_gallery_scene_parts`;
CREATE TABLE `v_gallery_scene_parts` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `style` text, `scene_part_id` int(11), `scene_part_name` varchar(255), `scene_part_description` text, `characters` varchar(500), `animas` varchar(500), `artifacts` varchar(300), `backgrounds` varchar(300), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_gallery_sketches`;
CREATE TABLE `v_gallery_sketches` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `sketch_id` int(11), `name` varchar(100), `description` text, `mood` text);


DROP VIEW IF EXISTS `v_gallery_spawns`;
CREATE TABLE `v_gallery_spawns` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `spawn_id` int(11), `name` varchar(100), `description` text, `type` varchar(50), `type_label` varchar(100), `spawn_type_id` int(11));


DROP VIEW IF EXISTS `v_gallery_spawns_reference`;
CREATE TABLE `v_gallery_spawns_reference` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `spawn_id` int(11), `name` varchar(100), `description` text, `type` varchar(50), `type_label` varchar(100));


DROP VIEW IF EXISTS `v_gallery_spawns_texture`;
CREATE TABLE `v_gallery_spawns_texture` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `spawn_id` int(11), `name` varchar(100), `description` text, `type` varchar(50), `type_label` varchar(100));


DROP VIEW IF EXISTS `v_gallery_vehicles`;
CREATE TABLE `v_gallery_vehicles` (`frame_id` int(11), `entity_id` int(11), `filename` varchar(255), `prompt` text, `style` text, `vehicle_id` int(11), `vehicle_name` varchar(100), `vehicle_type` varchar(50), `vehicle_status` enum('inactive','active','damaged','decommissioned'));


DROP VIEW IF EXISTS `v_map_runs_animas`;
CREATE TABLE `v_map_runs_animas` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_artifacts`;
CREATE TABLE `v_map_runs_artifacts` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_backgrounds`;
CREATE TABLE `v_map_runs_backgrounds` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_characters`;
CREATE TABLE `v_map_runs_characters` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_character_poses`;
CREATE TABLE `v_map_runs_character_poses` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_composites`;
CREATE TABLE `v_map_runs_composites` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_controlnet_maps`;
CREATE TABLE `v_map_runs_controlnet_maps` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_generatives`;
CREATE TABLE `v_map_runs_generatives` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_locations`;
CREATE TABLE `v_map_runs_locations` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_prompt_matrix_blueprints`;
CREATE TABLE `v_map_runs_prompt_matrix_blueprints` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_scene_parts`;
CREATE TABLE `v_map_runs_scene_parts` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_sketches`;
CREATE TABLE `v_map_runs_sketches` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_map_runs_vehicles`;
CREATE TABLE `v_map_runs_vehicles` (`id` int(11), `created_at` datetime, `note` text, `entity_id` int(11), `is_active` int(1));


DROP VIEW IF EXISTS `v_prompts_animas`;
CREATE TABLE `v_prompts_animas` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_artifacts`;
CREATE TABLE `v_prompts_artifacts` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_backgrounds`;
CREATE TABLE `v_prompts_backgrounds` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_characters`;
CREATE TABLE `v_prompts_characters` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_character_poses`;
CREATE TABLE `v_prompts_character_poses` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_composites`;
CREATE TABLE `v_prompts_composites` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_controlnet_maps`;
CREATE TABLE `v_prompts_controlnet_maps` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_generatives`;
CREATE TABLE `v_prompts_generatives` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_locations`;
CREATE TABLE `v_prompts_locations` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_prompt_matrix_blueprints`;
CREATE TABLE `v_prompts_prompt_matrix_blueprints` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_scene_parts`;
CREATE TABLE `v_prompts_scene_parts` (`scene_part_id` int(11), `id` int(11), `scene_id` int(11), `name` varchar(255), `description` text, `characters` varchar(500), `animas` varchar(500), `artifacts` varchar(300), `backgrounds` varchar(300), `prompt` mediumtext, `regenerate_images` tinyint(1));


DROP VIEW IF EXISTS `v_prompts_sketches`;
CREATE TABLE `v_prompts_sketches` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_prompts_vehicles`;
CREATE TABLE `v_prompts_vehicles` (`id` int(11), `regenerate_images` tinyint(1), `prompt` mediumtext);


DROP VIEW IF EXISTS `v_scenes_under_review`;
CREATE TABLE `v_scenes_under_review` (`scene_id` int(11), `scene_title` varchar(255), `scene_part_id` int(11), `stage` enum('draft','review','approved','locked'), `assigned_to` varchar(100), `updated_at` timestamp);


DROP VIEW IF EXISTS `v_scene_part_full`;
CREATE TABLE `v_scene_part_full` (`scene_part_id` int(11), `scene_part_name` varchar(255), `scene_part_description` text, `perspective_angle` varchar(500), `perspective_notes` text, `background_name` varchar(100), `background_description` text, `animas_in_scene` mediumtext, `animas_details` mediumtext);


DROP VIEW IF EXISTS `v_styles_helper`;
CREATE TABLE `v_styles_helper` (`id` int(11), `regenerate_images` int(1), `prompt` mediumtext);


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


DROP TABLE IF EXISTS `v_anima_activity`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_anima_activity` AS select `s`.`id` AS `scene_id`,`s`.`sequence` AS `scene_sequence`,`sp`.`id` AS `scene_part_id`,`sp`.`sequence` AS `part_sequence`,`a`.`id` AS `character_anima_id`,`ch`.`name` AS `character_name`,`a`.`name` AS `anima_name`,`span`.`action_type` AS `action_type`,`span`.`notes` AS `notes` from ((((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_animas` `span` on(`span`.`scene_part_id` = `sp`.`id`)) join `animas` `a` on(`a`.`id` = `span`.`character_anima_id`)) join `characters` `ch` on(`ch`.`id` = `a`.`character_id`)) order by `s`.`sequence`,`sp`.`sequence`,`ch`.`name`,`a`.`name`;

DROP TABLE IF EXISTS `v_artifact_usage`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_artifact_usage` AS select `s`.`id` AS `scene_id`,`s`.`sequence` AS `scene_sequence`,`sp`.`id` AS `scene_part_id`,`sp`.`sequence` AS `part_sequence`,`a`.`id` AS `artifact_id`,`a`.`name` AS `artifact_name`,`a`.`type` AS `artifact_type`,`a`.`status` AS `artifact_status`,`spa`.`notes` AS `notes` from (((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_artifacts` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) join `artifacts` `a` on(`a`.`id` = `spa`.`artifact_id`)) order by `s`.`sequence`,`sp`.`sequence`,`a`.`name`;

DROP TABLE IF EXISTS `v_character_pose_angle_combinations`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_character_pose_angle_combinations` AS select `c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`p`.`id` AS `pose_id`,`p`.`name` AS `pose_name`,`a`.`id` AS `angle_id`,`a`.`name` AS `angle_name`,concat(`c`.`name`,' (',`c`.`description`,') - ',`p`.`name`,' (',`p`.`description`,') - ',`a`.`name`,' (',`a`.`description`,')') AS `description` from ((`characters` `c` join `poses` `p`) join `angles` `a`);

DROP TABLE IF EXISTS `v_dialogue_tts`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dialogue_tts` AS select `s`.`id` AS `scene_id`,`s`.`sequence` AS `scene_sequence`,`s`.`title` AS `scene_title`,`sp`.`id` AS `scene_part_id`,`sp`.`sequence` AS `part_sequence`,`i`.`sequence` AS `line_sequence`,`c`.`name` AS `character_name`,`i`.`emotion` AS `emotion`,`i`.`content` AS `dialogue` from (((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `interactions` `i` on(`i`.`scene_part_id` = `sp`.`id` and `i`.`type` = 'dialogue')) left join `characters` `c` on(`c`.`id` = `i`.`character_id`)) order by `s`.`sequence`,`sp`.`sequence`,`i`.`sequence`;

DROP TABLE IF EXISTS `v_export_ready`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_export_ready` AS select `s`.`id` AS `scene_id`,`s`.`title` AS `scene_title`,`sp`.`id` AS `scene_part_id`,`ef`.`export_type` AS `export_type`,`ef`.`last_exported_at` AS `last_exported_at` from ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `export_flags` `ef` on(`ef`.`scene_part_id` = `sp`.`id`)) where `ef`.`ready_for_export` = 1;

DROP TABLE IF EXISTS `v_gallery_animas`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_animas` AS select `f`.`id` AS `frame_id`,`a`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`a`.`id` AS `anima_id`,`a`.`name` AS `anima_name`,`a`.`traits` AS `traits`,`a`.`abilities` AS `abilities`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`c`.`role` AS `character_role` from ((((`frames` `f` join `frames_2_animas` `m` on(`m`.`from_id` = `f`.`id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) join `characters` `c` on(`c`.`id` = `a`.`character_id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_artifacts`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_artifacts` AS select `f`.`id` AS `frame_id`,`a`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`a`.`id` AS `artifact_id`,`a`.`name` AS `artifact_name`,`a`.`type` AS `artifact_type`,`a`.`status` AS `artifact_status` from (((`frames` `f` join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `a` on(`m`.`to_id` = `a`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_backgrounds`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_backgrounds` AS select `f`.`id` AS `frame_id`,`b`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`b`.`id` AS `background_id`,`b`.`name` AS `background_name`,`b`.`type` AS `background_type`,`l`.`id` AS `location_id`,`l`.`name` AS `location_name` from ((((`frames` `f` join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`m`.`to_id` = `b`.`id`)) left join `locations` `l` on(`b`.`location_id` = `l`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_characters`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_characters` AS select `f`.`id` AS `frame_id`,`f`.`map_run_id` AS `map_run_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`c`.`role` AS `character_role` from (((`frames` `f` join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_character_poses`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_character_poses` AS select `f`.`id` AS `frame_id`,`cp`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`cp`.`description` AS `prompt`,`s`.`name` AS `style`,`cp`.`id` AS `character_pose_id`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`cp`.`pose_id` AS `pose_id`,`p`.`name` AS `pose_name`,`cp`.`angle_id` AS `angle_id`,`a`.`name` AS `angle_name` from ((((((`frames` `f` join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`m`.`to_id` = `cp`.`id`)) join `characters` `c` on(`cp`.`character_id` = `c`.`id`)) join `poses` `p` on(`cp`.`pose_id` = `p`.`id`)) join `angles` `a` on(`cp`.`angle_id` = `a`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 and `f`.`map_run_id` = `cp`.`active_map_run_id` order by `s`.`order`,`f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_composites`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_composites` AS select `f`.`id` AS `frame_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `composite_id`,`c`.`name` AS `composite_name` from (((`frames` `f` join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_controlnet_maps`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_controlnet_maps` AS select `f`.`id` AS `frame_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `map_id`,`c`.`name` AS `map_name` from ((`frames` `f` join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`m`.`to_id` = `c`.`id`)) where `f`.`map_run_id` = `c`.`active_map_run_id` order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_generatives`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_generatives` AS select `f`.`id` AS `frame_id`,`g`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`g`.`id` AS `generative_id`,`g`.`name` AS `name`,`g`.`description` AS `description` from (((`frames` `f` join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`m`.`to_id` = `g`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_locations`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_locations` AS select `f`.`id` AS `frame_id`,`l`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`l`.`id` AS `location_id`,`l`.`name` AS `location_name`,`l`.`type` AS `location_type` from (((`frames` `f` join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`m`.`to_id` = `l`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_prompt_matrix_blueprints`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_prompt_matrix_blueprints` AS select `f`.`id` AS `frame_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`f`.`map_run_id` AS `map_run_id`,`b`.`id` AS `entity_id`,`b`.`name` AS `blueprint_name`,`b`.`entity_type` AS `blueprint_entity_type`,`b`.`entity_id` AS `blueprint_entity_id`,`b`.`description` AS `blueprint_description`,`b`.`matrix_id` AS `blueprint_matrix_id`,`b`.`matrix_additions_id` AS `blueprint_matrix_additions_id`,`b`.`active_map_run_id` AS `blueprint_active_map_run_id`,`b`.`state_id_active` AS `blueprint_state_id_active`,`b`.`regenerate_images` AS `blueprint_regenerate_images`,`b`.`img2img` AS `blueprint_img2img`,`b`.`cnmap` AS `blueprint_cnmap` from (((`frames` `f` join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`m`.`to_id` = `b`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1;

DROP TABLE IF EXISTS `v_gallery_scene_parts`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_scene_parts` AS select `f`.`id` AS `frame_id`,`sp`.`scene_part_id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`style` AS `style`,`sp`.`scene_part_id` AS `scene_part_id`,`sp`.`name` AS `scene_part_name`,`sp`.`description` AS `scene_part_description`,`sp`.`characters` AS `characters`,`sp`.`animas` AS `animas`,`sp`.`artifacts` AS `artifacts`,`sp`.`backgrounds` AS `backgrounds`,`sp`.`prompt` AS `prompt` from (((`frames` `f` join `frames_2_scene_parts` `m` on(`f`.`id` = `m`.`from_id`)) join (select `sp`.`id` AS `scene_part_id`,`sp`.`name` AS `name`,`sp`.`description` AS `description`,`sp`.`regenerate_images` AS `regenerate_images`,`sp`.`active_map_run_id` AS `active_map_run_id`,substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`,substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`,substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`,substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`,concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt` from ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) group by `sp`.`id`) `sp` on(`m`.`to_id` = `sp`.`scene_part_id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 and `f`.`map_run_id` = `sp`.`active_map_run_id` order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_sketches`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_sketches` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `sketch_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`s`.`mood` AS `mood` from (((`frames` `f` join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`m`.`to_id` = `s`.`id`)) join `styles` `st` on(`f`.`style_id` = `st`.`id`)) where `st`.`visible` = 1 order by `st`.`order`,`f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_spawns`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_spawns` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,coalesce(`st`.`code`,`s`.`type`) AS `type`,`st`.`label` AS `type_label`,`st`.`id` AS `spawn_type_id` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) left join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_spawns_reference`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_spawns_reference` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'reference' order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_spawns_texture`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_spawns_texture` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'texture' order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_gallery_vehicles`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_vehicles` AS select `f`.`id` AS `frame_id`,`v`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`v`.`id` AS `vehicle_id`,`v`.`name` AS `vehicle_name`,`v`.`type` AS `vehicle_type`,`v`.`status` AS `vehicle_status` from (((`frames` `f` join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`m`.`to_id` = `v`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

DROP TABLE IF EXISTS `v_map_runs_animas`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_animas` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `a`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_animas` `m` on(`f`.`id` = `m`.`from_id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'animas';

DROP TABLE IF EXISTS `v_map_runs_artifacts`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_artifacts` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `ar`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `ar` on(`ar`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'artifacts';

DROP TABLE IF EXISTS `v_map_runs_backgrounds`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_backgrounds` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `b`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`b`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'backgrounds';

DROP TABLE IF EXISTS `v_map_runs_characters`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_characters` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'characters';

DROP TABLE IF EXISTS `v_map_runs_character_poses`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_character_poses` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `cp`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`cp`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'character_poses';

DROP TABLE IF EXISTS `v_map_runs_composites`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_composites` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'composites';

DROP TABLE IF EXISTS `v_map_runs_controlnet_maps`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_controlnet_maps` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'controlnet_maps';

DROP TABLE IF EXISTS `v_map_runs_generatives`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_generatives` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `g`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`g`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'generatives';

DROP TABLE IF EXISTS `v_map_runs_locations`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_locations` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `l`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`l`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'locations';

DROP TABLE IF EXISTS `v_map_runs_prompt_matrix_blueprints`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_prompt_matrix_blueprints` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `b`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`b`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'prompt_matrix_blueprints';

DROP TABLE IF EXISTS `v_map_runs_scene_parts`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_scene_parts` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`f2sp`.`to_id` AS `entity_id`,case when `mr`.`id` = `sp`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_scene_parts` `f2sp` on(`f2sp`.`from_id` = `f`.`id`)) join `scene_parts` `sp` on(`sp`.`id` = `f2sp`.`to_id`)) where `mr`.`entity_type` = 'scene_parts';

DROP TABLE IF EXISTS `v_map_runs_sketches`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_sketches` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `s`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`s`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'sketches';

DROP TABLE IF EXISTS `v_map_runs_vehicles`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_vehicles` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `v`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`v`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'vehicles';

DROP TABLE IF EXISTS `v_prompts_animas`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_animas` AS select `a`.`id` AS `id`,`a`.`regenerate_images` AS `regenerate_images`,coalesce(`a`.`description`,'') AS `prompt` from `animas` `a`;

DROP TABLE IF EXISTS `v_prompts_artifacts`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_artifacts` AS select `ar`.`id` AS `id`,`ar`.`regenerate_images` AS `regenerate_images`,coalesce(`ar`.`description`,'') AS `prompt` from `artifacts` `ar`;

DROP TABLE IF EXISTS `v_prompts_backgrounds`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_backgrounds` AS select `b`.`id` AS `id`,`b`.`regenerate_images` AS `regenerate_images`,coalesce(`b`.`description`,'') AS `prompt` from `backgrounds` `b`;

DROP TABLE IF EXISTS `v_prompts_characters`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_characters` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,coalesce(`c`.`description`,'') AS `prompt` from `characters` `c`;

DROP TABLE IF EXISTS `v_prompts_character_poses`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_character_poses` AS select `cp`.`id` AS `id`,`cp`.`regenerate_images` AS `regenerate_images`,concat('((',`c`.`name`,': ',`c`.`description`,')) ','(Pose: ',`p`.`description`,') ','(Angle: ',`a`.`description`,')') AS `prompt` from (((`character_poses` `cp` join `characters` `c` on(`cp`.`character_id` = `c`.`id`)) join `poses` `p` on(`cp`.`pose_id` = `p`.`id`)) join `angles` `a` on(`cp`.`angle_id` = `a`.`id`));

DROP TABLE IF EXISTS `v_prompts_composites`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_composites` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,coalesce(`c`.`description`,'') AS `prompt` from `composites` `c`;

DROP TABLE IF EXISTS `v_prompts_controlnet_maps`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_controlnet_maps` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,concat_ws(', ',`c`.`name`,coalesce(`c`.`description`,'')) AS `prompt` from `controlnet_maps` `c`;

DROP TABLE IF EXISTS `v_prompts_generatives`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_generatives` AS select `g`.`id` AS `id`,`g`.`regenerate_images` AS `regenerate_images`,concat_ws(', ',`g`.`name`,coalesce(`g`.`description`,'')) AS `prompt` from `generatives` `g`;

DROP TABLE IF EXISTS `v_prompts_locations`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_locations` AS select `l`.`id` AS `id`,`l`.`regenerate_images` AS `regenerate_images`,coalesce(`l`.`description`,'') AS `prompt` from `locations` `l`;

DROP TABLE IF EXISTS `v_prompts_prompt_matrix_blueprints`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_prompt_matrix_blueprints` AS select `prompt_matrix_blueprints`.`id` AS `id`,`prompt_matrix_blueprints`.`regenerate_images` AS `regenerate_images`,coalesce(`prompt_matrix_blueprints`.`description`,'') AS `prompt` from `prompt_matrix_blueprints`;

DROP TABLE IF EXISTS `v_prompts_scene_parts`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_scene_parts` AS select `sp`.`id` AS `scene_part_id`,`sp`.`id` AS `id`,`sp`.`scene_id` AS `scene_id`,`sp`.`name` AS `name`,`sp`.`description` AS `description`,substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`,substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`,substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`,substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`,concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt`,`sp`.`regenerate_images` AS `regenerate_images` from ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) group by `sp`.`id`;

DROP TABLE IF EXISTS `v_prompts_sketches`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_sketches` AS select `s`.`id` AS `id`,`s`.`regenerate_images` AS `regenerate_images`,coalesce(`s`.`description`,'') AS `prompt` from `sketches` `s`;

DROP TABLE IF EXISTS `v_prompts_vehicles`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_vehicles` AS select `v`.`id` AS `id`,`v`.`regenerate_images` AS `regenerate_images`,coalesce(`v`.`description`,'') AS `prompt` from `vehicles` `v`;

DROP TABLE IF EXISTS `v_scenes_under_review`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_scenes_under_review` AS select `s`.`id` AS `scene_id`,`s`.`title` AS `scene_title`,`sp`.`id` AS `scene_part_id`,`ps`.`stage` AS `stage`,`ps`.`assigned_to` AS `assigned_to`,`ps`.`updated_at` AS `updated_at` from ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `production_status` `ps` on(`ps`.`scene_part_id` = `sp`.`id`)) where `ps`.`stage` = 'review';

DROP TABLE IF EXISTS `v_scene_part_full`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_scene_part_full` AS select `sp`.`id` AS `scene_part_id`,`sp`.`name` AS `scene_part_name`,`sp`.`description` AS `scene_part_description`,`p`.`angle` AS `perspective_angle`,`p`.`description` AS `perspective_notes`,`b`.`name` AS `background_name`,`b`.`description` AS `background_description`,group_concat(distinct `a`.`name` separator ', ') AS `animas_in_scene`,group_concat(distinct concat(`a`.`name`,': ',`a`.`traits`,'; ',`a`.`abilities`) separator ' | ') AS `animas_details` from (((((`scene_parts` `sp` join `perspectives` `p` on(`p`.`scene_part_id` = `sp`.`id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `p`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) group by `sp`.`id`,`p`.`id`,`b`.`id` order by `sp`.`sequence`,`p`.`id`;

DROP TABLE IF EXISTS `v_styles_helper`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_styles_helper` AS select `s`.`id` AS `id`,0 AS `regenerate_images`,concat('(',coalesce(`s`.`description`,''),')','(',(select `prompt_globals`.`description` from `prompt_globals` where `prompt_globals`.`id` = 1),')') AS `prompt` from `styles` `s` where `s`.`active` = 1 order by `s`.`order`;

-- 2025-10-17 12:40:53 UTC
