<?php
/** Global search across vendors, documents, contracts, assessments, issues. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Search';
$qF = trim((string)($_GET['q'] ?? ''));
$res = ['vendors' => [], 'documents' => [], 'contracts' => [], 'assessments' => [], 'issues' => []];
if ($qF !== '') {
    $like = "%$qF%";
    $res['vendors'] = rows('SELECT id, name, tier, risk_score FROM vendors
        WHERE name LIKE ? OR domain LIKE ? OR industry LIKE ? OR description LIKE ? LIMIT 20', [$like, $like, $like, $like]);
    $res['documents'] = rows('SELECT d.id, d.title, d.category, v.name vn, v.id vid FROM documents d
        JOIN vendors v ON v.id = d.vendor_id WHERE d.title LIKE ? OR d.tags LIKE ? LIMIT 20', [$like, $like]);
    $res['contracts'] = rows('SELECT c.id, c.title, c.status, v.name vn, v.id vid FROM contracts c
        JOIN vendors v ON v.id = c.vendor_id WHERE c.title LIKE ? OR c.notes LIKE ? LIMIT 20', [$like, $like]);
    $res['assessments'] = rows('SELECT a.id, a.title, a.status, v.name vn FROM assessments a
        JOIN vendors v ON v.id = a.vendor_id WHERE a.title LIKE ? LIMIT 20', [$like]);
    $res['issues'] = rows('SELECT i.id, i.title, i.severity, i.status, v.name vn, v.id vid FROM issues i
        JOIN vendors v ON v.id = i.vendor_id WHERE i.title LIKE ? OR i.description LIKE ? LIMIT 20', [$like, $like]);
}
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar"><h1>Global search</h1></div>
<div class="card">
  <form method="get" action="index.php" style="display:flex;gap:.7rem">
    <input type="hidden" name="p" value="search">
    <input type="text" name="q" value="<?= e($qF) ?>" placeholder="Search vendors, documents, contracts, assessments, issues…" autofocus>
    <button class="btn btn-gold">Search</button>
  </form>
</div>
<?php if ($qF !== ''): ?>
  <div class="grid c2">
    <div class="card"><h2>Vendors (<?= count($res['vendors']) ?>)</h2>
      <?php foreach ($res['vendors'] as $r): ?>
        <p><a href="<?= e(url('vendor_view', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a>
          <?= tier_badge($r['tier']) ?> <?= score_badge((int)$r['risk_score']) ?></p>
      <?php endforeach; if (!$res['vendors']): ?><p class="muted small">No matches.</p><?php endif; ?></div>
    <div class="card"><h2>Assessments (<?= count($res['assessments']) ?>)</h2>
      <?php foreach ($res['assessments'] as $r): ?>
        <p><a href="<?= e(url('assessment_review', ['id' => $r['id']])) ?>"><?= e($r['title']) ?></a> <?= status_badge($r['status']) ?></p>
      <?php endforeach; if (!$res['assessments']): ?><p class="muted small">No matches.</p><?php endif; ?></div>
    <div class="card"><h2>Documents (<?= count($res['documents']) ?>)</h2>
      <?php foreach ($res['documents'] as $r): ?>
        <p><a href="<?= e(url('vendor_view', ['id' => $r['vid'], 'tab' => 'documents'])) ?>"><?= e($r['title']) ?></a>
          <span class="badge badge-status"><?= e($r['category']) ?></span> <span class="muted small"><?= e($r['vn']) ?></span></p>
      <?php endforeach; if (!$res['documents']): ?><p class="muted small">No matches.</p><?php endif; ?></div>
    <div class="card"><h2>Contracts (<?= count($res['contracts']) ?>)</h2>
      <?php foreach ($res['contracts'] as $r): ?>
        <p><a href="<?= e(url('vendor_view', ['id' => $r['vid'], 'tab' => 'contracts'])) ?>"><?= e($r['title']) ?></a>
          <?= status_badge($r['status']) ?> <span class="muted small"><?= e($r['vn']) ?></span></p>
      <?php endforeach; if (!$res['contracts']): ?><p class="muted small">No matches.</p><?php endif; ?></div>
    <div class="card"><h2>Issues (<?= count($res['issues']) ?>)</h2>
      <?php foreach ($res['issues'] as $r): ?>
        <p><a href="<?= e(url('issues', ['vendor' => $r['vid']])) ?>"><?= e($r['title']) ?></a>
          <?= tier_badge($r['severity']) ?> <?= status_badge($r['status']) ?> <span class="muted small"><?= e($r['vn']) ?></span></p>
      <?php endforeach; if (!$res['issues']): ?><p class="muted small">No matches.</p><?php endif; ?></div>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php';
