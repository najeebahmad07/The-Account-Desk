<?php
// File: index.php
// No whitespace before this line
require_once __DIR__ . '/config/init.php';

if (isLoggedIn()) {
    if (isSuperAdmin()) {
        redirect(APP_URL . '/superadmin/dashboard.php');
    } else {
        redirect(APP_URL . '/center/dashboard.php');
    }
} else {
    redirect(APP_URL . '/login.php');
}