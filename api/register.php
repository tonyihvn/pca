<?php
/**
 * Public registration endpoint.
 * Stores form submissions; admin views them in /admin/.
 */

declare(strict_types=1);

define('BOOTSTRAP_API', true);
$config = require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

// ---- CORS (allow only configured origins) ---------------------------------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $config['allowed_origins'] ?? [], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204); exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// ---- Origin / Referer check -----------------------------------------------
// Always allow same-origin requests (the form is on the same host as this API).
// Otherwise the request must match one of the configured allowed_origins.
$source     = $origin !== '' ? $origin : ($_SERVER['HTTP_REFERER'] ?? '');
$serverHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$sourceHost = $source !== '' ? strtolower((string) parse_url($source, PHP_URL_HOST)) : '';
$sameOrigin = $sourceHost !== '' && $sourceHost === $serverHost;

if (!$sameOrigin && $source !== '' && !empty($config['allowed_origins'])) {
    $ok = false;
    foreach ($config['allowed_origins'] as $allowed) {
        if (stripos($source, $allowed) === 0) { $ok = true; break; }
    }
    if (!$ok) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

// ---- Rate limiting --------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!rate_limit_ok($ip, 'reg', $config['rate_limit']['max'], $config['rate_limit']['window'])) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please try again later.']);
    exit;
}

// ---- Parse + validate -----------------------------------------------------
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 8192) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$errors = [];
$name        = trim((string)($data['name']        ?? ''));
$email       = trim((string)($data['email']       ?? ''));
$phone       = trim((string)($data['phone']       ?? ''));
$level       = trim((string)($data['level']       ?? ''));
$description = trim((string)($data['description'] ?? ''));
$course      = trim((string)($data['course']      ?? ''));

if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
    $errors['name'] = 'Please enter your full name (2-120 chars).';
} elseif (!preg_match("/^[\p{L}\p{M}\s'.\-]+$/u", $name)) {
    $errors['name'] = 'Name contains invalid characters.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
    $errors['email'] = 'Please enter a valid email address.';
}
$digits = preg_replace('/\D+/', '', $phone);
if ($phone === '' || strlen((string)$digits) < 7 || strlen((string)$digits) > 20
    || !preg_match('/^[\d\s+()\-]+$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}
if (!in_array($level, ['Beginner', 'Intermediate', 'Senior'], true)) {
    $errors['level'] = 'Please choose a valid experience level.';
}
if (!in_array($course, $config['allowed_courses'] ?? [], true)) {
    $errors['course'] = 'Invalid course selection.';
}
if (mb_strlen($description) > 2000) {
    $errors['description'] = 'Please keep your message under 2000 characters.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'errors' => $errors]);
    exit;
}

// ---- Insert ---------------------------------------------------------------
try {
    $stmt = db($config)->prepare(
        'INSERT INTO registrations
         (course, name, email, phone, level, description, ip, user_agent)
         VALUES (:course, :name, :email, :phone, :level, :description, :ip, :ua)'
    );
    $stmt->execute([
        ':course'      => $course,
        ':name'        => $name,
        ':email'       => $email,
        ':phone'       => $phone,
        ':level'       => $level,
        ':description' => $description,
        ':ip'          => $ip,
        ':ua'          => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
} catch (Throwable $e) {
    error_log('[register] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not save registration. Please try again.']);
    exit;
}

// Notify admin (best-effort; never blocks the success response)
$subject = "New {$course} registration: {$name}";
$body    = "A new course registration was submitted.\n\n"
         . "Course:      {$course}\n"
         . "Name:        {$name}\n"
         . "Email:       {$email}\n"
         . "Phone:       {$phone}\n"
         . "Level:       {$level}\n"
         . "Submitted:   " . date('Y-m-d H:i:s') . "\n"
         . "IP:          {$ip}\n\n"
         . "Why they want to join:\n"
         . ($description !== '' ? $description : '(not provided)') . "\n";

send_admin_notification($config, $subject, $body, $email, $name);

echo json_encode(['status' => 'success']);

// ---------------------------------------------------------------------------
function rate_limit_ok(string $ip, string $bucket, int $max, int $window): bool
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'academy_rl';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    $file = $dir . DIRECTORY_SEPARATOR . sha1($bucket . '|' . $ip) . '.json';
    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) { $hits = $decoded; }
    }
    $hits = array_values(array_filter($hits, static fn($t) => is_int($t) && ($now - $t) < $window));
    if (count($hits) >= $max) { return false; }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
}
