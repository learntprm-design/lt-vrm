<?php
/** Issues & remediation tracking with SLAs. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Issues & Remediation';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('manage_issues')) {
    $act = $_POST['_action'] ?? '';
    if ($act === 'create') {
        $vid = (int)($_POST['vendor_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        if (!$vid || $title === '') { flash('error', 'Vendor and title are required.'); redirect('issues'); }
        q('INSERT INTO issues (vendor_id, title, description, severity, status, sla_due, remediation_plan, owner_user_id, source)
           VALUES (?,?,?,?,?,?,?,?,?)',
          [$vid, $title, trim((string)($_POST['description'] ?? '')) ?: null,
           in_array($_POST['severity'] ?? '', ['low','medium','high','critical'], true) ? $_POST['severity'] : 'medium',
           'open', trim((string)($_POST['sla_due'] ?? '')) ?: null,
           trim((string)($_POST['remediation_plan'] ?? '')) ?: null, current_user()['id'], 'manual']);
        audit('issue_created', 'issue', (int)db()->lastInsertId(), $title);
        recompute_risk($vid);
        flash('success', 'Issue raised.');
        redirect('issues');
    }
    if ($act === 'status') {
        $iid = (int)($_POST['issue_id'] ?? 0);
        $st = $_POST['status'] ?? '';
        $i = row('SELECT * FROM issues WHERE id = ?', [$iid]);
        if ($i && in_array($st, ['open','in_remediation','resolved','accepted','overdue'], true)) {
            q('UPDATE issues SET status = ?, resolved_at = ? WHERE id = ?',
              [$st, in_array($st, ['resolved','accepted'], true) ? date('Y-m-d H:i:s') : null, $iid]);
            audit('issue_status', 'issue', $iid, $st);
            recompute_risk((int)$i['vendor_id']);
            flash('success', 'Issue updated.');
        }
        redirect('issues');
    }
    if ($act === 'mark_overdue') {
        $n = q("UPDATE issues SET status = 'overdue' WHERE sla_due < CURDATE() AND status IN ('open','in_remediation')")->rowCount();
        flash('success', $n . ' issue(s) flagged overdue against their SLA.');
        redirect('issues');
    }
}

$stF = in_array($_GET['status'] ?? '', ['open','in_remediation','resolved','accepted','overdue'], true) ? $_GET['status'] : '';
$vidF = (int)($_GET['vendor'] ?? 0);
$where = []; $params = [];
if ($stF) { $where[] = 'i.status = ?'; $params[] = $stF; }
if ($vidF) { $where[] = 'i.vendor_id = ?'; $params[] = $vidF; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pg = paginate("SELECT COUNT(*) FROM issues i$w",
    "SELECT i.*, v.name vendor_name FROM issues i JOIN vendors v ON v.id = i.vendor_id$w
     ORDER BY FIELD(i.status,'overdue','open','in_remediation','accepted','resolved'),
              FIELD(i.severity,'critical','high','medium','low'), i.sla_due", $params, 25);
$vendorOpts = rows('SELECT id, name FROM vendors ORDER BY name LIMIT 1100');
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Issues &amp; Remediation</h1>
  <div class="spacer"></div>
  <?php if (can('manage_issues')): ?>
    <form method="post" style="display:inline"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="mark_overdue">
      <button class="btn btn-outline btn-sm">⏰ Flag SLA breaches</button></form>
    <button class="btn btn-gold" data-open-modal="issue-modal">+ Raise issue</button>
  <?php endif; ?>
</div>

<div class="card tight">
  <div style="display:flex;gap:.4rem;flex-wrap:wrap">
    <a class="btn btn-sm <?= !$stF ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e(url('issues')) ?>">All</a>
    <?php foreach (['overdue','open','in_remediation','accepted','resolved'] as $s): ?>
      <a class="btn btn-sm <?= $stF === $s ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e(url('issues', ['status' => $s])) ?>"><?= ucwords(str_replace('_',' ',$s)) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Issue</th><th>Vendor</th><th>Severity</th><th>Status</th><th>SLA due<?= hint('sla') ?></th><th>Source</th><th></th></tr></thead>
    <tbody>
    <?php if (!$pg['items']): ?><tr><td colspan="7" class="muted">No issues — a clean queue. 🧹</td></tr><?php endif; ?>
    <?php foreach ($pg['items'] as $i): $du = days_until($i['sla_due']); ?>
      <tr>
        <td><strong><?= e($i['title']) ?></strong>
          <?php if ($i['description']): ?><div class="small muted"><?= e(mb_strimwidth($i['description'], 0, 110, '…')) ?></div><?php endif; ?>
          <?php if ($i['remediation_plan']): ?><div class="small" style="color:var(--blue)">Plan: <?= e(mb_strimwidth($i['remediation_plan'], 0, 110, '…')) ?></div><?php endif; ?></td>
        <td><a href="<?= e(url('vendor_view', ['id' => $i['vendor_id']])) ?>"><?= e($i['vendor_name']) ?></a></td>
        <td><?= tier_badge($i['severity']) ?></td>
        <td><?= status_badge($i['status']) ?></td>
        <td><?= fmt_date($i['sla_due']) ?>
          <?php if ($du !== null && $du < 0 && !in_array($i['status'], ['resolved','accepted'], true)): ?>
            <span class="badge badge-critical"><?= abs($du) ?>d over</span><?php endif; ?></td>
        <td class="muted small"><?= e($i['source']) ?></td>
        <td>
          <?php if (can('manage_issues')): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="status"><input type="hidden" name="issue_id" value="<?= (int)$i['id'] ?>">
            <select name="status" onchange="this.form.submit()" style="width:auto;padding:.3rem .5rem">
              <?php foreach (['open','in_remediation','resolved','accepted','overdue'] as $s): ?>
                <option value="<?= $s ?>" <?= $i['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?></select>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?= pagination_links($pg, array_filter(['status' => $stF, 'vendor' => $vidF ?: ''], fn($x) => $x !== '')) ?>
</div>

<div class="modal-back" id="issue-modal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0">Raise an issue</h2><button class="btn btn-ghost btn-sm" data-close-modal>✕</button></div>
    <form method="post" action="<?= e(url('issues')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="create">
      <label>Vendor *</label>
      <select name="vendor_id" required><option value="">— choose —</option>
        <?php foreach ($vendorOpts as $vo): ?><option value="<?= (int)$vo['id'] ?>" <?= $vidF === (int)$vo['id'] ? 'selected' : '' ?>><?= e($vo['name']) ?></option><?php endforeach; ?></select>
      <label>Title *</label><input type="text" name="title" required>
      <label>Description</label><textarea name="description"></textarea>
      <div class="form-row">
        <div><label>Severity</label><select name="severity"><option>medium</option><option>critical</option><option>high</option><option>low</option></select></div>
        <div><label>SLA due date</label><input type="date" name="sla_due" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
      </div>
      <label>Remediation plan</label><textarea name="remediation_plan"></textarea>
      <button class="btn btn-gold" style="margin-top:1rem">⚑ Raise issue</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php';
