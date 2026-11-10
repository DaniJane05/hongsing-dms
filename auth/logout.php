<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit_log.php';

$db = Database::getConnection();

if (isLoggedIn()) {

    logAudit(
    currentUserId(),
    "LOGOUT",
    "Authentication",
    currentUserId(),
    $_SESSION['full_name'] . " logged out."
);
}

logoutUser();

header("Location: login.php?logged_out");
exit;