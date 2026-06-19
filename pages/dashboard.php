<?php
/** TPRM program dashboard — the analyst's command center. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Dashboard';

// Portfolio hygiene: terminated vendors are history, not risk — they are
// excluded from averages, bands and the riskiest list (but kept in the total).
$totV = (int)scalar('SELECT COUNT(*) FROM vendors');
$actV = (int)scalar('SELECT COUNT(*) FROM vendors WHERE lifecycle <> "terminated"');
$avg = $actV ? (int)round((float)scalar('SELECT AVG(risk_score) FROM vendors WHERE lifecycle <> "terminated"')) : 0;
$critical = (int)scalar('SELECT COUNT(*) FROM vendors WHERE risk_score <= 399 AND lifecycle <> "terminated"');
$openIssues = (int)scalar("SELECT COUNT(*) FROM issues WHERE status IN ('open','in_remediation','overdue')");
$awaiting = (int)scalar("SELECT COUNT(*) FROM assessments WHERE status IN ('submitted','in_review')");
$expiring = (int)scalar("SELECT COUNT(*) FROM contracts WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND status IN ('active','expiring')");
$expDocs = (int)scalar('SELECT COUNT(*) FROM documents WHERE expiry_date IS NOT NULL AND expiry_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)');

$tiers = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
foreach (rows('SELECT tier, COUNT(*) c FROM vendors GROUP BY tier') as $r) $tiers[$r['tier']] = (int)$r['c'];
$bands = ['Critical' => 0, 'High' => 0, 'Moderate' => 0, 'Good' => 0, 'Excellent' => 0];
$bandKey = ['Critical Risk' => 'Critical', 'High Risk' => 'High', 'Moderate' => 'Moderate', 'Good' => 'Good', 'Excellent' => 'Excellent'];
foreach (rows('SELECT risk_score FROM vendors WHERE lifecycle <> "terminated"') as $r) {
    $bands[$bandKey[risk_band((int)$r['risk_score'])['label']]]++;
}

$riskiest = rows('SELECT id, name, tier, risk_score FROM vendors WHERE lifecycle <> "terminated" ORDER BY risk_score ASC LIMIT 8');
$pipeline = rows("SELECT a.id, a.title, a.status, a.due_date, v.name vendor_name FROM assessments a
                  JOIN vendors v ON v.id = a.vendor_id
                  WHERE a.status IN ('submitted','in_review','clarification','sent','in_progress')
                  ORDER BY FIELD(a.status,'submitted','in_review','clarification','in_progress','sent'), a.due_date LIMIT 8");
$expSoon = rows("SELECT c.id, c.title, c.end_date, v.name vendor_name, v.id vid FROM contracts c
                 JOIN vendors v ON v.id = c.vendor_id
                 WHERE c.end_date >= CURDATE() AND c.status IN ('active','expiring') ORDER BY c.end_date LIMIT 8");
$recentAlerts = rows('SELECT * FROM alerts ORDER BY created_at DESC LIMIT 8');
$recentNews = rows('SELECT n.*, v.name vendor_name FROM news_items n JOIN vendors v ON v.id = n.vendor_id
                    WHERE n.severity = "high" AND (n.relevant IS NULL OR n.relevant = 1)
                    ORDER BY n.published_date DESC LIMIT 6');
$bandColors = ['Critical' => '#f87171', 'High' => '#fb923c', 'Moderate' => '#fbbf24', 'Good' => '#a3e635', 'Excellent' => '#34d399'];
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <div>
    <h1>TPRM Program Dashboard</h1>
    <div class="muted small">Welcome back, <?= e(current_user()['name']) ?> — here's your third-party risk posture today.</div>
  </div>
  <div class="spacer"></div>
  <a class="btn btn-outline" href="<?= e(url('board_report')) ?>">📄 Board report</a>
  <?php if (can('manage_vendors')): ?><a class="btn btn-gold" href="<?= e(url('vendor_add')) ?>">+ Add vendor</a><?php endif; ?>
</div>

<div class="grid c4">
  <div class="card kpi"><div class="kpi-label">Vendors managed</div>
    <div class="kpi-value"><span data-count="<?= $totV ?>">0</span></div>
    <div class="kpi-sub">Built for 1,000+ third parties</div><div class="kpi-ico">▣</div></div>
  <div class="card kpi"><div class="kpi-label">Avg. portfolio score</div>
    <div class="kpi-value"><span data-count="<?= $avg ?>">0</span><small class="muted">/1000</small></div>
    <div class="kpi-sub"><?= e(risk_band($avg)['label']) ?> · <?= $actV ?> active (terminated excluded)</div><div class="kpi-ico">⚖</div></div>
  <div class="card kpi"><div class="kpi-label">Critical-risk vendors</div>
    <div class="kpi-value" style="color:<?= $critical ? 'var(--red)' : 'var(--green)' ?>"><span data-count="<?= $critical ?>">0</span></div>
    <div class="kpi-sub">Score ≤ 399</div><div class="kpi-ico">☠</div></div>
  <div class="card kpi"><div class="kpi-label">Open issues</div>
    <div class="kpi-value"><span data-count="<?= $openIssues ?>">0</span></div>
    <div class="kpi-sub"><a href="<?= e(url('issues')) ?>">Remediation queue →</a></div><div class="kpi-ico">⚑</div></div>
</div>

<div class="grid c3">
  <div class="card kpi"><div class="kpi-label">Assessments awaiting review</div>
    <div class="kpi-value" style="color:var(--blue)"><span data-count="<?= $awaiting ?>">0</span></div>
    <div class="kpi-sub"><a href="<?= e(url('assessments', ['status' => 'submitted'])) ?>">Review now →</a></div><div class="kpi-ico">✎</div></div>
  <div class="card kpi"><div class="kpi-label">Contracts expiring ≤60d</div>
    <div class="kpi-value" style="color:var(--orange)"><span data-count="<?= $expiring ?>">0</span></div>
    <div class="kpi-sub"><a href="<?= e(url('contracts')) ?>">Contracts →</a></div><div class="kpi-ico">✍</div></div>
  <div class="card kpi"><div class="kpi-label">Documents expiring ≤30d</div>
    <div class="kpi-value" style="color:var(--yellow)"><span data-count="<?= $expDocs ?>">0</span></div>
    <div class="kpi-sub"><a href="<?= e(url('documents', ['exp' => 1])) ?>">Documents →</a></div><div class="kpi-ico">⛁</div></div>
</div>

<div class="grid c2">
  <div class="card">
    <h2>Risk distribution</h2>
    <?php $maxB = max(1, max($bands)); ?>
    <?php foreach ($bands as $label => $cnt): ?>
      <div style="margin-bottom:.7rem">
        <div class="small" style="display:flex;justify-content:space-between">
          <span><span class="dot" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $bandColors[$label] ?>;margin-right:.4rem"></span><?= e($label) ?></span>
          <strong><?= $cnt ?></strong></div>
        <div class="meter"><span data-w="<?= (int)(100 * $cnt / $maxB) ?>" style="background:<?= $bandColors[$label] ?>"></span></div>
      </div>
    <?php endforeach; ?>
    <h3 style="margin-top:1.1rem">By criticality tier</h3>
    <p class="small">
      <?php foreach ($tiers as $t => $c): ?>
        <a href="<?= e(url('vendors', ['tier' => $t])) ?>"><?= tier_badge($t) ?></a> <?= $c ?> &nbsp;
      <?php endforeach; ?></p>
  </div>

  <div class="card">
    <h2>Highest-risk vendors</h2>
    <table class="data"><tbody>
      <?php foreach ($riskiest as $r): ?>
        <tr><td><a class="rowlink" href="<?= e(url('vendor_view', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a></td>
            <td><?= tier_badge($r['tier']) ?></td>
            <td style="text-align:right"><?= score_badge((int)$r['risk_score']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$riskiest): ?><tr><td class="muted">No vendors yet.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>

<div class="grid c2">
  <div class="card">
    <h2>Assessment pipeline</h2>
    <table class="data"><tbody>
      <?php foreach ($pipeline as $p): ?>
        <tr><td><a class="rowlink" href="<?= e(url('assessment_review', ['id' => $p['id']])) ?>"><?= e($p['vendor_name']) ?></a>
              <div class="small muted"><?= e($p['title']) ?></div></td>
            <td><?= status_badge($p['status']) ?></td>
            <td class="muted small" style="text-align:right"><?= fmt_date($p['due_date']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$pipeline): ?><tr><td class="muted">Nothing in flight — send a questionnaire from any vendor profile.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="card">
    <h2>Upcoming contract expiries</h2>
    <table class="data"><tbody>
      <?php foreach ($expSoon as $c2): $du = days_until($c2['end_date']); ?>
        <tr><td><a class="rowlink" href="<?= e(url('vendor_view', ['id' => $c2['vid'], 'tab' => 'contracts'])) ?>"><?= e($c2['vendor_name']) ?></a>
              <div class="small muted"><?= e($c2['title']) ?></div></td>
            <td class="muted small"><?= fmt_date($c2['end_date']) ?></td>
            <td style="text-align:right"><?php if ($du !== null && $du <= 60): ?><span class="badge badge-high"><?= $du ?>d</span>
              <?php else: ?><span class="badge badge-status"><?= $du ?>d</span><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$expSoon): ?><tr><td class="muted">No upcoming expiries.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>

<div class="grid c2">
  <div class="card">
    <h2>Latest alerts</h2>
    <ul class="timeline">
      <?php foreach ($recentAlerts as $al): ?>
        <li><?= e($al['message']) ?>
          <div class="small muted"><?= e(date('M j, H:i', strtotime($al['created_at']))) ?> ·
            <span class="badge badge-<?= $al['severity'] === 'critical' ? 'critical' : ($al['severity'] === 'warning' ? 'high' : 'status') ?>"><?= e($al['severity']) ?></span></div></li>
      <?php endforeach; ?>
      <?php if (!$recentAlerts): ?><li class="muted small">No alerts.</li><?php endif; ?>
    </ul>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('alerts')) ?>">All alerts →</a>
  </div>
  <div class="card">
    <h2>High-severity adverse media</h2>
    <?php foreach ($recentNews as $n): ?>
      <p class="small" style="margin:.45rem 0"><strong><?= e($n['headline']) ?></strong><br>
        <span class="muted"><a href="<?= e(url('vendor_view', ['id' => $n['vendor_id'], 'tab' => 'news'])) ?>"><?= e($n['vendor_name']) ?></a>
        · <?= fmt_date($n['published_date']) ?> · <?= e($n['source'] ?? '') ?></span></p>
    <?php endforeach; ?>
    <?php if (!$recentNews): ?><p class="muted small">No high-severity items. 👌</p><?php endif; ?>
  </div>
</div>

<div class="card" style="background:linear-gradient(120deg, rgba(238,192,92,.07), transparent 60%), var(--card)">
  <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:260px">
      <h2>Become a TPRM Warrior 🥷</h2>
      <p class="muted small">Sharpen your skills with the world's hardest free TPRM certification, practical lifecycle guides and live job boards — by LearnTPRM.com.</p>
    </div>
    <a class="btn btn-gold" href="<?= e(url('learn')) ?>">Open Learn TPRM →</a>
    <a class="btn btn-outline" href="https://learntprm.com/jobs" target="_blank" rel="noopener">TPRM Jobs ↗</a>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php';
