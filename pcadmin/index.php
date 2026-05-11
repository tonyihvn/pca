<?php
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/_layout.php';

start_admin_session($config);
admin_require_login('login.php');

// ---- Filters --------------------------------------------------------------
$course = trim((string)($_GET['course'] ?? ''));
$search = trim((string)($_GET['q']      ?? ''));
$page   = max(1, (int)($_GET['p']       ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($course !== '' && in_array($course, $config['allowed_courses'], true)) {
    $where[]            = 'course = :course';
    $params[':course']  = $course;
}
if ($search !== '') {
    $where[]            = '(name LIKE :q OR email LIKE :q OR phone LIKE :q)';
    $params[':q']       = '%' . $search . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $pdo = db($config);

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations $whereSql");
    $totalStmt->execute($params);
    $total = (int)$totalStmt->fetchColumn();

    $sql = "SELECT id, course, name, email, phone, level, description, ip, created_at
            FROM registrations
            $whereSql
            ORDER BY created_at DESC
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Stats
    $statsStmt = $pdo->query('SELECT course, COUNT(*) AS c FROM registrations GROUP BY course');
    $stats = ['Flutter' => 0, 'Java-Backend' => 0];
    foreach ($statsStmt->fetchAll() as $r) {
        $stats[$r['course']] = (int)$r['c'];
    }
    $grandTotal = array_sum($stats);

    $todayStmt = $pdo->query("SELECT COUNT(*) FROM registrations WHERE DATE(created_at) = CURDATE()");
    $todayCount = (int)$todayStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('[pcadmin/index] ' . $e->getMessage());
    $rows = [];
    $total = 0;
    $stats = ['Flutter' => 0, 'Java-Backend' => 0];
    $grandTotal = 0;
    $todayCount = 0;
    $dbError = 'Database unavailable. Check server logs.';
}

$totalPages = max(1, (int)ceil($total / $perPage));

pcadmin_head('Registrations');
?>
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-3xl font-extrabold">Course Registrations</h1>
        <p class="text-slate-500 text-sm">All applicants from the registration forms.</p>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="text-xs uppercase tracking-widest text-slate-500 font-bold">Total</div>
        <div class="text-3xl font-extrabold mt-1"><?= (int)$grandTotal ?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="text-xs uppercase tracking-widest text-cyan-600 font-bold">Flutter</div>
        <div class="text-3xl font-extrabold mt-1"><?= (int)$stats['Flutter'] ?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="text-xs uppercase tracking-widest text-orange-600 font-bold">Java-Backend</div>
        <div class="text-3xl font-extrabold mt-1"><?= (int)$stats['Java-Backend'] ?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="text-xs uppercase tracking-widest text-slate-500 font-bold">Today</div>
        <div class="text-3xl font-extrabold mt-1"><?= (int)$todayCount ?></div>
    </div>
</div>

<!-- Filters -->
<form method="get" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex flex-wrap gap-3 items-end mb-6">
    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Search</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name, email or phone"
               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
    </div>
    <div>
        <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Course</label>
        <select name="course" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option value="">All</option>
            <?php foreach ($config['allowed_courses'] as $c): ?>
                <option value="<?= e($c) ?>" <?= $course === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="bg-slate-900 text-white px-4 py-2 rounded-lg font-bold hover:bg-orange-600 transition">
        <i class="fa-solid fa-filter mr-1"></i> Apply
    </button>
    <?php if ($search !== '' || $course !== ''): ?>
        <a href="index.php" class="text-slate-500 hover:text-slate-900 text-sm">Clear</a>
    <?php endif; ?>
</form>

<?php if (!empty($dbError)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-6">
        <?= e($dbError) ?>
    </div>
<?php endif; ?>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="text-left px-4 py-3">When</th>
                    <th class="text-left px-4 py-3">Course</th>
                    <th class="text-left px-4 py-3">Name</th>
                    <th class="text-left px-4 py-3">Email</th>
                    <th class="text-left px-4 py-3">Phone</th>
                    <th class="text-left px-4 py-3">Level</th>
                    <th class="text-left px-4 py-3">Notes</th>
                    <th class="text-center px-4 py-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center py-12 text-slate-400">No registrations found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap"><?= e($r['created_at']) ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs font-bold
                              <?= $r['course'] === 'Flutter' ? 'bg-cyan-100 text-cyan-700' : 'bg-orange-100 text-orange-700' ?>">
                            <?= e($r['course']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td>
                    <td class="px-4 py-3"><a href="mailto:<?= e($r['email']) ?>" class="text-orange-600 hover:underline"><?= e($r['email']) ?></a></td>
                    <td class="px-4 py-3 whitespace-nowrap"><a href="tel:<?= e($r['phone']) ?>" class="hover:underline"><?= e($r['phone']) ?></a></td>
                    <td class="px-4 py-3"><?= e($r['level']) ?></td>
                    <td class="px-4 py-3 text-slate-600 max-w-xs">
                        <?php if ($r['description'] !== ''): ?>
                            <details>
                                <summary class="cursor-pointer text-slate-500 hover:text-slate-900">view</summary>
                                <p class="mt-2 text-xs whitespace-pre-wrap"><?= e($r['description']) ?></p>
                            </details>
                        <?php else: ?>
                            <span class="text-slate-300">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button type="button" data-id="<?= (int)$r['id'] ?>" class="delete-btn text-red-600 hover:text-red-900 hover:bg-red-50 px-2 py-1 rounded text-xs font-bold transition">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-6 text-sm">
    <span class="text-slate-500">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $total ?> result<?= $total === 1 ? '' : 's' ?></span>
    <div class="flex gap-2">
        <?php $qs = http_build_query(array_filter(['q' => $search, 'course' => $course])); ?>
        <?php if ($page > 1): ?>
            <a href="?<?= $qs ? $qs . '&' : '' ?>p=<?= $page - 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">&larr; Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= $qs ? $qs . '&' : '' ?>p=<?= $page + 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">Next &rarr;</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        if (!confirm('Are you sure you want to delete this registration?')) return;
        
        try {
            const response = await fetch('delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            
            const result = await response.json();
            if (result.status === 'success') {
                this.closest('tr').remove();
                alert('Registration deleted successfully.');
            } else {
                alert('Error: ' + (result.message || 'Could not delete registration'));
            }
        } catch (e) {
            alert('Network error: ' + e.message);
        }
    });
});
</script>

<?php pcadmin_foot();
