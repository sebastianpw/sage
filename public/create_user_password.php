<?php require_once __DIR__ . '/bootstrap.php'; require __DIR__ . '/env_locals.php'; ?>

echo password_hash('password', PASSWORD_DEFAULT) . PHP_EOL;


