<?php
/** Continuous-monitoring alerts center. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Alerts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'read_all') { q('UPDATE alerts SET is_read = 1'); flash('success', 'All alerts marked read.'); }
    if ($act === 'read_one') { q('UPDATE alerts SET is_read = 1 WHERE id = ?', [(int)($_POST['alert_id'] ?? 0)]); }
    if ($act === 'clear_read' && can('manage_vendors')) { q('DELETE FROM alerts WHERE is_read = 1'); flash('success', 'Read alerts cleared.'); }
    redirect('alerts');
}

$sevF = in_array($_GET['sev'] ?? '', ['info','warning','critical'], true) ? $_GET['sev'] : '';
$where = []; $params = [];
if ($sevF) { $where[] = 'a.severity = ?'; $params[] = $sevF; }
if (($_GET['unread'] ?? '') === '1') $where[] = 'a.is_read = 0';
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pg = paginate("SELECT COUNT(*) FROM alerts a$w",
    "SELECT a.*, v.name vendor_name FROM alerts a LEFT JOIN vendors v ON v.id = a.vendor_id$w ORDER BY a.created_at DESC",
    $params, 30);
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Alerts</h1>
  <div class="spacer"></div>
  <form method="post" style="display:inline"><?= csrf_field() ?>
    <input type="hidden" name="_action" value="read_all"><button class="btn btn-outline btn-sm">Mark all read</button></form>
  <?php if (can('manage_vendors')): ?>
  <form method="post" style="display:inline" data-confirm="Delete all read alerts?"><?= csrf_field() ?>
    <input type="hidden" name="_action" value="clear_read"><button class="btn btn-ghost btn-sm">Clear read</button></form>
  <?php endif; ?>
</div>

<div class="card tight">
  <div style="display:flex;gap:.4rem;flex-wrap:wrap">
    <a class="btn btn-sm <?= !$sevF && ($_GET['unread'] ?? '') !== '1' ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e(url('alerts')) ?>">All</a>
    <a class="btn btn-sm <?= ($_GET['unread'] ?? '') === '1' ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e(url('alerts', ['unread' => 1])) ?>">Unread</a>
    <?php foreach (['critical','warning','info'] as $s): ?>
      <a class="btn btn-sm <?= $sevF === $s ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e(url('alerts', ['sev' => $s])) ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$pg['items']): ?>
  <div class="card empty-state"><div class="empty-icon">🔕</div><h3>No alerts</h3>
    <p class="muted">Alerts appear here from scans, expiry reminders, score drops and assessment activity.</p></div>
<?php endif; ?>
<?php foreach ($pg['items'] as $al): ?>
  <div class="card tight" style="<?= $al['is_read'] ? 'opacity:.55' : '' ?>">
    <div style="display:flex;gap:.8rem;align-items:center;flex-wrap:wrap">
      <span class="badge badge-<?= $al['severity'] === 'critical' ? 'critical' : ($al['severity'] === 'warning' ? 'high' : 'status') ?>"><?= e(ucfirst($al['severity'])) ?></span>
      <div style="flex:1">
        <?= e($al['message']) ?>
        <div class="small muted"><?= e(date('M j, Y H:i', strtotime($al['created_at']))) ?>
          · <?= e(str_replace('_', ' ', $al['type'])) ?>
          <?php if ($al['vendor_id']): ?> · <a href="<?= e(url('vendor_view', ['id' => $al['vendor_id']])) ?>"><?= e($al['vendor_name'] ?? 'vendor') ?></a><?php endif; ?>
        </div>
      </div>
      <?php if (!$al['is_read']): ?>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="_action" value="read_one"><input type="hidden" name="alert_id" value="<?= (int)$al['id'] ?>">
          <button class="btn btn-sm btn-ghost">✓ Read</button></form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?= pagination_links($pg, array_filter(['sev' => $sevF, 'unread' => $_GET['unread'] ?? ''])) ?>
<?php include __DIR__ . '/../partials/footer.php';
