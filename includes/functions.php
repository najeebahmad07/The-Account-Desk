<?php
// File: includes/functions.php
// Core utility functions - AUTO-BLOCK BUG FIXED

function sanitize($data) {
    if ($data === null) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

function isCenterAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'center_admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('danger', 'Please login to continue.');
        redirect(APP_URL . '/login.php');
    }
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        setFlash('warning', 'Session expired. Please login again.');
        redirect(APP_URL . '/login.php');
    }
    $_SESSION['last_activity'] = time();
}

function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        setFlash('danger', 'Access denied. Super Admin privileges required.');
        redirect(APP_URL . '/login.php');
    }
}

function requireCenterAdmin() {
    requireLogin();
    if (!isCenterAdmin()) {
        setFlash('danger', 'Access denied.');
        redirect(APP_URL . '/login.php');
    }
}

/**
 * Format currency in Indian Rupees
 */
function formatCurrency($amount) {
    $amount = (float)$amount;
    $isNegative = $amount < 0;
    $amount = abs($amount);
    
    $whole = floor($amount);
    $decimal = sprintf('%02d', round(($amount - $whole) * 100));
    $wholeStr = (string)$whole;
    $length = strlen($wholeStr);
    
    if ($length > 3) {
        $lastThree = substr($wholeStr, -3);
        $remaining = substr($wholeStr, 0, $length - 3);
        $formatted = '';
        $remLen = strlen($remaining);
        for ($i = 0; $i < $remLen; $i++) {
            if ($i > 0 && ($remLen - $i) % 2 === 0) {
                $formatted .= ',';
            }
            $formatted .= $remaining[$i];
        }
        $result = $formatted . ',' . $lastThree . '.' . $decimal;
    } else {
        $result = $wholeStr . '.' . $decimal;
    }
    
    return ($isNegative ? '-' : '') . '₹' . $result;
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('d M, Y', strtotime($date));
}

function getMonthName($month) {
    return date('F', mktime(0, 0, 0, (int)$month, 1));
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function logActivity($userId, $action, $details = '') {
    try {
        $conn = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * ============================================
 * AUTO BLOCK CHECK - FIXED VERSION
 * ============================================
 * 
 * BUG FIX: Previously this function was blocking
 * users on EVERY page load, which meant even after
 * Super Admin unblocked them, they got blocked again
 * immediately on next page load.
 * 
 * FIX: 
 * 1. Only check ONCE per day (not every hour)
 * 2. Only block users who were NOT manually unblocked
 * 3. Use a database flag to track manual unblock
 * 4. Only check for PREVIOUS month (not all months)
 * 5. Skip if Super Admin already unblocked the user
 */
function autoBlockCheck() {
    // Only run once per day to prevent constant re-blocking
    if (isset($_SESSION['last_block_check']) && 
        (time() - $_SESSION['last_block_check'] < 86400)) { // 86400 = 24 hours
        return;
    }
    $_SESSION['last_block_check'] = time();
    
    try {
        $conn = getDBConnection();
        $currentDay = (int)date('j');
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');
        
        // Only check if we're past the auto-block day threshold
        if ($currentDay <= AUTO_BLOCK_DAYS) {
            $conn->close();
            return;
        }
        
        // Get ONLY the previous month
        $checkMonth = $currentMonth - 1;
        $checkYear = $currentYear;
        if ($checkMonth < 1) {
            $checkMonth = 12;
            $checkYear = $currentYear - 1;
        }
        
        // FIXED QUERY: Only auto-block users who:
        // 1. Are currently 'active' 
        // 2. Are center_admin role
        // 3. Have a center assigned
        // 4. Have NOT submitted report for previous month
        // 5. Were NOT manually unblocked by super admin (check activity_log)
        $query = "SELECT u.id, u.center_id, u.username FROM users u 
                  WHERE u.role = 'center_admin' 
                  AND u.status = 'active' 
                  AND u.center_id IS NOT NULL
                  AND u.center_id NOT IN (
                      SELECT mr.center_id FROM monthly_reports mr 
                      WHERE mr.month = ? AND mr.year = ? AND mr.status = 'submitted'
                  )
                  AND u.id NOT IN (
                      SELECT DISTINCT al.user_id FROM activity_log al 
                      WHERE al.action = 'Manual Unblock' 
                      AND al.user_id IS NOT NULL
                      AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  )";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $checkMonth, $checkYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Auto-block the user
            $updateStmt = $conn->prepare("UPDATE users SET status = 'blocked' WHERE id = ?");
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Log the auto-block
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'system';
            $detail = "Auto-blocked: No report for " . getMonthName($checkMonth) . " $checkYear";
            $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, 'Auto-Blocked', ?, ?)");
            $logStmt->bind_param("iss", $row['id'], $detail, $ip);
            $logStmt->execute();
            $logStmt->close();
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Silent fail
    }
}

function getSettings() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM settings LIMIT 1");
    $settings = $result->fetch_assoc();
    $conn->close();
    return $settings;
}

function getCenterName($centerId) {
    if (empty($centerId)) return 'N/A';
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT center_name FROM centers WHERE id = ?");
    $stmt->bind_param("i", $centerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $center = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $center ? $center['center_name'] : 'Unknown';
}

function getCenterMonthlyTotals($centerId, $month, $year) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE center_id = ? AND month = ? AND year = ? AND type = 'income'");
    $stmt->bind_param("iii", $centerId, $month, $year);
    $stmt->execute();
    $income = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE center_id = ? AND month = ? AND year = ? AND type = 'expense'");
    $stmt->bind_param("iii", $centerId, $month, $year);
    $stmt->execute();
    $expense = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $conn->close();
    
    return [
        'income' => (float)$income,
        'expense' => (float)$expense,
        'profit' => (float)($income - $expense)
    ];
}