<?php
/**
 * Smart University Lab & Exam Proctoring Portal
 * Core Constants & Global Configuration
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

// Dynamic Base URL calculation (works under http://localhost/exam-portal or http://localhost/university lab)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine the base subfolder
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $rootDir = str_replace('\\', '/', realpath(ROOT_PATH) ?: '');
    
    $subDir = '';
    if (!empty($docRoot) && !empty($rootDir) && strpos($rootDir, $docRoot) === 0) {
        $subDir = substr($rootDir, strlen($docRoot));
    } else {
        // Fallback: search for exam-portal or university lab in script path
        if (strpos($scriptName, '/exam-portal') !== false) {
            $subDir = '/exam-portal';
        } elseif (strpos($scriptName, '/university lab') !== false) {
            $subDir = '/university lab';
        } elseif (strpos($scriptName, '/university%20lab') !== false) {
            $subDir = '/university%20lab';
        }
    }
    
    $subDir = '/' . trim($subDir, '/');
    if ($subDir === '/') {
        $subDir = '';
    }
    
    define('BASE_URL', $protocol . $host . $subDir);
}

// System Constants
define('APP_NAME', 'Smart University Proctoring Portal');
define('APP_SHORT_NAME', 'SmartProctor');
define('APP_VERSION', '2.5.0');
define('MAX_TAB_SWITCH_LIMIT', 3);
define('HEARTBEAT_INTERVAL_SEC', 10);
define('SESSION_TIMEOUT_SEC', 7200); // 2 hours
define('DEFAULT_PASSING_PERCENT', 50);

// File Upload Directories
define('UPLOAD_DIR_PROFILES', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR);
define('UPLOAD_DIR_QUESTIONS', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR . 'question_images' . DIRECTORY_SEPARATOR);
define('BACKUP_DIR', ROOT_PATH . 'backups' . DIRECTORY_SEPARATOR . 'auto_backups' . DIRECTORY_SEPARATOR);
