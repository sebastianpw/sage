<?php
namespace App\Core;

class SetupManager
{
    public static function adminExists(\PDO $pdo): bool
    {
        $sql = "SELECT COUNT(*) as c FROM `user` WHERE `role` = 'admin'";
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0) > 0;
    }

    public static function usernameExists(\PDO $pdo, string $username): bool
    {
        $sql = "SELECT COUNT(*) as c FROM `user` WHERE `username` = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0) > 0;
    }

    public static function createAdmin(\PDO $pdo, string $username, string $password, ?string $name = null, ?string $email = null): bool
    {
        // bcrypt via password_hash
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO `user` 
            (`username`, `password`, `name`, `google_email`, `role`, `created_at`, `updated_at`)
            VALUES (:username, :password, :name, :email, 'admin', NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':username' => $username,
            ':password' => $hash,
            ':name'     => $name,
            ':email'    => $email,
        ]);
    }

// Inside class SetupManager
/**
 * Returns the user row of the currently authenticated session user, or null.
 */
public static function getSessionUser(\PDO $pdo): ?array
{
    if (!isset($_SESSION['user_id'])) return null;
    return self::getUserById($pdo, (int)$_SESSION['user_id']);
}


/**
 * Get a user row by id (or null).
 */
public static function getUserById(\PDO $pdo, int $id): ?array
{
    $sql = "SELECT * FROM `user` WHERE `id` = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Promote an existing user (by id) to admin.
 */
public static function promoteToAdmin(\PDO $pdo, int $userId): bool
{
    $sql = "UPDATE `user` SET `role` = 'admin', `updated_at` = NOW() WHERE `id` = :id";
    $stmt = $pdo->prepare($sql);
    return (bool)$stmt->execute([':id' => $userId]);
}

}
