<?php
/** Obligations calendar — everything with a date in the next 12 months. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Calendar';

$events = [];
foreach (rows("SELECT c.title, c.end_date d, v.name vn, v.id vid FROM contracts c JOIN vendors v ON v.id = c.vendor_id
               WHERE c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 12 MONTH) AND c.status IN ('active','expiring')") as $r) {
    $events[] = ['date' => $r['d'], 'type' => 'Contract expiry', 'label' => $r['title'], 'vendor' => $r['vn'], 'vid' => $r['vid'], 'sev' => 'high'];
}
foreach (rows('SELECT d.title, d.expiry_date d, v.name vn, v.id vid FROM documents d JOIN vendors v ON v.id = d.vendor_id
               WHERE d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 12 MONTH)') as $r) {
    $events[] = ['date' => $r['d'], 'type' => 'Document expiry', 'label' => $r['title'], 'vendor' => $r['vn'], 'vid' => $r['vid'], 'sev' => 'medium'];
}
foreach (rows("SELECT a.title, a.due_date d, v.name vn, v.id vid FROM assessments a JOIN vendors v ON v.id = a.vendor_id
               WHERE a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 12 MONTH)
               AND a.status NOT IN ('approved','rejected')") as $r) {
    $events[] = ['date' => $r['d'], 'type' => 'Assessment due', 'label' => $r['title'], 'vendor' => $r['vn'], 'vid' => $r['vid'], 'sev' => 'info'];
}
foreach (rows('SELECT v.name vn, v.id vid, v.next_review_date d FROM vendors v
               WHERE v.next_review_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 12 MONTH)') as $r) {
    $events[] = ['date' => $r['d'], 'type' => 'Periodic review', 'label' => 'Scheduled vendor review', 'vendor' => $r['vn'], 'vid' => $r['vid'], 'sev' => 'info'];
}
foreach (rows("SELECT i.title, i.sla_due d, v.name vn, v.id vid FROM issues i JOIN vendors v ON v.id = i.vendor_id
               WHERE i.sla_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 12 MONTH)
               AND i.status IN ('open','in_remediation','overdue')") as $r) {
    $events[] = ['date' => $r['d'], 'type' => 'Issue SLA', 'label' => $r['title'], 'vendor' => $r['vn'], 'vid' => $r['vid'], 'sev' => 'high'];
}
usort($events, fn($a, $b) => strcmp($a['date'], $b['date']));
$byMonth = [];
foreach ($events as $ev) $byMonth[date('F Y', strtotime($ev['date']))][] = $ev;
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Obligations Calendar</h1>
  <div class="spacer"></div>
  <span class="muted small"><?= count($events) ?> dated obligations in the next 12 months</span>
</div>

<?php if (!$events): ?>
  <div class="card empty-state"><div class="empty-icon">📆</div><h3>Nothing scheduled</h3>
    <p class="muted">Contract expiries, document expiries, assessment due dates, issue SLAs and periodic reviews all land here automatically.</p></div>
<?php endif; ?>

<?php foreach ($byMonth as $month => $list): ?>
  <h2 class="gold" style="margin:1.2rem 0 .6rem"><?= e($month) ?></h2>
  <div class="card tight">
    <table class="data"><tbody>
    <?php foreach ($list as $ev): $du = days_until($ev['date']); ?>
      <tr>
        <td style="width:110px;white-space:nowrap"><strong><?= e(date('D, M j', strtotime($ev['date']))) ?></strong>
          <div class="small muted">in <?= $du ?>d</div></td>
        <td><span class="badge badge-<?= $ev['sev'] === 'high' ? 'high' : ($ev['sev'] === 'medium' ? 'medium' : 'status') ?>"><?= e($ev['type']) ?></span></td>
        <td><?= e($ev['label']) ?></td>
        <td><a href="<?= e(url('vendor_view', ['id' => $ev['vid']])) ?>"><?= e($ev['vendor']) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
<?php endforeach; ?>
<?php include __DIR__ . '/../partials/footer.php';
