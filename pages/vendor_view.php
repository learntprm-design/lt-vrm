<?php
/**
 * Vendor 360° profile — tabbed: Overview (public info) · Risk score · Breach &
 * dark web · Digital footprint · Reputation news · Assessments · Documents ·
 * Contracts · Fourth parties · Notes & activity.
 */
require_perm('view');
$id = (int)($_GET['id'] ?? 0);
$v = row('SELECT * FROM vendors WHERE id = ?', [$id]);
if (!$v) { flash('error', 'Vendor not found.'); redirect('vendors'); }
$GLOBALS['VA_PAGE_TITLE'] = $v['name'];
$tab = preg_replace('/[^a-z_]/', '', (string)($_GET['tab'] ?? 'overview'));

/* ------------------------------- POST actions ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('manage_vendors')) {
    $act = $_POST['_action'] ?? '';
    if ($act === 'scan_breach') {
        $r = scan_breaches($id); recompute_risk($id);
        flash('success', 'Breach scan complete (' . $r['mode'] . ' mode): ' . $r['count'] . ' finding(s).');
        redirect('vendor_view', ['id' => $id, 'tab' => 'breach']);
    }
    if ($act === 'scan_footprint') {
        $r = scan_footprint($id); recompute_risk($id);
        flash('success', 'Footprint scan complete (' . $r['mode'] . ' mode).');
        redirect('vendor_view', ['id' => $id, 'tab' => 'footprint']);
    }
    if ($act === 'scan_news') {
        $r = scan_news($id); recompute_risk($id);
        flash('success', 'Reputation scan complete (' . $r['mode'] . ' mode).');
        redirect('vendor_view', ['id' => $id, 'tab' => 'news']);
    }
    if ($act === 'recalc') {
        $s = recompute_risk($id);
        flash('success', 'Risk score recalculated: ' . $s . '/1000.');
        redirect('vendor_view', ['id' => $id, 'tab' => 'score']);
    }
    if ($act === 'news_disposition') {
        $nid = (int)($_POST['news_id'] ?? 0);
        $rel = (int)($_POST['relevant'] ?? 0) ? 1 : 0;
        q('UPDATE news_items SET relevant = ? WHERE id = ? AND vendor_id = ?', [$rel, $nid, $id]);
        recompute_risk($id);
        audit('news_disposition', 'vendor', $id, 'Item #' . $nid . ' marked ' . ($rel ? 'relevant' : 'not relevant'));
        redirect('vendor_view', ['id' => $id, 'tab' => 'news']);
    }
    if ($act === 'add_note') {
        $note = trim((string)($_POST['note'] ?? ''));
        if ($note !== '') {
            q('INSERT INTO vendor_notes (vendor_id, user_id, user_name, note) VALUES (?,?,?,?)',
              [$id, current_user()['id'], current_user()['name'], $note]);
            audit('note_added', 'vendor', $id);
            flash('success', 'Note added.');
        }
        redirect('vendor_view', ['id' => $id, 'tab' => 'notes']);
    }
    if ($act === 'add_fourth') {
        $n = trim((string)($_POST['fp_name'] ?? ''));
        if ($n !== '') {
            q('INSERT INTO fourth_parties (vendor_id, name, service, criticality, notes) VALUES (?,?,?,?,?)',
              [$id, $n, trim((string)($_POST['fp_service'] ?? '')) ?: null,
               in_array($_POST['fp_crit'] ?? '', ['critical','high','medium','low'], true) ? $_POST['fp_crit'] : 'medium',
               trim((string)($_POST['fp_notes'] ?? '')) ?: null]);
            flash('success', 'Fourth party added.');
        }
        redirect('vendor_view', ['id' => $id, 'tab' => 'fourth']);
    }
    if ($act === 'del_fourth') {
        q('DELETE FROM fourth_parties WHERE id = ? AND vendor_id = ?', [(int)($_POST['fp_id'] ?? 0), $id]);
        redirect('vendor_view', ['id' => $id, 'tab' => 'fourth']);
    }
    if ($act === 'lifecycle') {
        $lc = $_POST['lifecycle'] ?? '';
        if (in_array($lc, ['onboarding','active','under_review','offboarding','terminated'], true)) {
            q('UPDATE vendors SET lifecycle = ? WHERE id = ?', [$lc, $id]);
            audit('lifecycle_change', 'vendor', $id, $lc);
            if ($lc === 'offboarding') {
                // Seed the standard exit checklist if not present
                if (!(int)scalar('SELECT COUNT(*) FROM offboarding_tasks WHERE vendor_id = ?', [$id])) {
                    $tasks = ['Revoke all system and network access', 'Confirm return or certified destruction of data',
                        'Retrieve company assets and badges', 'Settle final invoices and credits',
                        'Archive contracts and assessment evidence', 'Notify internal stakeholders',
                        'Remove vendor from monitoring tools', 'Conduct exit review and lessons learned'];
                    foreach ($tasks as $i => $t) {
                        q('INSERT INTO offboarding_tasks (vendor_id, task, sort_order) VALUES (?,?,?)', [$id, $t, $i]);
                    }
                }
                flash('info', 'Offboarding checklist created — see the Offboarding page.');
            }
            flash('success', 'Lifecycle updated.');
        }
        redirect('vendor_view', ['id' => $id]);
    }
}

// Freshness: time alone changes risk (documents expire, approvals age).
// If this vendor's score is >24h old, quietly recompute it on view.
$lastCalc = scalar('SELECT MAX(created_at) FROM risk_scores WHERE vendor_id = ?', [$id]);
if (!$lastCalc || strtotime($lastCalc) < time() - 86400) {
    recompute_risk($id);
    $v = row('SELECT * FROM vendors WHERE id = ?', [$id]);
}

$band = risk_band((int)$v['risk_score']);
$factors = risk_factors($id);
$contacts = rows('SELECT * FROM vendor_contacts WHERE vendor_id = ? ORDER BY is_primary DESC', [$id]);
include __DIR__ . '/../partials/header.php';

$tabs = [
  'overview' => 'Overview', 'score' => 'Risk Score', 'breach' => 'Breach & Dark Web',
  'footprint' => 'Digital Footprint', 'news' => 'Reputation News', 'assessments' => 'Assessments',
  'documents' => 'Documents', 'contracts' => 'Contracts', 'fourth' => 'Fourth Parties', 'notes' => 'Notes & Activity',
];
if (!isset($tabs[$tab])) $tab = 'overview';
?>
<div class="topbar">
  <div>
    <h1><?= e($v['name']) ?> <?= tier_badge($v['tier']) ?> <?= status_badge($v['lifecycle']) ?></h1>
    <div class="muted small"><?= e($v['industry'] ?? '') ?><?= $v['industry'] && $v['country'] ? ' · ' : '' ?><?= e($v['country'] ?? '') ?>
      <?php if ($v['website']): ?> · <a href="<?= e($v['website']) ?>" target="_blank" rel="noopener"><?= e($v['domain']) ?></a><?php endif; ?>
    </div>
  </div>
  <div class="spacer"></div>
  <?= score_badge((int)$v['risk_score']) ?>
  <?php if (can('manage_vendors')): ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('vendor_edit', ['id' => $id])) ?>">✎ Edit</a>
    <form method="post" style="display:inline">
      <?= csrf_field() ?><input type="hidden" name="_action" value="lifecycle">
      <select name="lifecycle" onchange="this.form.submit()" style="width:auto;padding:.34rem .6rem">
        <?php foreach (['onboarding','active','under_review','offboarding','terminated'] as $lc): ?>
          <option value="<?= $lc ?>" <?= $v['lifecycle'] === $lc ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$lc)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>
</div>

<?php if ($v['sanctions_status'] === 'flagged'): ?>
  <div class="flash flash-error" style="display:flex;gap:.6rem;align-items:center">
    <span style="font-size:1.2rem">⛔</span>
    <div><strong>Sanctions / watchlist flag.</strong> This vendor is marked as appearing on a sanctions or watch list.
      Do not expand business, sign contracts, or process payments until Legal/Compliance verifies against official
      lists (OFAC, EU, UN) and clears the flag in Edit → Public information.</div>
  </div>
<?php endif; ?>
<div class="tabs">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="tab <?= $tab === $k ? 'active' : '' ?>" href="<?= e(url('vendor_view', ['id' => $id, 'tab' => $k])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php /* ============================ OVERVIEW / PUBLIC INFO ============================ */
if ($tab === 'overview'):
  $lead = json_decode((string)$v['leadership'], true) ?: [];
  $counts = [
    'docs' => (int)scalar('SELECT COUNT(*) FROM documents WHERE vendor_id = ?', [$id]),
    'contracts' => (int)scalar('SELECT COUNT(*) FROM contracts WHERE vendor_id = ?', [$id]),
    'breaches' => (int)scalar('SELECT COUNT(*) FROM breach_findings WHERE vendor_id = ?', [$id]),
    'news' => (int)scalar('SELECT COUNT(*) FROM news_items WHERE vendor_id = ? AND (relevant = 1 OR relevant IS NULL)', [$id]),
    'issues' => (int)scalar("SELECT COUNT(*) FROM issues WHERE vendor_id = ? AND status IN ('open','in_remediation','overdue')", [$id]),
  ];
?>
<div class="grid c4">
  <div class="card kpi"><div class="kpi-label">Risk score</div>
    <div class="kpi-value"><span data-count="<?= (int)$v['risk_score'] ?>">0</span></div>
    <div class="kpi-sub"><?= e($band['label']) ?></div><div class="kpi-ico">⚖</div></div>
  <div class="card kpi"><div class="kpi-label">Open issues</div>
    <div class="kpi-value"><span data-count="<?= $counts['issues'] ?>">0</span></div>
    <div class="kpi-sub"><a href="<?= e(url('issues', ['vendor' => $id])) ?>">View issues →</a></div><div class="kpi-ico">⚑</div></div>
  <div class="card kpi"><div class="kpi-label">Known breaches</div>
    <div class="kpi-value"><span data-count="<?= $counts['breaches'] ?>">0</span></div>
    <div class="kpi-sub">Breach &amp; dark-web tab</div><div class="kpi-ico">☠</div></div>
  <div class="card kpi"><div class="kpi-label">Adverse media</div>
    <div class="kpi-value"><span data-count="<?= $counts['news'] ?>">0</span></div>
    <div class="kpi-sub">Reputation tab</div><div class="kpi-ico">📰</div></div>
</div>

<div class="grid c2">
  <div class="card">
    <h2>Public information</h2>
    <p class="muted small">Everything you should know about this vendor in one place.</p>
    <table class="data"><tbody>
      <tr><td class="muted" style="width:42%">Legal name</td><td><?= e($v['legal_name'] ?? '—') ?></td></tr>
      <tr><td class="muted">Registration №</td><td><?= e($v['registration_no'] ?? '—') ?></td></tr>
      <tr><td class="muted">DUNS №</td><td><?= e($v['duns_number'] ?? '—') ?></td></tr>
      <tr><td class="muted">Founded</td><td><?= e($v['year_founded'] ?? '—') ?></td></tr>
      <tr><td class="muted">Headquarters</td><td><?= e(trim(($v['hq_city'] ?? '') . ', ' . ($v['country'] ?? ''), ', ') ?: '—') ?></td></tr>
      <tr><td class="muted">Employees</td><td><?= e($v['employees_band'] ?? '—') ?></td></tr>
      <tr><td class="muted">Revenue band</td><td><?= e($v['revenue_band'] ?? '—') ?></td></tr>
      <tr><td class="muted">Certifications claimed</td><td><?= e($v['certifications'] ?? '—') ?></td></tr>
      <tr><td class="muted">Sanctions / watchlist<?= hint('sanctions') ?></td>
        <td><?= $v['sanctions_status'] === 'clear' ? '<span class="badge badge-low">Clear</span>'
             : ($v['sanctions_status'] === 'flagged' ? '<span class="badge badge-critical">Flagged — review required</span>'
             : '<span class="badge badge-status">Unchecked</span>') ?></td></tr>
      <tr><td class="muted">Data accessed</td><td><?= e($v['data_accessed'] ?? '—') ?></td></tr>
      <tr><td class="muted">Next review</td><td><?= fmt_date($v['next_review_date']) ?></td></tr>
    </tbody></table>
  </div>
  <div>
    <div class="card">
      <h2>About</h2>
      <p class="muted"><?= e($v['description'] ?: 'No description recorded yet.') ?></p>
      <h3 style="margin-top:1rem">Services provided</h3>
      <p class="muted"><?= e($v['services_provided'] ?: '—') ?></p>
      <?php if ($lead): ?>
        <h3 style="margin-top:1rem">Leadership</h3>
        <?php foreach ($lead as $L): ?>
          <p class="small"><strong><?= e($L['name'] ?? '') ?></strong> <span class="muted">— <?= e($L['title'] ?? '') ?></span></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>Contacts</h2>
      <?php if (!$contacts): ?><p class="muted small">No contacts recorded.</p><?php endif; ?>
      <?php foreach ($contacts as $c): ?>
        <p class="small"><strong><?= e($c['name']) ?></strong><?= $c['is_primary'] ? ' <span class="pill" style="font-size:.65rem">Primary</span>' : '' ?><br>
          <span class="muted"><?= e($c['title'] ?? '') ?> · <?= e($c['email'] ?? '') ?> · <?= e($c['phone'] ?? '') ?></span></p>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php /* ============================ RISK SCORE ============================ */
elseif ($tab === 'score'):
  $history = rows('SELECT score, created_at FROM risk_scores WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 24', [$id]);
  $history = array_reverse($history);
  $drivers = risk_drivers($factors);
  $weights = risk_weights();
  $labels = ['assessment' => 'Assessment results', 'breach' => 'Breach exposure', 'footprint' => 'Digital footprint',
             'news' => 'Adverse media', 'compliance' => 'Compliance health', 'inherent' => 'Inherent criticality'];
?>
<div class="grid c2">
  <div class="card">
    <h2>Composite risk score<?= hint('risk_score') ?></h2>
    <?php
      $pct = $v['risk_score'] / 1000;
      $deg = (int)round(360 * $pct);
      $color = ['band-critical' => '#f87171', 'band-high' => '#fb923c', 'band-moderate' => '#fbbf24',
                'band-good' => '#a3e635', 'band-excellent' => '#34d399'][$band['class']];
    ?>
    <div class="gauge">
      <div style="width:130px;height:130px;border-radius:50%;
        background:conic-gradient(<?= $color ?> <?= $deg ?>deg, var(--bg-2) 0);display:grid;place-items:center">
        <div style="width:100px;height:100px;border-radius:50%;background:var(--card);display:grid;place-items:center">
          <div style="text-align:center"><div class="big" style="color:<?= $color ?>"><?= (int)$v['risk_score'] ?></div>
          <div class="small muted">/1000</div></div>
        </div>
      </div>
      <div>
        <span class="score-badge <?= $band['class'] ?>" style="font-size:1.05rem"><?= e($band['label']) ?></span>
        <p class="muted small" style="margin-top:.6rem">0–399 Critical · 400–599 High · 600–749 Moderate · 750–899 Good · 900–1000 Excellent.<br>
        Lower is dangerous, higher is secure.</p>
        <?php if (can('manage_vendors')): ?>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="_action" value="recalc">
          <button class="btn btn-outline btn-sm">⟳ Recalculate now</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($drivers): ?>
      <h3 style="margin-top:1.2rem">What's dragging this score down</h3>
      <?php foreach ($drivers as $dr): ?>
        <p class="small" style="margin:.4rem 0"><span class="badge badge-high"><?= e($dr['label']) ?></span>
        <span class="muted">factor at <?= e((string)$dr['value']) ?>/100</span></p>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="small muted" style="margin-top:1rem">No weak factors — all categories at 70+/100. 💪</p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Factor breakdown <span class="muted small">(transparent weighted model)</span></h2>
    <?php foreach ($weights as $k => $w): $val = $factors[$k] ?? 0; ?>
      <div style="margin-bottom:.8rem">
        <div style="display:flex;justify-content:space-between" class="small">
          <span><?= e($labels[$k]) ?> <span class="muted">(<?= (int)($w * 100) ?>%)</span></span>
          <strong><?= e((string)$val) ?>/100</strong>
        </div>
        <div class="meter"><span data-w="<?= (int)$val ?>"></span></div>
      </div>
    <?php endforeach; ?>
    <p class="help">Composite = Σ(factor × weight) × 10. The full methodology is documented in the User Guide.</p>
  </div>
</div>
<div class="card">
  <h2>Score history</h2>
  <?php if (count($history) > 1): ?>
    <div class="barchart">
      <?php foreach ($history as $h): $hb = risk_band((int)$h['score']); ?>
        <div class="bar" style="height:<?= max(3, (int)($h['score'] / 10)) ?>%"
             title="<?= e(date('M j, Y', strtotime($h['created_at']))) ?>: <?= (int)$h['score'] ?>"></div>
      <?php endforeach; ?>
    </div>
    <p class="small muted" style="margin-top:.5rem"><?= e(date('M Y', strtotime($history[0]['created_at']))) ?> → today · hover bars for values</p>
  <?php else: ?>
    <p class="muted">Not enough history yet — scores are recorded every time they're recalculated.</p>
  <?php endif; ?>
</div>

<?php /* ============================ BREACH ============================ */
elseif ($tab === 'breach'):
  $findings = rows('SELECT * FROM breach_findings WHERE vendor_id = ? ORDER BY breach_date DESC', [$id]);
  $lastScan = scalar('SELECT MAX(scanned_at) FROM breach_findings WHERE vendor_id = ?', [$id]);
  $isDemo = (bool)scalar('SELECT COUNT(*) FROM breach_findings WHERE vendor_id = ? AND source = "demo"', [$id]);
?>
<div class="card">
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
    <div>
      <h2>Breach exposure &amp; dark-web signals<?= hint('demo_mode') ?></h2>
      <p class="muted small">Known breach history, exposed-credential indicators and paste/leak mentions for
        <strong><?= e($v['domain'] ?: $v['name']) ?></strong>.
        <?= $lastScan ? 'Last scanned ' . e(date('M j, Y H:i', strtotime($lastScan))) . ' UTC.' : 'Never scanned.' ?>
        <?php if ($isDemo): ?><span class="demo-tag">DEMO MODE</span>
        <?php elseif ($findings): ?><span class="live-tag">LIVE</span><?php endif; ?>
      </p>
    </div>
    <div class="spacer"></div>
    <?php if (can('run_scans')): ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="scan_breach">
      <button class="btn btn-gold">⟲ Run scan</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if (!setting('hibp_api_key')): ?>
    <div class="flash flash-info" style="margin-top:.8rem">No HaveIBeenPwned API key configured — scans run in
      <strong>Demo Mode</strong> with realistic sample data. Add a key under Settings → Integrations for live results.</div>
  <?php endif; ?>
</div>
<?php if (!$findings): ?>
  <div class="card empty-state"><div class="empty-icon">🛡️</div><h3>No breach findings</h3>
  <p class="muted">Run a scan to check this vendor's exposure.</p></div>
<?php else: foreach ($findings as $f): ?>
  <div class="card">
    <div style="display:flex;gap:.8rem;align-items:baseline;flex-wrap:wrap">
      <h3 style="margin:0"><?= e($f['breach_name']) ?></h3>
      <span class="badge badge-<?= e($f['severity'] === 'critical' ? 'critical' : ($f['severity'] === 'high' ? 'high' : ($f['severity'] === 'medium' ? 'medium' : 'low'))) ?>"><?= e(ucfirst($f['severity'])) ?></span>
      <span class="muted small"><?= fmt_date($f['breach_date']) ?></span>
      <?= $f['source'] === 'demo' ? '<span class="demo-tag">DEMO</span>' : '<span class="live-tag">' . e(strtoupper($f['source'])) . '</span>' ?>
    </div>
    <p class="muted small" style="margin-top:.4rem"><?= e($f['description'] ?? '') ?></p>
    <p class="small"><strong>Records exposed:</strong> <?= $f['records_exposed'] ? number_format((float)$f['records_exposed']) : 'Unknown' ?>
       · <strong>Data classes:</strong> <?= e($f['data_classes'] ?: 'Unknown') ?>
       <?php if ((int)$f['dark_web_mentions'] > 0): ?> · <strong>Dark-web mentions:</strong> <?= (int)$f['dark_web_mentions'] ?><?php endif; ?></p>
  </div>
<?php endforeach; endif; ?>

<?php /* ============================ FOOTPRINT ============================ */
elseif ($tab === 'footprint'):
  $fps = rows('SELECT * FROM footprint_findings WHERE vendor_id = ? ORDER BY FIELD(status,"fail","warn","pass","info"), category', [$id]);
  $lastScan = scalar('SELECT MAX(scanned_at) FROM footprint_findings WHERE vendor_id = ?', [$id]);
  $isDemo = false;
  foreach ($fps as $f) if (str_starts_with((string)$f['detail'], '[Demo]')) { $isDemo = true; break; }
?>
<div class="card">
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
    <div>
      <h2>Digital footprint <span class="muted small">(non-intrusive)</span></h2>
      <p class="muted small">Passive intelligence only: DNS, email security (SPF/DKIM/DMARC), TLS, security headers,
        certificate-transparency subdomains. We never probe or scan vendor infrastructure.
        <?= $lastScan ? 'Last scanned ' . e(date('M j, Y H:i', strtotime($lastScan))) . ' UTC.' : '' ?>
        <?= $isDemo ? '<span class="demo-tag">DEMO MODE</span>' : ($fps ? '<span class="live-tag">LIVE</span>' : '') ?>
      </p>
    </div>
    <div class="spacer"></div>
    <?php if (can('run_scans')): ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="scan_footprint">
      <button class="btn btn-gold">⟲ Run scan</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php if (!$fps): ?>
  <div class="card empty-state"><div class="empty-icon">🌐</div><h3>No footprint data yet</h3>
  <p class="muted">Run a scan to map this vendor's public attack surface.</p></div>
<?php else: ?>
  <div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Status</th><th>Check</th><th>Category</th><th>Detail</th></tr></thead><tbody>
    <?php foreach ($fps as $f):
      $icon = ['pass' => '<span class="badge badge-low">PASS</span>', 'warn' => '<span class="badge badge-medium">WARN</span>',
               'fail' => '<span class="badge badge-critical">FAIL</span>', 'info' => '<span class="badge badge-status">INFO</span>'][$f['status']];
    ?>
      <tr><td><?= $icon ?></td><td><strong><?= e($f['item']) ?></strong></td>
          <td class="muted"><?= e(str_replace('_', ' ', $f['category'])) ?></td>
          <td class="muted small"><?= e($f['detail'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>

<?php /* ============================ NEWS ============================ */
elseif ($tab === 'news'):
  $items = rows('SELECT * FROM news_items WHERE vendor_id = ? ORDER BY published_date DESC LIMIT 50', [$id]);
?>
<div class="card">
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
    <div>
      <h2>Reputation &amp; adverse media</h2>
      <p class="muted small">The most relevant negative news from the last 10 years — breaches, lawsuits, fines,
        financial distress, sanctions, leadership issues. Mark items relevant / not relevant; only relevant items affect the risk score.</p>
    </div>
    <div class="spacer"></div>
    <?php if (can('run_scans')): ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="scan_news">
      <button class="btn btn-gold">⟲ Scan news</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if (!setting('newsapi_key')): ?>
    <div class="flash flash-info" style="margin-top:.8rem">No news API key configured — running in <strong>Demo Mode</strong>.
      Add a NewsAPI.org key under Settings → Integrations for live adverse-media monitoring.</div>
  <?php endif; ?>
</div>
<?php if (!$items): ?>
  <div class="card empty-state"><div class="empty-icon">📰</div><h3>No adverse media on file</h3>
  <p class="muted">That's a good sign — run a scan to double-check.</p></div>
<?php else: foreach ($items as $n): ?>
  <div class="card tight" style="<?= $n['relevant'] === '0' || $n['relevant'] === 0 ? 'opacity:.45' : '' ?>">
    <div style="display:flex;gap:.8rem;align-items:baseline;flex-wrap:wrap">
      <strong><?= $n['url'] ? '<a href="' . e($n['url']) . '" target="_blank" rel="noopener">' . e($n['headline']) . '</a>' : e($n['headline']) ?></strong>
      <span class="badge badge-<?= $n['severity'] === 'high' ? 'critical' : ($n['severity'] === 'medium' ? 'high' : 'medium') ?>"><?= e(ucfirst($n['severity'])) ?></span>
      <span class="badge badge-status"><?= e(ucfirst($n['category'])) ?></span>
      <span class="muted small"><?= fmt_date($n['published_date']) ?> · <?= e($n['source'] ?? '') ?></span>
      <div class="spacer"></div>
      <?php if (can('manage_vendors')): ?>
      <form method="post" style="display:flex;gap:.4rem"><?= csrf_field() ?>
        <input type="hidden" name="_action" value="news_disposition">
        <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
        <button class="btn btn-sm <?= $n['relevant'] === null || (int)$n['relevant'] === 1 ? 'btn-outline' : 'btn-ghost' ?>" name="relevant" value="1">Relevant</button>
        <button class="btn btn-sm btn-ghost" name="relevant" value="0">Not relevant</button>
      </form>
      <?php endif; ?>
    </div>
    <p class="muted small" style="margin:.35rem 0 0"><?= e($n['summary'] ?? '') ?></p>
  </div>
<?php endforeach; endif; ?>

<?php /* ============================ ASSESSMENTS ============================ */
elseif ($tab === 'assessments'):
  $as = rows('SELECT a.*, t.name tpl FROM assessments a JOIN assessment_templates t ON t.id = a.template_id
              WHERE a.vendor_id = ? ORDER BY a.created_at DESC', [$id]);
?>
<div class="card">
  <div style="display:flex;align-items:center;gap:1rem">
    <h2 style="margin:0">Assessments</h2>
    <div class="spacer"></div>
    <?php if (can('manage_assessments')): ?>
      <a class="btn btn-gold" href="<?= e(url('assessment_new', ['vendor' => $id])) ?>">+ Send questionnaire</a>
    <?php endif; ?>
  </div>
</div>
<?php if (!$as): ?>
  <div class="card empty-state"><div class="empty-icon">✎</div><h3>No assessments yet</h3>
  <p class="muted">Send this vendor a security questionnaire to start due diligence.</p></div>
<?php else: ?>
  <div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Title</th><th>Template</th><th>Status</th><th>Round</th><th>Score</th><th>Due</th><th></th></tr></thead><tbody>
    <?php foreach ($as as $a): ?>
      <tr>
        <td><a class="rowlink" href="<?= e(url('assessment_review', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a></td>
        <td class="muted"><?= e($a['tpl']) ?></td>
        <td><?= status_badge($a['status']) ?></td>
        <td class="muted"><?= (int)$a['round'] ?></td>
        <td><?= $a['score'] !== null ? (int)$a['score'] . '/100' : '—' ?></td>
        <td class="muted"><?= fmt_date($a['due_date']) ?></td>
        <td><a class="btn btn-sm btn-ghost" href="<?= e(url('assessment_review', ['id' => $a['id']])) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>

<?php /* ============================ DOCUMENTS (vendor-scoped) ============================ */
elseif ($tab === 'documents'):
  $docs = rows('SELECT * FROM documents WHERE vendor_id = ? ORDER BY created_at DESC', [$id]);
?>
<div class="card">
  <div style="display:flex;align-items:center;gap:1rem">
    <h2 style="margin:0">Documents</h2>
    <div class="spacer"></div>
    <a class="btn btn-gold" href="<?= e(url('documents', ['vendor' => $id])) ?>">Manage in Documents Library →</a>
  </div>
</div>
<div class="card"><div class="table-wrap"><table class="data">
  <thead><tr><th>Title</th><th>Category</th><th>Version</th><th>Size</th><th>Expiry</th><th>Uploaded</th></tr></thead><tbody>
  <?php if (!$docs): ?><tr><td colspan="6" class="muted">No documents on file for this vendor.</td></tr><?php endif; ?>
  <?php foreach ($docs as $doc): $du = days_until($doc['expiry_date']); ?>
    <tr>
      <td><strong><?= e($doc['title']) ?></strong></td>
      <td><span class="badge badge-status"><?= e($doc['category']) ?></span></td>
      <td class="muted">v<?= (int)$doc['version'] ?></td>
      <td class="muted"><?= fmt_bytes((int)$doc['size_bytes']) ?></td>
      <td><?= fmt_date($doc['expiry_date']) ?>
        <?php if ($du !== null && $du < 0): ?> <span class="badge badge-critical">Expired</span>
        <?php elseif ($du !== null && $du <= 30): ?> <span class="badge badge-high"><?= $du ?>d left</span><?php endif; ?></td>
      <td class="muted"><?= fmt_date($doc['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
</tbody></table></div></div>

<?php /* ============================ CONTRACTS (vendor-scoped) ============================ */
elseif ($tab === 'contracts'):
  $cons = rows('SELECT * FROM contracts WHERE vendor_id = ? ORDER BY end_date DESC', [$id]);
?>
<div class="card">
  <div style="display:flex;align-items:center;gap:1rem">
    <h2 style="margin:0">Contracts</h2>
    <div class="spacer"></div>
    <a class="btn btn-gold" href="<?= e(url('contracts', ['vendor' => $id])) ?>">Manage in Contracts →</a>
  </div>
</div>
<div class="card"><div class="table-wrap"><table class="data">
  <thead><tr><th>Contract</th><th>Type</th><th>Value</th><th>Term</th><th>Key clauses</th><th>Status</th></tr></thead><tbody>
  <?php if (!$cons): ?><tr><td colspan="6" class="muted">No contracts on file. That alone lowers the compliance factor of the risk score.</td></tr><?php endif; ?>
  <?php foreach ($cons as $c): ?>
    <tr>
      <td><strong><?= e($c['title']) ?></strong></td>
      <td class="muted"><?= e($c['contract_type']) ?></td>
      <td class="muted"><?= $c['value_amount'] !== null ? e($c['currency']) . ' ' . number_format((float)$c['value_amount']) : '—' ?></td>
      <td class="muted"><?= fmt_date($c['start_date']) ?> → <?= fmt_date($c['end_date']) ?>
        <?php $du = days_until($c['end_date']); if ($du !== null && $du >= 0 && $du <= 60): ?>
          <span class="badge badge-high"><?= $du ?>d left</span><?php endif; ?></td>
      <td class="small">
        <?= $c['clause_right_to_audit'] ? '✅' : '❌' ?> Audit&nbsp;
        <?= $c['clause_data_protection'] ? '✅' : '❌' ?> DP&nbsp;
        <?= $c['clause_termination'] ? '✅' : '❌' ?> Term&nbsp;
        <?= $c['clause_sla'] ? '✅' : '❌' ?> SLA</td>
      <td><?= status_badge($c['status']) ?></td>
    </tr>
  <?php endforeach; ?>
</tbody></table></div></div>

<?php /* ============================ FOURTH PARTIES ============================ */
elseif ($tab === 'fourth'):
  $fps = rows('SELECT * FROM fourth_parties WHERE vendor_id = ? ORDER BY FIELD(criticality,"critical","high","medium","low")', [$id]);
?>
<div class="grid c2">
  <div class="card">
    <h2>Fourth parties <span class="muted small">(your vendor's vendors)</span><?= hint('fourth_party') ?></h2>
    <p class="muted small">Risks you inherit from subcontractors and sub-processors this vendor relies on.</p>
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Name</th><th>Service</th><th>Criticality</th><th></th></tr></thead><tbody>
      <?php if (!$fps): ?><tr><td colspan="4" class="muted">None recorded. Ask in your next assessment.</td></tr><?php endif; ?>
      <?php foreach ($fps as $f): ?>
        <tr><td><strong><?= e($f['name']) ?></strong><div class="small muted"><?= e($f['notes'] ?? '') ?></div></td>
            <td class="muted"><?= e($f['service'] ?? '—') ?></td>
            <td><?= tier_badge($f['criticality']) ?></td>
            <td><?php if (can('manage_vendors')): ?>
              <form method="post" data-confirm="Remove this fourth party?"><?= csrf_field() ?>
                <input type="hidden" name="_action" value="del_fourth">
                <input type="hidden" name="fp_id" value="<?= (int)$f['id'] ?>">
                <button class="btn btn-sm btn-danger">✕</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
  <?php if (can('manage_vendors')): ?>
  <div class="card">
    <h2>Add a fourth party</h2>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="add_fourth">
      <label>Name *</label><input type="text" name="fp_name" required>
      <label>Service provided</label><input type="text" name="fp_service" placeholder="e.g. Cloud hosting">
      <label>Criticality</label>
      <select name="fp_crit"><option value="medium">Medium</option><option value="critical">Critical</option>
        <option value="high">High</option><option value="low">Low</option></select>
      <label>Notes</label><input type="text" name="fp_notes">
      <button class="btn btn-gold" style="margin-top:1rem">+ Add</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php /* ============================ NOTES & ACTIVITY ============================ */
else:
  $notes = rows('SELECT * FROM vendor_notes WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 50', [$id]);
  $activity = rows('SELECT * FROM audit_log WHERE entity = "vendor" AND entity_id = ? ORDER BY created_at DESC LIMIT 30', [$id]);
?>
<div class="grid c2">
  <div class="card">
    <h2>Analyst notes</h2>
    <?php if (can('manage_vendors')): ?>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="add_note">
      <textarea name="note" placeholder="Record a decision, call summary, or observation…" required></textarea>
      <button class="btn btn-gold btn-sm" style="margin-top:.6rem">Add note</button>
    </form>
    <?php endif; ?>
    <div style="margin-top:1rem">
      <?php if (!$notes): ?><p class="muted small">No notes yet.</p><?php endif; ?>
      <?php foreach ($notes as $n): ?>
        <div class="card tight" style="background:var(--bg-2);margin-bottom:.6rem">
          <p style="margin:0"><?= nl2br(e($n['note'])) ?></p>
          <p class="small muted" style="margin:.3rem 0 0">— <?= e($n['user_name'] ?? 'Unknown') ?>, <?= e(date('M j, Y H:i', strtotime($n['created_at']))) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h2>Activity trail</h2>
    <ul class="timeline">
      <?php if (!$activity): ?><li class="muted small">No recorded activity.</li><?php endif; ?>
      <?php foreach ($activity as $a): ?>
        <li><strong><?= e(str_replace('_', ' ', $a['action'])) ?></strong>
          <span class="muted small"> — <?= e($a['user_name'] ?? 'System') ?>, <?= e(date('M j, Y H:i', strtotime($a['created_at']))) ?></span>
          <?php if ($a['detail']): ?><div class="small muted"><?= e($a['detail']) ?></div><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php';
