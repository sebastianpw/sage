-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 19, 2026 at 03:49 PM
-- Server version: 12.2.2-MariaDB
-- PHP Version: 8.5.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `starlight_guardians_nu`
--

-- --------------------------------------------------------

--
-- Table structure for table `ag_categories`
--

CREATE TABLE `ag_categories` (
  `id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ag_nodes`
--

CREATE TABLE `ag_nodes` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ag_node_items`
--

CREATE TABLE `ag_node_items` (
  `id` int(11) NOT NULL,
  `node_id` int(11) NOT NULL,
  `item_type` varchar(100) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_label` varchar(1024) DEFAULT NULL,
  `relationship` varchar(1024) DEFAULT NULL,
  `note` longtext DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `doc_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `animas`
--

CREATE TABLE `animas` (
  `id` int(11) NOT NULL,
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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `animatics`
--

CREATE TABLE `animatics` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `animatic_audios`
--

CREATE TABLE `animatic_audios` (
  `animatic_id` int(11) NOT NULL,
  `audio_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `animatic_cnmap_frames`
--

CREATE TABLE `animatic_cnmap_frames` (
  `animatic_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `animatic_frames`
--

CREATE TABLE `animatic_frames` (
  `animatic_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `animatic_meshes`
--

CREATE TABLE `animatic_meshes` (
  `animatic_id` int(11) NOT NULL,
  `mesh_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `animatic_videos`
--

CREATE TABLE `animatic_videos` (
  `animatic_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `animation_mouthshapes`
--

CREATE TABLE `animation_mouthshapes` (
  `id` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_action_effects`
--

CREATE TABLE `anivoc_action_effects` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_backgrounds`
--

CREATE TABLE `anivoc_backgrounds` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_categories`
--

CREATE TABLE `anivoc_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `item_count` int(10) UNSIGNED DEFAULT 0,
  `is_complete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_character_states`
--

CREATE TABLE `anivoc_character_states` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_chibi_modes`
--

CREATE TABLE `anivoc_chibi_modes` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_color_coding`
--

CREATE TABLE `anivoc_color_coding` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_duo_compositions`
--

CREATE TABLE `anivoc_duo_compositions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_expressions`
--

CREATE TABLE `anivoc_expressions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `use_case` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_hand_gestures`
--

CREATE TABLE `anivoc_hand_gestures` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_lighting`
--

CREATE TABLE `anivoc_lighting` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_motion_impact`
--

CREATE TABLE `anivoc_motion_impact` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_panel_frame`
--

CREATE TABLE `anivoc_panel_frame` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_scale_perspective`
--

CREATE TABLE `anivoc_scale_perspective` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_scene_functions`
--

CREATE TABLE `anivoc_scene_functions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `anime_notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_shot_pacing`
--

CREATE TABLE `anivoc_shot_pacing` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_symbolic_objects`
--

CREATE TABLE `anivoc_symbolic_objects` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_text_graphics`
--

CREATE TABLE `anivoc_text_graphics` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anivoc_transitions`
--

CREATE TABLE `anivoc_transitions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `artifacts`
--

CREATE TABLE `artifacts` (
  `id` int(11) NOT NULL,
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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios`
--

CREATE TABLE `audios` (
  `id` int(11) NOT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `rvc_model_name` varchar(100) DEFAULT NULL,
  `pitch_shift` int(11) DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_audio_ambiences`
--

CREATE TABLE `audios_2_audio_ambiences` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_audio_cues`
--

CREATE TABLE `audios_2_audio_cues` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_audio_dialogue_lines`
--

CREATE TABLE `audios_2_audio_dialogue_lines` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_audio_foleys`
--

CREATE TABLE `audios_2_audio_foleys` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_audio_fxsounds`
--

CREATE TABLE `audios_2_audio_fxsounds` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_audio_themes`
--

CREATE TABLE `audios_2_audio_themes` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_daw_projects`
--

CREATE TABLE `audios_2_daw_projects` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_documentations`
--

CREATE TABLE `audios_2_documentations` (
  `id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audios_2_editorial_shots`
--

CREATE TABLE `audios_2_editorial_shots` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_ambiences`
--

CREATE TABLE `audio_ambiences` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Environmental sound description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_counter`
--

CREATE TABLE `audio_counter` (
  `id` int(11) NOT NULL DEFAULT 1,
  `next_audio` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_cues`
--

CREATE TABLE `audio_cues` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Score cue description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_dialogue_lines`
--

CREATE TABLE `audio_dialogue_lines` (
  `id` int(11) NOT NULL,
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
  `active_audio_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_foleys`
--

CREATE TABLE `audio_foleys` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Foley action description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_fxsounds`
--

CREATE TABLE `audio_fxsounds` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Designed or synthetic sound effect',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_themes`
--

CREATE TABLE `audio_themes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'Narrative or musical theme description',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_audios` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate audios',
  `active_map_run_id` int(11) DEFAULT NULL,
  `wav2wav` tinyint(1) NOT NULL DEFAULT 0,
  `wav2wav_audio_id` int(11) DEFAULT NULL,
  `wav2wav_audio_filename` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_voice_identity`
--

CREATE TABLE `audio_voice_identity` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `model_path` varchar(255) DEFAULT NULL COMMENT 'RVC model or voice reference',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_voice_identity_xmpl`
--

CREATE TABLE `audio_voice_identity_xmpl` (
  `id` int(11) NOT NULL,
  `voice_identity_id` int(11) NOT NULL COMMENT 'FK → audio_voice_identity.id',
  `dialogue_line_id` int(11) NOT NULL COMMENT 'FK → audio_dialogue_lines.id (the xmpl sentinel line)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backgrounds`
--

CREATE TABLE `backgrounds` (
  `id` int(11) NOT NULL,
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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backups_media`
--

CREATE TABLE `backups_media` (
  `id` int(11) NOT NULL,
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
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_destinations`
--

CREATE TABLE `backup_destinations` (
  `id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name` varchar(100) NOT NULL COMMENT 'Human label, e.g. "Tablet SCP"',
  `slug` varchar(60) NOT NULL COMMENT 'Machine key, e.g. tablet_scp',
  `type` enum('scp','local') NOT NULL DEFAULT 'scp',
  `host_mode` enum('static','ap0_scan') NOT NULL DEFAULT 'ap0_scan' COMMENT 'static = fixed IP, ap0_scan = hotspot auto-detect',
  `host` varchar(255) DEFAULT NULL COMMENT 'IP or hostname when host_mode=static',
  `port` smallint(5) UNSIGNED NOT NULL DEFAULT 8022,
  `remote_base` varchar(500) NOT NULL DEFAULT 'sage_backup' COMMENT 'Base folder on remote device',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_jobs`
--

CREATE TABLE `backup_jobs` (
  `id` int(11) NOT NULL,
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
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_runs`
--

CREATE TABLE `backup_runs` (
  `id` int(11) NOT NULL,
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
  `log_text` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `boards`
--

CREATE TABLE `boards` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','archived','production') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `boards_categories`
--

CREATE TABLE `boards_categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `boards_items`
--

CREATE TABLE `boards_items` (
  `id` int(11) NOT NULL,
  `board_id` int(11) NOT NULL,
  `item_type` varchar(50) NOT NULL COMMENT 'e.g. map_run, frame, character, md_doc',
  `item_id` int(11) NOT NULL,
  `note` text DEFAULT NULL COMMENT 'Production notes specific to this assignment',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `camera_angles`
--

CREATE TABLE `camera_angles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `camera_perspectives`
--

CREATE TABLE `camera_perspectives` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `characters`
--

CREATE TABLE `characters` (
  `id` int(11) NOT NULL,
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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `character_anima_poses`
--

CREATE TABLE `character_anima_poses` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `character_expressions`
--

CREATE TABLE `character_expressions` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `character_poses`
--

CREATE TABLE `character_poses` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_message`
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
-- Table structure for table `chat_session`
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
-- Table structure for table `chat_summary`
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
-- Table structure for table `chroma_collections`
--

CREATE TABLE `chroma_collections` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Exact Chroma collection name',
  `type` enum('text','image') NOT NULL COMMENT 'Type of embeddings stored',
  `description` text DEFAULT NULL COMMENT 'Human-readable description',
  `dimension` int(11) DEFAULT NULL COMMENT 'Embedding dimension (e.g., 384, 512)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cinemagics`
--

CREATE TABLE `cinemagics` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Untitled Cinemagic',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cinemagics_2_sequences`
--

CREATE TABLE `cinemagics_2_sequences` (
  `cinemagic_id` int(11) NOT NULL,
  `sequence_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `chapter_label` varchar(100) DEFAULT NULL COMMENT 'Optional override label e.g. "Episode 1" or "Chapter: The Awakening"'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cinemagic_hub_posts`
--

CREATE TABLE `cinemagic_hub_posts` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Cinemagic Hub — cinematic content organised by series/season/episode';

-- --------------------------------------------------------

--
-- Table structure for table `cinemagic_series`
--

CREATE TABLE `cinemagic_series` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `asset_url_prefix` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cover_image_url` varchar(512) DEFAULT NULL,
  `template` varchar(50) DEFAULT 'default',
  `supported_languages` varchar(255) DEFAULT 'en',
  `seo_keywords` text DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------

--
-- Table structure for table `cinemagic_series_2_cinemagics`
--

CREATE TABLE `cinemagic_series_2_cinemagics` (
  `series_id` int(11) NOT NULL,
  `cinemagic_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clipboard_items`
--

CREATE TABLE `clipboard_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `label` varchar(120) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clipboard_visibility`
--

CREATE TABLE `clipboard_visibility` (
  `id` int(10) UNSIGNED NOT NULL,
  `clipboard_item_id` int(10) UNSIGNED NOT NULL,
  `view_area` varchar(80) NOT NULL DEFAULT 'global',
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `color_grade_presets`
--

CREATE TABLE `color_grade_presets` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `settings_json` longtext NOT NULL COMMENT 'Grade settings — same schema as color_grade_profiles.settings_json',
  `thumbnail_frame_id` int(11) DEFAULT NULL COMMENT 'Optional: frame used as preview thumbnail',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `color_grade_profiles`
--

CREATE TABLE `color_grade_profiles` (
  `id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL COMMENT 'Source frame this grade was applied to',
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `preset_id` int(11) DEFAULT NULL COMMENT 'FK to color_grade_presets if saved from a preset',
  `settings_json` longtext NOT NULL COMMENT 'Full grade settings object',
  `derived_frame_id` int(11) DEFAULT NULL COMMENT 'The new frame produced by Pillow render',
  `map_run_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `composites`
--

CREATE TABLE `composites` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `composite_audios`
--

CREATE TABLE `composite_audios` (
  `composite_id` int(11) NOT NULL,
  `audio_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `composite_frames`
--

CREATE TABLE `composite_frames` (
  `composite_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_elements`
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
-- Table structure for table `content_hub_posts`
--

CREATE TABLE `content_hub_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `post_type` enum('image_grid','image_swiper','video_playlist','youtube_playlist','url_reference','story','reel','thread','scrollmagic_gallery','cinematic_story','anime_gallery','narrative_gallery','spatial_viewer') NOT NULL DEFAULT 'image_grid',
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Content Hub — Social media post planning & scheduling';

-- --------------------------------------------------------

--
-- Table structure for table `continuity_jobs`
--

CREATE TABLE `continuity_jobs` (
  `id` int(11) NOT NULL,
  `sketch_id` int(11) NOT NULL COMMENT 'FK → sketches.id',
  `character_id` int(11) NOT NULL COMMENT 'FK → characters.id',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Character order within a sketch (for deterministic prompt assembly)',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | running | done | failed | skipped',
  `cont_gen_id` int(11) DEFAULT NULL COMMENT 'Override continuity generator_config.id (NULL = use CLI default)',
  `result_text` longtext DEFAULT NULL COMMENT 'Final scene_prompt returned by the AI',
  `error_msg` text DEFAULT NULL COMMENT 'Last error message if status = failed',
  `attempts` tinyint(3) NOT NULL DEFAULT 0 COMMENT 'Number of AI call attempts made',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defines which characters must be applied via continuity to which sketches.';

-- --------------------------------------------------------

--
-- Table structure for table `controlnet_maps`
--

CREATE TABLE `controlnet_maps` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daw_projects`
--

CREATE TABLE `daw_projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daw_project_files`
--

CREATE TABLE `daw_project_files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `state_data` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daw_shot_saves`
--

CREATE TABLE `daw_shot_saves` (
  `id` int(11) NOT NULL,
  `shot_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_data` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `derivates`
--

CREATE TABLE `derivates` (
  `id` int(11) NOT NULL,
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
  `vid2vid_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `design_axes`
--

CREATE TABLE `design_axes` (
  `id` int(10) UNSIGNED NOT NULL,
  `axis_name` varchar(128) NOT NULL,
  `axis_group` varchar(64) DEFAULT 'visual_style',
  `category` varchar(128) DEFAULT NULL,
  `pole_left` varchar(128) NOT NULL,
  `pole_right` varchar(128) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dict_dictionaries`
--

CREATE TABLE `dict_dictionaries` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `source_author` varchar(255) DEFAULT NULL COMMENT 'e.g., Henry Miller',
  `source_title` varchar(255) DEFAULT NULL COMMENT 'e.g., Tropic of Cancer',
  `language_code` varchar(10) DEFAULT 'en' COMMENT 'ISO language code',
  `total_lemmas` int(11) DEFAULT 0 COMMENT 'Cached count',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dict_lemmas`
--

CREATE TABLE `dict_lemmas` (
  `id` int(11) NOT NULL,
  `lemma` varchar(255) NOT NULL COMMENT 'Base form of the word',
  `language_code` varchar(10) DEFAULT 'en',
  `pos` varchar(50) DEFAULT NULL COMMENT 'Part of speech: noun, verb, adj, etc.',
  `frequency` int(11) DEFAULT 1 COMMENT 'Global frequency count',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dict_lemma_2_dictionary`
--

CREATE TABLE `dict_lemma_2_dictionary` (
  `id` int(11) NOT NULL,
  `dictionary_id` int(11) NOT NULL,
  `lemma_id` int(11) NOT NULL,
  `frequency_in_dict` int(11) DEFAULT 1 COMMENT 'How often this lemma appears in this dictionary',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dict_source_files`
--

CREATE TABLE `dict_source_files` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dimensionals`
--

CREATE TABLE `dimensionals` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `active_map_run_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dimensionals_2_meshes`
--

CREATE TABLE `dimensionals_2_meshes` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documentations`
--

CREATE TABLE `documentations` (
  `id` int(11) NOT NULL,
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
  `active_map_run_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documentation_categories`
--

CREATE TABLE `documentation_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_episodes`
--

CREATE TABLE `editorial_episodes` (
  `id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_scenes`
--

CREATE TABLE `editorial_scenes` (
  `id` int(11) NOT NULL,
  `sequence_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `int_ext` enum('INT','EXT','INT/EXT') DEFAULT 'INT',
  `time_of_day` enum('DAY','NIGHT','DAWN','DUSK','SPACE') DEFAULT 'DAY',
  `sort_order` int(11) DEFAULT 0,
  `directory` varchar(255) DEFAULT NULL COMMENT 'Relative path like /editorial/scene_001',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_seasons`
--

CREATE TABLE `editorial_seasons` (
  `id` int(11) NOT NULL,
  `series_id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_sequences`
--

CREATE TABLE `editorial_sequences` (
  `id` int(11) NOT NULL,
  `episode_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_series`
--

CREATE TABLE `editorial_series` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_shots`
--

CREATE TABLE `editorial_shots` (
  `id` int(11) NOT NULL,
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
  `audio_notes` text DEFAULT NULL COMMENT 'Global audio notes for this shot'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_shots_2_audio_ambiences`
--

CREATE TABLE `editorial_shots_2_audio_ambiences` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_ambiences.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_shots_2_audio_cues`
--

CREATE TABLE `editorial_shots_2_audio_cues` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_cues.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_shots_2_audio_foleys`
--

CREATE TABLE `editorial_shots_2_audio_foleys` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_foleys.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_shots_2_audio_fxsounds`
--

CREATE TABLE `editorial_shots_2_audio_fxsounds` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_fxsounds.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_shots_2_audio_themes`
--

CREATE TABLE `editorial_shots_2_audio_themes` (
  `from_id` int(11) NOT NULL COMMENT 'editorial_shots.id',
  `to_id` int(11) NOT NULL COMMENT 'audio_themes.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `editorial_shot_dialogues`
--

CREATE TABLE `editorial_shot_dialogues` (
  `id` int(11) NOT NULL,
  `shot_id` int(11) NOT NULL,
  `dialogue_line_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_flags`
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
-- Table structure for table `factions`
--

CREATE TABLE `factions` (
  `id` int(11) NOT NULL,
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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_notes`
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
-- Table structure for table `forge_jobs`
--

CREATE TABLE `forge_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `job_type` enum('kg_sketch','lore_sketch','autopilot','md_curator_extract','md_curator_aggregate','narrative_sequence_compose','sketch_tag_extract','github_sync','translation_compose','overlay_compose') NOT NULL,
  `label` varchar(255) NOT NULL DEFAULT '',
  `status` enum('pending','processing','done','failed','cancelled') NOT NULL DEFAULT 'pending',
  `priority` tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  `payload` longtext NOT NULL DEFAULT json_object(),
  `result` longtext DEFAULT NULL,
  `error_msg` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='CLI Forge Hub — queued jobs for all five CLI pipeline pairs';

-- --------------------------------------------------------

--
-- Table structure for table `forge_tool_settings`
--

CREATE TABLE `forge_tool_settings` (
  `id` int(11) NOT NULL,
  `settings_json` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames`
--

CREATE TABLE `frames` (
  `id` int(11) NOT NULL,
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
  `rating` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_animas`
--

CREATE TABLE `frames_2_animas` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_animatics`
--

CREATE TABLE `frames_2_animatics` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_artifacts`
--

CREATE TABLE `frames_2_artifacts` (
  `id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_backgrounds`
--

CREATE TABLE `frames_2_backgrounds` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_characters`
--

CREATE TABLE `frames_2_characters` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_character_anima_poses`
--

CREATE TABLE `frames_2_character_anima_poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_character_expressions`
--

CREATE TABLE `frames_2_character_expressions` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_character_poses`
--

CREATE TABLE `frames_2_character_poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_composites`
--

CREATE TABLE `frames_2_composites` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_controlnet_maps`
--

CREATE TABLE `frames_2_controlnet_maps` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_factions`
--

CREATE TABLE `frames_2_factions` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_generatives`
--

CREATE TABLE `frames_2_generatives` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_locations`
--

CREATE TABLE `frames_2_locations` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_pastebin`
--

CREATE TABLE `frames_2_pastebin` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_prompt_matrix_blueprints`
--

CREATE TABLE `frames_2_prompt_matrix_blueprints` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_scene_parts`
--

CREATE TABLE `frames_2_scene_parts` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_sketches`
--

CREATE TABLE `frames_2_sketches` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_spawns`
--

CREATE TABLE `frames_2_spawns` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_2_vehicles`
--

CREATE TABLE `frames_2_vehicles` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frames_chains`
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
-- Table structure for table `frames_failed`
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
-- Table structure for table `frames_trashcan`
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
-- Table structure for table `frame_counter`
--

CREATE TABLE `frame_counter` (
  `id` int(11) NOT NULL DEFAULT 1,
  `next_frame` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frame_enhancements`
--

CREATE TABLE `frame_enhancements` (
  `id` int(11) NOT NULL,
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
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate images'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frame_enhancement_frames`
--

CREATE TABLE `frame_enhancement_frames` (
  `frame_enhancement_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuzz_candidates`
--

CREATE TABLE `fuzz_candidates` (
  `id` int(11) NOT NULL,
  `label` varchar(512) NOT NULL COMMENT 'Canonical concept label',
  `concept_type` varchar(100) DEFAULT NULL COMMENT 'character, location, faction, artifact, event, concept, relationship, other',
  `status` enum('extracted','grouped','reviewed','promoted','canonized','rejected','deferred') NOT NULL DEFAULT 'extracted',
  `confidence` tinyint(3) UNSIGNED NOT NULL DEFAULT 50 COMMENT '0-100',
  `notes` longtext DEFAULT NULL,
  `kg_node_id` int(11) DEFAULT NULL COMMENT 'Resolved canonical kg_nodes.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuzz_candidate_aliases`
--

CREATE TABLE `fuzz_candidate_aliases` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `alias` varchar(512) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuzz_links`
--

CREATE TABLE `fuzz_links` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL COMMENT 'Source candidate',
  `target_candidate_id` int(11) NOT NULL COMMENT 'Target candidate',
  `relationship_type` varchar(100) NOT NULL DEFAULT 'may_refer_to',
  `confidence` tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuzz_mentions`
--

CREATE TABLE `fuzz_mentions` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `source_table` varchar(100) NOT NULL COMMENT 'Origin table name',
  `source_row_id` int(11) DEFAULT NULL,
  `source_field` varchar(100) DEFAULT NULL,
  `mention_type` varchar(100) DEFAULT NULL COMMENT 'sketch_name, sketch_desc, analysis_entity, analysis_thematic, kg_node, lore_history, ingredient, manual',
  `extracted_text` text NOT NULL,
  `normalized_text` varchar(512) DEFAULT NULL,
  `context_snippet` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuzz_queue`
--

CREATE TABLE `fuzz_queue` (
  `id` int(11) NOT NULL,
  `source_table` varchar(100) NOT NULL,
  `source_row_id` int(11) DEFAULT NULL,
  `source_field` varchar(100) DEFAULT NULL,
  `mention_type` varchar(100) DEFAULT NULL,
  `extracted_text` text NOT NULL,
  `normalized_text` varchar(512) DEFAULT NULL,
  `context_snippet` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuzz_resolutions`
--

CREATE TABLE `fuzz_resolutions` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `kg_node_id` int(11) DEFAULT NULL,
  `outcome` varchar(100) NOT NULL DEFAULT 'promoted',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuzz_reviews`
--

CREATE TABLE `fuzz_reviews` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `decision` varchar(100) NOT NULL COMMENT 'confirmed, rejected, deferred, split, promoted, linked, unresolved',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generated_phrase_maps`
--

CREATE TABLE `generated_phrase_maps` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_hash` varchar(64) NOT NULL,
  `profile_id` int(11) DEFAULT NULL,
  `model_name` varchar(128) NOT NULL,
  `prompt` text DEFAULT NULL,
  `phrase_map_json` longtext NOT NULL,
  `raw_model_response` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `usage_count` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generatives`
--

CREATE TABLE `generatives` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generator_config`
--

CREATE TABLE `generator_config` (
  `id` int(11) NOT NULL,
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
  `list_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Order for drag-and-drop listing'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Generator configurations. user_id=NULL indicates public/system generators accessible to all users.';

-- --------------------------------------------------------

--
-- Table structure for table `generator_config_display_area`
--

CREATE TABLE `generator_config_display_area` (
  `id` int(11) NOT NULL,
  `area_key` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Configurable display areas for generators';

-- --------------------------------------------------------

--
-- Table structure for table `generator_config_history`
--

CREATE TABLE `generator_config_history` (
  `id` int(11) NOT NULL,
  `generator_config_id` int(11) NOT NULL,
  `config_hash` varchar(32) NOT NULL COMMENT 'MD5 of the snapshot data for quick lookup',
  `snapshot_data` longtext NOT NULL CHECK (json_valid(`snapshot_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generator_config_to_display_area`
--

CREATE TABLE `generator_config_to_display_area` (
  `generator_config_id` int(11) NOT NULL,
  `display_area_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gs_assign_config`
--

CREATE TABLE `gs_assign_config` (
  `id` int(11) NOT NULL,
  `label` varchar(120) NOT NULL DEFAULT '' COMMENT 'Human-readable label for UI',
  `entity_type` enum('character_poses','character_expressions','character_anima_poses','locations') NOT NULL,
  `source_id` int(11) NOT NULL COMMENT 'character_id (or location_id etc.) in the source entity table',
  `node_id` int(11) NOT NULL COMMENT 'video_tree_nodes.id to assign matching videos into',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Maps entity source_id (e.g. character_id) to a video_tree_nodes node for batch assignment';

-- --------------------------------------------------------

--
-- Table structure for table `image_edits`
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
-- Table structure for table `image_stash`
--

CREATE TABLE `image_stash` (
  `id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interactions`
--

CREATE TABLE `interactions` (
  `id` int(11) NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `interaction_group` varchar(50) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `example_prompt` text NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `json_categories`
--

CREATE TABLE `json_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `json_files`
--

CREATE TABLE `json_files` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL CHECK (json_valid(`content`)),
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kg_categories`
--

CREATE TABLE `kg_categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kg_nodes`
--

CREATE TABLE `kg_nodes` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `node_type` varchar(100) DEFAULT 'note' COMMENT 'e.g. relationship, character, event, location, concept',
  `content` longtext DEFAULT NULL COMMENT 'Markdown content',
  `description` text DEFAULT NULL COMMENT 'Short summary',
  `keywords` text DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kg_node_items`
--

CREATE TABLE `kg_node_items` (
  `id` int(11) NOT NULL,
  `node_id` int(11) NOT NULL,
  `item_type` varchar(100) NOT NULL COMMENT 'e.g. character, location, md_doc, episode, kg_node',
  `item_id` int(11) DEFAULT NULL,
  `item_label` varchar(255) DEFAULT NULL COMMENT 'Display label or name snapshot',
  `relationship` varchar(255) DEFAULT NULL COMMENT 'e.g. protagonist, antagonist, woke, bonded_to',
  `note` longtext DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kg_staging_categories`
--

CREATE TABLE `kg_staging_categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kg_staging_nodes`
--

CREATE TABLE `kg_staging_nodes` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `node_type` varchar(100) DEFAULT 'note' COMMENT 'e.g. relationship, character, event, location, concept',
  `content` longtext DEFAULT NULL COMMENT 'Markdown content',
  `description` text DEFAULT NULL COMMENT 'Short summary',
  `keywords` text DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kg_staging_node_items`
--

CREATE TABLE `kg_staging_node_items` (
  `id` int(11) NOT NULL,
  `node_id` int(11) NOT NULL,
  `item_type` varchar(100) NOT NULL COMMENT 'e.g. character, location, md_doc, episode, kg_node',
  `item_id` int(11) DEFAULT NULL,
  `item_label` varchar(255) DEFAULT NULL COMMENT 'Display label or name snapshot',
  `relationship` varchar(255) DEFAULT NULL COMMENT 'e.g. protagonist, antagonist, woke, bonded_to',
  `note` longtext DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lightings`
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
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations_abstract`
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
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `level` varchar(10) NOT NULL,
  `message` longtext NOT NULL CHECK (json_valid(`message`)),
  `log_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lore_entities`
--

CREATE TABLE `lore_entities` (
  `id` int(11) NOT NULL,
  `ref_code` varchar(100) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL COMMENT 'Location, Character, Prop, etc',
  `raw_content` text NOT NULL COMMENT 'The original extracted text from the MD',
  `source_file` varchar(255) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0 COMMENT '1 if converted to showcase',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `magazine_pdf_jobs`
--

CREATE TABLE `magazine_pdf_jobs` (
  `id` int(11) NOT NULL,
  `series_id` int(11) NOT NULL COMMENT 'FK → cinemagic_series.id',
  `sequence_id` int(11) NOT NULL COMMENT 'FK → narrative_sequences.id (the episode/issue)',
  `languages` varchar(64) NOT NULL DEFAULT 'en' COMMENT 'Comma-separated language codes requested',
  `status` enum('pending','processing','done','error') NOT NULL DEFAULT 'pending',
  `result_zip` varchar(512) DEFAULT NULL COMMENT 'Absolute path to the produced ZIP file',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Async PDF export jobs — one row per issue+language-set request';

-- --------------------------------------------------------

--
-- Table structure for table `map_runs`
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
-- Table structure for table `map_run_queue`
--

CREATE TABLE `map_run_queue` (
  `id` bigint(20) NOT NULL,
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
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `map_run_queue_archive`
--

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
  `archived_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `md_doc_analysis`
--

CREATE TABLE `md_doc_analysis` (
  `id` int(11) NOT NULL,
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
  `is_locked` tinyint(1) DEFAULT 0 COMMENT 'If 1, the aggregator will skip this document to protect manual edits.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `md_doc_chunks`
--

CREATE TABLE `md_doc_chunks` (
  `id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `chunk_index` int(11) NOT NULL,
  `lore_raw` longtext DEFAULT NULL,
  `show_raw` longtext DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meshes`
--

CREATE TABLE `meshes` (
  `id` int(11) NOT NULL,
  `map_run_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meta_entities`
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

-- --------------------------------------------------------

--
-- Table structure for table `meta_sketches`
--

CREATE TABLE `meta_sketches` (
  `id` int(11) NOT NULL,
  `sketch_id` int(11) NOT NULL,
  `desc_gen_config_id` int(11) DEFAULT NULL COMMENT 'Original Config ID for reference',
  `desc_gen_history_id` int(11) DEFAULT NULL COMMENT 'Specific revision used for Description',
  `name_gen_config_id` int(11) DEFAULT NULL COMMENT 'Original Config ID for reference',
  `name_gen_history_id` int(11) DEFAULT NULL COMMENT 'Specific revision used for Name',
  `sketch_template_id` int(11) DEFAULT NULL,
  `interaction_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `montages`
--

CREATE TABLE `montages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `regenerate_videos` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = regenerate videos',
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `montage_videos`
--

CREATE TABLE `montage_videos` (
  `montage_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `motion_camera_presets`
--

CREATE TABLE `motion_camera_presets` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `config` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `motion_layers`
--

CREATE TABLE `motion_layers` (
  `id` int(11) NOT NULL,
  `motion_setup_id` int(11) NOT NULL,
  `frame_id` int(11) DEFAULT NULL,
  `video_id` int(11) DEFAULT NULL,
  `mesh_id` int(11) DEFAULT NULL,
  `role` enum('background','plane','sprite','model3d') NOT NULL DEFAULT 'plane',
  `z_index` int(11) DEFAULT 0,
  `layer_config` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `motion_render_queue`
--

CREATE TABLE `motion_render_queue` (
  `id` int(11) NOT NULL,
  `animatic_id` int(11) NOT NULL,
  `motion_setup_id` int(11) NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `flight_data_json` longtext NOT NULL,
  `result_video_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `motion_setups`
--

CREATE TABLE `motion_setups` (
  `id` int(11) NOT NULL,
  `animatic_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Default Scenario',
  `is_active` tinyint(1) DEFAULT 0,
  `environment_config` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `motion_takes`
--

CREATE TABLE `motion_takes` (
  `id` int(11) NOT NULL,
  `animatic_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Untitled Take',
  `telemetry_data` longtext NOT NULL,
  `duration` float DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `multiplane_arrangements`
--

CREATE TABLE `multiplane_arrangements` (
  `id` int(11) NOT NULL,
  `composite_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Untitled Arrangement',
  `description` text DEFAULT NULL,
  `layer_config` longtext DEFAULT NULL COMMENT 'JSON storage of x,y,scale,rotation,zIndex per frame',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `multiplane_layers`
--

CREATE TABLE `multiplane_layers` (
  `composite_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `z_index` int(11) NOT NULL DEFAULT 0 COMMENT '0=Back, 100=Front. Defines render order',
  `speed` float NOT NULL DEFAULT 0.5 COMMENT '0.0=Static BG, 1.0=Moves with Cam',
  `distance` float NOT NULL DEFAULT 10 COMMENT 'Distance from camera in meters. 10m is reference point.',
  `real_height` float DEFAULT NULL COMMENT 'Real world height of the object in meters'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `multiplane_settings`
--

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
  `frustum_height` float NOT NULL DEFAULT 10 COMMENT 'Height of the visible view (in meters) at the Focal Distance'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `multivid_arrangements`
--

CREATE TABLE `multivid_arrangements` (
  `id` int(11) NOT NULL,
  `animatic_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Untitled Arrangement',
  `description` text DEFAULT NULL,
  `layer_config` longtext DEFAULT NULL COMMENT 'JSON: per-layer x,y,scaleX,scaleY,rotation,zIndex,speed,opacity',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `multivid_layers`
--

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
  `playback_speed` float NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `multivid_render_jobs`
--

CREATE TABLE `multivid_render_jobs` (
  `id` int(11) NOT NULL,
  `animatic_id` int(11) NOT NULL,
  `arrangement_id` int(11) DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `task_id` varchar(64) DEFAULT NULL,
  `video_id` int(11) DEFAULT NULL,
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `multivid_settings`
--

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `muvitriccs_projects`
--

CREATE TABLE `muvitriccs_projects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL DEFAULT 'Untitled Project',
  `description` text DEFAULT NULL,
  `animatic_id` int(11) DEFAULT NULL COMMENT 'Optional animatic context',
  `canvas_w` int(11) NOT NULL DEFAULT 1080,
  `canvas_h` int(11) NOT NULL DEFAULT 1080,
  `fps` int(11) NOT NULL DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `muvitriccs_render_jobs`
--

CREATE TABLE `muvitriccs_render_jobs` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `slot_a_id` int(11) NOT NULL COMMENT 'outgoing slot',
  `slot_b_id` int(11) NOT NULL COMMENT 'incoming slot',
  `transition_name` varchar(64) NOT NULL DEFAULT '' COMMENT 'Snapshot of the transition type at render time',
  `pyapi_task_id` varchar(64) DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `video_id` int(11) DEFAULT NULL COMMENT 'resulting video in videos table',
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `muvitriccs_slots`
--

CREATE TABLE `muvitriccs_slots` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `muvitriccs_transition_demos`
--

CREATE TABLE `muvitriccs_transition_demos` (
  `id` int(11) NOT NULL,
  `transition_name` varchar(64) NOT NULL COMMENT 'matches TRANSITION_REGISTRY key, e.g. cross_dissolve',
  `video_id` int(11) NOT NULL COMMENT 'FK → videos.id  (the rendered transition video)',
  `job_id` int(11) DEFAULT NULL COMMENT 'FK → muvitriccs_render_jobs.id (origin job, informational)',
  `label` varchar(150) DEFAULT NULL COMMENT 'Optional human label for this demo clip',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = shown as the default preview for this transition',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Assigned demo/preview renders per MuviTriccs transition type';

-- --------------------------------------------------------

--
-- Table structure for table `narrative_beat_analysis`
--

CREATE TABLE `narrative_beat_analysis` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Per-beat AI analysis for narrative sequence episode generation';

-- --------------------------------------------------------

--
-- Table structure for table `narrative_sequences`
--

CREATE TABLE `narrative_sequences` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Untitled Sequence',
  `description` text DEFAULT NULL,
  `sequence_data` longtext DEFAULT NULL CHECK (json_valid(`sequence_data`)),
  `linked_doc_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `narrative_sequences_auto`
--

CREATE TABLE `narrative_sequences_auto` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Auto Generated',
  `description` text DEFAULT NULL,
  `sequence_data` longtext DEFAULT NULL CHECK (json_valid(`sequence_data`)),
  `generation_log` longtext DEFAULT NULL COMMENT 'JSON log of the decision process',
  `linked_doc_id` int(11) DEFAULT NULL,
  `score` float DEFAULT 0 COMMENT 'Internal coherence score',
  `status` enum('pending','promoted','archived') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `narrative_sequence_analysis`
--

CREATE TABLE `narrative_sequence_analysis` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Final synthesised episode document per narrative sequence';

-- --------------------------------------------------------

--
-- Table structure for table `pages`
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
-- Table structure for table `pastebin`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `pastebin`
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
-- Table structure for table `perspectives`
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
-- Table structure for table `playlist_videos`
--

CREATE TABLE `playlist_videos` (
  `id` int(11) NOT NULL,
  `playlist_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `added_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plush_collections`
--

CREATE TABLE `plush_collections` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plush_collections_2_stories`
--

CREATE TABLE `plush_collections_2_stories` (
  `collection_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `arc_label` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plush_highlight_blocks`
--

CREATE TABLE `plush_highlight_blocks` (
  `id` int(11) NOT NULL,
  `scene_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `text_content` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `bg_color` varchar(30) DEFAULT NULL,
  `language_code` varchar(2) NOT NULL DEFAULT 'en',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plush_highlight_block_entities`
--

CREATE TABLE `plush_highlight_block_entities` (
  `id` int(11) NOT NULL,
  `block_id` int(11) NOT NULL COMMENT 'FK → plush_highlight_blocks.id',
  `entity_type` varchar(50) NOT NULL COMMENT 'characters | factions | locations',
  `entity_id` int(11) NOT NULL COMMENT 'PK of the referenced entity row',
  `entity_label` varchar(255) DEFAULT NULL COMMENT 'Cached display name at time of tagging',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Polymorphic entity tags on PLUSH highlight blocks for timeline display';

-- --------------------------------------------------------

--
-- Table structure for table `plush_highlight_groups`
--

CREATE TABLE `plush_highlight_groups` (
  `id` int(11) NOT NULL,
  `scene_id` int(11) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `group_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plush_scenes`
--

CREATE TABLE `plush_scenes` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `synopsis` text DEFAULT NULL,
  `scene_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plush_scene_dates`
--

CREATE TABLE `plush_scene_dates` (
  `id` int(11) NOT NULL,
  `scene_id` int(11) NOT NULL COMMENT 'FK → plush_scenes.id',
  `start_label` varchar(100) DEFAULT NULL,
  `end_label` varchar(100) DEFAULT NULL,
  `sort_start` int(11) NOT NULL DEFAULT 0,
  `sort_end` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='In-universe timeline date metadata for PLUSH scenes';

-- --------------------------------------------------------

--
-- Table structure for table `plush_stories`
--

CREATE TABLE `plush_stories` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plush_story_dates`
--

CREATE TABLE `plush_story_dates` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL COMMENT 'FK → plush_stories.id',
  `era_label` varchar(100) DEFAULT NULL COMMENT 'Display era name e.g. "Age of the Fracture"',
  `start_label` varchar(100) DEFAULT NULL COMMENT 'Human-readable start e.g. "Year 0 AE"',
  `end_label` varchar(100) DEFAULT NULL COMMENT 'Human-readable end   e.g. "Year 12 AE"',
  `sort_start` int(11) NOT NULL DEFAULT 0 COMMENT 'Numeric value for timeline ordering',
  `sort_end` int(11) DEFAULT NULL COMMENT 'Numeric end value; NULL = point-in-time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='In-universe timeline date metadata for PLUSH stories';

-- --------------------------------------------------------

--
-- Table structure for table `poses`
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

-- --------------------------------------------------------

--
-- Table structure for table `poses_anima`
--

CREATE TABLE `poses_anima` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `anima_type` varchar(50) DEFAULT NULL COMMENT 'e.g. CHRONO, GRAVITA, MOMENTUM, ENERGIA, THERMA, LUMINA, RESONANTIA, MAGNETICA, FLUIDICA, COHESIVA, PROBABILIS, VITALIS, NOETICA, SPATIA, OSCILLA, MERGE',
  `hierarchy_level` varchar(20) DEFAULT NULL COMMENT 'Trace | Pulse | Surge | Manifest',
  `is_merge` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = this pose depicts a two-Anima merge moment',
  `merge_type` varchar(20) DEFAULT NULL COMMENT 'Harmonic | Forced | Dissonant — null when is_merge = 0',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `post_type` enum('image_grid','image_swiper','video_playlist','youtube_playlist','url_reference') NOT NULL,
  `preview_image_url` varchar(512) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_items` longtext DEFAULT NULL CHECK (json_valid(`media_items`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_status`
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
-- Table structure for table `prompt_additions`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prompt_globals`
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

-- --------------------------------------------------------

--
-- Table structure for table `prompt_ideations`
--

CREATE TABLE `prompt_ideations` (
  `id` int(10) UNSIGNED NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prompt_matrix`
--

CREATE TABLE `prompt_matrix` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `additions_snapshot` longtext DEFAULT NULL COMMENT 'Immutable snapshot: [{slot, addition_id|null, text}]' CHECK (json_valid(`additions_snapshot`)),
  `additions_count` int(11) DEFAULT NULL,
  `total_combinations` bigint(20) UNSIGNED DEFAULT NULL,
  `regenerate_images` tinyint(1) NOT NULL DEFAULT 0,
  `active_map_run_id` int(11) DEFAULT NULL,
  `state_id_active` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prompt_matrix_additions`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prompt_matrix_blueprints`
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prompt_negative_globals`
--

CREATE TABLE `prompt_negative_globals` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rapid_showcase`
--

CREATE TABLE `rapid_showcase` (
  `id` int(11) NOT NULL,
  `reference_code` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description_prompt` text NOT NULL,
  `generator_config_id` varchar(64) DEFAULT NULL,
  `is_generated` tinyint(1) NOT NULL DEFAULT 0,
  `created_sketch_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scenes`
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
-- Table structure for table `scene_kitchen_pots`
--

CREATE TABLE `scene_kitchen_pots` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `ingredients_json` longtext NOT NULL CHECK (json_valid(`ingredients_json`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scene_parts`
--

CREATE TABLE `scene_parts` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scene_part_animas`
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
-- Table structure for table `scene_part_artifacts`
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
-- Table structure for table `scene_part_backgrounds`
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
-- Table structure for table `scene_part_characters`
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
-- Table structure for table `scene_part_tags`
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
-- Table structure for table `scene_part_versions`
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
-- Table structure for table `scheduled_tasks`
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

-- --------------------------------------------------------

--
-- Table structure for table `scheduler_heartbeat`
--

CREATE TABLE `scheduler_heartbeat` (
  `id` tinyint(4) NOT NULL,
  `last_seen` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seeds`
--

CREATE TABLE `seeds` (
  `id` int(10) UNSIGNED NOT NULL,
  `value` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sequence_overlay_texts`
--

CREATE TABLE `sequence_overlay_texts` (
  `id` int(11) NOT NULL,
  `sequence_id` int(11) NOT NULL,
  `language_code` varchar(2) NOT NULL DEFAULT 'en',
  `name_overlay` varchar(255) DEFAULT NULL,
  `description_overlay` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shot_types`
--

CREATE TABLE `shot_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketches`
--

CREATE TABLE `sketches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order for interface/menu',
  `description` text DEFAULT NULL COMMENT 'raw or random prompt text',
  `description_raw` text DEFAULT NULL COMMENT 'Stores the original scene prompt before any continuity modifications',
  `prompt_negative` text DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `searchable` tinyint(1) NOT NULL DEFAULT 1,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketchmig_bundle`
--

CREATE TABLE `sketchmig_bundle` (
  `id` int(11) NOT NULL,
  `label` varchar(200) NOT NULL,
  `source_db` varchar(100) DEFAULT NULL COMMENT 'Origin SAGE instance identifier',
  `sketch_count` int(11) NOT NULL DEFAULT 0,
  `frame_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','exported','imported','failed') NOT NULL DEFAULT 'pending',
  `export_note` text DEFAULT NULL,
  `import_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `imported_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketchmig_frame`
--

CREATE TABLE `sketchmig_frame` (
  `id` int(11) NOT NULL,
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
  `rating` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `zip_path` varchar(500) NOT NULL,
  `imported_frame_name` varchar(255) DEFAULT NULL,
  `imported_frame_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketchmig_sketch`
--

CREATE TABLE `sketchmig_sketch` (
  `id` int(11) NOT NULL,
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
  `ssa_transition_usability` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_analysis`
--

CREATE TABLE `sketch_analysis` (
  `id` int(11) NOT NULL,
  `sketch_id` int(11) NOT NULL,
  `entities` longtext DEFAULT NULL CHECK (json_valid(`entities`)),
  `classification` longtext DEFAULT NULL CHECK (json_valid(`classification`)),
  `scoring` longtext DEFAULT NULL CHECK (json_valid(`scoring`)),
  `thematics` longtext DEFAULT NULL CHECK (json_valid(`thematics`)),
  `recommendations` longtext DEFAULT NULL CHECK (json_valid(`recommendations`)),
  `overall_quality` float DEFAULT 0,
  `analyzed_at` timestamp NULL DEFAULT current_timestamp(),
  `generator_config_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_categories`
--

CREATE TABLE `sketch_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_ingredients`
--

CREATE TABLE `sketch_ingredients` (
  `id` int(11) NOT NULL,
  `sketch_id` int(11) NOT NULL,
  `ingredient_type` varchar(100) NOT NULL COMMENT 'Class name or type identifier',
  `source_id` int(11) DEFAULT NULL COMMENT 'ID of the template/interaction if applicable',
  `prompt_fragment` text DEFAULT NULL COMMENT 'The actual text text generated/contributed',
  `snapshot_data` longtext DEFAULT NULL COMMENT 'JSON snapshot of source data at time of creation',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_location_ranges`
--

CREATE TABLE `sketch_location_ranges` (
  `id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL COMMENT 'Descriptive name of the location',
  `sketch_id_from` int(11) NOT NULL COMMENT 'Start of the sketch ID range',
  `sketch_id_to` int(11) NOT NULL COMMENT 'End of the sketch ID range',
  `notes` text DEFAULT NULL COMMENT 'Optional details about this range',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_lore_history`
--

CREATE TABLE `sketch_lore_history` (
  `id` int(11) NOT NULL,
  `sketch_id` int(11) NOT NULL COMMENT 'Links to sketches.id',
  `doc_id` int(11) NOT NULL COMMENT 'Links to documentations.id',
  `entity_type` varchar(50) NOT NULL COMMENT 'e.g. characters, locations',
  `entity_name` varchar(255) NOT NULL COMMENT 'Name of the specific entity used',
  `generator_config_id` int(11) DEFAULT NULL,
  `prompt_used` text DEFAULT NULL COMMENT 'The context/prompt sent to AI',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_migration_entities`
--

CREATE TABLE `sketch_migration_entities` (
  `source_type` varchar(50) NOT NULL,
  `source_id` int(11) NOT NULL,
  `target_sketch_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_migration_frames`
--

CREATE TABLE `sketch_migration_frames` (
  `source_frame_id` int(11) NOT NULL,
  `target_frame_id` int(11) NOT NULL,
  `migrated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_overlay_texts`
--

CREATE TABLE `sketch_overlay_texts` (
  `id` int(11) NOT NULL,
  `sketch_id` int(11) NOT NULL,
  `text_content` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `language_code` varchar(2) NOT NULL DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_sequence_analysis`
--

CREATE TABLE `sketch_sequence_analysis` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sketch_templates`
--

CREATE TABLE `sketch_templates` (
  `id` int(11) NOT NULL,
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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spawns`
--

CREATE TABLE `spawns` (
  `id` int(11) NOT NULL,
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
  `cnmap_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spawn_types`
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

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `storyboards`
--

CREATE TABLE `storyboards` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `directory` varchar(255) NOT NULL COMMENT 'Relative path like /storyboards/storyboard001',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category` enum('editorial','location','character','misc') NOT NULL DEFAULT 'misc',
  `editorial_scene_id` int(11) DEFAULT NULL,
  `custom_tag` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `storyboard_categories`
--

CREATE TABLE `storyboard_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL COMMENT 'Slug for logic: editorial, location, character, misc',
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `storyboard_frames`
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
-- Table structure for table `story_arcs`
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
-- Table structure for table `styles`
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

-- --------------------------------------------------------

--
-- Table structure for table `style_profiles`
--

CREATE TABLE `style_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `axis_group` varchar(64) DEFAULT 'visual_style',
  `filename` varchar(255) DEFAULT NULL,
  `json_payload` longtext DEFAULT NULL,
  `convert_result` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `style_profile_axes`
--

CREATE TABLE `style_profile_axes` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `axis_id` int(10) UNSIGNED NOT NULL,
  `value` tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `style_profile_config`
--

CREATE TABLE `style_profile_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(64) NOT NULL,
  `config_value` varchar(64) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_languages`
--

CREATE TABLE `system_languages` (
  `code` varchar(2) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `show_in_ui` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = shown in Taggerang UI, 0 = hidden from UI. Never deleted via UI.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags2poses`
--

CREATE TABLE `tags2poses` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags_2_animatics`
--

CREATE TABLE `tags_2_animatics` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Animatic ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags_2_frames`
--

CREATE TABLE `tags_2_frames` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Frame ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags_2_frames_staged`
--

CREATE TABLE `tags_2_frames_staged` (
  `id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `frame_id` int(11) NOT NULL,
  `score` float NOT NULL DEFAULT 0 COMMENT 'Vector similarity score from auto-tag run',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=approved by reviewer, 0=toggled off',
  `reviewed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=frame marked done, removed from unreviewed queue',
  `run_id` varchar(36) DEFAULT NULL COMMENT 'UUID of the auto-tag run that created this row',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Auto-tag staging — proposals written here, persisted to tags_2_frames after review';

-- --------------------------------------------------------

--
-- Table structure for table `tags_2_sketches`
--

CREATE TABLE `tags_2_sketches` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Sketch ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags_2_videos`
--

CREATE TABLE `tags_2_videos` (
  `from_id` int(11) NOT NULL COMMENT 'Tag ID',
  `to_id` int(11) NOT NULL COMMENT 'Video ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags_2_videos_staged`
--

CREATE TABLE `tags_2_videos_staged` (
  `id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `score` float NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `run_id` varchar(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_execution_stats`
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
-- Table structure for table `task_locks`
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
-- Table structure for table `task_runs`
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
-- Table structure for table `task_wrappers`
--

CREATE TABLE `task_wrappers` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL COMMENT 'Link to scheduled_tasks.id',
  `name` varchar(100) NOT NULL COMMENT 'Short button label',
  `summary` varchar(255) DEFAULT NULL COMMENT 'Small description text',
  `fixed_args` text DEFAULT NULL COMMENT 'The specific args to inject',
  `icon` varchar(50) DEFAULT '?' COMMENT 'Emoji or short icon code',
  `display_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
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
-- Table structure for table `vector_state`
--

CREATE TABLE `vector_state` (
  `id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `collection` varchar(100) NOT NULL,
  `status` enum('pending','indexed','failed','outdated') DEFAULT 'pending',
  `last_ingested_at` timestamp NULL DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  `error_msg` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vedtriccs_connectors`
--

CREATE TABLE `vedtriccs_connectors` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL COMMENT 'vedtriccs_project_files.id',
  `connector_key` varchar(200) NOT NULL COMMENT 'hash key identifying this clip-pair boundary',
  `params_json` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vedtriccs_projects`
--

CREATE TABLE `vedtriccs_projects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Project',
  `folder_name` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vedtriccs_project_files`
--

CREATE TABLE `vedtriccs_project_files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `filename` varchar(120) NOT NULL,
  `state_data` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vedtriccs_render_jobs`
--

CREATE TABLE `vedtriccs_render_jobs` (
  `id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `connector_key` varchar(200) DEFAULT NULL,
  `transition_name` varchar(64) NOT NULL DEFAULT 'cross_dissolve',
  `pyapi_task_id` varchar(64) DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  `video_id` int(11) DEFAULT NULL,
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vedtriccs_transition_demos`
--

CREATE TABLE `vedtriccs_transition_demos` (
  `id` int(11) NOT NULL,
  `transition_name` varchar(64) NOT NULL,
  `video_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ved_projects`
--

CREATE TABLE `ved_projects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Project',
  `folder_name` varchar(120) NOT NULL COMMENT 'Slug used for file organisation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ved_project_files`
--

CREATE TABLE `ved_project_files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `filename` varchar(120) NOT NULL COMMENT 'Human-readable save name',
  `state_data` longtext NOT NULL COMMENT 'Full JSON state of the timeline',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
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
  `is_ingredient` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
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
  `review` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `videos_2_animatics`
--

CREATE TABLE `videos_2_animatics` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `videos_2_composites`
--

CREATE TABLE `videos_2_composites` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `videos_2_derivates`
--

CREATE TABLE `videos_2_derivates` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `videos_2_montages`
--

CREATE TABLE `videos_2_montages` (
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_categories`
--

CREATE TABLE `video_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_counter`
--

CREATE TABLE `video_counter` (
  `next_video` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_enhancements`
--

CREATE TABLE `video_enhancements` (
  `id` int(11) NOT NULL,
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
  `regenerate_videos` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_playlists`
--

CREATE TABLE `video_playlists` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_tree_items`
--

CREATE TABLE `video_tree_items` (
  `id` int(11) NOT NULL,
  `node_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_tree_nodes`
--

CREATE TABLE `video_tree_nodes` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `node_type` enum('folder','episode','sequence','scene','other') DEFAULT 'folder',
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_anima_activity`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_artifact_usage`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_character_anima_pose_angle_combinations`
-- (See below for the actual view)
--
CREATE TABLE `v_character_anima_pose_angle_combinations` (
`character_id` int(11)
,`character_name` varchar(100)
,`pose_id` int(11)
,`pose_name` varchar(100)
,`angle_id` int(11)
,`angle_name` varchar(100)
,`perspective_id` int(11)
,`perspective_name` varchar(100)
,`base_prompt` mediumtext
,`description` mediumtext
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_character_expression_angle_combinations`
-- (See below for the actual view)
--
CREATE TABLE `v_character_expression_angle_combinations` (
`character_id` int(11)
,`character_name` varchar(100)
,`expression_id` int(10) unsigned
,`expression_name` varchar(255)
,`angle_id` int(11)
,`angle_name` varchar(100)
,`perspective_id` int(11)
,`perspective_name` varchar(100)
,`base_prompt` mediumtext
,`description` mediumtext
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_character_pose_angle_combinations`
-- (See below for the actual view)
--
CREATE TABLE `v_character_pose_angle_combinations` (
`character_id` int(11)
,`character_name` varchar(100)
,`pose_id` int(11)
,`pose_name` varchar(100)
,`angle_id` int(11)
,`angle_name` varchar(100)
,`perspective_id` int(11)
,`perspective_name` varchar(100)
,`base_prompt` mediumtext
,`description` mediumtext
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_export_ready`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_animas`
-- (See below for the actual view)
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
,`entity_type` varchar(6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_animatics`
-- (See below for the actual view)
--
CREATE TABLE `v_gallery_animatics` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`name` varchar(100)
,`description` text
,`map_run_id` int(11)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_artifacts`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_backgrounds`
-- (See below for the actual view)
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
,`entity_type` varchar(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_characters`
-- (See below for the actual view)
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
,`entity_type` varchar(10)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_character_anima_poses`
-- (See below for the actual view)
--
CREATE TABLE `v_gallery_character_anima_poses` (
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
,`perspective_id` int(11)
,`perspective_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_character_expressions`
-- (See below for the actual view)
--
CREATE TABLE `v_gallery_character_expressions` (
`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` varchar(100)
,`character_expression_id` int(11)
,`character_id` int(11)
,`character_name` varchar(100)
,`expression_id` int(11)
,`expression_name` varchar(255)
,`angle_id` int(11)
,`angle_name` varchar(100)
,`perspective_id` int(11)
,`perspective_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_character_poses`
-- (See below for the actual view)
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
,`perspective_id` int(11)
,`perspective_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_composites`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_controlnet_maps`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_factions`
-- (See below for the actual view)
--
CREATE TABLE `v_gallery_factions` (
`frame_id` int(11)
,`map_run_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` text
,`style` text
,`faction_id` int(11)
,`faction_name` varchar(100)
,`entity_type` varchar(8)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_generatives`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_locations`
-- (See below for the actual view)
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
,`entity_type` varchar(9)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_prompt_matrix_blueprints`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_scene_parts`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_sketches`
-- (See below for the actual view)
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
,`map_run_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_gallery_spawns`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_spawns_location`
-- (See below for the actual view)
--
CREATE TABLE `v_gallery_spawns_location` (
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
-- Stand-in structure for view `v_gallery_spawns_prop`
-- (See below for the actual view)
--
CREATE TABLE `v_gallery_spawns_prop` (
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
-- Stand-in structure for view `v_gallery_spawns_reference`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_spawns_texture`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_vehicles`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_gallery_wall_of_images`
-- (See below for the actual view)
--
CREATE TABLE `v_gallery_wall_of_images` (
`entity_type` varchar(11)
,`frame_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`prompt` mediumtext
,`entity_name` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_animas`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_artifacts`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_audio_ambiences`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_audio_ambiences` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_audio_cues`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_audio_cues` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_audio_dialogue_lines`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_audio_dialogue_lines` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_audio_foleys`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_audio_foleys` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_audio_fxsounds`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_audio_fxsounds` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_audio_themes`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_audio_themes` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_backgrounds`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_characters`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_character_anima_poses`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_character_anima_poses` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_character_expressions`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_character_expressions` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_character_poses`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_composites`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_controlnet_maps`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_factions`
-- (See below for the actual view)
--
CREATE TABLE `v_map_runs_factions` (
`id` int(11)
,`created_at` datetime
,`note` text
,`entity_id` int(11)
,`is_active` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_map_runs_generatives`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_locations`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_prompt_matrix_blueprints`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_scene_parts`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_sketches`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_map_runs_vehicles`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_player_audio_ambiences`
-- (See below for the actual view)
--
CREATE TABLE `v_player_audio_ambiences` (
`audio_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`audio_name` varchar(255)
,`model` varchar(100)
,`audio_ambience_id` int(11)
,`name` varchar(100)
,`description` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_player_audio_cues`
-- (See below for the actual view)
--
CREATE TABLE `v_player_audio_cues` (
`audio_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`audio_name` varchar(255)
,`model` varchar(100)
,`audio_cue_id` int(11)
,`name` varchar(100)
,`description` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_player_audio_dialogue_lines`
-- (See below for the actual view)
--
CREATE TABLE `v_player_audio_dialogue_lines` (
`audio_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`audio_name` varchar(255)
,`model` varchar(100)
,`audio_dialogue_line_id` int(11)
,`name` varchar(100)
,`description` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_player_audio_foleys`
-- (See below for the actual view)
--
CREATE TABLE `v_player_audio_foleys` (
`audio_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`audio_name` varchar(255)
,`model` varchar(100)
,`audio_foley_id` int(11)
,`name` varchar(100)
,`description` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_player_audio_fxsounds`
-- (See below for the actual view)
--
CREATE TABLE `v_player_audio_fxsounds` (
`audio_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`audio_name` varchar(255)
,`model` varchar(100)
,`audio_fxsound_id` int(11)
,`name` varchar(100)
,`description` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_player_audio_themes`
-- (See below for the actual view)
--
CREATE TABLE `v_player_audio_themes` (
`audio_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`audio_name` varchar(255)
,`model` varchar(100)
,`audio_theme_id` int(11)
,`name` varchar(100)
,`description` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_player_daw_projects`
-- (See below for the actual view)
--
CREATE TABLE `v_player_daw_projects` (
`audio_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`audio_name` varchar(255)
,`model` varchar(100)
,`daw_project_id` int(11)
,`name` varchar(255)
,`description` varchar(255)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_player_editorial_shots`
-- (See below for the actual view)
--
CREATE TABLE `v_player_editorial_shots` (
`audio_id` int(11)
,`entity_id` int(11)
,`filename` varchar(255)
,`audio_name` varchar(255)
,`model` varchar(100)
,`editorial_shot_id` int(11)
,`name` varchar(255)
,`description` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_animas`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_animas` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_artifacts`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_artifacts` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_backgrounds`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_backgrounds` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_characters`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_characters` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_character_anima_poses`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_character_anima_poses` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` text
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_character_expressions`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_character_expressions` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` text
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_character_poses`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_character_poses` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` text
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_composites`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_composites` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_controlnet_maps`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_controlnet_maps` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_factions`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_factions` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_generatives`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_generatives` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_locations`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_locations` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_prompt_matrix_blueprints`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_prompt_matrix_blueprints` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_scene_parts`
-- (See below for the actual view)
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
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_sketches`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_sketches` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_prompts_vehicles`
-- (See below for the actual view)
--
CREATE TABLE `v_prompts_vehicles` (
`id` int(11)
,`regenerate_images` tinyint(1)
,`prompt` mediumtext
,`prompt_negative` mediumtext
,`seed` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_scenes_under_review`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_scene_part_full`
-- (See below for the actual view)
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
-- Stand-in structure for view `v_styles_helper`
-- (See below for the actual view)
--
CREATE TABLE `v_styles_helper` (
`id` int(11)
,`regenerate_images` int(1)
,`prompt` mediumtext
);

-- --------------------------------------------------------

--
-- Table structure for table `weather_conditions`
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

-- --------------------------------------------------------

--
-- Table structure for table `worker_img_api_endpoint`
--

CREATE TABLE `worker_img_api_endpoint` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worker_img_api_endpoint_param`
--

CREATE TABLE `worker_img_api_endpoint_param` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `endpoint_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK → worker_img_api_endpoint.id (no FK constraint by design)',
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worker_img_provider_default`
--

CREATE TABLE `worker_img_provider_default` (
  `id` int(10) UNSIGNED NOT NULL,
  `scope` varchar(64) NOT NULL DEFAULT 'global' COMMENT 'global = cron worker; manual = ad-hoc runs',
  `endpoint_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Points to worker_img_api_endpoint.id',
  `model_override` varchar(128) DEFAULT NULL COMMENT 'Optional: override the model param for this scope',
  `width_override` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Optional: override width',
  `height_override` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Optional: override height',
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wroom_chat_sessions`
--

CREATE TABLE `wroom_chat_sessions` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wroom_chekhov`
--

CREATE TABLE `wroom_chekhov` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `thread_id` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ep` varchar(50) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wroom_conversation`
--

CREATE TABLE `wroom_conversation` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) DEFAULT 'default',
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `protocol` varchar(50) DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  `context_snapshot` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wroom_deltas`
--

CREATE TABLE `wroom_deltas` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_str` varchar(100) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `decisions` text DEFAULT NULL,
  `deferred` text DEFAULT NULL,
  `threads` text DEFAULT NULL,
  `updates` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wroom_settings`
--

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
  `draft_delta` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wroom_threads`
--

CREATE TABLE `wroom_threads` (
  `id` int(11) NOT NULL,
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
  `is_selected` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ag_categories`
--
ALTER TABLE `ag_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_doc_id` (`doc_id`);

--
-- Indexes for table `ag_nodes`
--
ALTER TABLE `ag_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_node_type` (`node_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_doc_id` (`doc_id`);

--
-- Indexes for table `ag_node_items`
--
ALTER TABLE `ag_node_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_node` (`node_id`),
  ADD KEY `idx_item` (`item_type`,`item_id`),
  ADD KEY `idx_source_doc` (`doc_id`);

--
-- Indexes for table `animas`
--
ALTER TABLE `animas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_character_animas_character` (`character_id`),
  ADD KEY `idx_character_animas_name` (`name`);

--
-- Indexes for table `animatics`
--
ALTER TABLE `animatics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `animatic_audios`
--
ALTER TABLE `animatic_audios`
  ADD PRIMARY KEY (`animatic_id`,`audio_id`);

--
-- Indexes for table `animatic_cnmap_frames`
--
ALTER TABLE `animatic_cnmap_frames`
  ADD PRIMARY KEY (`animatic_id`,`frame_id`);

--
-- Indexes for table `animatic_frames`
--
ALTER TABLE `animatic_frames`
  ADD PRIMARY KEY (`animatic_id`,`frame_id`);

--
-- Indexes for table `animatic_meshes`
--
ALTER TABLE `animatic_meshes`
  ADD PRIMARY KEY (`animatic_id`,`mesh_id`);

--
-- Indexes for table `animatic_videos`
--
ALTER TABLE `animatic_videos`
  ADD PRIMARY KEY (`animatic_id`,`video_id`);

--
-- Indexes for table `animation_mouthshapes`
--
ALTER TABLE `animation_mouthshapes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `anivoc_action_effects`
--
ALTER TABLE `anivoc_action_effects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_backgrounds`
--
ALTER TABLE `anivoc_backgrounds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_categories`
--
ALTER TABLE `anivoc_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_table_name` (`table_name`);

--
-- Indexes for table `anivoc_character_states`
--
ALTER TABLE `anivoc_character_states`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_chibi_modes`
--
ALTER TABLE `anivoc_chibi_modes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_color_coding`
--
ALTER TABLE `anivoc_color_coding`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_duo_compositions`
--
ALTER TABLE `anivoc_duo_compositions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_expressions`
--
ALTER TABLE `anivoc_expressions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_hand_gestures`
--
ALTER TABLE `anivoc_hand_gestures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_lighting`
--
ALTER TABLE `anivoc_lighting`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_motion_impact`
--
ALTER TABLE `anivoc_motion_impact`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_panel_frame`
--
ALTER TABLE `anivoc_panel_frame`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_scale_perspective`
--
ALTER TABLE `anivoc_scale_perspective`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_scene_functions`
--
ALTER TABLE `anivoc_scene_functions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_slug` (`slug`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_shot_pacing`
--
ALTER TABLE `anivoc_shot_pacing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_symbolic_objects`
--
ALTER TABLE `anivoc_symbolic_objects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_text_graphics`
--
ALTER TABLE `anivoc_text_graphics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `anivoc_transitions`
--
ALTER TABLE `anivoc_transitions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `artifacts`
--
ALTER TABLE `artifacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_artifacts_name` (`name`),
  ADD KEY `idx_artifacts_type` (`type`),
  ADD KEY `idx_artifacts_status` (`status`);

--
-- Indexes for table `audios`
--
ALTER TABLE `audios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audios_2_audio_ambiences`
--
ALTER TABLE `audios_2_audio_ambiences`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `audios_2_audio_cues`
--
ALTER TABLE `audios_2_audio_cues`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `audios_2_audio_dialogue_lines`
--
ALTER TABLE `audios_2_audio_dialogue_lines`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `audios_2_audio_foleys`
--
ALTER TABLE `audios_2_audio_foleys`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `audios_2_audio_fxsounds`
--
ALTER TABLE `audios_2_audio_fxsounds`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `audios_2_audio_themes`
--
ALTER TABLE `audios_2_audio_themes`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `audios_2_daw_projects`
--
ALTER TABLE `audios_2_daw_projects`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `audios_2_documentations`
--
ALTER TABLE `audios_2_documentations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_id` (`from_id`),
  ADD KEY `to_id` (`to_id`);

--
-- Indexes for table `audios_2_editorial_shots`
--
ALTER TABLE `audios_2_editorial_shots`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `audio_ambiences`
--
ALTER TABLE `audio_ambiences`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_counter`
--
ALTER TABLE `audio_counter`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_cues`
--
ALTER TABLE `audio_cues`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_dialogue_lines`
--
ALTER TABLE `audio_dialogue_lines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_foleys`
--
ALTER TABLE `audio_foleys`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_fxsounds`
--
ALTER TABLE `audio_fxsounds`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_themes`
--
ALTER TABLE `audio_themes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_voice_identity`
--
ALTER TABLE `audio_voice_identity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_voice_identity_xmpl`
--
ALTER TABLE `audio_voice_identity_xmpl`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_voice_identity` (`voice_identity_id`);

--
-- Indexes for table `backgrounds`
--
ALTER TABLE `backgrounds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_backgrounds_name` (`name`),
  ADD KEY `idx_backgrounds_type` (`type`),
  ADD KEY `idx_backgrounds_location` (`location_id`);

--
-- Indexes for table `backups_media`
--
ALTER TABLE `backups_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `backup_destinations`
--
ALTER TABLE `backup_destinations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`);

--
-- Indexes for table `backup_jobs`
--
ALTER TABLE `backup_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_dest` (`destination_id`);

--
-- Indexes for table `backup_runs`
--
ALTER TABLE `backup_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `boards`
--
ALTER TABLE `boards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indexes for table `boards_categories`
--
ALTER TABLE `boards_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent` (`parent_id`);

--
-- Indexes for table `boards_items`
--
ALTER TABLE `boards_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_board` (`board_id`),
  ADD KEY `idx_item_lookup` (`item_type`,`item_id`);

--
-- Indexes for table `camera_angles`
--
ALTER TABLE `camera_angles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `camera_perspectives`
--
ALTER TABLE `camera_perspectives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `characters`
--
ALTER TABLE `characters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_characters_name` (`name`),
  ADD KEY `idx_characters_role` (`role`);

--
-- Indexes for table `character_anima_poses`
--
ALTER TABLE `character_anima_poses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `character_id` (`character_id`),
  ADD KEY `pose_id` (`pose_id`),
  ADD KEY `angle_id` (`angle_id`);

--
-- Indexes for table `character_expressions`
--
ALTER TABLE `character_expressions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_char_expr_angle_persp` (`character_id`,`expression_id`,`angle_id`,`perspective_id`),
  ADD KEY `character_id` (`character_id`),
  ADD KEY `expression_id` (`expression_id`),
  ADD KEY `angle_id` (`angle_id`),
  ADD KEY `idx_perspective_id` (`perspective_id`);

--
-- Indexes for table `character_poses`
--
ALTER TABLE `character_poses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `character_id` (`character_id`),
  ADD KEY `pose_id` (`pose_id`),
  ADD KEY `angle_id` (`angle_id`);

--
-- Indexes for table `chat_message`
--
ALTER TABLE `chat_message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `chat_session`
--
ALTER TABLE `chat_session`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_chat_session_type` (`type`);

--
-- Indexes for table `chat_summary`
--
ALTER TABLE `chat_summary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `chroma_collections`
--
ALTER TABLE `chroma_collections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_name` (`name`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `cinemagics`
--
ALTER TABLE `cinemagics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cinemagics_2_sequences`
--
ALTER TABLE `cinemagics_2_sequences`
  ADD PRIMARY KEY (`cinemagic_id`,`sequence_id`),
  ADD KEY `idx_c2s_cinemagic` (`cinemagic_id`),
  ADD KEY `idx_c2s_sequence` (`sequence_id`),
  ADD KEY `idx_c2s_sort` (`cinemagic_id`,`sort_order`);

--
-- Indexes for table `cinemagic_hub_posts`
--
ALTER TABLE `cinemagic_hub_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cm_slug` (`slug`),
  ADD KEY `idx_cm_status` (`status`),
  ADD KEY `idx_cm_seq` (`sequence_id`),
  ADD KEY `idx_cm_cinemagic` (`cinemagic_id`),
  ADD KEY `idx_cm_series` (`series_label`,`season_label`,`tree_sort_order`),
  ADD KEY `idx_cm_sort` (`sort_order`);

--
-- Indexes for table `cinemagic_series`
--
ALTER TABLE `cinemagic_series`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cinemagic_series_2_cinemagics`
--
ALTER TABLE `cinemagic_series_2_cinemagics`
  ADD PRIMARY KEY (`series_id`,`cinemagic_id`);

--
-- Indexes for table `clipboard_items`
--
ALTER TABLE `clipboard_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clipboard_visibility`
--
ALTER TABLE `clipboard_visibility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_item_area` (`clipboard_item_id`,`view_area`);

--
-- Indexes for table `color_grade_presets`
--
ALTER TABLE `color_grade_presets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cgpreset_name` (`name`);

--
-- Indexes for table `color_grade_profiles`
--
ALTER TABLE `color_grade_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cgp_frame` (`frame_id`),
  ADD KEY `idx_cgp_derived` (`derived_frame_id`),
  ADD KEY `idx_cgp_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `composites`
--
ALTER TABLE `composites`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `composite_audios`
--
ALTER TABLE `composite_audios`
  ADD PRIMARY KEY (`composite_id`,`audio_id`);

--
-- Indexes for table `composite_frames`
--
ALTER TABLE `composite_frames`
  ADD PRIMARY KEY (`composite_id`,`frame_id`);

--
-- Indexes for table `content_elements`
--
ALTER TABLE `content_elements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `page_id` (`page_id`);

--
-- Indexes for table `content_hub_posts`
--
ALTER TABLE `content_hub_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_platform` (`platform`),
  ADD KEY `idx_scheduled` (`scheduled_at`),
  ADD KEY `idx_sort` (`sort_order`),
  ADD KEY `idx_status_platform` (`status`,`platform`);

--
-- Indexes for table `continuity_jobs`
--
ALTER TABLE `continuity_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cj_sketch_char` (`sketch_id`,`character_id`),
  ADD KEY `idx_cj_sketch` (`sketch_id`),
  ADD KEY `idx_cj_character` (`character_id`),
  ADD KEY `idx_cj_status` (`status`);

--
-- Indexes for table `controlnet_maps`
--
ALTER TABLE `controlnet_maps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daw_projects`
--
ALTER TABLE `daw_projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daw_project_files`
--
ALTER TABLE `daw_project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `daw_shot_saves`
--
ALTER TABLE `daw_shot_saves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shot_id` (`shot_id`);

--
-- Indexes for table `derivates`
--
ALTER TABLE `derivates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `design_axes`
--
ALTER TABLE `design_axes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_axis_group` (`axis_group`),
  ADD KEY `idx_group_category` (`axis_group`,`category`);

--
-- Indexes for table `dict_dictionaries`
--
ALTER TABLE `dict_dictionaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `sort_order_index` (`sort_order`),
  ADD KEY `language_code` (`language_code`);

--
-- Indexes for table `dict_lemmas`
--
ALTER TABLE `dict_lemmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lemma_language` (`lemma`,`language_code`),
  ADD KEY `lemma_index` (`lemma`),
  ADD KEY `frequency_index` (`frequency`);

--
-- Indexes for table `dict_lemma_2_dictionary`
--
ALTER TABLE `dict_lemma_2_dictionary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dict_lemma_unique` (`dictionary_id`,`lemma_id`),
  ADD KEY `dictionary_id` (`dictionary_id`),
  ADD KEY `lemma_id` (`lemma_id`);

--
-- Indexes for table `dict_source_files`
--
ALTER TABLE `dict_source_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dictionary_id` (`dictionary_id`),
  ADD KEY `parse_status` (`parse_status`);

--
-- Indexes for table `dimensionals`
--
ALTER TABLE `dimensionals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dimensionals_2_meshes`
--
ALTER TABLE `dimensionals_2_meshes`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `documentations`
--
ALTER TABLE `documentations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_target_collection` (`target_collection`);

--
-- Indexes for table `documentation_categories`
--
ALTER TABLE `documentation_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `editorial_episodes`
--
ALTER TABLE `editorial_episodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_editorial_season` (`season_id`);

--
-- Indexes for table `editorial_scenes`
--
ALTER TABLE `editorial_scenes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_directory` (`directory`),
  ADD KEY `idx_editorial_sequence` (`sequence_id`);

--
-- Indexes for table `editorial_seasons`
--
ALTER TABLE `editorial_seasons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_editorial_series` (`series_id`);

--
-- Indexes for table `editorial_sequences`
--
ALTER TABLE `editorial_sequences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_editorial_episode` (`episode_id`);

--
-- Indexes for table `editorial_series`
--
ALTER TABLE `editorial_series`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `editorial_shots`
--
ALTER TABLE `editorial_shots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_editorial_scene` (`scene_id`),
  ADD KEY `idx_video` (`video_id`);

--
-- Indexes for table `editorial_shots_2_audio_ambiences`
--
ALTER TABLE `editorial_shots_2_audio_ambiences`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `editorial_shots_2_audio_cues`
--
ALTER TABLE `editorial_shots_2_audio_cues`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `editorial_shots_2_audio_foleys`
--
ALTER TABLE `editorial_shots_2_audio_foleys`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `editorial_shots_2_audio_fxsounds`
--
ALTER TABLE `editorial_shots_2_audio_fxsounds`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `editorial_shots_2_audio_themes`
--
ALTER TABLE `editorial_shots_2_audio_themes`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `editorial_shot_dialogues`
--
ALTER TABLE `editorial_shot_dialogues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shot` (`shot_id`),
  ADD KEY `idx_dialogue` (`dialogue_line_id`);

--
-- Indexes for table `export_flags`
--
ALTER TABLE `export_flags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_export_scene_part` (`scene_part_id`),
  ADD KEY `idx_export_ready` (`ready_for_export`),
  ADD KEY `idx_export_type` (`export_type`);

--
-- Indexes for table `factions`
--
ALTER TABLE `factions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_factions_name` (`name`),
  ADD KEY `idx_origin` (`origin_source`,`origin_id`);

--
-- Indexes for table `feedback_notes`
--
ALTER TABLE `feedback_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_source` (`source`),
  ADD KEY `idx_feedback_status` (`resolved_status`),
  ADD KEY `idx_feedback_scene_part` (`scene_part_id`);

--
-- Indexes for table `forge_jobs`
--
ALTER TABLE `forge_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_type_status` (`job_type`,`status`),
  ADD KEY `idx_status_priority` (`status`,`priority`,`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `forge_tool_settings`
--
ALTER TABLE `forge_tool_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `frames`
--
ALTER TABLE `frames`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `frames_2_animas`
--
ALTER TABLE `frames_2_animas`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `anima_id` (`to_id`);

--
-- Indexes for table `frames_2_animatics`
--
ALTER TABLE `frames_2_animatics`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `idx_frame_animatic_from` (`from_id`),
  ADD KEY `idx_frame_animatic_to` (`to_id`);

--
-- Indexes for table `frames_2_artifacts`
--
ALTER TABLE `frames_2_artifacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_frame_artifact_from` (`from_id`),
  ADD KEY `idx_frame_artifact_to` (`to_id`);

--
-- Indexes for table `frames_2_backgrounds`
--
ALTER TABLE `frames_2_backgrounds`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `background_id` (`to_id`);

--
-- Indexes for table `frames_2_characters`
--
ALTER TABLE `frames_2_characters`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `character_id` (`to_id`);

--
-- Indexes for table `frames_2_character_anima_poses`
--
ALTER TABLE `frames_2_character_anima_poses`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `from_id` (`to_id`);

--
-- Indexes for table `frames_2_character_expressions`
--
ALTER TABLE `frames_2_character_expressions`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `from_id` (`to_id`);

--
-- Indexes for table `frames_2_character_poses`
--
ALTER TABLE `frames_2_character_poses`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `from_id` (`to_id`);

--
-- Indexes for table `frames_2_composites`
--
ALTER TABLE `frames_2_composites`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `composite_id` (`to_id`);

--
-- Indexes for table `frames_2_controlnet_maps`
--
ALTER TABLE `frames_2_controlnet_maps`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `to_id_idx` (`to_id`);

--
-- Indexes for table `frames_2_factions`
--
ALTER TABLE `frames_2_factions`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `faction_id` (`to_id`);

--
-- Indexes for table `frames_2_generatives`
--
ALTER TABLE `frames_2_generatives`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `idx_frame_generative_from` (`from_id`),
  ADD KEY `idx_frame_generative_to` (`to_id`);

--
-- Indexes for table `frames_2_locations`
--
ALTER TABLE `frames_2_locations`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `location_id` (`to_id`);

--
-- Indexes for table `frames_2_pastebin`
--
ALTER TABLE `frames_2_pastebin`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `location_id` (`to_id`);

--
-- Indexes for table `frames_2_prompt_matrix_blueprints`
--
ALTER TABLE `frames_2_prompt_matrix_blueprints`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `prompt_matrix_blueprint_id` (`to_id`);

--
-- Indexes for table `frames_2_scene_parts`
--
ALTER TABLE `frames_2_scene_parts`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `to_id` (`to_id`);

--
-- Indexes for table `frames_2_sketches`
--
ALTER TABLE `frames_2_sketches`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `idx_frame_sketch_from` (`from_id`),
  ADD KEY `idx_frame_sketch_to` (`to_id`);

--
-- Indexes for table `frames_2_spawns`
--
ALTER TABLE `frames_2_spawns`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `seed_id` (`to_id`);

--
-- Indexes for table `frames_2_vehicles`
--
ALTER TABLE `frames_2_vehicles`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `idx_from_id` (`from_id`),
  ADD KEY `idx_to_id` (`to_id`);

--
-- Indexes for table `frames_chains`
--
ALTER TABLE `frames_chains`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_frame_id` (`frame_id`),
  ADD KEY `idx_parent_frame_id` (`parent_frame_id`);

--
-- Indexes for table `frames_failed`
--
ALTER TABLE `frames_failed`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `frames_trashcan`
--
ALTER TABLE `frames_trashcan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `frame_counter`
--
ALTER TABLE `frame_counter`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `frame_enhancements`
--
ALTER TABLE `frame_enhancements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `frame_enhancement_frames`
--
ALTER TABLE `frame_enhancement_frames`
  ADD PRIMARY KEY (`frame_enhancement_id`,`frame_id`);

--
-- Indexes for table `fuzz_candidates`
--
ALTER TABLE `fuzz_candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`concept_type`),
  ADD KEY `idx_kg_node` (`kg_node_id`);

--
-- Indexes for table `fuzz_candidate_aliases`
--
ALTER TABLE `fuzz_candidate_aliases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidate` (`candidate_id`);

--
-- Indexes for table `fuzz_links`
--
ALTER TABLE `fuzz_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidate` (`candidate_id`),
  ADD KEY `idx_target` (`target_candidate_id`);

--
-- Indexes for table `fuzz_mentions`
--
ALTER TABLE `fuzz_mentions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidate` (`candidate_id`),
  ADD KEY `idx_source` (`source_table`,`source_row_id`),
  ADD KEY `idx_mention_type` (`mention_type`);

--
-- Indexes for table `fuzz_queue`
--
ALTER TABLE `fuzz_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_norm` (`normalized_text`);

--
-- Indexes for table `fuzz_resolutions`
--
ALTER TABLE `fuzz_resolutions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidate` (`candidate_id`),
  ADD KEY `idx_kg_node` (`kg_node_id`);

--
-- Indexes for table `fuzz_reviews`
--
ALTER TABLE `fuzz_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidate` (`candidate_id`);

--
-- Indexes for table `generated_phrase_maps`
--
ALTER TABLE `generated_phrase_maps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_profile_hash_model` (`profile_hash`,`model_name`),
  ADD KEY `idx_profile_id` (`profile_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `generatives`
--
ALTER TABLE `generatives`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `generator_config`
--
ALTER TABLE `generator_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_id` (`config_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `active` (`active`),
  ADD KEY `idx_user_active` (`user_id`,`active`),
  ADD KEY `idx_public` (`user_id`),
  ADD KEY `idx_is_public` (`is_public`,`active`),
  ADD KEY `idx_list_order` (`list_order`);

--
-- Indexes for table `generator_config_display_area`
--
ALTER TABLE `generator_config_display_area`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_area_key` (`area_key`);

--
-- Indexes for table `generator_config_history`
--
ALTER TABLE `generator_config_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `generator_config_id` (`generator_config_id`),
  ADD KEY `config_hash` (`config_hash`);

--
-- Indexes for table `generator_config_to_display_area`
--
ALTER TABLE `generator_config_to_display_area`
  ADD PRIMARY KEY (`generator_config_id`,`display_area_id`),
  ADD KEY `idx_generator_config_id` (`generator_config_id`),
  ADD KEY `idx_display_area_id` (`display_area_id`);

--
-- Indexes for table `gs_assign_config`
--
ALTER TABLE `gs_assign_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_type_source_node` (`entity_type`,`source_id`,`node_id`),
  ADD KEY `idx_node` (`node_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `image_edits`
--
ALTER TABLE `image_edits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent_frame` (`parent_frame_id`),
  ADD KEY `idx_chain` (`chain_id`),
  ADD KEY `idx_derived_frame` (`derived_frame_id`),
  ADD KEY `idx_map_run` (`map_run_id`);

--
-- Indexes for table `image_stash`
--
ALTER TABLE `image_stash`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `interactions`
--
ALTER TABLE `interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `interaction_group` (`interaction_group`),
  ADD KEY `category` (`category`),
  ADD KEY `active` (`active`);

--
-- Indexes for table `json_categories`
--
ALTER TABLE `json_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `json_files`
--
ALTER TABLE `json_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `kg_categories`
--
ALTER TABLE `kg_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent` (`parent_id`);

--
-- Indexes for table `kg_nodes`
--
ALTER TABLE `kg_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_node_type` (`node_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `kg_node_items`
--
ALTER TABLE `kg_node_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_node` (`node_id`),
  ADD KEY `idx_item` (`item_type`,`item_id`);

--
-- Indexes for table `kg_staging_categories`
--
ALTER TABLE `kg_staging_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent` (`parent_id`);

--
-- Indexes for table `kg_staging_nodes`
--
ALTER TABLE `kg_staging_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_node_type` (`node_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `kg_staging_node_items`
--
ALTER TABLE `kg_staging_node_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_node` (`node_id`),
  ADD KEY `idx_item` (`item_type`,`item_id`);

--
-- Indexes for table `lightings`
--
ALTER TABLE `lightings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `angle_id` (`angle_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_locations_name` (`name`),
  ADD KEY `idx_locations_type` (`type`),
  ADD KEY `idx_origin` (`origin_source`,`origin_id`);

--
-- Indexes for table `locations_abstract`
--
ALTER TABLE `locations_abstract`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_locations_name` (`name`),
  ADD KEY `idx_locations_type` (`type`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lore_entities`
--
ALTER TABLE `lore_entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_ref` (`ref_code`);

--
-- Indexes for table `magazine_pdf_jobs`
--
ALTER TABLE `magazine_pdf_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mpj_status` (`status`),
  ADD KEY `idx_mpj_series` (`series_id`),
  ADD KEY `idx_mpj_sequence` (`sequence_id`);

--
-- Indexes for table `map_runs`
--
ALTER TABLE `map_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_map_run_idx` (`parent_map_run_id`);

--
-- Indexes for table `map_run_queue`
--
ALTER TABLE `map_run_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_priority` (`status`,`priority`),
  ADD KEY `idx_map_run_id` (`map_run_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_asset` (`asset_type`,`asset_id`);

--
-- Indexes for table `map_run_queue_archive`
--
ALTER TABLE `map_run_queue_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_map_run_id` (`map_run_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_asset` (`asset_type`,`asset_id`),
  ADD KEY `idx_archived_at` (`archived_at`);

--
-- Indexes for table `md_doc_analysis`
--
ALTER TABLE `md_doc_analysis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_doc` (`doc_id`),
  ADD KEY `idx_utility` (`narrative_utility`);

--
-- Indexes for table `md_doc_chunks`
--
ALTER TABLE `md_doc_chunks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_doc_chunk` (`doc_id`,`chunk_index`);

--
-- Indexes for table `meshes`
--
ALTER TABLE `meshes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meta_entities`
--
ALTER TABLE `meta_entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `meta_sketches`
--
ALTER TABLE `meta_sketches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sketch_id` (`sketch_id`),
  ADD KEY `desc_gen_history_id` (`desc_gen_history_id`),
  ADD KEY `sketch_template_id` (`sketch_template_id`);

--
-- Indexes for table `montages`
--
ALTER TABLE `montages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `montage_videos`
--
ALTER TABLE `montage_videos`
  ADD PRIMARY KEY (`montage_id`,`video_id`);

--
-- Indexes for table `motion_camera_presets`
--
ALTER TABLE `motion_camera_presets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `motion_layers`
--
ALTER TABLE `motion_layers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_setup` (`motion_setup_id`),
  ADD KEY `idx_frame` (`frame_id`),
  ADD KEY `idx_video` (`video_id`);

--
-- Indexes for table `motion_render_queue`
--
ALTER TABLE `motion_render_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `motion_setups`
--
ALTER TABLE `motion_setups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_animatic` (`animatic_id`);

--
-- Indexes for table `motion_takes`
--
ALTER TABLE `motion_takes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_animatic` (`animatic_id`);

--
-- Indexes for table `multiplane_arrangements`
--
ALTER TABLE `multiplane_arrangements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `composite_id` (`composite_id`);

--
-- Indexes for table `multiplane_layers`
--
ALTER TABLE `multiplane_layers`
  ADD PRIMARY KEY (`composite_id`,`frame_id`);

--
-- Indexes for table `multiplane_settings`
--
ALTER TABLE `multiplane_settings`
  ADD PRIMARY KEY (`composite_id`);

--
-- Indexes for table `multivid_arrangements`
--
ALTER TABLE `multivid_arrangements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_animatic` (`animatic_id`);

--
-- Indexes for table `multivid_layers`
--
ALTER TABLE `multivid_layers`
  ADD PRIMARY KEY (`animatic_id`,`asset_type`,`asset_id`);

--
-- Indexes for table `multivid_render_jobs`
--
ALTER TABLE `multivid_render_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_animatic` (`animatic_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `multivid_settings`
--
ALTER TABLE `multivid_settings`
  ADD PRIMARY KEY (`animatic_id`);

--
-- Indexes for table `muvitriccs_projects`
--
ALTER TABLE `muvitriccs_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_animatic` (`animatic_id`);

--
-- Indexes for table `muvitriccs_render_jobs`
--
ALTER TABLE `muvitriccs_render_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slots` (`slot_a_id`,`slot_b_id`);

--
-- Indexes for table `muvitriccs_slots`
--
ALTER TABLE `muvitriccs_slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_order` (`project_id`,`slot_order`);

--
-- Indexes for table `muvitriccs_transition_demos`
--
ALTER TABLE `muvitriccs_transition_demos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transition` (`transition_name`),
  ADD KEY `idx_video` (`video_id`),
  ADD KEY `idx_primary` (`transition_name`,`is_primary`);

--
-- Indexes for table `narrative_beat_analysis`
--
ALTER TABLE `narrative_beat_analysis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seq_pos` (`sequence_id`,`position`),
  ADD KEY `idx_nba_sketch` (`sketch_id`),
  ADD KEY `idx_nba_sequence` (`sequence_id`);

--
-- Indexes for table `narrative_sequences`
--
ALTER TABLE `narrative_sequences`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `narrative_sequences_auto`
--
ALTER TABLE `narrative_sequences_auto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `narrative_sequence_analysis`
--
ALTER TABLE `narrative_sequence_analysis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nsa_sequence` (`sequence_id`),
  ADD KEY `idx_nsa_config` (`generator_config_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `pastebin`
--
ALTER TABLE `pastebin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `url_token` (`url_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_visibility` (`visibility`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `perspectives`
--
ALTER TABLE `perspectives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_perspectives_scene_part` (`scene_part_id`);

--
-- Indexes for table `playlist_videos`
--
ALTER TABLE `playlist_videos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_playlist_video` (`playlist_id`,`video_id`),
  ADD KEY `idx_playlist` (`playlist_id`),
  ADD KEY `idx_video` (`video_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `plush_collections`
--
ALTER TABLE `plush_collections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plush_collections_2_stories`
--
ALTER TABLE `plush_collections_2_stories`
  ADD PRIMARY KEY (`collection_id`,`story_id`),
  ADD KEY `idx_c2s_collection` (`collection_id`),
  ADD KEY `idx_c2s_story` (`story_id`);

--
-- Indexes for table `plush_highlight_blocks`
--
ALTER TABLE `plush_highlight_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scene_group` (`scene_id`,`group_id`);

--
-- Indexes for table `plush_highlight_block_entities`
--
ALTER TABLE `plush_highlight_block_entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_block_entity` (`block_id`,`entity_type`,`entity_id`),
  ADD KEY `idx_block_id` (`block_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `plush_highlight_groups`
--
ALTER TABLE `plush_highlight_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scene_id` (`scene_id`);

--
-- Indexes for table `plush_scenes`
--
ALTER TABLE `plush_scenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_story_id` (`story_id`);

--
-- Indexes for table `plush_scene_dates`
--
ALTER TABLE `plush_scene_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pscd_scene` (`scene_id`);

--
-- Indexes for table `plush_stories`
--
ALTER TABLE `plush_stories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plush_story_dates`
--
ALTER TABLE `plush_story_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_psd_story` (`story_id`);

--
-- Indexes for table `poses`
--
ALTER TABLE `poses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `poses_anima`
--
ALTER TABLE `poses_anima`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `sort_order_index` (`sort_order`);

--
-- Indexes for table `production_status`
--
ALTER TABLE `production_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prodstatus_scene_part` (`scene_part_id`),
  ADD KEY `idx_prodstatus_stage` (`stage`),
  ADD KEY `idx_prodstatus_assignee` (`assigned_to`);

--
-- Indexes for table `prompt_additions`
--
ALTER TABLE `prompt_additions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_slot_order` (`slot`,`order`),
  ADD KEY `idx_active_slot` (`active`,`slot`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `prompt_globals`
--
ALTER TABLE `prompt_globals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prompt_globals_name` (`name`);

--
-- Indexes for table `prompt_ideations`
--
ALTER TABLE `prompt_ideations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `prompt_matrix`
--
ALTER TABLE `prompt_matrix`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_map_run` (`active_map_run_id`),
  ADD KEY `idx_state` (`state_id_active`);

--
-- Indexes for table `prompt_matrix_additions`
--
ALTER TABLE `prompt_matrix_additions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_matrix_slot` (`matrix_id`,`slot`),
  ADD KEY `idx_addition_id` (`addition_id`);

--
-- Indexes for table `prompt_matrix_blueprints`
--
ALTER TABLE `prompt_matrix_blueprints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sketches_name` (`name`);

--
-- Indexes for table `prompt_negative_globals`
--
ALTER TABLE `prompt_negative_globals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prompt_globals_name` (`name`);

--
-- Indexes for table `rapid_showcase`
--
ALTER TABLE `rapid_showcase`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_generated` (`is_generated`);

--
-- Indexes for table `scenes`
--
ALTER TABLE `scenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scenes_sequence` (`sequence`),
  ADD KEY `fk_scene_arc` (`arc_id`);

--
-- Indexes for table `scene_kitchen_pots`
--
ALTER TABLE `scene_kitchen_pots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `scene_parts`
--
ALTER TABLE `scene_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scene_parts_scene` (`scene_id`),
  ADD KEY `idx_scene_parts_scene_seq` (`scene_id`,`sequence`);

--
-- Indexes for table `scene_part_animas`
--
ALTER TABLE `scene_part_animas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_span_unique` (`scene_part_id`,`character_anima_id`,`action_type`),
  ADD KEY `idx_span_scene_part` (`scene_part_id`),
  ADD KEY `idx_span_character_anima` (`character_anima_id`);

--
-- Indexes for table `scene_part_artifacts`
--
ALTER TABLE `scene_part_artifacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spa_unique` (`scene_part_id`,`artifact_id`),
  ADD KEY `idx_spa_artifact` (`artifact_id`),
  ADD KEY `idx_spa_scene_part` (`scene_part_id`);

--
-- Indexes for table `scene_part_backgrounds`
--
ALTER TABLE `scene_part_backgrounds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spb_unique` (`perspective_id`,`background_id`),
  ADD KEY `idx_spb_background` (`background_id`),
  ADD KEY `idx_spb_perspective` (`perspective_id`);

--
-- Indexes for table `scene_part_characters`
--
ALTER TABLE `scene_part_characters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spc_unique` (`scene_part_id`,`character_id`),
  ADD KEY `idx_spc_char` (`character_id`),
  ADD KEY `idx_spc_scene_part` (`scene_part_id`);

--
-- Indexes for table `scene_part_tags`
--
ALTER TABLE `scene_part_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spt_unique` (`scene_part_id`,`tag_id`),
  ADD KEY `idx_spt_tag` (`tag_id`),
  ADD KEY `idx_spt_scene_part` (`scene_part_id`);

--
-- Indexes for table `scene_part_versions`
--
ALTER TABLE `scene_part_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_spv_unique` (`scene_part_id`,`version_number`),
  ADD KEY `idx_spv_scene_part` (`scene_part_id`),
  ADD KEY `idx_spv_version` (`version_number`);

--
-- Indexes for table `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_time` (`active`,`schedule_time`),
  ADD KEY `idx_active_interval` (`active`,`schedule_interval`),
  ADD KEY `idx_last_run` (`last_run`);

--
-- Indexes for table `scheduler_heartbeat`
--
ALTER TABLE `scheduler_heartbeat`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seeds`
--
ALTER TABLE `seeds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seeds_value` (`value`);

--
-- Indexes for table `sequence_overlay_texts`
--
ALTER TABLE `sequence_overlay_texts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seq_lang` (`sequence_id`,`language_code`);

--
-- Indexes for table `shot_types`
--
ALTER TABLE `shot_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sketches`
--
ALTER TABLE `sketches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sketches_name` (`name`);

--
-- Indexes for table `sketchmig_bundle`
--
ALTER TABLE `sketchmig_bundle`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sketchmig_frame`
--
ALTER TABLE `sketchmig_frame`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bundle_frame_key` (`bundle_id`,`meta_frame_key`),
  ADD KEY `idx_smig_frame_bundle` (`bundle_id`),
  ADD KEY `idx_smig_frame_sketch` (`meta_sketch_key`);

--
-- Indexes for table `sketchmig_sketch`
--
ALTER TABLE `sketchmig_sketch`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bundle_sketch_key` (`bundle_id`,`meta_sketch_key`),
  ADD KEY `idx_smig_bundle` (`bundle_id`);

--
-- Indexes for table `sketch_analysis`
--
ALTER TABLE `sketch_analysis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_sketch` (`sketch_id`),
  ADD KEY `idx_quality` (`overall_quality`);

--
-- Indexes for table `sketch_categories`
--
ALTER TABLE `sketch_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sketch_ingredients`
--
ALTER TABLE `sketch_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sketch_id` (`sketch_id`),
  ADD KEY `type_source` (`ingredient_type`,`source_id`);

--
-- Indexes for table `sketch_location_ranges`
--
ALTER TABLE `sketch_location_ranges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sketch_range` (`sketch_id_from`,`sketch_id_to`);

--
-- Indexes for table `sketch_lore_history`
--
ALTER TABLE `sketch_lore_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sketch` (`sketch_id`),
  ADD KEY `idx_doc` (`doc_id`),
  ADD KEY `idx_entity` (`entity_name`);

--
-- Indexes for table `sketch_migration_entities`
--
ALTER TABLE `sketch_migration_entities`
  ADD PRIMARY KEY (`source_type`,`source_id`),
  ADD KEY `idx_target_sketch` (`target_sketch_id`);

--
-- Indexes for table `sketch_migration_frames`
--
ALTER TABLE `sketch_migration_frames`
  ADD PRIMARY KEY (`source_frame_id`),
  ADD KEY `idx_target_frame` (`target_frame_id`);

--
-- Indexes for table `sketch_overlay_texts`
--
ALTER TABLE `sketch_overlay_texts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sketch_id` (`sketch_id`);

--
-- Indexes for table `sketch_sequence_analysis`
--
ALTER TABLE `sketch_sequence_analysis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sketch_sequence` (`sketch_id`),
  ADD KEY `idx_energy` (`energy`),
  ADD KEY `idx_position` (`position`),
  ADD KEY `idx_intensity` (`intensity`),
  ADD KEY `idx_standalone` (`standalone`),
  ADD KEY `idx_shot_scale` (`shot_scale`),
  ADD KEY `idx_edit_relationship` (`edit_relationship`),
  ADD KEY `idx_structure_type` (`structure_type`),
  ADD KEY `idx_world_specificity` (`world_specificity`),
  ADD KEY `idx_confidence` (`confidence`),
  ADD KEY `idx_nf_mask` (`narrative_function_mask`),
  ADD KEY `idx_layer_mask` (`layer_mask`);

--
-- Indexes for table `sketch_templates`
--
ALTER TABLE `sketch_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_type` (`entity_type`),
  ADD KEY `active` (`active`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `spawns`
--
ALTER TABLE `spawns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_seeds_type` (`type`),
  ADD KEY `idx_spawn_type_id` (`spawn_type_id`);

--
-- Indexes for table `spawn_types`
--
ALTER TABLE `spawn_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_states_name` (`name`);

--
-- Indexes for table `storyboards`
--
ALTER TABLE `storyboards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `directory` (`directory`),
  ADD KEY `idx_sb_category` (`category`),
  ADD KEY `idx_sb_scene` (`editorial_scene_id`);

--
-- Indexes for table `storyboard_categories`
--
ALTER TABLE `storyboard_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_code` (`code`);

--
-- Indexes for table `storyboard_frames`
--
ALTER TABLE `storyboard_frames`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_storyboard` (`storyboard_id`),
  ADD KEY `idx_frame` (`frame_id`),
  ADD KEY `idx_sort` (`storyboard_id`,`sort_order`);

--
-- Indexes for table `story_arcs`
--
ALTER TABLE `story_arcs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `styles`
--
ALTER TABLE `styles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_styles_name` (`name`);

--
-- Indexes for table `style_profiles`
--
ALTER TABLE `style_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_axis_group` (`axis_group`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `style_profile_axes`
--
ALTER TABLE `style_profile_axes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_profile_axis` (`profile_id`,`axis_id`),
  ADD KEY `profile_id` (`profile_id`),
  ADD KEY `axis_id` (`axis_id`);

--
-- Indexes for table `style_profile_config`
--
ALTER TABLE `style_profile_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Indexes for table `system_languages`
--
ALTER TABLE `system_languages`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tags_name` (`name`);

--
-- Indexes for table `tags2poses`
--
ALTER TABLE `tags2poses`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `tags_2_animatics`
--
ALTER TABLE `tags_2_animatics`
  ADD UNIQUE KEY `uq_tags_2_animatics` (`from_id`,`to_id`),
  ADD KEY `idx_t2a_from` (`from_id`),
  ADD KEY `idx_t2a_to` (`to_id`);

--
-- Indexes for table `tags_2_frames`
--
ALTER TABLE `tags_2_frames`
  ADD UNIQUE KEY `uq_tags_2_frames` (`from_id`,`to_id`);

--
-- Indexes for table `tags_2_frames_staged`
--
ALTER TABLE `tags_2_frames_staged`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staged` (`tag_id`,`frame_id`),
  ADD KEY `idx_frame_id` (`frame_id`),
  ADD KEY `idx_reviewed` (`reviewed`),
  ADD KEY `idx_run_id` (`run_id`);

--
-- Indexes for table `tags_2_sketches`
--
ALTER TABLE `tags_2_sketches`
  ADD UNIQUE KEY `uq_tags_2_sketches` (`from_id`,`to_id`),
  ADD KEY `idx_t2s_from` (`from_id`),
  ADD KEY `idx_t2s_to` (`to_id`);

--
-- Indexes for table `tags_2_videos`
--
ALTER TABLE `tags_2_videos`
  ADD UNIQUE KEY `uq_tags_2_videos` (`from_id`,`to_id`),
  ADD KEY `idx_t2v_from` (`from_id`),
  ADD KEY `idx_t2v_to` (`to_id`);

--
-- Indexes for table `tags_2_videos_staged`
--
ALTER TABLE `tags_2_videos_staged`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staged_vid` (`tag_id`,`video_id`),
  ADD KEY `idx_video_id` (`video_id`),
  ADD KEY `idx_reviewed` (`reviewed`),
  ADD KEY `idx_run_id` (`run_id`);

--
-- Indexes for table `task_execution_stats`
--
ALTER TABLE `task_execution_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_task_date` (`task_id`,`date`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `task_locks`
--
ALTER TABLE `task_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lock_key` (`lock_key`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `run_id` (`run_id`),
  ADD KEY `idx_status_expires` (`status`,`expires_at`),
  ADD KEY `idx_task_locks_owner_token` (`owner_token`);

--
-- Indexes for table `task_runs`
--
ALTER TABLE `task_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `lock_id` (`lock_id`),
  ADD KEY `idx_task_runs_status_pid` (`status`,`pid`);

--
-- Indexes for table `task_wrappers`
--
ALTER TABLE `task_wrappers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `google_id_unique` (`google_id`);

--
-- Indexes for table `vector_state`
--
ALTER TABLE `vector_state`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_entity_target` (`entity_type`,`entity_id`,`collection`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `vedtriccs_connectors`
--
ALTER TABLE `vedtriccs_connectors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_file_key` (`file_id`,`connector_key`);

--
-- Indexes for table `vedtriccs_projects`
--
ALTER TABLE `vedtriccs_projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vedtriccs_project_files`
--
ALTER TABLE `vedtriccs_project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `vedtriccs_render_jobs`
--
ALTER TABLE `vedtriccs_render_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_trans` (`transition_name`);

--
-- Indexes for table `vedtriccs_transition_demos`
--
ALTER TABLE `vedtriccs_transition_demos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_trans_vid` (`transition_name`,`video_id`),
  ADD KEY `idx_trans` (`transition_name`);

--
-- Indexes for table `ved_projects`
--
ALTER TABLE `ved_projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ved_project_files`
--
ALTER TABLE `ved_project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vehicles_name` (`name`),
  ADD KEY `idx_vehicles_type` (`type`),
  ADD KEY `idx_vehicles_status` (`status`);

--
-- Indexes for table `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `videos_2_animatics`
--
ALTER TABLE `videos_2_animatics`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `videos_2_composites`
--
ALTER TABLE `videos_2_composites`
  ADD PRIMARY KEY (`from_id`,`to_id`),
  ADD KEY `composite_id` (`to_id`);

--
-- Indexes for table `videos_2_derivates`
--
ALTER TABLE `videos_2_derivates`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `videos_2_montages`
--
ALTER TABLE `videos_2_montages`
  ADD PRIMARY KEY (`from_id`,`to_id`);

--
-- Indexes for table `video_categories`
--
ALTER TABLE `video_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `video_enhancements`
--
ALTER TABLE `video_enhancements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ve_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_ve_regen` (`regenerate_videos`);

--
-- Indexes for table `video_playlists`
--
ALTER TABLE `video_playlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `video_tree_items`
--
ALTER TABLE `video_tree_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_node_video` (`node_id`,`video_id`),
  ADD KEY `idx_node` (`node_id`),
  ADD KEY `idx_video` (`video_id`);

--
-- Indexes for table `video_tree_nodes`
--
ALTER TABLE `video_tree_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parent` (`parent_id`);

--
-- Indexes for table `weather_conditions`
--
ALTER TABLE `weather_conditions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_weather_name` (`name`);

--
-- Indexes for table `worker_img_api_endpoint`
--
ALTER TABLE `worker_img_api_endpoint`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_worker_img_endpoint_code` (`endpoint_code`),
  ADD KEY `idx_worker_img_endpoint_enabled` (`is_enabled`),
  ADD KEY `idx_worker_img_endpoint_provider` (`provider_name`);

--
-- Indexes for table `worker_img_api_endpoint_param`
--
ALTER TABLE `worker_img_api_endpoint_param`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_worker_img_endpoint_param` (`endpoint_id`,`param_key`,`location`),
  ADD KEY `idx_worker_img_param_endpoint` (`endpoint_id`),
  ADD KEY `idx_worker_img_param_key` (`param_key`),
  ADD KEY `idx_worker_img_param_enabled` (`is_enabled`),
  ADD KEY `idx_worker_img_param_sort` (`endpoint_id`,`sort_order`);

--
-- Indexes for table `worker_img_provider_default`
--
ALTER TABLE `worker_img_provider_default`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_worker_img_provider_default_scope` (`scope`);

--
-- Indexes for table `wroom_chat_sessions`
--
ALTER TABLE `wroom_chat_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_wroom_chats_user` (`user_id`);

--
-- Indexes for table `wroom_chekhov`
--
ALTER TABLE `wroom_chekhov`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wroom_chekhov_user` (`user_id`);

--
-- Indexes for table `wroom_conversation`
--
ALTER TABLE `wroom_conversation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wroom_conversation_user` (`user_id`),
  ADD KEY `idx_wroom_conv_session` (`session_id`);

--
-- Indexes for table `wroom_deltas`
--
ALTER TABLE `wroom_deltas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wroom_deltas_user` (`user_id`);

--
-- Indexes for table `wroom_settings`
--
ALTER TABLE `wroom_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `wroom_threads`
--
ALTER TABLE `wroom_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wroom_threads_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ag_categories`
--
ALTER TABLE `ag_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ag_nodes`
--
ALTER TABLE `ag_nodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ag_node_items`
--
ALTER TABLE `ag_node_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `animas`
--
ALTER TABLE `animas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `animatics`
--
ALTER TABLE `animatics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `animation_mouthshapes`
--
ALTER TABLE `animation_mouthshapes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_action_effects`
--
ALTER TABLE `anivoc_action_effects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_backgrounds`
--
ALTER TABLE `anivoc_backgrounds`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_categories`
--
ALTER TABLE `anivoc_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_character_states`
--
ALTER TABLE `anivoc_character_states`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_chibi_modes`
--
ALTER TABLE `anivoc_chibi_modes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_color_coding`
--
ALTER TABLE `anivoc_color_coding`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_duo_compositions`
--
ALTER TABLE `anivoc_duo_compositions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_expressions`
--
ALTER TABLE `anivoc_expressions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_hand_gestures`
--
ALTER TABLE `anivoc_hand_gestures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_lighting`
--
ALTER TABLE `anivoc_lighting`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_motion_impact`
--
ALTER TABLE `anivoc_motion_impact`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_panel_frame`
--
ALTER TABLE `anivoc_panel_frame`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_scale_perspective`
--
ALTER TABLE `anivoc_scale_perspective`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_scene_functions`
--
ALTER TABLE `anivoc_scene_functions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_shot_pacing`
--
ALTER TABLE `anivoc_shot_pacing`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_symbolic_objects`
--
ALTER TABLE `anivoc_symbolic_objects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_text_graphics`
--
ALTER TABLE `anivoc_text_graphics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anivoc_transitions`
--
ALTER TABLE `anivoc_transitions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `artifacts`
--
ALTER TABLE `artifacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audios`
--
ALTER TABLE `audios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audios_2_documentations`
--
ALTER TABLE `audios_2_documentations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_ambiences`
--
ALTER TABLE `audio_ambiences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_cues`
--
ALTER TABLE `audio_cues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_dialogue_lines`
--
ALTER TABLE `audio_dialogue_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_foleys`
--
ALTER TABLE `audio_foleys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_fxsounds`
--
ALTER TABLE `audio_fxsounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_themes`
--
ALTER TABLE `audio_themes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_voice_identity`
--
ALTER TABLE `audio_voice_identity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_voice_identity_xmpl`
--
ALTER TABLE `audio_voice_identity_xmpl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backgrounds`
--
ALTER TABLE `backgrounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backups_media`
--
ALTER TABLE `backups_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_destinations`
--
ALTER TABLE `backup_destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_jobs`
--
ALTER TABLE `backup_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_runs`
--
ALTER TABLE `backup_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `boards`
--
ALTER TABLE `boards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `boards_categories`
--
ALTER TABLE `boards_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `boards_items`
--
ALTER TABLE `boards_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `camera_angles`
--
ALTER TABLE `camera_angles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `camera_perspectives`
--
ALTER TABLE `camera_perspectives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `characters`
--
ALTER TABLE `characters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `character_anima_poses`
--
ALTER TABLE `character_anima_poses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `character_expressions`
--
ALTER TABLE `character_expressions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `character_poses`
--
ALTER TABLE `character_poses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_message`
--
ALTER TABLE `chat_message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_session`
--
ALTER TABLE `chat_session`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_summary`
--
ALTER TABLE `chat_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chroma_collections`
--
ALTER TABLE `chroma_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cinemagics`
--
ALTER TABLE `cinemagics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cinemagic_hub_posts`
--
ALTER TABLE `cinemagic_hub_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cinemagic_series`
--
ALTER TABLE `cinemagic_series`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clipboard_items`
--
ALTER TABLE `clipboard_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clipboard_visibility`
--
ALTER TABLE `clipboard_visibility`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `color_grade_presets`
--
ALTER TABLE `color_grade_presets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `color_grade_profiles`
--
ALTER TABLE `color_grade_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `composites`
--
ALTER TABLE `composites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `content_elements`
--
ALTER TABLE `content_elements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `content_hub_posts`
--
ALTER TABLE `content_hub_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `continuity_jobs`
--
ALTER TABLE `continuity_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `controlnet_maps`
--
ALTER TABLE `controlnet_maps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daw_projects`
--
ALTER TABLE `daw_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daw_project_files`
--
ALTER TABLE `daw_project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daw_shot_saves`
--
ALTER TABLE `daw_shot_saves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `derivates`
--
ALTER TABLE `derivates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `design_axes`
--
ALTER TABLE `design_axes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dict_dictionaries`
--
ALTER TABLE `dict_dictionaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dict_lemmas`
--
ALTER TABLE `dict_lemmas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dict_lemma_2_dictionary`
--
ALTER TABLE `dict_lemma_2_dictionary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dict_source_files`
--
ALTER TABLE `dict_source_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dimensionals`
--
ALTER TABLE `dimensionals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documentations`
--
ALTER TABLE `documentations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documentation_categories`
--
ALTER TABLE `documentation_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `editorial_episodes`
--
ALTER TABLE `editorial_episodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `editorial_scenes`
--
ALTER TABLE `editorial_scenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `editorial_seasons`
--
ALTER TABLE `editorial_seasons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `editorial_sequences`
--
ALTER TABLE `editorial_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `editorial_series`
--
ALTER TABLE `editorial_series`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `editorial_shots`
--
ALTER TABLE `editorial_shots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `editorial_shot_dialogues`
--
ALTER TABLE `editorial_shot_dialogues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `export_flags`
--
ALTER TABLE `export_flags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `factions`
--
ALTER TABLE `factions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_notes`
--
ALTER TABLE `feedback_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forge_jobs`
--
ALTER TABLE `forge_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forge_tool_settings`
--
ALTER TABLE `forge_tool_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `frames`
--
ALTER TABLE `frames`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `frames_2_artifacts`
--
ALTER TABLE `frames_2_artifacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `frames_chains`
--
ALTER TABLE `frames_chains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `frames_failed`
--
ALTER TABLE `frames_failed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `frames_trashcan`
--
ALTER TABLE `frames_trashcan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `frame_enhancements`
--
ALTER TABLE `frame_enhancements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuzz_candidates`
--
ALTER TABLE `fuzz_candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuzz_candidate_aliases`
--
ALTER TABLE `fuzz_candidate_aliases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuzz_links`
--
ALTER TABLE `fuzz_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuzz_mentions`
--
ALTER TABLE `fuzz_mentions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuzz_queue`
--
ALTER TABLE `fuzz_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuzz_resolutions`
--
ALTER TABLE `fuzz_resolutions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuzz_reviews`
--
ALTER TABLE `fuzz_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generated_phrase_maps`
--
ALTER TABLE `generated_phrase_maps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generatives`
--
ALTER TABLE `generatives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generator_config`
--
ALTER TABLE `generator_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generator_config_display_area`
--
ALTER TABLE `generator_config_display_area`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generator_config_history`
--
ALTER TABLE `generator_config_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gs_assign_config`
--
ALTER TABLE `gs_assign_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `image_edits`
--
ALTER TABLE `image_edits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `image_stash`
--
ALTER TABLE `image_stash`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interactions`
--
ALTER TABLE `interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `json_categories`
--
ALTER TABLE `json_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `json_files`
--
ALTER TABLE `json_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kg_categories`
--
ALTER TABLE `kg_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kg_nodes`
--
ALTER TABLE `kg_nodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kg_node_items`
--
ALTER TABLE `kg_node_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kg_staging_categories`
--
ALTER TABLE `kg_staging_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kg_staging_nodes`
--
ALTER TABLE `kg_staging_nodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kg_staging_node_items`
--
ALTER TABLE `kg_staging_node_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lightings`
--
ALTER TABLE `lightings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations_abstract`
--
ALTER TABLE `locations_abstract`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lore_entities`
--
ALTER TABLE `lore_entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `magazine_pdf_jobs`
--
ALTER TABLE `magazine_pdf_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `map_runs`
--
ALTER TABLE `map_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `map_run_queue`
--
ALTER TABLE `map_run_queue`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `md_doc_analysis`
--
ALTER TABLE `md_doc_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `md_doc_chunks`
--
ALTER TABLE `md_doc_chunks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meshes`
--
ALTER TABLE `meshes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meta_entities`
--
ALTER TABLE `meta_entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meta_sketches`
--
ALTER TABLE `meta_sketches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `montages`
--
ALTER TABLE `montages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `motion_camera_presets`
--
ALTER TABLE `motion_camera_presets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `motion_layers`
--
ALTER TABLE `motion_layers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `motion_render_queue`
--
ALTER TABLE `motion_render_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `motion_setups`
--
ALTER TABLE `motion_setups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `motion_takes`
--
ALTER TABLE `motion_takes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `multiplane_arrangements`
--
ALTER TABLE `multiplane_arrangements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `multivid_arrangements`
--
ALTER TABLE `multivid_arrangements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `multivid_render_jobs`
--
ALTER TABLE `multivid_render_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `muvitriccs_projects`
--
ALTER TABLE `muvitriccs_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `muvitriccs_render_jobs`
--
ALTER TABLE `muvitriccs_render_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `muvitriccs_slots`
--
ALTER TABLE `muvitriccs_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `muvitriccs_transition_demos`
--
ALTER TABLE `muvitriccs_transition_demos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `narrative_beat_analysis`
--
ALTER TABLE `narrative_beat_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `narrative_sequences`
--
ALTER TABLE `narrative_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `narrative_sequences_auto`
--
ALTER TABLE `narrative_sequences_auto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `narrative_sequence_analysis`
--
ALTER TABLE `narrative_sequence_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pastebin`
--
ALTER TABLE `pastebin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `perspectives`
--
ALTER TABLE `perspectives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `playlist_videos`
--
ALTER TABLE `playlist_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plush_collections`
--
ALTER TABLE `plush_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plush_highlight_blocks`
--
ALTER TABLE `plush_highlight_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plush_highlight_block_entities`
--
ALTER TABLE `plush_highlight_block_entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plush_highlight_groups`
--
ALTER TABLE `plush_highlight_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plush_scenes`
--
ALTER TABLE `plush_scenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plush_scene_dates`
--
ALTER TABLE `plush_scene_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plush_stories`
--
ALTER TABLE `plush_stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plush_story_dates`
--
ALTER TABLE `plush_story_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poses`
--
ALTER TABLE `poses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `poses_anima`
--
ALTER TABLE `poses_anima`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_status`
--
ALTER TABLE `production_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompt_additions`
--
ALTER TABLE `prompt_additions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompt_globals`
--
ALTER TABLE `prompt_globals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompt_ideations`
--
ALTER TABLE `prompt_ideations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompt_matrix`
--
ALTER TABLE `prompt_matrix`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompt_matrix_additions`
--
ALTER TABLE `prompt_matrix_additions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompt_matrix_blueprints`
--
ALTER TABLE `prompt_matrix_blueprints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompt_negative_globals`
--
ALTER TABLE `prompt_negative_globals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rapid_showcase`
--
ALTER TABLE `rapid_showcase`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scenes`
--
ALTER TABLE `scenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scene_kitchen_pots`
--
ALTER TABLE `scene_kitchen_pots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scene_parts`
--
ALTER TABLE `scene_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scene_part_animas`
--
ALTER TABLE `scene_part_animas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scene_part_artifacts`
--
ALTER TABLE `scene_part_artifacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scene_part_backgrounds`
--
ALTER TABLE `scene_part_backgrounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scene_part_characters`
--
ALTER TABLE `scene_part_characters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scene_part_tags`
--
ALTER TABLE `scene_part_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scene_part_versions`
--
ALTER TABLE `scene_part_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scheduled_tasks`
--
ALTER TABLE `scheduled_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seeds`
--
ALTER TABLE `seeds`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sequence_overlay_texts`
--
ALTER TABLE `sequence_overlay_texts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shot_types`
--
ALTER TABLE `shot_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketches`
--
ALTER TABLE `sketches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketchmig_bundle`
--
ALTER TABLE `sketchmig_bundle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketchmig_frame`
--
ALTER TABLE `sketchmig_frame`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketchmig_sketch`
--
ALTER TABLE `sketchmig_sketch`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketch_analysis`
--
ALTER TABLE `sketch_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketch_categories`
--
ALTER TABLE `sketch_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketch_ingredients`
--
ALTER TABLE `sketch_ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketch_location_ranges`
--
ALTER TABLE `sketch_location_ranges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketch_lore_history`
--
ALTER TABLE `sketch_lore_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketch_overlay_texts`
--
ALTER TABLE `sketch_overlay_texts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketch_sequence_analysis`
--
ALTER TABLE `sketch_sequence_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sketch_templates`
--
ALTER TABLE `sketch_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `spawns`
--
ALTER TABLE `spawns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `spawn_types`
--
ALTER TABLE `spawn_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `storyboards`
--
ALTER TABLE `storyboards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `storyboard_categories`
--
ALTER TABLE `storyboard_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `storyboard_frames`
--
ALTER TABLE `storyboard_frames`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `story_arcs`
--
ALTER TABLE `story_arcs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `styles`
--
ALTER TABLE `styles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `style_profiles`
--
ALTER TABLE `style_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `style_profile_axes`
--
ALTER TABLE `style_profile_axes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `style_profile_config`
--
ALTER TABLE `style_profile_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags_2_frames_staged`
--
ALTER TABLE `tags_2_frames_staged`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags_2_videos_staged`
--
ALTER TABLE `tags_2_videos_staged`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_execution_stats`
--
ALTER TABLE `task_execution_stats`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_locks`
--
ALTER TABLE `task_locks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_runs`
--
ALTER TABLE `task_runs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_wrappers`
--
ALTER TABLE `task_wrappers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vector_state`
--
ALTER TABLE `vector_state`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vedtriccs_connectors`
--
ALTER TABLE `vedtriccs_connectors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vedtriccs_projects`
--
ALTER TABLE `vedtriccs_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vedtriccs_project_files`
--
ALTER TABLE `vedtriccs_project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vedtriccs_render_jobs`
--
ALTER TABLE `vedtriccs_render_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vedtriccs_transition_demos`
--
ALTER TABLE `vedtriccs_transition_demos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ved_projects`
--
ALTER TABLE `ved_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ved_project_files`
--
ALTER TABLE `ved_project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_categories`
--
ALTER TABLE `video_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_enhancements`
--
ALTER TABLE `video_enhancements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_playlists`
--
ALTER TABLE `video_playlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_tree_items`
--
ALTER TABLE `video_tree_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_tree_nodes`
--
ALTER TABLE `video_tree_nodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weather_conditions`
--
ALTER TABLE `weather_conditions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worker_img_api_endpoint`
--
ALTER TABLE `worker_img_api_endpoint`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worker_img_api_endpoint_param`
--
ALTER TABLE `worker_img_api_endpoint_param`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worker_img_provider_default`
--
ALTER TABLE `worker_img_provider_default`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wroom_chekhov`
--
ALTER TABLE `wroom_chekhov`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wroom_conversation`
--
ALTER TABLE `wroom_conversation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wroom_deltas`
--
ALTER TABLE `wroom_deltas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wroom_threads`
--
ALTER TABLE `wroom_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `v_anima_activity`
--
DROP TABLE IF EXISTS `v_anima_activity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_anima_activity`  AS SELECT `s`.`id` AS `scene_id`, `s`.`sequence` AS `scene_sequence`, `sp`.`id` AS `scene_part_id`, `sp`.`sequence` AS `part_sequence`, `a`.`id` AS `character_anima_id`, `ch`.`name` AS `character_name`, `a`.`name` AS `anima_name`, `span`.`action_type` AS `action_type`, `span`.`notes` AS `notes` FROM ((((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_animas` `span` on(`span`.`scene_part_id` = `sp`.`id`)) join `animas` `a` on(`a`.`id` = `span`.`character_anima_id`)) join `characters` `ch` on(`ch`.`id` = `a`.`character_id`)) ORDER BY `s`.`sequence` ASC, `sp`.`sequence` ASC, `ch`.`name` ASC, `a`.`name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_artifact_usage`
--
DROP TABLE IF EXISTS `v_artifact_usage`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_artifact_usage`  AS SELECT `s`.`id` AS `scene_id`, `s`.`sequence` AS `scene_sequence`, `sp`.`id` AS `scene_part_id`, `sp`.`sequence` AS `part_sequence`, `a`.`id` AS `artifact_id`, `a`.`name` AS `artifact_name`, `a`.`type` AS `artifact_type`, `a`.`status` AS `artifact_status`, `spa`.`notes` AS `notes` FROM (((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `scene_part_artifacts` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) join `artifacts` `a` on(`a`.`id` = `spa`.`artifact_id`)) ORDER BY `s`.`sequence` ASC, `sp`.`sequence` ASC, `a`.`name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_character_anima_pose_angle_combinations`
--
DROP TABLE IF EXISTS `v_character_anima_pose_angle_combinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_character_anima_pose_angle_combinations`  AS SELECT `c`.`id` AS `character_id`, `c`.`name` AS `character_name`, `p`.`id` AS `pose_id`, `p`.`name` AS `pose_name`, `ca`.`id` AS `angle_id`, `ca`.`name` AS `angle_name`, `cp`.`id` AS `perspective_id`, `cp`.`name` AS `perspective_name`, concat('((',ifnull(`p`.`description`,`p`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),'))') AS `base_prompt`, concat('((',ifnull(`p`.`description`,`p`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),')), ',ifnull(`c`.`description`,'')) AS `description` FROM (((`characters` `c` join `poses_anima` `p`) join `camera_angles` `ca`) join `camera_perspectives` `cp`) ;

-- --------------------------------------------------------

--
-- Structure for view `v_character_expression_angle_combinations`
--
DROP TABLE IF EXISTS `v_character_expression_angle_combinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_character_expression_angle_combinations`  AS SELECT `c`.`id` AS `character_id`, `c`.`name` AS `character_name`, `e`.`id` AS `expression_id`, `e`.`name` AS `expression_name`, `ca`.`id` AS `angle_id`, `ca`.`name` AS `angle_name`, `cp`.`id` AS `perspective_id`, `cp`.`name` AS `perspective_name`, concat('((',ifnull(`e`.`description`,`e`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),'))') AS `base_prompt`, concat('((',ifnull(`e`.`description`,`e`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),')), ',ifnull(`c`.`description`,'')) AS `description` FROM (((`characters` `c` join `anivoc_expressions` `e`) join `camera_angles` `ca`) join `camera_perspectives` `cp`) ;

-- --------------------------------------------------------

--
-- Structure for view `v_character_pose_angle_combinations`
--
DROP TABLE IF EXISTS `v_character_pose_angle_combinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_character_pose_angle_combinations`  AS SELECT `c`.`id` AS `character_id`, `c`.`name` AS `character_name`, `p`.`id` AS `pose_id`, `p`.`name` AS `pose_name`, `ca`.`id` AS `angle_id`, `ca`.`name` AS `angle_name`, `cp`.`id` AS `perspective_id`, `cp`.`name` AS `perspective_name`, concat('((',ifnull(`p`.`description`,`p`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),'))') AS `base_prompt`, concat('((',ifnull(`p`.`description`,`p`.`name`),')), ','((',ifnull(`cp`.`description`,`cp`.`name`),')), ','((',ifnull(`ca`.`description`,`ca`.`name`),')), ',ifnull(`c`.`description`,'')) AS `description` FROM (((`characters` `c` join `poses` `p`) join `camera_angles` `ca`) join `camera_perspectives` `cp`) ;

-- --------------------------------------------------------

--
-- Structure for view `v_export_ready`
--
DROP TABLE IF EXISTS `v_export_ready`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_export_ready`  AS SELECT `s`.`id` AS `scene_id`, `s`.`title` AS `scene_title`, `sp`.`id` AS `scene_part_id`, `ef`.`export_type` AS `export_type`, `ef`.`last_exported_at` AS `last_exported_at` FROM ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `export_flags` `ef` on(`ef`.`scene_part_id` = `sp`.`id`)) WHERE `ef`.`ready_for_export` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `v_gallery_animas`
--
DROP TABLE IF EXISTS `v_gallery_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost`
SQL SECURITY DEFINER VIEW `v_gallery_animas` AS
select
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
  _utf8mb4'animas' collate utf8mb4_general_ci AS `entity_type`
from
  ((((`frames` `f`
  join `frames_2_animas` `m` on(`m`.`from_id` = `f`.`id`))
  join `animas` `a` on(`a`.`id` = `m`.`to_id`))
  left join `characters` `c` on(`c`.`id` = `a`.`character_id`))
  join `styles` `s` on(`f`.`style_id` = `s`.`id`))
where `s`.`visible` = 1
order by `s`.`order`,`f`.`created_at` desc;

-- --------------------------------------------------------

--
-- Structure for view `v_gallery_animatics`
--
DROP TABLE IF EXISTS `v_gallery_animatics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_animatics`  AS SELECT `f`.`id` AS `frame_id`, `a`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `a`.`name` AS `name`, `a`.`description` AS `description`, `f`.`map_run_id` AS `map_run_id`, `f`.`created_at` AS `created_at` FROM ((`frames` `f` join `frames_2_animatics` `fa` on(`f`.`id` = `fa`.`from_id`)) join `animatics` `a` on(`fa`.`to_id` = `a`.`id`)) ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_gallery_artifacts`
--
DROP TABLE IF EXISTS `v_gallery_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_artifacts`  AS SELECT `f`.`id` AS `frame_id`, `a`.`id` AS `entity_id`, `f`.`filename` AS `filename`, `f`.`prompt` AS `prompt`, `f`.`style` AS `style`, `a`.`id` AS `artifact_id`, `a`.`name` AS `artifact_name`, `a`.`type` AS `artifact_type`, `a`.`status` AS `artifact_status` FROM (((`frames` `f` join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `a` on(`m`.`to_id` = `a`.`id`)) join `styles` `s` on(`f`.`style_id` = `s`.`id`)) WHERE `s`.`visible` = 1 ORDER BY `f`.`created_at` DESC ;

-- --------------------------------------------------------
-- --------------------------------------------------------
--
-- Structure for view `v_gallery_backgrounds`
--
-- --------------------------------------------------------
--
-- Structure for view `v_gallery_backgrounds`
--
DROP TABLE IF EXISTS `v_gallery_backgrounds`;
DROP VIEW IF EXISTS `v_gallery_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_backgrounds` AS
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
  _utf8mb4'backgrounds' COLLATE utf8mb4_general_ci AS `entity_type`
FROM ((((`frames` `f`
  JOIN `frames_2_backgrounds` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `backgrounds` `b` ON (`m`.`to_id` = `b`.`id`))
  LEFT JOIN `locations` `l` ON (`b`.`location_id` = `l`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_characters`
--
DROP TABLE IF EXISTS `v_gallery_characters`;
DROP VIEW IF EXISTS `v_gallery_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_characters` AS
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
  _utf8mb4'characters' COLLATE utf8mb4_general_ci AS `entity_type`
FROM (((`frames` `f`
  JOIN `frames_2_characters` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `characters` `c` ON (`m`.`to_id` = `c`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `s`.`order` ASC, `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_character_anima_poses`
--
DROP TABLE IF EXISTS `v_gallery_character_anima_poses`;
DROP VIEW IF EXISTS `v_gallery_character_anima_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_character_anima_poses` AS
SELECT
  `f`.`id` AS `frame_id`,
  `cap`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `cap`.`description` AS `prompt`,
  `s`.`name` AS `style`,
  `cap`.`id` AS `character_pose_id`,
  `c`.`id` AS `character_id`,
  `c`.`name` AS `character_name`,
  `cap`.`pose_id` AS `pose_id`,
  `p`.`name` AS `pose_name`,
  `cap`.`angle_id` AS `angle_id`,
  `ca`.`name` AS `angle_name`,
  `cap`.`perspective_id` AS `perspective_id`,
  `cpe`.`name` AS `perspective_name`
FROM (((((((`frames` `f`
  JOIN `frames_2_character_anima_poses` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `character_anima_poses` `cap` ON (`m`.`to_id` = `cap`.`id`))
  JOIN `characters` `c` ON (`cap`.`character_id` = `c`.`id`))
  JOIN `poses_anima` `p` ON (`cap`.`pose_id` = `p`.`id`))
  JOIN `camera_angles` `ca` ON (`cap`.`angle_id` = `ca`.`id`))
  LEFT JOIN `camera_perspectives` `cpe` ON (`cap`.`perspective_id` = `cpe`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `s`.`order` ASC, `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_character_expressions`
--
DROP TABLE IF EXISTS `v_gallery_character_expressions`;
DROP VIEW IF EXISTS `v_gallery_character_expressions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_character_expressions` AS
SELECT
  `f`.`id` AS `frame_id`,
  `ce`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `ce`.`description` AS `prompt`,
  `s`.`name` AS `style`,
  `ce`.`id` AS `character_expression_id`,
  `c`.`id` AS `character_id`,
  `c`.`name` AS `character_name`,
  `ce`.`expression_id` AS `expression_id`,
  `e`.`name` AS `expression_name`,
  `ce`.`angle_id` AS `angle_id`,
  `ca`.`name` AS `angle_name`,
  `ce`.`perspective_id` AS `perspective_id`,
  `cp`.`name` AS `perspective_name`
FROM (((((((`frames` `f`
  JOIN `frames_2_character_expressions` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `character_expressions` `ce` ON (`m`.`to_id` = `ce`.`id`))
  JOIN `characters` `c` ON (`ce`.`character_id` = `c`.`id`))
  JOIN `anivoc_expressions` `e` ON (`ce`.`expression_id` = `e`.`id`))
  JOIN `camera_angles` `ca` ON (`ce`.`angle_id` = `ca`.`id`))
  JOIN `camera_perspectives` `cp` ON (`ce`.`perspective_id` = `cp`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `s`.`order` ASC, `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_character_poses`
--
DROP TABLE IF EXISTS `v_gallery_character_poses`;
DROP VIEW IF EXISTS `v_gallery_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_character_poses` AS
SELECT
  `f`.`id` AS `frame_id`,
  `cp`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `cp`.`description` AS `prompt`,
  `s`.`name` AS `style`,
  `cp`.`id` AS `character_pose_id`,
  `c`.`id` AS `character_id`,
  `c`.`name` AS `character_name`,
  `cp`.`pose_id` AS `pose_id`,
  `p`.`name` AS `pose_name`,
  `cp`.`angle_id` AS `angle_id`,
  `ca`.`name` AS `angle_name`,
  `cp`.`perspective_id` AS `perspective_id`,
  `cpe`.`name` AS `perspective_name`
FROM (((((((`frames` `f`
  JOIN `frames_2_character_poses` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `character_poses` `cp` ON (`m`.`to_id` = `cp`.`id`))
  JOIN `characters` `c` ON (`cp`.`character_id` = `c`.`id`))
  JOIN `poses` `p` ON (`cp`.`pose_id` = `p`.`id`))
  JOIN `camera_angles` `ca` ON (`cp`.`angle_id` = `ca`.`id`))
  LEFT JOIN `camera_perspectives` `cpe` ON (`cp`.`perspective_id` = `cpe`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `s`.`order` ASC, `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_composites`
--
DROP TABLE IF EXISTS `v_gallery_composites`;
DROP VIEW IF EXISTS `v_gallery_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_composites` AS
SELECT
  `f`.`id` AS `frame_id`,
  `c`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `c`.`id` AS `composite_id`,
  `c`.`name` AS `composite_name`
FROM (((`frames` `f`
  JOIN `frames_2_composites` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `composites` `c` ON (`m`.`to_id` = `c`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_controlnet_maps`
--
DROP TABLE IF EXISTS `v_gallery_controlnet_maps`;
DROP VIEW IF EXISTS `v_gallery_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_controlnet_maps` AS
SELECT
  `f`.`id` AS `frame_id`,
  `c`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `c`.`id` AS `map_id`,
  `c`.`name` AS `map_name`
FROM ((`frames` `f`
  JOIN `frames_2_controlnet_maps` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `controlnet_maps` `c` ON (`m`.`to_id` = `c`.`id`))
WHERE `f`.`map_run_id` = `c`.`active_map_run_id`
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_factions`
--
DROP TABLE IF EXISTS `v_gallery_factions`;
DROP VIEW IF EXISTS `v_gallery_factions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_factions` AS
SELECT
  `f`.`id` AS `frame_id`,
  `f`.`map_run_id` AS `map_run_id`,
  `c`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `c`.`id` AS `faction_id`,
  `c`.`name` AS `faction_name`,
  _utf8mb4'factions' COLLATE utf8mb4_general_ci AS `entity_type`
FROM (((`frames` `f`
  JOIN `frames_2_factions` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `factions` `c` ON (`m`.`to_id` = `c`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `s`.`order` ASC, `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_generatives`
--
DROP TABLE IF EXISTS `v_gallery_generatives`;
DROP VIEW IF EXISTS `v_gallery_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_generatives` AS
SELECT
  `f`.`id` AS `frame_id`,
  `g`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `g`.`id` AS `generative_id`,
  `g`.`name` AS `name`,
  `g`.`description` AS `description`
FROM (((`frames` `f`
  JOIN `frames_2_generatives` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `generatives` `g` ON (`m`.`to_id` = `g`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_locations`
--
DROP TABLE IF EXISTS `v_gallery_locations`;
DROP VIEW IF EXISTS `v_gallery_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_locations` AS
SELECT
  `f`.`id` AS `frame_id`,
  `l`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `l`.`id` AS `location_id`,
  `l`.`name` AS `location_name`,
  `l`.`type` AS `location_type`,
  _utf8mb4'locations' COLLATE utf8mb4_general_ci AS `entity_type`
FROM (((`frames` `f`
  JOIN `frames_2_locations` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `locations` `l` ON (`m`.`to_id` = `l`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_prompt_matrix_blueprints`
--
DROP TABLE IF EXISTS `v_gallery_prompt_matrix_blueprints`;
DROP VIEW IF EXISTS `v_gallery_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_prompt_matrix_blueprints` AS
SELECT
  `f`.`id` AS `frame_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `f`.`map_run_id` AS `map_run_id`,
  `b`.`id` AS `entity_id`,
  `b`.`name` AS `blueprint_name`,
  `b`.`entity_type` AS `blueprint_entity_type`,
  `b`.`entity_id` AS `blueprint_entity_id`,
  `b`.`description` AS `blueprint_description`,
  `b`.`matrix_id` AS `blueprint_matrix_id`,
  `b`.`matrix_additions_id` AS `blueprint_matrix_additions_id`,
  `b`.`active_map_run_id` AS `blueprint_active_map_run_id`,
  `b`.`state_id_active` AS `blueprint_state_id_active`,
  `b`.`regenerate_images` AS `blueprint_regenerate_images`,
  `b`.`img2img` AS `blueprint_img2img`,
  `b`.`cnmap` AS `blueprint_cnmap`
FROM (((`frames` `f`
  JOIN `frames_2_prompt_matrix_blueprints` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `prompt_matrix_blueprints` `b` ON (`m`.`to_id` = `b`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_scene_parts`
--
DROP TABLE IF EXISTS `v_gallery_scene_parts`;
DROP VIEW IF EXISTS `v_gallery_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_scene_parts` AS
SELECT
  `f`.`id` AS `frame_id`,
  `sp`.`scene_part_id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`style` AS `style`,
  `sp`.`scene_part_id` AS `scene_part_id`,
  `sp`.`name` AS `scene_part_name`,
  `sp`.`description` AS `scene_part_description`,
  `sp`.`characters` AS `characters`,
  `sp`.`animas` AS `animas`,
  `sp`.`artifacts` AS `artifacts`,
  `sp`.`backgrounds` AS `backgrounds`,
  `sp`.`prompt` AS `prompt`
FROM (((`frames` `f`
  JOIN `frames_2_scene_parts` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN (
    SELECT
      `sp`.`id` AS `scene_part_id`,
      `sp`.`name` AS `name`,
      `sp`.`description` AS `description`,
      `sp`.`regenerate_images` AS `regenerate_images`,
      `sp`.`active_map_run_id` AS `active_map_run_id`,
      substr(group_concat(DISTINCT concat(`c`.`name`, IF(`spc`.`role_in_part` IS NOT NULL, concat(' (', `spc`.`role_in_part`, ')'), '')) SEPARATOR ', '), 1, 500) AS `characters`,
      substr(group_concat(DISTINCT concat(`a`.`name`, ' (', `spa`.`action_type`, ')') SEPARATOR ', '), 1, 500) AS `animas`,
      substr(group_concat(DISTINCT `ar`.`name` SEPARATOR ', '), 1, 300) AS `artifacts`,
      substr(group_concat(DISTINCT concat(`b`.`name`, IF(`b`.`type` IS NOT NULL, concat(' (', `b`.`type`, ')'), '')) SEPARATOR ', '), 1, 300) AS `backgrounds`,
      concat_ws('. ',
        coalesce(`sp`.`name`, ''),
        coalesce(`sp`.`description`, ''),
        'Characters: ',
        substr(group_concat(DISTINCT concat(`c`.`name`, IF(`spc`.`role_in_part` IS NOT NULL, concat(' (', `spc`.`role_in_part`, ')'), '')) SEPARATOR ', '), 1, 500),
        '. Animas: ',
        substr(group_concat(DISTINCT concat(`a`.`name`, ' (', `spa`.`action_type`, ')') SEPARATOR ', '), 1, 500),
        '. Artifacts: ',
        substr(group_concat(DISTINCT `ar`.`name` SEPARATOR ', '), 1, 300),
        '. Backgrounds: ',
        substr(group_concat(DISTINCT concat(`b`.`name`, IF(`b`.`type` IS NOT NULL, concat(' (', `b`.`type`, ')'), '')) SEPARATOR ', '), 1, 300)
      ) AS `prompt`
    FROM ((((((((`scene_parts` `sp`
      LEFT JOIN `scene_part_characters` `spc` ON (`spc`.`scene_part_id` = `sp`.`id`))
      LEFT JOIN `characters` `c` ON (`c`.`id` = `spc`.`character_id`))
      LEFT JOIN `scene_part_animas` `spa` ON (`spa`.`scene_part_id` = `sp`.`id`))
      LEFT JOIN `animas` `a` ON (`a`.`id` = `spa`.`character_anima_id`))
      LEFT JOIN `scene_part_artifacts` `spa2` ON (`spa2`.`scene_part_id` = `sp`.`id`))
      LEFT JOIN `artifacts` `ar` ON (`ar`.`id` = `spa2`.`artifact_id`))
      LEFT JOIN `scene_part_backgrounds` `spb` ON (`spb`.`perspective_id` = `sp`.`id`))
      LEFT JOIN `backgrounds` `b` ON (`b`.`id` = `spb`.`background_id`))
    GROUP BY `sp`.`id`
  ) `sp` ON (`m`.`to_id` = `sp`.`scene_part_id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
  AND `f`.`map_run_id` = `sp`.`active_map_run_id`
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_sketches`
--
DROP TABLE IF EXISTS `v_gallery_sketches`;
DROP VIEW IF EXISTS `v_gallery_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_sketches` AS
SELECT
  `f`.`id` AS `frame_id`,
  `s`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `s`.`id` AS `sketch_id`,
  `s`.`name` AS `name`,
  `s`.`description` AS `description`,
  `s`.`mood` AS `mood`,
  `f`.`map_run_id` AS `map_run_id`
FROM (((`frames` `f`
  JOIN `frames_2_sketches` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `sketches` `s` ON (`m`.`to_id` = `s`.`id`))
  JOIN `styles` `st` ON (`f`.`style_id` = `st`.`id`))
WHERE `st`.`visible` = 1
ORDER BY `st`.`order` ASC, `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_spawns`
--
DROP TABLE IF EXISTS `v_gallery_spawns`;
DROP VIEW IF EXISTS `v_gallery_spawns`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns` AS
SELECT
  `f`.`id` AS `frame_id`,
  `s`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `s`.`id` AS `spawn_id`,
  `s`.`name` AS `name`,
  `s`.`description` AS `description`,
  COALESCE(`st`.`code`, `s`.`type`) AS `type`,
  `st`.`label` AS `type_label`,
  `st`.`id` AS `spawn_type_id`
FROM (((`frames` `f`
  JOIN `frames_2_spawns` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `spawns` `s` ON (`m`.`to_id` = `s`.`id`))
  LEFT JOIN `spawn_types` `st` ON (`s`.`spawn_type_id` = `st`.`id`))
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_spawns_location`
--
DROP TABLE IF EXISTS `v_gallery_spawns_location`;
DROP VIEW IF EXISTS `v_gallery_spawns_location`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_location` AS
SELECT
  `f`.`id` AS `frame_id`,
  `s`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `s`.`id` AS `spawn_id`,
  `s`.`name` AS `name`,
  `s`.`description` AS `description`,
  `st`.`code` AS `type`,
  `st`.`label` AS `type_label`
FROM (((`frames` `f`
  JOIN `frames_2_spawns` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `spawns` `s` ON (`m`.`to_id` = `s`.`id`))
  JOIN `spawn_types` `st` ON (`s`.`spawn_type_id` = `st`.`id`))
WHERE `st`.`code` = 'location'
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_spawns_prop`
--
DROP TABLE IF EXISTS `v_gallery_spawns_prop`;
DROP VIEW IF EXISTS `v_gallery_spawns_prop`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_prop` AS
SELECT
  `f`.`id` AS `frame_id`,
  `s`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `s`.`id` AS `spawn_id`,
  `s`.`name` AS `name`,
  `s`.`description` AS `description`,
  `st`.`code` AS `type`,
  `st`.`label` AS `type_label`
FROM (((`frames` `f`
  JOIN `frames_2_spawns` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `spawns` `s` ON (`m`.`to_id` = `s`.`id`))
  JOIN `spawn_types` `st` ON (`s`.`spawn_type_id` = `st`.`id`))
WHERE `st`.`code` = 'prop'
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_spawns_reference`
--
DROP TABLE IF EXISTS `v_gallery_spawns_reference`;
DROP VIEW IF EXISTS `v_gallery_spawns_reference`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_reference` AS
SELECT
  `f`.`id` AS `frame_id`,
  `s`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `s`.`id` AS `spawn_id`,
  `s`.`name` AS `name`,
  `s`.`description` AS `description`,
  `st`.`code` AS `type`,
  `st`.`label` AS `type_label`
FROM (((`frames` `f`
  JOIN `frames_2_spawns` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `spawns` `s` ON (`m`.`to_id` = `s`.`id`))
  JOIN `spawn_types` `st` ON (`s`.`spawn_type_id` = `st`.`id`))
WHERE `st`.`code` = 'reference'
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_spawns_texture`
--
DROP TABLE IF EXISTS `v_gallery_spawns_texture`;
DROP VIEW IF EXISTS `v_gallery_spawns_texture`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_spawns_texture` AS
SELECT
  `f`.`id` AS `frame_id`,
  `s`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `s`.`id` AS `spawn_id`,
  `s`.`name` AS `name`,
  `s`.`description` AS `description`,
  `st`.`code` AS `type`,
  `st`.`label` AS `type_label`
FROM (((`frames` `f`
  JOIN `frames_2_spawns` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `spawns` `s` ON (`m`.`to_id` = `s`.`id`))
  JOIN `spawn_types` `st` ON (`s`.`spawn_type_id` = `st`.`id`))
WHERE `st`.`code` = 'texture'
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_vehicles`
--
DROP TABLE IF EXISTS `v_gallery_vehicles`;
DROP VIEW IF EXISTS `v_gallery_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_vehicles` AS
SELECT
  `f`.`id` AS `frame_id`,
  `v`.`id` AS `entity_id`,
  `f`.`filename` AS `filename`,
  `f`.`prompt` AS `prompt`,
  `f`.`style` AS `style`,
  `v`.`id` AS `vehicle_id`,
  `v`.`name` AS `vehicle_name`,
  `v`.`type` AS `vehicle_type`,
  `v`.`status` AS `vehicle_status`
FROM (((`frames` `f`
  JOIN `frames_2_vehicles` `m` ON (`f`.`id` = `m`.`from_id`))
  JOIN `vehicles` `v` ON (`m`.`to_id` = `v`.`id`))
  JOIN `styles` `s` ON (`f`.`style_id` = `s`.`id`))
WHERE `s`.`visible` = 1
ORDER BY `f`.`created_at` DESC;

-- --------------------------------------------------------
--
-- Structure for view `v_gallery_wall_of_images`
--
DROP TABLE IF EXISTS `v_gallery_wall_of_images`;
DROP VIEW IF EXISTS `v_gallery_wall_of_images`;

CREATE ALGORITHM=UNDEFINED DEFINER=`adminer`@`localhost` SQL SECURITY DEFINER VIEW `v_gallery_wall_of_images` AS
SELECT
  _utf8mb4'animas' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_animas`.`frame_id` AS `frame_id`,
  `v_gallery_animas`.`entity_id` AS `entity_id`,
  `v_gallery_animas`.`filename` AS `filename`,
  `v_gallery_animas`.`prompt` AS `prompt`,
  `v_gallery_animas`.`anima_name` AS `entity_name`
FROM `v_gallery_animas`

UNION ALL

SELECT
  _utf8mb4'artifacts' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_artifacts`.`frame_id` AS `frame_id`,
  `v_gallery_artifacts`.`entity_id` AS `entity_id`,
  `v_gallery_artifacts`.`filename` AS `filename`,
  `v_gallery_artifacts`.`prompt` AS `prompt`,
  `v_gallery_artifacts`.`artifact_name` AS `entity_name`
FROM `v_gallery_artifacts`

UNION ALL

SELECT
  _utf8mb4'backgrounds' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_backgrounds`.`frame_id` AS `frame_id`,
  `v_gallery_backgrounds`.`entity_id` AS `entity_id`,
  `v_gallery_backgrounds`.`filename` AS `filename`,
  `v_gallery_backgrounds`.`prompt` AS `prompt`,
  `v_gallery_backgrounds`.`background_name` AS `entity_name`
FROM `v_gallery_backgrounds`

UNION ALL

SELECT
  _utf8mb4'characters' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_characters`.`frame_id` AS `frame_id`,
  `v_gallery_characters`.`entity_id` AS `entity_id`,
  `v_gallery_characters`.`filename` AS `filename`,
  `v_gallery_characters`.`prompt` AS `prompt`,
  `v_gallery_characters`.`character_name` AS `entity_name`
FROM `v_gallery_characters`

UNION ALL

SELECT
  _utf8mb4'composites' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_composites`.`frame_id` AS `frame_id`,
  `v_gallery_composites`.`entity_id` AS `entity_id`,
  `v_gallery_composites`.`filename` AS `filename`,
  `v_gallery_composites`.`prompt` AS `prompt`,
  `v_gallery_composites`.`composite_name` AS `entity_name`
FROM `v_gallery_composites`

UNION ALL

SELECT
  _utf8mb4'generatives' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_generatives`.`frame_id` AS `frame_id`,
  `v_gallery_generatives`.`entity_id` AS `entity_id`,
  `v_gallery_generatives`.`filename` AS `filename`,
  `v_gallery_generatives`.`prompt` AS `prompt`,
  `v_gallery_generatives`.`name` AS `entity_name`
FROM `v_gallery_generatives`

UNION ALL

SELECT
  _utf8mb4'locations' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_locations`.`frame_id` AS `frame_id`,
  `v_gallery_locations`.`entity_id` AS `entity_id`,
  `v_gallery_locations`.`filename` AS `filename`,
  `v_gallery_locations`.`prompt` AS `prompt`,
  `v_gallery_locations`.`location_name` AS `entity_name`
FROM `v_gallery_locations`

UNION ALL

SELECT
  _utf8mb4'sketches' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_sketches`.`frame_id` AS `frame_id`,
  `v_gallery_sketches`.`entity_id` AS `entity_id`,
  `v_gallery_sketches`.`filename` AS `filename`,
  `v_gallery_sketches`.`prompt` AS `prompt`,
  `v_gallery_sketches`.`name` AS `entity_name`
FROM `v_gallery_sketches`

UNION ALL

SELECT
  _utf8mb4'vehicles' COLLATE utf8mb4_general_ci AS `entity_type`,
  `v_gallery_vehicles`.`frame_id` AS `frame_id`,
  `v_gallery_vehicles`.`entity_id` AS `entity_id`,
  `v_gallery_vehicles`.`filename` AS `filename`,
  `v_gallery_vehicles`.`prompt` AS `prompt`,
  `v_gallery_vehicles`.`vehicle_name` AS `entity_name`
FROM `v_gallery_vehicles`;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_animas`
--
DROP TABLE IF EXISTS `v_map_runs_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_animas`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `a`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_animas` `m` on(`f`.`id` = `m`.`from_id`)) join `animas` `a` on(`a`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'animas' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_artifacts`
--
DROP TABLE IF EXISTS `v_map_runs_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_artifacts`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `ar`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_artifacts` `m` on(`f`.`id` = `m`.`from_id`)) join `artifacts` `ar` on(`ar`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'artifacts' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_audio_ambiences`
--
DROP TABLE IF EXISTS `v_map_runs_audio_ambiences`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_audio_ambiences`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `e`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_ambiences` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_ambiences` `e` on(`m`.`to_id` = `e`.`id`)) WHERE `mr`.`entity_type` = 'audio_ambiences' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_audio_cues`
--
DROP TABLE IF EXISTS `v_map_runs_audio_cues`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_audio_cues`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `e`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_cues` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_cues` `e` on(`m`.`to_id` = `e`.`id`)) WHERE `mr`.`entity_type` = 'audio_cues' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_audio_dialogue_lines`
--
DROP TABLE IF EXISTS `v_map_runs_audio_dialogue_lines`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_audio_dialogue_lines`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `adl`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_dialogue_lines` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_dialogue_lines` `adl` on(`adl`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'audio_dialogue_lines' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_audio_foleys`
--
DROP TABLE IF EXISTS `v_map_runs_audio_foleys`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_audio_foleys`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `e`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_foleys` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_foleys` `e` on(`m`.`to_id` = `e`.`id`)) WHERE `mr`.`entity_type` = 'audio_foleys' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_audio_fxsounds`
--
DROP TABLE IF EXISTS `v_map_runs_audio_fxsounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_audio_fxsounds`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `e`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_fxsounds` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_fxsounds` `e` on(`m`.`to_id` = `e`.`id`)) WHERE `mr`.`entity_type` = 'audio_fxsounds' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_audio_themes`
--
DROP TABLE IF EXISTS `v_map_runs_audio_themes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_audio_themes`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `e`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `audios` `a` on(`a`.`map_run_id` = `mr`.`id`)) join `audios_2_audio_themes` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_themes` `e` on(`m`.`to_id` = `e`.`id`)) WHERE `mr`.`entity_type` = 'audio_themes' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_backgrounds`
--
DROP TABLE IF EXISTS `v_map_runs_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_backgrounds`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `b`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_backgrounds` `m` on(`f`.`id` = `m`.`from_id`)) join `backgrounds` `b` on(`b`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'backgrounds' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_characters`
--
DROP TABLE IF EXISTS `v_map_runs_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_characters`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `c`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_characters` `m` on(`f`.`id` = `m`.`from_id`)) join `characters` `c` on(`c`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'characters' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_character_anima_poses`
--
DROP TABLE IF EXISTS `v_map_runs_character_anima_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_character_anima_poses`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `cap`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_anima_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_anima_poses` `cap` on(`cap`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'character_anima_poses' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_character_expressions`
--
DROP TABLE IF EXISTS `v_map_runs_character_expressions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_character_expressions`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `ce`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_expressions` `m` on(`f`.`id` = `m`.`from_id`)) join `character_expressions` `ce` on(`ce`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'character_expressions' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_character_poses`
--
DROP TABLE IF EXISTS `v_map_runs_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_character_poses`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `cp`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_character_poses` `m` on(`f`.`id` = `m`.`from_id`)) join `character_poses` `cp` on(`cp`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'character_poses' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_composites`
--
DROP TABLE IF EXISTS `v_map_runs_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_composites`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `c`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_composites` `m` on(`f`.`id` = `m`.`from_id`)) join `composites` `c` on(`c`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'composites' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_controlnet_maps`
--
DROP TABLE IF EXISTS `v_map_runs_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_controlnet_maps`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `c`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_controlnet_maps` `m` on(`f`.`id` = `m`.`from_id`)) join `controlnet_maps` `c` on(`c`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'controlnet_maps' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_factions`
--
DROP TABLE IF EXISTS `v_map_runs_factions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_factions`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `c`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_factions` `m` on(`f`.`id` = `m`.`from_id`)) join `factions` `c` on(`c`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'factions' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_generatives`
--
DROP TABLE IF EXISTS `v_map_runs_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_generatives`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `g`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_generatives` `m` on(`f`.`id` = `m`.`from_id`)) join `generatives` `g` on(`g`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'generatives' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_locations`
--
DROP TABLE IF EXISTS `v_map_runs_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_locations`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `l`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_locations` `m` on(`f`.`id` = `m`.`from_id`)) join `locations` `l` on(`l`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'locations' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_prompt_matrix_blueprints`
--
DROP TABLE IF EXISTS `v_map_runs_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_prompt_matrix_blueprints`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `b`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_prompt_matrix_blueprints` `m` on(`f`.`id` = `m`.`from_id`)) join `prompt_matrix_blueprints` `b` on(`b`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'prompt_matrix_blueprints' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_scene_parts`
--
DROP TABLE IF EXISTS `v_map_runs_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_scene_parts`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `f2sp`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `sp`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_scene_parts` `f2sp` on(`f2sp`.`from_id` = `f`.`id`)) join `scene_parts` `sp` on(`sp`.`id` = `f2sp`.`to_id`)) WHERE `mr`.`entity_type` = 'scene_parts' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_sketches`
--
DROP TABLE IF EXISTS `v_map_runs_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_sketches`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `s`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_sketches` `m` on(`f`.`id` = `m`.`from_id`)) join `sketches` `s` on(`s`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'sketches' ;

-- --------------------------------------------------------

--
-- Structure for view `v_map_runs_vehicles`
--
DROP TABLE IF EXISTS `v_map_runs_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_map_runs_vehicles`  AS SELECT DISTINCT `mr`.`id` AS `id`, `mr`.`created_at` AS `created_at`, `mr`.`note` AS `note`, `m`.`to_id` AS `entity_id`, CASE WHEN `mr`.`id` = `v`.`active_map_run_id` THEN 1 ELSE 0 END AS `is_active` FROM (((`map_runs` `mr` join `frames` `f` on(`f`.`map_run_id` = `mr`.`id`)) join `frames_2_vehicles` `m` on(`f`.`id` = `m`.`from_id`)) join `vehicles` `v` on(`v`.`id` = `m`.`to_id`)) WHERE `mr`.`entity_type` = 'vehicles' ;

-- --------------------------------------------------------

--
-- Structure for view `v_player_audio_ambiences`
--
DROP TABLE IF EXISTS `v_player_audio_ambiences`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_player_audio_ambiences`  AS SELECT `a`.`id` AS `audio_id`, `e`.`id` AS `entity_id`, `a`.`filename` AS `filename`, `a`.`name` AS `audio_name`, `a`.`rvc_model_name` AS `model`, `e`.`id` AS `audio_ambience_id`, `e`.`name` AS `name`, `e`.`description` AS `description`, `a`.`created_at` AS `created_at` FROM ((`audios` `a` join `audios_2_audio_ambiences` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_ambiences` `e` on(`m`.`to_id` = `e`.`id`)) ORDER BY `a`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_player_audio_cues`
--
DROP TABLE IF EXISTS `v_player_audio_cues`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_player_audio_cues`  AS SELECT `a`.`id` AS `audio_id`, `e`.`id` AS `entity_id`, `a`.`filename` AS `filename`, `a`.`name` AS `audio_name`, `a`.`rvc_model_name` AS `model`, `e`.`id` AS `audio_cue_id`, `e`.`name` AS `name`, `e`.`description` AS `description`, `a`.`created_at` AS `created_at` FROM ((`audios` `a` join `audios_2_audio_cues` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_cues` `e` on(`m`.`to_id` = `e`.`id`)) ORDER BY `a`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_player_audio_dialogue_lines`
--
DROP TABLE IF EXISTS `v_player_audio_dialogue_lines`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_player_audio_dialogue_lines`  AS SELECT `a`.`id` AS `audio_id`, `e`.`id` AS `entity_id`, `a`.`filename` AS `filename`, `a`.`name` AS `audio_name`, `a`.`rvc_model_name` AS `model`, `e`.`id` AS `audio_dialogue_line_id`, `e`.`name` AS `name`, `e`.`description` AS `description`, `a`.`created_at` AS `created_at` FROM ((`audios` `a` join `audios_2_audio_dialogue_lines` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_dialogue_lines` `e` on(`m`.`to_id` = `e`.`id`)) ORDER BY `a`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_player_audio_foleys`
--
DROP TABLE IF EXISTS `v_player_audio_foleys`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_player_audio_foleys`  AS SELECT `a`.`id` AS `audio_id`, `e`.`id` AS `entity_id`, `a`.`filename` AS `filename`, `a`.`name` AS `audio_name`, `a`.`rvc_model_name` AS `model`, `e`.`id` AS `audio_foley_id`, `e`.`name` AS `name`, `e`.`description` AS `description`, `a`.`created_at` AS `created_at` FROM ((`audios` `a` join `audios_2_audio_foleys` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_foleys` `e` on(`m`.`to_id` = `e`.`id`)) ORDER BY `a`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_player_audio_fxsounds`
--
DROP TABLE IF EXISTS `v_player_audio_fxsounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_player_audio_fxsounds`  AS SELECT `a`.`id` AS `audio_id`, `e`.`id` AS `entity_id`, `a`.`filename` AS `filename`, `a`.`name` AS `audio_name`, `a`.`rvc_model_name` AS `model`, `e`.`id` AS `audio_fxsound_id`, `e`.`name` AS `name`, `e`.`description` AS `description`, `a`.`created_at` AS `created_at` FROM ((`audios` `a` join `audios_2_audio_fxsounds` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_fxsounds` `e` on(`m`.`to_id` = `e`.`id`)) ORDER BY `a`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_player_audio_themes`
--
DROP TABLE IF EXISTS `v_player_audio_themes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_player_audio_themes`  AS SELECT `a`.`id` AS `audio_id`, `e`.`id` AS `entity_id`, `a`.`filename` AS `filename`, `a`.`name` AS `audio_name`, `a`.`rvc_model_name` AS `model`, `e`.`id` AS `audio_theme_id`, `e`.`name` AS `name`, `e`.`description` AS `description`, `a`.`created_at` AS `created_at` FROM ((`audios` `a` join `audios_2_audio_themes` `m` on(`a`.`id` = `m`.`from_id`)) join `audio_themes` `e` on(`m`.`to_id` = `e`.`id`)) ORDER BY `a`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_player_daw_projects`
--
DROP TABLE IF EXISTS `v_player_daw_projects`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_player_daw_projects`  AS SELECT `a`.`id` AS `audio_id`, `e`.`id` AS `entity_id`, `a`.`filename` AS `filename`, `a`.`name` AS `audio_name`, `a`.`rvc_model_name` AS `model`, `e`.`id` AS `daw_project_id`, `e`.`name` AS `name`, `e`.`folder_name` AS `description`, `a`.`created_at` AS `created_at` FROM ((`audios` `a` join `audios_2_daw_projects` `m` on(`a`.`id` = `m`.`from_id`)) join `daw_projects` `e` on(`m`.`to_id` = `e`.`id`)) ORDER BY `a`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_player_editorial_shots`
--
DROP TABLE IF EXISTS `v_player_editorial_shots`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_player_editorial_shots`  AS SELECT `a`.`id` AS `audio_id`, `e`.`id` AS `entity_id`, `a`.`filename` AS `filename`, `a`.`name` AS `audio_name`, `a`.`rvc_model_name` AS `model`, `e`.`id` AS `editorial_shot_id`, `e`.`name` AS `name`, `e`.`description` AS `description`, `a`.`created_at` AS `created_at` FROM ((`audios` `a` join `audios_2_editorial_shots` `m` on(`a`.`id` = `m`.`from_id`)) join `editorial_shots` `e` on(`m`.`to_id` = `e`.`id`)) ORDER BY `a`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_animas`
--
DROP TABLE IF EXISTS `v_prompts_animas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_animas`  AS SELECT `a`.`id` AS `id`, `a`.`regenerate_images` AS `regenerate_images`, coalesce(`a`.`description`,'') AS `prompt`, coalesce(`a`.`prompt_negative`,'') AS `prompt_negative`, `a`.`seed` AS `seed` FROM `animas` AS `a` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_artifacts`
--
DROP TABLE IF EXISTS `v_prompts_artifacts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_artifacts`  AS SELECT `ar`.`id` AS `id`, `ar`.`regenerate_images` AS `regenerate_images`, coalesce(`ar`.`description`,'') AS `prompt`, coalesce(`ar`.`prompt_negative`,'') AS `prompt_negative`, `ar`.`seed` AS `seed` FROM `artifacts` AS `ar` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_backgrounds`
--
DROP TABLE IF EXISTS `v_prompts_backgrounds`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_backgrounds`  AS SELECT `b`.`id` AS `id`, `b`.`regenerate_images` AS `regenerate_images`, coalesce(`b`.`description`,'') AS `prompt`, coalesce(`b`.`prompt_negative`,'') AS `prompt_negative`, `b`.`seed` AS `seed` FROM `backgrounds` AS `b` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_characters`
--
DROP TABLE IF EXISTS `v_prompts_characters`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_characters`  AS SELECT `c`.`id` AS `id`, `c`.`regenerate_images` AS `regenerate_images`, coalesce(`c`.`description`,'') AS `prompt`, coalesce(`c`.`prompt_negative`,'') AS `prompt_negative`, `c`.`seed` AS `seed` FROM `characters` AS `c` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_character_anima_poses`
--
DROP TABLE IF EXISTS `v_prompts_character_anima_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_character_anima_poses`  AS SELECT `cap`.`id` AS `id`, `cap`.`regenerate_images` AS `regenerate_images`, `cap`.`description` AS `prompt`, coalesce(`cap`.`prompt_negative`,'') AS `prompt_negative`, `cap`.`seed` AS `seed` FROM `character_anima_poses` AS `cap` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_character_expressions`
--
DROP TABLE IF EXISTS `v_prompts_character_expressions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_character_expressions`  AS SELECT `ce`.`id` AS `id`, `ce`.`regenerate_images` AS `regenerate_images`, `ce`.`description` AS `prompt`, coalesce(`ce`.`prompt_negative`,'') AS `prompt_negative`, `ce`.`seed` AS `seed` FROM `character_expressions` AS `ce` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_character_poses`
--
DROP TABLE IF EXISTS `v_prompts_character_poses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_character_poses`  AS SELECT `cp`.`id` AS `id`, `cp`.`regenerate_images` AS `regenerate_images`, `cp`.`description` AS `prompt`, coalesce(`cp`.`prompt_negative`,'') AS `prompt_negative`, `cp`.`seed` AS `seed` FROM `character_poses` AS `cp` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_composites`
--
DROP TABLE IF EXISTS `v_prompts_composites`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_composites`  AS SELECT `c`.`id` AS `id`, `c`.`regenerate_images` AS `regenerate_images`, coalesce(`c`.`description`,'') AS `prompt`, coalesce(`c`.`prompt_negative`,'') AS `prompt_negative`, `c`.`seed` AS `seed` FROM `composites` AS `c` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_controlnet_maps`
--
DROP TABLE IF EXISTS `v_prompts_controlnet_maps`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_controlnet_maps`  AS SELECT `c`.`id` AS `id`, `c`.`regenerate_images` AS `regenerate_images`, concat_ws(', ',`c`.`name`,coalesce(`c`.`description`,'')) AS `prompt`, coalesce(`c`.`prompt_negative`,'') AS `prompt_negative`, `c`.`seed` AS `seed` FROM `controlnet_maps` AS `c` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_factions`
--
DROP TABLE IF EXISTS `v_prompts_factions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_factions`  AS SELECT `c`.`id` AS `id`, `c`.`regenerate_images` AS `regenerate_images`, coalesce(`c`.`description`,'') AS `prompt`, coalesce(`c`.`prompt_negative`,'') AS `prompt_negative`, `c`.`seed` AS `seed` FROM `factions` AS `c` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_generatives`
--
DROP TABLE IF EXISTS `v_prompts_generatives`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_generatives`  AS SELECT `g`.`id` AS `id`, `g`.`regenerate_images` AS `regenerate_images`, concat_ws(', ',`g`.`name`,coalesce(`g`.`description`,'')) AS `prompt`, coalesce(`g`.`prompt_negative`,'') AS `prompt_negative`, `g`.`seed` AS `seed` FROM `generatives` AS `g` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_locations`
--
DROP TABLE IF EXISTS `v_prompts_locations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_locations`  AS SELECT `l`.`id` AS `id`, `l`.`regenerate_images` AS `regenerate_images`, coalesce(`l`.`description`,'') AS `prompt`, coalesce(`l`.`prompt_negative`,'') AS `prompt_negative`, `l`.`seed` AS `seed` FROM `locations` AS `l` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_prompt_matrix_blueprints`
--
DROP TABLE IF EXISTS `v_prompts_prompt_matrix_blueprints`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_prompt_matrix_blueprints`  AS SELECT `prompt_matrix_blueprints`.`id` AS `id`, `prompt_matrix_blueprints`.`regenerate_images` AS `regenerate_images`, coalesce(`prompt_matrix_blueprints`.`description`,'') AS `prompt`, coalesce(`prompt_matrix_blueprints`.`prompt_negative`,'') AS `prompt_negative`, `prompt_matrix_blueprints`.`seed` AS `seed` FROM `prompt_matrix_blueprints` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_scene_parts`
--
DROP TABLE IF EXISTS `v_prompts_scene_parts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_scene_parts`  AS SELECT `sp`.`id` AS `scene_part_id`, `sp`.`id` AS `id`, `sp`.`scene_id` AS `scene_id`, `sp`.`name` AS `name`, `sp`.`description` AS `description`, substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500) AS `characters`, substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500) AS `animas`, substr(group_concat(distinct `ar`.`name` separator ', '),1,300) AS `artifacts`, substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300) AS `backgrounds`, concat_ws('. ',coalesce(`sp`.`name`,''),coalesce(`sp`.`description`,''),'Characters: ',substr(group_concat(distinct concat(`c`.`name`,if(`spc`.`role_in_part` is not null,concat(' (',`spc`.`role_in_part`,')'),'')) separator ', '),1,500),'. Animas: ',substr(group_concat(distinct concat(`a`.`name`,' (',`spa`.`action_type`,')') separator ', '),1,500),'. Artifacts: ',substr(group_concat(distinct `ar`.`name` separator ', '),1,300),'. Backgrounds: ',substr(group_concat(distinct concat(`b`.`name`,if(`b`.`type` is not null,concat(' (',`b`.`type`,')'),'')) separator ', '),1,300)) AS `prompt`, `sp`.`regenerate_images` AS `regenerate_images`, coalesce(`sp`.`prompt_negative`,'') AS `prompt_negative`, `sp`.`seed` AS `seed` FROM ((((((((`scene_parts` `sp` left join `scene_part_characters` `spc` on(`spc`.`scene_part_id` = `sp`.`id`)) left join `characters` `c` on(`c`.`id` = `spc`.`character_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) left join `scene_part_artifacts` `spa2` on(`spa2`.`scene_part_id` = `sp`.`id`)) left join `artifacts` `ar` on(`ar`.`id` = `spa2`.`artifact_id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `sp`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) GROUP BY `sp`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_sketches`
--
DROP TABLE IF EXISTS `v_prompts_sketches`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_sketches`  AS SELECT `s`.`id` AS `id`, `s`.`regenerate_images` AS `regenerate_images`, coalesce(`s`.`description`,'') AS `prompt`, coalesce(`s`.`prompt_negative`,'') AS `prompt_negative`, `s`.`seed` AS `seed` FROM `sketches` AS `s` ;

-- --------------------------------------------------------

--
-- Structure for view `v_prompts_vehicles`
--
DROP TABLE IF EXISTS `v_prompts_vehicles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prompts_vehicles`  AS SELECT `v`.`id` AS `id`, `v`.`regenerate_images` AS `regenerate_images`, coalesce(`v`.`description`,'') AS `prompt`, coalesce(`v`.`prompt_negative`,'') AS `prompt_negative`, `v`.`seed` AS `seed` FROM `vehicles` AS `v` ;

-- --------------------------------------------------------

--
-- Structure for view `v_scenes_under_review`
--
DROP TABLE IF EXISTS `v_scenes_under_review`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_scenes_under_review`  AS SELECT `s`.`id` AS `scene_id`, `s`.`title` AS `scene_title`, `sp`.`id` AS `scene_part_id`, `ps`.`stage` AS `stage`, `ps`.`assigned_to` AS `assigned_to`, `ps`.`updated_at` AS `updated_at` FROM ((`scenes` `s` join `scene_parts` `sp` on(`sp`.`scene_id` = `s`.`id`)) join `production_status` `ps` on(`ps`.`scene_part_id` = `sp`.`id`)) WHERE `ps`.`stage` = 'review' ;

-- --------------------------------------------------------

--
-- Structure for view `v_scene_part_full`
--
DROP TABLE IF EXISTS `v_scene_part_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_scene_part_full`  AS SELECT `sp`.`id` AS `scene_part_id`, `sp`.`name` AS `scene_part_name`, `sp`.`description` AS `scene_part_description`, `p`.`angle` AS `perspective_angle`, `p`.`description` AS `perspective_notes`, `b`.`name` AS `background_name`, `b`.`description` AS `background_description`, group_concat(distinct `a`.`name` separator ', ') AS `animas_in_scene`, group_concat(distinct concat(`a`.`name`,': ',`a`.`traits`,'; ',`a`.`abilities`) separator ' | ') AS `animas_details` FROM (((((`scene_parts` `sp` join `perspectives` `p` on(`p`.`scene_part_id` = `sp`.`id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `p`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) GROUP BY `sp`.`id`, `p`.`id`, `b`.`id` ORDER BY `sp`.`sequence` ASC, `p`.`id` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_styles_helper`
--
DROP TABLE IF EXISTS `v_styles_helper`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_styles_helper`  AS SELECT `s`.`id` AS `id`, 0 AS `regenerate_images`, concat('(',coalesce(`s`.`description`,''),')','(',(select `prompt_globals`.`description` from `prompt_globals` where `prompt_globals`.`id` = 1),')') AS `prompt` FROM `styles` AS `s` WHERE `s`.`active` = 1 ORDER BY `s`.`order` ASC ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `backgrounds`
--
ALTER TABLE `backgrounds`
  ADD CONSTRAINT `fk_backgrounds_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cinemagics_2_sequences`
--
ALTER TABLE `cinemagics_2_sequences`
  ADD CONSTRAINT `fk_c2s_cinemagic` FOREIGN KEY (`cinemagic_id`) REFERENCES `cinemagics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_c2s_sequence` FOREIGN KEY (`sequence_id`) REFERENCES `narrative_sequences` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clipboard_visibility`
--
ALTER TABLE `clipboard_visibility`
  ADD CONSTRAINT `fk_cv_item` FOREIGN KEY (`clipboard_item_id`) REFERENCES `clipboard_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `content_elements`
--
ALTER TABLE `content_elements`
  ADD CONSTRAINT `content_elements_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`);

--
-- Constraints for table `daw_project_files`
--
ALTER TABLE `daw_project_files`
  ADD CONSTRAINT `fk_daw_project` FOREIGN KEY (`project_id`) REFERENCES `daw_projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daw_shot_saves`
--
ALTER TABLE `daw_shot_saves`
  ADD CONSTRAINT `fk_daw_shot_save_shot` FOREIGN KEY (`shot_id`) REFERENCES `editorial_shots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dict_lemma_2_dictionary`
--
ALTER TABLE `dict_lemma_2_dictionary`
  ADD CONSTRAINT `fk_dict_id` FOREIGN KEY (`dictionary_id`) REFERENCES `dict_dictionaries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lemma_id` FOREIGN KEY (`lemma_id`) REFERENCES `dict_lemmas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dict_source_files`
--
ALTER TABLE `dict_source_files`
  ADD CONSTRAINT `fk_source_dict_id` FOREIGN KEY (`dictionary_id`) REFERENCES `dict_dictionaries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `export_flags`
--
ALTER TABLE `export_flags`
  ADD CONSTRAINT `fk_export_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback_notes`
--
ALTER TABLE `feedback_notes`
  ADD CONSTRAINT `fk_feedback_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `frames_2_artifacts`
--
ALTER TABLE `frames_2_artifacts`
  ADD CONSTRAINT `fk_frames_artifacts_artifact` FOREIGN KEY (`to_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `frames_2_generatives`
--
ALTER TABLE `frames_2_generatives`
  ADD CONSTRAINT `fk_frames_generatives_generative` FOREIGN KEY (`to_id`) REFERENCES `generatives` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `frames_2_scene_parts`
--
ALTER TABLE `frames_2_scene_parts`
  ADD CONSTRAINT `frames_2_scene_parts_ibfk_2` FOREIGN KEY (`to_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `frames_2_sketches`
--
ALTER TABLE `frames_2_sketches`
  ADD CONSTRAINT `fk_frames_sketches_sketch` FOREIGN KEY (`to_id`) REFERENCES `sketches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `frames_2_vehicles`
--
ALTER TABLE `frames_2_vehicles`
  ADD CONSTRAINT `fk_frames_2_vehicles_to` FOREIGN KEY (`to_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `generator_config_to_display_area`
--
ALTER TABLE `generator_config_to_display_area`
  ADD CONSTRAINT `fk_display_area` FOREIGN KEY (`display_area_id`) REFERENCES `generator_config_display_area` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_generator_config` FOREIGN KEY (`generator_config_id`) REFERENCES `generator_config` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `playlist_videos`
--
ALTER TABLE `playlist_videos`
  ADD CONSTRAINT `fk_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `video_playlists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plush_collections_2_stories`
--
ALTER TABLE `plush_collections_2_stories`
  ADD CONSTRAINT `fk_plush_c2s_col` FOREIGN KEY (`collection_id`) REFERENCES `plush_collections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_plush_c2s_story` FOREIGN KEY (`story_id`) REFERENCES `plush_stories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plush_highlight_blocks`
--
ALTER TABLE `plush_highlight_blocks`
  ADD CONSTRAINT `fk_plush_blocks_scene` FOREIGN KEY (`scene_id`) REFERENCES `plush_scenes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plush_highlight_block_entities`
--
ALTER TABLE `plush_highlight_block_entities`
  ADD CONSTRAINT `fk_phbe_block` FOREIGN KEY (`block_id`) REFERENCES `plush_highlight_blocks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plush_highlight_groups`
--
ALTER TABLE `plush_highlight_groups`
  ADD CONSTRAINT `fk_plush_groups_scene` FOREIGN KEY (`scene_id`) REFERENCES `plush_scenes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plush_scenes`
--
ALTER TABLE `plush_scenes`
  ADD CONSTRAINT `fk_plush_scenes_story` FOREIGN KEY (`story_id`) REFERENCES `plush_stories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plush_scene_dates`
--
ALTER TABLE `plush_scene_dates`
  ADD CONSTRAINT `fk_pscd_scene` FOREIGN KEY (`scene_id`) REFERENCES `plush_scenes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plush_story_dates`
--
ALTER TABLE `plush_story_dates`
  ADD CONSTRAINT `fk_psd_story` FOREIGN KEY (`story_id`) REFERENCES `plush_stories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `production_status`
--
ALTER TABLE `production_status`
  ADD CONSTRAINT `fk_prodstatus_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scenes`
--
ALTER TABLE `scenes`
  ADD CONSTRAINT `fk_scene_arc` FOREIGN KEY (`arc_id`) REFERENCES `story_arcs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `scene_parts`
--
ALTER TABLE `scene_parts`
  ADD CONSTRAINT `fk_scene_parts_scene` FOREIGN KEY (`scene_id`) REFERENCES `scenes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scene_part_animas`
--
ALTER TABLE `scene_part_animas`
  ADD CONSTRAINT `fk_span_character_anima` FOREIGN KEY (`character_anima_id`) REFERENCES `animas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_span_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scene_part_artifacts`
--
ALTER TABLE `scene_part_artifacts`
  ADD CONSTRAINT `fk_spa_artifact` FOREIGN KEY (`artifact_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_spa_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scene_part_backgrounds`
--
ALTER TABLE `scene_part_backgrounds`
  ADD CONSTRAINT `fk_spb_background` FOREIGN KEY (`background_id`) REFERENCES `backgrounds` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_spb_perspective` FOREIGN KEY (`perspective_id`) REFERENCES `perspectives` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scene_part_tags`
--
ALTER TABLE `scene_part_tags`
  ADD CONSTRAINT `fk_spt_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_spt_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scene_part_versions`
--
ALTER TABLE `scene_part_versions`
  ADD CONSTRAINT `fk_spv_scene_part` FOREIGN KEY (`scene_part_id`) REFERENCES `scene_parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sketchmig_frame`
--
ALTER TABLE `sketchmig_frame`
  ADD CONSTRAINT `fk_smig_frame_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `sketchmig_bundle` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sketchmig_sketch`
--
ALTER TABLE `sketchmig_sketch`
  ADD CONSTRAINT `fk_smig_sketch_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `sketchmig_bundle` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sketch_sequence_analysis`
--
ALTER TABLE `sketch_sequence_analysis`
  ADD CONSTRAINT `fk_ssa_sketch` FOREIGN KEY (`sketch_id`) REFERENCES `sketches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spawns`
--
ALTER TABLE `spawns`
  ADD CONSTRAINT `fk_spawns_spawn_type` FOREIGN KEY (`spawn_type_id`) REFERENCES `spawn_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `style_profile_axes`
--
ALTER TABLE `style_profile_axes`
  ADD CONSTRAINT `fk_spa_axis` FOREIGN KEY (`axis_id`) REFERENCES `design_axes` (`id`),
  ADD CONSTRAINT `fk_spa_profile` FOREIGN KEY (`profile_id`) REFERENCES `style_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_execution_stats`
--
ALTER TABLE `task_execution_stats`
  ADD CONSTRAINT `task_execution_stats_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_locks`
--
ALTER TABLE `task_locks`
  ADD CONSTRAINT `task_locks_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_locks_ibfk_2` FOREIGN KEY (`run_id`) REFERENCES `task_runs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `task_runs`
--
ALTER TABLE `task_runs`
  ADD CONSTRAINT `task_runs_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_runs_ibfk_2` FOREIGN KEY (`lock_id`) REFERENCES `task_locks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `task_wrappers`
--
ALTER TABLE `task_wrappers`
  ADD CONSTRAINT `task_wrappers_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `scheduled_tasks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- ============================================================
-- Incremental Update Rollout SQL
-- Source DB : starlight_guardians_nu
-- Generated : 2026-06-25 13:00:58
-- Apply this patch to your baseline rollout SQL in your repo.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- [CREATE_TABLE] table/view: bang_arrangements
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
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! — saved Konva scene arrangements (versioned)';

-- [CREATE_TABLE] table/view: bang_canvases
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
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! comic panel composer — canvas metadata per composite';

-- [CREATE_TABLE] table/view: bang_elements
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
) ENGINE=InnoDB AUTO_INCREMENT=802 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! — flat element index (mirrors scene_json for lookups)';

-- [CREATE_TABLE] table/view: bang_exports
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! — PNG export job tracker';

-- [CREATE_TABLE] table/view: bang_fonts
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BANG! — registered fonts for the lettering system';

-- [CREATE_TABLE] table/view: beap_beats
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
  KEY `idx_beap_beats_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Individual beats/steps within a BEAP session';

-- [CREATE_TABLE] table/view: beap_sessions
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='BEAP workflow sessions — one per scene-to-panel run';

-- [CREATE_TABLE] table/view: cinemagic_series_seasons
DROP TABLE IF EXISTS `cinemagic_series_seasons`;
CREATE TABLE `cinemagic_series_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `series_id` int(11) NOT NULL COMMENT 'FK → cinemagic_series.id',
  `title` varchar(255) NOT NULL DEFAULT 'Season 1',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_css_series` (`series_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Optional season grouping containers for a cinemagic_series';

-- [CREATE_TABLE] table/view: fuki_texts
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
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Fuki — Multilingual text overlays for narrative sequences';

-- [CREATE_TABLE] table/view: kg_edge_offline_state
DROP TABLE IF EXISTS `kg_edge_offline_state`;
CREATE TABLE `kg_edge_offline_state` (
  `id` tinyint(4) NOT NULL DEFAULT 1,
  `is_offline` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: kg_edge_proposals
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
) ENGINE=InnoDB AUTO_INCREMENT=328 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: kg_edge_runs
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
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: kg_edge_run_ai_edges
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
  KEY `idx_target_node` (`target_node_id`)
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: kg_edge_run_candidates
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
  KEY `idx_node_id` (`node_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2365 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: kg_edge_run_logs
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
  KEY `idx_step_key` (`step_key`)
) ENGINE=InnoDB AUTO_INCREMENT=341 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: kg_node_coordinates
DROP TABLE IF EXISTS `kg_node_coordinates`;
CREATE TABLE `kg_node_coordinates` (
  `node_id` int(11) NOT NULL,
  `x` double NOT NULL,
  `y` double NOT NULL,
  PRIMARY KEY (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: mail_hub_events
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

-- [CREATE_TABLE] table/view: mail_hub_lists
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — subscriber lists / audiences';

-- [CREATE_TABLE] table/view: mail_hub_newsletters
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

-- [CREATE_TABLE] table/view: mail_hub_providers
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='SAGE Mail Hub — mail delivery providers';

-- [CREATE_TABLE] table/view: mail_hub_queue
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

-- [CREATE_TABLE] table/view: mail_hub_queue_archive
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

-- [CREATE_TABLE] table/view: mail_hub_templates
DROP TABLE IF EXISTS `mail_hub_templates`;
CREATE TABLE `mail_hub_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `body_html` longtext DEFAULT NULL,
  `body_text` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: mail_hub_unsubscribe_tokens
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

-- [CREATE_TABLE] table/view: narrative_sequence_categories
DROP TABLE IF EXISTS `narrative_sequence_categories`;
CREATE TABLE `narrative_sequence_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: popkorn_pots
DROP TABLE IF EXISTS `popkorn_pots`;
CREATE TABLE `popkorn_pots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: popkorn_pot_videos
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- [CREATE_TABLE] table/view: pytoon_canvas_sizes
DROP TABLE IF EXISTS `pytoon_canvas_sizes`;
CREATE TABLE `pytoon_canvas_sizes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL DEFAULT '',
  `width` int(11) NOT NULL DEFAULT 1080,
  `height` int(11) NOT NULL DEFAULT 1920,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- [CREATE_TABLE] table/view: pytoon_jobs
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- [CREATE_TABLE] table/view: sitemap_imports
DROP TABLE IF EXISTS `sitemap_imports`;
CREATE TABLE `sitemap_imports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `system_name` varchar(255) NOT NULL,
  `urls_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sitemap_sys` (`system_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- [ADD_COLUMN] table/view: cinemagics_2_sequences
ALTER TABLE `cinemagics_2_sequences` ADD COLUMN IF NOT EXISTS `cover_image_url` varchar(512) NULL DEFAULT NULL;

-- [ADD_COLUMN] table/view: cinemagics_2_sequences
ALTER TABLE `cinemagics_2_sequences` ADD COLUMN IF NOT EXISTS `seo_keywords` text NULL DEFAULT NULL;

-- [ADD_COLUMN] table/view: cinemagics_2_sequences
ALTER TABLE `cinemagics_2_sequences` ADD COLUMN IF NOT EXISTS `seo_description` text NULL DEFAULT NULL;

-- [ADD_COLUMN] table/view: cinemagics_2_sequences
ALTER TABLE `cinemagics_2_sequences` ADD COLUMN IF NOT EXISTS `social_links` longtext NULL DEFAULT NULL;

-- [ADD_COLUMN] table/view: cinemagic_series
ALTER TABLE `cinemagic_series` ADD COLUMN IF NOT EXISTS `landing_page_script` varchar(255) NULL DEFAULT NULL;

-- [ADD_COLUMN] table/view: cinemagic_series
ALTER TABLE `cinemagic_series` ADD COLUMN IF NOT EXISTS `has_seasons` tinyint(1) NOT NULL DEFAULT 0;

-- [ADD_COLUMN] table/view: cinemagic_series
ALTER TABLE `cinemagic_series` ADD COLUMN IF NOT EXISTS `pdf_full_upright` tinyint(1) NOT NULL DEFAULT 0;

-- [ADD_COLUMN] table/view: cinemagic_series
ALTER TABLE `cinemagic_series` ADD COLUMN IF NOT EXISTS `pdf_disable_texts` tinyint(1) NOT NULL DEFAULT 0;

-- [ADD_COLUMN] table/view: cinemagic_series
ALTER TABLE `cinemagic_series` ADD COLUMN IF NOT EXISTS `pdf_disable_fuki` tinyint(1) NOT NULL DEFAULT 0;

-- [ADD_COLUMN] table/view: cinemagic_series_2_cinemagics
ALTER TABLE `cinemagic_series_2_cinemagics` ADD COLUMN IF NOT EXISTS `season_id` int(11) NULL DEFAULT NULL;

-- [ADD_COLUMN] table/view: cinemagic_series_2_cinemagics
ALTER TABLE `cinemagic_series_2_cinemagics` ADD COLUMN IF NOT EXISTS `cover_image_url` varchar(512) NULL DEFAULT NULL;

-- [ADD_COLUMN] table/view: narrative_sequences
ALTER TABLE `narrative_sequences` ADD COLUMN IF NOT EXISTS `category_id` int(11) NULL DEFAULT NULL;

-- [ADD_FOREIGN_KEY] table/view: beap_beats
ALTER TABLE `beap_beats` ADD CONSTRAINT IF NOT EXISTS `fk_beap_beats_session` FOREIGN KEY (`session_id`) REFERENCES `beap_sessions` (`id`) ON DELETE CASCADE;

-- [ADD_FOREIGN_KEY] table/view: kg_edge_run_ai_edges
ALTER TABLE `kg_edge_run_ai_edges` ADD CONSTRAINT IF NOT EXISTS `fk_kg_edge_run_ai_edges_run` FOREIGN KEY (`run_id`) REFERENCES `kg_edge_runs` (`id`) ON DELETE CASCADE;

-- [ADD_FOREIGN_KEY] table/view: kg_edge_run_candidates
ALTER TABLE `kg_edge_run_candidates` ADD CONSTRAINT IF NOT EXISTS `fk_kg_edge_run_candidates_run` FOREIGN KEY (`run_id`) REFERENCES `kg_edge_runs` (`id`) ON DELETE CASCADE;

-- [ADD_FOREIGN_KEY] table/view: kg_edge_run_logs
ALTER TABLE `kg_edge_run_logs` ADD CONSTRAINT IF NOT EXISTS `fk_kg_edge_run_logs_run` FOREIGN KEY (`run_id`) REFERENCES `kg_edge_runs` (`id`) ON DELETE CASCADE;

-- [ADD_FOREIGN_KEY] table/view: kg_node_coordinates
ALTER TABLE `kg_node_coordinates` ADD CONSTRAINT IF NOT EXISTS `fk_kg_node_coord` FOREIGN KEY (`node_id`) REFERENCES `kg_nodes` (`id`) ON DELETE CASCADE;

-- [REPLACE_VIEW] table/view: v_scene_part_full
CREATE OR REPLACE VIEW `v_scene_part_full` AS select `sp`.`id` AS `scene_part_id`,`sp`.`name` AS `scene_part_name`,`sp`.`description` AS `scene_part_description`,`p`.`angle` AS `perspective_angle`,`p`.`description` AS `perspective_notes`,`b`.`name` AS `background_name`,`b`.`description` AS `background_description`,group_concat(distinct `a`.`name` separator ', ') AS `animas_in_scene`,group_concat(distinct concat(`a`.`name`,': ',`a`.`traits`,'; ',`a`.`abilities`) separator ' | ') AS `animas_details` from (((((`scene_parts` `sp` join `perspectives` `p` on(`p`.`scene_part_id` = `sp`.`id`)) left join `scene_part_backgrounds` `spb` on(`spb`.`perspective_id` = `p`.`id`)) left join `backgrounds` `b` on(`b`.`id` = `spb`.`background_id`)) left join `scene_part_animas` `spa` on(`spa`.`scene_part_id` = `sp`.`id`)) left join `animas` `a` on(`a`.`id` = `spa`.`character_anima_id`)) group by `sp`.`id`,`p`.`id`,`b`.`id` order by `sp`.`sequence`,`p`.`id`;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
