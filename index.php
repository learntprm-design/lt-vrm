<?php
/**
 * VendorAssess 360 by LearnTPRM.com — Front controller
 * All requests route through here: index.php?p=<page>
 */
require __DIR__ . '/app/bootstrap.php';

$page = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_GET['p'] ?? 'dashboard')));

// Whitelisted routes → pages/<file>.php   (false = public, no login needed)
$routes = [
    'login'             => false,
    'logout'            => true,
    'accept_invite'     => false,
    'dashboard'         => true,
    'vendors'           => true,
    'vendor_add'        => true,
    'vendor_import'     => true,
    'vendor_view'       => true,
    'vendor_edit'       => true,
    'documents'         => true,
    'contracts'         => true,
    'assessments'       => true,
    'assessment_new'    => true,
    'assessment_review' => true,
    'templates'         => true,
    'template_edit'     => true,
    'risk_register'     => true,
    'issues'            => true,
    'offboarding'       => true,
    'alerts'            => true,
    'reports'           => true,
    'board_report'      => true,
    'calendar'          => true,
    'users'             => true,
    'settings'          => true,
    'audit'             => true,
    'search'            => true,
    'learn'             => true,
    'download'          => true,
    'export'            => true,
];

if (!array_key_exists($page, $routes)) {
    http_response_code(404);
    $page = '_404';
} elseif ($routes[$page] && !is_logged_in()) {
    redirect('login');
}

csrf_check();

$file = __DIR__ . '/pages/' . $page . '.php';
if (!is_file($file)) {
    http_response_code(404);
    $file = __DIR__ . '/pages/_404.php';
}
require $file;
