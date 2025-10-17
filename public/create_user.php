<?php

require_once "bootstrap.php";

$password = password_hash('password', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO `user` (`username`, `password`, `name`, `role`) VALUES (?, ?, ?, ?)");
$result = $stmt->execute(['admin', $password, 'Peter Sebring', 'admin']);
var_dump($result);

