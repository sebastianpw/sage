-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Erstellungszeit: 29. Nov 2025 um 02:32
-- Server-Version: 12.0.2-MariaDB
-- PHP-Version: 8.4.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `sg_sys`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `code_analysis_log`
--

CREATE TABLE `code_analysis_log` (
  `id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `chunk_index` int(11) DEFAULT NULL,
  `tokens_estimate` int(11) DEFAULT NULL,
  `response_length` int(11) DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `raw_response` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `code_classes`
--

CREATE TABLE `code_classes` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `class_name` varchar(255) DEFAULT NULL,
  `extends_class` varchar(255) DEFAULT NULL,
  `interfaces` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`interfaces`)),
  `methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`methods`)),
  `summary` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `code_files`
--

CREATE TABLE `code_files` (
  `id` int(11) NOT NULL,
  `path` varchar(1024) NOT NULL,
  `file_hash` char(40) NOT NULL,
  `last_analyzed_at` datetime DEFAULT NULL,
  `chunk_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `gpt_conversations`
--

CREATE TABLE `gpt_conversations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `model` varchar(128) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `message_count` int(10) UNSIGNED DEFAULT 0,
  `flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `gpt_messages`
--

CREATE TABLE `gpt_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `conversation_external_id` varchar(128) NOT NULL,
  `message_index` int(11) NOT NULL DEFAULT 0,
  `role` varchar(32) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `content_text` mediumtext DEFAULT NULL,
  `raw_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `model` varchar(128) DEFAULT NULL,
  `tokens` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `pages_dashboard`
--

CREATE TABLE `pages_dashboard` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `href` varchar(2048) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `recipes`
--

CREATE TABLE `recipes` (
  `id` int(11) NOT NULL,
  `recipe_group_id` int(11) NOT NULL COMMENT 'FK to recipe_groups.id',
  `output_filename` varchar(255) NOT NULL COMMENT 'Relative path of the output file, e.g., temp/dc.txt',
  `rerun_command` text NOT NULL COMMENT 'The full CLI command to reproduce this exact recipe.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `recipe_groups`
--

CREATE TABLE `recipe_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'The conceptual name of the recipe.',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `recipe_ingredients`
--

CREATE TABLE `recipe_ingredients` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `snapshot_id` int(11) NOT NULL COMMENT 'FK to recipe_ingredient_snapshots.id',
  `source_filename` varchar(255) NOT NULL COMMENT 'The original relative path or db: handle.',
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `recipe_ingredient_snapshots`
--

CREATE TABLE `recipe_ingredient_snapshots` (
  `id` int(11) NOT NULL,
  `content_hash` char(64) NOT NULL COMMENT 'SHA-256 hash of the content. This is our version identifier.',
  `content` longtext NOT NULL COMMENT 'The full snapshot content.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sage_todos`
--

CREATE TABLE `sage_todos` (
  `id` int(11) NOT NULL,
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
  `img2img_prompt` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `code_analysis_log`
--
ALTER TABLE `code_analysis_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`);

--
-- Indizes für die Tabelle `code_classes`
--
ALTER TABLE `code_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`);

--
-- Indizes für die Tabelle `code_files`
--
ALTER TABLE `code_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `path` (`path`) USING HASH;

--
-- Indizes für die Tabelle `gpt_conversations`
--
ALTER TABLE `gpt_conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `external_id` (`external_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `updated_at` (`updated_at`),
  ADD KEY `model` (`model`);

--
-- Indizes für die Tabelle `gpt_messages`
--
ALTER TABLE `gpt_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_external_id` (`conversation_external_id`),
  ADD KEY `message_index` (`message_index`),
  ADD KEY `created_at` (`created_at`);
ALTER TABLE `gpt_messages` ADD FULLTEXT KEY `ft_content` (`content_text`);

--
-- Indizes für die Tabelle `pages_dashboard`
--
ALTER TABLE `pages_dashboard`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indizes für die Tabelle `recipes`
--
ALTER TABLE `recipes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_recipes_recipe_groups_idx` (`recipe_group_id`);

--
-- Indizes für die Tabelle `recipe_groups`
--
ALTER TABLE `recipe_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_UNIQUE` (`name`);

--
-- Indizes für die Tabelle `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_recipe_ingredients_recipes_idx` (`recipe_id`),
  ADD KEY `fk_recipe_ingredients_snapshots_idx` (`snapshot_id`);

--
-- Indizes für die Tabelle `recipe_ingredient_snapshots`
--
ALTER TABLE `recipe_ingredient_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `content_hash_UNIQUE` (`content_hash`);

--
-- Indizes für die Tabelle `sage_todos`
--
ALTER TABLE `sage_todos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `code_analysis_log`
--
ALTER TABLE `code_analysis_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `code_classes`
--
ALTER TABLE `code_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `code_files`
--
ALTER TABLE `code_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `gpt_conversations`
--
ALTER TABLE `gpt_conversations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `gpt_messages`
--
ALTER TABLE `gpt_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `pages_dashboard`
--
ALTER TABLE `pages_dashboard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `recipes`
--
ALTER TABLE `recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `recipe_groups`
--
ALTER TABLE `recipe_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `recipe_ingredient_snapshots`
--
ALTER TABLE `recipe_ingredient_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `sage_todos`
--
ALTER TABLE `sage_todos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `code_analysis_log`
--
ALTER TABLE `code_analysis_log`
  ADD CONSTRAINT `code_analysis_log_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `code_files` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `code_classes`
--
ALTER TABLE `code_classes`
  ADD CONSTRAINT `code_classes_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `code_files` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `recipes`
--
ALTER TABLE `recipes`
  ADD CONSTRAINT `fk_recipes_recipe_groups` FOREIGN KEY (`recipe_group_id`) REFERENCES `recipe_groups` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD CONSTRAINT `fk_recipe_ingredients_recipes` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_recipe_ingredients_snapshots` FOREIGN KEY (`snapshot_id`) REFERENCES `recipe_ingredient_snapshots` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
