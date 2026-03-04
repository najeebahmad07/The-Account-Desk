<?php
// File: config/app.php
// Application configuration - LIVE SERVER FIX
// IMPORTANT: No whitespace or BOM before opening <?php tag

// Prevent double initialization
if (defined('APP_INITIALIZED')) {
    return;
}
define('APP_INITIALIZED', true);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    // Check if headers already sent
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
}

// Error reporting (set display_errors to 0 on live)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ============================================
// CHANGE THESE FOR YOUR LIVE SERVER
// ============================================
define('APP_NAME', 'TAD');
define('APP_TAGLINE', 'Smart Multi-Center Financial Management');
define('APP_VERSION', '1.0.0');

// IMPORTANT: Change this to your actual domain
define('APP_URL', '/tad');

define('SESSION_TIMEOUT', 3600);
define('AUTO_BLOCK_DAYS', 5);

// Base path - auto detect
define('BASE_PATH', dirname(__DIR__));
define('REPORTS_PATH', BASE_PATH . '/reports/');

// Expense categories
define('EXPENSE_CATEGORIES', [
    'Salaries',
    'Wages',
    'Advertisement',
    'Repairs to Building',
    'Electronic Fitting Items',
    'Power and Fuel (Indirect)',
    'Bank Charges',
    'Website [hosting/renewal/maintainance] charges',
    'Entertainment',
    'Legal Expenses',
    'Travelling expenses',
    'Dearness Allowance',
    'Rent Paid',
    'Bonus',
    'Increment',
    'Workmen and Staff Welfare Expenses',
    'Festival Celebration Expenses',
    'Audit Fee',
    'Gift',
    'Maintainance',
    'Electricity bill',
    'Water bill',
    'Other Expenses',
    'Toileteries and sanitation items',
    'Depreciation',
    'Assets',
    'Equipments',
    'Furniture and fitting',
    'Generator',
    'Fan',
    'Smart Classes',
    'Computer',
    'Air Condition',
    'Invertor and Battery',
    'Printer'
]);
// Timezone for India
date_default_timezone_set('Asia/Kolkata');