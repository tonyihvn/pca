<?php
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/_layout.php';

start_admin_session($config);
admin_require_login('login.php');

$search   = trim((string)($_GET['q']    ?? ''));
$inquiry  = trim((string)($_GET['type'] ?? ''));
$page     = max(1, (int)($_GET['p']     ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($search !== '') {
    $where[]      = '(name LIKE :q OR email LIKE :q OR message LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
if ($inquiry !== '') {
    $where[]            = 'inquiry_type = :type';
    $params[':type']    = $inquiry;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $pdo = db($config);

    $total = (int)$pdo->prepare("SELECT COUNT(*) FROM contacts $whereSql")
        ->execute($params) ? 0 : 0; // placeholder
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM contacts $whereSql");
    $totalStmt->execute($params);
    $total = (int)$totalStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, name, email, phone, inquiry_type, message, ip, created_at
                           FROM contacts
                           $whereSql
                           ORDER BY created_at DESC
                           LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $todayCount = (int)$pdo->query("SELECT COUNT(*) FROM contacts WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $allCount   = (int)$pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
} catch (Throwable $e) {
    error_log('[pcadmin/contacts] ' . $e->getMessage());
    $rows = [];
    $total = 0;
    $todayCount = 0;
    $allCount = 0;
    $dbError = 'Database unavailable. Check server logs.';
}

$totalPages = max(1, (int)ceil($total / $perPage));

$inquiryTypes = [
    'Java Academy Admissions',
    'Flutter Academy Admissions',
    'Payment & Installments',
    'Corporate Training',
    'Other',
];

pcadmin_head('Contact Messages');
?>
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-3xl font-extrabold">Contact Messages</h1>
        <p class="text-slate-500 text-sm">Submissions from the contact form.</p>
    </div>
</div>

<div class="grid grid-cols-2 gap-4 mb-8 max-w-md">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="text-xs uppercase tracking-widest text-slate-500 font-bold">Total</div>
        <div class="text-3xl font-extrabold mt-1"><?= (int)$allCount ?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
        <div class="text-xs uppercase tracking-widest text-slate-500 font-bold">Today</div>
        <div class="text-3xl font-extrabold mt-1"><?= (int)$todayCount ?></div>
    </div>
</div>

<form method="get" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex flex-wrap gap-3 items-end mb-6">
    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Search</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name, email or message"
               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
    </div>
    <div>
        <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Type</label>
        <select name="type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option value="">All</option>
            <?php foreach ($inquiryTypes as $t): ?>
                <option value="<?= e($t) ?>" <?= $inquiry === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="bg-slate-900 text-white px-4 py-2 rounded-lg font-bold hover:bg-orange-600 transition">
        <i class="fa-solid fa-filter mr-1"></i> Apply
    </button>
    <?php if ($search !== '' || $inquiry !== ''): ?>
        <a href="contacts.php" class="text-slate-500 hover:text-slate-900 text-sm">Clear</a>
    <?php endif; ?>
</form>

<?php if (!empty($dbError)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-6">
        <?= e($dbError) ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="text-left px-4 py-3">When</th>
                    <th class="text-left px-4 py-3">Name</th>
                    <th class="text-left px-4 py-3">Email</th>
                    <th class="text-left px-4 py-3">Phone</th>
                    <th class="text-left px-4 py-3">Inquiry</th>
                    <th class="text-left px-4 py-3">Message</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center py-12 text-slate-400">No messages found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap"><?= e($r['created_at']) ?></td>
                    <td class="px-4 py-3 font-medium"><?= e($r['name']) ?></td>
                    <td class="px-4 py-3"><a href="mailto:<?= e($r['email']) ?>" class="text-orange-600 hover:underline"><?= e($r['email']) ?></a></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?= e($r['phone'] ?? '') ?: '<span class="text-slate-300">&mdash;</span>' ?></td>
                    <td class="px-4 py-3"><?= e($r['inquiry_type']) ?></td>
                    <td class="px-4 py-3 text-slate-600 max-w-md">
                        <details>
                            <summary class="cursor-pointer text-slate-500 hover:text-slate-900">view</summary>
                            <p class="mt-2 text-xs whitespace-pre-wrap"><?= e($r['message']) ?></p>
                        </details>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-6 text-sm">
    <span class="text-slate-500">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $total ?> result<?= $total === 1 ? '' : 's' ?></span>
    <div class="flex gap-2">
        <?php $qs = http_build_query(array_filter(['q' => $search, 'type' => $inquiry])); ?>
        <?php if ($page > 1): ?>
            <a href="?<?= $qs ? $qs . '&' : '' ?>p=<?= $page - 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">&larr; Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= $qs ? $qs . '&' : '' ?>p=<?= $page + 1 ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">Next &rarr;</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php pcadmin_foot();
