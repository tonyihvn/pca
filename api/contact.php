<?php
/**
 * Public contact-form endpoint.
 * Stores submissions in `contacts` table and emails the admin.
 */

declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

// ---- CORS -----------------------------------------------------------------
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

// ---- Origin/Referer check -------------------------------------------------
$source = $origin !== '' ? $origin : ($_SERVER['HTTP_REFERER'] ?? '');
if ($source !== '' && !empty($config['allowed_origins'])) {
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

// ---- Rate limit -----------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!contact_rate_limit_ok($ip, $config['rate_limit']['max'], $config['rate_limit']['window'])) {
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

$errors      = [];
$name        = trim((string)($data['name']        ?? ''));
$email       = trim((string)($data['email']       ?? ''));
$phone       = trim((string)($data['phone']       ?? ''));
$inquiryType = trim((string)($data['inquiryType'] ?? ''));
$message     = trim((string)($data['description'] ?? $data['message'] ?? ''));

$validInquiries = [
    'Java Academy Admissions',
    'Flutter Academy Admissions',
    'Payment & Installments',
    'Corporate Training',
    'Other',
];

if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
    $errors['name'] = 'Please enter your full name (2-120 chars).';
} elseif (!preg_match("/^[\p{L}\p{M}\s'.\-]+$/u", $name)) {
    $errors['name'] = 'Name contains invalid characters.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($phone !== '') {
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen((string)$digits) < 7 || strlen((string)$digits) > 20
        || !preg_match('/^[\d\s+()\-]+$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
}
if (!in_array($inquiryType, $validInquiries, true)) {
    $errors['inquiry'] = 'Please choose a valid inquiry type.';
}
if ($message === '' || mb_strlen($message) > 2000) {
    $errors['description'] = 'Please enter a message (max 2000 chars).';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'errors' => $errors]);
    exit;
}

// ---- Insert ---------------------------------------------------------------
try {
    $stmt = db($config)->prepare(
        'INSERT INTO contacts
         (name, email, phone, inquiry_type, message, ip, user_agent)
         VALUES (:name, :email, :phone, :inquiry, :message, :ip, :ua)'
    );
    $stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':phone'   => $phone,
        ':inquiry' => $inquiryType,
        ':message' => $message,
        ':ip'      => $ip,
        ':ua'      => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
} catch (Throwable $e) {
    error_log('[contact] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not send your message. Please try again.']);
    exit;
}

// ---- Notify admin ---------------------------------------------------------
$subject = "New contact message: {$inquiryType} ({$name})";
$body    = "A new contact-form message was submitted.\n\n"
         . "Name:         {$name}\n"
         . "Email:        {$email}\n"
         . "Phone:        " . ($phone !== '' ? $phone : '(not provided)') . "\n"
         . "Inquiry type: {$inquiryType}\n"
         . "Submitted:    " . date('Y-m-d H:i:s') . "\n"
         . "IP:           {$ip}\n\n"
         . "Message:\n{$message}\n";

send_admin_notification($config, $subject, $body, $email, $name);

echo json_encode(['status' => 'success']);

// ---------------------------------------------------------------------------
function contact_rate_limit_ok(string $ip, int $max, int $window): bool
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'academy_rl';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    $file = $dir . DIRECTORY_SEPARATOR . sha1('contact|' . $ip) . '.json';
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
