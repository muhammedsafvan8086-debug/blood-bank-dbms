<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    log_action($pdo, 'Logout', $_SESSION['name'] . ' logged out');
}
$_SESSION = [];
session_destroy();
header('Location: /bloodbank/index.php');
exit;
