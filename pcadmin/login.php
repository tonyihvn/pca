<?php
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/_layout.php';

start_admin_session($config);

// Already logged in? Go to dashboard.
if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Per-IP brute-force throttle (5 failed attempts / 15 min)
$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rlDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'academy_rl';
if (!is_dir($rlDir)) { @mkdir($rlDir, 0700, true); }
$rlFile = $rlDir . DIRECTORY_SEPARATOR . sha1('login|' . $ip) . '.json';

function login_attempts_load(string $file, int $window): array {
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) { $hits = $decoded; }
    }
    return array_values(array_filter($hits, static fn($t) => is_int($t) && ($now - $t) < $window));
}
function login_attempts_record(string $file, array $hits): void {
    $hits[] = time();
    @file_put_contents($file, json_encode($hits), LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hits = login_attempts_load($rlFile, 900);
    if (count($hits) >= 5) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        // CSRF
        if (!csrf_check($_POST['csrf'] ?? null)) {
            $error = 'Invalid session. Please reload the page and try again.';
        } else {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if (admin_login_attempt($config, $username, $password)) {
                @unlink($rlFile);
                header('Location: index.php');
                exit;
            }
            login_attempts_record($rlFile, $hits);
            // Generic error so we don't reveal whether the username exists
            $error = 'Invalid username or password.';
            // brief delay to slow down brute force
            usleep(500000);
        }
    }
}

pcadmin_head('Sign in');
?>
<div class="max-w-md mx-auto mt-12 bg-white rounded-2xl shadow-xl p-10">
    <div class="text-center mb-8">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-slate-900 text-orange-500 items-center justify-center text-2xl mb-4">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 class="text-2xl font-bold">Admin Sign-in</h1>
        <p class="text-slate-500 text-sm mt-1">Restricted area &mdash; authorized personnel only.</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-6">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" class="space-y-5">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Username</label>
            <input name="username" type="text" required autofocus
                   value="<?= e($_POST['username'] ?? '') ?>"
                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Password</label>
            <input name="password" type="password" required
                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>

        <button type="submit"
                class="w-full bg-slate-900 text-white py-3 rounded-xl font-bold hover:bg-orange-600 transition">
            Sign in <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
        </button>
    </form>
</div>
<?php pcadmin_foot();
