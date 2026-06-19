<?php
/** Edit vendor master data (public-information fields included). */
require_perm('manage_vendors');
$id = (int)($_GET['id'] ?? 0);
$v = row('SELECT * FROM vendors WHERE id = ?', [$id]);
if (!$v) { flash('error', 'Vendor not found.'); redirect('vendors'); }
$GLOBALS['VA_PAGE_TITLE'] = 'Edit · ' . $v['name'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['_action'] ?? '') === 'delete') {
        q('DELETE FROM vendors WHERE id = ?', [$id]);
        audit('vendor_deleted', 'vendor', $id, $v['name']);
        flash('success', 'Vendor "' . $v['name'] . '" and all related records were deleted.');
        redirect('vendors');
    }
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') $errors[] = 'Vendor name is required.';
    $dup = row('SELECT id FROM vendors WHERE name = ? AND id <> ?', [$name, $id]);
    if ($dup) $errors[] = 'Another vendor already uses this name.';
    $website = trim((string)($_POST['website'] ?? ''));
    if ($website !== '' && !preg_match('#^https?://#i', $website)) $website = 'https://' . $website;
    $domain = $website ? preg_replace('/^www\./', '', strtolower((string)parse_url($website, PHP_URL_HOST))) : null;
    $tier = in_array($_POST['tier'] ?? '', ['critical','high','medium','low'], true) ? $_POST['tier'] : $v['tier'];
    $sanc = in_array($_POST['sanctions_status'] ?? '', ['clear','flagged','unchecked'], true) ? $_POST['sanctions_status'] : 'unchecked';
    $yr = (int)($_POST['year_founded'] ?? 0); if ($yr < 1700 || $yr > (int)date('Y')) $yr = null;
    $nr = trim((string)($_POST['next_review_date'] ?? '')) ?: null;

    if (!$errors) {
        q('UPDATE vendors SET name=?, legal_name=?, website=?, domain=?, industry=?, country=?, hq_city=?,
           description=?, services_provided=?, data_accessed=?, employees_band=?, revenue_band=?, year_founded=?,
           registration_no=?, duns_number=?, certifications=?, sanctions_status=?, tier=?, next_review_date=? WHERE id=?',
          [$name, trim((string)($_POST['legal_name'] ?? '')) ?: null, $website ?: null, $domain ?: null,
           trim((string)($_POST['industry'] ?? '')) ?: null, trim((string)($_POST['country'] ?? '')) ?: null,
           trim((string)($_POST['hq_city'] ?? '')) ?: null, trim((string)($_POST['description'] ?? '')) ?: null,
           trim((string)($_POST['services_provided'] ?? '')) ?: null, trim((string)($_POST['data_accessed'] ?? '')) ?: null,
           trim((string)($_POST['employees_band'] ?? '')) ?: null, trim((string)($_POST['revenue_band'] ?? '')) ?: null,
           $yr, trim((string)($_POST['registration_no'] ?? '')) ?: null, trim((string)($_POST['duns_number'] ?? '')) ?: null,
           trim((string)($_POST['certifications'] ?? '')) ?: null, $sanc, $tier, $nr, $id]);
        audit('vendor_updated', 'vendor', $id, $name);
        recompute_risk($id);
        flash('success', 'Vendor updated.');
        redirect('vendor_view', ['id' => $id]);
    }
}

include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Edit vendor</h1>
  <div class="spacer"></div>
  <a class="btn btn-ghost" href="<?= e(url('vendor_view', ['id' => $id])) ?>">← Back to profile</a>
</div>
<?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>
<div class="card" style="max-width:860px">
  <form method="post"><?= csrf_field() ?>
    <h2>Identity</h2>
    <div class="form-row">
      <div><label>Name *</label><input type="text" name="name" required value="<?= e($v['name']) ?>"></div>
      <div><label>Legal name</label><input type="text" name="legal_name" value="<?= e($v['legal_name'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Website</label><input type="text" name="website" value="<?= e($v['website'] ?? '') ?>"></div>
      <div><label>Industry</label><input type="text" name="industry" value="<?= e($v['industry'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Country</label><input type="text" name="country" value="<?= e($v['country'] ?? '') ?>"></div>
      <div><label>HQ city</label><input type="text" name="hq_city" value="<?= e($v['hq_city'] ?? '') ?>"></div>
    </div>
    <h2 style="margin-top:1.3rem">Public information</h2>
    <div class="form-row">
      <div><label>Registration №</label><input type="text" name="registration_no" value="<?= e($v['registration_no'] ?? '') ?>"></div>
      <div><label>DUNS №</label><input type="text" name="duns_number" value="<?= e($v['duns_number'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Year founded</label><input type="number" name="year_founded" value="<?= e($v['year_founded'] ?? '') ?>"></div>
      <div><label>Employees band</label><input type="text" name="employees_band" placeholder="e.g. 201-1000" value="<?= e($v['employees_band'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Revenue band</label><input type="text" name="revenue_band" placeholder="e.g. $50M-$250M" value="<?= e($v['revenue_band'] ?? '') ?>"></div>
      <div><label>Certifications claimed</label><input type="text" name="certifications" placeholder="ISO 27001, SOC 2…" value="<?= e($v['certifications'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Sanctions / watchlist status</label>
        <select name="sanctions_status">
          <?php foreach (['clear' => 'Clear', 'flagged' => 'Flagged', 'unchecked' => 'Unchecked'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $v['sanctions_status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?></select></div>
      <div><label>Criticality tier</label>
        <select name="tier"><?php foreach (['critical','high','medium','low'] as $t): ?>
          <option value="<?= $t ?>" <?= $v['tier'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
    </div>
    <h2 style="margin-top:1.3rem">Engagement</h2>
    <label>Description</label><textarea name="description"><?= e($v['description'] ?? '') ?></textarea>
    <label>Services provided</label><textarea name="services_provided"><?= e($v['services_provided'] ?? '') ?></textarea>
    <div class="form-row">
      <div><label>Data accessed</label><input type="text" name="data_accessed" placeholder="PII, PCI, PHI…" value="<?= e($v['data_accessed'] ?? '') ?>"></div>
      <div><label>Next review date</label><input type="date" name="next_review_date" value="<?= e($v['next_review_date'] ?? '') ?>"></div>
    </div>
    <button class="btn btn-gold" style="margin-top:1.3rem">✓ Save changes</button>
  </form>
</div>
<div class="card" style="max-width:860px;border-color:rgba(248,113,113,.35)">
  <h3 style="color:var(--red)">Danger zone</h3>
  <p class="muted small">Deleting a vendor permanently removes all of their documents, contracts, assessments, findings and history.</p>
  <form method="post" data-confirm="Permanently delete <?= e($v['name']) ?> and ALL related records? This cannot be undone.">
    <?= csrf_field() ?><input type="hidden" name="_action" value="delete">
    <button class="btn btn-danger">Delete this vendor</button>
  </form>
</div>
<?php include __DIR__ . '/../partials/footer.php';
