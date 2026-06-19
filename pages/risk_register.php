<?php
/** Risk register — likelihood × impact, treatments, 5×5 heat map. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Risk Register';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('manage_issues')) {
    $act = $_POST['_action'] ?? '';
    if ($act === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') { flash('error', 'Risk title is required.'); redirect('risk_register'); }
        q('INSERT INTO risk_register (vendor_id, title, description, likelihood, impact, treatment, treatment_plan, status, owner_user_id, review_date)
           VALUES (?,?,?,?,?,?,?,?,?,?)',
          [(int)($_POST['vendor_id'] ?? 0) ?: null, $title, trim((string)($_POST['description'] ?? '')) ?: null,
           max(1, min(5, (int)($_POST['likelihood'] ?? 3))), max(1, min(5, (int)($_POST['impact'] ?? 3))),
           in_array($_POST['treatment'] ?? '', ['mitigate','accept','transfer','avoid'], true) ? $_POST['treatment'] : 'mitigate',
           trim((string)($_POST['treatment_plan'] ?? '')) ?: null, 'open', current_user()['id'],
           trim((string)($_POST['review_date'] ?? '')) ?: null]);
        audit('risk_created', 'risk', (int)db()->lastInsertId(), $title);
        flash('success', 'Risk recorded.');
        redirect('risk_register');
    }
    if ($act === 'status') {
        $rid = (int)($_POST['risk_id'] ?? 0);
        if (in_array($_POST['status'] ?? '', ['open','monitoring','closed'], true)) {
            q('UPDATE risk_register SET status = ? WHERE id = ?', [$_POST['status'], $rid]);
            flash('success', 'Risk updated.');
        }
        redirect('risk_register');
    }
}

$risks = rows('SELECT r.*, v.name vendor_name FROM risk_register r LEFT JOIN vendors v ON v.id = r.vendor_id
               ORDER BY (r.likelihood * r.impact) DESC, r.status');
$heat = array_fill(1, 5, array_fill(1, 5, 0));
foreach ($risks as $r) if ($r['status'] !== 'closed') $heat[(int)$r['impact']][(int)$r['likelihood']]++;
$vendorOpts = rows('SELECT id, name FROM vendors ORDER BY name LIMIT 1100');
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Risk Register</h1>
  <div class="spacer"></div>
  <?php if (can('manage_issues')): ?><button class="btn btn-gold" data-open-modal="risk-modal">+ Record risk</button><?php endif; ?>
</div>

<div class="grid c2">
  <div class="card">
    <h2>Heat map <span class="muted small">(open & monitoring risks)</span></h2>
    <table style="border-collapse:collapse;width:100%;text-align:center">
      <?php for ($impact = 5; $impact >= 1; $impact--): ?>
      <tr>
        <td class="small muted" style="padding:.3rem;width:70px"><?= $impact === 5 ? 'Impact 5' : $impact ?></td>
        <?php for ($lik = 1; $lik <= 5; $lik++):
          $score = $impact * $lik;
          $bg = $score >= 15 ? 'rgba(248,113,113,.5)' : ($score >= 8 ? 'rgba(251,146,60,.45)' : ($score >= 4 ? 'rgba(251,191,36,.35)' : 'rgba(52,211,153,.3)'));
        ?>
        <td style="border:1px solid var(--border);padding:.6rem;background:<?= $bg ?>;font-weight:700">
          <?= $heat[$impact][$lik] ?: '' ?></td>
        <?php endfor; ?>
      </tr>
      <?php endfor; ?>
      <tr><td></td><?php for ($lik = 1; $lik <= 5; $lik++): ?>
        <td class="small muted" style="padding:.3rem"><?= $lik === 1 ? 'Lik. 1' : $lik ?></td><?php endfor; ?></tr>
    </table>
  </div>
  <div class="card">
    <h2>How to use the register</h2>
    <p class="muted small">Record risks that go beyond a single finding — concentration risk, geographic exposure,
      exit-barrier risk. Score <strong>likelihood × impact</strong> (1–5 each), pick a treatment
      (<strong>mitigate / accept / transfer / avoid</strong>), write the plan, and set a review date.
      Program-level risks can be recorded without a vendor.</p>
  </div>
</div>

<div class="card">
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Risk</th><th>Vendor</th><th>L×I<?= hint('lxi') ?></th><th>Treatment</th><th>Status</th><th>Review</th><th></th></tr></thead>
    <tbody>
    <?php if (!$risks): ?><tr><td colspan="7" class="muted">No risks recorded yet.</td></tr><?php endif; ?>
    <?php foreach ($risks as $r): $sc = (int)$r['likelihood'] * (int)$r['impact']; ?>
      <tr>
        <td><strong><?= e($r['title']) ?></strong>
          <?php if ($r['treatment_plan']): ?><div class="small" style="color:var(--blue)">Plan: <?= e(mb_strimwidth($r['treatment_plan'], 0, 100, '…')) ?></div><?php endif; ?></td>
        <td class="muted"><?= $r['vendor_id'] ? '<a href="' . e(url('vendor_view', ['id' => $r['vendor_id']])) . '">' . e($r['vendor_name']) . '</a>' : 'Program-level' ?></td>
        <td><span class="badge <?= $sc >= 15 ? 'badge-critical' : ($sc >= 8 ? 'badge-high' : ($sc >= 4 ? 'badge-medium' : 'badge-low')) ?>"><?= (int)$r['likelihood'] ?>×<?= (int)$r['impact'] ?> = <?= $sc ?></span></td>
        <td class="muted"><?= e(ucfirst($r['treatment'])) ?></td>
        <td><?= status_badge($r['status'] === 'monitoring' ? 'in_review' : ($r['status'] === 'closed' ? 'resolved' : 'open')) ?></td>
        <td class="muted"><?= fmt_date($r['review_date']) ?></td>
        <td><?php if (can('manage_issues')): ?>
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="status"><input type="hidden" name="risk_id" value="<?= (int)$r['id'] ?>">
            <select name="status" onchange="this.form.submit()" style="width:auto;padding:.3rem .5rem">
              <?php foreach (['open','monitoring','closed'] as $s): ?>
                <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?></select></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="modal-back" id="risk-modal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0">Record a risk</h2><button class="btn btn-ghost btn-sm" data-close-modal>✕</button></div>
    <form method="post" action="<?= e(url('risk_register')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="create">
      <label>Title *</label><input type="text" name="title" required>
      <label>Vendor <span class="muted">(blank = program-level)</span></label>
      <select name="vendor_id"><option value="0">— program-level —</option>
        <?php foreach ($vendorOpts as $vo): ?><option value="<?= (int)$vo['id'] ?>"><?= e($vo['name']) ?></option><?php endforeach; ?></select>
      <label>Description</label><textarea name="description"></textarea>
      <div class="form-row">
        <div><label>Likelihood (1–5)</label><input type="number" name="likelihood" min="1" max="5" value="3"></div>
        <div><label>Impact (1–5)</label><input type="number" name="impact" min="1" max="5" value="3"></div>
      </div>
      <div class="form-row">
        <div><label>Treatment</label><select name="treatment"><option>mitigate</option><option>accept</option><option>transfer</option><option>avoid</option></select></div>
        <div><label>Review date</label><input type="date" name="review_date" value="<?= date('Y-m-d', strtotime('+90 days')) ?>"></div>
      </div>
      <label>Treatment plan</label><textarea name="treatment_plan"></textarea>
      <button class="btn btn-gold" style="margin-top:1rem">⚖ Record risk</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php';
