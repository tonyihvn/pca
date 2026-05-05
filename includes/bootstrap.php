<?php
/**
 * Bootstrap: loads config, sets safe defaults, returns config array.
 * Required by every PHP entry point.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Server not configured']);
    exit;
}

/** @var array $CONFIG */
$CONFIG = require $configPath;
return $CONFIG;
