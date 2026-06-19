<?php
/** Shared layout header: <head>, sidebar nav, opening of main column. */
$pageTitle = $GLOBALS['VA_PAGE_TITLE'] ?? 'LT-VRM';
$active    = $_GET['p'] ?? 'dashboard';
$unreadAlerts = 0;
if (is_logged_in()) {
    try { $unreadAlerts = (int)scalar('SELECT COUNT(*) FROM alerts WHERE is_read = 0'); } catch (Throwable $e) {}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · LT-VRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230d1322'/><text x='16' y='22' font-size='16' text-anchor='middle' fill='%23eec05c' font-family='sans-serif' font-weight='bold'>V</text></svg>">
</head>
<body>
<div class="app">
<?php if (is_logged_in()): ?>
  <aside class="sidebar">
    <a class="brand" href="<?= e(url('dashboard')) ?>">
      <span class="logo">Vendor<span class="tm">Assess</span> 360</span>
      <div class="tagline">by LearnTPRM.com</div>
    </a>
    <?php
    $nav = [
      'PROGRAM' => [
        ['dashboard', '◈', 'Dashboard'],
        ['search', '🔍', 'Search'],
        ['vendors', '▣', 'Vendors'],
        ['alerts', '◉', 'Alerts', $unreadAlerts],
        ['calendar', '▦', 'Calendar'],
        ['reports', '◫', 'Reports'],
      ],
      'DUE DILIGENCE' => [
        ['assessments', '✎', 'Assessments'],
        ['templates', '☰', 'Questionnaire Templates'],
        ['documents', '⛁', 'Documents Library'],
        ['contracts', '✍', 'Contracts'],
      ],
      'RISK' => [
        ['risk_register', '⚖', 'Risk Register'],
        ['issues', '⚑', 'Issues & Remediation'],
        ['offboarding', '⏏', 'Offboarding'],
      ],
      'GROW' => [
        ['learn', '🎓', 'Learn TPRM'],
        ['https://learntprm.com', '⚔', 'TPRM Warrior Cert ↗'],
        ['https://learntprm.com/jobs', '💼', 'TPRM Jobs ↗'],
      ],
      'ADMIN' => [
        ['users', '👤', 'Users & Access'],
        ['settings', '⚙', 'Settings'],
        ['audit', '🗒', 'Audit Trail'],
      ],
    ];
    foreach ($nav as $section => $links) {
        if ($section === 'ADMIN' && !can('*')) continue;
        echo '<div class="nav-section">' . e($section) . '</div>';
        foreach ($links as $l) {
            $external = str_starts_with($l[0], 'http');
            $href = $external ? $l[0] : url($l[0]);
            $cls = !$external && $active === $l[0] ? 'nav-link active' : 'nav-link';
            echo '<a class="' . $cls . '" href="' . e($href) . '"' . ($external ? ' target="_blank" rel="noopener"' : '') . '>'
               . '<span class="ico">' . $l[1] . '</span>' . e($l[2]);
            if (!empty($l[3])) echo '<span class="nav-badge">' . (int)$l[3] . '</span>';
            echo '</a>';
        }
    }
    ?>
    <div style="margin-top:auto;padding:.9rem .55rem .3rem">
      <div class="small muted"><?= e(current_user()['name']) ?><br>
        <span class="pill" style="font-size:.68rem;margin-top:.25rem;display:inline-block"><?= e(ucfirst(current_user()['role'])) ?></span>
      </div>
      <a class="nav-link" style="margin-top:.5rem" href="<?= e(url('logout')) ?>"><span class="ico">⎋</span>Sign out</a>
      <div class="small muted" style="padding:.6rem .55rem 0;font-size:.68rem">
        Developed by <a href="https://learntprm.com" target="_blank" rel="noopener">LearnTPRM.com</a>
      </div>
    </div>
  </aside>
<?php endif; ?>
  <main class="main">
    <?php foreach (flashes() as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>" data-auto><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <?php if (is_logged_in()) echo page_hint_strip($active); ?>
