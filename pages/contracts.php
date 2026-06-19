<?php
/** Contract management — terms, clauses, expiry engine with reminders. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Contracts';
$TYPES = ['MSA','SOW','DPA','NDA','SLA','License','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('manage_contracts')) {
    $act = $_POST['_action'] ?? '';
    if ($act === 'create' || $act === 'update') {
        try {
            $cid = (int)($_POST['contract_id'] ?? 0);
            $vid = (int)($_POST['vendor_id'] ?? 0);
            if (!row('SELECT id FROM vendors WHERE id = ?', [$vid])) throw new RuntimeException('Choose a vendor.');
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') throw new RuntimeException('Contract title is required.');
            $type = in_array($_POST['contract_type'] ?? '', $TYPES, true) ? $_POST['contract_type'] : 'Other';
            $start = trim((string)($_POST['start_date'] ?? '')) ?: null;
            $end   = trim((string)($_POST['end_date'] ?? '')) ?: null;
            if ($start && $end && strtotime($end) < strtotime($start)) throw new RuntimeException('End date is before start date.');
            $value = $_POST['value_amount'] !== '' ? (float)$_POST['value_amount'] : null;
            $status = in_array($_POST['status'] ?? '', ['draft','active','expiring','expired','terminated'], true) ? $_POST['status'] : 'active';
            $up = handle_upload('file');
            $fields = [$vid, $title, $type, $value, strtoupper(substr(trim((string)($_POST['currency'] ?? 'USD')), 0, 8)) ?: 'USD',
                $start, $end, !empty($_POST['auto_renew']) ? 1 : 0,
                $_POST['notice_period_days'] !== '' ? (int)$_POST['notice_period_days'] : null,
                !empty($_POST['clause_right_to_audit']) ? 1 : 0, !empty($_POST['clause_data_protection']) ? 1 : 0,
                !empty($_POST['clause_termination']) ? 1 : 0, !empty($_POST['clause_sla']) ? 1 : 0,
                $status, trim((string)($_POST['notes'] ?? '')) ?: null];
            if ($act === 'create') {
                q('INSERT INTO contracts (vendor_id, title, contract_type, value_amount, currency, start_date, end_date,
                   auto_renew, notice_period_days, clause_right_to_audit, clause_data_protection, clause_termination,
                   clause_sla, status, notes, filename, orig_name, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                  array_merge($fields, [$up['stored'] ?? null, $up['orig'] ?? null, current_user()['id']]));
                audit('contract_created', 'contract', (int)db()->lastInsertId(), $title);
                flash('success', 'Contract added.');
            } else {
                $old = row('SELECT * FROM contracts WHERE id = ?', [$cid]);
                if (!$old) throw new RuntimeException('Contract not found.');
                $sql = 'UPDATE contracts SET vendor_id=?, title=?, contract_type=?, value_amount=?, currency=?,
                        start_date=?, end_date=?, auto_renew=?, notice_period_days=?, clause_right_to_audit=?,
                        clause_data_protection=?, clause_termination=?, clause_sla=?, status=?, notes=?';
                $p = $fields;
                if ($up) { $sql .= ', filename=?, orig_name=?'; $p[] = $up['stored']; $p[] = $up['orig'];
                           if ($old['filename']) @unlink(__DIR__ . '/../uploads/' . $old['filename']); }
                $sql .= ' WHERE id=?'; $p[] = $cid;
                q($sql, $p);
                audit('contract_updated', 'contract', $cid, $title);
                flash('success', 'Contract updated.');
            }
            // Expert check: a vendor touching personal/health/card data should have
            // a data-protection clause. Allowed without it, but never silently.
            $da = (string)scalar('SELECT data_accessed FROM vendors WHERE id = ?', [$vid]);
            if (preg_match('/PII|PHI|PCI/i', $da) && empty($_POST['clause_data_protection'])) {
                flash('info', '⚠ TPRM heads-up: this vendor handles ' . $da . ' but no data-protection clause is ticked. '
                    . 'If the signed contract truly lacks one, raise it at the next renewal — the gap is lowering their compliance score.');
            }
            recompute_risk($vid);
        } catch (RuntimeException $ex) { flash('error', $ex->getMessage()); }
        redirect('contracts');
    }
    if ($act === 'delete') {
        $c = row('SELECT * FROM contracts WHERE id = ?', [(int)($_POST['contract_id'] ?? 0)]);
        if ($c) {
            if ($c['filename']) @unlink(__DIR__ . '/../uploads/' . $c['filename']);
            q('DELETE FROM contracts WHERE id = ?', [$c['id']]);
            audit('contract_deleted', 'contract', (int)$c['id'], $c['title']);
            flash('success', 'Contract deleted.');
        }
        redirect('contracts');
    }
    if ($act === 'send_reminders') {
        // Expiry engine: refresh statuses + create alerts (+emails if SMTP configured)
        q("UPDATE contracts SET status = 'expired' WHERE end_date < CURDATE() AND status IN ('active','expiring')");
        q("UPDATE contracts SET status = 'expiring' WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND status = 'active'");
        $thresholds = array_map('intval', explode(',', (string)setting('reminder_days', '90,60,30')));
        $soon = rows('SELECT c.*, v.name vendor_name FROM contracts c JOIN vendors v ON v.id = c.vendor_id
                      WHERE c.end_date >= CURDATE() AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                      AND c.status IN ("active","expiring")', [max($thresholds ?: [90])]);
        $sentMail = 0;
        foreach ($soon as $c) {
            $du = days_until($c['end_date']);
            add_alert((int)$c['vendor_id'], 'contract_expiry',
                'Contract "' . $c['title'] . '" (' . $c['vendor_name'] . ') expires in ' . $du . ' day(s) on ' . fmt_date($c['end_date']),
                $du <= 30 ? 'critical' : 'warning');
            if (send_mail(current_user()['email'], 'Contract expiry: ' . $c['title'],
                '<p>The contract <strong>' . e($c['title']) . '</strong> with <strong>' . e($c['vendor_name']) .
                '</strong> expires on ' . e(fmt_date($c['end_date'])) . ' (' . (int)$du . ' days).</p>')) $sentMail++;
        }
        // One reminders engine for the whole program — not just contracts:
        // expiring documents, assessment deadlines, and overdue periodic reviews.
        $nDocs = 0;
        foreach (rows('SELECT d.title, d.expiry_date, d.vendor_id, v.name vn FROM documents d
                       JOIN vendors v ON v.id = d.vendor_id
                       WHERE d.expiry_date IS NOT NULL AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                       AND v.lifecycle <> "terminated"') as $d2) {
            $du = days_until($d2['expiry_date']);
            add_alert((int)$d2['vendor_id'], 'doc_expiry',
                'Document "' . $d2['title'] . '" (' . $d2['vn'] . ') ' . ($du < 0 ? 'EXPIRED ' . abs($du) . ' day(s) ago' : 'expires in ' . $du . ' day(s)'),
                $du < 0 ? 'critical' : 'warning');
            $nDocs++;
        }
        $nAs = 0;
        foreach (rows("SELECT a.title, a.due_date, a.vendor_id, v.name vn FROM assessments a
                       JOIN vendors v ON v.id = a.vendor_id
                       WHERE a.due_date IS NOT NULL AND a.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                       AND a.status NOT IN ('approved','rejected','draft')") as $a2) {
            $du = days_until($a2['due_date']);
            add_alert((int)$a2['vendor_id'], 'assessment_due',
                'Assessment "' . $a2['title'] . '" (' . $a2['vn'] . ') is ' . ($du < 0 ? abs($du) . ' day(s) OVERDUE' : 'due in ' . $du . ' day(s)'),
                $du < 0 ? 'critical' : 'warning');
            $nAs++;
        }
        $nRev = 0;
        foreach (rows('SELECT id, name, next_review_date FROM vendors
                       WHERE next_review_date < CURDATE() AND lifecycle NOT IN ("terminated","offboarding")') as $v2) {
            add_alert((int)$v2['id'], 'review_due',
                'Periodic review of ' . $v2['name'] . ' is overdue (was due ' . fmt_date($v2['next_review_date']) . ')', 'warning');
            $nRev++;
        }
        audit('contract_reminders', 'contract', null,
              count($soon) . ' contracts, ' . $nDocs . ' documents, ' . $nAs . ' assessments, ' . $nRev . ' reviews');
        flash('success', 'Reminders created — ' . count($soon) . ' contract, ' . $nDocs . ' document, '
              . $nAs . ' assessment and ' . $nRev . ' overdue-review alert(s)' .
              ($sentMail ? " + $sentMail email(s) sent." : (setting('smtp_host') ? '.' : '. Configure SMTP in Settings to also receive emails.')));
        redirect('contracts');
    }
}

// Freshness: contract statuses follow the calendar even if nobody presses the
// reminders button — refreshed on every page view (two cheap indexed UPDATEs).
q("UPDATE contracts SET status = 'expired' WHERE end_date < CURDATE() AND status IN ('active','expiring')");
q("UPDATE contracts SET status = 'expiring' WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND status = 'active'");

$vidF = (int)($_GET['vendor'] ?? 0);
$stF  = in_array($_GET['status'] ?? '', ['draft','active','expiring','expired','terminated'], true) ? $_GET['status'] : '';
$where = []; $params = [];
if ($vidF) { $where[] = 'c.vendor_id = ?'; $params[] = $vidF; }
if ($stF)  { $where[] = 'c.status = ?'; $params[] = $stF; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$pg = paginate("SELECT COUNT(*) FROM contracts c$w",
               "SELECT c.*, v.name vendor_name FROM contracts c JOIN vendors v ON v.id = c.vendor_id$w
                ORDER BY c.end_date IS NULL, c.end_date ASC", $params, 25);
$vendorOpts = rows('SELECT id, name FROM vendors ORDER BY name LIMIT 1100');
$editing = null;
if (isset($_GET['edit']) && can('manage_contracts')) {
    $editing = row('SELECT * FROM contracts WHERE id = ?', [(int)$_GET['edit']]);
}
$expiring60 = (int)scalar("SELECT COUNT(*) FROM contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND status IN ('active','expiring')");
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Contracts <span class="muted" style="font-size:1rem">(<?= (int)$pg['total'] ?>)</span></h1>
  <div class="spacer"></div>
  <?php if (can('manage_contracts')): ?>
    <form method="post" style="display:inline"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="send_reminders">
      <button class="btn btn-outline">🔔 Run expiry reminders</button>
    </form>
    <?= hint('expiry_engine') ?>
    <button class="btn btn-gold" data-open-modal="contract-modal">+ Add contract</button>
  <?php endif; ?>
</div>

<?php if ($expiring60): ?>
  <div class="flash flash-info">⏳ <?= $expiring60 ?> contract(s) expire within 60 days.
    <a href="<?= e(url('contracts', ['status' => 'expiring'])) ?>">Review them →</a></div>
<?php endif; ?>

<div class="card tight">
  <form method="get" action="index.php" style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="p" value="contracts">
    <div><label>Vendor</label><select name="vendor"><option value="0">All vendors</option>
      <?php foreach ($vendorOpts as $vo): ?><option value="<?= (int)$vo['id'] ?>" <?= $vidF === (int)$vo['id'] ? 'selected' : '' ?>><?= e($vo['name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Status</label><select name="status"><option value="">All</option>
      <?php foreach (['draft','active','expiring','expired','terminated'] as $s): ?>
        <option value="<?= $s ?>" <?= $stF === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-gold">Filter</button>
    <a class="btn btn-ghost" href="<?= e(url('contracts')) ?>">Reset</a>
  </form>
</div>

<div class="card">
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Contract</th><th>Vendor</th><th>Type</th><th>Value</th><th>Ends</th><th>Auto-renew</th><th>Clauses<?= hint('clauses') ?></th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$pg['items']): ?><tr><td colspan="9" class="muted">No contracts match.</td></tr><?php endif; ?>
    <?php foreach ($pg['items'] as $c): $du = days_until($c['end_date']); ?>
      <tr>
        <td><strong><?= e($c['title']) ?></strong>
          <?php if ($c['notice_period_days']): ?><div class="small muted"><?= (int)$c['notice_period_days'] ?>d notice period</div><?php endif; ?></td>
        <td><a href="<?= e(url('vendor_view', ['id' => $c['vendor_id'], 'tab' => 'contracts'])) ?>"><?= e($c['vendor_name']) ?></a></td>
        <td class="muted"><?= e($c['contract_type']) ?></td>
        <td class="muted"><?= $c['value_amount'] !== null ? e($c['currency']) . ' ' . number_format((float)$c['value_amount']) : '—' ?></td>
        <td><?= fmt_date($c['end_date']) ?>
          <?php if ($du !== null && $du >= 0 && $du <= 60): ?><span class="badge badge-high"><?= $du ?>d</span><?php endif; ?></td>
        <td class="muted"><?= $c['auto_renew'] ? 'Yes ⟳' : 'No' ?></td>
        <td class="small" title="Right-to-audit / Data protection / Termination / SLA">
          <?= $c['clause_right_to_audit'] ? '✅' : '❌' ?><?= $c['clause_data_protection'] ? '✅' : '❌' ?><?= $c['clause_termination'] ? '✅' : '❌' ?><?= $c['clause_sla'] ? '✅' : '❌' ?></td>
        <td><?= status_badge($c['status']) ?></td>
        <td style="white-space:nowrap">
          <?php if ($c['filename']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('download', ['type' => 'contract', 'id' => $c['id']])) ?>">⇓</a><?php endif; ?>
          <?php if (can('manage_contracts')): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('contracts', ['edit' => $c['id']])) ?>">✎</a>
            <form method="post" style="display:inline" data-confirm="Delete this contract?"><?= csrf_field() ?>
              <input type="hidden" name="_action" value="delete"><input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-danger">✕</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?= pagination_links($pg, array_filter(['vendor' => $vidF ?: '', 'status' => $stF], fn($x) => $x !== '')) ?>
</div>

<div class="modal-back <?= $editing ? 'open' : '' ?>" id="contract-modal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0"><?= $editing ? 'Edit contract' : 'Add contract' ?></h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('contracts')) ?>">✕</a>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('contracts')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="contract_id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
      <label>Vendor *</label>
      <select name="vendor_id" required><option value="">— choose —</option>
        <?php foreach ($vendorOpts as $vo): ?>
          <option value="<?= (int)$vo['id'] ?>" <?= ($editing['vendor_id'] ?? $vidF) == $vo['id'] ? 'selected' : '' ?>><?= e($vo['name']) ?></option>
        <?php endforeach; ?></select>
      <label>Title *</label><input type="text" name="title" required value="<?= e($editing['title'] ?? '') ?>">
      <div class="form-row">
        <div><label>Type</label><select name="contract_type">
          <?php foreach ($TYPES as $t): ?><option <?= ($editing['contract_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div><label>Status</label><select name="status">
          <?php foreach (['draft','active','expiring','expired','terminated'] as $s): ?>
            <option value="<?= $s ?>" <?= ($editing['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="form-row">
        <div><label>Value</label><input type="number" step="0.01" name="value_amount" value="<?= e($editing['value_amount'] ?? '') ?>"></div>
        <div><label>Currency</label><input type="text" name="currency" value="<?= e($editing['currency'] ?? 'USD') ?>"></div>
      </div>
      <div class="form-row">
        <div><label>Start date</label><input type="date" name="start_date" value="<?= e($editing['start_date'] ?? '') ?>"></div>
        <div><label>End date</label><input type="date" name="end_date" value="<?= e($editing['end_date'] ?? '') ?>"></div>
      </div>
      <div class="form-row">
        <div><label>Notice period (days)</label><input type="number" name="notice_period_days" value="<?= e($editing['notice_period_days'] ?? '') ?>"></div>
        <div class="checkline" style="margin-top:2rem"><input type="checkbox" id="ar" name="auto_renew" value="1" <?= !empty($editing['auto_renew']) ? 'checked' : '' ?>>
          <label for="ar">Auto-renews</label></div>
      </div>
      <label>Key clauses present</label>
      <div class="checkline"><input type="checkbox" id="c1" name="clause_right_to_audit" value="1" <?= !empty($editing['clause_right_to_audit']) ? 'checked' : '' ?>><label for="c1">Right to audit</label></div>
      <div class="checkline"><input type="checkbox" id="c2" name="clause_data_protection" value="1" <?= !empty($editing['clause_data_protection']) ? 'checked' : '' ?>><label for="c2">Data protection</label></div>
      <div class="checkline"><input type="checkbox" id="c3" name="clause_termination" value="1" <?= !empty($editing['clause_termination']) ? 'checked' : '' ?>><label for="c3">Termination rights</label></div>
      <div class="checkline"><input type="checkbox" id="c4" name="clause_sla" value="1" <?= !empty($editing['clause_sla']) ? 'checked' : '' ?>><label for="c4">SLA commitments</label></div>
      <label>Contract file <?= $editing && $editing['filename'] ? '<span class="muted">(replaces current file)</span>' : '' ?></label>
      <input type="file" name="file">
      <label>Notes</label><textarea name="notes"><?= e($editing['notes'] ?? '') ?></textarea>
      <button class="btn btn-gold" style="margin-top:1.1rem"><?= $editing ? '✓ Save changes' : '+ Add contract' ?></button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php';
