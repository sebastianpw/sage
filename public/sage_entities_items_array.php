<?php
// sage_entities_items_array.php
// Centralized list of entities for menus, scripts, and CLI
// Uses the refreshable PDO connection from bootstrap.php

// Initialize $items for IDE hints and to avoid undefined variable notices
$items = [];

// Include database connection (refreshable)
require_once 'bootstrap.php';

// Fetch all entities from meta_entities table, ordered by 'order'
try {
    $stmt = $pdo->query("SELECT * FROM meta_entities ORDER BY `order` ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    die("Failed to load entities from meta_entities: " . $e->getMessage());
}



