<?php
/**
 * Bulk vendor upload — CSV with row-by-row validation, duplicate detection,
 * partial import and a downloadable error report. Chunk-safe for 1,000+ rows.
 */
require_perm('manage_vendors');
$GLOBALS['VA_PAGE_TITLE'] = 'Bulk upload';

$TEMPLATE_COLS = ['name','legal_name','website','industry','country','hq_city','description',
                  'services_provided','data_accessed','tier','contact_name','contact_email'];

// --- Serve the CSV template
if (isset($_GET['template'])) {
    csv_download('vendor_import_template.csv', $TEMPLATE_COLS, [[
        'Acme Analytics','Acme Analytics Ltd','https://acme-analytics.com','Data & Analytics',
        'United States','Austin','BI dashboards provider','Business intelligence SaaS','PII','high',
        'Jane Doe','jane@acme-analytics.com',
    ]]);
}

// --- Serve the error report from the last run
if (isset($_GET['errors']) && !empty($_SESSION['import_errors'])) {
    csv_download('vendor_import_errors.csv', ['row','problem'], $_SESSION['import_errors']);
}

$report = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(300); // big files on slow laptops
    $ok = 0; $failed = []; $rowNum = 1;
    try {
        if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No file received — choose a CSV file first (max 15 MB).');
        }
        $ext = strtolower(pathinfo((string)$_FILES['csv']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') throw new RuntimeException('Please upload a .csv file (download the template below).');

        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) throw new RuntimeException('Could not read the uploaded file.');
        $header = fgetcsv($fh);
        if (!$header) throw new RuntimeException('The file is empty.');
        // BOM tolerance + lowercase header map
        $header = array_map(fn($h) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string)$h))), $header);
        if (!in_array('name', $header, true)) {
            throw new RuntimeException('Missing required column "name". Use the provided template.');
        }
        $idx = array_flip($header);
        $get = fn(array $r, string $col) => isset($idx[$col], $r[$idx[$col]]) ? trim((string)$r[$idx[$col]]) : '';

        $existing = [];
        foreach (rows('SELECT LOWER(name) n FROM vendors') as $r) $existing[$r['n']] = true;

        $ins = db()->prepare('INSERT INTO vendors (name, legal_name, website, domain, industry, country, hq_city,
            description, services_provided, data_accessed, tier, lifecycle, inherent_risk, risk_score, created_by, next_review_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insC = db()->prepare('INSERT INTO vendor_contacts (vendor_id, name, email, is_primary) VALUES (?,?,?,1)');

        db()->beginTransaction();
        $batch = 0;
        while (($r = fgetcsv($fh)) !== false) {
            $rowNum++;
            if (count($r) === 1 && trim((string)$r[0]) === '') continue; // blank line
            $name = $get($r, 'name');
            if ($name === '') { $failed[] = [$rowNum, 'Missing vendor name']; continue; }
            if (mb_strlen($name) > 190) { $failed[] = [$rowNum, 'Name longer than 190 characters']; continue; }
            if (isset($existing[mb_strtolower($name)])) { $failed[] = [$rowNum, 'Duplicate — vendor already exists: ' . $name]; continue; }
            $tier = strtolower($get($r, 'tier'));
            if ($tier !== '' && !in_array($tier, ['critical','high','medium','low'], true)) {
                $failed[] = [$rowNum, 'Invalid tier "' . $tier . '" (use critical/high/medium/low or leave blank)'];
                continue;
            }
            $email = $get($r, 'contact_email');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed[] = [$rowNum, 'Invalid contact_email: ' . $email]; continue;
            }
            $website = $get($r, 'website');
            if ($website !== '' && !preg_match('#^https?://#i', $website)) $website = 'https://' . $website;
            $domain = $website ? preg_replace('/^www\./', '', strtolower((string)parse_url($website, PHP_URL_HOST))) : null;
            $inherent = ['critical' => 75, 'high' => 60, 'medium' => 40, 'low' => 20][$tier ?: 'medium'] ?? 40;

            $ins->execute([$name, $get($r,'legal_name') ?: null, $website ?: null, $domain ?: null,
                $get($r,'industry') ?: null, $get($r,'country') ?: null, $get($r,'hq_city') ?: null,
                $get($r,'description') ?: null, $get($r,'services_provided') ?: null,
                $get($r,'data_accessed') ?: null, $tier ?: 'medium', 'onboarding', $inherent, 500,
                current_user()['id'], date('Y-m-d', strtotime('+1 year'))]);
            $vid = (int)db()->lastInsertId();
            if ($get($r, 'contact_name') !== '' || $email !== '') {
                $insC->execute([$vid, $get($r,'contact_name') ?: 'Primary contact', $email ?: null]);
            }
            $existing[mb_strtolower($name)] = true;
            $ok++;
            // commit in chunks of 200 so a 1,000-row file never times out in one giant transaction
            if (++$batch >= 200) { db()->commit(); db()->beginTransaction(); $batch = 0; }
        }
        if (db()->inTransaction()) db()->commit();
        fclose($fh);

        $_SESSION['import_errors'] = $failed;
        audit('bulk_import', 'vendor', null, "$ok imported, " . count($failed) . ' failed');
        $report = ['ok' => $ok, 'failed' => $failed];
        if ($ok > 0) flash('success', "$ok vendor(s) imported successfully.");
    } catch (RuntimeException $e) {
        if (db()->inTransaction()) db()->rollBack();
        $report = ['ok' => $ok, 'failed' => $failed, 'fatal' => $e->getMessage()];
    }
}

include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Bulk vendor upload</h1>
  <div class="spacer"></div>
  <a class="btn btn-ghost" href="<?= e(url('vendors')) ?>">← Back to vendors</a>
</div>

<div class="grid c2">
  <div class="card">
    <h2>1 · Download the template<?= hint('bulk_import') ?></h2>
    <p class="muted small">Fill one vendor per row. Only <strong>name</strong> is mandatory.
      Valid tiers: critical, high, medium, low (blank = medium). Tested with 1,000+ rows.</p>
    <a class="btn btn-outline" href="<?= e(url('vendor_import', ['template' => 1])) ?>">⇓ Download CSV template</a>
  </div>
  <div class="card">
    <h2>2 · Upload your file</h2>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('vendor_import')) ?>">
      <?= csrf_field() ?>
      <label>CSV file (max 15 MB)</label>
      <input type="file" name="csv" accept=".csv" required>
      <button class="btn btn-gold" style="margin-top:1rem">⇪ Validate &amp; import</button>
    </form>
    <p class="help">Rows with problems are skipped — good rows still import (partial import). You'll get a precise error report.</p>
  </div>
</div>

<?php if ($report !== null): ?>
<div class="card">
  <h2>Import result</h2>
  <?php if (!empty($report['fatal'])): ?>
    <div class="flash flash-error"><?= e($report['fatal']) ?></div>
  <?php endif; ?>
  <div class="grid c3">
    <div class="card tight kpi"><div class="kpi-label">Imported</div><div class="kpi-value" style="color:var(--green)"><?= (int)$report['ok'] ?></div></div>
    <div class="card tight kpi"><div class="kpi-label">Skipped</div><div class="kpi-value" style="color:var(--red)"><?= count($report['failed']) ?></div></div>
    <div class="card tight kpi"><div class="kpi-label">Error report</div>
      <?php if ($report['failed']): ?>
        <a class="btn btn-outline btn-sm" style="margin-top:.4rem" href="<?= e(url('vendor_import', ['errors' => 1])) ?>">⇓ Download CSV</a>
      <?php else: ?><div class="kpi-sub">No errors 🎉</div><?php endif; ?>
    </div>
  </div>
  <?php if ($report['failed']): ?>
    <div class="table-wrap"><table class="data">
      <thead><tr><th style="width:90px">Row</th><th>Problem</th></tr></thead><tbody>
      <?php foreach (array_slice($report['failed'], 0, 50) as $f): ?>
        <tr><td><?= (int)$f[0] ?></td><td class="muted"><?= e($f[1]) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php if (count($report['failed']) > 50): ?>
      <p class="muted small">Showing first 50 — download the full report above.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php';
