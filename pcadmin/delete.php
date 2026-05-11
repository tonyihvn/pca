<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

define('BOOTSTRAP_API', true);
$config = require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

// ---- Auth check ---------------------------------------------------------------
start_admin_session($config);
if (!admin_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// ---- Validate request --------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$id = (int)($data['id'] ?? 0);
$csrf = trim((string)($data['csrf'] ?? ''));

if (!csrf_check($csrf)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF token invalid']);
    exit;
}

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

// ---- Delete ---------------------------------------------------------------
try {
    $pdo = db($config);
    $stmt = $pdo->prepare('DELETE FROM registrations WHERE id = :id');
    $stmt->execute([':id' => $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Registration deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Registration not found']);
    }
} catch (Throwable $e) {
    error_log('[pcadmin/delete] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
