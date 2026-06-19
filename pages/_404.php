<?php
$GLOBALS['VA_PAGE_TITLE'] = 'Page not found';
if (!is_logged_in()) { redirect('login'); }
include __DIR__ . '/../partials/header.php';
?>
<div class="card empty-state">
  <div class="empty-icon">🧭</div>
  <h2>Page not found</h2>
  <p class="muted">The page you requested doesn't exist.</p>
  <p><a class="btn btn-gold" href="<?= e(url('dashboard')) ?>">Back to dashboard</a></p>
</div>
<?php include __DIR__ . '/../partials/footer.php';
