<?php
/**
 * Mail / SMTP Configuration
 * Supports transactional emails for exam completion, password resets, and proctoring breach alerts
 */

define('MAIL_ENABLED', false); // Set true when SMTP credentials are configured
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'portal-notifications@university.edu');
define('SMTP_PASS', 'your_secure_smtp_app_password');
define('SMTP_SECURE', 'tls');
define('MAIL_FROM_ADDRESS', 'noreply@university.edu');
define('MAIL_FROM_NAME', 'Smart University Proctoring System');

/**
 * Dispatch portal alert or scorecard email
 */
function sendPortalEmail($to, $subject, $htmlMessage) {
    if (!MAIL_ENABLED) {
        // Return simulated success in offline lab testing environment
        return ['status' => true, 'message' => 'Simulated: Mail delivery bypassed (MAIL_ENABLED is false).'];
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'Reply-To: ' . MAIL_FROM_ADDRESS,
        'X-Mailer: PHP/' . phpversion()
    ];

    $success = @mail($to, $subject, $htmlMessage, implode("\r\n", $headers));
    return [
        'status' => $success,
        'message' => $success ? 'Email sent successfully.' : 'Standard mail delivery failed.'
    ];
}
