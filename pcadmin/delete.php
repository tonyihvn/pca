<?php
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Auth check ---------------------------------------------------------------
start_admin_session($config);
admin_require_login('login.php');

// ---- Validate request --------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$id = (int)($data['id'] ?? 0);

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
