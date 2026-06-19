<?php
/**
 * VendorAssess 360 — Bootstrap
 * Loads config, opens the session securely, registers a friendly error handler.
 */

declare(strict_types=1);

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    // Not installed yet → send to the installer.
    header('Location: install/index.php');
    exit;
}
$GLOBALS['VA_CONFIG'] = require $configFile;

error_reporting(E_ALL);
ini_set('display_errors', !empty($GLOBALS['VA_CONFIG']['debug']) ? '1' : '0');
ini_set('log_errors', '1');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_name('VA360SESS');
session_start();

require __DIR__ . '/helpers.php';
require __DIR__ . '/risk.php';
require __DIR__ . '/connectors.php';
require __DIR__ . '/hints.php';

date_default_timezone_set('UTC');

/** Friendly fatal-error page (production). */
set_exception_handler(function (Throwable $e) {
    error_log('VendorAssess360: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!empty($GLOBALS['VA_CONFIG']['debug'])) {
        http_response_code(500);
        echo '<pre style="padding:2rem;color:#c00">' . htmlspecialchars((string)$e) . '</pre>';
        exit;
    }
    http_response_code(500);
    echo '<!doctype html><html><head><title>Something went wrong</title>
    <style>body{font-family:system-ui,sans-serif;background:#0a0e17;color:#e6ecf5;display:grid;place-items:center;min-height:100vh;margin:0}
    .box{max-width:480px;text-align:center;padding:2rem}.box h1{color:#eec05c}</style></head><body>
    <div class="box"><h1>Something went wrong</h1>
    <p>The error has been logged. Try going back, or check that MySQL is running in the XAMPP Control Panel.</p>
    <p><a style="color:#eec05c" href="index.php">&larr; Back to dashboard</a></p></div></body></html>';
    exit;
});
