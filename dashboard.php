<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/charts.php';
$user = require_login();

$pageTitle = ROLES[$user['role']]['portal'];
$activeNav = 'dashboard';
include __DIR__ . '/includes/app_top.php';

match ($user['role']) {
    'admin'      => include __DIR__ . '/includes/dashboards/admin.php',
    'technician' => include __DIR__ . '/includes/dashboards/technician.php',
    default      => include __DIR__ . '/includes/dashboards/requester.php',
};

include __DIR__ . '/includes/app_bottom.php';
