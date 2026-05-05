<?php
declare(strict_types=1);

/**
 * Shared layout + helpers for /pcadmin/ pages.
 * Included by every page AFTER the login check (or by login.php itself).
 */

require_once __DIR__ . '/../includes/auth.php';

function pcadmin_head(string $title): void
{
    $loggedIn = admin_is_logged_in();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> | Academy Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 text-slate-900 font-sans min-h-screen flex flex-col">
<?php if ($loggedIn): ?>
<nav class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-8">
        <span class="font-mono font-bold text-lg">
            <span class="text-orange-500">public class</span> Admin {}
        </span>
        <a href="index.php" class="text-sm hover:text-orange-400 <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'text-orange-400' : '' ?>">
            <i class="fa-solid fa-graduation-cap mr-1"></i> Registrations
        </a>
        <a href="contacts.php" class="text-sm hover:text-orange-400 <?= basename($_SERVER['PHP_SELF']) === 'contacts.php' ? 'text-orange-400' : '' ?>">
            <i class="fa-solid fa-envelope mr-1"></i> Contacts
        </a>
    </div>
    <div class="flex items-center gap-4 text-sm">
        <span class="text-slate-400">Signed in as <strong class="text-white"><?= e($_SESSION['admin_user'] ?? '') ?></strong></span>
        <a href="logout.php" class="bg-slate-800 hover:bg-red-600 transition px-3 py-1.5 rounded">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>
<?php endif; ?>
<main class="flex-1 px-6 py-8 max-w-7xl mx-auto w-full">
    <?php
}

function pcadmin_foot(): void
{
    ?>
</main>
<footer class="text-center text-xs text-slate-500 py-6">
    Public Class Academy &middot; Admin Console
</footer>
</body>
</html>
    <?php
}
