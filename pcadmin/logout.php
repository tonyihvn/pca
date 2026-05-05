<?php
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';

start_admin_session($config);
admin_logout();
