<?php
/** Audit trail — every significant action, who, when, from where. */
require_perm('*');
$GLOBALS['VA_PAGE_TITLE'] = 'Audit Trail';

$qF = trim((string)($_GET['q'] ?? ''));
$where = []; $params = [];
if ($qF !== '') { $where[] = '(action LIKE ? OR user_name LIKE ? OR detail LIKE ?)'; array_push($params, "%$qF%", "%$qF%", "%$qF%"); }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pg = paginate("SELECT COUNT(*) FROM audit_log$w",
               "SELECT * FROM audit_log$w ORDER BY created_at DESC", $params, 50);
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Audit Trail <span class="muted" style="font-size:1rem">(<?= (int)$pg['total'] ?> events)</span><?= hint('audit_trail') ?></h1>
  <div class="spacer"></div>
  <a class="btn btn-ghost" href="<?= e(url('export', ['what' => 'audit'])) ?>">Export CSV</a>
</div>
<div class="card tight">
  <form method="get" action="index.php" style="display:flex;gap:.7rem;align-items:end">
    <input type="hidden" name="p" value="audit">
    <div style="flex:1"><label>Search</label><input type="text" name="q" value="<?= e($qF) ?>" placeholder="Action, user or detail…"></div>
    <button class="btn btn-gold">Search</button>
    <a class="btn btn-ghost" href="<?= e(url('audit')) ?>">Reset</a>
  </form>
</div>
<div class="card">
  <div class="table-wrap"><table class="data">
    <thead><tr><th>When (UTC)</th><th>User</th><th>Action</th><th>Entity</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php if (!$pg['items']): ?><tr><td colspan="6" class="muted">No events match.</td></tr><?php endif; ?>
    <?php foreach ($pg['items'] as $a): ?>
      <tr>
        <td class="muted small" style="white-space:nowrap"><?= e(date('M j, Y H:i:s', strtotime($a['created_at']))) ?></td>
        <td><?= e($a['user_name'] ?? 'System') ?></td>
        <td><span class="badge badge-status"><?= e(str_replace('_', ' ', $a['action'])) ?></span></td>
        <td class="muted"><?= e($a['entity'] ?? '—') ?><?= $a['entity_id'] ? ' #' . (int)$a['entity_id'] : '' ?></td>
        <td class="muted small"><?= e($a['detail'] ?? '') ?></td>
        <td class="muted small"><?= e($a['ip'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?= pagination_links($pg, array_filter(['q' => $qF])) ?>
</div>
<?php include __DIR__ . '/../partials/footer.php';
