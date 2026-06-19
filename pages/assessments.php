<?php
/** Assessments pipeline — every questionnaire across the program. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Assessments';

$STATUSES = ['draft','sent','in_progress','submitted','in_review','clarification','approved','rejected'];
$stF = in_array($_GET['status'] ?? '', $STATUSES, true) ? $_GET['status'] : '';
$where = []; $params = [];
if ($stF) { $where[] = 'a.status = ?'; $params[] = $stF; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$pg = paginate("SELECT COUNT(*) FROM assessments a$w",
    "SELECT a.*, v.name vendor_name, t.name tpl_name FROM assessments a
     JOIN vendors v ON v.id = a.vendor_id JOIN assessment_templates t ON t.id = a.template_id$w
     ORDER BY FIELD(a.status,'submitted','in_review','clarification','in_progress','sent','draft','approved','rejected'), a.due_date",
    $params, 25);

$counts = [];
foreach (rows('SELECT status, COUNT(*) c FROM assessments GROUP BY status') as $r) $counts[$r['status']] = (int)$r['c'];
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Assessments</h1>
  <div class="spacer"></div>
  <?php if (can('manage_assessments')): ?>
    <a class="btn btn-gold" href="<?= e(url('assessment_new')) ?>">+ Send questionnaire</a>
  <?php endif; ?>
</div>

<div class="grid c4">
  <div class="card kpi"><div class="kpi-label">Awaiting review</div>
    <div class="kpi-value" style="color:var(--blue)"><span data-count="<?= ($counts['submitted'] ?? 0) + ($counts['in_review'] ?? 0) ?>">0</span></div>
    <div class="kpi-sub">Submitted + in review</div><div class="kpi-ico">👁</div></div>
  <div class="card kpi"><div class="kpi-label">With vendors</div>
    <div class="kpi-value" style="color:var(--orange)"><span data-count="<?= ($counts['sent'] ?? 0) + ($counts['in_progress'] ?? 0) + ($counts['clarification'] ?? 0) ?>">0</span></div>
    <div class="kpi-sub">Sent, in progress, clarification</div><div class="kpi-ico">✉</div></div>
  <div class="card kpi"><div class="kpi-label">Approved</div>
    <div class="kpi-value" style="color:var(--green)"><span data-count="<?= $counts['approved'] ?? 0 ?>">0</span></div>
    <div class="kpi-sub">Completed successfully</div><div class="kpi-ico">✓</div></div>
  <div class="card kpi"><div class="kpi-label">Rejected</div>
    <div class="kpi-value" style="color:var(--red)"><span data-count="<?= $counts['rejected'] ?? 0 ?>">0</span></div>
    <div class="kpi-sub">Failed due diligence</div><div class="kpi-ico">✕</div></div>
</div>

<div class="card tight">
  <div style="display:flex;gap:.4rem;flex-wrap:wrap">
    <a class="btn btn-sm <?= $stF === '' ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e(url('assessments')) ?>">All</a>
    <?php foreach ($STATUSES as $s): ?>
      <a class="btn btn-sm <?= $stF === $s ? 'btn-gold' : 'btn-ghost' ?>" href="<?= e(url('assessments', ['status' => $s])) ?>">
        <?= ucwords(str_replace('_', ' ', $s)) ?> (<?= $counts[$s] ?? 0 ?>)</a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Assessment</th><th>Vendor</th><th>Template</th><th>Status<?= hint('assessment_status') ?></th><th>Round</th><th>Due</th><th>Score</th><th></th></tr></thead>
    <tbody>
    <?php if (!$pg['items']): ?><tr><td colspan="8" class="muted">No assessments in this state.</td></tr><?php endif; ?>
    <?php foreach ($pg['items'] as $a): $du = days_until($a['due_date']); ?>
      <tr>
        <td><a class="rowlink" href="<?= e(url('assessment_review', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a></td>
        <td><a href="<?= e(url('vendor_view', ['id' => $a['vendor_id'], 'tab' => 'assessments'])) ?>"><?= e($a['vendor_name']) ?></a></td>
        <td class="muted"><?= e($a['tpl_name']) ?></td>
        <td><?= status_badge($a['status']) ?></td>
        <td class="muted"><?= (int)$a['round'] ?></td>
        <td><?= fmt_date($a['due_date']) ?>
          <?php if ($du !== null && $du < 0 && !in_array($a['status'], ['approved','rejected'], true)): ?>
            <span class="badge badge-critical">Overdue</span><?php endif; ?></td>
        <td><?= $a['score'] !== null ? (int)$a['score'] . '/100' : '—' ?></td>
        <td><a class="btn btn-sm btn-outline" href="<?= e(url('assessment_review', ['id' => $a['id']])) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?= pagination_links($pg, array_filter(['status' => $stF])) ?>
</div>
<?php include __DIR__ . '/../partials/footer.php';
