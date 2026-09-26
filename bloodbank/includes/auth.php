<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Is anyone logged in? */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/** Redirect to login if not authenticated. */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /bloodbank/login.php');
        exit;
    }
}

/** Restrict a page to one or more roles. Call after require_login(). */
function require_role($roles): void {
    require_login();
    $roles = (array) $roles;
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('<h2>403 - Access Denied</h2><p>You do not have permission to view this page.</p><a href="/bloodbank/index.php">Return home</a>');
    }
}

/** Current logged-in user's role, or null. */
function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/** CSRF token helpers */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

/** Record an audit-log entry. */
function log_action(PDO $pdo, string $action, string $description = ''): void {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, description) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'] ?? null, $action, $description]);
}

/** Create a notification for a role (e.g. all admins) or a specific user. */
function notify(PDO $pdo, ?string $roleTarget, string $message, ?int $userId = null): void {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, role_target, message) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $roleTarget, $message]);
}

/** Simple helper to escape output. */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
