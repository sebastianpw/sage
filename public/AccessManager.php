<?php
class AccessManager
{
    public static array $allowedWithoutLogin = [
        '/login.php',
        '/google_login.php',
        '/google_callback.php',
        '/logout.php',
        '/register.php',
    ];

    public static array $allowedPrefixes = [
        '/ajax/',
    ];

public static function authenticate(array $options = []): void
{
    $allowed  = $options['allowedWithoutLogin'] ?? self::$allowedWithoutLogin;
    $prefixes = $options['allowedPrefixes'] ?? self::$allowedPrefixes;

    // CLI handling
    if (PHP_SAPI === 'cli') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $systemUserId = getenv('SYSTEM_USER_ID') ?: null;
        if ($systemUserId) {
            $_SESSION['user_id'] = (int)$systemUserId;
        }
        return; // no redirect in CLI
    }

    // Web request: start session if needed
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '/';
    $requestUri    = $_SERVER['REQUEST_URI'] ?? $currentScript;

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Check allow lists
    $isAllowed = in_array($currentScript, $allowed, true);
    if (!$isAllowed) {
        foreach ($prefixes as $prefix) {
            if (strpos($currentScript, $prefix) === 0 || strpos($requestUri, $prefix) === 0) {
                $isAllowed = true;
                break;
            }
        }
    }

    // If not authenticated and not allowed, redirect or respond
    if (!isset($_SESSION['user_id']) && !$isAllowed) {
        // prefer explicit ?redirect=... from GET, otherwise use the actual requested URI (preserves query)
        $redirect = $_GET['redirect'] ?? $requestUri;
        if (!self::isValidLocalPath($redirect)) {
            $redirect = '/dashboard.php';
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'error'    => 'not_logged_in',
                'redirect' => '/login.php?redirect=' . rawurlencode($redirect),
            ]);
            exit;
        } else {
            header("Location: /login.php?redirect=" . rawurlencode($redirect));
            exit;
        }
    }
}


    /**
     * Utility: check if a session currently has an authenticated user.
     */
    public static function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) return false;
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Validate redirect path is local and safe
     */
    protected static function isValidLocalPath(string $candidate): bool
    {
        if ($candidate === '') return false;
        if ($candidate[0] !== '/') return false;
        if (preg_match('#[a-zA-Z0-9+\-.]+://#', $candidate)) return false;
        if (preg_match('/[\r\n]/', $candidate)) return false;
        return true;
    }

    /**
     * Render a hidden input for the redirect in forms.
     */
    public static function renderHiddenRedirectInput(string $default = '/dashboard.php'): string
    {
        $redirect = $_GET['redirect'] ?? $default;
        if (!self::isValidLocalPath($redirect)) {
            $redirect = $default;
        }
        return '<input type="hidden" name="redirect" value="' . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . '">';
    }


    /**
     * Return a URL for $path with the current safe GET redirect appended.
     * Example: AccessManager::urlWithRedirect('/google_login.php')
     */
    public static function urlWithRedirect(string $path, string $default = '/dashboard.php'): string
    {
        $redirect = $_GET['redirect'] ?? self::getPendingRedirect() ?? $default;
        if (!self::isValidLocalPath($redirect)) {
            $redirect = $default;
        }
        return $path . '?redirect=' . rawurlencode($redirect);
    }

public static function storePendingRedirectFromGet(string $default = '/dashboard.php'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Prefer explicit ?redirect=... (from login link). If missing, keep any already stored pending redirect.
    // Otherwise, fall back to the current REQUEST_URI (which may contain query string) or finally the default.
    $candidate = $_GET['redirect'] ?? null;
    if ($candidate !== null && $candidate !== '' && self::isValidLocalPath($candidate)) {
        $_SESSION['pending_redirect'] = $candidate;
        return;
    }

    if (!empty($_SESSION['pending_redirect']) && self::isValidLocalPath($_SESSION['pending_redirect'])) {
        // keep existing pending redirect
        return;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if ($requestUri !== '' && self::isValidLocalPath($requestUri)) {
        $_SESSION['pending_redirect'] = $requestUri;
        return;
    }

    // final fallback
    $_SESSION['pending_redirect'] = $default;
}


    /**
     * Return pending redirect stored in session (or null if none).
     * Does NOT clear it.
     */
    public static function getPendingRedirect(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            return null;
        }
        return $_SESSION['pending_redirect'] ?? null;
    }

    /**
     * Consume and return pending redirect (clear it afterwards).
     * Returns $default if none found or invalid.
     */
    public static function consumePendingRedirect(string $default = '/dashboard.php'): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $r = $_SESSION['pending_redirect'] ?? null;
        unset($_SESSION['pending_redirect']);
        if ($r === null || !self::isValidLocalPath($r)) {
            return $default;
        }
        return $r;
    }


}
