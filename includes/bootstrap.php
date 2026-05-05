<?php
/**
 * Bootstrap: loads config, sets safe defaults, returns config array.
 * Required by every PHP entry point.
 *
 * Define `BOOTSTRAP_API` (bool true) BEFORE requiring this file from API
 * endpoints so missing-config errors are returned as JSON instead of HTML.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    $isApi = defined('BOOTSTRAP_API') && BOOTSTRAP_API === true;
    http_response_code(500);

    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Server not configured']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Setup required</title>'
           . '<style>body{font-family:system-ui,Segoe UI,sans-serif;background:#0f172a;color:#e2e8f0;'
           . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:2rem}'
           . '.card{background:#1e293b;padding:2.5rem;border-radius:1rem;max-width:560px;'
           . 'box-shadow:0 20px 50px rgba(0,0,0,.4);border:1px solid #334155}'
           . 'h1{margin:0 0 .5rem;color:#f97316;font-size:1.5rem}'
           . 'code{background:#0f172a;padding:.15rem .4rem;border-radius:.3rem;color:#fbbf24}'
           . 'p{line-height:1.6;color:#cbd5e1}'
           . 'ol{line-height:1.8;color:#cbd5e1}</style></head><body>'
           . '<div class="card"><h1>Setup required</h1>'
           . '<p>The site cannot start because <code>includes/config.php</code> is missing.</p>'
           . '<ol><li>Copy <code>includes/config.sample.php</code> to <code>includes/config.php</code>.</li>'
           . '<li>Fill in your database credentials and admin email.</li>'
           . '<li>Reload this page.</li></ol></div></body></html>';
    }
    exit;
}

/** @var array $CONFIG */
$CONFIG = require $configPath;
return $CONFIG;
