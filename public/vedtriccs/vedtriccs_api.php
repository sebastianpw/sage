<?php
// public/vedtriccs/vedtriccs_api.php
// SAGE VedTriccs — Standalone API entry point
// Mirrors the pattern of public/ved/ved_api.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';
require_once __DIR__ . '/classes/VedTriccsConfig.php';
require_once __DIR__ . '/classes/VedTriccsApi.php';

header('Content-Type: application/json');

if (empty($_REQUEST['api_action'])) {
    echo json_encode(['status' => 'error', 'message' => 'api_action is required']);
    exit;
}

(new VedTriccsApi($pdo))->dispatch();
