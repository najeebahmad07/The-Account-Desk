<?php
// File: config/init.php
// System initialization - FIXED

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/export.php';

// Run auto-block check ONLY for non-super-admin pages
// Super Admin actions should not trigger auto-block
if (!isSuperAdmin()) {
    autoBlockCheck();
}