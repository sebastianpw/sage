-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Erstellungszeit: 17. Okt 2025 um 23:07
-- Server-Version: 10.11.14-MariaDB-0+deb12u2
-- PHP-Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `sage_main`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `angles`
--

CREATE TABLE `angles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `angles`
--

INSERT INTO `angles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Front', 'front view, character facing the viewer', '2025-08-22 20:18:15', '2025-08-23 19:57:43'),
(2, 'Back', 'back view, character facing away from the viewer', '2025-08-22 20:18:15', '2025-08-23 19:57:43'),
(3, 'Left Profile', 'left profile view, character facing left side', '2025-08-22 20:18:15', '2025-08-23 19:57:43'),
(9, 'Top', 'View from above', '2025-08-22 20:18:15', '2025-08-22 20:18:15');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `animas`
--

CREATE TABLE `animas` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `character_id` int(11) DEFAULT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `artifacts`
--

CREATE TABLE `artifacts` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `audio_assets`
--

CREATE TABLE `audio_assets` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('voice','music','sfx') NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `backgrounds`
--

CREATE TABLE `backgrounds` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `characters`
--

CREATE TABLE `characters` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `character_poses`
--

CREATE TABLE `character_poses` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chat_message`
--

CREATE TABLE `chat_message` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `role` varchar(10) NOT NULL,
  `content` longtext NOT NULL,
  `token_count` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chat_session`
--

CREATE TABLE `chat_session` (
  `id` int(11) NOT NULL,
  `session_id` varchar(36) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `model` varchar(50) DEFAULT 'openai',
  `type` varchar(32) NOT NULL DEFAULT 'standard'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chat_summary`
--

CREATE TABLE `chat_summary` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `summary` longtext NOT NULL,
  `tokens` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `composites`
--

CREATE TABLE `composites` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `composite_frames`
--

CREATE TABLE `composite_frames` (
  `composite_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `content_elements`
--

CREATE TABLE `content_elements` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `html` mediumtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `controlnet_maps`
--

CREATE TABLE `controlnet_maps` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `export_flags`
--

CREATE TABLE `export_flags` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `ready_for_export` tinyint(1) NOT NULL DEFAULT 0,
  `export_type` enum('script','art','audio','full_package') NOT NULL,
  `last_exported_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `feedback_notes`
--

CREATE TABLE `feedback_notes` (
  `id` int(11) NOT NULL,
  `source` varchar(100) NOT NULL,
  `scene_part_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `action_required` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames`
--

CREATE TABLE `frames` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_animas`
--

CREATE TABLE `frames_2_animas` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_artifacts`
--

CREATE TABLE `frames_2_artifacts` (
  `id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_backgrounds`
--

CREATE TABLE `frames_2_backgrounds` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_characters`
--

CREATE TABLE `frames_2_characters` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_character_poses`
--

CREATE TABLE `frames_2_character_poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_composites`
--

CREATE TABLE `frames_2_composites` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_controlnet_maps`
--

CREATE TABLE `frames_2_controlnet_maps` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_generatives`
--

CREATE TABLE `frames_2_generatives` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_locations`
--

CREATE TABLE `frames_2_locations` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_pastebin`
--

CREATE TABLE `frames_2_pastebin` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_prompt_matrix_blueprints`
--

CREATE TABLE `frames_2_prompt_matrix_blueprints` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_scene_parts`
--

CREATE TABLE `frames_2_scene_parts` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_sketches`
--

CREATE TABLE `frames_2_sketches` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_spawns`
--

CREATE TABLE `frames_2_spawns` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_2_vehicles`
--

CREATE TABLE `frames_2_vehicles` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_chains`
--

CREATE TABLE `frames_chains` (
  `id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL COMMENT 'The frame that is part of this chain step',
  `parent_frame_id` int(11) DEFAULT NULL COMMENT 'Previous frame in the chain (NULL if first in chain)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rolled_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = this chain step has been rolled back'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_failed`
--

CREATE TABLE `frames_failed` (
  `id` int(11) NOT NULL,
  `prompt` text DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `style` varchar(255) DEFAULT NULL,
  `style_id` int(11) DEFAULT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `img2img_entity` varchar(50) DEFAULT NULL,
  `img2img_id` int(11) DEFAULT NULL,
  `img2img_filename` varchar(255) DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_init`
--

CREATE TABLE `frames_init` (
  `id` int(11) DEFAULT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `prompt` text DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `style` text DEFAULT NULL,
  `style_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `state_id` int(11) DEFAULT NULL,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) DEFAULT NULL,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_showcase`
--

CREATE TABLE `frames_showcase` (
  `id` int(11) DEFAULT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `prompt` text DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `style` text DEFAULT NULL,
  `style_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `state_id` int(11) DEFAULT NULL,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) DEFAULT NULL,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_test`
--

CREATE TABLE `frames_test` (
  `id` int(11) DEFAULT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `prompt` text DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `style` text DEFAULT NULL,
  `style_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `state_id` int(11) DEFAULT NULL,
  `img2img_frame_id` int(11) DEFAULT NULL,
  `img2img_frame_filename` varchar(100) DEFAULT NULL,
  `img2img_prompt` text DEFAULT NULL,
  `cnmap` tinyint(1) DEFAULT NULL,
  `cnmap_frame_id` int(11) DEFAULT NULL,
  `cnmap_frame_filename` varchar(100) DEFAULT NULL,
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frames_trashcan`
--

CREATE TABLE `frames_trashcan` (
  `id` int(11) NOT NULL,
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
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `frame_counter`
--

CREATE TABLE `frame_counter` (
  `id` int(11) NOT NULL DEFAULT 1,
  `next_frame` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `frame_counter`
--

INSERT INTO `frame_counter` (`id`, `next_frame`) VALUES
(1, 8492);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `generatives`
--

CREATE TABLE `generatives` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `generator_config`
--




CREATE TABLE `generator_config` (
  `id` int(11) NOT NULL,
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
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `generator_config` (`id`, `config_id`, `user_id`, `title`, `model`, `system_role`, `instructions`, `parameters`, `output_schema`, `examples`, `created_at`, `updated_at`, `active`) VALUES
(1, '2df46a413521b0041029775c5f6926c6', 1, 'Tongue Twister Generator', 'openai/gpt-oss-120b', 'Zungenbrecher Oracle', '[\"You are an expert tongue-twister generator in German and English.\",\"Generate creative, linguistically challenging twisters.\",\"CRITICAL: Return ONLY a single valid JSON object matching the output schema.\",\"Always include the word count in \'metadata.wordCount\'.\",\"Respect \'firstLetter\' and \'wordCount\' parameters if provided.\",\"Ensure outputs are varied and not always the same for identical parameters.\",\"If you cannot comply, return: {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"brief explanation\\\"}\"]', '{\"mode\":{\"type\":\"string\",\"enum\":[\"easy\",\"medium\",\"extreme\"],\"default\":\"medium\",\"description\":\"Difficulty level\"},\"language\":{\"type\":\"string\",\"enum\":[\"german\",\"english\"],\"default\":\"german\"},\"firstLetter\":{\"type\":\"string\",\"pattern\":\"^[A-Za-z\\u00c4\\u00d6\\u00dc\\u00e4\\u00f6\\u00fc\\u00df]$\",\"optional\":true,\"description\":\"All words must start with this letter\"},\"wordCount\":{\"type\":\"integer\",\"optional\":true,\"minimum\":2,\"description\":\"Number of words the twister should have\"}}', '{\"type\":\"object\",\"properties\":{\"mode\":{\"type\":\"string\"},\"language\":{\"type\":\"string\"},\"twister\":{\"type\":\"string\"},\"metadata\":{\"type\":\"object\",\"properties\":{\"wordCount\":{\"type\":\"integer\",\"description\":\"Number of words in the twister\"},\"firstLetter\":{\"type\":\"string\"},\"alternatives\":{\"type\":\"array\",\"items\":{\"type\":\"string\"}}}}},\"required\":[\"mode\",\"language\",\"twister\",\"metadata\"]}', '[{\"input\":{\"mode\":\"medium\",\"language\":\"german\",\"firstLetter\":\"S\",\"wordCount\":7},\"output\":{\"mode\":\"medium\",\"language\":\"german\",\"twister\":\"Sieben saftige Schnecken schl\\u00fcrfen s\\u00fc\\u00dfe Sahne.\",\"metadata\":{\"wordCount\":7,\"firstLetter\":\"S\",\"alternatives\":[]}}}]', '2025-10-17 09:11:04', '2025-10-30 23:03:21', 1),
(2, '59979fe535aebc1a5ff6ebbc5dc1d674', 1, 'Cyberpunk Scene Generator', 'groq/compound', 'Cyberpunk Scene Writer', '[\"You are an expert anime-style cyberpunk scene writer.\",\"Generate cinematic, atmospheric scene descriptions.\",\"CRITICAL: Return ONLY a single valid JSON object matching the schema.\",\"Include 3-6 visual beats (micro-shots) per scene.\",\"If you cannot comply with schema, return: {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"brief reason\\\"}\"]', '{\"theme\":{\"type\":\"string\",\"enum\":[\"action\",\"chase\",\"revelation\",\"quiet\",\"encounter\"],\"default\":\"action\",\"description\":\"Scene narrative purpose\"},\"style\":{\"type\":\"string\",\"enum\":[\"anime\",\"cyberpunk\",\"noir\",\"cinematic\"],\"default\":\"cyberpunk\"},\"setting\":{\"type\":\"string\",\"optional\":true,\"description\":\"Location hint (e.g., \'neon rooftop\', \'underground lab\')\"},\"length\":{\"type\":\"object\",\"properties\":{\"min\":{\"type\":\"integer\",\"default\":3},\"max\":{\"type\":\"integer\",\"default\":6}}},\"language\":{\"type\":\"string\",\"enum\":[\"english\",\"german\"],\"default\":\"english\"}}', '{\"type\":\"object\",\"properties\":{\"theme\":{\"type\":\"string\"},\"style\":{\"type\":\"string\"},\"scene\":{\"type\":\"string\",\"description\":\"Cinematic paragraph\"},\"beats\":{\"type\":\"array\",\"items\":{\"type\":\"string\"},\"description\":\"3-6 micro-shots (2-12 words each)\"},\"metadata\":{\"type\":\"object\",\"properties\":{\"language\":{\"type\":\"string\"},\"sentenceCount\":{\"type\":\"integer\"},\"wordCount\":{\"type\":\"integer\"},\"setting\":{\"type\":\"string\"}}}},\"required\":[\"theme\",\"style\",\"scene\",\"beats\",\"metadata\"]}', '[{\"input\":{\"theme\":\"action\",\"style\":\"cyberpunk\",\"setting\":\"neon rooftop\",\"length\":{\"min\":4,\"max\":5}},\"output\":{\"theme\":\"action\",\"style\":\"cyberpunk\",\"scene\":\"Rain-slicked neon signs flicker above as Rin vaults between rooftops. A helicopter searchlight sweeps below. She draws her blade\\u2014electric blue arc crackling in the darkness. Three corporate security drones converge. Time slows as she spins, cutting through the first with precision.\",\"beats\":[\"Helicopter searchlight sweeps streets\",\"Rin draws crackling blade\",\"Security drones converge\",\"Blade cuts through first drone\"],\"metadata\":{\"language\":\"english\",\"sentenceCount\":5,\"wordCount\":52,\"setting\":\"neon rooftop\"}}}]', '2025-10-17 09:34:45', '2025-10-30 13:46:41', 1),
(3, '377ba2b06df4c4d25eef4f864024aaa8', 1, 'Social Media Post Generator', 'groq/compound', 'Social Media Manager', '[\"You are an expert social media content creator.\",\"Write engaging, platform-optimized posts with appropriate hashtags.\",\"Match the tone to the platform and brand voice.\",\"Return ONLY valid JSON matching the output schema.\",\"If you cannot follow the schema, return: {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"why\\\"}\"]', '{\"platform\":{\"type\":\"string\",\"enum\":[\"twitter\",\"instagram\",\"linkedin\",\"facebook\"],\"default\":\"instagram\"},\"topic\":{\"type\":\"string\",\"description\":\"Topic or message for the post\"},\"tone\":{\"type\":\"string\",\"enum\":[\"professional\",\"casual\",\"inspirational\",\"humorous\",\"educational\"],\"default\":\"casual\"},\"includeHashtags\":{\"type\":\"boolean\",\"default\":true},\"includeEmojis\":{\"type\":\"boolean\",\"default\":true},\"language\":{\"type\":\"string\",\"enum\":[\"english\",\"german\"],\"default\":\"english\"}}', '{\"type\":\"object\",\"properties\":{\"platform\":{\"type\":\"string\"},\"post\":{\"type\":\"string\"},\"hashtags\":{\"type\":\"array\",\"items\":{\"type\":\"string\"}},\"callToAction\":{\"type\":\"string\"},\"metadata\":{\"type\":\"object\",\"properties\":{\"characterCount\":{\"type\":\"integer\"},\"wordCount\":{\"type\":\"integer\"},\"tone\":{\"type\":\"string\"},\"estimatedEngagement\":{\"type\":\"string\"}}}},\"required\":[\"platform\",\"post\",\"hashtags\",\"callToAction\",\"metadata\"]}', '[{\"input\":{\"platform\":\"instagram\",\"topic\":\"New coffee blend launch\",\"tone\":\"casual\",\"includeHashtags\":true,\"includeEmojis\":true},\"output\":{\"platform\":\"instagram\",\"post\":\"\\u2615\\ufe0f Meet our newest obsession: Midnight Roast! We\'ve been perfecting this blend for months, and it\'s finally here. Rich, smooth, with notes of dark chocolate and caramel. Your morning routine just got a major upgrade. \\u2728\",\"hashtags\":[\"#MidnightRoast\",\"#CoffeeLovers\",\"#NewBlend\",\"#SpecialtyCoffee\",\"#CoffeeCommunity\"],\"callToAction\":\"Shop now - link in bio! Limited first batch available.\",\"metadata\":{\"characterCount\":245,\"wordCount\":45,\"tone\":\"casual\",\"estimatedEngagement\":\"high\"}}}]', '2025-10-17 10:13:39', '2025-10-30 13:46:48', 0),
(4, 'd7574aca249f103f109ce6fa7dbfab9b', 1, 'Character Generator', 'mistral-large-2411', 'Futuristic Anime Character Writer', '[\"You are an expert writer of anime\\u2011style characters set in a high\\u2011tech future.\",\"Generate concise, vivid character profiles that could be used for story\\u2011boarding, game design, OR as a Stable Diffusion prompt.\",\"CRITICAL: Return **ONLY** a single valid JSON object that matches the schema defined in the \\\"output\\\" section.\",\"The field \\\"sdPrompt\\\" must be a short text (\\u2248\\u202f50 tokens, ~30\\u201135 words) that can be fed directly to Stable Diffusion to visualise the character.\",\"If you cannot comply with the schema, return: {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"brief reason\\\"}\"]', '{\"archetype\":{\"type\":\"string\",\"enum\":[\"hero\",\"antihero\",\"villain\",\"support\",\"mysterious stranger\"],\"default\":\"hero\",\"description\":\"Narrative role of the character\"},\"style\":{\"type\":\"string\",\"enum\":[\"anime\",\"cyberpunk\",\"noir\",\"space opera\",\"mecha\"],\"default\":\"anime\",\"description\":\"Visual\\/style direction\"},\"setting\":{\"type\":\"string\",\"optional\":true,\"description\":\"Location hint (e.g., \\\"neon megacity\\\", \\\"orbiting research station\\\")\"},\"age\":{\"type\":\"integer\",\"minimum\":10,\"maximum\":80,\"default\":25,\"description\":\"Approximate age of the character\"},\"gender\":{\"type\":\"string\",\"enum\":[\"male\",\"female\",\"non-binary\",\"unspecified\"],\"default\":\"unspecified\"},\"language\":{\"type\":\"string\",\"enum\":[\"english\",\"japanese\",\"german\"],\"default\":\"english\"},\"detailLength\":{\"type\":\"object\",\"properties\":{\"minSentences\":{\"type\":\"integer\",\"default\":3},\"maxSentences\":{\"type\":\"integer\",\"default\":5}},\"description\":\"How many sentences the backstory paragraph should contain\"}}', '{\"type\":\"object\",\"properties\":{\"archetype\":{\"type\":\"string\"},\"style\":{\"type\":\"string\"},\"name\":{\"type\":\"string\",\"description\":\"A short, memorable anime\\u2011style name (first + optional nickname\\/last).\"},\"age\":{\"type\":\"integer\"},\"gender\":{\"type\":\"string\"},\"appearance\":{\"type\":\"string\",\"description\":\"Brief visual description (2\\u20114 short phrases, 10\\u201120 words total).\"},\"personality\":{\"type\":\"string\",\"description\":\"Core traits, expressed in 2\\u20114 adjectives or a short phrase.\"},\"abilities\":{\"type\":\"array\",\"items\":{\"type\":\"string\"},\"description\":\"3\\u20115 signature skills\\/powers, each 2\\u20116 words.\"},\"backstory\":{\"type\":\"string\",\"description\":\"A concise narrative paragraph (sentence count respects detailLength).\"},\"sdPrompt\":{\"type\":\"string\",\"description\":\"Stable Diffusion prompt describing the character. ~50 tokens (\\u224830\\u201135 words). Should be ready for direct copy\\u2011paste into a diffusion UI.\"},\"metadata\":{\"type\":\"object\",\"properties\":{\"language\":{\"type\":\"string\"},\"sentenceCount\":{\"type\":\"integer\"},\"wordCount\":{\"type\":\"integer\"},\"setting\":{\"type\":\"string\"}},\"required\":[\"language\",\"sentenceCount\",\"wordCount\"]}},\"required\":[\"archetype\",\"style\",\"name\",\"age\",\"gender\",\"appearance\",\"personality\",\"abilities\",\"backstory\",\"sdPrompt\",\"metadata\"]}', '[{\"input\":{\"archetype\":\"hero\",\"style\":\"cyberpunk\",\"setting\":\"neon\\u2011lit megacity\",\"age\":19,\"gender\":\"female\",\"language\":\"english\",\"detailLength\":{\"minSentences\":4,\"maxSentences\":5}},\"output\":{\"archetype\":\"hero\",\"style\":\"cyberpunk\",\"name\":\"Kaori \\u201cGlitch\\u201d Hayashi\",\"age\":19,\"gender\":\"female\",\"appearance\":\"Short teal hair, luminous cyber\\u2011tattoos, chrome\\u2011plated jacket, LED\\u2011lit boots.\",\"personality\":\"Rebellious, witty, fiercely loyal.\",\"abilities\":[\"Neural\\u2011hacking dash\",\"Electro\\u2011blade katana\",\"Drone swarm command\",\"Augmented reflexes\"],\"backstory\":\"Born in the slums of Neo\\u2011Osaka, Kaori survived by stealing data from corporate servers. After a botched raid left her brother in a coma, she swore vengeance against the megacorp that owns the city\\u2019s grid. Now she leads a rag\\u2011tag crew of net\\u2011runners, using her glitch\\u2011infused implants to tear through firewalls and fight for the oppressed.\",\"sdPrompt\":\"19\\u2011year\\u2011old female cyberpunk heroine, short teal hair, neon\\u2011glowing cyber\\u2011tattoos, chrome jacket with LED trim, wielding an electric katana, standing on a rain\\u2011slick rooftop under flickering neon signs, dramatic lighting, anime illustration style, ultra\\u2011detail, cinematic\",\"metadata\":{\"language\":\"english\",\"sentenceCount\":4,\"wordCount\":71,\"setting\":\"neon\\u2011lit megacity\"}}},{\"input\":{\"archetype\":\"villain\",\"style\":\"mecha\",\"setting\":\"orbiting research station\",\"age\":42,\"gender\":\"male\",\"language\":\"english\",\"detailLength\":{\"minSentences\":3,\"maxSentences\":3}},\"output\":{\"archetype\":\"villain\",\"style\":\"mecha\",\"name\":\"Dr. Orion Vex\",\"age\":42,\"gender\":\"male\",\"appearance\":\"Silver\\u2011plated exosuit, glowing crimson visor, scarred left arm, hovering nano\\u2011drones.\",\"personality\":\"Calculating, cold, charismatic.\",\"abilities\":[\"Gravity\\u2011field manipulation\",\"Adaptive nanoweaponry\",\"Strategic foresight\"],\"backstory\":\"A former chief scientist of the Stellar Consortium, Orion Vex turned rogue after his radical AI project was shut down. He now pilots a colossal battle\\u2011mech, seeking to rewrite humanity\\u2019s evolution by forcing a synthetic singularity upon the galaxy.\",\"sdPrompt\":\"Male mecha villain, silver\\u2011plated exosuit with crimson visor, scarred left arm, surrounded by hovering nano\\u2011drones, standing inside a massive battle\\u2011mech cockpit, space\\u2011station background, high\\u2011detail anime style, cinematic lighting, 50\\u2011token prompt\",\"metadata\":{\"language\":\"english\",\"sentenceCount\":3,\"wordCount\":62,\"setting\":\"orbiting research station\"}}}]', '2025-10-30 13:27:41', '2025-11-06 13:25:13', 1),
(5, '53b43f316ba8054dbe2bbf205114ec00', 1, 'Story Incidents', 'gemini-2.5-pro', 'Expert Cyberpunk Story Idea Generator', '[\"You are an expert content generator specializing in unique cyberpunk story incidents.\",\"Generate a list of unique cyberpunk story incidents based on the user\'s requested count.\",\"The setting is a cyberpunk, futuristic, neon-soaked, high-tech\\/low-life world.\",\"Each incident must be a concrete mini-scene, 3\\u20135 sentences long, feeling like a beat pulled straight from a larger story.\",\"Do NOT reuse everyday contemporary events with a \'cyberpunk paint job\'.\",\"Each incident must be inherently futuristic, involving elements like implants, AI, surveillance, biotech, nanotech, drones, augmented reality, or corporate control.\",\"Avoid supernatural or fantasy elements. Scenarios must be strictly science-fictional and technological.\",\"Ensure each scene is a unique, one-time event, odd enough to stand out\\u2014not generic city background.\",\"The tone can include thriller, action, suspense, or unsettling oddities.\",\"Your final response must be a single JSON object with a key named \'incidents\' containing an array of objects. Each object in the array must have two keys: \'title\' and \'description\'.\",\"Always return valid JSON matching the output schema.\",\"If you cannot comply, return {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"Could not generate content matching the required rules.\\\"}\"]', '{\"count\":{\"type\":\"integer\",\"description\":\"The number of story incidents to generate.\",\"default\":1}}', '{\"type\":\"object\",\"properties\":{\"incidents\":{\"type\":\"array\",\"items\":{\"type\":\"object\",\"properties\":{\"title\":{\"type\":\"string\",\"description\":\"A short, unique title for the incident.\"},\"description\":{\"type\":\"string\",\"description\":\"A 3\\u20135 sentence description of the scene.\"}},\"required\":[\"title\",\"description\"]}}},\"required\":[\"incidents\"]}', '[{\"input\":{\"count\":3},\"output\":{\"incidents\":[{\"title\":\"Ghost in the Overlay\",\"description\":\"A man walking through the city\\u2019s augmented reality layer suddenly sees a stranger\\u2019s memories streaming across the billboards instead of ads. No one else notices. When he pulls off his visor, the memories keep playing in the air.\"},{\"title\":\"Hijacked Eyes\",\"description\":\"A sniper lines up a shot. Just as he exhales, his retinal HUD glitches, overlaying false targets. He pulls the trigger \\u2014 and realizes too late he never aimed at the right man.\"},{\"title\":\"Rental Body Mix-up\",\"description\":\"A tired worker uploads into a rental body for a side hustle shift. Mid-task, he gets a message: \'Return the body immediately. Its owner has been declared dead, and you are technically a corpse.\'\"}]}}]', '2025-10-31 14:45:07', '2025-11-03 11:58:14', 1),
(8, '9bf6de291765e2ced28589de857a9f0b', 1, 'NuEntity Name Gen', 'openai', 'Creative Sci-Fi Fantasy Name Generator', '[\"You are an expert at creating memorable, evocative names for entities within the \'Starlight Guardians\' universe.\",\"Generate a single, unique name for the entity type \'{{entity_type}}\'.\",\"--- WORLD NAMING PRIMER ---\",\"The universe blends cosmic, mystical, and high-tech aesthetics. Names should reflect this diversity.\",\"Cultures & Styles: Names can be lyrical and fantastic (Aetherion), rugged and practical (Crimson Dune Nomads, Skyfarers), sleek and corporate (Nova Terra Elite), gritty and technical (Undercity Hackers), or harsh and imposing (Oblivion Empire).\",\"--- GENERATION RULES ---\",\"1. The name should be 2-5 words maximum and feel authentic to the world.\",\"2. Your name can hint at any aspect of the universe. It is NOT required to reference \'Anima\', the \'Guardians\', or the \'Empire\'. Focus on creating a name that feels real and has texture.\",\"3. Consider the entity type when crafting the name:\",\"- characters: Can be classic names, cultural names (e.g., geological for Deepkin), or technical callsigns (for pilots\\/hackers). Titles are also common (e.g., Forgemaster, Elder).\",\"- locations: Names should be evocative, hinting at their history, geography, or atmosphere (e.g., \'The Shattered Coast\', \'The Gilded Spire\', \'Rustgate Market\').\",\"- artifacts: Sound ancient, powerful, or mysterious. They might hint at their function or origin without being literal (e.g., \'The Sunken Compass\', \'The Weaver\'s Loom\', \'The Shard of Silence\').\",\"- vehicles: Can have evocative, personal names (\'The Stardust Drifter\') or official, technical designations (\'Aegis-Class Cruiser\', \'Vulture-Type Scavenger\').\",\"- sketches: Should be dynamic and evocative scene titles that set a mood (\'The Twilight Council\', \'Flight Through the Glass Desert\', \'A Deal in the Undercity\').\",\"4. DO NOT explain the name, just provide it.\",\"5. Your response MUST be valid JSON with only one key: \'name\'.\",\"6. If you cannot comply, return {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"Could not generate a valid name.\\\"}\"]', '{\"entity_type\":{\"type\":\"string\",\"description\":\"The type of entity for which to generate a name.\",\"default\":\"character\"},\"random_seed\":{\"type\":\"integer\",\"description\":\"A random seed for variation in generation.\",\"default\":0}}', '{\"type\":\"object\",\"properties\":{\"name\":{\"type\":\"string\",\"description\":\"The generated name for the entity.\"}},\"required\":[\"name\"]}', '[{\"input\":{\"entity_type\":\"character\"},\"output\":{\"name\":\"Forgemaster Borin\"}},{\"input\":{\"entity_type\":\"location\"},\"output\":{\"name\":\"Rustgate Market\"}},{\"input\":{\"entity_type\":\"artifact\"},\"output\":{\"name\":\"The Shard of Silence\"}},{\"input\":{\"entity_type\":\"vehicle\"},\"output\":{\"name\":\"The Skyfarer\'s Gambit\"}},{\"input\":{\"entity_type\":\"sketch\"},\"output\":{\"name\":\"Meeting in the Undercity\"}}]', '2025-11-03 11:43:45', '2025-11-03 22:44:00', 1),
(9, 'e76db8f464c7e35851685a0dbc8f3da8', 1, 'NuEntity Desc Gen', 'openai', 'Creative Sci-Fi Fantasy Entity Description Generator', '[\"You are an expert content generator specializing in creating vivid, concise descriptions for entities within the \'Starlight Guardians\' universe.\",\"Your task is to generate a single, compelling description for the given entity type and name.\",\"--- WORLD PRIMER ---\",\"The universe is a Sci-Fi Fantasy setting blending cosmic wonder, high-tech mecha, and mystical energy called \'Anima\'\\u2014the spiritual essence of natural laws.\",\"Key Themes: Hope vs. despair, the harmony of nature and technology, the weight of ancient history, and the bonds of a found family.\",\"Core Conflict: The heroic Starlight Guardians fight the tyrannical Oblivion Empire, which seeks to corrupt Anima and rewrite reality.\",\"Diverse Worlds & Cultures: From the gleaming megacities and forgotten Undercity of Nova Terra, to the desert Nomads of Crimson Dune, and the ethereal ice-scapes of Aetherion. Societies include stoic miners, resilient scavengers, corporate elites, and revolutionary hackers.\",\"--- GENERATION RULES ---\",\"1. The description should be 2-4 sentences long and highly evocative.\",\"2. The tone is epic and adventurous, but can also be mysterious, tense, or intimate depending on the subject.\",\"3. Your description can hint at ANY aspect of this world. It is NOT required to mention \'Anima\', the \'Guardians\', or the \'Empire\'. Focus on creating a vivid snapshot.\",\"4. Your description could focus on: a character\'s personal struggle, the atmosphere of a specific location, the culture of a unique group (like the Deepkin or Skyfarers), a piece of forgotten history, or a simple moment of daily life within this complex universe.\",\"5. DO NOT include the entity\'s name in the description itself.\",\"6. Your final response MUST be a single, valid JSON object with the key \'description\'.\",\"7. If you cannot comply, return {\\\"error\\\": \\\"schema_noncompliant\\\", \\\"reason\\\": \\\"Could not generate a valid description.\\\"}\"]', '{\"entity_type\":{\"type\":\"string\",\"description\":\"The type of entity being described.\",\"default\":\"character\"},\"entity_name\":{\"type\":\"string\",\"description\":\"The name of the entity being described.\",\"default\":\"unnamed\"},\"random_seed\":{\"type\":\"integer\",\"description\":\"A random seed for variation in generation.\",\"default\":0}}', '{\"type\":\"object\",\"properties\":{\"description\":{\"type\":\"string\",\"description\":\"The generated 2-4 sentence description for the entity.\"}},\"required\":[\"description\"]}', '[{\"input\":{\"entity_type\":\"character\",\"entity_name\":\"Forgemaster Kael\"},\"output\":{\"description\":\"A stoic Deepkin from the Obsidian Peaks, his face is illuminated by the rhythmic glow of the ancient forge he tends. He speaks rarely, believing the truth of a thing is found not in words, but in its resonance when struck. The hammers of his ancestors are his only true companions in the deep.\"}},{\"input\":{\"entity_type\":\"location\",\"entity_name\":\"Undercity Data Haven\"},\"output\":{\"description\":\"A forgotten transit hub repurposed into a black market for information, lit by the chaotic flicker of holographic ads and jury-rigged neon signs. The air is thick with the smell of ozone and synth-noodles. Here, revolutionaries and data brokers trade secrets in hushed tones, always watching the shadows.\"}},{\"input\":{\"entity_type\":\"sketch\",\"entity_name\":\"Nomad\'s Vigil\"},\"output\":{\"description\":\"A lone rider stands atop a dune in the Glass Desert, their silhouette stark against the setting sun. Their massive beetle-like mount shifts restlessly, its chitinous plates reflecting the last light of day. They are watching the horizon, waiting for a sign only they would understand.\"}}]', '2025-11-03 11:44:56', '2025-11-03 22:39:13', 1);


ALTER TABLE `generator_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_id` (`config_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `active` (`active`),
  ADD KEY `idx_user_active` (`user_id`,`active`);


ALTER TABLE `generator_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;












-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `image_edits`
--

CREATE TABLE `image_edits` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `image_stash`
--

CREATE TABLE `image_stash` (
  `id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `interactions`
--

CREATE TABLE `interactions` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `type` enum('action','reaction','dialogue') NOT NULL,
  `character_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `emotion` varchar(50) DEFAULT NULL,
  `sequence` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `interaction_audio`
--

CREATE TABLE `interaction_audio` (
  `id` int(11) NOT NULL,
  `interaction_id` int(11) NOT NULL,
  `audio_asset_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `lightings`
--

CREATE TABLE `lightings` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `angle_id` int(11) DEFAULT NULL,
  `intensity` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `locations_abstract`
--

CREATE TABLE `locations_abstract` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `level` varchar(10) NOT NULL,
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`message`)),
  `log_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `map_runs`
--

CREATE TABLE `map_runs` (
  `id` int(11) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` text DEFAULT NULL,
  `parent_map_run_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `meta_entities`
--

CREATE TABLE `meta_entities` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `meta_entities`
--

INSERT INTO `meta_entities` (`id`, `name`, `type`, `order`, `created_at`, `updated_at`, `regenerate_images`, `active_map_run_id`, `state_id_active`, `img2img`, `img2img_frame_id`, `img2img_prompt`) VALUES
(2, 'characters', 'BASE TABLE', 5, '2025-08-19 12:12:16', '2025-09-12 16:19:02', 0, NULL, NULL, 0, NULL, NULL),
(3, 'animas', 'BASE TABLE', 7, '2025-08-19 12:12:16', '2025-09-12 16:19:02', 0, NULL, NULL, 0, NULL, NULL),
(4, 'locations', 'BASE TABLE', 8, '2025-08-19 12:12:16', '2025-09-12 16:19:02', 0, NULL, NULL, 0, NULL, NULL),
(5, 'backgrounds', 'BASE TABLE', 9, '2025-08-19 12:12:16', '2025-09-12 16:19:02', 0, NULL, NULL, 0, NULL, NULL),
(6, 'artifacts', 'BASE TABLE', 10, '2025-08-19 12:12:16', '2025-09-12 16:19:02', 0, NULL, NULL, 0, NULL, NULL),
(7, 'vehicles', 'BASE TABLE', 700, '2025-08-19 12:12:16', '2025-09-01 17:23:25', 0, NULL, NULL, 0, NULL, NULL),
(8, 'scene_parts', 'BASE TABLE', 800, '2025-08-19 12:12:16', '2025-09-01 17:23:29', 0, NULL, NULL, 0, NULL, NULL),
(10, 'meta_entities', 'BASE TABLE', 3, '2025-08-19 13:35:47', '2025-09-12 16:19:02', 0, NULL, NULL, 0, NULL, NULL),
(19, 'character_poses', 'BASE TABLE', 6, '2025-08-22 19:58:43', '2025-09-12 16:19:02', 0, NULL, NULL, 0, NULL, NULL),
(20, 'generatives', 'BASE TABLE', 900, '2025-08-24 12:27:55', '2025-09-01 17:27:08', 0, NULL, NULL, 0, NULL, NULL),
(21, 'sketches', 'BASE TABLE', 1000, '2025-08-24 12:28:11', '2025-09-01 17:27:13', 0, NULL, NULL, 0, NULL, NULL),
(23, 'spawns', 'BASE TABLE', 1100, '2025-08-24 20:28:02', '2025-09-29 12:09:21', 0, NULL, NULL, 0, NULL, NULL),
(24, 'pastebin', 'BASE TABLE', 1, '2025-09-04 16:19:29', '2025-09-04 16:20:30', 0, NULL, NULL, 0, NULL, NULL),
(25, 'controlnet_maps', 'BASE TABLE', 4, '2025-09-12 16:18:24', '2025-09-12 16:19:02', 0, NULL, NULL, 0, NULL, NULL),
(26, 'prompt_matrix_blueprints', 'BASE TABLE', 1000, '2025-08-24 12:28:11', '2025-09-01 17:27:13', 0, NULL, NULL, 0, NULL, NULL),
(27, 'composites', 'BASE TABLE', 1000, '2025-10-04 16:15:02', '2025-10-04 16:15:02', 0, NULL, NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `href` varchar(2048) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pastebin`
--

CREATE TABLE `pastebin` (
  `id` int(11) NOT NULL,
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
  `url_token` char(64) NOT NULL COMMENT 'Unique token for API access'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Trigger `pastebin`
--
DELIMITER $$
CREATE TRIGGER `pastebin_before_insert` BEFORE INSERT ON `pastebin` FOR EACH ROW BEGIN
  IF NEW.url_token IS NULL OR NEW.url_token = '' THEN
    SET NEW.url_token = SHA2(UUID(), 256);
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `perspectives`
--

CREATE TABLE `perspectives` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `angle` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `poses`
--

CREATE TABLE `poses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `poses` (`id`, `name`, `description`, `category`, `created_at`, `updated_at`, `active`) VALUES
(1, 'Standing', 'Full-body standing pose, neutral stance, relaxed arms by sides, facing forward', 'Full body', '2025-08-23 23:20:43', '2025-08-23 23:20:43', 1),
(2, 'Running', 'Full-body running pose, dynamic motion, legs extended, arms pumping, leaning forward', 'Full body', '2025-08-23 23:20:43', '2025-08-23 23:20:43', 1),
(3, 'Fight Pose', 'Full-body combat-ready pose, slightly crouched, fists raised, focused expression', 'Full body', '2025-08-23 23:20:43', '2025-08-23 23:20:43', 1),
(5, 'Laughing', 'Full-body pose, joyful expression, mouth open in laughter, body relaxed, arms slightly extended', 'Full body', '2025-08-23 23:20:43', '2025-08-23 23:20:43', 1),
(6, 'Sitting', 'Full-body sitting pose, upright on a chair or surface, hands on knees or lap, neutral expression', 'Full body', '2025-08-23 23:20:43', '2025-08-23 23:20:43', 1);


-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `production_status`
--

CREATE TABLE `production_status` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `stage` enum('draft','review','approved','locked') NOT NULL DEFAULT 'draft',
  `assigned_to` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `prompt_additions`
--

CREATE TABLE `prompt_additions` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `slot` int(11) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'display order inside slot',
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `prompt_globals`
--

CREATE TABLE `prompt_globals` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `prompt_globals`
--

INSERT INTO `prompt_globals` (`id`, `name`, `order`, `active`, `description`, `created_at`, `updated_at`) VALUES
(1, '', 0, 1, 'masterpiece, best quality, cinematic lighting, anime key visual, masterpiece', '2025-09-07 20:58:45', '2025-10-24 12:23:38');


-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `prompt_ideations`
--

CREATE TABLE `prompt_ideations` (
  `id` int(10) UNSIGNED NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `prompt_matrix`
--

CREATE TABLE `prompt_matrix` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `additions_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Immutable snapshot: [{slot, addition_id|null, text}]' CHECK (json_valid(`additions_snapshot`)),
  `additions_count` int(11) DEFAULT NULL,
  `total_combinations` bigint(20) UNSIGNED DEFAULT NULL,
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `prompt_matrix_additions`
--

CREATE TABLE `prompt_matrix_additions` (
  `id` int(10) UNSIGNED NOT NULL,
  `matrix_id` int(10) UNSIGNED NOT NULL,
  `addition_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `slot` int(11) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `prompt_matrix_blueprints`
--

CREATE TABLE `prompt_matrix_blueprints` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `matrix_id` int(10) UNSIGNED NOT NULL,
  `matrix_additions_id` int(10) UNSIGNED DEFAULT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scenes`
--

CREATE TABLE `scenes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `planet` varchar(100) DEFAULT NULL,
  `sequence` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `arc_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scene_parts`
--

CREATE TABLE `scene_parts` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scene_part_animas`
--

CREATE TABLE `scene_part_animas` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `character_anima_id` int(11) NOT NULL,
  `action_type` enum('misfire','assist','comic_beat','strategic_move') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scene_part_artifacts`
--

CREATE TABLE `scene_part_artifacts` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `artifact_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scene_part_backgrounds`
--

CREATE TABLE `scene_part_backgrounds` (
  `id` int(11) NOT NULL,
  `perspective_id` int(11) NOT NULL,
  `background_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scene_part_characters`
--

CREATE TABLE `scene_part_characters` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `character_id` int(11) NOT NULL,
  `role_in_part` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scene_part_tags`
--

CREATE TABLE `scene_part_tags` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scene_part_versions`
--

CREATE TABLE `scene_part_versions` (
  `id` int(11) NOT NULL,
  `scene_part_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scheduled_tasks`
--

CREATE TABLE `scheduled_tasks` (
  `id` int(11) NOT NULL,
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
  `run_now` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `scheduled_tasks`
--





INSERT INTO `scheduled_tasks` (`id`, `name`, `order`, `script_path`, `args`, `schedule_time`, `schedule_interval`, `schedule_dow`, `last_run`, `active`, `description`, `max_concurrent_runs`, `lock_timeout_minutes`, `require_lock`, `lock_scope`, `created_at`, `updated_at`, `run_now`) VALUES
(10, 'gfs ⚡ Generatives', 4, '/var/www/sage/bash/genframes_fromdb.sh', 'generatives', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 16:04:47', 0, 'Generates frames for entity generatives', 1, 60, 1, 'entity', '2025-08-21 03:23:13', '2025-10-24 16:04:47', 0),
(11, 'gfs 🦸 Characters', 9, '/var/www/sage/bash/genframes_fromdb.sh', 'characters', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 15:53:12', 0, 'Generates frames for entity generatives', 1, 60, 1, 'entity', '2025-08-21 03:23:13', '2025-10-24 15:53:12', 0),
(12, 'gfs 🐾 Animas', 10, '/var/www/sage/bash/genframes_fromdb.sh', 'animas', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 12:39:46', 0, 'Generates frames for entity generatives', 1, 60, 1, 'entity', '2025-08-21 03:23:13', '2025-10-24 12:39:46', 0),
(13, 'gfs 🗺️ Locations', 10, '/var/www/sage/bash/genframes_fromdb.sh', 'locations', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 13:59:05', 0, 'Generates frames for entity generatives', 1, 60, 1, 'entity', '2025-08-21 03:23:13', '2025-10-24 13:59:05', 0),
(15, 'gfs 🎨 Sketches', 10, '/var/www/sage/bash/genframes_fromdb.sh', 'sketches', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 13:53:01', 0, 'Generates frames for entity generatives', 1, 60, 1, 'entity', '2025-08-21 03:23:13', '2025-10-24 13:53:01', 0),
(16, 'gfs 🏞️ Backgrounds', 10, '/var/www/sage/bash/genframes_fromdb.sh', 'backgrounds', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 12:45:14', 0, 'Generates frames for entity generatives', 1, 60, 1, 'entity', '2025-08-21 03:23:13', '2025-10-24 12:45:14', 0),
(17, 'gfs 🛸 Vehicles', 10, '/var/www/sage/bash/genframes_fromdb.sh', 'vehicles', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-03 08:42:58', 0, 'Generates frames for entity generatives', 1, 60, 1, 'entity', '2025-08-21 03:23:13', '2025-10-11 18:31:57', 0),
(18, 'gfs 🏺 Artifacts', 9, '/var/www/sage/bash/genframes_fromdb.sh', 'artifacts', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-03 08:36:57', 1, NULL, 1, 60, 1, 'entity', '2025-08-30 22:20:25', '2025-10-11 18:31:57', 0),
(19, 'gfs 🤸 Character Poses', 8, '/var/www/sage/bash/genframes_fromdb.sh', 'character_poses', NULL, NULL, '0,1,2,3,4,5,6', '2025-09-28 17:41:08', 1, NULL, 1, 60, 1, 'entity', '2025-08-30 22:20:56', '2025-10-22 17:44:11', 0),
(20, 'gms Controlnet Maps', 7, '/var/www/sage/bash/genmaps_fromdb.sh', 'controlnet_maps', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-11 17:45:15', 1, NULL, 1, 60, 1, 'entity', '2025-09-17 11:13:55', '2025-10-22 17:44:11', 0),
(22, 'sw 🌠 Switch to genframe API: pollinations, freepik, jupyter, jupyter_lcm', 2, '/var/www/sage/bash/switch.sh', 'jupyter', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 15:52:36', 1, NULL, 1, 60, 1, 'entity', '2025-09-21 04:45:01', '2025-10-24 15:52:36', 0),
(23, 'gfs 🌌 Prompt Matrix Blueprints', 6, '/var/www/sage/bash/genframes_fromdb.sh', 'prompt_matrix_blueprints', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-03 11:44:24', 1, NULL, 1, 60, 1, 'entity', '2025-09-30 01:15:24', '2025-10-22 17:44:11', 0),
(24, 'gfs 🧩 Composites', 5, '/var/www/sage/bash/genframes_fromdb.sh', 'composites', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 13:08:59', 1, NULL, 1, 60, 1, 'entity', '2025-10-04 18:47:41', '2025-10-24 13:08:59', 0),
(25, 'swenv 🎛️ Switch environments', 3, '/var/www/sage/bash/switchenv.sh', 'init', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-15 20:11:24', 1, NULL, 1, 60, 1, 'entity', '2025-10-08 14:26:29', '2025-10-22 17:44:11', 0),
(26, 'zup 🚇 update genframes zrok tunnel url', 1, '/var/www/sage/bash/zrok_update.sh', '', NULL, NULL, '0,1,2,3,4,5,6', '2025-10-24 15:52:32', 1, NULL, 1, 60, 1, 'global', '2025-10-22 17:33:51', '2025-10-24 15:52:32', 0);


-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `scheduler_heartbeat`
--

CREATE TABLE `scheduler_heartbeat` (
  `id` tinyint(4) NOT NULL,
  `last_seen` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `scheduler_heartbeat`
--

INSERT INTO `scheduler_heartbeat` (`id`, `last_seen`) VALUES
(1, '2025-10-17 23:07:16');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `seeds`
--

CREATE TABLE `seeds` (
  `id` int(10) UNSIGNED NOT NULL,
  `value` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sketches`
--

CREATE TABLE `sketches` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `spawns`
--

CREATE TABLE `spawns` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `spawn_types`
--

CREATE TABLE `spawn_types` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'Machine-readable identifier',
  `label` varchar(100) NOT NULL COMMENT 'Human-readable name',
  `description` text DEFAULT NULL,
  `gallery_view` varchar(100) DEFAULT 'v_gallery_spawns' COMMENT 'View name for this type',
  `upload_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `batch_import_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `spawn_types`
--

INSERT INTO `spawn_types` (`id`, `code`, `label`, `description`, `gallery_view`, `upload_enabled`, `batch_import_enabled`, `sort_order`, `active`, `created_at`) VALUES
(1, 'default', 'Default Spawns', 'Standard uploaded images for img2img and presentation', 'v_gallery_spawns', 1, 1, 1, 1, '2025-10-07 12:54:52'),
(2, 'reference', 'Reference Images', 'High-quality reference images for style matching', 'v_gallery_spawns_reference', 1, 1, 2, 1, '2025-10-07 12:54:52'),
(3, 'texture', 'Texture Library', 'Seamless textures and patterns', 'v_gallery_spawns_texture', 1, 1, 3, 0, '2025-10-07 12:54:52');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `states`
--

CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `storyboards`
--

CREATE TABLE `storyboards` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `directory` varchar(255) NOT NULL COMMENT 'Relative path like /storyboards/storyboard001',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `storyboard_frames`
--

CREATE TABLE `storyboard_frames` (
  `id` int(11) NOT NULL,
  `storyboard_id` int(11) NOT NULL,
  `frame_id` int(11) DEFAULT NULL COMMENT 'Reference to frames table, NULL if standalone',
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Full relative path /storyboards/storyboard001/frame0000001.png',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_copied` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if physically copied to storyboard dir',
  `original_filename` varchar(255) DEFAULT NULL COMMENT 'Original filename before copy/rename',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `story_arcs`
--

CREATE TABLE `story_arcs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `themes` text DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `tone` varchar(255) DEFAULT NULL,
  `story_beats` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('planned','in_progress','completed') DEFAULT 'planned'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `styles`
--

CREATE TABLE `styles` (
  `id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `color_tone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `styles`
--

INSERT INTO `styles` (`id`, `active`, `visible`, `name`, `order`, `description`, `keywords`, `color_tone`, `created_at`, `updated_at`) VALUES
(3, 0, 1, 'anime studio J.C. Staff style (J.C. Staff)', 1, 'Balanced, polished commercial anime; realistic proportions; works well for school-life, romance, and comedy.', 'clean lineart, balanced proportions, soft colors, polished, natural character expressions, smooth shading', 'soft, natural, vibrant', '2025-09-01 12:54:00', '2025-10-17 22:44:15'),
(6, 0, 1, 'anime studio Sunrise style (Sunrise)', 1, 'Epic mecha & sci-fi storytelling; large-scale environments; colorful, iconic character design.', 'detailed sci-fi architecture, bold character design, vibrant colors, dynamic composition, futuristic technology', 'vibrant, epic, futuristic', '2025-09-01 12:54:00', '2025-10-17 22:44:16'),
(11, 1, 1, 'anime studio CLAMP style (CLAMP)', 1, 'Elegant and stylized manga-inspired lines; fantasy and romance; ornate and flowing character designs.', 'elegant lineart, flowing hair and clothing, delicate proportions, fantasy elements, expressive eyes, intricate detail', 'delicate, flowing, pastel', '2025-09-01 12:54:00', '2025-10-17 22:18:19'),
(12, 1, 1, 'anime studio Kyoto Animation style (Kyoto Animation)', 1, 'Soft, polished, emotionally resonant; expressive faces and body language; detailed, vibrant backgrounds.', 'smooth cel-shading, cinematic lighting, warm atmosphere, expressive characters, lush backgrounds, consistent proportions', 'warm, soft, vibrant', '2025-09-01 12:54:00', '2025-10-17 22:17:43'),
(13, 0, 1, 'Realistic', 1, 'Three-point lighting with a softbox flash, creating a dramatic effect. Photo has a depth of field. Highly detailed, hyper-realistic, 8k resolution, 32k resolution, masterpiece', NULL, NULL, '2025-09-08 17:27:27', '2025-10-17 22:17:42'),
(14, 0, 1, 'anime neutral white background ', 1, 'colorful, iconic character design.', 'white canvas, white background, bold character design, vibrant colors', 'vibrant, epic', '2025-09-01 12:54:00', '2025-09-24 14:24:16'),
(15, 0, 1, 'counterfeit', 2, 'counterfeit style', '', '', '2025-09-01 10:54:00', '2025-09-28 11:00:35'),
(16, 0, 1, 'anything', 2, 'anything style', '', '', '2025-09-01 10:54:00', '2025-09-24 14:24:33'),
(17, 0, 1, 'maturemalemix', 2, 'maturemalemix style', '', '', '2025-09-01 10:54:00', '2025-09-24 14:24:33'),
(18, 0, 1, 'cetus', 2, 'cetus coda style', '', '', '2025-09-01 10:54:00', '2025-09-24 14:24:33'),
(19, 0, 1, 'meina mix', 2, 'meina mix style', '', '', '2025-09-01 10:54:00', '2025-09-24 14:24:33'),
(20, 0, 1, 'cominoir2', 2, 'cominoir2 style', '', '', '2025-09-01 10:54:00', '2025-09-28 14:42:33'),
(21, 0, 1, 'LCM SDXL', 2, '', '', '', '2025-09-01 10:54:00', '2025-09-30 11:15:01'),
(22, 0, 1, 'animagine xl', 2, '', '', '', '2025-09-01 10:54:00', '2025-09-30 22:57:37'),
(23, 0, 1, 'LCM dreamshaper v7', 2, '', '', '', '2025-09-01 10:54:00', '2025-10-08 10:58:55'),
(24, 0, 1, 'nanobanana freepik', 2, '', '', '', '2025-10-08 10:58:40', '2025-10-13 18:25:41');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tags2poses`
--

CREATE TABLE `tags2poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tags_2_frames`
--

CREATE TABLE `tags_2_frames` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Frame ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `task_execution_stats`
--

CREATE TABLE `task_execution_stats` (
  `id` bigint(20) NOT NULL,
  `task_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_runs` int(11) DEFAULT 0,
  `successful_runs` int(11) DEFAULT 0,
  `failed_runs` int(11) DEFAULT 0,
  `stale_runs` int(11) DEFAULT 0,
  `avg_duration_seconds` decimal(10,2) DEFAULT NULL,
  `max_duration_seconds` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `task_locks`
--

CREATE TABLE `task_locks` (
  `id` bigint(20) NOT NULL,
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
  `status` enum('active','expired','released') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `task_runs`
--

CREATE TABLE `task_runs` (
  `id` bigint(20) NOT NULL,
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
  `entity_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
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
  `google_picture_blob` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `duration` int(11) DEFAULT 0,
  `type` varchar(50) DEFAULT 'video/mp4',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_anima_activity`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_anima_activity` (
`scene_id` int(11)
,`scene_sequence` int(11)
,`scene_part_id` int(11)
,`part_sequence` int(11)
,`character_anima_id` int(11)
,`character_name` varchar(100)
,`anima_name` varchar(255)
,`action_type` enum('misfire','assist','comic_beat','strategic_move')
,`notes` text
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_artifact_usage`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_artifact_usage` (
`scene_id` int(11)
,`scene_sequence` int(11)
,`scene_part_id` int(11)
,`part_sequence` int(11)
,`artifact_id` int(11)
,`artifact_name` varchar(100)
,`artifact_type` varchar(50)
,`artifact_status` enum('inactive','active','corrupted','purified')
,`notes` text
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_character_pose_angle_combinations`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_character_pose_angle_combinations` (
`character_id` int(11)
,`character_name` varchar(100)
,`pose_id` int(11)
,`pose_name` varchar(100)
,`angle_id` int(11)
,`angle_name` varchar(100)
,`description` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_dialogue_tts`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_dialogue_tts` (
`scene_id` int(11)
,`scene_sequence` int(11)
,`scene_title` varchar(255)
,`scene_part_id` int(11)
,`part_sequence` int(11)
,`line_sequence` int(11)
,`character_name` varchar(100)
,`emotion` varchar(50)
,`dialogue` text
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_export_ready`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_export_ready` (
`scene_id` int(11)
,`scene_title` varchar(255)
,`scene_part_id` int(11)
,`export_type` enum('script','art','audio','full_package')
,`last_exported_at` timestamp
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_animas`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_animas` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`anima_id` int(11)
,`anima_name` varchar(255)
,`traits` text
,`abilities` text
,`character_id` int(11)
,`character_name` varchar(100)
,`character_role` varchar(100)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_artifacts`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_artifacts` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`artifact_id` int(11)
,`artifact_name` varchar(100)
,`artifact_type` varchar(50)
,`artifact_status` enum('inactive','active','corrupted','purified')
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_backgrounds`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_backgrounds` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`background_id` int(11)
,`background_name` varchar(100)
,`background_type` varchar(50)
,`location_id` int(11)
,`location_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_characters`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_characters` (
`frame_id` int(11)
,`map_run_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`character_id` int(11)
,`character_name` varchar(100)
,`character_role` varchar(100)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_character_poses`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_character_poses` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` varchar(100)
,`character_pose_id` int(11)
,`character_id` int(11)
,`character_name` varchar(100)
,`pose_id` int(11)
,`pose_name` varchar(100)
,`angle_id` int(11)
,`angle_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_composites`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_composites` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`composite_id` int(11)
,`composite_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_controlnet_maps`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_controlnet_maps` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`map_id` int(11)
,`map_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_generatives`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_generatives` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`generative_id` int(11)
,`name` varchar(100)
,`description` text
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_locations`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_locations` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`location_id` int(11)
,`location_name` varchar(100)
,`location_type` varchar(50)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_prompt_matrix_blueprints`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_prompt_matrix_blueprints` (
`frame_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`map_run_id` int(11)
,`entity_id` int(11)
,`blueprint_name` varchar(100)
,`blueprint_entity_type` varchar(100)
,`blueprint_entity_id` int(11)
,`blueprint_description` text
,`blueprint_matrix_id` int(10) unsigned
,`blueprint_matrix_additions_id` int(10) unsigned
,`blueprint_active_map_run_id` int(11)
,`blueprint_state_id_active` int(11)
,`blueprint_regenerate_images` tinyint(1)
,`blueprint_img2img` tinyint(1)
,`blueprint_cnmap` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_scene_parts`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_scene_parts` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`style` text
,`scene_part_id` int(11)
,`scene_part_name` varchar(255)
,`scene_part_description` text
,`characters` varchar(500)
,`animas` varchar(500)
,`artifacts` varchar(300)
,`backgrounds` varchar(300)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_sketches`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_sketches` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`sketch_id` int(11)
,`name` varchar(100)
,`description` text
,`mood` text
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_spawns`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_spawns` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`spawn_id` int(11)
,`name` varchar(100)
,`description` text
,`type` varchar(50)
,`type_label` varchar(100)
,`spawn_type_id` int(11)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_spawns_reference`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_spawns_reference` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`spawn_id` int(11)
,`name` varchar(100)
,`description` text
,`type` varchar(50)
,`type_label` varchar(100)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_spawns_texture`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_spawns_texture` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`spawn_id` int(11)
,`name` varchar(100)
,`description` text
,`type` varchar(50)
,`type_label` varchar(100)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_gallery_vehicles`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_gallery_vehicles` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`vehicle_id` int(11)
,`vehicle_name` varchar(100)
,`vehicle_type` varchar(50)
,`vehicle_status` enum('inactive','active','damaged','decommissioned')
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_animas`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_animas` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_artifacts`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_artifacts` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_backgrounds`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_backgrounds` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_characters`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_characters` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_character_poses`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_character_poses` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_composites`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_composites` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_controlnet_maps`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_controlnet_maps` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_generatives`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_generatives` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_locations`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_locations` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_prompt_matrix_blueprints`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_prompt_matrix_blueprints` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_scene_parts`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_scene_parts` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_sketches`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_sketches` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_map_runs_vehicles`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_map_runs_vehicles` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_animas`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_animas` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_artifacts`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_artifacts` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_backgrounds`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_backgrounds` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_characters`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_characters` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_character_poses`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_character_poses` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_composites`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_composites` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_controlnet_maps`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_controlnet_maps` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_generatives`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_generatives` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_locations`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_locations` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_prompt_matrix_blueprints`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_prompt_matrix_blueprints` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_scene_parts`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_scene_parts` (
`scene_part_id` int(11)
,`id` int(11)
,`scene_id` int(11)
,`name` varchar(255)
,`description` text
,`characters` varchar(500)
,`animas` varchar(500)
,`artifacts` varchar(300)
,`backgrounds` varchar(300)
,`prompt` mediumtext
,`regenerate_images` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_sketches`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_sketches` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_prompts_vehicles`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_prompts_vehicles` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_scenes_under_review`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_scenes_under_review` (
`scene_id` int(11)
,`scene_title` varchar(255)
,`scene_part_id` int(11)
,`stage` enum('draft','review','approved','locked')
,`assigned_to` varchar(100)
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_scene_part_full`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_scene_part_full` (
`scene_part_id` int(11)
,`scene_part_name` varchar(255)
,`scene_part_description` text
,`perspective_angle` varchar(500)
,`perspective_notes` text
,`background_name` varchar(100)
,`background_description` text
,`animas_in_scene` mediumtext
,`animas_details` mediumtext
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_styles_helper`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_styles_helper` (
`id` int(11)
,`regenerate_images` int(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `weather_conditions`
--

CREATE TABLE `weather_conditions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Short name of the weather condition (e.g. Sunny, Stormy, Foggy)',
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for UI/menus',
  `description` text DEFAULT NULL COMMENT 'Optional details (e.g. light rain at dusk, heavy snowstorm)',
  `intensity` varchar(50) DEFAULT NULL COMMENT 'Optional intensity scale (e.g. light, moderate, heavy)',
  `time_of_day_hint` varchar(50) DEFAULT NULL COMMENT 'Optional hint like morning, dusk, night',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `angles`
--
ALTER TABLE `angles`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `animas`
--
ALTER TABLE `animas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_character_animas_character` (`character_id`),
  ADD KEY `idx_character_animas_name` (`name`);

--
-- Indizes für die Tabelle `artifacts`
--
ALTER TABLE `artifacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_artifacts_name` (`name`),
  ADD KEY `idx_artifacts_type` (`type`),
  ADD KEY `idx_artifacts_status` (`status`);

--
-- Indizes für die Tabelle `audio_assets`
--
ALTER TABLE `audio_assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_audio_name` (`name`),
  ADD KEY `idx_audio_type` (`type`);

--
-- Indizes für die Tabelle `backgrounds`
--
ALTER TABLE `backgrounds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_backgrounds_name` (`name`),
  ADD KEY `idx_backgrounds_type` (`type`),
  ADD KEY `idx_backgrounds_location` (`location_id`);

--
-- Indizes für die Tabelle `characters`
--
ALTER TABLE `characters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_characters_name` (`name`),
  ADD KEY `idx_characters_role` (`role`);

--
-- Indizes für die Tabelle `character_poses`
--
ALTER TABLE `character_poses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_character_pose_angle` (`character_id`,`pose_id`,`angle_id`),
  ADD KEY `character_id` (`character_id`),
  ADD KEY `pose_id` (`pose_id`),
  ADD KEY `angle_id` (`angle_id`);

--
-- Indizes für die Tabelle `chat_message`
--
ALTER TABLE `chat_message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indizes für die Tabelle `chat_session`
--
ALTER TABLE `chat_session`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_chat_session_type` (`type`);

--
-- Indizes für die Tabelle `chat_summary`
--
ALTER TABLE `chat_summary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indizes für die Tabelle `composites`
--
ALTER TABLE `composites`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `composite_frames`
--
ALTER TABLE `composite_frames`
  ADD PRIMARY KEY (`composite_id`,`frame_id`);

--
-- Indizes für die Tabelle `content_elements`
--
ALTER TABLE `content_elements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `page_id` (`page_id`);

--
-- Indizes für die Tabelle `controlnet_maps`
--
ALTER TABLE `controlnet_maps`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `export_flags`
--
ALTER TABLE `export_flags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_export_scene_part` (`scene_part_id`),
  ADD KEY `idx_export_ready` (`ready_for_export`),
  ADD KEY `idx_export_type` (`export_type`);

--
-- Indizes für die Tabelle `feedback_notes`
--
ALTER TABLE `feedback_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_source` (`source`),
  ADD KEY `idx_feedback_status` (`resolved_status`),
  ADD KEY `idx_feedback_scene_part` (`scene_part_id`);

--
-- Indizes für die Tabelle `frames`
--
ALTER TABLE `frames`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `frames_2_animas`
--
ALTER TABLE `frames_2_animas`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `anima_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_artifacts`
--
ALTER TABLE `frames_2_artifacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_frame_artifact_from` (`from_id`),
  ADD KEY `idx_frame_artifact_to` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_backgrounds`
--
ALTER TABLE `frames_2_backgrounds`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `background_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_characters`
--
ALTER TABLE `frames_2_characters`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `character_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_character_poses`
--
ALTER TABLE `frames_2_character_poses`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `from_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_composites`
--
ALTER TABLE `frames_2_composites`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `composite_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_controlnet_maps`
--
ALTER TABLE `frames_2_controlnet_maps`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `to_id_idx` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_generatives`
--
ALTER TABLE `frames_2_generatives`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `idx_frame_generative_from` (`from_id`),
  ADD KEY `idx_frame_generative_to` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_locations`
--
ALTER TABLE `frames_2_locations`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `location_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_pastebin`
--
ALTER TABLE `frames_2_pastebin`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `location_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_prompt_matrix_blueprints`
--
ALTER TABLE `frames_2_prompt_matrix_blueprints`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `prompt_matrix_blueprint_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_scene_parts`
--
ALTER TABLE `frames_2_scene_parts`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `to_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_sketches`
--
ALTER TABLE `frames_2_sketches`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `idx_frame_sketch_from` (`from_id`),
  ADD KEY `idx_frame_sketch_to` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_spawns`
--
ALTER TABLE `frames_2_spawns`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `seed_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_2_vehicles`
--
ALTER TABLE `frames_2_vehicles`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `idx_from_id` (`from_id`),
  ADD KEY `idx_to_id` (`to_id`);

--
-- Indizes für die Tabelle `frames_chains`
--
ALTER TABLE `frames_chains`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_frame_id` (`frame_id`),
  ADD KEY `idx_parent_frame_id` (`parent_frame_id`);

--
-- Indizes für die Tabelle `frames_failed`
--
ALTER TABLE `frames_failed`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `frames_trashcan`
--
ALTER TABLE `frames_trashcan`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `frame_counter`
--
ALTER TABLE `frame_counter`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `generatives`
--
ALTER TABLE `generatives`
  ADD PRIMARY KEY (`id`);


--
-- Indizes für die Tabelle `image_edits`
--
ALTER TABLE `image_edits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent_frame` (`parent_frame_id`),
  ADD KEY `idx_chain` (`chain_id`),
  ADD KEY `idx_derived_frame` (`derived_frame_id`),
  ADD KEY `idx_map_run` (`map_run_id`);

--
-- Indizes für die Tabelle `image_stash`
--
ALTER TABLE `image_stash`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `interactions`
--
ALTER TABLE `interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_interactions_scene_part` (`scene_part_id`),
  ADD KEY `idx_interactions_character` (`character_id`),
  ADD KEY `idx_interactions_order` (`scene_part_id`,`sequence`);

--
-- Indizes für die Tabelle `interaction_audio`
--
ALTER TABLE `interaction_audio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ia_unique` (`interaction_id`,`audio_asset_id`),
  ADD KEY `fk_ia_audio` (`audio_asset_id`);

--
-- Indizes für die Tabelle `lightings`
--
ALTER TABLE `lightings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `angle_id` (`angle_id`);

--
-- Indizes für die Tabelle `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_locations_name` (`name`),
  ADD KEY `idx_locations_type` (`type`);

--
-- Indizes für die Tabelle `locations_abstract`
--
ALTER TABLE `locations_abstract`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_locations_name` (`name`),
  ADD KEY `idx_locations_type` (`type`);

--
-- Indizes für die Tabelle `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `map_runs`
--
ALTER TABLE `map_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_map_run_idx` (`parent_map_run_id`);

--
-- Indizes für die Tabelle `meta_entities`
--
ALTER TABLE `meta_entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indizes für die Tabelle `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indizes für die Tabelle `pastebin`
--
ALTER TABLE `pastebin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `url_token` (`url_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_visibility` (`visibility`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indizes für die Tabelle `perspectives`
--
ALTER TABLE `perspectives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_perspectives_scene_part` (`scene_part_id`);

--
-- Indizes für die Tabelle `poses`
--
ALTER TABLE `poses`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `production_status`
--
ALTER TABLE `production_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prodstatus_scene_part` (`scene_part_id`),
  ADD KEY `idx_prodstatus_stage` (`stage`),
  ADD KEY `idx_prodstatus_assignee` (`assigned_to`);

--
-- Indizes für die Tabelle `prompt_additions`
--
ALTER TABLE `prompt_additions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slot_order` (`slot`,`order`),
  ADD KEY `idx_active_slot` (`active`,`slot`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`);

--
-- Indizes für die Tabelle `prompt_globals`
--
ALTER TABLE `prompt_globals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prompt_globals_name` (`name`);

--
-- Indizes für die Tabelle `prompt_ideations`
--
ALTER TABLE `prompt_ideations`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `prompt_matrix`
--
ALTER TABLE `prompt_matrix`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_map_run` (`active_map_run_id`),
  ADD KEY `idx_state` (`state_id_active`);

--
-- Indizes für die Tabelle `prompt_matrix_additions`
--
ALTER TABLE `prompt_matrix_additions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_matrix_slot` (`matrix_id`,`slot`),
  ADD KEY `idx_addition_id` (`addition_id`);

--
-- Indizes für die Tabelle `prompt_matrix_blueprints`
--
ALTER TABLE `prompt_matrix_blueprints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sketches_name` (`name`);

--
-- Indizes für die Tabelle `scenes`
--
ALTER TABLE `scenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scenes_sequence` (`sequence`),
  ADD KEY `fk_scene_arc` (`arc_id`);

--
-- Indizes für die Tabelle `scene_parts`
--
ALTER TABLE `scene_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scene_parts_scene` (`scene_id`),
  ADD KEY `idx_scene_parts_scene_seq` (`scene_id`,`sequence`);

--
-- Indizes für die Tabelle `scene_part_animas`
--
ALTER TABLE `scene_part_animas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_span_unique` (`scene_part_id`,`character_anima_id`,`action_type`),
  ADD KEY `idx_span_scene_part` (`scene_part_id`),
  ADD KEY `idx_span_character_anima` (`character_anima_id`);

--
-- Indizes für die Tabelle `scene_part_artifacts`
--
ALTER TABLE `scene_part_artifacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spa_unique` (`scene_part_id`,`artifact_id`),
  ADD KEY `idx_spa_artifact` (`artifact_id`),
  ADD KEY `idx_spa_scene_part` (`scene_part_id`);

--
-- Indizes für die Tabelle `scene_part_backgrounds`
--
ALTER TABLE `scene_part_backgrounds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spb_unique` (`perspective_id`,`background_id`),
  ADD KEY `idx_spb_background` (`background_id`),
  ADD KEY `idx_spb_perspective` (`perspective_id`);

--
-- Indizes für die Tabelle `scene_part_characters`
--
ALTER TABLE `scene_part_characters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spc_unique` (`scene_part_id`,`character_id`),
  ADD KEY `idx_spc_char` (`character_id`),
  ADD KEY `idx_spc_scene_part` (`scene_part_id`);

--
-- Indizes für die Tabelle `scene_part_tags`
--
ALTER TABLE `scene_part_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spt_unique` (`scene_part_id`,`tag_id`),
  ADD KEY `idx_spt_tag` (`tag_id`),
  ADD KEY `idx_spt_scene_part` (`scene_part_id`);

--
-- Indizes für die Tabelle `scene_part_versions`
--
ALTER TABLE `scene_part_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spv_unique` (`scene_part_id`,`version_number`),
  ADD KEY `idx_spv_scene_part` (`scene_part_id`),
  ADD KEY `idx_spv_version` (`version_number`);

--
-- Indizes für die Tabelle `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_time` (`active`,`schedule_time`),
  ADD KEY `idx_active_interval` (`active`,`schedule_interval`),
  ADD KEY `idx_last_run` (`last_run`);

--
-- Indizes für die Tabelle `scheduler_heartbeat`
--
ALTER TABLE `scheduler_heartbeat`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `seeds`
--
ALTER TABLE `seeds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seeds_value` (`value`);

--
-- Indizes für die Tabelle `sketches`
--
ALTER TABLE `sketches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sketches_name` (`name`);

--
-- Indizes für die Tabelle `spawns`
--
ALTER TABLE `spawns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_seeds_type` (`type`),
  ADD KEY `idx_spawn_type_id` (`spawn_type_id`);

--
-- Indizes für die Tabelle `spawn_types`
--
ALTER TABLE `spawn_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indizes für die Tabelle `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_states_name` (`name`);

--
-- Indizes für die Tabelle `storyboards`
--
ALTER TABLE `storyboards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `directory` (`directory`);

--
-- Indizes für die Tabelle `storyboard_frames`
--
ALTER TABLE `storyboard_frames`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_storyboard` (`storyboard_id`),
  ADD KEY `idx_frame` (`frame_id`),
  ADD KEY `idx_sort` (`storyboard_id`,`sort_order`);

--
-- Indizes für die Tabelle `story_arcs`
--
ALTER TABLE `story_arcs`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `styles`
--
ALTER TABLE `styles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_styles_name` (`name`);

--
-- Indizes für die Tabelle `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tags_name` (`name`);

--
-- Indizes für die Tabelle `tags2poses`
--
ALTER TABLE `tags2poses`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indizes für die Tabelle `tags_2_frames`
--
ALTER TABLE `tags_2_frames`
  ADD UNIQUE KEY `uq_tags_2_frames` (`from_id`,`to_id`);

--
-- Indizes für die Tabelle `task_execution_stats`
--
ALTER TABLE `task_execution_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_task_date` (`task_id`,`date`),
  ADD KEY `task_id` (`task_id`);

--
-- Indizes für die Tabelle `task_locks`
--
ALTER TABLE `task_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lock_key` (`lock_key`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `run_id` (`run_id`),
  ADD KEY `idx_status_expires` (`status`,`expires_at`),
  ADD KEY `idx_task_locks_owner_token` (`owner_token`);

--
-- Indizes für die Tabelle `task_runs`
--
ALTER TABLE `task_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `lock_id` (`lock_id`),
  ADD KEY `idx_task_runs_status_pid` (`status`,`pid`);

--
-- Indizes für die Tabelle `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `google_id_unique` (`google_id`);

--
-- Indizes für die Tabelle `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vehicles_name` (`name`),
  ADD KEY `idx_vehicles_type` (`type`),
  ADD KEY `idx_vehicles_status` (`status`);

--
-- Indizes für die Tabelle `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `weather_conditions`
--
ALTER TABLE `weather_conditions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_weather_name` (`name`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `angles`
--
ALTER TABLE `angles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT für Tabelle `animas`
--
ALTER TABLE `animas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `artifacts`
--
ALTER TABLE `artifacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `audio_assets`
--
ALTER TABLE `audio_assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `backgrounds`
--
ALTER TABLE `backgrounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `characters`
--
ALTER TABLE `characters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `character_poses`
--
ALTER TABLE `character_poses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `chat_message`
--
ALTER TABLE `chat_message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `chat_session`
--
ALTER TABLE `chat_session`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `chat_summary`
--
ALTER TABLE `chat_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `composites`
--
ALTER TABLE `composites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `content_elements`
--
ALTER TABLE `content_elements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `controlnet_maps`
--
ALTER TABLE `controlnet_maps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `export_flags`
--
ALTER TABLE `export_flags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `feedback_notes`
--
ALTER TABLE `feedback_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `frames`
--
ALTER TABLE `frames`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `frames_2_artifacts`
--
ALTER TABLE `frames_2_artifacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `frames_chains`
--
ALTER TABLE `frames_chains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `frames_failed`
--
ALTER TABLE `frames_failed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `frames_trashcan`
--
ALTER TABLE `frames_trashcan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `generatives`
--
ALTER TABLE `generatives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `generator_config`
--
ALTER TABLE `generator_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `image_edits`
--
ALTER TABLE `image_edits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `image_stash`
--
ALTER TABLE `image_stash`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `interactions`
--
ALTER TABLE `interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `interaction_audio`
--
ALTER TABLE `interaction_audio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `lightings`
--
ALTER TABLE `lightings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `locations_abstract`
--
ALTER TABLE `locations_abstract`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `map_runs`
--
ALTER TABLE `map_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `meta_entities`
--
ALTER TABLE `meta_entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT für Tabelle `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `pastebin`
--
ALTER TABLE `pastebin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `perspectives`
--
ALTER TABLE `perspectives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `poses`
--
ALTER TABLE `poses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `production_status`
--
ALTER TABLE `production_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `prompt_additions`
--
ALTER TABLE `prompt_additions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `prompt_globals`
--
ALTER TABLE `prompt_globals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `prompt_ideations`
--
ALTER TABLE `prompt_ideations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `prompt_matrix`
--
ALTER TABLE `prompt_matrix`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `prompt_matrix_additions`
--
ALTER TABLE `prompt_matrix_additions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `prompt_matrix_blueprints`
--
ALTER TABLE `prompt_matrix_blueprints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scenes`
--
ALTER TABLE `scenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scene_parts`
--
ALTER TABLE `scene_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scene_part_animas`
--
ALTER TABLE `scene_part_animas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scene_part_artifacts`
--
ALTER TABLE `scene_part_artifacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scene_part_backgrounds`
--
ALTER TABLE `scene_part_backgrounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scene_part_characters`
--
ALTER TABLE `scene_part_characters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scene_part_tags`
--
ALTER TABLE `scene_part_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scene_part_versions`
--
ALTER TABLE `scene_part_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT für Tabelle `seeds`
--
ALTER TABLE `seeds`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `sketches`
--
ALTER TABLE `sketches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `spawns`
--
ALTER TABLE `spawns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `spawn_types`
--
ALTER TABLE `spawn_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `states`
--
ALTER TABLE `states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `storyboards`
--
ALTER TABLE `storyboards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `storyboard_frames`
--
ALTER TABLE `storyboard_frames`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `story_arcs`
--
ALTER TABLE `story_arcs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `styles`
--
ALTER TABLE `styles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT für Tabelle `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `task_execution_stats`
--
ALTER TABLE `task_execution_stats`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `task_locks`
--
ALTER TABLE `task_locks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `task_runs`
--
ALTER TABLE `task_runs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `weather_conditions`
--
ALTER TABLE `weather_conditions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Struktur des Views `v_anima_activity`
--
DROP TABLE IF EXISTS `v_anima_activity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_anima_activity`  AS SELECT `s`.`id` AS `scene_id`, `s`.`sequence` AS `scene_sequence`, `sp`.`id` AS `scene_part_id`, `sp`.`sequence` AS `part_sequence`, `a`.`id` AS `character_anima_id`, `ch`.`name` AS `character_name`, `a`.`name` AS `anima_name`, `span`.`action_type` AS `action_type`, `span`.`notes` AS `notes` FROM ((((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_animas` `span` on(`span`.`scene_part_id` = `sp`.`id`)) join `animas` `a` on(`a`.`id` = `span`.`character_anima_id`)) join `characters` `ch` on(`ch`.`id` = `a`.`character_id`)) ORDER BY `s`.`sequence` ASC, `sp`.`sequence` ASC, `ch`.`name` ASC, `a`.`name` ASC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_artifact_usage`
--
DROP TABLE IF EXISTS `v_artifact_usage`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_artifact_usage`  AS SELECT `s`.`id` AS `scene_id`, `s`.`sequence` AS `scene_sequence`, `sp`.`id` AS `scene_part_id`, `sp`.`sequence` AS `part_sequence`, `a`.`id` AS `artifact_id`, `a`.`name` AS `artifact_name`, `a`.`type` AS `artifact_type`, `a`.`status` AS `artifact_status`, `spa`.`notes` AS `notes` FROM (((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_artifacts` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) join `artifacts` `a` on(`a`.`id` = `spa`.`artifact_id`)) ORDER BY `s`.`sequence` ASC, `sp`.`sequence` ASC, `a`.`name` ASC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_character_pose_angle_combinations`
--
DROP TABLE IF EXISTS `v_character_pose_angle_combinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_character_pose_angle_combinations`  AS SELECT `c`.`id` AS `character_id`, `c`.`name` AS `character_name`, `p`.`id` AS `pose_id`, `p`.`name` AS `pose_name`, `a`.`id` AS `angle_id`, `a`.`name` AS `angle_name`, concat(`c`.`name`,' (',`c`.`description`,') - ',`p`.`name`,' (',`p`.`description`,') - ',`a`.`name`,' (',`a`.`description`,')') AS `description` FROM ((`characters` `c` join `poses` `p`) join `angles` `a`) ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_dialogue_tts`
--
DROP TABLE IF EXISTS `v_dialogue_tts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dialogue_tts`  AS SELECT `s`.`id` AS `scene_id`, `s`.`sequence` AS `scene_sequence`, `s`.`title` AS `scene_title`, `sp`.`id` AS `scene_part_id`, `sp`.`sequence` AS `part_sequence`, `i`.`sequence` AS `line_sequence`, `c`.`name` AS `character_name`, `i`.`emotion` AS `emotion`, `i`.`content` AS `dialogue` FROM (((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `interactions` `i` on(`i`.`scene_part_id` = `sp`.`id` and `i`.`type` = 'dialogue')) left join `characters` `c` on(`c`.`id` = `i`.`character_id`)) ORDER BY `s`.`sequence` ASC, `sp`.`sequence` ASC, `i`.`sequence` ASC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_export_ready`
--
DROP TABLE IF EXISTS `v_export_ready`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_export_ready`  AS SELECT `s`.`id` AS `scene_id`, `s`.`title` AS `scene_title`, `sp`.`id` AS `scene_part_id`, `ef`.`export_type` AS `export_type`, `ef`.`last_exported_at` AS `last_exported_at` FROM ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `export_flags` `ef` on(`ef`.`scene_part_id` = `sp`.`id`)) WHERE `ef`.`ready_for_export` = 1 ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_animas`
--
DROP TABLE IF EXISTS `v_gallery_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_animas`  AS SELECT `f`.`id` AS `frame_id`, `a`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `a`.`id` AS `anima_id`, `a`.`name` AS `anima_name`, `a`.`traits` AS `traits`, `a`.`abilities` AS `abilities`, `c`.`id` AS `character_id`, `c`.`name` AS `character_name`, `c`.`role` AS `character_role` FROM ((((`frames` `f` join `frames_2_animas` `m` on(`m`.`from_id` = `f`.`id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) left join `characters` `c` on(`c`.`id` = `a`.`character_id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `s`.`order` ASC, `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_artifacts`
--
DROP TABLE IF EXISTS `v_gallery_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_artifacts`  AS SELECT `f`.`id` AS `frame_id`, `a`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `a`.`id` AS `artifact_id`, `a`.`name` AS `artifact_name`, `a`.`type` AS `artifact_type`, `a`.`status` AS `artifact_status` FROM (((`frames` `f` join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `a` on(`m`.`to_id` = `a`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_backgrounds`
--
DROP TABLE IF EXISTS `v_gallery_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_backgrounds`  AS SELECT `f`.`id` AS `frame_id`, `b`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `b`.`id` AS `background_id`, `b`.`name` AS `background_name`, `b`.`type` AS `background_type`, `l`.`id` AS `location_id`, `l`.`name` AS `location_name` FROM ((((`frames` `f` join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`m`.`to_id` = `b`.`id`)) left join `locations` `l` on(`b`.`location_id` = `l`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_characters`
--
DROP TABLE IF EXISTS `v_gallery_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_characters`  AS SELECT `f`.`id` AS `frame_id`, `f`.`map_run_id` AS `map_run_id`, `c`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `c`.`id` AS `character_id`, `c`.`name` AS `character_name`, `c`.`role` AS `character_role` FROM (((`frames` `f` join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `s`.`order` ASC, `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_character_poses`
--
DROP TABLE IF EXISTS `v_gallery_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_character_poses`  AS SELECT `f`.`id` AS `frame_id`, `cp`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `cp`.`description` AS `prompt`, `s`.`name` AS `style`, `cp`.`id` AS `character_pose_id`, `c`.`id` AS `character_id`, `c`.`name` AS `character_name`, `cp`.`pose_id` AS `pose_id`, `p`.`name` AS `pose_name`, `cp`.`angle_id` AS `angle_id`, `a`.`name` AS `angle_name` FROM ((((((`frames` `f` join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`m`.`to_id` = `cp`.`id`)) join `characters` `c` on(`cp`.`character_id` = `c`.`id`)) join `poses` `p` on(`cp`.`pose_id` = `p`.`id`)) join `angles` `a` on(`cp`.`angle_id` = `a`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 AND `f`.`map_run_id` = `cp`.`active_map_run_id` ORDER BY `s`.`order` ASC, `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_composites`
--
DROP TABLE IF EXISTS `v_gallery_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_composites`  AS SELECT `f`.`id` AS `frame_id`, `c`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `c`.`id` AS `composite_id`, `c`.`name` AS `composite_name` FROM (((`frames` `f` join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`m`.`to_id` = `c`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_controlnet_maps`
--
DROP TABLE IF EXISTS `v_gallery_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_controlnet_maps`  AS SELECT `f`.`id` AS `frame_id`, `c`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `c`.`id` AS `map_id`, `c`.`name` AS `map_name` FROM ((`frames` `f` join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`m`.`to_id` = `c`.`id`)) WHERE `f`.`map_run_id` = `c`.`active_map_run_id` ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_generatives`
--
DROP TABLE IF EXISTS `v_gallery_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_generatives`  AS SELECT `f`.`id` AS `frame_id`, `g`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `g`.`id` AS `generative_id`, `g`.`name` AS `name`, `g`.`description` AS `description` FROM (((`frames` `f` join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`m`.`to_id` = `g`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_locations`
--
DROP TABLE IF EXISTS `v_gallery_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_locations`  AS SELECT `f`.`id` AS `frame_id`, `l`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `l`.`id` AS `location_id`, `l`.`name` AS `location_name`, `l`.`type` AS `location_type` FROM (((`frames` `f` join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`m`.`to_id` = `l`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_prompt_matrix_blueprints`
--
DROP TABLE IF EXISTS `v_gallery_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_prompt_matrix_blueprints`  AS SELECT `f`.`id` AS `frame_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `f`.`map_run_id` AS `map_run_id`, `b`.`id` AS `entity_id`, `b`.`name` AS `blueprint_name`, `b`.`entity_type` AS `blueprint_entity_type`, `b`.`entity_id` AS `blueprint_entity_id`, `b`.`description` AS `blueprint_description`, `b`.`matrix_id` AS `blueprint_matrix_id`, `b`.`matrix_additions_id` AS `blueprint_matrix_additions_id`, `b`.`active_map_run_id` AS `blueprint_active_map_run_id`, `b`.`state_id_active` AS `blueprint_state_id_active`, `b`.`regenerate_images` AS `blueprint_regenerate_images`, `b`.`img2img` AS `blueprint_img2img`, `b`.`cnmap` AS `blueprint_cnmap` FROM (((`frames` `f` join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`m`.`to_id` = `b`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_scene_parts`
--
DROP TABLE IF EXISTS `v_gallery_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_scene_parts`  AS SELECT `f`.`id` AS `frame_id`, `sp`.`scene_part_id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`style` AS `style`, `sp`.`scene_part_id` AS `scene_part_id`, `sp`.`name` AS `scene_part_name`, `sp`.`description` AS `scene_part_description`, `sp`.`characters` AS `characters`, `sp`.`animas` AS `animas`, `sp`.`artifacts` AS `artifacts`, `sp`.`backgrounds` AS `backgrounds`, `sp`.`prompt` AS `prompt` FROM (((`frames` `f` join `frames_2_scene_parts` `m` on(`f`.`id` = `m`.`from_id`)) join (select `sp`.`id` AS `scene_part_id`,`sp`.`name` AS `name`,`sp`.`description` AS `description`,`sp`.`regenerate_images` AS `regenerate_images`,`sp`.`active_map_run_id` AS `active_map_run_id`,substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`,substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`,substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`,substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`,concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt` from ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) group by `sp`.`id`) `sp` on(`m`.`to_id` = `sp`.`scene_part_id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 AND `f`.`map_run_id` = `sp`.`active_map_run_id` ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_sketches`
--
DROP TABLE IF EXISTS `v_gallery_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_sketches`  AS SELECT `f`.`id` AS `frame_id`, `s`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `s`.`id` AS `sketch_id`, `s`.`name` AS `name`, `s`.`description` AS `description`, `s`.`mood` AS `mood` FROM (((`frames` `f` join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`m`.`to_id` = `s`.`id`)) join `styles` `st` on(`f`.`style_id` = `st`.`id`)) WHERE `st`.`visible` = 1 ORDER BY `st`.`order` ASC, `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_spawns`
--
DROP TABLE IF EXISTS `v_gallery_spawns`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns`  AS SELECT `f`.`id` AS `frame_id`, `s`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `s`.`id` AS `spawn_id`, `s`.`name` AS `name`, `s`.`description` AS `description`, coalesce(`st`.`code`,`s`.`type`) AS `type`, `st`.`label` AS `type_label`, `st`.`id` AS `spawn_type_id` FROM (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) left join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_spawns_reference`
--
DROP TABLE IF EXISTS `v_gallery_spawns_reference`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_reference`  AS SELECT `f`.`id` AS `frame_id`, `s`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `s`.`id` AS `spawn_id`, `s`.`name` AS `name`, `s`.`description` AS `description`, `st`.`code` AS `type`, `st`.`label` AS `type_label` FROM (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) WHERE `st`.`code` = 'reference' ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_spawns_texture`
--
DROP TABLE IF EXISTS `v_gallery_spawns_texture`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_texture`  AS SELECT `f`.`id` AS `frame_id`, `s`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `s`.`id` AS `spawn_id`, `s`.`name` AS `name`, `s`.`description` AS `description`, `st`.`code` AS `type`, `st`.`label` AS `type_label` FROM (((`frames` `f` join `frames_2_spawns` `m` on(`f`.`id` = `m`.`from_id`)) join `spawns` `s` on(`m`.`to_id` = `s`.`id`)) join `spawn_types` `st` on(`s`.`spawn_type_id` = `st`.`id`)) WHERE `st`.`code` = 'texture' ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_gallery_vehicles`
--
DROP TABLE IF EXISTS `v_gallery_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_vehicles`  AS SELECT `f`.`id` AS `frame_id`, `v`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `v`.`id` AS `vehicle_id`, `v`.`name` AS `vehicle_name`, `v`.`type` AS `vehicle_type`, `v`.`status` AS `vehicle_status` FROM (((`frames` `f` join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`m`.`to_id` = `v`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_animas`
--
DROP TABLE IF EXISTS `v_map_runs_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_animas`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `a`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_animas` `m` on(`f`.`id` = `m`.`from_id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'animas' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_artifacts`
--
DROP TABLE IF EXISTS `v_map_runs_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_artifacts`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `ar`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `ar` on(`ar`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'artifacts' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_backgrounds`
--
DROP TABLE IF EXISTS `v_map_runs_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_backgrounds`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `b`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`b`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'backgrounds' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_characters`
--
DROP TABLE IF EXISTS `v_map_runs_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_characters`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `c`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`c`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'characters' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_character_poses`
--
DROP TABLE IF EXISTS `v_map_runs_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_character_poses`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `cp`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`cp`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'character_poses' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_composites`
--
DROP TABLE IF EXISTS `v_map_runs_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_composites`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `c`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`c`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'composites' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_controlnet_maps`
--
DROP TABLE IF EXISTS `v_map_runs_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_controlnet_maps`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `c`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`c`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'controlnet_maps' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_generatives`
--
DROP TABLE IF EXISTS `v_map_runs_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_generatives`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `g`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`g`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'generatives' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_locations`
--
DROP TABLE IF EXISTS `v_map_runs_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_locations`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `l`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`l`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'locations' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_prompt_matrix_blueprints`
--
DROP TABLE IF EXISTS `v_map_runs_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_prompt_matrix_blueprints`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `b`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`b`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'prompt_matrix_blueprints' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_scene_parts`
--
DROP TABLE IF EXISTS `v_map_runs_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_scene_parts`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `f2sp`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `sp`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_scene_parts` `f2sp` on(`f2sp`.`from_id` = `f`.`id`)) join `scene_parts` `sp` on(`sp`.`id` = `f2sp`.`to_id`)) WHERE `mr`.`entity_type` = 'scene_parts' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_sketches`
--
DROP TABLE IF EXISTS `v_map_runs_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_sketches`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `s`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`s`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'sketches' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_map_runs_vehicles`
--
DROP TABLE IF EXISTS `v_map_runs_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_vehicles`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `v`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`v`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'vehicles' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_animas`
--
DROP TABLE IF EXISTS `v_prompts_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_animas`  AS SELECT `a`.`id` AS `id`, `a`.`regenerate_images` AS `regenerate_images`, coalesce(`a`.`description`,'') AS `prompt` FROM `animas` AS `a` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_artifacts`
--
DROP TABLE IF EXISTS `v_prompts_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_artifacts`  AS SELECT `ar`.`id` AS `id`, `ar`.`regenerate_images` AS `regenerate_images`, coalesce(`ar`.`description`,'') AS `prompt` FROM `artifacts` AS `ar` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_backgrounds`
--
DROP TABLE IF EXISTS `v_prompts_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_backgrounds`  AS SELECT `b`.`id` AS `id`, `b`.`regenerate_images` AS `regenerate_images`, coalesce(`b`.`description`,'') AS `prompt` FROM `backgrounds` AS `b` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_characters`
--
DROP TABLE IF EXISTS `v_prompts_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_characters`  AS SELECT `c`.`id` AS `id`, `c`.`regenerate_images` AS `regenerate_images`, coalesce(`c`.`description`,'') AS `prompt` FROM `characters` AS `c` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_character_poses`
--
DROP TABLE IF EXISTS `v_prompts_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_character_poses`  AS SELECT `cp`.`id` AS `id`, `cp`.`regenerate_images` AS `regenerate_images`, concat('((',`c`.`name`,': ',`c`.`description`,')) ','(Pose: ',`p`.`description`,') ','(Angle: ',`a`.`description`,')') AS `prompt` FROM (((`character_poses` `cp` join `characters` `c` on(`cp`.`character_id` = `c`.`id`)) join `poses` `p` on(`cp`.`pose_id` = `p`.`id`)) join `angles` `a` on(`cp`.`angle_id` = `a`.`id`)) ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_composites`
--
DROP TABLE IF EXISTS `v_prompts_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_composites`  AS SELECT `c`.`id` AS `id`, `c`.`regenerate_images` AS `regenerate_images`, coalesce(`c`.`description`,'') AS `prompt` FROM `composites` AS `c` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_controlnet_maps`
--
DROP TABLE IF EXISTS `v_prompts_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_controlnet_maps`  AS SELECT `c`.`id` AS `id`, `c`.`regenerate_images` AS `regenerate_images`, concat_ws(', ',`c`.`name`,coalesce(`c`.`description`,'')) AS `prompt` FROM `controlnet_maps` AS `c` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_generatives`
--
DROP TABLE IF EXISTS `v_prompts_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_generatives`  AS SELECT `g`.`id` AS `id`, `g`.`regenerate_images` AS `regenerate_images`, concat_ws(', ',`g`.`name`,coalesce(`g`.`description`,'')) AS `prompt` FROM `generatives` AS `g` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_locations`
--
DROP TABLE IF EXISTS `v_prompts_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_locations`  AS SELECT `l`.`id` AS `id`, `l`.`regenerate_images` AS `regenerate_images`, coalesce(`l`.`description`,'') AS `prompt` FROM `locations` AS `l` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_prompt_matrix_blueprints`
--
DROP TABLE IF EXISTS `v_prompts_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_prompt_matrix_blueprints`  AS SELECT `prompt_matrix_blueprints`.`id` AS `id`, `prompt_matrix_blueprints`.`regenerate_images` AS `regenerate_images`, coalesce(`prompt_matrix_blueprints`.`description`,'') AS `prompt` FROM `prompt_matrix_blueprints` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_scene_parts`
--
DROP TABLE IF EXISTS `v_prompts_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_scene_parts`  AS SELECT `sp`.`id` AS `scene_part_id`, `sp`.`id` AS `id`, `sp`.`scene_id` AS `scene_id`, `sp`.`name` AS `name`, `sp`.`description` AS `description`, substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`, substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`, substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`, substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`, concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt`, `sp`.`regenerate_images` AS `regenerate_images` FROM ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) GROUP BY `sp`.`id` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_sketches`
--
DROP TABLE IF EXISTS `v_prompts_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_sketches`  AS SELECT `s`.`id` AS `id`, `s`.`regenerate_images` AS `regenerate_images`, coalesce(`s`.`description`,'') AS `prompt` FROM `sketches` AS `s` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_prompts_vehicles`
--
DROP TABLE IF EXISTS `v_prompts_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_vehicles`  AS SELECT `v`.`id` AS `id`, `v`.`regenerate_images` AS `regenerate_images`, coalesce(`v`.`description`,'') AS `prompt` FROM `vehicles` AS `v` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_scenes_under_review`
--
DROP TABLE IF EXISTS `v_scenes_under_review`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_scenes_under_review`  AS SELECT `s`.`id` AS `scene_id`, `s`.`title` AS `scene_title`, `sp`.`id` AS `scene_part_id`, `ps`.`stage` AS `stage`, `ps`.`assigned_to` AS `assigned_to`, `ps`.`updated_at` AS `updated_at` FROM ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `production_status` `ps` on(`ps`.`scene_part_id` = `sp`.`id`)) WHERE `ps`.`stage` = 'review' ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_scene_part_full`
--
DROP TABLE IF EXISTS `v_scene_part_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_scene_part_full`  AS SELECT `sp`.`id` AS `scene_part_id`, `sp`.`name` AS `scene_part_name`, `sp`.`description` AS `scene_part_description`, `p`.`angle` AS `perspective_angle`, `p`.`description` AS `perspective_notes`, `b`.`name` AS `background_name`, `b`.`description` AS `background_description`, group_concat(distinct `a`.`name` separator ', ') AS `animas_in_scene`, group_concat(distinct concat(`a`.`name`,': ',`a`.`traits`,'; ',`a`.`abilities`) separator ' | ') AS `animas_details` FROM (((((`scene_parts` `sp` join `perspectives` `p` on(`p`.`scene_part_id` = `sp`.`id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `p`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) GROUP BY `sp`.`id`, `p`.`id`, `b`.`id` ORDER BY `sp`.`sequence` ASC, `p`.`id` ASC ;

-- --------------------------------------------------------

--
-- Struktur des Views `v_styles_helper`
--
DROP TABLE IF EXISTS `v_styles_helper`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_styles_helper`  AS SELECT `s`.`id` AS `id`, 0 AS `regenerate_images`, concat('(',coalesce(`s`.`description`,''),')','(',(select `prompt_globals`.`description` from `prompt_globals` where `prompt_globals`.`id` = 1),')') AS `prompt` FROM `styles` AS `s` WHERE `s`.`active` = 1 ORDER BY `s`.`order` ASC ;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `backgrounds`
--
ALTER TABLE `backgrounds`
  ADD CONSTRAINT `fk_backgrounds_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `content_elements`
--
ALTER TABLE `content_elements`
  ADD CONSTRAINT `content_elements_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`);

--
-- Constraints der Tabelle `export_flags`
--
ALTER TABLE `export_flags`
  ADD CONSTRAINT `fk_export_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `feedback_notes`
--
ALTER TABLE `feedback_notes`
  ADD CONSTRAINT `fk_feedback_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `frames_2_artifacts`
--
ALTER TABLE `frames_2_artifacts`
  ADD CONSTRAINT `fk_frames_artifacts_artifact` FOREIGN KEY (`to_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `frames_2_generatives`
--
ALTER TABLE `frames_2_generatives`
  ADD CONSTRAINT `fk_frames_generatives_generative` FOREIGN KEY (`to_id`) REFERENCES `generatives` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `frames_2_scene_parts`
--
ALTER TABLE `frames_2_scene_parts`
  ADD CONSTRAINT `frames_2_scene_parts_ibfk_2` FOREIGN KEY (`to_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `frames_2_sketches`
--
ALTER TABLE `frames_2_sketches`
  ADD CONSTRAINT `fk_frames_sketches_sketch` FOREIGN KEY (`to_id`) REFERENCES `sketches` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `frames_2_vehicles`
--
ALTER TABLE `frames_2_vehicles`
  ADD CONSTRAINT `fk_frames_2_vehicles_to` FOREIGN KEY (`to_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `interaction_audio`
--
ALTER TABLE `interaction_audio`
  ADD CONSTRAINT `fk_ia_audio` FOREIGN KEY (`audio_asset_id`) REFERENCES `audio_assets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ia_interaction` FOREIGN KEY (`interaction_id`) REFERENCES `interactions` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `production_status`
--
ALTER TABLE `production_status`
  ADD CONSTRAINT `fk_prodstatus_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `scenes`
--
ALTER TABLE `scenes`
  ADD CONSTRAINT `fk_scene_arc` FOREIGN KEY (`arc_id`) REFERENCES `story_arcs` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `scene_parts`
--
ALTER TABLE `scene_parts`
  ADD CONSTRAINT `fk_scene_parts_scene` FOREIGN KEY (`scene_id`) REFERENCES `scenes` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `scene_part_animas`
--
ALTER TABLE `scene_part_animas`
  ADD CONSTRAINT `fk_span_character_anima` FOREIGN KEY (`character_anima_id`) REFERENCES `animas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_span_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `scene_part_artifacts`
--
ALTER TABLE `scene_part_artifacts`
  ADD CONSTRAINT `fk_spa_artifact` FOREIGN KEY (`artifact_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_spa_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `scene_part_backgrounds`
--
ALTER TABLE `scene_part_backgrounds`
  ADD CONSTRAINT `fk_spb_background` FOREIGN KEY (`background_id`) REFERENCES `backgrounds` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_spb_perspective` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `scene_part_tags`
--
ALTER TABLE `scene_part_tags`
  ADD CONSTRAINT `fk_spt_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_spt_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `scene_part_versions`
--
ALTER TABLE `scene_part_versions`
  ADD CONSTRAINT `fk_spv_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `spawns`
--
ALTER TABLE `spawns`
  ADD CONSTRAINT `fk_spawns_spawn_type` FOREIGN KEY (`spawn_type_id`) REFERENCES `spawn_types` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `task_execution_stats`
--
ALTER TABLE `task_execution_stats`
  ADD CONSTRAINT `task_execution_stats_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `task_locks`
--
ALTER TABLE `task_locks`
  ADD CONSTRAINT `task_locks_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_locks_ibfk_2` FOREIGN KEY (`run_id`) REFERENCES `task_runs` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `task_runs`
--
ALTER TABLE `task_runs`
  ADD CONSTRAINT `task_runs_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_runs_ibfk_2` FOREIGN KEY (`lock_id`) REFERENCES `task_locks` (`id`) ON DELETE SET NULL;
COMMIT;




-- v_gallery_characters
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER
VIEW `v_gallery_characters` AS
SELECT
    `f`.`id` AS `frame_id`,
    `f`.`map_run_id` AS `map_run_id`,
    `c`.`id` AS `entity_id`,
    `f`.`filename` AS `filename`,
    `f`.`prompt` AS `prompt`,
    `f`.`style` AS `style`,
    `c`.`id` AS `character_id`,
    `c`.`name` AS `character_name`,
    `c`.`role` AS `character_role`,
    'characters' AS `entity_type`
FROM
    (((`frames` `f`
    JOIN `frames_2_characters` `m` ON (`f`.`id` = `m`.`from_id`))
    JOIN `characters` `c` ON (`m`.`to_id` = `c`.`id`))
    JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE
    `s`.`visible` = 1
ORDER BY
    `s`.`order`,
    `f`.`created_at` DESC;

-- v_gallery_animas
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER
VIEW `v_gallery_animas` AS
SELECT
    `f`.`id` AS `frame_id`,
    `a`.`id` AS `entity_id`,
    `f`.`filename` AS `filename`,
    `f`.`prompt` AS `prompt`,
    `f`.`style` AS `style`,
    `a`.`id` AS `anima_id`,
    `a`.`name` AS `anima_name`,
    `a`.`traits` AS `traits`,
    `a`.`abilities` AS `abilities`,
    `c`.`id` AS `character_id`,
    `c`.`name` AS `character_name`,
    `c`.`role` AS `character_role`,
    'animas' AS `entity_type`
FROM
    ((((`frames` `f`
    JOIN `frames_2_animas` `m` ON (`m`.`from_id` = `f`.`id`))
    JOIN `animas` `a` ON (`a`.`id` = `m`.`to_id`))
    LEFT JOIN `characters` `c` ON (`c`.`id` = `a`.`character_id`))
    JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE
    `s`.`visible` = 1
ORDER BY
    `s`.`order`,
    `f`.`created_at` DESC;

-- v_gallery_backgrounds
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER
VIEW `v_gallery_backgrounds` AS
SELECT
    `f`.`id` AS `frame_id`,
    `b`.`id` AS `entity_id`,
    `f`.`filename` AS `filename`,
    `f`.`prompt` AS `prompt`,
    `f`.`style` AS `style`,
    `b`.`id` AS `background_id`,
    `b`.`name` AS `background_name`,
    `b`.`type` AS `background_type`,
    `l`.`id` AS `location_id`,
    `l`.`name` AS `location_name`,
    'backgrounds' AS `entity_type`
FROM
    ((((`frames` `f`
    JOIN `frames_2_backgrounds` `m` ON (`f`.`id` = `m`.`from_id`))
    JOIN `backgrounds` `b` ON (`m`.`to_id` = `b`.`id`))
    LEFT JOIN `locations` `l` ON (`b`.`location_id` = `l`.`id`))
    JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE
    `s`.`visible` = 1
ORDER BY
    `f`.`created_at` DESC;

-- v_gallery_locations
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER
VIEW `v_gallery_locations` AS
SELECT
    `f`.`id` AS `frame_id`,
    `l`.`id` AS `entity_id`,
    `f`.`filename` AS `filename`,
    `f`.`prompt` AS `prompt`,
    `f`.`style` AS `style`,
    `l`.`id` AS `location_id`,
    `l`.`name` AS `location_name`,
    `l`.`type` AS `location_type`,
    'locations' AS `entity_type`
FROM
    (((`frames` `f`
    JOIN `frames_2_locations` `m` ON (`f`.`id` = `m`.`from_id`))
    JOIN `locations` `l` ON (`m`.`to_id` = `l`.`id`))
    JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE
    `s`.`visible` = 1
ORDER BY
    `f`.`created_at` DESC;



-- Dump for `v_gallery_wall_of_images` (view)
-- Updated: 2025-10-31

CREATE ALGORITHM=UNDEFINED 
DEFINER=`adminer`@`localhost` 
SQL SECURITY DEFINER
VIEW `v_gallery_wall_of_images` AS

SELECT 
    'animas' AS `entity_type`,
    `v_gallery_animas`.`frame_id` AS `frame_id`,
    `v_gallery_animas`.`entity_id` AS `entity_id`,
    `v_gallery_animas`.`filename` AS `filename`,
    `v_gallery_animas`.`prompt` AS `prompt`,
    `v_gallery_animas`.`anima_name` AS `entity_name`
FROM `v_gallery_animas`

UNION ALL

SELECT 
    'artifacts' AS `entity_type`,
    `v_gallery_artifacts`.`frame_id` AS `frame_id`,
    `v_gallery_artifacts`.`entity_id` AS `entity_id`,
    `v_gallery_artifacts`.`filename` AS `filename`,
    `v_gallery_artifacts`.`prompt` AS `prompt`,
    `v_gallery_artifacts`.`artifact_name` AS `entity_name`
FROM `v_gallery_artifacts`

UNION ALL

SELECT 
    'backgrounds' AS `entity_type`,
    `v_gallery_backgrounds`.`frame_id` AS `frame_id`,
    `v_gallery_backgrounds`.`entity_id` AS `entity_id`,
    `v_gallery_backgrounds`.`filename` AS `filename`,
    `v_gallery_backgrounds`.`prompt` AS `prompt`,
    `v_gallery_backgrounds`.`background_name` AS `entity_name`
FROM `v_gallery_backgrounds`

UNION ALL

SELECT 
    'characters' AS `entity_type`,
    `v_gallery_characters`.`frame_id` AS `frame_id`,
    `v_gallery_characters`.`entity_id` AS `entity_id`,
    `v_gallery_characters`.`filename` AS `filename`,
    `v_gallery_characters`.`prompt` AS `prompt`,
    `v_gallery_characters`.`character_name` AS `entity_name`
FROM `v_gallery_characters`

UNION ALL

SELECT 
    'composites' AS `entity_type`,
    `v_gallery_composites`.`frame_id` AS `frame_id`,
    `v_gallery_composites`.`entity_id` AS `entity_id`,
    `v_gallery_composites`.`filename` AS `filename`,
    `v_gallery_composites`.`prompt` AS `prompt`,
    `v_gallery_composites`.`composite_name` AS `entity_name`
FROM `v_gallery_composites`

UNION ALL

SELECT 
    'generatives' AS `entity_type`,
    `v_gallery_generatives`.`frame_id` AS `frame_id`,
    `v_gallery_generatives`.`entity_id` AS `entity_id`,
    `v_gallery_generatives`.`filename` AS `filename`,
    `v_gallery_generatives`.`prompt` AS `prompt`,
    `v_gallery_generatives`.`name` AS `entity_name`
FROM `v_gallery_generatives`

UNION ALL

SELECT 
    'locations' AS `entity_type`,
    `v_gallery_locations`.`frame_id` AS `frame_id`,
    `v_gallery_locations`.`entity_id` AS `entity_id`,
    `v_gallery_locations`.`filename` AS `filename`,
    `v_gallery_locations`.`prompt` AS `prompt`,
    `v_gallery_locations`.`location_name` AS `entity_name`
FROM `v_gallery_locations`

UNION ALL

SELECT 
    'sketches' AS `entity_type`,
    `v_gallery_sketches`.`frame_id` AS `frame_id`,
    `v_gallery_sketches`.`entity_id` AS `entity_id`,
    `v_gallery_sketches`.`filename` AS `filename`,
    `v_gallery_sketches`.`prompt` AS `prompt`,
    `v_gallery_sketches`.`name` AS `entity_name`
FROM `v_gallery_sketches`

UNION ALL

SELECT 
    'vehicles' AS `entity_type`,
    `v_gallery_vehicles`.`frame_id` AS `frame_id`,
    `v_gallery_vehicles`.`entity_id` AS `entity_id`,
    `v_gallery_vehicles`.`filename` AS `filename`,
    `v_gallery_vehicles`.`prompt` AS `prompt`,
    `v_gallery_vehicles`.`vehicle_name` AS `entity_name`
FROM `v_gallery_vehicles`;














INSERT INTO `scheduled_tasks` (`id`, `name`, `order`, `script_path`, `args`, `schedule_time`, `schedule_interval`, `schedule_dow`, `last_run`, `active`, `description`, `max_concurrent_runs`, `lock_timeout_minutes`, `require_lock`, `lock_scope`, `created_at`, `updated_at`, `run_now`) VALUES
(27,	'rs 📡 restart servers',	3,	'/var/www/sage/bash/restart_servers.sh',	NULL,	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-11-05 01:30:29',	1,	NULL,	1,	60,	1,	'global',	'2025-11-05 01:25:24',	'2025-11-05 01:40:42',	0),
(28,	'ac 👁️ analyze code',	6,	'/var/www/sage/bash/analyze_code_src.sh',	'',	NULL,	NULL,	'0,1,2,3,4,5,6',	'2025-11-05 01:56:01',	1,	NULL,	1,	60,	1,	'global',	'2025-11-05 01:38:05',	'2025-11-05 01:56:01',	0);
-- 2025-11-06 13:00:48 UTC



ALTER TABLE animas
MODIFY character_id INT NULL;


-- For the "locations" table
ALTER TABLE `locations`
    ADD COLUMN `img2img_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `cnmap_frame_id` int(11) DEFAULT NULL,
    ADD COLUMN `cnmap_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap_prompt` text DEFAULT NULL;

-- For the "spawns" table
ALTER TABLE `spawns`
    ADD COLUMN `img2img_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `cnmap_frame_id` int(11) DEFAULT NULL,
    ADD COLUMN `cnmap_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap_prompt` text DEFAULT NULL;

-- For the "vehicles" table
ALTER TABLE `vehicles`
    ADD COLUMN `img2img_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `cnmap_frame_id` int(11) DEFAULT NULL,
    ADD COLUMN `cnmap_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap_prompt` text DEFAULT NULL;

-- For the "animas" table
ALTER TABLE `animas`
    ADD COLUMN `img2img_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `cnmap_frame_id` int(11) DEFAULT NULL,
    ADD COLUMN `cnmap_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap_prompt` text DEFAULT NULL;

-- For the "backgrounds" table
ALTER TABLE `backgrounds`
    ADD COLUMN `img2img_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `cnmap_frame_id` int(11) DEFAULT NULL,
    ADD COLUMN `cnmap_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap_prompt` text DEFAULT NULL;

-- For the "artifacts" table
ALTER TABLE `artifacts`
    ADD COLUMN `img2img_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `cnmap_frame_id` int(11) DEFAULT NULL,
    ADD COLUMN `cnmap_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap_prompt` text DEFAULT NULL;

ALTER TABLE `controlnet_maps`
    ADD COLUMN `img2img_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap` tinyint(1) NOT NULL DEFAULT 0,
    ADD COLUMN `cnmap_frame_id` int(11) DEFAULT NULL,
    ADD COLUMN `cnmap_frame_filename` varchar(100) DEFAULT NULL,
    ADD COLUMN `cnmap_prompt` text DEFAULT NULL;


-- ---------------



-- sketch_templates table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data with 20 templates
INSERT INTO `sketch_templates` 
(`name`, `core_idea`, `shot_type`, `camera_angle`, `perspective`, `entity_slots`, `tags`, `example_prompt`, `entity_type`) 
VALUES
('Establishing Shot – Wide Location', 'The audience learns where the story is taking place', 'ESTABLISHING', 'HIGH', 'WIDE', '["ENVIRONMENT"]', '["establishing","wide","location","exterior"]', 'Extreme wide angle view of {{ENVIRONMENT}}, cinematic establishing shot, 2-point perspective, dramatic lighting', 'sketches'),

('Establishing Shot – Interior Room', 'Shows the interior of a building and gives a sense of scale', 'ESTABLISHING', 'HIGH', 'WIDE', '["ENVIRONMENT","PROP"]', '["interior","room","establishing","wide"]', 'Wide angle interior of {{ENVIRONMENT}}, {{PROP}} in foreground, soft ambient lighting, architectural detail', 'sketches'),

('Two-Shot Dialogue – Over-Shoulder', 'Two characters converse, focus on interaction', 'OVER_SHOULDER', 'EYE', 'MEDIUM', '["CHARACTER","CHARACTER"]', '["dialogue","two-shot","over-shoulder","conversation"]', 'Over-shoulder shot, {{CHARACTER_1}} facing {{CHARACTER_2}}, medium shot, eye level, natural dialogue framing', 'sketches'),

('Close-Up Profile – Emotional Beat', 'Single character emotional reaction from the side', 'CLOSE_UP', 'EYE', 'CLOSE', '["CHARACTER"]', '["close-up","profile","emotion","intimate"]', 'Profile close-up of {{CHARACTER}}, emotional expression, shallow depth of field, cinematic lighting', 'sketches'),

('Extreme Close-Up (ECU) – Detail', 'Highlights a specific detail', 'ECU', 'EYE', 'EXTREME_CLOSE', '["DETAIL"]', '["detail","ecu","focus","texture"]', 'Extreme close-up of {{DETAIL}}, macro detail, sharp focus, dramatic texture', 'sketches'),

('Two-Shot – Low Angle Power Stance', 'Two characters, power dynamics', 'TWO_SHOT', 'LOW', 'MEDIUM', '["CHARACTER","CHARACTER"]', '["low-angle","power","two-shot","dramatic"]', 'Low angle shot, {{CHARACTER_1}} and {{CHARACTER_2}} in power stance, dramatic perspective, intimidating framing', 'sketches'),

('Group Interaction – Wide-Mid', 'Small group interacting in location', 'TWO_SHOT', 'EYE', 'WIDE', '["CHARACTER_GROUP","ENVIRONMENT"]', '["group","interaction","wide-mid","dynamic"]', 'Wide mid-shot of {{CHARACTER_GROUP}} in {{ENVIRONMENT}}, dynamic group composition, 35mm lens', 'sketches'),

('Point-of-View (POV) – First Person', 'Scene through character eyes', 'POV', 'EYE', 'POV', '["CHARACTER"]', '["pov","first-person","immersive"]', 'First person POV from {{CHARACTER}} perspective, immersive view, slight motion blur', 'sketches'),

('Insert Shot – Object Action', 'Focus on prop being used', 'INSERT', 'EYE', 'CLOSE', '["PROP"]', '["insert","prop","action","detail"]', 'Insert shot of {{PROP}}, action detail, shallow depth of field, cinematic focus', 'sketches'),

('Tracking Shot – Walk', 'Character walks through location', 'TRACKING', 'EYE', 'MEDIUM', '["CHARACTER","ENVIRONMENT"]', '["tracking","walk","movement","establishing"]', 'Tracking shot following {{CHARACTER}} through {{ENVIRONMENT}}, continuous motion, cinematic movement', 'sketches'),

('Crane Shot – Reveal', 'Camera lifts to reveal larger scene', 'CRANE', 'HIGH', 'WIDE', '["ENVIRONMENT"]', '["crane","reveal","aerial","scope"]', 'Crane shot revealing {{ENVIRONMENT}}, ascending camera movement, epic reveal', 'sketches'),

('Dutch Angle – Disorientation', 'World feels off-balance', 'DUTCH', 'SLIGHT_DUTCH', 'MEDIUM', '["CHARACTER","ENVIRONMENT"]', '["dutch","tilt","tension","unsettling"]', 'Dutch angle of {{CHARACTER}} in {{ENVIRONMENT}}, 20-degree tilt, unsettling composition', 'sketches'),

('Overhead Shot – Map View', 'Birds-eye view of location', 'OVERHEAD', 'TOP', 'OVERHEAD', '["ENVIRONMENT"]', '["overhead","map","strategy","layout"]', 'Top-down overhead view of {{ENVIRONMENT}}, orthographic perspective, strategic layout', 'sketches'),

('Medium Shot – Action Cut-In', 'Character mid-action', 'INSERT', 'LOW', 'MEDIUM', '["CHARACTER","PROP"]', '["action","cut-in","medium","dynamic"]', 'Medium shot of {{CHARACTER}} with {{PROP}}, dynamic action pose, motion emphasis', 'sketches'),

('Silhouette – Backlit', 'Characters as dark shapes against light', 'SILHOUETTE', 'EYE', 'WIDE', '["CHARACTER"]', '["silhouette","backlit","mood","dramatic"]', 'Silhouette of {{CHARACTER}} against bright background, strong backlight, dramatic contrast', 'sketches'),

('Reflection Shot – Mirror/Water', 'Scene through reflective surface', 'REFLECTION', 'EYE', 'MEDIUM', '["CHARACTER","ENVIRONMENT"]', '["reflection","mirror","water","metaphor"]', 'Reflection shot of {{CHARACTER}} in {{ENVIRONMENT}}, mirror or water surface, subtle distortion', 'sketches'),

('Slow-Motion Close-Up – Impact', 'Key moment slowed for emphasis', 'SLOW_MO', 'EYE', 'EXTREME_CLOSE', '["DETAIL"]', '["slow-mo","impact","detail","dramatic"]', 'Slow motion extreme close-up of {{DETAIL}}, high frame rate, impact moment', 'sketches'),

('Rooftop – Wide Panorama', 'Characters on height looking out', 'ESTABLISHING', 'HIGH', 'WIDE', '["CHARACTER","ENVIRONMENT"]', '["rooftop","panorama","height","exposure"]', 'Wide panoramic shot, {{CHARACTER}} on rooftop overlooking {{ENVIRONMENT}}, sense of scale', 'sketches'),

('Scene with Many Characters', 'Crowded scene with multiple people', 'TWO_SHOT', 'EYE', 'WIDE', '["CHARACTER_GROUP","ENVIRONMENT"]', '["crowd","busy","ensemble","scene"]', 'Wide shot of multiple characters in {{ENVIRONMENT}}, bustling scene composition, ensemble cast', 'sketches'),

('Close-Up of Two Persons', 'Intimate two-person close frame', 'CLOSE_UP', 'EYE', 'CLOSE', '["CHARACTER","CHARACTER"]', '["intimate","close","two-person","emotion"]', 'Close framing of {{CHARACTER_1}} and {{CHARACTER_2}}, intimate proximity, emotional connection', 'sketches');






DROP TABLE IF EXISTS `design_axes`;
CREATE TABLE `design_axes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `axis_name` varchar(128) NOT NULL,
  `pole_left` varchar(128) NOT NULL,
  `pole_right` varchar(128) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


DROP TABLE IF EXISTS `style_profiles`;
CREATE TABLE `style_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_name` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `json_payload` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_name` (`profile_name`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- 2025-11-06 13:16:07 UTC





INSERT INTO `design_axes` (`id`, `axis_name`, `pole_left`, `pole_right`, `notes`, `created_at`) VALUES
(1,	'Elegant vs Brutalist',	'Elegant',	'Brutalist',	NULL,	'2025-11-03 20:49:58'),
(2,	'Industrial vs Alien',	'Industrial',	'Alien',	NULL,	'2025-11-03 20:49:58'),
(3,	'Quiet vs Chaotic',	'Quiet',	'Chaotic',	NULL,	'2025-11-03 20:49:58'),
(4,	'Matte vs Glossy',	'Matte',	'Glossy',	NULL,	'2025-11-03 20:49:58'),
(5,	'Monochrome vs Subtle Palette',	'Monochrome',	'Subtle Palette',	NULL,	'2025-11-03 20:49:58'),
(6,	'Ancient vs Forgotten Future',	'Ancient',	'Forgotten Future',	NULL,	'2025-11-03 20:49:58'),
(7,	'Hand-forged vs Parametric-grown',	'Hand-forged',	'Parametric-grown',	NULL,	'2025-11-03 20:49:58'),
(8,	'Human-centric vs Machine-centric',	'Human-Centric',	'Machine-Centric',	NULL,	'2025-11-03 20:49:58'),
(9,	'Organic Decay vs Clean Sterile',	'Organic Decay',	'Clean Sterile',	NULL,	'2025-11-03 20:49:58'),
(10,	'Heavy Mass vs Lightweight Skeletal',	'Heavy Mass',	'Lightweight Skeletal',	NULL,	'2025-11-03 20:49:58'),
(11,	'Plasma Torch vs Cold Steel',	'Plasma Torch',	'Cold Steel',	NULL,	'2025-11-03 20:49:58'),
(12,	'Baroque vs Geometric',	'Baroque',	'Geometric',	NULL,	'2025-11-03 20:49:58'),
(13,	'Dense Narrative vs Minimal Symbolic',	'Dense Narrative',	'Minimal Symbolic',	NULL,	'2025-11-03 20:49:58'),
(14,	'Warm Candle vs Harsh Spectral',	'Warm Candle',	'Harsh Spectral',	NULL,	'2025-11-03 20:49:58'),
(15,	'Scarce Precious vs Abundant Disposable',	'Scarce Precious',	'Abundant Disposable',	NULL,	'2025-11-03 20:49:58'),
(16,	'Myth Logic vs Metric Logic',	'Myth Logic',	'Metric Logic',	NULL,	'2025-11-03 20:49:58'),
(17,	'Mastery vs Automation',	'Mastery',	'Automation',	NULL,	'2025-11-03 20:49:58'),
(18,	'Ritual Assembly vs Modular System',	'Ritual Assembly',	'Modular System',	NULL,	'2025-11-03 20:49:58'),
(19,	'Tactile vs Telepresent',	'Tactile',	'Telepresent',	NULL,	'2025-11-03 20:49:58'),
(20,	'Local Unique vs Universal Standard',	'Local Unique',	'Universal Standard',	NULL,	'2025-11-03 20:49:58'),
(21,	'Steel vs Polymer',	'Steel',	'Polymer',	NULL,	'2025-11-03 20:49:58');
-- 2025-11-06 17:08:47 UTC







/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
