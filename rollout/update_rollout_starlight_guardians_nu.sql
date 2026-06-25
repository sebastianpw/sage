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
