<?php
/** Vendor inventory — paginated, filterable, built for 1,000+ records. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Vendors';

$tierF   = in_array($_GET['tier'] ?? '', ['critical','high','medium','low'], true) ? $_GET['tier'] : '';
$lifeF   = in_array($_GET['lifecycle'] ?? '', ['onboarding','active','under_review','offboarding','terminated'], true) ? $_GET['lifecycle'] : '';
$qF      = trim((string)($_GET['q'] ?? ''));
$sort    = $_GET['sort'] ?? 'name';
$sortMap = ['name' => 'name ASC', 'score' => 'risk_score ASC', 'score_desc' => 'risk_score DESC', 'tier' => "FIELD(tier,'critical','high','medium','low')", 'newest' => 'created_at DESC'];
$orderBy = $sortMap[$sort] ?? 'name ASC';

$where = []; $params = [];
if ($tierF) { $where[] = 'tier = ?'; $params[] = $tierF; }
if ($lifeF) { $where[] = 'lifecycle = ?'; $params[] = $lifeF; }
if ($qF !== '') { $where[] = '(name LIKE ? OR domain LIKE ? OR industry LIKE ?)'; array_push($params, "%$qF%", "%$qF%", "%$qF%"); }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$pg = paginate("SELECT COUNT(*) FROM vendors$w",
               "SELECT * FROM vendors$w ORDER BY $orderBy", $params, 25);
$keep = array_filter(['tier' => $tierF, 'lifecycle' => $lifeF, 'q' => $qF, 'sort' => $sort], fn($v) => $v !== '');

include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Vendors <span class="muted" style="font-size:1rem">(<?= (int)$pg['total'] ?>)</span></h1>
  <div class="spacer"></div>
  <?php if (can('manage_vendors')): ?>
    <a class="btn btn-outline" href="<?= e(url('vendor_import')) ?>">⇪ Bulk upload</a>
    <a class="btn btn-gold" href="<?= e(url('vendor_add')) ?>">+ Add vendor</a>
  <?php endif; ?>
  <a class="btn btn-ghost" href="<?= e(url('export', ['what' => 'vendors'])) ?>">Export CSV</a>
</div>

<div class="card tight">
  <form method="get" action="index.php" style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="p" value="vendors">
    <div style="flex:1;min-width:200px"><label>Search</label>
      <input type="text" name="q" value="<?= e($qF) ?>" placeholder="Name, domain or industry…"></div>
    <div><label>Tier</label>
      <select name="tier"><option value="">All tiers</option>
      <?php foreach (['critical','high','medium','low'] as $t): ?>
        <option value="<?= $t ?>" <?= $tierF === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?></select></div>
    <div><label>Lifecycle</label>
      <select name="lifecycle"><option value="">All stages</option>
      <?php foreach (['onboarding','active','under_review','offboarding','terminated'] as $l): ?>
        <option value="<?= $l ?>" <?= $lifeF === $l ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$l)) ?></option>
      <?php endforeach; ?></select></div>
    <div><label>Sort by</label>
      <select name="sort">
        <option value="name" <?= $sort==='name'?'selected':'' ?>>Name A–Z</option>
        <option value="score" <?= $sort==='score'?'selected':'' ?>>Riskiest first</option>
        <option value="score_desc" <?= $sort==='score_desc'?'selected':'' ?>>Safest first</option>
        <option value="tier" <?= $sort==='tier'?'selected':'' ?>>Tier</option>
        <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest</option>
      </select></div>
    <button class="btn btn-gold">Filter</button>
    <a class="btn btn-ghost" href="<?= e(url('vendors')) ?>">Reset</a>
  </form>
</div>

<div class="card">
<?php if (!$pg['items']): ?>
  <div class="empty-state"><div class="empty-icon">🗂️</div>
    <h3>No vendors found</h3>
    <p class="muted">Try clearing filters, or add your first vendor.</p>
    <?php if (can('manage_vendors')): ?><a class="btn btn-gold" href="<?= e(url('vendor_add')) ?>">+ Add vendor</a><?php endif; ?>
  </div>
<?php else: ?>
  <div class="table-wrap">
  <table class="data">
    <thead><tr>
      <th>Vendor</th><th>Tier<?= hint('tier') ?></th><th>Risk score<?= hint('risk_score') ?></th><th>Lifecycle<?= hint('lifecycle') ?></th>
      <th>Industry</th><th>Country</th><th>Next review</th>
    </tr></thead>
    <tbody>
    <?php foreach ($pg['items'] as $v): ?>
      <tr>
        <td><a class="rowlink" href="<?= e(url('vendor_view', ['id' => $v['id']])) ?>"><?= e($v['name']) ?></a>
            <div class="small muted"><?= e($v['domain'] ?? '') ?></div></td>
        <td><?= tier_badge($v['tier']) ?></td>
        <td><?= score_badge((int)$v['risk_score']) ?></td>
        <td><?= status_badge($v['lifecycle']) ?></td>
        <td class="muted"><?= e($v['industry'] ?? '—') ?></td>
        <td class="muted"><?= e($v['country'] ?? '—') ?></td>
        <td class="muted"><?= fmt_date($v['next_review_date']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= pagination_links($pg, $keep) ?>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php';
