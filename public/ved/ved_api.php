<?php
// public/ved/ved_api.php
// SAGE VED — Dedicated API entry point
// This file handles all api_action requests independently of index.php,
// so it can be called directly without loading the full page template.
// Pattern mirrors: public/animatic_multiplane_api.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';
require_once __DIR__ . '/classes/VedConfig.php';
require_once __DIR__ . '/classes/VedApi.php';

// All responses are JSON
header('Content-Type: application/json');

// Guard: action is required
if (empty($_REQUEST['api_action'])) {
    echo json_encode(['status' => 'error', 'message' => 'api_action is required']);
    exit;
}

// Dispatch
(new VedApi($pdo))->dispatch();
