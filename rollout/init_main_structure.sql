-- ============================================================
-- Consolidated Structure Rollout
-- Source DB    : starlight_guardians_nu
-- Consolidated : 2026-10-30 10:34:26
-- Supersedes   : init_main_structure.sql + its incremental appendix
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;


-- Multi-Table Export
-- Database: starlight_guardians_nu
-- Generated: 2026-10-30 09:44:08
-- Tables with structure: 480
-- Tables with data: 0

-- Export order: Tables first, then Views

-- ========================================================
-- TABLES
-- ========================================================

-- --------------------------------------------------------
-- Table: `agent_chat_messages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `agent_chat_messages`;

CREATE TABLE `agent_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(50) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `operations_log` mediumtext DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agent_msg_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `agent_chat_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `agent_chat_sessions`;

CREATE TABLE `agent_chat_sessions` (
  `id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `lore` longtext DEFAULT NULL,
  `model` varchar(100) DEFAULT 'openai',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_agent_sess_user_mod` (`user_id`,`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `agent_session_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `agent_session_items`;

CREATE TABLE `agent_session_items` (
  `session_id` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`,`item_id`),
  KEY `idx_agent_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `ag_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ag_categories`;

CREATE TABLE `ag_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_doc_id` (`doc_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `ag_nodes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ag_nodes`;

CREATE TABLE `ag_nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(1024) NOT NULL,
  `node_type` varchar(100) DEFAULT 'note',
  `content` longtext DEFAULT NULL,
  `description` text DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_node_type` (`node_type`),
  KEY `idx_status` (`status`),
  KEY `idx_doc_id` (`doc_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `ag_node_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ag_node_items`;

CREATE TABLE `ag_node_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `item_type` varchar(100) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_label` varchar(1024) DEFAULT NULL,
  `relationship` varchar(1024) DEFAULT NULL,
  `note` longtext DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `doc_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_node` (`node_id`),
  KEY `idx_item` (`item_type`,`item_id`),
  KEY `idx_source_doc` (`doc_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `aim_chat_messages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `aim_chat_messages`;

CREATE TABLE `aim_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(50) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `operations_log` mediumtext DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aim_msg_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `aim_chat_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `aim_chat_sessions`;

CREATE TABLE `aim_chat_sessions` (
  `id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lore` longtext DEFAULT NULL,
  `kg_selected_nodes` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aim_sess_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `aim_clusters`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `aim_clusters`;

CREATE TABLE `aim_clusters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `session_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aim_clusters_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `aim_ideas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `aim_ideas`;

CREATE TABLE `aim_ideas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cluster_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('unassigned','yes','no') DEFAULT 'unassigned',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `session_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aim_ideas_cluster` (`cluster_id`),
  KEY `idx_aim_ideas_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `aim_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `aim_settings`;

CREATE TABLE `aim_settings` (
  `user_id` int(11) NOT NULL,
  `lore` longtext DEFAULT NULL,
  `model` varchar(100) DEFAULT 'openai-fast',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animas`;

CREATE TABLE `animas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
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
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_character_animas_character` (`character_id`),
  KEY `idx_character_animas_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animatics`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animatics`;

CREATE TABLE `animatics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_videos` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate videos',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_img2img_frame_id` (`img2img_frame_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animatic_audios`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animatic_audios`;

CREATE TABLE `animatic_audios` (
  `animatic_id` int(11) NOT NULL,
  `audio_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`animatic_id`,`audio_id`),
  KEY `idx_audio_id` (`audio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animatic_cnmap_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animatic_cnmap_frames`;

CREATE TABLE `animatic_cnmap_frames` (
  `animatic_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`animatic_id`,`frame_id`),
  KEY `idx_frame_id` (`frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animatic_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animatic_frames`;

CREATE TABLE `animatic_frames` (
  `animatic_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`animatic_id`,`frame_id`),
  KEY `idx_frame_id` (`frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animatic_meshes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animatic_meshes`;

CREATE TABLE `animatic_meshes` (
  `animatic_id` int(11) NOT NULL,
  `mesh_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`animatic_id`,`mesh_id`),
  KEY `idx_mesh_id` (`mesh_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animatic_videos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animatic_videos`;

CREATE TABLE `animatic_videos` (
  `animatic_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`animatic_id`,`video_id`),
  KEY `idx_video_id` (`video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `animation_mouthshapes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `animation_mouthshapes`;

CREATE TABLE `animation_mouthshapes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_action_effects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_action_effects`;

CREATE TABLE `anivoc_action_effects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_backgrounds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_backgrounds`;

CREATE TABLE `anivoc_backgrounds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_categories`;

CREATE TABLE `anivoc_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `item_count` int(10) unsigned DEFAULT 0,
  `is_complete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_table_name` (`table_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_character_states`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_character_states`;

CREATE TABLE `anivoc_character_states` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_chibi_modes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_chibi_modes`;

CREATE TABLE `anivoc_chibi_modes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_color_coding`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_color_coding`;

CREATE TABLE `anivoc_color_coding` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_duo_compositions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_duo_compositions`;

CREATE TABLE `anivoc_duo_compositions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_expressions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_expressions`;

CREATE TABLE `anivoc_expressions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `use_case` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_hand_gestures`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_hand_gestures`;

CREATE TABLE `anivoc_hand_gestures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_lighting`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_lighting`;

CREATE TABLE `anivoc_lighting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_motion_impact`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_motion_impact`;

CREATE TABLE `anivoc_motion_impact` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_panel_frame`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_panel_frame`;

CREATE TABLE `anivoc_panel_frame` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_scale_perspective`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_scale_perspective`;

CREATE TABLE `anivoc_scale_perspective` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_scene_functions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_scene_functions`;

CREATE TABLE `anivoc_scene_functions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `anime_notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_shot_pacing`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_shot_pacing`;

CREATE TABLE `anivoc_shot_pacing` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_symbolic_objects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_symbolic_objects`;

CREATE TABLE `anivoc_symbolic_objects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_text_graphics`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_text_graphics`;

CREATE TABLE `anivoc_text_graphics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `anivoc_transitions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `anivoc_transitions`;

CREATE TABLE `anivoc_transitions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('inactive','active','corrupted','purified') NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artifacts_name` (`name`),
  KEY `idx_artifacts_type` (`type`),
  KEY `idx_artifacts_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios`;

CREATE TABLE `audios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `rvc_model_name` varchar(100) DEFAULT NULL,
  `pitch_shift` int(11) DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_audio_ambiences`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_audio_ambiences`;

CREATE TABLE `audios_2_audio_ambiences` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_audio_cues`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_audio_cues`;

CREATE TABLE `audios_2_audio_cues` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_audio_dialogue_lines`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_audio_dialogue_lines`;

CREATE TABLE `audios_2_audio_dialogue_lines` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_audio_foleys`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_audio_foleys`;

CREATE TABLE `audios_2_audio_foleys` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_audio_fxsounds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_audio_fxsounds`;

CREATE TABLE `audios_2_audio_fxsounds` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_audio_themes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_audio_themes`;

CREATE TABLE `audios_2_audio_themes` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_daw_projects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_daw_projects`;

CREATE TABLE `audios_2_daw_projects` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_documentations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_documentations`;

CREATE TABLE `audios_2_documentations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `from_id` (`from_id`),
  KEY `to_id` (`to_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audios_2_editorial_shots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audios_2_editorial_shots`;

CREATE TABLE `audios_2_editorial_shots` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_ambiences`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_ambiences`;

CREATE TABLE `audio_ambiences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Environmental sound description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_counter`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_counter`;

CREATE TABLE `audio_counter` (
  `id` int(11) NOT NULL DEFAULT 1,
  `next_audio` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_cues`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_cues`;

CREATE TABLE `audio_cues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Score cue description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_dialogue_lines`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_dialogue_lines`;

CREATE TABLE `audio_dialogue_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `character_id` int(11) DEFAULT NULL,
  `audio_voice_identity_id` int(11) DEFAULT NULL,
  `pitch_shift` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(255) DEFAULT NULL,
  `active_audio_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_foleys`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_foleys`;

CREATE TABLE `audio_foleys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Foley action description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_fxsounds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_fxsounds`;

CREATE TABLE `audio_fxsounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Designed or synthetic sound effect',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_themes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_themes`;

CREATE TABLE `audio_themes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Narrative or musical theme description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_voice_identity`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_voice_identity`;

CREATE TABLE `audio_voice_identity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `model_path` varchar(255) DEFAULT NULL COMMENT 'RVC model or voice reference',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `audio_voice_identity_xmpl`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audio_voice_identity_xmpl`;

CREATE TABLE `audio_voice_identity_xmpl` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voice_identity_id` int(11) NOT NULL COMMENT 'FK → audio_voice_identity.id',
  `dialogue_line_id` int(11) NOT NULL COMMENT 'FK → audio_dialogue_lines.id (the xmpl sentinel line)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voice_identity` (`voice_identity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `backgrounds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `backgrounds`;

CREATE TABLE `backgrounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(50) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backgrounds_name` (`name`),
  KEY `idx_backgrounds_type` (`type`),
  KEY `idx_backgrounds_location` (`location_id`),
  CONSTRAINT `fk_backgrounds_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `backups_media`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `backups_media`;

CREATE TABLE `backups_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','done','failed') NOT NULL DEFAULT 'pending',
  `frames_tar` varchar(255) DEFAULT NULL,
  `frames_sha256` char(64) DEFAULT NULL,
  `frames_bytes` bigint(20) DEFAULT NULL,
  `frames_max_id` int(11) DEFAULT 0,
  `audios_tar` varchar(255) DEFAULT NULL,
  `audios_sha256` char(64) DEFAULT NULL,
  `audios_bytes` bigint(20) DEFAULT NULL,
  `audios_max_id` int(11) DEFAULT 0,
  `videos_tar` varchar(255) DEFAULT NULL,
  `videos_sha256` char(64) DEFAULT NULL,
  `videos_bytes` bigint(20) DEFAULT NULL,
  `videos_max_id` int(11) DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `backup_destinations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `backup_destinations`;

CREATE TABLE `backup_destinations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name` varchar(100) NOT NULL COMMENT 'Human label, e.g. "Tablet SCP"',
  `slug` varchar(60) NOT NULL COMMENT 'Machine key, e.g. tablet_scp',
  `type` enum('scp','local') NOT NULL DEFAULT 'scp',
  `host_mode` enum('static','ap0_scan') NOT NULL DEFAULT 'ap0_scan' COMMENT 'static = fixed IP, ap0_scan = hotspot auto-detect',
  `host` varchar(255) DEFAULT NULL COMMENT 'IP or hostname when host_mode=static',
  `port` smallint(5) unsigned NOT NULL DEFAULT 8022,
  `remote_base` varchar(500) NOT NULL DEFAULT 'sage_backup' COMMENT 'Base folder on remote device',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `backup_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `backup_jobs`;

CREATE TABLE `backup_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name` varchar(100) NOT NULL COMMENT 'Human label, e.g. "Media Incremental"',
  `slug` varchar(60) NOT NULL COMMENT 'Machine key, e.g. media_incremental',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `job_type` enum('media_tar','mysqldump','zip_paths') NOT NULL,
  `destination_id` int(11) NOT NULL,
  `options_json` text NOT NULL DEFAULT '{}',
  `remote_subfolder` varchar(255) DEFAULT NULL COMMENT 'e.g. "media" → full path: remote_base/media/',
  `schedule_hint` varchar(100) DEFAULT NULL COMMENT 'e.g. "daily 02:00"',
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_active` (`active`),
  KEY `idx_dest` (`destination_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `backup_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `backup_runs`;

CREATE TABLE `backup_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `job_id` int(11) NOT NULL,
  `job_slug` varchar(60) NOT NULL COMMENT 'Snapshot of slug at run time',
  `job_type` varchar(30) NOT NULL,
  `status` enum('pending','running','done','failed','partial') NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `elapsed_sec` int(11) DEFAULT NULL,
  `artifacts_json` text DEFAULT NULL COMMENT 'JSON array describing each file transferred',
  `watermark_json` text DEFAULT NULL COMMENT 'e.g. {"frames_max_id":39337,"audios_max_id":1328}',
  `files_total` int(11) DEFAULT 0,
  `files_ok` int(11) DEFAULT 0,
  `bytes_total` bigint(20) DEFAULT 0,
  `message` text DEFAULT NULL,
  `log_text` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_id` (`job_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `bang_arrangements`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `bang_arrangements`;

CREATE TABLE `bang_arrangements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `canvas_id` int(11) NOT NULL COMMENT 'FK → bang_canvases.id',
  `composite_id` int(11) NOT NULL COMMENT 'Denormalized for fast lookup',
  `name` varchar(255) NOT NULL DEFAULT 'Draft',
  `scene_json` longtext DEFAULT NULL COMMENT 'Full Konva scene JSON: panels, images, balloons, sfx, captions',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = current live arrangement for this canvas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bang_arr_canvas` (`canvas_id`),
  KEY `idx_bang_arr_composite` (`composite_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! — saved Konva scene arrangements (versioned)';

-- --------------------------------------------------------
-- Table: `bang_canvases`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `bang_canvases`;

CREATE TABLE `bang_canvases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `composite_id` int(11) NOT NULL COMMENT 'FK → composites.id (image layer container)',
  `name` varchar(255) NOT NULL DEFAULT 'Untitled Panel Strip',
  `canvas_width` int(11) NOT NULL DEFAULT 800 COMMENT 'Canvas width in pixels (design target)',
  `canvas_height` int(11) NOT NULL DEFAULT 3200 COMMENT 'Canvas height in pixels (variable strip height)',
  `bg_color` varchar(20) NOT NULL DEFAULT '#000000' COMMENT 'Canvas background color hex',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bang_canvas_composite` (`composite_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! comic panel composer — canvas metadata per composite';

-- --------------------------------------------------------
-- Table: `bang_elements`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `bang_elements`;

CREATE TABLE `bang_elements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arrangement_id` int(11) NOT NULL COMMENT 'FK → bang_arrangements.id',
  `canvas_id` int(11) NOT NULL,
  `element_uid` varchar(64) NOT NULL COMMENT 'Client-side UUID for this element',
  `element_type` enum('image','balloon','sfx','caption','shape') NOT NULL DEFAULT 'image',
  `frame_id` int(11) DEFAULT NULL COMMENT 'FK → frames.id (for image elements)',
  `text_content` text DEFAULT NULL COMMENT 'Text content (balloons, sfx, captions)',
  `x` float NOT NULL DEFAULT 0,
  `y` float NOT NULL DEFAULT 0,
  `z_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bang_elem_uid` (`arrangement_id`,`element_uid`),
  KEY `idx_bang_elem_canvas` (`canvas_id`),
  KEY `idx_bang_elem_frame` (`frame_id`),
  KEY `idx_bang_elem_type` (`element_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! — flat element index (mirrors scene_json for lookups)';

-- --------------------------------------------------------
-- Table: `bang_exports`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `bang_exports`;

CREATE TABLE `bang_exports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `canvas_id` int(11) NOT NULL COMMENT 'FK → bang_canvases.id',
  `arrangement_id` int(11) NOT NULL COMMENT 'FK → bang_arrangements.id',
  `frame_id` int(11) DEFAULT NULL COMMENT 'FK → frames.id (the rendered output frame)',
  `composite_id` int(11) NOT NULL,
  `export_width` int(11) NOT NULL DEFAULT 800,
  `export_height` int(11) NOT NULL DEFAULT 3200,
  `status` enum('pending','processing','done','error') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bang_exp_canvas` (`canvas_id`),
  KEY `idx_bang_exp_frame` (`frame_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! — PNG export job tracker';

-- --------------------------------------------------------
-- Table: `bang_fonts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `bang_fonts`;

CREATE TABLE `bang_fonts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Display name shown in UI',
  `font_key` varchar(100) NOT NULL COMMENT 'CSS font-family / Pillow lookup key',
  `file_path` varchar(512) DEFAULT NULL COMMENT 'Relative path to TTF file (for PyAPI Pillow)',
  `google_url` varchar(512) DEFAULT NULL COMMENT 'Google Fonts URL for browser loading',
  `category` enum('dialogue','sfx','caption','display','mono') NOT NULL DEFAULT 'dialogue',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bang_font_key` (`font_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! — registered fonts for the lettering system';

-- --------------------------------------------------------
-- Table: `beap_beats`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `beap_beats`;

CREATE TABLE `beap_beats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `beat_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order (0-based)',
  `beat_text` text NOT NULL COMMENT 'The narrative beat extracted by AI',
  `shot_intent` text DEFAULT NULL COMMENT 'Shot intent extracted in same AI pass',
  `panel_data` longtext DEFAULT NULL COMMENT 'JSON — panelisation result for this beat',
  `status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT 'pending | panelised | exported',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_beap_beats_session` (`session_id`),
  CONSTRAINT `fk_beap_beats_session` FOREIGN KEY (`session_id`) REFERENCES `beap_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Individual beats/steps within a BEAP session';

-- --------------------------------------------------------
-- Table: `beap_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `beap_sessions`;

CREATE TABLE `beap_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sketch_id` int(11) NOT NULL COMMENT 'Source sketches.id',
  `sketch_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Snapshot of name at creation time',
  `sketch_desc` text DEFAULT NULL COMMENT 'Snapshot of description at creation time',
  `narseq_id` int(11) DEFAULT NULL COMMENT 'Output narrative_sequences.id (created after panelisation)',
  `depth` varchar(20) NOT NULL DEFAULT 'normal' COMMENT 'short | normal | epic',
  `status` varchar(30) NOT NULL DEFAULT 'beats_pending' COMMENT 'beats_pending | beats_done | panels_done | exported',
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_beap_sketch` (`sketch_id`),
  KEY `idx_beap_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BEAP workflow sessions — one per scene-to-panel run';

-- --------------------------------------------------------
-- Table: `boards`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `boards`;

CREATE TABLE `boards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','archived','production') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `boards_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `boards_categories`;

CREATE TABLE `boards_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `boards_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `boards_items`;

CREATE TABLE `boards_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `board_id` int(11) NOT NULL,
  `item_type` varchar(50) NOT NULL COMMENT 'e.g. map_run, frame, character, md_doc',
  `item_id` int(11) NOT NULL,
  `note` text DEFAULT NULL COMMENT 'Production notes specific to this assignment',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_board` (`board_id`),
  KEY `idx_item_lookup` (`item_type`,`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `camera_angles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `camera_angles`;

CREATE TABLE `camera_angles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `camera_perspectives`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `camera_perspectives`;

CREATE TABLE `camera_perspectives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `desc_abbr` varchar(255) NOT NULL DEFAULT '',
  `motivations` text DEFAULT NULL,
  `hooks_arc_potential` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_characters_name` (`name`),
  KEY `idx_characters_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `character_anima_poses`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `character_anima_poses`;

CREATE TABLE `character_anima_poses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text NOT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `character_id` int(11) NOT NULL,
  `pose_id` int(11) NOT NULL COMMENT 'References poses_anima.id',
  `angle_id` int(11) NOT NULL,
  `perspective_id` int(11) NOT NULL DEFAULT 1,
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`),
  KEY `pose_id` (`pose_id`),
  KEY `angle_id` (`angle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `character_expressions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `character_expressions`;

CREATE TABLE `character_expressions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text NOT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `character_id` int(11) NOT NULL,
  `expression_id` int(11) NOT NULL,
  `angle_id` int(11) NOT NULL,
  `perspective_id` int(11) NOT NULL,
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_char_expr_angle_persp` (`character_id`,`expression_id`,`angle_id`,`perspective_id`),
  KEY `character_id` (`character_id`),
  KEY `expression_id` (`expression_id`),
  KEY `angle_id` (`angle_id`),
  KEY `idx_perspective_id` (`perspective_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `character_poses`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `character_poses`;

CREATE TABLE `character_poses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text NOT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `character_id` int(11) NOT NULL,
  `pose_id` int(11) NOT NULL,
  `angle_id` int(11) NOT NULL,
  `perspective_id` int(11) NOT NULL DEFAULT 1,
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`),
  KEY `pose_id` (`pose_id`),
  KEY `angle_id` (`angle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `chatty_chat_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chatty_chat_sessions`;

CREATE TABLE `chatty_chat_sessions` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_chatty_chats_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `chatty_conversation`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chatty_conversation`;

CREATE TABLE `chatty_conversation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) DEFAULT 'default',
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chatty_conversation_user` (`user_id`),
  KEY `idx_chatty_conv_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `chatty_session_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chatty_session_settings`;

CREATE TABLE `chatty_session_settings` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `model` varchar(100) DEFAULT 'openai',
  `system_prompt` longtext DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `idx_chatty_session_settings_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `chatty_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chatty_settings`;

CREATE TABLE `chatty_settings` (
  `user_id` int(11) NOT NULL,
  `model` varchar(100) DEFAULT 'openai',
  `system_prompt` longtext DEFAULT NULL,
  `mute_notifications` tinyint(1) DEFAULT 0,
  `last_read_msg_id` int(11) DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table: `chroma_collections`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chroma_collections`;

CREATE TABLE `chroma_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Exact Chroma collection name',
  `type` enum('text','image') NOT NULL COMMENT 'Type of embeddings stored',
  `description` text DEFAULT NULL COMMENT 'Human-readable description',
  `dimension` int(11) DEFAULT NULL COMMENT 'Embedding dimension (e.g., 384, 512)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `cinemagics`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cinemagics`;

CREATE TABLE `cinemagics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT 'Untitled Cinemagic',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `cinemagics_2_sequences`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cinemagics_2_sequences`;

CREATE TABLE `cinemagics_2_sequences` (
  `cinemagic_id` int(11) NOT NULL,
  `sequence_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `chapter_label` varchar(100) DEFAULT NULL COMMENT 'Optional override label e.g. "Episode 1" or "Chapter: The Awakening"',
  `cover_image_url` varchar(512) DEFAULT NULL,
  `seo_keywords` text DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_links`)),
  PRIMARY KEY (`cinemagic_id`,`sequence_id`),
  KEY `idx_c2s_cinemagic` (`cinemagic_id`),
  KEY `idx_c2s_sequence` (`sequence_id`),
  KEY `idx_c2s_sort` (`cinemagic_id`,`sort_order`),
  CONSTRAINT `fk_c2s_cinemagic` FOREIGN KEY (`cinemagic_id`) REFERENCES `cinemagics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_c2s_sequence` FOREIGN KEY (`sequence_id`) REFERENCES `narrative_sequences` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `cinemagic_hub_posts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cinemagic_hub_posts`;

CREATE TABLE `cinemagic_hub_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `post_type` enum('cinematic_story','scrollmagic_gallery','narrative_gallery','anime_gallery','spatial_viewer') NOT NULL DEFAULT 'cinematic_story',
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `sequence_id` int(11) DEFAULT NULL COMMENT 'FK → narrative_sequences.id (optional)',
  `cinemagic_id` int(11) DEFAULT NULL COMMENT 'FK → cinemagics.id (optional)',
  `series_label` varchar(255) DEFAULT NULL COMMENT 'Top-level series name e.g. "The Anima Chronicles"',
  `season_label` varchar(255) DEFAULT NULL COMMENT 'Season or arc label e.g. "Season 1"',
  `episode_label` varchar(255) DEFAULT NULL COMMENT 'Episode label e.g. "Episode 3: The Awakening"',
  `tree_sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Sort within its series/season group',
  `preview_image_url` varchar(512) DEFAULT NULL,
  `content` text DEFAULT NULL COMMENT 'Synopsis or description shown on detail page',
  `media_items` longtext DEFAULT NULL CHECK (json_valid(`media_items`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `asset_url_prefix` varchar(512) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cm_slug` (`slug`),
  KEY `idx_cm_status` (`status`),
  KEY `idx_cm_seq` (`sequence_id`),
  KEY `idx_cm_cinemagic` (`cinemagic_id`),
  KEY `idx_cm_series` (`series_label`,`season_label`,`tree_sort_order`),
  KEY `idx_cm_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Cinemagic Hub — cinematic content organised by series/season/episode';

-- --------------------------------------------------------
-- Table: `cinemagic_series`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cinemagic_series`;

CREATE TABLE `cinemagic_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `asset_url_prefix` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cover_image_url` varchar(512) DEFAULT NULL,
  `template` varchar(50) DEFAULT 'default',
  `supported_languages` varchar(255) DEFAULT 'en',
  `landing_page_script` varchar(255) DEFAULT NULL,
  `seo_keywords` text DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `has_seasons` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = series uses Season grouping layer above Cinemagics',
  `pdf_full_upright` tinyint(1) NOT NULL DEFAULT 0,
  `pdf_full_landscape` tinyint(1) NOT NULL DEFAULT 0,
  `pdf_disable_texts` tinyint(1) NOT NULL DEFAULT 0,
  `pdf_disable_fuki` tinyint(1) NOT NULL DEFAULT 0,
  `pdf_layout` varchar(50) NOT NULL DEFAULT 'sgtac',
  `pdf_page_size` varchar(50) NOT NULL DEFAULT 'A4',
  `web_disable_texts` tinyint(1) NOT NULL DEFAULT 0,
  `web_disable_fuki` tinyint(1) NOT NULL DEFAULT 0,
  `is_adult_18_plus` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `cinemagic_series_2_cinemagics`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cinemagic_series_2_cinemagics`;

CREATE TABLE `cinemagic_series_2_cinemagics` (
  `series_id` int(11) NOT NULL,
  `cinemagic_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `season_id` int(11) DEFAULT NULL COMMENT 'FK → cinemagic_series_seasons.id  (NULL when has_seasons=0)',
  `cover_image_url` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`series_id`,`cinemagic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `cinemagic_series_seasons`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cinemagic_series_seasons`;

CREATE TABLE `cinemagic_series_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL COMMENT 'FK → cinemagic_series.id',
  `title` varchar(255) NOT NULL DEFAULT 'Season 1',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_css_series` (`series_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Optional season grouping containers for a cinemagic_series';

-- --------------------------------------------------------
-- Table: `clipboard_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `clipboard_items`;

CREATE TABLE `clipboard_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `label` varchar(120) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `clipboard_visibility`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `clipboard_visibility`;

CREATE TABLE `clipboard_visibility` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clipboard_item_id` int(10) unsigned NOT NULL,
  `view_area` varchar(80) NOT NULL DEFAULT 'global',
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_area` (`clipboard_item_id`,`view_area`),
  CONSTRAINT `fk_cv_item` FOREIGN KEY (`clipboard_item_id`) REFERENCES `clipboard_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `color_grade_presets`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `color_grade_presets`;

CREATE TABLE `color_grade_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `settings_json` longtext NOT NULL COMMENT 'Grade settings — same schema as color_grade_profiles.settings_json',
  `thumbnail_frame_id` int(11) DEFAULT NULL COMMENT 'Optional: frame used as preview thumbnail',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cgpreset_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `color_grade_profiles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `color_grade_profiles`;

CREATE TABLE `color_grade_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `frame_id` int(11) NOT NULL COMMENT 'Source frame this grade was applied to',
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `preset_id` int(11) DEFAULT NULL COMMENT 'FK to color_grade_presets if saved from a preset',
  `settings_json` longtext NOT NULL COMMENT 'Full grade settings object',
  `derived_frame_id` int(11) DEFAULT NULL COMMENT 'The new frame produced by Pillow render',
  `map_run_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cgp_frame` (`frame_id`),
  KEY `idx_cgp_derived` (`derived_frame_id`),
  KEY `idx_cgp_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `composites`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `composites`;

CREATE TABLE `composites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `composite_audios`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `composite_audios`;

CREATE TABLE `composite_audios` (
  `composite_id` int(11) NOT NULL,
  `audio_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`composite_id`,`audio_id`),
  KEY `idx_audio_id` (`audio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `composite_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `composite_frames`;

CREATE TABLE `composite_frames` (
  `composite_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`composite_id`,`frame_id`),
  KEY `idx_frame_id` (`frame_id`)
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `content_hub_posts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `content_hub_posts`;

CREATE TABLE `content_hub_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `post_type` enum('image_grid','image_swiper','video_playlist','youtube_playlist','url_reference','story','reel','thread','scrollmagic_gallery','cinematic_story','anime_gallery','narrative_gallery','spatial_viewer','magazine_highlight') NOT NULL DEFAULT 'image_grid',
  `status` enum('draft','scheduled','published','archived') NOT NULL DEFAULT 'draft',
  `platform` varchar(64) DEFAULT NULL COMMENT 'instagram|tiktok|youtube|twitter|facebook',
  `platforms_json` text DEFAULT NULL COMMENT 'JSON array of platform names',
  `preview_image_url` varchar(512) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `hashtags` text DEFAULT NULL,
  `media_items` longtext DEFAULT NULL CHECK (json_valid(`media_items`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `external_post_id` varchar(255) DEFAULT NULL COMMENT 'Platform post ID after publishing',
  `asset_url_prefix` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_platform` (`platform`),
  KEY `idx_scheduled` (`scheduled_at`),
  KEY `idx_sort` (`sort_order`),
  KEY `idx_status_platform` (`status`,`platform`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Content Hub — Social media post planning & scheduling';

-- --------------------------------------------------------
-- Table: `continuity_entities`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `continuity_entities`;

CREATE TABLE `continuity_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(50) NOT NULL,
  `canonical_name` varchar(100) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT 'character',
  `description` text NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag_name` (`tag_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `continuity_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `continuity_jobs`;

CREATE TABLE `continuity_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sketch_id` int(11) NOT NULL COMMENT 'FK → sketches.id',
  `character_id` int(11) NOT NULL COMMENT 'FK → characters.id',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Character order within a sketch (for deterministic prompt assembly)',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | running | done | failed | skipped',
  `cont_gen_id` int(11) DEFAULT NULL COMMENT 'Override continuity generator_config.id (NULL = use CLI default)',
  `result_text` longtext DEFAULT NULL COMMENT 'Final scene_prompt returned by the AI',
  `error_msg` text DEFAULT NULL COMMENT 'Last error message if status = failed',
  `attempts` tinyint(3) NOT NULL DEFAULT 0 COMMENT 'Number of AI call attempts made',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cj_sketch_char` (`sketch_id`,`character_id`),
  KEY `idx_cj_sketch` (`sketch_id`),
  KEY `idx_cj_character` (`character_id`),
  KEY `idx_cj_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Defines which characters must be applied via continuity to which sketches.';

-- --------------------------------------------------------
-- Table: `controlnet_maps`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `controlnet_maps`;

CREATE TABLE `controlnet_maps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `cv_batch_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cv_batch_sessions`;

CREATE TABLE `cv_batch_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `config_data` longtext DEFAULT NULL,
  `state_data` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `cv_batch_session_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cv_batch_session_logs`;

CREATE TABLE `cv_batch_session_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `frame_id` int(11) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `prompt` text DEFAULT NULL,
  `raw_response` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_frame_id` (`frame_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `daw_projects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `daw_projects`;

CREATE TABLE `daw_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `daw_project_files`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `daw_project_files`;

CREATE TABLE `daw_project_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `state_data` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `fk_daw_project` FOREIGN KEY (`project_id`) REFERENCES `daw_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `daw_shot_saves`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `daw_shot_saves`;

CREATE TABLE `daw_shot_saves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shot_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_data` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shot_id` (`shot_id`),
  CONSTRAINT `fk_daw_shot_save_shot` FOREIGN KEY (`shot_id`) REFERENCES `editorial_shots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `derivates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `derivates`;

CREATE TABLE `derivates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_videos` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `vid2vid` tinyint(1) NOT NULL DEFAULT 0,
  `vid2vid_video_id` int(11) DEFAULT NULL,
  `vid2vid_video_filename` varchar(100) DEFAULT NULL,
  `vid2vid_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `dimensionals`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `dimensionals`;

CREATE TABLE `dimensionals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_meshes` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `dimensionals_2_meshes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `dimensionals_2_meshes`;

CREATE TABLE `dimensionals_2_meshes` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `documentations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `documentations`;

CREATE TABLE `documentations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `desc_short` text DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `duration` int(11) DEFAULT 0,
  `type` varchar(50) DEFAULT 'md',
  `category_id` int(11) DEFAULT NULL,
  `target_collection` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_created` (`created_at`),
  KEY `idx_target_collection` (`target_collection`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `documentation_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `documentation_categories`;

CREATE TABLE `documentation_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_episodes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_episodes`;

CREATE TABLE `editorial_episodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season_id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_editorial_season` (`season_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_scenes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_scenes`;

CREATE TABLE `editorial_scenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `int_ext` enum('INT','EXT','INT/EXT') DEFAULT 'INT',
  `time_of_day` enum('DAY','NIGHT','DAWN','DUSK','SPACE') DEFAULT 'DAY',
  `sort_order` int(11) DEFAULT 0,
  `directory` varchar(255) DEFAULT NULL COMMENT 'Relative path like /editorial/scene_001',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_directory` (`directory`),
  KEY `idx_editorial_sequence` (`sequence_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_seasons`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_seasons`;

CREATE TABLE `editorial_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_editorial_series` (`series_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_sequences`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_sequences`;

CREATE TABLE `editorial_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `episode_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_editorial_episode` (`episode_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_series`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_series`;

CREATE TABLE `editorial_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_shots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_shots`;

CREATE TABLE `editorial_shots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `shot_type` varchar(50) DEFAULT NULL,
  `duration_est` float DEFAULT 2,
  `video_id` int(11) DEFAULT NULL COMMENT 'Reference to videos table',
  `filename` varchar(255) DEFAULT NULL COMMENT 'Full relative path to the physical file in scene dir',
  `is_copied` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if physically copied to scene dir',
  `original_filename` varchar(255) DEFAULT NULL COMMENT 'Original filename before copy',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `audio_notes` text DEFAULT NULL COMMENT 'Global audio notes for this shot',
  PRIMARY KEY (`id`),
  KEY `idx_editorial_scene` (`scene_id`),
  KEY `idx_video` (`video_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_shots_2_audio_ambiences`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_shots_2_audio_ambiences`;

CREATE TABLE `editorial_shots_2_audio_ambiences` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_ambiences.id',
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_shots_2_audio_cues`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_shots_2_audio_cues`;

CREATE TABLE `editorial_shots_2_audio_cues` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_cues.id',
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_shots_2_audio_foleys`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_shots_2_audio_foleys`;

CREATE TABLE `editorial_shots_2_audio_foleys` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_foleys.id',
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_shots_2_audio_fxsounds`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_shots_2_audio_fxsounds`;

CREATE TABLE `editorial_shots_2_audio_fxsounds` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_fxsounds.id',
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_shots_2_audio_themes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_shots_2_audio_themes`;

CREATE TABLE `editorial_shots_2_audio_themes` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_themes.id',
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `editorial_shot_dialogues`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `editorial_shot_dialogues`;

CREATE TABLE `editorial_shot_dialogues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shot_id` int(11) NOT NULL,
  `dialogue_line_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shot` (`shot_id`),
  KEY `idx_dialogue` (`dialogue_line_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table: `factions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `factions`;

CREATE TABLE `factions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `desc_abbr` varchar(255) NOT NULL DEFAULT '',
  `origin_source` varchar(255) DEFAULT NULL COMMENT 'e.g., fuzz, kg, ag, sketches',
  `origin_id` int(11) DEFAULT NULL COMMENT 'ID of the source entity',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate frames',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_factions_name` (`name`),
  KEY `idx_origin` (`origin_source`,`origin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table: `forge_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `forge_jobs`;

CREATE TABLE `forge_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_type` enum('kg_sketch','lore_sketch','autopilot','md_curator_extract','md_curator_aggregate','narrative_sequence_compose','sketch_tag_extract','github_sync','translation_compose','overlay_compose') NOT NULL,
  `label` varchar(255) NOT NULL DEFAULT '',
  `status` enum('pending','processing','done','failed','cancelled') NOT NULL DEFAULT 'pending',
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 50,
  `payload` longtext NOT NULL DEFAULT json_object(),
  `result` longtext DEFAULT NULL,
  `error_msg` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_type_status` (`job_type`,`status`),
  KEY `idx_status_priority` (`status`,`priority`,`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='CLI Forge Hub — queued jobs for all five CLI pipeline pairs';

-- --------------------------------------------------------
-- Table: `forge_tool_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `forge_tool_settings`;

CREATE TABLE `forge_tool_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `settings_json` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames`;

CREATE TABLE `frames` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `map_run_id` int(11) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `depth_map_filename` varchar(255) DEFAULT NULL,
  `prompt` text NOT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
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
  `rating` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_map_run_id` (`map_run_id`),
  KEY `idx_img2img` (`img2img_frame_id`),
  KEY `idx_cnmap` (`cnmap_frame_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table: `frames_2_animatics`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_animatics`;

CREATE TABLE `frames_2_animatics` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_frame_animatic_from` (`from_id`),
  KEY `idx_frame_animatic_to` (`to_id`)
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table: `frames_2_character_anima_poses`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_character_anima_poses`;

CREATE TABLE `frames_2_character_anima_poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `from_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_character_expressions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_character_expressions`;

CREATE TABLE `frames_2_character_expressions` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `from_id` (`to_id`)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_dimensionals`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_dimensionals`;

CREATE TABLE `frames_2_dimensionals` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `dimensional_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frames_2_factions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frames_2_factions`;

CREATE TABLE `frames_2_factions` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `faction_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table: `frame_enhancements`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frame_enhancements`;

CREATE TABLE `frame_enhancements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'raw or random prompt text',
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img` tinyint(1) NOT NULL DEFAULT 1,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `active_map_run_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `frame_enhancement_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `frame_enhancement_frames`;

CREATE TABLE `frame_enhancement_frames` (
  `frame_enhancement_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`frame_enhancement_id`,`frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `fuki_texts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fuki_texts`;

CREATE TABLE `fuki_texts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` int(11) NOT NULL,
  `sketch_id` int(11) NOT NULL,
  `element_uid` varchar(64) NOT NULL COMMENT 'Unique identifier linking translations of the same text block',
  `language_code` varchar(2) NOT NULL DEFAULT 'en',
  `text_content` text DEFAULT NULL,
  `x` float NOT NULL DEFAULT 0,
  `y` float NOT NULL DEFAULT 0,
  `width` float NOT NULL DEFAULT 200,
  `rotation` float NOT NULL DEFAULT 0,
  `font_family` varchar(100) NOT NULL DEFAULT 'Bangers',
  `font_size` float NOT NULL DEFAULT 24,
  `fill_color` varchar(20) NOT NULL DEFAULT '#111111',
  `text_align` varchar(10) NOT NULL DEFAULT 'center',
  `is_bold` tinyint(1) NOT NULL DEFAULT 0,
  `is_italic` tinyint(1) NOT NULL DEFAULT 0,
  `is_underline` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fuki_uid_lang` (`sequence_id`,`sketch_id`,`element_uid`,`language_code`),
  KEY `idx_fuki_seq` (`sequence_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Fuki — Multilingual text overlays for narrative sequences';

-- --------------------------------------------------------
-- Table: `fuzz_candidates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fuzz_candidates`;

CREATE TABLE `fuzz_candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(512) NOT NULL COMMENT 'Canonical concept label',
  `concept_type` varchar(100) DEFAULT NULL COMMENT 'character, location, faction, artifact, event, concept, relationship, other',
  `status` enum('extracted','grouped','reviewed','promoted','canonized','rejected','deferred') NOT NULL DEFAULT 'extracted',
  `confidence` tinyint(3) unsigned NOT NULL DEFAULT 50 COMMENT '0-100',
  `notes` longtext DEFAULT NULL,
  `kg_node_id` int(11) DEFAULT NULL COMMENT 'Resolved canonical kg_nodes.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`concept_type`),
  KEY `idx_kg_node` (`kg_node_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `fuzz_candidate_aliases`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fuzz_candidate_aliases`;

CREATE TABLE `fuzz_candidate_aliases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `alias` varchar(512) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidate` (`candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `fuzz_links`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fuzz_links`;

CREATE TABLE `fuzz_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL COMMENT 'Source candidate',
  `target_candidate_id` int(11) NOT NULL COMMENT 'Target candidate',
  `relationship_type` varchar(100) NOT NULL DEFAULT 'may_refer_to',
  `confidence` tinyint(3) unsigned NOT NULL DEFAULT 50,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidate` (`candidate_id`),
  KEY `idx_target` (`target_candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `fuzz_mentions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fuzz_mentions`;

CREATE TABLE `fuzz_mentions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `source_table` varchar(100) NOT NULL COMMENT 'Origin table name',
  `source_row_id` int(11) DEFAULT NULL,
  `source_field` varchar(100) DEFAULT NULL,
  `mention_type` varchar(100) DEFAULT NULL COMMENT 'sketch_name, sketch_desc, analysis_entity, analysis_thematic, kg_node, lore_history, ingredient, manual',
  `extracted_text` text NOT NULL,
  `normalized_text` varchar(512) DEFAULT NULL,
  `context_snippet` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidate` (`candidate_id`),
  KEY `idx_source` (`source_table`,`source_row_id`),
  KEY `idx_mention_type` (`mention_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `fuzz_queue`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fuzz_queue`;

CREATE TABLE `fuzz_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_table` varchar(100) NOT NULL,
  `source_row_id` int(11) DEFAULT NULL,
  `source_field` varchar(100) DEFAULT NULL,
  `mention_type` varchar(100) DEFAULT NULL,
  `extracted_text` text NOT NULL,
  `normalized_text` varchar(512) DEFAULT NULL,
  `context_snippet` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_norm` (`normalized_text`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `fuzz_resolutions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fuzz_resolutions`;

CREATE TABLE `fuzz_resolutions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `kg_node_id` int(11) DEFAULT NULL,
  `outcome` varchar(100) NOT NULL DEFAULT 'promoted',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidate` (`candidate_id`),
  KEY `idx_kg_node` (`kg_node_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `fuzz_reviews`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `fuzz_reviews`;

CREATE TABLE `fuzz_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `decision` varchar(100) NOT NULL COMMENT 'confirmed, rejected, deferred, split, promoted, linked, unresolved',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidate` (`candidate_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `generatives`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generatives`;

CREATE TABLE `generatives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'raw or random prompt text',
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `instructions` longtext NOT NULL CHECK (json_valid(`instructions`)),
  `parameters` longtext NOT NULL CHECK (json_valid(`parameters`)),
  `output_schema` longtext NOT NULL CHECK (json_valid(`output_schema`)),
  `examples` longtext DEFAULT NULL CHECK (json_valid(`examples`)),
  `oracle_config` longtext DEFAULT NULL COMMENT 'Configuration for the Bloom Oracle creative seeding' CHECK (json_valid(`oracle_config`)),
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Generator configurations. user_id=NULL indicates public/system generators accessible to all users.';

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Configurable display areas for generators';

-- --------------------------------------------------------
-- Table: `generator_config_history`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generator_config_history`;

CREATE TABLE `generator_config_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `generator_config_id` int(11) NOT NULL,
  `config_hash` varchar(32) NOT NULL COMMENT 'MD5 of the snapshot data for quick lookup',
  `snapshot_data` longtext NOT NULL CHECK (json_valid(`snapshot_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `generator_config_id` (`generator_config_id`),
  KEY `config_hash` (`config_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `gs_assign_config`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `gs_assign_config`;

CREATE TABLE `gs_assign_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(120) NOT NULL DEFAULT '' COMMENT 'Human-readable label for UI',
  `entity_type` enum('character_poses','character_expressions','character_anima_poses','locations') NOT NULL,
  `source_id` int(11) NOT NULL COMMENT 'character_id (or location_id etc.) in the source entity table',
  `node_id` int(11) NOT NULL COMMENT 'video_tree_nodes.id to assign matching videos into',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_source_node` (`entity_type`,`source_id`,`node_id`),
  KEY `idx_node` (`node_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Maps entity source_id (e.g. character_id) to a video_tree_nodes node for batch assignment';

-- --------------------------------------------------------
-- Table: `ideas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ideas`;

CREATE TABLE `ideas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cluster_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('unassigned','yes','no') DEFAULT 'unassigned',
  `source_array` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cluster_id` (`cluster_id`),
  CONSTRAINT `1` FOREIGN KEY (`cluster_id`) REFERENCES `idea_clusters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `idea_clusters`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `idea_clusters`;

CREATE TABLE `idea_clusters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `interaction_group` (`interaction_group`),
  KEY `category` (`category`),
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `json_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `json_categories`;

CREATE TABLE `json_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `json_files`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `json_files`;

CREATE TABLE `json_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL CHECK (json_valid(`content`)),
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_categories`;

CREATE TABLE `kg_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_edge_offline_state`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_edge_offline_state`;

CREATE TABLE `kg_edge_offline_state` (
  `id` tinyint(4) NOT NULL DEFAULT 1,
  `is_offline` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_edge_proposals`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_edge_proposals`;

CREATE TABLE `kg_edge_proposals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `focal_node_id` int(11) NOT NULL,
  `target_node_id` int(11) NOT NULL,
  `target_name` varchar(255) NOT NULL,
  `relationship` varchar(255) DEFAULT NULL,
  `rationale` text DEFAULT NULL,
  `status` enum('pending','promoted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_edge_proposal` (`focal_node_id`,`target_node_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_edge_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_edge_runs`;

CREATE TABLE `kg_edge_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_uuid` varchar(32) NOT NULL,
  `focal_node_id` int(11) NOT NULL,
  `focal_node_name` varchar(255) NOT NULL,
  `focal_node_type` varchar(100) NOT NULL DEFAULT 'note',
  `focal_text_chars` int(11) NOT NULL DEFAULT 0,
  `focal_snippet` text DEFAULT NULL,
  `status` enum('queued','running','awaiting_offline','completed','error') NOT NULL DEFAULT 'queued',
  `step` tinyint(4) NOT NULL DEFAULT 0,
  `step_label` varchar(100) NOT NULL DEFAULT 'Queued',
  `candidate_count` int(11) NOT NULL DEFAULT 0,
  `ai_model` varchar(255) DEFAULT NULL,
  `ai_prompt_chars` int(11) NOT NULL DEFAULT 0,
  `ai_response_excerpt` text DEFAULT NULL,
  `ai_error` text DEFAULT NULL,
  `result_inserted` int(11) NOT NULL DEFAULT 0,
  `result_skipped` int(11) NOT NULL DEFAULT 0,
  `message` text DEFAULT NULL,
  `offline_requested_at` timestamp NULL DEFAULT NULL,
  `offline_ingested_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_uuid` (`run_uuid`),
  KEY `idx_status` (`status`),
  KEY `idx_focal_node` (`focal_node_id`),
  KEY `idx_step` (`step`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_edge_run_ai_edges`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_edge_run_ai_edges`;

CREATE TABLE `kg_edge_run_ai_edges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `target_node_id` int(11) NOT NULL,
  `target_name` varchar(255) NOT NULL,
  `relationship_label` varchar(255) NOT NULL,
  `rationale` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_run_id` (`run_id`),
  KEY `idx_target_node` (`target_node_id`),
  CONSTRAINT `fk_kg_edge_run_ai_edges_run` FOREIGN KEY (`run_id`) REFERENCES `kg_edge_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_edge_run_candidates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_edge_run_candidates`;

CREATE TABLE `kg_edge_run_candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `node_id` int(11) NOT NULL,
  `target_name` varchar(255) NOT NULL,
  `node_type` varchar(100) NOT NULL DEFAULT 'note',
  `category_name` varchar(255) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `content_status` varchar(20) DEFAULT NULL,
  `content_chars` int(11) NOT NULL DEFAULT 0,
  `score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `excerpt` text DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_node` (`run_id`,`node_id`),
  KEY `idx_run_id` (`run_id`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_node_id` (`node_id`),
  CONSTRAINT `fk_kg_edge_run_candidates_run` FOREIGN KEY (`run_id`) REFERENCES `kg_edge_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_edge_run_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_edge_run_logs`;

CREATE TABLE `kg_edge_run_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `step_key` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `context_text` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_run_id` (`run_id`),
  KEY `idx_step_key` (`step_key`),
  CONSTRAINT `fk_kg_edge_run_logs_run` FOREIGN KEY (`run_id`) REFERENCES `kg_edge_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_filter_state`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_filter_state`;

CREATE TABLE `kg_filter_state` (
  `id` tinyint(4) NOT NULL DEFAULT 1,
  `live_ids` longtext DEFAULT NULL COMMENT 'JSON array of visible kg_nodes.id, NULL = all visible' CHECK (json_valid(`live_ids`)),
  `staging_ids` longtext DEFAULT NULL COMMENT 'JSON array of visible staging node ids, NULL = all visible' CHECK (json_valid(`staging_ids`)),
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Server-side mirror of the kg_filter.php graph-filter selection';

-- --------------------------------------------------------
-- Table: `kg_id_reservations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_id_reservations`;

CREATE TABLE `kg_id_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_name` varchar(255) NOT NULL,
  `target_table` varchar(50) NOT NULL DEFAULT 'kg_nodes',
  `start_id` int(11) NOT NULL,
  `end_id` int(11) NOT NULL,
  `current_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_nodes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_nodes`;

CREATE TABLE `kg_nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `node_type` varchar(100) DEFAULT 'note' COMMENT 'e.g. relationship, character, event, location, concept',
  `content` longtext DEFAULT NULL COMMENT 'Markdown content',
  `continuity_data` longtext DEFAULT NULL COMMENT 'JSON storage for current state and temporal milestones',
  `description` text DEFAULT NULL COMMENT 'Short summary',
  `keywords` text DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_node_type` (`node_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_node_coordinates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_node_coordinates`;

CREATE TABLE `kg_node_coordinates` (
  `node_id` int(11) NOT NULL,
  `x` double NOT NULL,
  `y` double NOT NULL,
  PRIMARY KEY (`node_id`),
  CONSTRAINT `fk_kg_node_coord` FOREIGN KEY (`node_id`) REFERENCES `kg_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_node_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_node_items`;

CREATE TABLE `kg_node_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `item_type` varchar(100) NOT NULL COMMENT 'e.g. character, location, md_doc, episode, kg_node',
  `item_id` int(11) DEFAULT NULL,
  `item_label` varchar(255) DEFAULT NULL COMMENT 'Display label or name snapshot',
  `relationship` varchar(255) DEFAULT NULL COMMENT 'e.g. protagonist, antagonist, woke, bonded_to',
  `note` longtext DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_node` (`node_id`),
  KEY `idx_item` (`item_type`,`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_staging_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_staging_categories`;

CREATE TABLE `kg_staging_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_staging_nodes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_staging_nodes`;

CREATE TABLE `kg_staging_nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `node_type` varchar(100) DEFAULT 'note' COMMENT 'e.g. relationship, character, event, location, concept',
  `content` longtext DEFAULT NULL COMMENT 'Markdown content',
  `description` text DEFAULT NULL COMMENT 'Short summary',
  `keywords` text DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_node_type` (`node_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `kg_staging_node_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `kg_staging_node_items`;

CREATE TABLE `kg_staging_node_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `item_type` varchar(100) NOT NULL COMMENT 'e.g. character, location, md_doc, episode, kg_node',
  `item_id` int(11) DEFAULT NULL,
  `item_label` varchar(255) DEFAULT NULL COMMENT 'Display label or name snapshot',
  `relationship` varchar(255) DEFAULT NULL COMMENT 'e.g. protagonist, antagonist, woke, bonded_to',
  `note` longtext DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_node` (`node_id`),
  KEY `idx_item` (`item_type`,`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(50) DEFAULT NULL,
  `coordinates` varchar(100) DEFAULT NULL,
  `origin_source` varchar(255) DEFAULT NULL COMMENT 'e.g., fuzz, kg, ag, sketches',
  `origin_id` int(11) DEFAULT NULL COMMENT 'ID of the source entity',
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locations_name` (`name`),
  KEY `idx_locations_type` (`type`),
  KEY `idx_origin` (`origin_source`,`origin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `logs`;

CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` varchar(10) NOT NULL,
  `message` longtext NOT NULL CHECK (json_valid(`message`)),
  `log_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `lore_entities`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `lore_entities`;

CREATE TABLE `lore_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_code` varchar(100) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL COMMENT 'Location, Character, Prop, etc',
  `raw_content` text NOT NULL COMMENT 'The original extracted text from the MD',
  `source_file` varchar(255) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0 COMMENT '1 if converted to showcase',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ref` (`ref_code`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `magazine_pdf_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `magazine_pdf_jobs`;

CREATE TABLE `magazine_pdf_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL COMMENT 'FK → cinemagic_series.id',
  `sequence_id` int(11) NOT NULL COMMENT 'FK → narrative_sequences.id (the episode/issue)',
  `languages` varchar(64) NOT NULL DEFAULT 'en' COMMENT 'Comma-separated language codes requested',
  `status` enum('pending','processing','done','error') NOT NULL DEFAULT 'pending',
  `result_zip` varchar(512) DEFAULT NULL COMMENT 'Absolute path to the produced ZIP file',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mpj_status` (`status`),
  KEY `idx_mpj_series` (`series_id`),
  KEY `idx_mpj_sequence` (`sequence_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Async PDF export jobs — one row per issue+language-set request';

-- --------------------------------------------------------
-- Table: `mail_hub_events`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mail_hub_events`;

CREATE TABLE `mail_hub_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `queue_id` bigint(20) DEFAULT NULL COMMENT 'FK → mail_hub_queue.id (or archive)',
  `newsletter_id` int(11) NOT NULL,
  `subscriber_id` int(11) NOT NULL,
  `event_type` enum('queued','attempt','sent','failed','opened','clicked','bounced','complained','unsubscribed') NOT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `provider_msg_id` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Extra info: click URL, bounce type, etc.' CHECK (json_valid(`metadata`)),
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_newsletter_id` (`newsletter_id`),
  KEY `idx_subscriber_id` (`subscriber_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_occurred_at` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — delivery and engagement event log';

-- --------------------------------------------------------
-- Table: `mail_hub_lists`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mail_hub_lists`;

CREATE TABLE `mail_hub_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = used when no list is specified',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — subscriber lists / audiences';

-- --------------------------------------------------------
-- Table: `mail_hub_newsletters`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mail_hub_newsletters`;

CREATE TABLE `mail_hub_newsletters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Internal title for identification',
  `subject` varchar(500) NOT NULL COMMENT 'Email subject line',
  `preview_text` varchar(255) DEFAULT NULL COMMENT 'Preview/preheader text shown in inbox',
  `body_html` longtext DEFAULT NULL COMMENT 'Full HTML body of the newsletter',
  `body_text` longtext DEFAULT NULL COMMENT 'Plain-text fallback',
  `status` enum('draft','scheduled','sending','sent','cancelled','failed') NOT NULL DEFAULT 'draft',
  `from_name` varchar(255) DEFAULT NULL COMMENT 'Override sender name; NULL = use provider default',
  `from_email` varchar(255) DEFAULT NULL COMMENT 'Override sender address; NULL = use provider default',
  `reply_to` varchar(255) DEFAULT NULL,
  `list_id` int(11) DEFAULT NULL COMMENT 'FK → mail_hub_lists.id; NULL = all active subscribers',
  `provider_id` int(11) DEFAULT NULL COMMENT 'FK → mail_hub_providers.id; NULL = use default',
  `template_id` int(11) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `total_recipients` int(11) NOT NULL DEFAULT 0,
  `total_sent` int(11) NOT NULL DEFAULT 0,
  `total_failed` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_at` (`scheduled_at`),
  KEY `idx_list_id` (`list_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — newsletter campaigns';

-- --------------------------------------------------------
-- Table: `mail_hub_providers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mail_hub_providers`;

CREATE TABLE `mail_hub_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Human label, e.g. "Brevo Free", "My Postfix"',
  `driver` varchar(64) NOT NULL COMMENT 'Class key: brevo | smtp | mailchimp',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Provider-specific config JSON (api_key, host, port, etc.)' CHECK (json_valid(`config`)),
  `daily_limit` int(11) DEFAULT NULL COMMENT 'Max emails per day; NULL = unlimited',
  `sent_today` int(11) NOT NULL DEFAULT 0,
  `last_reset` date DEFAULT NULL COMMENT 'Date when sent_today was last zeroed',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_is_default` (`is_default`),
  KEY `idx_driver` (`driver`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — mail delivery providers';

-- --------------------------------------------------------
-- Table: `mail_hub_queue`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mail_hub_queue`;

CREATE TABLE `mail_hub_queue` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `newsletter_id` int(11) NOT NULL,
  `subscriber_id` int(11) NOT NULL,
  `status` enum('pending','processing','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `priority` int(11) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `provider_id` int(11) DEFAULT NULL COMMENT 'FK → mail_hub_providers.id',
  `provider_msg_id` varchar(255) DEFAULT NULL COMMENT 'Message-ID returned by provider on success',
  `error_msg` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL COMMENT 'Earliest time to attempt delivery',
  `started_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status_priority` (`status`,`priority`),
  KEY `idx_newsletter_id` (`newsletter_id`),
  KEY `idx_subscriber_id` (`subscriber_id`),
  KEY `idx_scheduled_at` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — per-recipient send queue';

-- --------------------------------------------------------
-- Table: `mail_hub_queue_archive`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mail_hub_queue_archive`;

CREATE TABLE `mail_hub_queue_archive` (
  `id` bigint(20) NOT NULL COMMENT 'Preserves original ID from mail_hub_queue',
  `newsletter_id` int(11) NOT NULL,
  `subscriber_id` int(11) NOT NULL,
  `status` enum('sent','failed','skipped','cancelled') NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `provider_id` int(11) DEFAULT NULL,
  `provider_msg_id` varchar(255) DEFAULT NULL,
  `error_msg` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `archived_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_newsletter_id` (`newsletter_id`),
  KEY `idx_status` (`status`),
  KEY `idx_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — archived send queue rows';

-- --------------------------------------------------------
-- Table: `mail_hub_templates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mail_hub_templates`;

CREATE TABLE `mail_hub_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `body_html` longtext DEFAULT NULL,
  `body_text` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `mail_hub_unsubscribe_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `mail_hub_unsubscribe_tokens`;

CREATE TABLE `mail_hub_unsubscribe_tokens` (
  `token` char(64) NOT NULL,
  `subscriber_id` int(11) NOT NULL,
  `newsletter_id` int(11) DEFAULT NULL COMMENT 'If set, tracks which send triggered the unsub',
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token`),
  KEY `idx_subscriber_id` (`subscriber_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — one-click unsubscribe tokens';

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `map_run_queue`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `map_run_queue`;

CREATE TABLE `map_run_queue` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `map_run_id` int(11) NOT NULL COMMENT 'Context: Groups items belonging to the same map run',
  `entity_type` varchar(100) NOT NULL COMMENT 'The source semantic entity (e.g., sketches, animatics)',
  `entity_id` int(11) NOT NULL COMMENT 'The ID of the source entity',
  `asset_type` varchar(100) NOT NULL COMMENT 'The intended physical asset (e.g., frames, videos)',
  `asset_id` int(11) DEFAULT NULL COMMENT 'Filled ONLY after successful generation',
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `priority` int(11) NOT NULL DEFAULT 0 COMMENT 'Higher numbers process first',
  `attempts` int(11) NOT NULL DEFAULT 0 COMMENT 'Tracks retries if generation fails',
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `api_provider_config` longtext DEFAULT NULL CHECK (json_valid(`api_provider_config`)),
  `error_msg` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_priority` (`status`,`priority`),
  KEY `idx_map_run_id` (`map_run_id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_asset` (`asset_type`,`asset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `map_run_queue_archive`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `map_run_queue_archive`;

CREATE TABLE `map_run_queue_archive` (
  `id` bigint(20) NOT NULL COMMENT 'Keeps original ID from map_run_queue, NOT auto_increment',
  `map_run_id` int(11) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `asset_type` varchar(100) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `api_provider_config` longtext DEFAULT NULL CHECK (json_valid(`api_provider_config`)),
  `error_msg` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `archived_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_map_run_id` (`map_run_id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_asset` (`asset_type`,`asset_id`),
  KEY `idx_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `md_doc_analysis`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `md_doc_analysis`;

CREATE TABLE `md_doc_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `summary` longtext DEFAULT NULL,
  `entities` longtext DEFAULT NULL CHECK (json_valid(`entities`)),
  `lore_points` longtext DEFAULT NULL CHECK (json_valid(`lore_points`)),
  `production_assessment` longtext DEFAULT NULL CHECK (json_valid(`production_assessment`)),
  `thematics` longtext DEFAULT NULL CHECK (json_valid(`thematics`)),
  `narrative_utility` float DEFAULT 0,
  `showrunner_analysis` longtext DEFAULT NULL CHECK (json_valid(`showrunner_analysis`)),
  `analyzed_at` timestamp NULL DEFAULT current_timestamp(),
  `generator_config_id` int(11) DEFAULT NULL,
  `series_bible` longtext DEFAULT NULL,
  `target_collection` varchar(100) DEFAULT 'sage_lore_entities_draft',
  `is_locked` tinyint(1) DEFAULT 0 COMMENT 'If 1, the aggregator will skip this document to protect manual edits.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_doc` (`doc_id`),
  KEY `idx_utility` (`narrative_utility`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `md_doc_chunks`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `md_doc_chunks`;

CREATE TABLE `md_doc_chunks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `chunk_index` int(11) NOT NULL,
  `lore_raw` longtext DEFAULT NULL,
  `show_raw` longtext DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_doc_chunk` (`doc_id`,`chunk_index`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `merm_beats`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `merm_beats`;

CREATE TABLE `merm_beats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` int(11) NOT NULL COMMENT 'references merm_threads.id — no FK',
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `content` longtext DEFAULT NULL COMMENT 'markdown beat writeup, mirrors kg_nodes.content',
  `beat_type` varchar(50) DEFAULT 'event' COMMENT 'event, decision, reveal, death, encounter, milestone, sync_point',
  `rhythm` varchar(50) DEFAULT NULL,
  `story_time_dt` datetime DEFAULT NULL,
  `seq_order` int(11) NOT NULL DEFAULT 0 COMMENT 'ordering within thread_id, the swimlane x-position',
  `status` enum('draft','planned','locked','archived') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_thread` (`thread_id`),
  KEY `idx_seq` (`thread_id`,`seq_order`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `merm_beat_links`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `merm_beat_links`;

CREATE TABLE `merm_beat_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_beat_id` int(11) NOT NULL COMMENT 'references merm_beats.id — no FK',
  `to_beat_id` int(11) NOT NULL COMMENT 'references merm_beats.id — no FK',
  `relationship` varchar(100) DEFAULT 'leads_to' COMMENT 'leads_to, concurrent_with, causes, references, contradicts, sync_point',
  `label` varchar(255) DEFAULT NULL COMMENT 'optional edge label shown on the mermaid arrow',
  `pending_resolution` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_from` (`from_beat_id`),
  KEY `idx_to` (`to_beat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `merm_beat_participants`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `merm_beat_participants`;

CREATE TABLE `merm_beat_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `beat_id` int(11) NOT NULL COMMENT 'references merm_beats.id — no FK',
  `kg_node_id` int(11) NOT NULL COMMENT 'references kg_nodes.id — no FK, deliberately loose',
  `role` varchar(100) DEFAULT NULL COMMENT 'e.g. protagonist, victim, witness, location, faction_involved',
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_beat` (`beat_id`),
  KEY `idx_kg_node` (`kg_node_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `merm_beat_threads`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `merm_beat_threads`;

CREATE TABLE `merm_beat_threads` (
  `beat_id` int(11) NOT NULL,
  `thread_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`beat_id`,`thread_id`),
  KEY `idx_thread` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `merm_boards`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `merm_boards`;

CREATE TABLE `merm_boards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `thread_ids` text DEFAULT NULL COMMENT 'JSON array of merm_threads.id included in this board',
  `layout` varchar(20) DEFAULT 'LR' COMMENT 'swimlane-beta direction: LR, TB, RL, BT',
  `filter_status` varchar(50) DEFAULT NULL COMMENT 'optional beat status filter, e.g. locked',
  `notes` text DEFAULT NULL COMMENT 'used as accDescr when rendering',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `merm_threads`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `merm_threads`;

CREATE TABLE `merm_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `series_key` varchar(100) DEFAULT NULL COMMENT 'free-text series tag, e.g. fame_killer, pirate_wannabe, red_latitude',
  `season_key` varchar(100) DEFAULT NULL COMMENT 'free-text season tag',
  `episode_key` varchar(100) DEFAULT NULL COMMENT 'free-text episode tag',
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL COMMENT 'optional hex for lane styling via mermaid classDef',
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_series` (`series_key`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `meshes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `meshes`;

CREATE TABLE `meshes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `meta_sketches`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `meta_sketches`;

CREATE TABLE `meta_sketches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sketch_id` int(11) NOT NULL,
  `desc_gen_config_id` int(11) DEFAULT NULL COMMENT 'Original Config ID for reference',
  `desc_gen_history_id` int(11) DEFAULT NULL COMMENT 'Specific revision used for Description',
  `name_gen_config_id` int(11) DEFAULT NULL COMMENT 'Original Config ID for reference',
  `name_gen_history_id` int(11) DEFAULT NULL COMMENT 'Specific revision used for Name',
  `sketch_template_id` int(11) DEFAULT NULL,
  `interaction_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sketch_id` (`sketch_id`),
  KEY `desc_gen_history_id` (`desc_gen_history_id`),
  KEY `sketch_template_id` (`sketch_template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `montages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `montages`;

CREATE TABLE `montages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_videos` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate videos',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `montage_videos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `montage_videos`;

CREATE TABLE `montage_videos` (
  `montage_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`montage_id`,`video_id`),
  KEY `idx_video_id` (`video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `motion_camera_presets`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `motion_camera_presets`;

CREATE TABLE `motion_camera_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `config` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `motion_layers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `motion_layers`;

CREATE TABLE `motion_layers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `motion_setup_id` int(11) NOT NULL,
  `frame_id` int(11) DEFAULT NULL,
  `video_id` int(11) DEFAULT NULL,
  `mesh_id` int(11) DEFAULT NULL,
  `role` enum('background','plane','sprite','model3d') NOT NULL DEFAULT 'plane',
  `z_index` int(11) DEFAULT 0,
  `layer_config` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_setup` (`motion_setup_id`),
  KEY `idx_frame` (`frame_id`),
  KEY `idx_video` (`video_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `motion_render_queue`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `motion_render_queue`;

CREATE TABLE `motion_render_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `animatic_id` int(11) NOT NULL,
  `motion_setup_id` int(11) NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `flight_data_json` longtext NOT NULL,
  `result_video_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `motion_setups`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `motion_setups`;

CREATE TABLE `motion_setups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `animatic_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Default Scenario',
  `is_active` tinyint(1) DEFAULT 0,
  `environment_config` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_animatic` (`animatic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `motion_takes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `motion_takes`;

CREATE TABLE `motion_takes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `animatic_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Untitled Take',
  `telemetry_data` longtext NOT NULL,
  `duration` float DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_animatic` (`animatic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `multiplane_arrangements`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `multiplane_arrangements`;

CREATE TABLE `multiplane_arrangements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `composite_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Untitled Arrangement',
  `description` text DEFAULT NULL,
  `layer_config` longtext DEFAULT NULL COMMENT 'JSON storage of x,y,scale,rotation,zIndex per frame',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `composite_id` (`composite_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `multiplane_layers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `multiplane_layers`;

CREATE TABLE `multiplane_layers` (
  `composite_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `z_index` int(11) NOT NULL DEFAULT 0 COMMENT '0=Back, 100=Front. Defines render order',
  `speed` float NOT NULL DEFAULT 0.5 COMMENT '0.0=Static BG, 1.0=Moves with Cam',
  `distance` float NOT NULL DEFAULT 10 COMMENT 'Distance from camera in meters. 10m is reference point.',
  `real_height` float DEFAULT NULL COMMENT 'Real world height of the object in meters',
  PRIMARY KEY (`composite_id`,`frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `multiplane_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `multiplane_settings`;

CREATE TABLE `multiplane_settings` (
  `composite_id` int(11) NOT NULL,
  `frames` int(11) NOT NULL DEFAULT 60,
  `fps` int(11) NOT NULL DEFAULT 30,
  `move_x` int(11) NOT NULL DEFAULT 100 COMMENT 'Total pixels camera moves X',
  `move_y` int(11) NOT NULL DEFAULT 0 COMMENT 'Total pixels camera moves Y',
  `zoom_start` float NOT NULL DEFAULT 1,
  `zoom_end` float NOT NULL DEFAULT 1.05,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `focal_distance` float NOT NULL DEFAULT 10 COMMENT 'The distance from camera where parallax speed is 1.0',
  `scale_reference` float NOT NULL DEFAULT 10 COMMENT 'Distance where visual scale is 1.0',
  `frustum_height` float NOT NULL DEFAULT 10 COMMENT 'Height of the visible view (in meters) at the Focal Distance',
  PRIMARY KEY (`composite_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `multivid_arrangements`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `multivid_arrangements`;

CREATE TABLE `multivid_arrangements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `animatic_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Untitled Arrangement',
  `description` text DEFAULT NULL,
  `layer_config` longtext DEFAULT NULL COMMENT 'JSON: per-layer x,y,scaleX,scaleY,rotation,zIndex,speed,opacity',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_animatic` (`animatic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `multivid_layers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `multivid_layers`;

CREATE TABLE `multivid_layers` (
  `animatic_id` int(11) NOT NULL,
  `asset_type` enum('frame','video') NOT NULL DEFAULT 'frame' COMMENT 'frame=animatic_frames, video=animatic_videos',
  `asset_id` int(11) NOT NULL COMMENT 'frame_id or video_id depending on asset_type',
  `z_index` int(11) NOT NULL DEFAULT 0 COMMENT '0=Back, 100=Front',
  `speed` float NOT NULL DEFAULT 0.5 COMMENT '0.0=static BG, 1.0=moves with cam',
  `distance` float NOT NULL DEFAULT 10 COMMENT 'Distance from camera in meters',
  `real_height` float DEFAULT NULL COMMENT 'Real world height in meters (for scale calibration)',
  `opacity` float NOT NULL DEFAULT 1 COMMENT '0.0=transparent, 1.0=opaque',
  `start_offset` float NOT NULL DEFAULT 0,
  `end_offset` float DEFAULT NULL,
  `playback_speed` float NOT NULL DEFAULT 1,
  PRIMARY KEY (`animatic_id`,`asset_type`,`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `multivid_render_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `multivid_render_jobs`;

CREATE TABLE `multivid_render_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `animatic_id` int(11) NOT NULL,
  `arrangement_id` int(11) DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `task_id` varchar(64) DEFAULT NULL,
  `video_id` int(11) DEFAULT NULL,
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_animatic` (`animatic_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `multivid_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `multivid_settings`;

CREATE TABLE `multivid_settings` (
  `animatic_id` int(11) NOT NULL,
  `duration_ms` int(11) NOT NULL DEFAULT 5000 COMMENT 'Recording/export duration in milliseconds',
  `fps` int(11) NOT NULL DEFAULT 30,
  `move_x` int(11) NOT NULL DEFAULT 80 COMMENT 'Total pixels camera pans X over duration',
  `move_y` int(11) NOT NULL DEFAULT 0 COMMENT 'Total pixels camera tilts Y over duration',
  `zoom_start` float NOT NULL DEFAULT 1 COMMENT 'Camera zoom at frame 0',
  `zoom_end` float NOT NULL DEFAULT 1.04 COMMENT 'Camera zoom at last frame',
  `focal_distance` float NOT NULL DEFAULT 10 COMMENT 'Distance where parallax speed=1.0',
  `frustum_height` float NOT NULL DEFAULT 10 COMMENT 'Visible world height in meters at focal distance',
  `scale_reference` float NOT NULL DEFAULT 10 COMMENT 'Fallback scale reference distance',
  `canvas_width` int(11) NOT NULL DEFAULT 1024,
  `canvas_height` int(11) NOT NULL DEFAULT 1024,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`animatic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `muvitriccs_projects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `muvitriccs_projects`;

CREATE TABLE `muvitriccs_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT 'Untitled Project',
  `description` text DEFAULT NULL,
  `animatic_id` int(11) DEFAULT NULL COMMENT 'Optional animatic context',
  `canvas_w` int(11) NOT NULL DEFAULT 1080,
  `canvas_h` int(11) NOT NULL DEFAULT 1080,
  `fps` int(11) NOT NULL DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_animatic` (`animatic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `muvitriccs_render_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `muvitriccs_render_jobs`;

CREATE TABLE `muvitriccs_render_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `slot_a_id` int(11) NOT NULL COMMENT 'outgoing slot',
  `slot_b_id` int(11) NOT NULL COMMENT 'incoming slot',
  `transition_name` varchar(64) NOT NULL DEFAULT '' COMMENT 'Snapshot of the transition type at render time',
  `pyapi_task_id` varchar(64) DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `video_id` int(11) DEFAULT NULL COMMENT 'resulting video in videos table',
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_status` (`status`),
  KEY `idx_slots` (`slot_a_id`,`slot_b_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `muvitriccs_slots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `muvitriccs_slots`;

CREATE TABLE `muvitriccs_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `slot_order` int(11) NOT NULL DEFAULT 0,
  `asset_type` enum('video','frame') NOT NULL DEFAULT 'video',
  `asset_id` int(11) NOT NULL,
  `label` varchar(150) DEFAULT NULL,
  `trim_start` float NOT NULL DEFAULT 0 COMMENT 'seconds into source to start',
  `trim_end` float DEFAULT NULL COMMENT 'NULL = use full asset',
  `playback_speed` float NOT NULL DEFAULT 1,
  `transition_name` varchar(64) NOT NULL DEFAULT 'cross_dissolve' COMMENT 'Transition INTO the NEXT slot (ignored on last slot)',
  `transition_duration_frames` int(11) NOT NULL DEFAULT 24,
  `transition_intensity` float NOT NULL DEFAULT 1,
  `transition_easing` varchar(64) NOT NULL DEFAULT 'ease_in_out_cubic',
  `transition_seed` int(11) NOT NULL DEFAULT 42,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_order` (`project_id`,`slot_order`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `muvitriccs_transition_demos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `muvitriccs_transition_demos`;

CREATE TABLE `muvitriccs_transition_demos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transition_name` varchar(64) NOT NULL COMMENT 'matches TRANSITION_REGISTRY key, e.g. cross_dissolve',
  `video_id` int(11) NOT NULL COMMENT 'FK → videos.id  (the rendered transition video)',
  `job_id` int(11) DEFAULT NULL COMMENT 'FK → muvitriccs_render_jobs.id (origin job, informational)',
  `label` varchar(150) DEFAULT NULL COMMENT 'Optional human label for this demo clip',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = shown as the default preview for this transition',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transition` (`transition_name`),
  KEY `idx_video` (`video_id`),
  KEY `idx_primary` (`transition_name`,`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Assigned demo/preview renders per MuviTriccs transition type';

-- --------------------------------------------------------
-- Table: `narrative_beat_analysis`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `narrative_beat_analysis`;

CREATE TABLE `narrative_beat_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` int(11) NOT NULL COMMENT 'FK → narrative_sequences.id',
  `position` int(11) NOT NULL COMMENT 'Zero-based index in sequence_data array',
  `sketch_id` int(11) NOT NULL COMMENT 'Denormalised for fast lookup',
  `frame_id` int(11) DEFAULT NULL,
  `beat_raw` longtext DEFAULT NULL COMMENT 'Raw JSON from Beat Analyst pass',
  `compose_raw` longtext DEFAULT NULL COMMENT 'Raw JSON from Episode Composer pass',
  `scene_title` varchar(255) DEFAULT NULL,
  `act_label` varchar(255) DEFAULT NULL COMMENT 'e.g. "ACT ONE", "COLD OPEN", "EPILOGUE"',
  `emotional_register` text DEFAULT NULL,
  `rolling_context` longtext DEFAULT NULL COMMENT 'JSON snapshot of rolling narrative state after this beat',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seq_pos` (`sequence_id`,`position`),
  KEY `idx_nba_sketch` (`sketch_id`),
  KEY `idx_nba_sequence` (`sequence_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Per-beat AI analysis for narrative sequence episode generation';

-- --------------------------------------------------------
-- Table: `narrative_sequences`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `narrative_sequences`;

CREATE TABLE `narrative_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT 'Untitled Sequence',
  `description` text DEFAULT NULL,
  `sequence_data` longtext DEFAULT NULL CHECK (json_valid(`sequence_data`)),
  `linked_doc_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `narrative_sequences_auto`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `narrative_sequences_auto`;

CREATE TABLE `narrative_sequences_auto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT 'Auto Generated',
  `description` text DEFAULT NULL,
  `sequence_data` longtext DEFAULT NULL CHECK (json_valid(`sequence_data`)),
  `generation_log` longtext DEFAULT NULL COMMENT 'JSON log of the decision process',
  `linked_doc_id` int(11) DEFAULT NULL,
  `score` float DEFAULT 0 COMMENT 'Internal coherence score',
  `status` enum('pending','promoted','archived') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `narrative_sequence_analysis`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `narrative_sequence_analysis`;

CREATE TABLE `narrative_sequence_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` int(11) NOT NULL COMMENT 'FK → narrative_sequences.id',
  `episode_title` varchar(255) DEFAULT NULL,
  `episode_subtitle` varchar(255) DEFAULT NULL,
  `logline` text DEFAULT NULL,
  `act_structure` longtext DEFAULT NULL COMMENT 'JSON array of act objects',
  `production_notes` longtext DEFAULT NULL,
  `recurring_motifs` longtext DEFAULT NULL COMMENT 'JSON array of motif strings',
  `episode_thesis` text DEFAULT NULL,
  `open_tensions` longtext DEFAULT NULL COMMENT 'JSON array — unresolved threads at episode end',
  `synthesiser_raw` longtext DEFAULT NULL,
  `generator_config_id` int(11) DEFAULT NULL COMMENT 'Which generator config produced this',
  `beat_count` int(11) DEFAULT 0,
  `model_used` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nsa_sequence` (`sequence_id`),
  KEY `idx_nsa_config` (`generator_config_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Final synthesised episode document per narrative sequence';

-- --------------------------------------------------------
-- Table: `narrative_sequence_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `narrative_sequence_categories`;

CREATE TABLE `narrative_sequence_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `plush_collections`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_collections`;

CREATE TABLE `plush_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `plush_collections_2_stories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_collections_2_stories`;

CREATE TABLE `plush_collections_2_stories` (
  `collection_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `arc_label` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`collection_id`,`story_id`),
  KEY `idx_c2s_collection` (`collection_id`),
  KEY `idx_c2s_story` (`story_id`),
  CONSTRAINT `fk_plush_c2s_col` FOREIGN KEY (`collection_id`) REFERENCES `plush_collections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plush_c2s_story` FOREIGN KEY (`story_id`) REFERENCES `plush_stories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `plush_highlight_blocks`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_highlight_blocks`;

CREATE TABLE `plush_highlight_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `text_content` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `bg_color` varchar(30) DEFAULT NULL,
  `language_code` varchar(2) NOT NULL DEFAULT 'en',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scene_group` (`scene_id`,`group_id`),
  CONSTRAINT `fk_plush_blocks_scene` FOREIGN KEY (`scene_id`) REFERENCES `plush_scenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `plush_highlight_block_entities`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_highlight_block_entities`;

CREATE TABLE `plush_highlight_block_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `block_id` int(11) NOT NULL COMMENT 'FK → plush_highlight_blocks.id',
  `entity_type` varchar(50) NOT NULL COMMENT 'characters | factions | locations',
  `entity_id` int(11) NOT NULL COMMENT 'PK of the referenced entity row',
  `entity_label` varchar(255) DEFAULT NULL COMMENT 'Cached display name at time of tagging',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_block_entity` (`block_id`,`entity_type`,`entity_id`),
  KEY `idx_block_id` (`block_id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_phbe_block` FOREIGN KEY (`block_id`) REFERENCES `plush_highlight_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Polymorphic entity tags on PLUSH highlight blocks for timeline display';

-- --------------------------------------------------------
-- Table: `plush_highlight_groups`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_highlight_groups`;

CREATE TABLE `plush_highlight_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_id` int(11) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `group_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scene_id` (`scene_id`),
  CONSTRAINT `fk_plush_groups_scene` FOREIGN KEY (`scene_id`) REFERENCES `plush_scenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `plush_scenes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_scenes`;

CREATE TABLE `plush_scenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `story_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `synopsis` text DEFAULT NULL,
  `scene_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_story_id` (`story_id`),
  CONSTRAINT `fk_plush_scenes_story` FOREIGN KEY (`story_id`) REFERENCES `plush_stories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `plush_scene_dates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_scene_dates`;

CREATE TABLE `plush_scene_dates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scene_id` int(11) NOT NULL COMMENT 'FK → plush_scenes.id',
  `start_label` varchar(100) DEFAULT NULL,
  `end_label` varchar(100) DEFAULT NULL,
  `sort_start` int(11) NOT NULL DEFAULT 0,
  `sort_end` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pscd_scene` (`scene_id`),
  CONSTRAINT `fk_pscd_scene` FOREIGN KEY (`scene_id`) REFERENCES `plush_scenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='In-universe timeline date metadata for PLUSH scenes';

-- --------------------------------------------------------
-- Table: `plush_stories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_stories`;

CREATE TABLE `plush_stories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `plush_story_dates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `plush_story_dates`;

CREATE TABLE `plush_story_dates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `story_id` int(11) NOT NULL COMMENT 'FK → plush_stories.id',
  `era_label` varchar(100) DEFAULT NULL COMMENT 'Display era name e.g. "Age of the Fracture"',
  `start_label` varchar(100) DEFAULT NULL COMMENT 'Human-readable start e.g. "Year 0 AE"',
  `end_label` varchar(100) DEFAULT NULL COMMENT 'Human-readable end   e.g. "Year 12 AE"',
  `sort_start` int(11) NOT NULL DEFAULT 0 COMMENT 'Numeric value for timeline ordering',
  `sort_end` int(11) DEFAULT NULL COMMENT 'Numeric end value; NULL = point-in-time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_psd_story` (`story_id`),
  CONSTRAINT `fk_psd_story` FOREIGN KEY (`story_id`) REFERENCES `plush_stories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='In-universe timeline date metadata for PLUSH stories';

-- --------------------------------------------------------
-- Table: `popkorn_pots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `popkorn_pots`;

CREATE TABLE `popkorn_pots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `popkorn_pot_videos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `popkorn_pot_videos`;

CREATE TABLE `popkorn_pot_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pot_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pot_video` (`pot_id`,`video_id`),
  KEY `idx_pot` (`pot_id`),
  KEY `idx_video` (`video_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `poses_anima`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `poses_anima`;

CREATE TABLE `poses_anima` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `anima_type` varchar(50) DEFAULT NULL COMMENT 'e.g. CHRONO, GRAVITA, MOMENTUM, ENERGIA, THERMA, LUMINA, RESONANTIA, MAGNETICA, FLUIDICA, COHESIVA, PROBABILIS, VITALIS, NOETICA, SPATIA, OSCILLA, MERGE',
  `hierarchy_level` varchar(20) DEFAULT NULL COMMENT 'Trace | Pulse | Surge | Manifest',
  `is_merge` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = this pose depicts a two-Anima merge moment',
  `merge_type` varchar(20) DEFAULT NULL COMMENT 'Harmonic | Forced | Dissonant — null when is_merge = 0',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `posts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `posts`;

CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `post_type` enum('image_grid','image_swiper','video_playlist','youtube_playlist','url_reference') NOT NULL,
  `preview_image_url` varchar(512) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_items` longtext DEFAULT NULL CHECK (json_valid(`media_items`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `sort_order_index` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `additions_snapshot` longtext DEFAULT NULL COMMENT 'Immutable snapshot: [{slot, addition_id|null, text}]' CHECK (json_valid(`additions_snapshot`)),
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sketches_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `prompt_negative_globals`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prompt_negative_globals`;

CREATE TABLE `prompt_negative_globals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prompt_globals_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `pytoon_canvas_sizes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `pytoon_canvas_sizes`;

CREATE TABLE `pytoon_canvas_sizes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL DEFAULT '',
  `width` int(11) NOT NULL DEFAULT 1080,
  `height` int(11) NOT NULL DEFAULT 1920,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `pytoon_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `pytoon_jobs`;

CREATE TABLE `pytoon_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_type` enum('pdf_split','cover_compose') NOT NULL,
  `label` varchar(255) NOT NULL DEFAULT '',
  `status` enum('pending','processing','done','error') NOT NULL DEFAULT 'pending',
  `pyapi_job_id` varchar(64) DEFAULT NULL,
  `source_ref` varchar(512) DEFAULT NULL COMMENT 'series/post ID or raw PDF path that spawned this job',
  `result_zip` varchar(512) DEFAULT NULL,
  `page_count` int(11) NOT NULL DEFAULT 0,
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pj_status` (`status`),
  KEY `idx_pj_type` (`job_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `rapid_showcase`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `rapid_showcase`;

CREATE TABLE `rapid_showcase` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description_prompt` text NOT NULL,
  `generator_config_id` varchar(64) DEFAULT NULL,
  `is_generated` tinyint(1) NOT NULL DEFAULT 0,
  `created_sketch_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_generated` (`is_generated`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sage_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sage_settings`;

CREATE TABLE `sage_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_name` varchar(100) NOT NULL DEFAULT 'Default',
  `settings_json` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `profile_name` (`profile_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sage_task_wrappers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sage_task_wrappers`;

CREATE TABLE `sage_task_wrappers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_name` varchar(100) NOT NULL DEFAULT 'Default',
  `task_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `fixed_args` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT '?',
  `display_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_profile` (`profile_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table: `scene_kitchen_pots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `scene_kitchen_pots`;

CREATE TABLE `scene_kitchen_pots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ingredients_json` longtext NOT NULL CHECK (json_valid(`ingredients_json`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `sequence` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scene_parts_scene` (`scene_id`),
  KEY `idx_scene_parts_scene_seq` (`scene_id`,`sequence`),
  CONSTRAINT `fk_scene_parts_scene` FOREIGN KEY (`scene_id`) REFERENCES `scenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `seopy_keyword_harvest_candidates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `seopy_keyword_harvest_candidates`;

CREATE TABLE `seopy_keyword_harvest_candidates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `seed_id` bigint(20) unsigned DEFAULT NULL,
  `query_text` varchar(255) NOT NULL,
  `query_norm` varchar(255) NOT NULL,
  `query_hash` char(40) NOT NULL,
  `parent_query` varchar(255) DEFAULT NULL,
  `source_kind` varchar(32) NOT NULL DEFAULT 'suggest',
  `depth` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `suggestion_rank` int(10) unsigned DEFAULT NULL,
  `discovered_count` int(10) unsigned NOT NULL DEFAULT 1,
  `score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `language_code` varchar(10) NOT NULL DEFAULT 'en',
  `country_code` varchar(10) NOT NULL DEFAULT 'DE',
  `suggestion_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`suggestion_payload`)),
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `title_guess` varchar(255) DEFAULT NULL,
  `slug_guess` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_query_locale` (`run_id`,`query_hash`,`language_code`,`country_code`),
  KEY `idx_run_score` (`run_id`,`score`),
  KEY `idx_query_norm` (`query_norm`),
  KEY `idx_depth` (`depth`),
  KEY `idx_parent_query` (`parent_query`),
  CONSTRAINT `fk_seopy_keyword_harvest_candidates_run` FOREIGN KEY (`run_id`) REFERENCES `seopy_keyword_harvest_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `seopy_keyword_harvest_queue`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `seopy_keyword_harvest_queue`;

CREATE TABLE `seopy_keyword_harvest_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `seed_id` bigint(20) unsigned DEFAULT NULL,
  `query_text` varchar(255) NOT NULL,
  `query_norm` varchar(255) NOT NULL,
  `query_hash` char(40) NOT NULL,
  `parent_query` varchar(255) DEFAULT NULL,
  `source_kind` varchar(32) NOT NULL DEFAULT 'seed',
  `depth` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `suggestion_rank` int(10) unsigned DEFAULT NULL,
  `discovered_count` int(10) unsigned NOT NULL DEFAULT 1,
  `score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `language_code` varchar(10) NOT NULL DEFAULT 'en',
  `country_code` varchar(10) NOT NULL DEFAULT 'DE',
  `status` enum('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `locked_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `error_text` mediumtext DEFAULT NULL,
  `suggestion_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`suggestion_payload`)),
  `title_guess` varchar(255) DEFAULT NULL,
  `slug_guess` varchar(255) DEFAULT NULL,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_run_query_locale` (`run_id`,`query_hash`,`language_code`,`country_code`),
  KEY `idx_run_status_score` (`run_id`,`status`,`score`,`id`),
  KEY `idx_run_status_depth` (`run_id`,`status`,`depth`,`id`),
  KEY `idx_run_parent` (`run_id`,`parent_query`),
  KEY `idx_status_locked` (`status`,`locked_at`),
  KEY `idx_query_norm` (`query_norm`),
  CONSTRAINT `fk_seopy_keyword_harvest_queue_run` FOREIGN KEY (`run_id`) REFERENCES `seopy_keyword_harvest_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `seopy_keyword_harvest_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `seopy_keyword_harvest_runs`;

CREATE TABLE `seopy_keyword_harvest_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `finished_at` datetime DEFAULT NULL,
  `status` enum('running','finished','failed') NOT NULL DEFAULT 'running',
  `source_mode` varchar(50) NOT NULL DEFAULT 'db_seeds',
  `language_code` varchar(10) NOT NULL DEFAULT 'en',
  `country_code` varchar(10) NOT NULL DEFAULT 'DE',
  `max_depth` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `max_seeds` int(10) unsigned NOT NULL DEFAULT 200,
  `max_expand_per_node` int(10) unsigned NOT NULL DEFAULT 20,
  `delay_ms` int(10) unsigned NOT NULL DEFAULT 350,
  `total_seeds` int(10) unsigned NOT NULL DEFAULT 0,
  `total_queries` int(10) unsigned NOT NULL DEFAULT 0,
  `total_candidates` int(10) unsigned NOT NULL DEFAULT 0,
  `total_saved` int(10) unsigned NOT NULL DEFAULT 0,
  `error_count` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` varchar(500) DEFAULT NULL,
  `error_text` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_started` (`status`,`started_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `seopy_keyword_seed_inputs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `seopy_keyword_seed_inputs`;

CREATE TABLE `seopy_keyword_seed_inputs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_type` varchar(50) NOT NULL DEFAULT 'manual',
  `source_table_name` varchar(128) DEFAULT NULL,
  `source_row_id` varchar(64) DEFAULT NULL,
  `seed_text` varchar(255) NOT NULL,
  `seed_weight` decimal(6,2) NOT NULL DEFAULT 1.00,
  `language_code` varchar(10) NOT NULL DEFAULT 'en',
  `country_code` varchar(10) NOT NULL DEFAULT 'DE',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seed_origin` (`source_type`,`source_table_name`,`source_row_id`,`seed_text`),
  KEY `idx_active_weight` (`active`,`seed_weight`),
  KEY `idx_seed_text` (`seed_text`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sequence_overlay_texts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sequence_overlay_texts`;

CREATE TABLE `sequence_overlay_texts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` int(11) NOT NULL,
  `language_code` varchar(2) NOT NULL DEFAULT 'en',
  `name_overlay` varchar(255) DEFAULT NULL,
  `description_overlay` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seq_lang` (`sequence_id`,`language_code`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sitemap_imports`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sitemap_imports`;

CREATE TABLE `sitemap_imports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `system_name` varchar(255) NOT NULL,
  `urls_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sitemap_sys` (`system_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketches`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketches`;

CREATE TABLE `sketches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'raw or random prompt text',
  `description_raw` text DEFAULT NULL COMMENT 'Stores the original scene prompt before any continuity modifications',
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) DEFAULT 1,
  `mood` text DEFAULT NULL COMMENT 'optional mood description, e.g. whimsical, dark, peaceful',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sketches_name` (`name`),
  KEY `idx_img2img` (`img2img_frame_id`),
  KEY `idx_cnmap` (`cnmap_frame_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketchmig_bundle`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketchmig_bundle`;

CREATE TABLE `sketchmig_bundle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(200) NOT NULL,
  `source_db` varchar(100) DEFAULT NULL COMMENT 'Origin SAGE instance identifier',
  `sketch_count` int(11) NOT NULL DEFAULT 0,
  `frame_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','exported','imported','failed') NOT NULL DEFAULT 'pending',
  `export_note` text DEFAULT NULL,
  `import_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `imported_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketchmig_frame`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketchmig_frame`;

CREATE TABLE `sketchmig_frame` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bundle_id` int(11) NOT NULL,
  `meta_frame_key` varchar(50) NOT NULL,
  `meta_sketch_key` varchar(50) NOT NULL,
  `name_orig` varchar(255) NOT NULL COMMENT 'Original frame name, for reference only',
  `prompt` text NOT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `style` text DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `img2img_meta_frame_key` varchar(50) DEFAULT NULL COMMENT 'Self-referential — meta key of img2img parent frame',
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_meta_frame_key` varchar(50) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `rating` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `zip_path` varchar(500) NOT NULL,
  `imported_frame_name` varchar(255) DEFAULT NULL,
  `imported_frame_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bundle_frame_key` (`bundle_id`,`meta_frame_key`),
  KEY `idx_smig_frame_bundle` (`bundle_id`),
  KEY `idx_smig_frame_sketch` (`meta_sketch_key`),
  CONSTRAINT `fk_smig_frame_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `sketchmig_bundle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketchmig_sketch`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketchmig_sketch`;

CREATE TABLE `sketchmig_sketch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bundle_id` int(11) NOT NULL,
  `meta_sketch_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `description_raw` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `mood` text DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_meta_frame_key` varchar(50) DEFAULT NULL COMMENT 'meta_frame_key of the img2img source frame',
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_meta_frame_key` varchar(50) DEFAULT NULL COMMENT 'meta_frame_key of the cnmap source frame',
  `cnmap_prompt` text DEFAULT NULL,
  `sa_entities` longtext DEFAULT NULL CHECK (json_valid(`sa_entities`)),
  `sa_classification` longtext DEFAULT NULL CHECK (json_valid(`sa_classification`)),
  `sa_scoring` longtext DEFAULT NULL CHECK (json_valid(`sa_scoring`)),
  `sa_thematics` longtext DEFAULT NULL CHECK (json_valid(`sa_thematics`)),
  `sa_recommendations` longtext DEFAULT NULL CHECK (json_valid(`sa_recommendations`)),
  `sa_overall_quality` float DEFAULT 0,
  `ssa_narrative_function` longtext DEFAULT NULL CHECK (json_valid(`ssa_narrative_function`)),
  `ssa_layer` longtext DEFAULT NULL CHECK (json_valid(`ssa_layer`)),
  `ssa_energy` varchar(20) DEFAULT NULL,
  `ssa_position` varchar(20) DEFAULT NULL,
  `ssa_standalone` varchar(30) DEFAULT NULL,
  `ssa_intensity` varchar(10) DEFAULT NULL,
  `ssa_shot_scale` varchar(20) DEFAULT NULL,
  `ssa_edit_relationship` varchar(30) DEFAULT NULL,
  `ssa_structure_type` varchar(20) DEFAULT NULL,
  `ssa_fabula_position` varchar(20) DEFAULT NULL,
  `ssa_syuzhet_position` varchar(20) DEFAULT NULL,
  `ssa_character_presence` varchar(20) DEFAULT NULL,
  `ssa_world_specificity` varchar(20) DEFAULT NULL,
  `ssa_narrative_function_mask` int(11) DEFAULT 0,
  `ssa_layer_mask` tinyint(4) DEFAULT 0,
  `ssa_short_logline` varchar(200) DEFAULT NULL,
  `ssa_connective_hint` varchar(240) DEFAULT NULL,
  `ssa_tags` longtext DEFAULT NULL CHECK (json_valid(`ssa_tags`)),
  `ssa_confidence` float DEFAULT 0,
  `ssa_novelty` float DEFAULT NULL,
  `ssa_thematic_relevance` float DEFAULT NULL,
  `ssa_transition_usability` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bundle_sketch_key` (`bundle_id`,`meta_sketch_key`),
  KEY `idx_smig_bundle` (`bundle_id`),
  CONSTRAINT `fk_smig_sketch_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `sketchmig_bundle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_analysis`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_analysis`;

CREATE TABLE `sketch_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sketch_id` int(11) NOT NULL,
  `entities` longtext DEFAULT NULL CHECK (json_valid(`entities`)),
  `classification` longtext DEFAULT NULL CHECK (json_valid(`classification`)),
  `scoring` longtext DEFAULT NULL CHECK (json_valid(`scoring`)),
  `thematics` longtext DEFAULT NULL CHECK (json_valid(`thematics`)),
  `recommendations` longtext DEFAULT NULL CHECK (json_valid(`recommendations`)),
  `overall_quality` float DEFAULT 0,
  `analyzed_at` timestamp NULL DEFAULT current_timestamp(),
  `generator_config_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sketch` (`sketch_id`),
  KEY `idx_quality` (`overall_quality`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_categories`;

CREATE TABLE `sketch_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_frames`;

CREATE TABLE `sketch_frames` (
  `sketch_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sketch_id`,`frame_id`),
  KEY `idx_frame_id` (`frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_ingredients`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_ingredients`;

CREATE TABLE `sketch_ingredients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sketch_id` int(11) NOT NULL,
  `ingredient_type` varchar(100) NOT NULL COMMENT 'Class name or type identifier',
  `source_id` int(11) DEFAULT NULL COMMENT 'ID of the template/interaction if applicable',
  `prompt_fragment` text DEFAULT NULL COMMENT 'The actual text text generated/contributed',
  `snapshot_data` longtext DEFAULT NULL COMMENT 'JSON snapshot of source data at time of creation',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sketch_id` (`sketch_id`),
  KEY `type_source` (`ingredient_type`,`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_location_ranges`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_location_ranges`;

CREATE TABLE `sketch_location_ranges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL COMMENT 'Descriptive name of the location',
  `sketch_id_from` int(11) NOT NULL COMMENT 'Start of the sketch ID range',
  `sketch_id_to` int(11) NOT NULL COMMENT 'End of the sketch ID range',
  `notes` text DEFAULT NULL COMMENT 'Optional details about this range',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sketch_range` (`sketch_id_from`,`sketch_id_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_lore_history`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_lore_history`;

CREATE TABLE `sketch_lore_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sketch_id` int(11) NOT NULL COMMENT 'Links to sketches.id',
  `doc_id` int(11) NOT NULL COMMENT 'Links to documentations.id',
  `entity_type` varchar(50) NOT NULL COMMENT 'e.g. characters, locations',
  `entity_name` varchar(255) NOT NULL COMMENT 'Name of the specific entity used',
  `generator_config_id` int(11) DEFAULT NULL,
  `prompt_used` text DEFAULT NULL COMMENT 'The context/prompt sent to AI',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sketch` (`sketch_id`),
  KEY `idx_doc` (`doc_id`),
  KEY `idx_entity` (`entity_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_migration_entities`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_migration_entities`;

CREATE TABLE `sketch_migration_entities` (
  `source_type` varchar(50) NOT NULL,
  `source_id` int(11) NOT NULL,
  `target_sketch_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`source_type`,`source_id`),
  KEY `idx_target_sketch` (`target_sketch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_migration_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_migration_frames`;

CREATE TABLE `sketch_migration_frames` (
  `source_frame_id` int(11) NOT NULL,
  `target_frame_id` int(11) NOT NULL,
  `migrated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`source_frame_id`),
  KEY `idx_target_frame` (`target_frame_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_overlay_texts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_overlay_texts`;

CREATE TABLE `sketch_overlay_texts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sketch_id` int(11) NOT NULL,
  `text_content` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `language_code` varchar(2) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`id`),
  KEY `idx_sketch_id` (`sketch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sketch_sequence_analysis`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sketch_sequence_analysis`;

CREATE TABLE `sketch_sequence_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sketch_id` int(11) NOT NULL,
  `narrative_function` longtext DEFAULT NULL CHECK (json_valid(`narrative_function`)),
  `layer` longtext DEFAULT NULL CHECK (json_valid(`layer`)),
  `energy` varchar(20) DEFAULT NULL,
  `position` varchar(20) DEFAULT NULL,
  `standalone` varchar(30) DEFAULT NULL,
  `intensity` varchar(10) DEFAULT NULL,
  `shot_scale` varchar(20) DEFAULT NULL,
  `edit_relationship` varchar(30) DEFAULT NULL,
  `structure_type` varchar(20) DEFAULT NULL,
  `fabula_position` varchar(20) DEFAULT NULL,
  `syuzhet_position` varchar(20) DEFAULT NULL,
  `character_presence` varchar(20) DEFAULT NULL,
  `world_specificity` varchar(20) DEFAULT NULL,
  `narrative_function_mask` int(11) DEFAULT 0,
  `layer_mask` tinyint(4) DEFAULT 0,
  `short_logline` varchar(200) DEFAULT NULL,
  `connective_hint` varchar(240) DEFAULT NULL,
  `tags` longtext DEFAULT NULL CHECK (json_valid(`tags`)),
  `confidence` float DEFAULT 0,
  `novelty` float DEFAULT NULL,
  `thematic_relevance` float DEFAULT NULL,
  `transition_usability` float DEFAULT NULL,
  `generator_config_id` int(11) DEFAULT NULL,
  `analyzed_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sketch_sequence` (`sketch_id`),
  KEY `idx_energy` (`energy`),
  KEY `idx_position` (`position`),
  KEY `idx_intensity` (`intensity`),
  KEY `idx_standalone` (`standalone`),
  KEY `idx_shot_scale` (`shot_scale`),
  KEY `idx_edit_relationship` (`edit_relationship`),
  KEY `idx_structure_type` (`structure_type`),
  KEY `idx_world_specificity` (`world_specificity`),
  KEY `idx_confidence` (`confidence`),
  KEY `idx_nf_mask` (`narrative_function_mask`),
  KEY `idx_layer_mask` (`layer_mask`),
  CONSTRAINT `fk_ssa_sketch` FOREIGN KEY (`sketch_id`) REFERENCES `sketches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `entity_slots` longtext NOT NULL CHECK (json_valid(`entity_slots`)),
  `tags` longtext NOT NULL CHECK (json_valid(`tags`)),
  `example_prompt` text NOT NULL,
  `entity_type` varchar(50) NOT NULL DEFAULT 'sketches',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `category_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`),
  KEY `active` (`active`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splashu_conversation`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splashu_conversation`;

CREATE TABLE `splashu_conversation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) DEFAULT 'default',
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `display_content` mediumtext DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_splashu_conv_user` (`user_id`),
  KEY `idx_splashu_conv_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splashu_plans`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splashu_plans`;

CREATE TABLE `splashu_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `premise` mediumtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_splashu_plans_user` (`user_id`),
  KEY `idx_splashu_plans_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splashu_plan_steps`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splashu_plan_steps`;

CREATE TABLE `splashu_plan_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL,
  `purpose` text DEFAULT NULL,
  `generator_config_id` varchar(255) DEFAULT NULL,
  `prompt_instructions` mediumtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `result_summary` mediumtext DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_splashu_steps_plan` (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splashu_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splashu_sessions`;

CREATE TABLE `splashu_sessions` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_splashu_sessions_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splashu_session_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splashu_session_settings`;

CREATE TABLE `splashu_session_settings` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `auto_advance` tinyint(1) DEFAULT 0,
  `source_text` longtext DEFAULT NULL,
  `offline_mode` tinyint(1) DEFAULT 0,
  `overwrite_done` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`session_id`),
  KEY `idx_splashu_settings_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splash_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splash_runs`;

CREATE TABLE `splash_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `source_text` longtext DEFAULT NULL COMMENT 'The original lore/plot fed into the room',
  `status` varchar(30) NOT NULL DEFAULT 'running' COMMENT 'running|done|failed|cancelled',
  `final_output_text` longtext DEFAULT NULL COMMENT 'The final script produced by the last role',
  `last_error` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_splash_runs_user` (`user_id`),
  KEY `idx_splash_runs_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splash_run_roles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splash_run_roles`;

CREATE TABLE `splash_run_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `template_role_id` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `role_name` varchar(191) NOT NULL,
  `generator_config_id` varchar(255) NOT NULL,
  `prompt_instructions` mediumtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT 'pending|active|done|failed',
  `input_text` longtext DEFAULT NULL COMMENT 'The draft passed from the previous role',
  `output_text` longtext DEFAULT NULL COMMENT 'The draft produced by this role',
  `error_text` mediumtext DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_splash_run_roles_run` (`run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splash_templates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splash_templates`;

CREATE TABLE `splash_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_splash_templates_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `splash_template_roles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `splash_template_roles`;

CREATE TABLE `splash_template_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `role_name` varchar(191) NOT NULL,
  `generator_config_id` varchar(255) NOT NULL COMMENT 'FK to generator_config.config_id',
  `prompt_instructions` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_splash_tmpl_roles_template` (`template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sqlish_extraction_log`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sqlish_extraction_log`;

CREATE TABLE `sqlish_extraction_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `source_text` mediumtext DEFAULT NULL COMMENT 'the text fed into the extraction call',
  `schema_used_json` longtext DEFAULT NULL COMMENT 'the JSON schema built from template columns for this attempt — logged so you can see exactly what was asked for',
  `raw_extraction_output` mediumtext DEFAULT NULL COMMENT 'verbatim response from the extraction generator, before parsing/validation',
  `parsed_ok` tinyint(1) NOT NULL DEFAULT 0,
  `items_found` int(11) NOT NULL DEFAULT 0,
  `parse_error` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sqlish_extract_run` (`run_id`),
  KEY `idx_sqlish_extract_user` (`user_id`),
  KEY `idx_sqlish_extract_template` (`template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sqlish_op_templates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sqlish_op_templates`;

CREATE TABLE `sqlish_op_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'e.g. "Insert Episode Sketches"',
  `description` mediumtext DEFAULT NULL COMMENT 'human-facing notes on what this template is for',
  `target_table` varchar(191) NOT NULL COMMENT 'validated against INFORMATION_SCHEMA.TABLES at save time — never used unescaped beyond identifier-quoting',
  `operation_type` enum('insert','update','select') NOT NULL DEFAULT 'insert' COMMENT 'delete intentionally not offered in v1 — too high-risk a default for AI-assisted writes',
  `key_column` varchar(191) DEFAULT NULL COMMENT 'required for operation_type=update/select — identifies which column locates the target row(s); value must come from run context, never free AI text',
  `ai_instructions` mediumtext DEFAULT NULL COMMENT 'extra guidance folded into the extraction prompt, e.g. "one row per distinct scene mentioned in the source text"',
  `writeback_context_key` varchar(191) DEFAULT NULL COMMENT 'if set, after the op completes the engine writes {min_id,max_id,count} into sqlish_runs.context_json under this key, for later runs/WTF steps to reference',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sqlish_templates_user` (`user_id`),
  KEY `idx_sqlish_templates_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sqlish_op_template_columns`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sqlish_op_template_columns`;

CREATE TABLE `sqlish_op_template_columns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `column_name` varchar(191) NOT NULL COMMENT 'validated against INFORMATION_SCHEMA.COLUMNS for target_table at save time',
  `data_type` varchar(100) DEFAULT NULL COMMENT 'cached from INFORMATION_SCHEMA at save time — informational + used to build the JSON schema type (string/integer/number/boolean)',
  `is_ai_fillable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = value comes from the AI extraction call; 0 = value comes from default_value or context_source_key',
  `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'if 1 and is_ai_fillable=1, the column is required in the extraction JSON schema',
  `default_value` varchar(500) DEFAULT NULL COMMENT 'literal value used when is_ai_fillable=0 and context_source_key is empty (e.g. a fixed status string)',
  `context_source_key` varchar(191) DEFAULT NULL COMMENT 'when is_ai_fillable=0, pull this column value from sqlish_runs.context_json under this key instead of default_value (e.g. episode_id from a prior stage)',
  `description_for_ai` mediumtext DEFAULT NULL COMMENT 'shown to the AI as the only explanation of what this column means — the entire scoping surface for that column, nothing else is inferred',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sqlish_tmpl_cols_template` (`template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sqlish_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sqlish_runs`;

CREATE TABLE `sqlish_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL COMMENT 'defaults to template name + timestamp',
  `source_text` mediumtext DEFAULT NULL COMMENT 'the lore/prose chunk the AI extracts values from (e.g. a Wrish step result)',
  `status` varchar(30) NOT NULL DEFAULT 'running' COMMENT 'running|done|failed|cancelled — same vocabulary as wrish_runs.status',
  `context_json` longtext DEFAULT NULL COMMENT 'free-form key/value data — writeback_context_key results land here, and later runs can read prior runs writeback values by passing them in as extra_context at start time',
  `items_written` int(11) NOT NULL DEFAULT 0 COMMENT 'count of rows successfully written to target_table by this run',
  `items_failed` int(11) NOT NULL DEFAULT 0 COMMENT 'count of extraction items that failed schema validation and were logged to sqlish_run_items with status=failed instead of being written',
  `last_error` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sqlish_runs_user` (`user_id`),
  KEY `idx_sqlish_runs_template` (`template_id`),
  KEY `idx_sqlish_runs_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `sqlish_run_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sqlish_run_items`;

CREATE TABLE `sqlish_run_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_index` int(11) NOT NULL DEFAULT 0 COMMENT 'position of this item within the extraction result array, 0-based',
  `extracted_values_json` longtext DEFAULT NULL COMMENT 'the AI-fillable column values as extracted, before any system columns are merged in',
  `final_values_json` longtext DEFAULT NULL COMMENT 'the complete column=>value map actually used for the write (AI values + system/default/context values merged) — set even on failure, for diagnosis',
  `status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT 'pending|written|failed',
  `written_row_id` bigint(20) DEFAULT NULL COMMENT 'the auto-increment id of the row this item created, when target_table has one and operation_type=insert',
  `error_text` mediumtext DEFAULT NULL COMMENT 'set when status=failed — schema validation error or DB error, human-readable',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sqlish_items_run` (`run_id`),
  KEY `idx_sqlish_items_user` (`user_id`),
  KEY `idx_sqlish_items_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `category_id` int(11) DEFAULT NULL,
  `directory` varchar(255) NOT NULL COMMENT 'Relative path like /storyboards/storyboard001',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category` enum('editorial','location','character','misc') NOT NULL DEFAULT 'misc',
  `editorial_scene_id` int(11) DEFAULT NULL,
  `custom_tag` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `directory` (`directory`),
  KEY `idx_sb_category` (`category`),
  KEY `idx_sb_scene` (`editorial_scene_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `storyboard_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `storyboard_categories`;

CREATE TABLE `storyboard_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL COMMENT 'Slug for logic: editorial, location, character, misc',
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `idx_axis_group` (`axis_group`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `system_languages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `system_languages`;

CREATE TABLE `system_languages` (
  `code` varchar(2) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `tags`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags`;

CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `show_in_ui` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = shown in Taggerang UI, 0 = hidden from UI. Never deleted via UI.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table: `tags_2_animatics`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags_2_animatics`;

CREATE TABLE `tags_2_animatics` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Animatic ID',
  UNIQUE KEY `uq_tags_2_animatics` (`from_id`,`to_id`),
  KEY `idx_t2a_from` (`from_id`),
  KEY `idx_t2a_to` (`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `tags_2_frames`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags_2_frames`;

CREATE TABLE `tags_2_frames` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Frame ID',
  UNIQUE KEY `uq_tags_2_frames` (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `tags_2_frames_staged`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags_2_frames_staged`;

CREATE TABLE `tags_2_frames_staged` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `score` float NOT NULL DEFAULT 0 COMMENT 'Vector similarity score from auto-tag run',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=approved by reviewer, 0=toggled off',
  `reviewed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=frame marked done, removed from unreviewed queue',
  `run_id` varchar(36) DEFAULT NULL COMMENT 'UUID of the auto-tag run that created this row',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staged` (`tag_id`,`frame_id`),
  KEY `idx_frame_id` (`frame_id`),
  KEY `idx_reviewed` (`reviewed`),
  KEY `idx_run_id` (`run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Auto-tag staging — proposals written here, persisted to tags_2_frames after review';

-- --------------------------------------------------------
-- Table: `tags_2_sketches`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags_2_sketches`;

CREATE TABLE `tags_2_sketches` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Sketch ID',
  UNIQUE KEY `uq_tags_2_sketches` (`from_id`,`to_id`),
  KEY `idx_t2s_from` (`from_id`),
  KEY `idx_t2s_to` (`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `tags_2_videos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags_2_videos`;

CREATE TABLE `tags_2_videos` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Video ID',
  UNIQUE KEY `uq_tags_2_videos` (`from_id`,`to_id`),
  KEY `idx_t2v_from` (`from_id`),
  KEY `idx_t2v_to` (`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `tags_2_videos_staged`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tags_2_videos_staged`;

CREATE TABLE `tags_2_videos_staged` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `score` float NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `run_id` varchar(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staged_vid` (`tag_id`,`video_id`),
  KEY `idx_video_id` (`video_id`),
  KEY `idx_reviewed` (`reviewed`),
  KEY `idx_run_id` (`run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `task_wrappers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `task_wrappers`;

CREATE TABLE `task_wrappers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL COMMENT 'Link to scheduled_tasks.id',
  `name` varchar(100) NOT NULL COMMENT 'Short button label',
  `summary` varchar(255) DEFAULT NULL COMMENT 'Small description text',
  `fixed_args` text DEFAULT NULL COMMENT 'The specific args to inject',
  `icon` varchar(50) DEFAULT '?' COMMENT 'Emoji or short icon code',
  `display_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_wrappers_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `tiktok_queue`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tiktok_queue`;

CREATE TABLE `tiktok_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `filepath` varchar(500) DEFAULT NULL,
  `status` enum('pending','downloading','downloaded','pushed','error') NOT NULL DEFAULT 'pending',
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `vector_state`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `vector_state`;

CREATE TABLE `vector_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `collection` varchar(100) NOT NULL,
  `status` enum('pending','indexed','failed','outdated') DEFAULT 'pending',
  `last_ingested_at` timestamp NULL DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  `error_msg` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_entity_target` (`entity_type`,`entity_id`,`collection`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `vedtriccs_connectors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `vedtriccs_connectors`;

CREATE TABLE `vedtriccs_connectors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL COMMENT 'vedtriccs_project_files.id',
  `connector_key` varchar(200) NOT NULL COMMENT 'hash key identifying this clip-pair boundary',
  `params_json` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_file_key` (`file_id`,`connector_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `vedtriccs_projects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `vedtriccs_projects`;

CREATE TABLE `vedtriccs_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT 'Project',
  `folder_name` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `vedtriccs_project_files`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `vedtriccs_project_files`;

CREATE TABLE `vedtriccs_project_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `filename` varchar(120) NOT NULL,
  `state_data` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `vedtriccs_render_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `vedtriccs_render_jobs`;

CREATE TABLE `vedtriccs_render_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) DEFAULT NULL,
  `connector_key` varchar(200) DEFAULT NULL,
  `transition_name` varchar(64) NOT NULL DEFAULT 'cross_dissolve',
  `pyapi_task_id` varchar(64) DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `video_id` int(11) DEFAULT NULL,
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_trans` (`transition_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `vedtriccs_transition_demos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `vedtriccs_transition_demos`;

CREATE TABLE `vedtriccs_transition_demos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transition_name` varchar(64) NOT NULL,
  `video_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trans_vid` (`transition_name`,`video_id`),
  KEY `idx_trans` (`transition_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `ved_projects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ved_projects`;

CREATE TABLE `ved_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT 'Project',
  `folder_name` varchar(120) NOT NULL COMMENT 'Slug used for file organisation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `ved_project_files`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ved_project_files`;

CREATE TABLE `ved_project_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `filename` varchar(120) NOT NULL COMMENT 'Human-readable save name',
  `state_data` longtext NOT NULL COMMENT 'Full JSON state of the timeline',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('inactive','active','damaged','decommissioned') NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `img2img` tinyint(1) NOT NULL DEFAULT 0,
  `depth2img` tinyint(1) DEFAULT 0,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap` tinyint(1) NOT NULL DEFAULT 0,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL,
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicles_name` (`name`),
  KEY `idx_vehicles_type` (`type`),
  KEY `idx_vehicles_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `videos`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `videos`;

CREATE TABLE `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `map_run_id` int(11) DEFAULT NULL,
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
  `review` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_created` (`created_at`),
  KEY `idx_map_run` (`map_run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `videos_2_animatics`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `videos_2_animatics`;

CREATE TABLE `videos_2_animatics` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `videos_2_composites`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `videos_2_composites`;

CREATE TABLE `videos_2_composites` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `composite_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `videos_2_derivates`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `videos_2_derivates`;

CREATE TABLE `videos_2_derivates` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `videos_2_montages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `videos_2_montages`;

CREATE TABLE `videos_2_montages` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  PRIMARY KEY (`from_id`,`to_id`),
  KEY `idx_to_id` (`to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `video_counter`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `video_counter`;

CREATE TABLE `video_counter` (
  `next_video` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `video_enhancements`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `video_enhancements`;

CREATE TABLE `video_enhancements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `chromakey_color` varchar(10) NOT NULL DEFAULT '#00FB00',
  `vid2vid_video_id` int(11) DEFAULT NULL,
  `vid2vid_video_url` varchar(500) DEFAULT NULL,
  `active_map_run_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_videos` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ve_entity` (`entity_type`,`entity_id`),
  KEY `idx_ve_regen` (`regenerate_videos`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `video_tree_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `video_tree_items`;

CREATE TABLE `video_tree_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `node_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_node_video` (`node_id`,`video_id`),
  KEY `idx_node` (`node_id`),
  KEY `idx_video` (`video_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `video_tree_nodes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `video_tree_nodes`;

CREATE TABLE `video_tree_nodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `node_type` enum('folder','episode','sequence','scene','other') DEFAULT 'folder',
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------
-- Table: `worker_img_api_endpoint`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `worker_img_api_endpoint`;

CREATE TABLE `worker_img_api_endpoint` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_code` varchar(64) NOT NULL COMMENT 'Short machine identifier, e.g. pollinations_get_image',
  `provider_name` varchar(128) NOT NULL COMMENT 'Human-readable provider label, e.g. Pollinations',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = disabled, hidden from worker selection',
  `base_url` varchar(255) NOT NULL COMMENT 'Protocol + host, e.g. https://gen.pollinations.ai',
  `path_template` varchar(255) NOT NULL COMMENT 'URL path, use {prompt} token for path-mode prompts',
  `http_method` enum('GET','POST','PUT','PATCH','DELETE') NOT NULL DEFAULT 'GET',
  `url_mode` enum('query','path','mixed') NOT NULL DEFAULT 'query' COMMENT 'How extra params attach: query string vs path segments',
  `default_query_json` longtext DEFAULT NULL COMMENT 'Default key/value pairs appended as query string' CHECK (json_valid(`default_query_json`)),
  `default_headers_json` longtext DEFAULT NULL COMMENT 'Static request headers (excluding auth, which comes from token files)' CHECK (json_valid(`default_headers_json`)),
  `url_template_json` longtext DEFAULT NULL COMMENT 'Optional structured template hints for mixed-mode endpoints' CHECK (json_valid(`url_template_json`)),
  `supports_prompt_in_path` tinyint(1) NOT NULL DEFAULT 0,
  `supports_prompt_in_query` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_worker_img_endpoint_code` (`endpoint_code`),
  KEY `idx_worker_img_endpoint_enabled` (`is_enabled`),
  KEY `idx_worker_img_endpoint_provider` (`provider_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `worker_img_api_endpoint_param`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `worker_img_api_endpoint_param`;

CREATE TABLE `worker_img_api_endpoint_param` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_id` bigint(20) unsigned NOT NULL COMMENT 'FK → worker_img_api_endpoint.id (no FK constraint by design)',
  `param_key` varchar(128) NOT NULL COMMENT 'URL parameter name, e.g. model, width, nologo',
  `location` enum('query','path','header') NOT NULL DEFAULT 'query',
  `value_type` enum('string','number','boolean','json','array','null') NOT NULL DEFAULT 'string',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Lower = earlier in URL',
  `default_value_text` longtext DEFAULT NULL COMMENT 'Scalar default as text',
  `default_value_json` longtext DEFAULT NULL COMMENT 'Complex default (array/object)' CHECK (json_valid(`default_value_json`)),
  `source_key` varchar(128) DEFAULT NULL COMMENT 'Runtime context key that overrides this param',
  `transform_rule` varchar(255) DEFAULT NULL COMMENT 'Optional transform hint for the worker, e.g. urlencode',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_worker_img_endpoint_param` (`endpoint_id`,`param_key`,`location`),
  KEY `idx_worker_img_param_endpoint` (`endpoint_id`),
  KEY `idx_worker_img_param_key` (`param_key`),
  KEY `idx_worker_img_param_enabled` (`is_enabled`),
  KEY `idx_worker_img_param_sort` (`endpoint_id`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `worker_img_provider_default`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `worker_img_provider_default`;

CREATE TABLE `worker_img_provider_default` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(64) NOT NULL DEFAULT 'global' COMMENT 'global = cron worker; manual = ad-hoc runs',
  `endpoint_id` bigint(20) unsigned NOT NULL COMMENT 'Points to worker_img_api_endpoint.id',
  `model_override` varchar(128) DEFAULT NULL COMMENT 'Optional: override the model param for this scope',
  `width_override` smallint(5) unsigned DEFAULT NULL COMMENT 'Optional: override width',
  `height_override` smallint(5) unsigned DEFAULT NULL COMMENT 'Optional: override height',
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_worker_img_provider_default_scope` (`scope`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_chat_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_chat_sessions`;

CREATE TABLE `wrish_chat_sessions` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_wrish_chats_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_conversation`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_conversation`;

CREATE TABLE `wrish_conversation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) DEFAULT 'default',
  `plan_id` int(11) DEFAULT NULL,
  `step_id` int(11) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `display_content` mediumtext DEFAULT NULL COMMENT 'Allows UI to display just the prompt without showing the massive appended context block',
  `protocol` varchar(50) DEFAULT NULL,
  `extraction_status` varchar(20) DEFAULT NULL COMMENT 'NULL|pending|done — Export Mode only; see WrishEngine notes',
  `ts` bigint(20) DEFAULT NULL,
  `context_snapshot` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wrish_conversation_user` (`user_id`),
  KEY `idx_wrish_conv_session` (`session_id`),
  KEY `idx_wrish_conv_plan` (`plan_id`),
  KEY `idx_wrish_conv_step` (`step_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_extraction_log`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_extraction_log`;

CREATE TABLE `wrish_extraction_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) DEFAULT NULL COMMENT 'nullable — extraction can also happen from the live UI outside a formal run',
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) NOT NULL,
  `attempt` int(11) NOT NULL DEFAULT 1,
  `source_text` mediumtext DEFAULT NULL COMMENT 'the creative-call output that was fed into the extraction call',
  `raw_extraction_output` mediumtext DEFAULT NULL COMMENT 'verbatim response from the extraction generator, before parsing',
  `parsed_ok` tinyint(1) NOT NULL DEFAULT 0,
  `parse_error` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wrish_extract_run` (`run_id`),
  KEY `idx_wrish_extract_user` (`user_id`),
  KEY `idx_wrish_extract_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_plans`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_plans`;

CREATE TABLE `wrish_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL COMMENT 'e.g. "Season Three Battle Plan"',
  `premise` mediumtext DEFAULT NULL COMMENT 'the seed brief / vibe / direction the human gave, if any',
  `status` varchar(30) NOT NULL DEFAULT 'active' COMMENT 'active|completed|abandoned',
  `total_steps_estimate` int(11) DEFAULT NULL COMMENT 'AI initial guess at step count — informational only, not a cap',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wrish_plans_user` (`user_id`),
  KEY `idx_wrish_plans_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_plan_steps`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_plan_steps`;

CREATE TABLE `wrish_plan_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL,
  `purpose` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `result_summary` mediumtext DEFAULT NULL,
  `conversation_message_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wrish_steps_plan` (`plan_id`),
  KEY `idx_wrish_steps_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_pools`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_pools`;

CREATE TABLE `wrish_pools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) DEFAULT NULL COMMENT 'NULL = global pool reusable across all sessions',
  `pool_type` varchar(30) NOT NULL COMMENT 'vocabulary|image|tone|friction|fact|question',
  `label` varchar(255) NOT NULL COMMENT 'short name/title of this pool entry',
  `body` mediumtext DEFAULT NULL COMMENT 'the actual content/definition/fragment',
  `source` varchar(50) DEFAULT 'human' COMMENT 'human|ai — who added this entry',
  `weight` int(11) NOT NULL DEFAULT 1 COMMENT 'relative importance/frequency of use, 1-10',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wrish_pools_user` (`user_id`),
  KEY `idx_wrish_pools_session` (`session_id`),
  KEY `idx_wrish_pools_type` (`pool_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_runs`;

CREATE TABLE `wrish_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) NOT NULL COMMENT 'FK-by-convention to wrish_chat_sessions.session_id',
  `status` varchar(30) NOT NULL DEFAULT 'running' COMMENT 'running|paused|done|failed_extraction|cancelled',
  `steps_completed` int(11) NOT NULL DEFAULT 0,
  `max_steps` int(11) NOT NULL DEFAULT 100 COMMENT 'hard ceiling regardless of what the model proposes; internal-only for now',
  `auto_advance` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'if 1, a headless caller may keep calling runStep() without a human between turns',
  `last_error` mediumtext DEFAULT NULL COMMENT 'set when status=failed_extraction; human-readable summary of why the run halted',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wrish_runs_user` (`user_id`),
  KEY `idx_wrish_runs_session` (`session_id`),
  KEY `idx_wrish_runs_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_session_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_session_settings`;

CREATE TABLE `wrish_session_settings` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `depth` varchar(50) DEFAULT 'standard',
  `session_topic` varchar(255) DEFAULT NULL,
  `offline_mode` tinyint(1) DEFAULT 0,
  `auto_advance` tinyint(1) DEFAULT 0,
  `friction_intensity` varchar(20) DEFAULT 'medium',
  `context_extra` longtext DEFAULT NULL,
  `overwrite_done` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`session_id`),
  KEY `idx_wrish_session_settings_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wrish_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wrish_settings`;

CREATE TABLE `wrish_settings` (
  `user_id` int(11) NOT NULL,
  `depth` varchar(50) DEFAULT 'standard' COMMENT 'brief|standard|deep',
  `session_topic` varchar(255) DEFAULT NULL,
  `offline_mode` tinyint(1) DEFAULT 0,
  `auto_advance` tinyint(1) DEFAULT 0,
  `friction_intensity` varchar(20) DEFAULT 'medium' COMMENT 'low|medium|high',
  `context_extra` longtext DEFAULT NULL,
  `draft_pool_entry` longtext DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wroom_chat_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wroom_chat_sessions`;

CREATE TABLE `wroom_chat_sessions` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_wroom_chats_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wroom_chekhov`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wroom_chekhov`;

CREATE TABLE `wroom_chekhov` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `thread_id` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ep` varchar(50) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_wroom_chekhov_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wroom_conversation`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wroom_conversation`;

CREATE TABLE `wroom_conversation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) DEFAULT 'default',
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `protocol` varchar(50) DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  `context_snapshot` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wroom_conversation_user` (`user_id`),
  KEY `idx_wroom_conv_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wroom_deltas`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wroom_deltas`;

CREATE TABLE `wroom_deltas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date_str` varchar(100) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `decisions` text DEFAULT NULL,
  `deferred` text DEFAULT NULL,
  `threads` text DEFAULT NULL,
  `updates` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wroom_deltas_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wroom_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wroom_settings`;

CREATE TABLE `wroom_settings` (
  `user_id` int(11) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `depth` varchar(50) DEFAULT NULL,
  `auto_failure` varchar(50) DEFAULT NULL,
  `session_topic` varchar(255) DEFAULT NULL,
  `context_phase` varchar(50) DEFAULT NULL,
  `context_status` longtext DEFAULT NULL,
  `context_focus` longtext DEFAULT NULL,
  `context_registry_ver` varchar(50) DEFAULT NULL,
  `context_extra` longtext DEFAULT NULL,
  `offline_mode` tinyint(1) DEFAULT 0,
  `kg_selected_nodes` longtext DEFAULT NULL,
  `kg_with_content` tinyint(1) DEFAULT 0,
  `kg_with_edges` tinyint(1) DEFAULT 1,
  `draft_delta` longtext DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wroom_threads`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wroom_threads`;

CREATE TABLE `wroom_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `thread_id` varchar(50) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `seasons` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `axis` varchar(255) DEFAULT NULL,
  `tensions` text DEFAULT NULL,
  `questions` text DEFAULT NULL,
  `chekhov` text DEFAULT NULL,
  `connections` text DEFAULT NULL,
  `is_selected` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_wroom_threads_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wtf_runs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wtf_runs`;

CREATE TABLE `wtf_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL COMMENT 'defaults to workflow title + timestamp, but user may rename a run (e.g. "Season 3 Ep 4 run")',
  `status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT 'pending|running|paused|done|failed|cancelled',
  `current_step_id` int(11) DEFAULT NULL COMMENT 'FK to wtf_run_steps.id — the active/next step, nullable when run is done/pending',
  `context_json` longtext DEFAULT NULL COMMENT 'free-form run-level context/variables handlers may read+write (NOT interpreted by WTF core itself)',
  `auto_advance` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'if 1, executor proceeds to next step automatically after a step completes successfully; if 0, waits for human to advance',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wtf_runs_user` (`user_id`),
  KEY `idx_wtf_runs_workflow` (`workflow_id`),
  KEY `idx_wtf_runs_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wtf_run_events`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wtf_run_events`;

CREATE TABLE `wtf_run_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `step_id` int(11) DEFAULT NULL COMMENT 'nullable — some events are run-level, not step-level (e.g. run paused, run started)',
  `event_type` varchar(50) NOT NULL COMMENT 'run_started|run_paused|run_resumed|run_completed|run_failed|run_cancelled|step_started|step_done|step_failed|step_skipped|step_retried|note',
  `message` mediumtext DEFAULT NULL,
  `meta_json` longtext DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL COMMENT 'client-friendly epoch millis, mirrors wrish_conversation.ts convention',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wtf_run_events_run` (`run_id`),
  KEY `idx_wtf_run_events_step` (`step_id`),
  KEY `idx_wtf_run_events_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wtf_run_steps`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wtf_run_steps`;

CREATE TABLE `wtf_run_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `step_key` varchar(100) DEFAULT NULL COMMENT 'stable key from the template step, e.g. "compile_manifest" — useful for handlers/logs to refer back to the template step',
  `label` varchar(255) NOT NULL,
  `handler_key` varchar(100) NOT NULL COMMENT 'resolved at execution time via HandlerRegistry, e.g. "manual", "noop_delay", later "wrish_invoke" etc.',
  `config_json` longtext DEFAULT NULL COMMENT 'handler-specific config, copied verbatim from the template step at run start',
  `status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT 'pending|active|done|failed|skipped',
  `result_json` longtext DEFAULT NULL COMMENT 'handler output, structured, not prose',
  `result_summary` mediumtext DEFAULT NULL COMMENT 'short human-readable summary of the result, shown in the stepper UI',
  `error_text` mediumtext DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wtf_run_steps_run` (`run_id`),
  KEY `idx_wtf_run_steps_user` (`user_id`),
  KEY `idx_wtf_run_steps_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: `wtf_workflows`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wtf_workflows`;

CREATE TABLE `wtf_workflows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `steps_json` longtext NOT NULL COMMENT 'JSON array of step defs: [{key, label, handler_key, config, notes}, ...]. Authoritative template; copied into wtf_run_steps at run start.',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wtf_workflows_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================================
-- VIEWS (created after tables)
-- ========================================================

-- --------------------------------------------------------
-- View: `v_anima_activity`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_anima_activity`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_anima_activity` AS select `s`.`id` AS `scene_id`,`s`.`sequence` AS `scene_sequence`,`sp`.`id` AS `scene_part_id`,`sp`.`sequence` AS `part_sequence`,`a`.`id` AS `character_anima_id`,`ch`.`name` AS `character_name`,`a`.`name` AS `anima_name`,`span`.`action_type` AS `action_type`,`span`.`notes` AS `notes` from ((((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_animas` `span` on(`span`.`scene_part_id` = `sp`.`id`)) join `animas` `a` on(`a`.`id` = `span`.`character_anima_id`)) join `characters` `ch` on(`ch`.`id` = `a`.`character_id`)) order by `s`.`sequence`,`sp`.`sequence`,`ch`.`name`,`a`.`name`;

-- --------------------------------------------------------
-- View: `v_artifact_usage`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_artifact_usage`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_artifact_usage` AS select `s`.`id` AS `scene_id`,`s`.`sequence` AS `scene_sequence`,`sp`.`id` AS `scene_part_id`,`sp`.`sequence` AS `part_sequence`,`a`.`id` AS `artifact_id`,`a`.`name` AS `artifact_name`,`a`.`type` AS `artifact_type`,`a`.`status` AS `artifact_status`,`spa`.`notes` AS `notes` from (((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_artifacts` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) join `artifacts` `a` on(`a`.`id` = `spa`.`artifact_id`)) order by `s`.`sequence`,`sp`.`sequence`,`a`.`name`;

-- --------------------------------------------------------
-- View: `v_character_anima_pose_angle_combinations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_character_anima_pose_angle_combinations`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_character_anima_pose_angle_combinations` AS select `c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`p`.`id` AS `pose_id`,`p`.`name` AS `pose_name`,`ca`.`id` AS `angle_id`,`ca`.`name` AS `angle_name`,`cp`.`id` AS `perspective_id`,`cp`.`name` AS `perspective_name`,concat('((',ifnull(`p`.`description`,`p`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),'))') AS `base_prompt`,concat('((',ifnull(`p`.`description`,`p`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),')), ',ifnull(`c`.`description`,'')) AS `description` from (((`characters` `c` join `poses_anima` `p`) join `camera_angles` `ca`) join `camera_perspectives` `cp`);

-- --------------------------------------------------------
-- View: `v_character_expression_angle_combinations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_character_expression_angle_combinations`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_character_expression_angle_combinations` AS select `c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`e`.`id` AS `expression_id`,`e`.`name` AS `expression_name`,`ca`.`id` AS `angle_id`,`ca`.`name` AS `angle_name`,`cp`.`id` AS `perspective_id`,`cp`.`name` AS `perspective_name`,concat('((',ifnull(`e`.`description`,`e`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),'))') AS `base_prompt`,concat('((',ifnull(`e`.`description`,`e`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),')), ',ifnull(`c`.`description`,'')) AS `description` from (((`characters` `c` join `anivoc_expressions` `e`) join `camera_angles` `ca`) join `camera_perspectives` `cp`);

-- --------------------------------------------------------
-- View: `v_character_pose_angle_combinations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_character_pose_angle_combinations`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_character_pose_angle_combinations` AS select `c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`p`.`id` AS `pose_id`,`p`.`name` AS `pose_name`,`ca`.`id` AS `angle_id`,`ca`.`name` AS `angle_name`,`cp`.`id` AS `perspective_id`,`cp`.`name` AS `perspective_name`,concat('((',ifnull(`p`.`description`,`p`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),'))') AS `base_prompt`,concat('((',ifnull(`p`.`description`,`p`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),')), ',ifnull(`c`.`description`,'')) AS `description` from (((`characters` `c` join `poses` `p`) join `camera_angles` `ca`) join `camera_perspectives` `cp`);

-- --------------------------------------------------------
-- View: `v_export_ready`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_export_ready`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_export_ready` AS select `s`.`id` AS `scene_id`,`s`.`title` AS `scene_title`,`sp`.`id` AS `scene_part_id`,`ef`.`export_type` AS `export_type`,`ef`.`last_exported_at` AS `last_exported_at` from ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `export_flags` `ef` on(`ef`.`scene_part_id` = `sp`.`id`)) where `ef`.`ready_for_export` = 1;

-- --------------------------------------------------------
-- View: `v_gallery_animas`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_animas`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_animas` AS select `f`.`id` AS `frame_id`,`a`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`a`.`id` AS `anima_id`,`a`.`name` AS `anima_name`,`a`.`traits` AS `traits`,`a`.`abilities` AS `abilities`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`c`.`role` AS `character_role`,_utf8mb4'animas' collate utf8mb4_general_ci AS `entity_type` from ((((`frames` `f` join `frames_2_animas` `m` on(`m`.`from_id` = `f`.`id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) left join `characters` `c` on(`c`.`id` = `a`.`character_id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_animatics`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_animatics`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_animatics` AS select `f`.`id` AS `frame_id`,`a`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`a`.`name` AS `name`,`a`.`description` AS `description`,`f`.`map_run_id` AS `map_run_id`,`f`.`created_at` AS `created_at` from ((`frames` `f` join `frames_2_animatics` `fa` on(`f`.`id` = `fa`.`from_id`)) join `animatics` `a` on(`fa`.`to_id` = `a`.`id`)) order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_artifacts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_artifacts`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_artifacts` AS select `f`.`id` AS `frame_id`,`a`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`a`.`id` AS `artifact_id`,`a`.`name` AS `artifact_name`,`a`.`type` AS `artifact_type`,`a`.`status` AS `artifact_status` from (((`frames` `f` join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `a` on(`m`.`to_id` = `a`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_backgrounds`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_backgrounds`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_backgrounds` AS select `f`.`id` AS `frame_id`,`b`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`b`.`id` AS `background_id`,`b`.`name` AS `background_name`,`b`.`type` AS `background_type`,`l`.`id` AS `location_id`,`l`.`name` AS `location_name`,_utf8mb4'backgrounds' collate utf8mb4_general_ci AS `entity_type` from ((((`frames` `f` join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`m`.`to_id` = `b`.`id`)) left join `locations` `l` on(`b`.`location_id` = `l`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_characters`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_characters`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_characters` AS select `f`.`id` AS `frame_id`,`f`.`map_run_id` AS `map_run_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`c`.`role` AS `character_role`,_utf8mb4'characters' collate utf8mb4_general_ci AS `entity_type` from (((`frames` `f` join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_character_anima_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_character_anima_poses`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_character_anima_poses` AS select `f`.`id` AS `frame_id`,`cap`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`cap`.`description` AS `prompt`,`s`.`name` AS `style`,`cap`.`id` AS `character_pose_id`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`cap`.`pose_id` AS `pose_id`,`p`.`name` AS `pose_name`,`cap`.`angle_id` AS `angle_id`,`ca`.`name` AS `angle_name`,`cap`.`perspective_id` AS `perspective_id`,`cpe`.`name` AS `perspective_name` from (((((((`frames` `f` join `frames_2_character_anima_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_anima_poses` `cap` on(`m`.`to_id` = `cap`.`id`)) join `characters` `c` on(`cap`.`character_id` = `c`.`id`)) join `poses_anima` `p` on(`cap`.`pose_id` = `p`.`id`)) join `camera_angles` `ca` on(`cap`.`angle_id` = `ca`.`id`)) left join `camera_perspectives` `cpe` on(`cap`.`perspective_id` = `cpe`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_character_expressions`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_character_expressions`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_character_expressions` AS select `f`.`id` AS `frame_id`,`ce`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`ce`.`description` AS `prompt`,`s`.`name` AS `style`,`ce`.`id` AS `character_expression_id`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`ce`.`expression_id` AS `expression_id`,`e`.`name` AS `expression_name`,`ce`.`angle_id` AS `angle_id`,`ca`.`name` AS `angle_name`,`ce`.`perspective_id` AS `perspective_id`,`cp`.`name` AS `perspective_name` from (((((((`frames` `f` join `frames_2_character_expressions` `m` on(`f`.`id` = `m`.`from_id`)) join `character_expressions` `ce` on(`m`.`to_id` = `ce`.`id`)) join `characters` `c` on(`ce`.`character_id` = `c`.`id`)) join `anivoc_expressions` `e` on(`ce`.`expression_id` = `e`.`id`)) join `camera_angles` `ca` on(`ce`.`angle_id` = `ca`.`id`)) join `camera_perspectives` `cp` on(`ce`.`perspective_id` = `cp`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_character_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_character_poses`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_character_poses` AS select `f`.`id` AS `frame_id`,`cp`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`cp`.`description` AS `prompt`,`s`.`name` AS `style`,`cp`.`id` AS `character_pose_id`,`c`.`id` AS `character_id`,`c`.`name` AS `character_name`,`cp`.`pose_id` AS `pose_id`,`p`.`name` AS `pose_name`,`cp`.`angle_id` AS `angle_id`,`ca`.`name` AS `angle_name`,`cp`.`perspective_id` AS `perspective_id`,`cpe`.`name` AS `perspective_name` from (((((((`frames` `f` join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`m`.`to_id` = `cp`.`id`)) join `characters` `c` on(`cp`.`character_id` = `c`.`id`)) join `poses` `p` on(`cp`.`pose_id` = `p`.`id`)) join `camera_angles` `ca` on(`cp`.`angle_id` = `ca`.`id`)) left join `camera_perspectives` `cpe` on(`cp`.`perspective_id` = `cpe`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_composites`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_composites`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_composites` AS select `f`.`id` AS `frame_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `composite_id`,`c`.`name` AS `composite_name` from (((`frames` `f` join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_controlnet_maps`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_controlnet_maps` AS select `f`.`id` AS `frame_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `map_id`,`c`.`name` AS `map_name` from ((`frames` `f` join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`m`.`to_id` = `c`.`id`)) where `f`.`map_run_id` = `c`.`active_map_run_id` order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_factions`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_factions`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_factions` AS select `f`.`id` AS `frame_id`,`f`.`map_run_id` AS `map_run_id`,`c`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`c`.`id` AS `faction_id`,`c`.`name` AS `faction_name`,_utf8mb4'factions' collate utf8mb4_general_ci AS `entity_type` from (((`frames` `f` join `frames_2_factions` `m` on(`f`.`id` = `m`.`from_id`)) join `factions` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_generatives`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_generatives`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_generatives` AS select `f`.`id` AS `frame_id`,`g`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`g`.`id` AS `generative_id`,`g`.`name` AS `name`,`g`.`description` AS `description` from (((`frames` `f` join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`m`.`to_id` = `g`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_locations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_locations`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_locations` AS select `f`.`id` AS `frame_id`,`l`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`l`.`id` AS `location_id`,`l`.`name` AS `location_name`,`l`.`type` AS `location_type`,_utf8mb4'locations' collate utf8mb4_general_ci AS `entity_type` from (((`frames` `f` join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`m`.`to_id` = `l`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_prompt_matrix_blueprints`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_prompt_matrix_blueprints` AS select `f`.`id` AS `frame_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`f`.`map_run_id` AS `map_run_id`,`b`.`id` AS `entity_id`,`b`.`name` AS `blueprint_name`,`b`.`entity_type` AS `blueprint_entity_type`,`b`.`entity_id` AS `blueprint_entity_id`,`b`.`description` AS `blueprint_description`,`b`.`matrix_id` AS `blueprint_matrix_id`,`b`.`matrix_additions_id` AS `blueprint_matrix_additions_id`,`b`.`active_map_run_id` AS `blueprint_active_map_run_id`,`b`.`state_id_active` AS `blueprint_state_id_active`,`b`.`regenerate_images` AS `blueprint_regenerate_images`,`b`.`img2img` AS `blueprint_img2img`,`b`.`cnmap` AS `blueprint_cnmap` from (((`frames` `f` join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`m`.`to_id` = `b`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1;

-- --------------------------------------------------------
-- View: `v_gallery_scene_parts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_scene_parts`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_scene_parts` AS select `f`.`id` AS `frame_id`,`sp`.`scene_part_id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`style` AS `style`,`sp`.`scene_part_id` AS `scene_part_id`,`sp`.`name` AS `scene_part_name`,`sp`.`description` AS `scene_part_description`,`sp`.`characters` AS `characters`,`sp`.`animas` AS `animas`,`sp`.`artifacts` AS `artifacts`,`sp`.`backgrounds` AS `backgrounds`,`sp`.`prompt` AS `prompt` from (((`frames` `f` join `frames_2_scene_parts` `m` on(`f`.`id` = `m`.`from_id`)) join (select `sp`.`id` AS `scene_part_id`,`sp`.`name` AS `name`,`sp`.`description` AS `description`,`sp`.`regenerate_images` AS `regenerate_images`,`sp`.`active_map_run_id` AS `active_map_run_id`,substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`,substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`,substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`,substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`,concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt` from ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) group by `sp`.`id`) `sp` on(`m`.`to_id` = `sp`.`scene_part_id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 and `f`.`map_run_id` = `sp`.`active_map_run_id` order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_sketches`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_sketches`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_sketches` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `sketch_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`s`.`mood` AS `mood`,`f`.`map_run_id` AS `map_run_id` from (((`frames` `f` join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`m`.`to_id` = `s`.`id`)) join `styles` `st` on(`f`.`style_id` = `st`.`id`)) where `st`.`visible` = 1 order by `st`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_spawns` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,coalesce(`st`.`code`,`s`.`type`) AS `type`,`st`.`label` AS `type_label`,`st`.`id` AS `spawn_type_id` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) left join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns_location`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns_location`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_spawns_location` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'location' order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns_prop`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns_prop`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_spawns_prop` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'prop' order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns_reference`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns_reference`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_spawns_reference` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'reference' order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_spawns_texture`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_spawns_texture`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_spawns_texture` AS select `f`.`id` AS `frame_id`,`s`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`s`.`id` AS `spawn_id`,`s`.`name` AS `name`,`s`.`description` AS `description`,`st`.`code` AS `type`,`st`.`label` AS `type_label` from (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) where `st`.`code` = 'texture' order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_vehicles`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_vehicles`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_vehicles` AS select `f`.`id` AS `frame_id`,`v`.`id` AS `entity_id`,`f`.`filename` AS `filename`,`f`.`prompt` AS `prompt`,`f`.`style` AS `style`,`v`.`id` AS `vehicle_id`,`v`.`name` AS `vehicle_name`,`v`.`type` AS `vehicle_type`,`v`.`status` AS `vehicle_status` from (((`frames` `f` join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`m`.`to_id` = `v`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) where `s`.`visible` = 1 order by `f`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_gallery_wall_of_images`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_gallery_wall_of_images`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_gallery_wall_of_images` AS select _utf8mb4'animas' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_animas`.`frame_id` AS `frame_id`,`v_gallery_animas`.`entity_id` AS `entity_id`,`v_gallery_animas`.`filename` AS `filename`,`v_gallery_animas`.`prompt` AS `prompt`,`v_gallery_animas`.`anima_name` AS `entity_name` from `v_gallery_animas` union all select _utf8mb4'artifacts' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_artifacts`.`frame_id` AS `frame_id`,`v_gallery_artifacts`.`entity_id` AS `entity_id`,`v_gallery_artifacts`.`filename` AS `filename`,`v_gallery_artifacts`.`prompt` AS `prompt`,`v_gallery_artifacts`.`artifact_name` AS `entity_name` from `v_gallery_artifacts` union all select _utf8mb4'backgrounds' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_backgrounds`.`frame_id` AS `frame_id`,`v_gallery_backgrounds`.`entity_id` AS `entity_id`,`v_gallery_backgrounds`.`filename` AS `filename`,`v_gallery_backgrounds`.`prompt` AS `prompt`,`v_gallery_backgrounds`.`background_name` AS `entity_name` from `v_gallery_backgrounds` union all select _utf8mb4'characters' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_characters`.`frame_id` AS `frame_id`,`v_gallery_characters`.`entity_id` AS `entity_id`,`v_gallery_characters`.`filename` AS `filename`,`v_gallery_characters`.`prompt` AS `prompt`,`v_gallery_characters`.`character_name` AS `entity_name` from `v_gallery_characters` union all select _utf8mb4'composites' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_composites`.`frame_id` AS `frame_id`,`v_gallery_composites`.`entity_id` AS `entity_id`,`v_gallery_composites`.`filename` AS `filename`,`v_gallery_composites`.`prompt` AS `prompt`,`v_gallery_composites`.`composite_name` AS `entity_name` from `v_gallery_composites` union all select _utf8mb4'generatives' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_generatives`.`frame_id` AS `frame_id`,`v_gallery_generatives`.`entity_id` AS `entity_id`,`v_gallery_generatives`.`filename` AS `filename`,`v_gallery_generatives`.`prompt` AS `prompt`,`v_gallery_generatives`.`name` AS `entity_name` from `v_gallery_generatives` union all select _utf8mb4'locations' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_locations`.`frame_id` AS `frame_id`,`v_gallery_locations`.`entity_id` AS `entity_id`,`v_gallery_locations`.`filename` AS `filename`,`v_gallery_locations`.`prompt` AS `prompt`,`v_gallery_locations`.`location_name` AS `entity_name` from `v_gallery_locations` union all select _utf8mb4'sketches' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_sketches`.`frame_id` AS `frame_id`,`v_gallery_sketches`.`entity_id` AS `entity_id`,`v_gallery_sketches`.`filename` AS `filename`,`v_gallery_sketches`.`prompt` AS `prompt`,`v_gallery_sketches`.`name` AS `entity_name` from `v_gallery_sketches` union all select _utf8mb4'vehicles' collate utf8mb4_general_ci AS `entity_type`,`v_gallery_vehicles`.`frame_id` AS `frame_id`,`v_gallery_vehicles`.`entity_id` AS `entity_id`,`v_gallery_vehicles`.`filename` AS `filename`,`v_gallery_vehicles`.`prompt` AS `prompt`,`v_gallery_vehicles`.`vehicle_name` AS `entity_name` from `v_gallery_vehicles`;

-- --------------------------------------------------------
-- View: `v_map_runs_animas`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_animas`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_animas` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `a`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_animas` `m` on(`f`.`id` = `m`.`from_id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'animas';

-- --------------------------------------------------------
-- View: `v_map_runs_artifacts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_artifacts`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_artifacts` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `ar`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `ar` on(`ar`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'artifacts';

-- --------------------------------------------------------
-- View: `v_map_runs_audio_ambiences`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_audio_ambiences`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_audio_ambiences` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `e`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_ambiences` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_ambiences` `e` on(`m`.`to_id` = `e`.`id`)) where `mr`.`entity_type` = 'audio_ambiences';

-- --------------------------------------------------------
-- View: `v_map_runs_audio_cues`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_audio_cues`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_audio_cues` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `e`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_cues` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_cues` `e` on(`m`.`to_id` = `e`.`id`)) where `mr`.`entity_type` = 'audio_cues';

-- --------------------------------------------------------
-- View: `v_map_runs_audio_dialogue_lines`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_audio_dialogue_lines`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_audio_dialogue_lines` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `adl`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_dialogue_lines` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_dialogue_lines` `adl` on(`adl`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'audio_dialogue_lines';

-- --------------------------------------------------------
-- View: `v_map_runs_audio_foleys`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_audio_foleys`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_audio_foleys` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `e`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_foleys` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_foleys` `e` on(`m`.`to_id` = `e`.`id`)) where `mr`.`entity_type` = 'audio_foleys';

-- --------------------------------------------------------
-- View: `v_map_runs_audio_fxsounds`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_audio_fxsounds`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_audio_fxsounds` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `e`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_fxsounds` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_fxsounds` `e` on(`m`.`to_id` = `e`.`id`)) where `mr`.`entity_type` = 'audio_fxsounds';

-- --------------------------------------------------------
-- View: `v_map_runs_audio_themes`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_audio_themes`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_audio_themes` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `e`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_themes` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_themes` `e` on(`m`.`to_id` = `e`.`id`)) where `mr`.`entity_type` = 'audio_themes';

-- --------------------------------------------------------
-- View: `v_map_runs_backgrounds`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_backgrounds`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_backgrounds` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `b`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`b`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'backgrounds';

-- --------------------------------------------------------
-- View: `v_map_runs_characters`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_characters`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_characters` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'characters';

-- --------------------------------------------------------
-- View: `v_map_runs_character_anima_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_character_anima_poses`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_character_anima_poses` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `cap`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_anima_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_anima_poses` `cap` on(`cap`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'character_anima_poses';

-- --------------------------------------------------------
-- View: `v_map_runs_character_expressions`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_character_expressions`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_character_expressions` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `ce`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_expressions` `m` on(`f`.`id` = `m`.`from_id`)) join `character_expressions` `ce` on(`ce`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'character_expressions';

-- --------------------------------------------------------
-- View: `v_map_runs_character_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_character_poses`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_character_poses` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `cp`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`cp`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'character_poses';

-- --------------------------------------------------------
-- View: `v_map_runs_composites`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_composites`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_composites` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'composites';

-- --------------------------------------------------------
-- View: `v_map_runs_controlnet_maps`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_controlnet_maps` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'controlnet_maps';

-- --------------------------------------------------------
-- View: `v_map_runs_factions`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_factions`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_factions` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `c`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_factions` `m` on(`f`.`id` = `m`.`from_id`)) join `factions` `c` on(`c`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'factions';

-- --------------------------------------------------------
-- View: `v_map_runs_generatives`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_generatives`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_generatives` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `g`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`g`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'generatives';

-- --------------------------------------------------------
-- View: `v_map_runs_locations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_locations`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_locations` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `l`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`l`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'locations';

-- --------------------------------------------------------
-- View: `v_map_runs_prompt_matrix_blueprints`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_prompt_matrix_blueprints` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `b`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`b`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'prompt_matrix_blueprints';

-- --------------------------------------------------------
-- View: `v_map_runs_scene_parts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_scene_parts`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_scene_parts` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`f2sp`.`to_id` AS `entity_id`,case when `mr`.`id` = `sp`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_scene_parts` `f2sp` on(`f2sp`.`from_id` = `f`.`id`)) join `scene_parts` `sp` on(`sp`.`id` = `f2sp`.`to_id`)) where `mr`.`entity_type` = 'scene_parts';

-- --------------------------------------------------------
-- View: `v_map_runs_sketches`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_sketches`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_sketches` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `s`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`s`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'sketches';

-- --------------------------------------------------------
-- View: `v_map_runs_vehicles`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_map_runs_vehicles`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_map_runs_vehicles` AS select distinct `mr`.`id` AS `id`,`mr`.`created_at` AS `created_at`,`mr`.`note` AS `note`,`m`.`to_id` AS `entity_id`,case when `mr`.`id` = `v`.`active_map_run_id` then 1 else 0 end AS `is_active` from (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`v`.`id` = `m`.`to_id`)) where `mr`.`entity_type` = 'vehicles';

-- --------------------------------------------------------
-- View: `v_player_audio_ambiences`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_player_audio_ambiences`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_player_audio_ambiences` AS select `a`.`id` AS `audio_id`,`e`.`id` AS `entity_id`,`a`.`filename` AS `filename`,`a`.`name` AS `audio_name`,`a`.`rvc_model_name` AS `model`,`e`.`id` AS `audio_ambience_id`,`e`.`name` AS `name`,`e`.`description` AS `description`,`a`.`created_at` AS `created_at` from ((`audios` `a` join `audios_2_audio_ambiences` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_ambiences` `e` on(`m`.`to_id` = `e`.`id`)) order by `a`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_player_audio_cues`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_player_audio_cues`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_player_audio_cues` AS select `a`.`id` AS `audio_id`,`e`.`id` AS `entity_id`,`a`.`filename` AS `filename`,`a`.`name` AS `audio_name`,`a`.`rvc_model_name` AS `model`,`e`.`id` AS `audio_cue_id`,`e`.`name` AS `name`,`e`.`description` AS `description`,`a`.`created_at` AS `created_at` from ((`audios` `a` join `audios_2_audio_cues` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_cues` `e` on(`m`.`to_id` = `e`.`id`)) order by `a`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_player_audio_dialogue_lines`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_player_audio_dialogue_lines`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_player_audio_dialogue_lines` AS select `a`.`id` AS `audio_id`,`e`.`id` AS `entity_id`,`a`.`filename` AS `filename`,`a`.`name` AS `audio_name`,`a`.`rvc_model_name` AS `model`,`e`.`id` AS `audio_dialogue_line_id`,`e`.`name` AS `name`,`e`.`description` AS `description`,`a`.`created_at` AS `created_at` from ((`audios` `a` join `audios_2_audio_dialogue_lines` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_dialogue_lines` `e` on(`m`.`to_id` = `e`.`id`)) order by `a`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_player_audio_foleys`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_player_audio_foleys`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_player_audio_foleys` AS select `a`.`id` AS `audio_id`,`e`.`id` AS `entity_id`,`a`.`filename` AS `filename`,`a`.`name` AS `audio_name`,`a`.`rvc_model_name` AS `model`,`e`.`id` AS `audio_foley_id`,`e`.`name` AS `name`,`e`.`description` AS `description`,`a`.`created_at` AS `created_at` from ((`audios` `a` join `audios_2_audio_foleys` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_foleys` `e` on(`m`.`to_id` = `e`.`id`)) order by `a`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_player_audio_fxsounds`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_player_audio_fxsounds`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_player_audio_fxsounds` AS select `a`.`id` AS `audio_id`,`e`.`id` AS `entity_id`,`a`.`filename` AS `filename`,`a`.`name` AS `audio_name`,`a`.`rvc_model_name` AS `model`,`e`.`id` AS `audio_fxsound_id`,`e`.`name` AS `name`,`e`.`description` AS `description`,`a`.`created_at` AS `created_at` from ((`audios` `a` join `audios_2_audio_fxsounds` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_fxsounds` `e` on(`m`.`to_id` = `e`.`id`)) order by `a`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_player_audio_themes`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_player_audio_themes`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_player_audio_themes` AS select `a`.`id` AS `audio_id`,`e`.`id` AS `entity_id`,`a`.`filename` AS `filename`,`a`.`name` AS `audio_name`,`a`.`rvc_model_name` AS `model`,`e`.`id` AS `audio_theme_id`,`e`.`name` AS `name`,`e`.`description` AS `description`,`a`.`created_at` AS `created_at` from ((`audios` `a` join `audios_2_audio_themes` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_themes` `e` on(`m`.`to_id` = `e`.`id`)) order by `a`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_player_daw_projects`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_player_daw_projects`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_player_daw_projects` AS select `a`.`id` AS `audio_id`,`e`.`id` AS `entity_id`,`a`.`filename` AS `filename`,`a`.`name` AS `audio_name`,`a`.`rvc_model_name` AS `model`,`e`.`id` AS `daw_project_id`,`e`.`name` AS `name`,`e`.`folder_name` AS `description`,`a`.`created_at` AS `created_at` from ((`audios` `a` join `audios_2_daw_projects` `m` on(`a`.`id` = `m`.`from_id`)) join `daw_projects` `e` on(`m`.`to_id` = `e`.`id`)) order by `a`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_player_editorial_shots`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_player_editorial_shots`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_player_editorial_shots` AS select `a`.`id` AS `audio_id`,`e`.`id` AS `entity_id`,`a`.`filename` AS `filename`,`a`.`name` AS `audio_name`,`a`.`rvc_model_name` AS `model`,`e`.`id` AS `editorial_shot_id`,`e`.`name` AS `name`,`e`.`description` AS `description`,`a`.`created_at` AS `created_at` from ((`audios` `a` join `audios_2_editorial_shots` `m` on(`a`.`id` = `m`.`from_id`)) join `editorial_shots` `e` on(`m`.`to_id` = `e`.`id`)) order by `a`.`created_at` desc;

-- --------------------------------------------------------
-- View: `v_prompts_animas`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_animas`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_animas` AS select `a`.`id` AS `id`,`a`.`regenerate_images` AS `regenerate_images`,coalesce(`a`.`description`,'') AS `prompt`,coalesce(`a`.`prompt_negative`,'') AS `prompt_negative`,`a`.`seed` AS `seed` from `animas` `a`;

-- --------------------------------------------------------
-- View: `v_prompts_artifacts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_artifacts`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_artifacts` AS select `ar`.`id` AS `id`,`ar`.`regenerate_images` AS `regenerate_images`,coalesce(`ar`.`description`,'') AS `prompt`,coalesce(`ar`.`prompt_negative`,'') AS `prompt_negative`,`ar`.`seed` AS `seed` from `artifacts` `ar`;

-- --------------------------------------------------------
-- View: `v_prompts_backgrounds`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_backgrounds`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_backgrounds` AS select `b`.`id` AS `id`,`b`.`regenerate_images` AS `regenerate_images`,coalesce(`b`.`description`,'') AS `prompt`,coalesce(`b`.`prompt_negative`,'') AS `prompt_negative`,`b`.`seed` AS `seed` from `backgrounds` `b`;

-- --------------------------------------------------------
-- View: `v_prompts_characters`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_characters`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_characters` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,coalesce(`c`.`description`,'') AS `prompt`,coalesce(`c`.`prompt_negative`,'') AS `prompt_negative`,`c`.`seed` AS `seed` from `characters` `c`;

-- --------------------------------------------------------
-- View: `v_prompts_character_anima_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_character_anima_poses`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_character_anima_poses` AS select `cap`.`id` AS `id`,`cap`.`regenerate_images` AS `regenerate_images`,`cap`.`description` AS `prompt`,coalesce(`cap`.`prompt_negative`,'') AS `prompt_negative`,`cap`.`seed` AS `seed` from `character_anima_poses` `cap`;

-- --------------------------------------------------------
-- View: `v_prompts_character_expressions`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_character_expressions`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_character_expressions` AS select `ce`.`id` AS `id`,`ce`.`regenerate_images` AS `regenerate_images`,`ce`.`description` AS `prompt`,coalesce(`ce`.`prompt_negative`,'') AS `prompt_negative`,`ce`.`seed` AS `seed` from `character_expressions` `ce`;

-- --------------------------------------------------------
-- View: `v_prompts_character_poses`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_character_poses`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_character_poses` AS select `cp`.`id` AS `id`,`cp`.`regenerate_images` AS `regenerate_images`,`cp`.`description` AS `prompt`,coalesce(`cp`.`prompt_negative`,'') AS `prompt_negative`,`cp`.`seed` AS `seed` from `character_poses` `cp`;

-- --------------------------------------------------------
-- View: `v_prompts_composites`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_composites`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_composites` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,coalesce(`c`.`description`,'') AS `prompt`,coalesce(`c`.`prompt_negative`,'') AS `prompt_negative`,`c`.`seed` AS `seed` from `composites` `c`;

-- --------------------------------------------------------
-- View: `v_prompts_controlnet_maps`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_controlnet_maps` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,concat_ws(', ',`c`.`name`,coalesce(`c`.`description`,'')) AS `prompt`,coalesce(`c`.`prompt_negative`,'') AS `prompt_negative`,`c`.`seed` AS `seed` from `controlnet_maps` `c`;

-- --------------------------------------------------------
-- View: `v_prompts_factions`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_factions`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_factions` AS select `c`.`id` AS `id`,`c`.`regenerate_images` AS `regenerate_images`,coalesce(`c`.`description`,'') AS `prompt`,coalesce(`c`.`prompt_negative`,'') AS `prompt_negative`,`c`.`seed` AS `seed` from `factions` `c`;

-- --------------------------------------------------------
-- View: `v_prompts_generatives`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_generatives`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_generatives` AS select `g`.`id` AS `id`,`g`.`regenerate_images` AS `regenerate_images`,concat_ws(', ',`g`.`name`,coalesce(`g`.`description`,'')) AS `prompt`,coalesce(`g`.`prompt_negative`,'') AS `prompt_negative`,`g`.`seed` AS `seed` from `generatives` `g`;

-- --------------------------------------------------------
-- View: `v_prompts_locations`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_locations`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_locations` AS select `l`.`id` AS `id`,`l`.`regenerate_images` AS `regenerate_images`,coalesce(`l`.`description`,'') AS `prompt`,coalesce(`l`.`prompt_negative`,'') AS `prompt_negative`,`l`.`seed` AS `seed` from `locations` `l`;

-- --------------------------------------------------------
-- View: `v_prompts_prompt_matrix_blueprints`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_prompt_matrix_blueprints` AS select `prompt_matrix_blueprints`.`id` AS `id`,`prompt_matrix_blueprints`.`regenerate_images` AS `regenerate_images`,coalesce(`prompt_matrix_blueprints`.`description`,'') AS `prompt`,coalesce(`prompt_matrix_blueprints`.`prompt_negative`,'') AS `prompt_negative`,`prompt_matrix_blueprints`.`seed` AS `seed` from `prompt_matrix_blueprints`;

-- --------------------------------------------------------
-- View: `v_prompts_scene_parts`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_scene_parts`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_scene_parts` AS select `sp`.`id` AS `scene_part_id`,`sp`.`id` AS `id`,`sp`.`scene_id` AS `scene_id`,`sp`.`name` AS `name`,`sp`.`description` AS `description`,substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`,substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`,substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`,substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`,concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt`,`sp`.`regenerate_images` AS `regenerate_images`,coalesce(`sp`.`prompt_negative`,'') AS `prompt_negative`,`sp`.`seed` AS `seed` from ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) group by `sp`.`id`;

-- --------------------------------------------------------
-- View: `v_prompts_sketches`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_sketches`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_sketches` AS select `s`.`id` AS `id`,`s`.`regenerate_images` AS `regenerate_images`,coalesce(`s`.`description`,'') AS `prompt`,coalesce(`s`.`prompt_negative`,'') AS `prompt_negative`,`s`.`seed` AS `seed` from `sketches` `s`;

-- --------------------------------------------------------
-- View: `v_prompts_vehicles`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_prompts_vehicles`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_prompts_vehicles` AS select `v`.`id` AS `id`,`v`.`regenerate_images` AS `regenerate_images`,coalesce(`v`.`description`,'') AS `prompt`,coalesce(`v`.`prompt_negative`,'') AS `prompt_negative`,`v`.`seed` AS `seed` from `vehicles` `v`;

-- --------------------------------------------------------
-- View: `v_scenes_under_review`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_scenes_under_review`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_scenes_under_review` AS select `s`.`id` AS `scene_id`,`s`.`title` AS `scene_title`,`sp`.`id` AS `scene_part_id`,`ps`.`stage` AS `stage`,`ps`.`assigned_to` AS `assigned_to`,`ps`.`updated_at` AS `updated_at` from ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `production_status` `ps` on(`ps`.`scene_part_id` = `sp`.`id`)) where `ps`.`stage` = 'review';

-- --------------------------------------------------------
-- View: `v_scene_part_full`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_scene_part_full`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_scene_part_full` AS select `sp`.`id` AS `scene_part_id`,`sp`.`name` AS `scene_part_name`,`sp`.`description` AS `scene_part_description`,`p`.`angle` AS `perspective_angle`,`p`.`description` AS `perspective_notes`,`b`.`name` AS `background_name`,`b`.`description` AS `background_description`,group_concat(distinct `a`.`name` separator ', ') AS `animas_in_scene`,group_concat(distinct concat(`a`.`name`,': ',`a`.`traits`,'; ',`a`.`abilities`) separator ' | ') AS `animas_details` from (((((`scene_parts` `sp` join `perspectives` `p` on(`p`.`scene_part_id` = `sp`.`id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `p`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) group by `sp`.`id`,`p`.`id`,`b`.`id` order by `sp`.`sequence`,`p`.`id`;

-- --------------------------------------------------------
-- View: `v_styles_helper`
-- --------------------------------------------------------

DROP VIEW IF EXISTS `v_styles_helper`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_styles_helper` AS select `s`.`id` AS `id`,0 AS `regenerate_images`,concat('(',coalesce(`s`.`description`,''),')','(',(select `prompt_globals`.`description` from `prompt_globals` where `prompt_globals`.`id` = 1),')') AS `prompt` from `styles` `s` where `s`.`active` = 1 order by `s`.`order`;



COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
