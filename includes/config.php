<?php
/**
 * Copy this file to `config.php` and fill in real values.
 * `config.php` is denied by .htaccess so it cannot be downloaded.
 */

return [
    // ---- Database (MySQL/MariaDB) -----------------------------------------
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'publddkr_pcadb',
        'user'    => 'publddkr_pcadbadmin',
        'pass'    => '@@PCADBAdmin22',
        'charset' => 'utf8mb4',
    ],

    // ---- Allowed origins (your site URL) ----------------------------------
    // Used for CORS + Origin/Referer check. Add your live domain here.
    'allowed_origins' => [
        'https://publicclass.academy'
    ],

    // ---- Whitelist of valid courses for the hidden field ------------------
    'allowed_courses' => ['Flutter', 'Java-Backend'],

    // ---- Email notifications ---------------------------------------------
    // Recipient(s) for new registrations / contact messages.
    // Can be a single address (string) or a list (array). Empty disables email.
    'notify_email' => 'tokunbooyelekan@gmail.com',

    // The "From" address shown to recipients. MUST be a real mailbox on your
    // server's domain or it will be rejected/marked as spam.
    'mail_from'      => 'tokunbooyelekan@gmail.com',
    'mail_from_name' => 'Public Class Academy',

    // ---- Per-IP rate limit ------------------------------------------------
    'rate_limit' => [
        'max'    => 5,
        'window' => 600, // seconds
    ],

    // ---- Admin credentials -------------------------------------------------
    // Generate a hash from PHP CLI:
    //   php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
    // Then paste the resulting hash below. NEVER store the plain password.
    'admin' => [
        'username' => 'pcadmin',
        // Hash for: TayoTutor222   (regenerate immediately if this leaks)
        'password_hash' => '$2y$10$OYEWwaUkecA7GqZ8rtjvZurWHUAlx9A2c/dnme2Vpx6IldOA5ZNiS',
    ],

    // ---- Session ----------------------------------------------------------
    'session' => [
        'name'     => 'ACADEMY_SID',
        'lifetime' => 3600,   // seconds of inactivity before auto-logout
        'secure'   => false,  // set true when serving over HTTPS
    ],
];
