<?php
declare(strict_types=1);

/**
 * Bootstrap: loads config, prepares DB, registers helpers.
 * Included by index.php on every request.
 */

$root = __DIR__;

// Load config (fallback to config.example.php so the app still boots out of the box).
$configPath = $root . '/config.php';
if (!file_exists($configPath)) {
    $configPath = $root . '/config.example.php';
}
$CONFIG = require $configPath;

require_once $root . '/lib/db.php';
require_once $root . '/lib/http.php';
require_once $root . '/lib/auth.php';
require_once $root . '/lib/slug.php';
require_once $root . '/lib/cloudflare.php';

date_default_timezone_set('Asia/Dhaka');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Bootstrap schema + seed on first run.
db_init_schema($CONFIG);
db_seed_if_empty($CONFIG);
