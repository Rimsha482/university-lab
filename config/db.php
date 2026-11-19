<?php
/**
 * Database Connection & Global Database Utilities
 * Uses PDO for prepared statements & robust error handling
 */

require_once __DIR__ . '/constants.php';

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'exam_portal_db';
$db_port = 3306;

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // If database doesn't exist, provide a clean friendly diagnostic screen
    die("<div style='font-family:sans-serif;padding:30px;background:#fff5f5;border-left:5px solid #dc3545;margin:40px;'>
            <h2 style='color:#dc3545;margin-top:0;'>Database Connection Failed</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Please ensure XAMPP MySQL is running and the database <code>exam_portal_db</code> has been imported from <code>backups/schema.sql</code>.</p>
         </div>");
}

// Global helper to get PDO instance
if (!function_exists('getDb')) {
    function getDb() {
        global $pdo;
        return $pdo;
    }
}

// Helper to log system actions
if (!function_exists('logSystemActivity')) {
    function logSystemActivity($userId, $action, $details = '') {
        try {
            $pdo = getDb();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 250);
            $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $details, $ip, $ua]);
        } catch (Exception $e) {
            // Silently ignore logging failures to avoid halting operations
        }
    }
}

// Helper to sanitize inputs
if (!function_exists('cleanInput')) {
    function cleanInput($data) {
        if (is_array($data)) {
            return array_map('cleanInput', $data);
        }
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}

// Helper to get system settings
if (!function_exists('getSystemSetting')) {
    function getSystemSetting($key, $default = '') {
        try {
            $pdo = getDb();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// Helper for relative time display
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (!$datetime) return 'Never';
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 5) return 'Just now';
        if ($diff < 60) return $diff . ' sec ago';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
        return date('M d, Y h:i A', $time);
    }
}
