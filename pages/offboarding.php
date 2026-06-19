<?php
/** Vendor offboarding / exit checklists. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Offboarding';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('manage_vendors')) {
    $act = $_POST['_action'] ?? '';
    if ($act === 'toggle') {
        $t = row('SELECT * FROM offboarding_tasks WHERE id = ?', [(int)($_POST['task_id'] ?? 0)]);
        if ($t) {
            q('UPDATE offboarding_tasks SET done = ?, done_by = ?, done_at = ? WHERE id = ?',
              [$t['done'] ? 0 : 1, $t['done'] ? null : current_user()['id'], $t['done'] ? null : date('Y-m-d H:i:s'), $t['id']]);
        }
        redirect('offboarding');
    }
    if ($act === 'add_task') {
        $vid = (int)($_POST['vendor_id'] ?? 0);
        $task = trim((string)($_POST['task'] ?? ''));
        if ($vid && $task !== '') {
            $max = (int)scalar('SELECT COALESCE(MAX(sort_order), -1) FROM offboarding_tasks WHERE vendor_id = ?', [$vid]);
            q('INSERT INTO offboarding_tasks (vendor_id, task, sort_order) VALUES (?,?,?)', [$vid, $task, $max + 1]);
            flash('success', 'Task added.');
        }
        redirect('offboarding');
    }
    if ($act === 'complete') {
        $vid = (int)($_POST['vendor_id'] ?? 0);
        $open = (int)scalar('SELECT COUNT(*) FROM offboarding_tasks WHERE vendor_id = ? AND done = 0', [$vid]);
        if ($open) { flash('error', $open . ' task(s) still open — finish the checklist before terminating.'); }
        else {
            q('UPDATE vendors SET lifecycle = "terminated" WHERE id = ?', [$vid]);
            // Expert hygiene: a terminated vendor cannot have "active" contracts.
            $nC = q('UPDATE contracts SET status = "terminated"
                     WHERE vendor_id = ? AND status IN ("draft","active","expiring")', [$vid])->rowCount();
            if ($nC) add_alert($vid, 'contract_expiry', $nC . ' contract(s) auto-closed with vendor termination', 'info');
            audit('vendor_terminated', 'vendor', $vid, $nC ? $nC . ' contracts auto-closed' : '');
            flash('success', 'Vendor terminated — relationship closed cleanly. 🎌'
                . ($nC ? " $nC open contract(s) were auto-closed with it." : ''));
        }
        redirect('offboarding');
    }
}

$inOff = rows('SELECT v.*, (SELECT COUNT(*) FROM offboarding_tasks t WHERE t.vendor_id = v.id) total,
               (SELECT COUNT(*) FROM offboarding_tasks t WHERE t.vendor_id = v.id AND t.done = 1) done
               FROM vendors v WHERE v.lifecycle = "offboarding" ORDER BY v.name');
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Offboarding</h1>
  <div class="spacer"></div>
  <span class="muted small">Move a vendor's lifecycle to “Offboarding” on their profile to start an exit checklist.</span>
</div>

<?php if (!$inOff): ?>
  <div class="card empty-state"><div class="empty-icon">⛩️</div><h3>No vendors in offboarding</h3>
    <p class="muted">When a relationship ends, set the vendor's lifecycle to <strong>Offboarding</strong> — a standard
    8-step exit checklist (access revocation, data return, asset recovery…) is created automatically.</p></div>
<?php endif; ?>

<?php foreach ($inOff as $v):
  $tasks = rows('SELECT * FROM offboarding_tasks WHERE vendor_id = ? ORDER BY sort_order', [$v['id']]);
  $pct = $v['total'] ? (int)round(100 * $v['done'] / $v['total']) : 0;
?>
<div class="card">
  <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:240px">
      <h2 style="margin:0"><a href="<?= e(url('vendor_view', ['id' => $v['id']])) ?>"><?= e($v['name']) ?></a></h2>
      <div class="small muted"><?= (int)$v['done'] ?>/<?= (int)$v['total'] ?> tasks complete</div>
      <div class="meter" style="margin-top:.4rem"><span data-w="<?= $pct ?>"></span></div>
    </div>
    <?php if (can('manage_vendors')): ?>
    <form method="post" data-confirm="Terminate <?= e($v['name']) ?>? All checklist items are complete."><?= csrf_field() ?>
      <input type="hidden" name="_action" value="complete"><input type="hidden" name="vendor_id" value="<?= (int)$v['id'] ?>">
      <button class="btn <?= $pct === 100 ? 'btn-gold' : 'btn-ghost' ?>" <?= $pct < 100 ? 'disabled' : '' ?>>✓ Complete offboarding</button>
    </form>
    <?= $pct < 100 ? hint('offboarding_lock') : '' ?>
    <?php endif; ?>
  </div>
  <div style="margin-top:.9rem">
    <?php foreach ($tasks as $t): ?>
      <form method="post" style="display:flex;align-items:center;gap:.6rem;padding:.3rem 0"><?= csrf_field() ?>
        <input type="hidden" name="_action" value="toggle"><input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
        <button class="btn btn-sm <?= $t['done'] ? 'btn-gold' : 'btn-ghost' ?>" <?= can('manage_vendors') ? '' : 'disabled' ?>
                style="width:2rem;justify-content:center"><?= $t['done'] ? '✓' : '○' ?></button>
        <span style="<?= $t['done'] ? 'text-decoration:line-through;opacity:.55' : '' ?>"><?= e($t['task']) ?></span>
        <?php if ($t['done'] && $t['done_at']): ?><span class="small muted">· <?= e(date('M j', strtotime($t['done_at']))) ?></span><?php endif; ?>
      </form>
    <?php endforeach; ?>
    <?php if (can('manage_vendors')): ?>
    <form method="post" style="display:flex;gap:.5rem;margin-top:.5rem"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="add_task"><input type="hidden" name="vendor_id" value="<?= (int)$v['id'] ?>">
      <input type="text" name="task" placeholder="Add a custom exit task…" style="max-width:380px">
      <button class="btn btn-sm btn-outline">+ Add</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/../partials/footer.php';
