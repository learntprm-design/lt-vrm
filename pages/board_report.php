<?php
/** Executive board report — print-optimized (browser Print → Save as PDF). */
require_perm('export');
$GLOBALS['VA_PAGE_TITLE'] = 'Board Report';

// Active portfolio only — terminated vendors are excluded from risk statistics.
$totV = (int)scalar('SELECT COUNT(*) FROM vendors WHERE lifecycle <> "terminated"');
$avg = $totV ? (int)round((float)scalar('SELECT AVG(risk_score) FROM vendors WHERE lifecycle <> "terminated"')) : 0;
$bands = ['Critical Risk' => 0, 'High Risk' => 0, 'Moderate' => 0, 'Good' => 0, 'Excellent' => 0];
foreach (rows('SELECT risk_score FROM vendors WHERE lifecycle <> "terminated"') as $r) $bands[risk_band((int)$r['risk_score'])['label']]++;
$tiers = [];
foreach (rows('SELECT tier, COUNT(*) c FROM vendors WHERE lifecycle <> "terminated" GROUP BY tier') as $r) $tiers[$r['tier']] = (int)$r['c'];
$riskiest = rows('SELECT name, tier, risk_score, lifecycle FROM vendors WHERE lifecycle <> "terminated" ORDER BY risk_score ASC LIMIT 10');
$openIssues = rows("SELECT i.title, i.severity, i.sla_due, v.name vn FROM issues i JOIN vendors v ON v.id = i.vendor_id
                    WHERE i.status IN ('open','in_remediation','overdue')
                    ORDER BY FIELD(i.severity,'critical','high','medium','low') LIMIT 12");
$expiring = rows("SELECT c.title, c.end_date, v.name vn FROM contracts c JOIN vendors v ON v.id = c.vendor_id
                  WHERE c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                  AND c.status IN ('active','expiring') ORDER BY c.end_date LIMIT 12");
$asCounts = [];
foreach (rows('SELECT status, COUNT(*) c FROM assessments GROUP BY status') as $r) $asCounts[$r['status']] = (int)$r['c'];
$flagged = (int)scalar("SELECT COUNT(*) FROM vendors WHERE sanctions_status = 'flagged'");
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar no-print">
  <h1>Executive Board Report</h1>
  <div class="spacer"></div>
  <button class="btn btn-gold" onclick="window.print()">🖨 Print / Save as PDF</button>
  <a class="btn btn-ghost" href="<?= e(url('reports')) ?>">← Reports</a>
</div>

<div class="card" style="text-align:center">
  <h1 style="font-size:1.7rem">Third-Party Risk Management — Program Report</h1>
  <p class="muted"><?= e(setting('org_name', 'My Organization')) ?> · Generated <?= e(date('F j, Y')) ?> ·
    Prepared with VendorAssess 360 by LearnTPRM.com</p>
</div>

<div class="grid c4">
  <div class="card kpi"><div class="kpi-label">Third parties</div><div class="kpi-value"><?= $totV ?></div></div>
  <div class="card kpi"><div class="kpi-label">Avg. risk score</div><div class="kpi-value"><?= $avg ?>/1000</div>
    <div class="kpi-sub"><?= e(risk_band($avg)['label']) ?></div></div>
  <div class="card kpi"><div class="kpi-label">Critical-band vendors</div><div class="kpi-value"><?= $bands['Critical Risk'] ?></div></div>
  <div class="card kpi"><div class="kpi-label">Sanctions-flagged</div><div class="kpi-value"><?= $flagged ?></div></div>
</div>

<div class="grid c2">
  <div class="card">
    <h2>Portfolio risk distribution</h2>
    <table class="data"><tbody>
      <?php foreach ($bands as $label => $cnt): ?>
        <tr><td><?= e($label) ?></td><td style="text-align:right"><strong><?= $cnt ?></strong></td>
          <td style="text-align:right" class="muted"><?= $totV ? round(100 * $cnt / $totV) : 0 ?>%</td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <h3 style="margin-top:1rem">By criticality tier</h3>
    <table class="data"><tbody>
      <?php foreach (['critical','high','medium','low'] as $t): ?>
        <tr><td><?= ucfirst($t) ?></td><td style="text-align:right"><strong><?= $tiers[$t] ?? 0 ?></strong></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card">
    <h2>Ten highest-risk vendors</h2>
    <table class="data">
      <thead><tr><th>Vendor</th><th>Tier</th><th>Score</th><th>Lifecycle</th></tr></thead><tbody>
      <?php foreach ($riskiest as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td><?= e(ucfirst($r['tier'])) ?></td>
          <td><strong><?= (int)$r['risk_score'] ?></strong> <span class="muted">(<?= e(risk_band((int)$r['risk_score'])['label']) ?>)</span></td>
          <td class="muted"><?= e(ucwords(str_replace('_',' ',$r['lifecycle']))) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
</div>

<div class="grid c2">
  <div class="card">
    <h2>Assessment pipeline</h2>
    <table class="data"><tbody>
      <?php foreach (['sent' => 'Sent to vendors', 'in_progress' => 'Vendor in progress', 'submitted' => 'Awaiting review',
                      'in_review' => 'In review', 'clarification' => 'In clarification', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $lbl): ?>
        <tr><td><?= e($lbl) ?></td><td style="text-align:right"><strong><?= $asCounts[$k] ?? 0 ?></strong></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card">
    <h2>Contracts expiring ≤90 days</h2>
    <table class="data"><tbody>
      <?php foreach ($expiring as $c): ?>
        <tr><td><?= e($c['vn']) ?></td><td class="muted"><?= e($c['title']) ?></td>
          <td style="text-align:right"><?= fmt_date($c['end_date']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$expiring): ?><tr><td class="muted">None.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>

<div class="card">
  <h2>Open issues requiring attention</h2>
  <table class="data">
    <thead><tr><th>Vendor</th><th>Issue</th><th>Severity</th><th>SLA due</th></tr></thead><tbody>
    <?php foreach ($openIssues as $i): ?>
      <tr><td><?= e($i['vn']) ?></td><td><?= e($i['title']) ?></td>
        <td><?= e(ucfirst($i['severity'])) ?></td><td><?= fmt_date($i['sla_due']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$openIssues): ?><tr><td class="muted">No open issues.</td></tr><?php endif; ?>
  </tbody></table>
</div>

<div class="card">
  <h2>Methodology note</h2>
  <p class="muted small">Vendor risk scores (0–1000, higher = safer) are a weighted composite of six factors:
    assessment results 25%, breach &amp; dark-web exposure 20%, digital-footprint hygiene 15%, compliance health
    (documents, contract clauses, open issues) 15%, inherent criticality 15%, adverse media 10%.
    Bands: 0–399 Critical · 400–599 High · 600–749 Moderate · 750–899 Good · 900–1000 Excellent.
    Assessment results carry full credit for 12 months, then decay toward neutral by month 24 — stale
    due diligence does not vouch for a vendor. Statistics cover the active portfolio (terminated vendors excluded).</p>
  <p class="muted small">Report produced by VendorAssess 360 — The World's Most Comprehensive Open-Source TPRM Platform,
    developed by LearnTPRM.com.</p>
</div>
<?php include __DIR__ . '/../partials/footer.php';
