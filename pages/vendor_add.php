<?php
/**
 * Intelligent vendor onboarding — 4-step wizard.
 * Steps live in the session; the inherent-risk answers auto-suggest a tier.
 */
require_perm('manage_vendors');
$GLOBALS['VA_PAGE_TITLE'] = 'Add vendor';

$wiz = &$_SESSION['vendor_wizard'];
if (!is_array($wiz ?? null) || isset($_GET['restart'])) {
    $wiz = ['step' => 1, 'data' => []];
    if (isset($_GET['restart'])) redirect('vendor_add');
}
$step = $wiz['step'];
$d = $wiz['data'];
$errors = [];

/** Inherent-risk model: each answer adds points; 0-100 total. */
function inherent_points(array $a): int {
    $pts = 0;
    $pts += ['None' => 0, 'Internal' => 8, 'Confidential' => 16, 'PII' => 24, 'PCI' => 28, 'PHI' => 30][$a['ir_data'] ?? 'None'] ?? 0;
    $pts += ['no' => 0, 'yes' => 20][$a['ir_critical'] ?? 'no'] ?? 0;
    $pts += ['no' => 0, 'yes' => 15][$a['ir_network'] ?? 'no'] ?? 0;
    $pts += ['no' => 0, 'yes' => 10][$a['ir_regulated'] ?? 'no'] ?? 0;
    $pts += ['none' => 0, 'few' => 8, 'many' => 15][$a['ir_subs'] ?? 'none'] ?? 0;
    $pts += ['easy' => 0, 'moderate' => 5, 'hard' => 10][$a['ir_replace'] ?? 'easy'] ?? 0;
    return min(100, $pts);
}
function suggested_tier(int $pts): string {
    if ($pts >= 70) return 'critical';
    if ($pts >= 45) return 'high';
    if ($pts >= 25) return 'medium';
    return 'low';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $go = $_POST['_go'] ?? 'next';
    if ($go === 'back') {
        $wiz['step'] = max(1, $step - 1);
        redirect('vendor_add');
    }

    if ($step === 1) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') $errors[] = 'Vendor name is required.';
        $website = trim((string)($_POST['website'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) $website = 'https://' . $website;
        $dup = $name !== '' ? row('SELECT id FROM vendors WHERE name = ?', [$name]) : null;
        if ($dup) $errors[] = 'A vendor with this exact name already exists.';
        if (!$errors) {
            $domain = '';
            if ($website !== '') $domain = strtolower((string)parse_url($website, PHP_URL_HOST));
            $domain = preg_replace('/^www\./', '', $domain ?? '');
            $wiz['data'] = array_merge($d, [
                'name' => $name, 'legal_name' => trim((string)($_POST['legal_name'] ?? '')),
                'website' => $website, 'domain' => $domain,
                'industry' => trim((string)($_POST['industry'] ?? '')),
                'country' => trim((string)($_POST['country'] ?? '')),
                'hq_city' => trim((string)($_POST['hq_city'] ?? '')),
                'description' => trim((string)($_POST['description'] ?? '')),
            ]);
            $wiz['step'] = 2;
            redirect('vendor_add');
        }
    } elseif ($step === 2) {
        $wiz['data'] = array_merge($d, [
            'contact_name' => trim((string)($_POST['contact_name'] ?? '')),
            'contact_title' => trim((string)($_POST['contact_title'] ?? '')),
            'contact_email' => trim((string)($_POST['contact_email'] ?? '')),
            'contact_phone' => trim((string)($_POST['contact_phone'] ?? '')),
        ]);
        if ($wiz['data']['contact_email'] !== '' && !filter_var($wiz['data']['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact email is not valid.';
        } else { $wiz['step'] = 3; redirect('vendor_add'); }
    } elseif ($step === 3) {
        $wiz['data'] = array_merge($d, [
            'services_provided' => trim((string)($_POST['services_provided'] ?? '')),
            'ir_data' => $_POST['ir_data'] ?? 'None',
            'ir_critical' => $_POST['ir_critical'] ?? 'no',
            'ir_network' => $_POST['ir_network'] ?? 'no',
            'ir_regulated' => $_POST['ir_regulated'] ?? 'no',
            'ir_subs' => $_POST['ir_subs'] ?? 'none',
            'ir_replace' => $_POST['ir_replace'] ?? 'easy',
        ]);
        $wiz['step'] = 4;
        redirect('vendor_add');
    } elseif ($step === 4) {
        $tier = in_array($_POST['tier'] ?? '', ['critical','high','medium','low'], true)
              ? $_POST['tier'] : suggested_tier(inherent_points($d));
        $pts = inherent_points($d);
        // Expert sanity check: overriding 2+ levels BELOW the suggested tier is unusual
        // (e.g. PHI data + business-critical, but tiered "Low"). Allowed, but flagged.
        $rank = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $sug = suggested_tier($pts);
        if ($rank[$tier] <= $rank[$sug] - 2) {
            flash('info', 'Heads-up: you tiered this vendor "' . ucfirst($tier) . '" while their answers suggest "'
                . ucfirst($sug) . '". That is allowed, but unusual — add a note on the vendor profile explaining why, '
                . 'so auditors (and future you) understand the decision.');
        }
        q('INSERT INTO vendors (name, legal_name, website, domain, industry, country, hq_city, description,
            services_provided, data_accessed, tier, lifecycle, inherent_risk, risk_score, created_by, next_review_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$d['name'], $d['legal_name'] ?: null, $d['website'] ?: null, $d['domain'] ?: null,
           $d['industry'] ?: null, $d['country'] ?: null, $d['hq_city'] ?: null, $d['description'] ?: null,
           $d['services_provided'] ?: null, $d['ir_data'], $tier, 'onboarding', $pts, 500,
           current_user()['id'], date('Y-m-d', strtotime('+1 year'))]);
        $vid = (int)db()->lastInsertId();
        if (!empty($d['contact_name'])) {
            q('INSERT INTO vendor_contacts (vendor_id, name, title, email, phone, is_primary) VALUES (?,?,?,?,?,1)',
              [$vid, $d['contact_name'], $d['contact_title'] ?: null, $d['contact_email'] ?: null, $d['contact_phone'] ?: null]);
        }
        recompute_risk($vid);
        audit('vendor_created', 'vendor', $vid, $d['name'] . ' (tier ' . $tier . ', suggested ' . $sug . ')');
        add_alert($vid, 'onboarding', 'New vendor onboarded: ' . $d['name'], 'info');
        unset($_SESSION['vendor_wizard']);
        flash('success', 'Vendor "' . $d['name'] . '" onboarded. Now run scans and send an assessment from their profile.');
        redirect('vendor_view', ['id' => $vid]);
    }
}

include __DIR__ . '/../partials/header.php';
$pts = inherent_points($d);
?>
<div class="topbar">
  <h1>Onboard a vendor</h1>
  <div class="spacer"></div>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('vendor_add', ['restart' => 1])) ?>">↺ Start over</a>
</div>

<div class="steps">
  <div class="step <?= $step > 1 ? 'done' : 'now' ?>">1 · Company profile</div>
  <div class="step <?= $step > 2 ? 'done' : ($step === 2 ? 'now' : '') ?>">2 · Primary contact</div>
  <div class="step <?= $step > 3 ? 'done' : ($step === 3 ? 'now' : '') ?>">3 · Services &amp; inherent risk</div>
  <div class="step <?= $step === 4 ? 'now' : '' ?>">4 · Tiering &amp; confirm</div>
</div>

<?php foreach ($errors as $er): ?><div class="flash flash-error"><?= e($er) ?></div><?php endforeach; ?>

<div class="card" style="max-width:760px">
<form method="post" action="<?= e(url('vendor_add')) ?>">
<?= csrf_field() ?>

<?php if ($step === 1): ?>
  <h2>Company profile</h2>
  <p class="muted small">Who is this third party? Only the name is mandatory — everything else strengthens their public-information profile.</p>
  <div class="form-row">
    <div><label>Vendor name *</label><input type="text" name="name" required value="<?= e($d['name'] ?? '') ?>"></div>
    <div><label>Legal name</label><input type="text" name="legal_name" value="<?= e($d['legal_name'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div><label>Website</label><input type="text" name="website" placeholder="https://vendor.com" value="<?= e($d['website'] ?? '') ?>">
      <div class="help">The domain powers breach &amp; footprint scans.</div></div>
    <div><label>Industry</label><input type="text" name="industry" value="<?= e($d['industry'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div><label>Country</label><input type="text" name="country" value="<?= e($d['country'] ?? '') ?>"></div>
    <div><label>HQ city</label><input type="text" name="hq_city" value="<?= e($d['hq_city'] ?? '') ?>"></div>
  </div>
  <label>Short description</label>
  <textarea name="description"><?= e($d['description'] ?? '') ?></textarea>

<?php elseif ($step === 2): ?>
  <h2>Primary contact</h2>
  <p class="muted small">Used to send questionnaires and reminders. You can skip and add later.</p>
  <div class="form-row">
    <div><label>Name</label><input type="text" name="contact_name" value="<?= e($d['contact_name'] ?? '') ?>"></div>
    <div><label>Title</label><input type="text" name="contact_title" value="<?= e($d['contact_title'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div><label>Email</label><input type="email" name="contact_email" value="<?= e($d['contact_email'] ?? '') ?>"></div>
    <div><label>Phone</label><input type="text" name="contact_phone" value="<?= e($d['contact_phone'] ?? '') ?>"></div>
  </div>

<?php elseif ($step === 3): ?>
  <h2>Services &amp; inherent risk<?= hint('inherent_risk') ?></h2>
  <p class="muted small">Six quick questions. The platform converts your answers into an inherent-risk score and suggests the right tier — no guesswork.</p>
  <label>What service does this vendor provide?</label>
  <textarea name="services_provided"><?= e($d['services_provided'] ?? '') ?></textarea>
  <div class="form-row">
    <div><label>Most sensitive data they touch</label>
      <select name="ir_data"><?php foreach (['None','Internal','Confidential','PII','PCI','PHI'] as $o): ?>
        <option <?= ($d['ir_data'] ?? '') === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div><label>Supports a business-critical process?</label>
      <select name="ir_critical"><option value="no" <?= ($d['ir_critical'] ?? '') === 'no' ? 'selected':'' ?>>No</option>
        <option value="yes" <?= ($d['ir_critical'] ?? '') === 'yes' ? 'selected':'' ?>>Yes</option></select></div>
  </div>
  <div class="form-row">
    <div><label>Has access to your network / systems?</label>
      <select name="ir_network"><option value="no" <?= ($d['ir_network'] ?? '') === 'no' ? 'selected':'' ?>>No</option>
        <option value="yes" <?= ($d['ir_network'] ?? '') === 'yes' ? 'selected':'' ?>>Yes</option></select></div>
    <div><label>Operates in a regulated domain (finance, health…)?</label>
      <select name="ir_regulated"><option value="no" <?= ($d['ir_regulated'] ?? '') === 'no' ? 'selected':'' ?>>No</option>
        <option value="yes" <?= ($d['ir_regulated'] ?? '') === 'yes' ? 'selected':'' ?>>Yes</option></select></div>
  </div>
  <div class="form-row">
    <div><label>Their use of subcontractors (fourth parties)</label>
      <select name="ir_subs">
        <option value="none" <?= ($d['ir_subs'] ?? '') === 'none' ? 'selected':'' ?>>None / unknown</option>
        <option value="few" <?= ($d['ir_subs'] ?? '') === 'few' ? 'selected':'' ?>>A few</option>
        <option value="many" <?= ($d['ir_subs'] ?? '') === 'many' ? 'selected':'' ?>>Many</option></select></div>
    <div><label>How hard would they be to replace?</label>
      <select name="ir_replace">
        <option value="easy" <?= ($d['ir_replace'] ?? '') === 'easy' ? 'selected':'' ?>>Easy — many alternatives</option>
        <option value="moderate" <?= ($d['ir_replace'] ?? '') === 'moderate' ? 'selected':'' ?>>Moderate</option>
        <option value="hard" <?= ($d['ir_replace'] ?? '') === 'hard' ? 'selected':'' ?>>Hard — deeply embedded</option></select></div>
  </div>

<?php else: ?>
  <h2>Tiering &amp; confirm</h2>
  <?php $sug = suggested_tier($pts); ?>
  <div class="card tight" style="background:var(--bg-2)">
    <p><strong><?= e($d['name'] ?? '') ?></strong> · <?= e($d['industry'] ?: 'Industry n/a') ?> · <?= e($d['country'] ?: 'Country n/a') ?></p>
    <p class="muted small"><?= e($d['services_provided'] ?: 'No service description provided.') ?></p>
    <p>Inherent-risk score: <strong class="gold"><?= $pts ?>/100</strong> →
       Suggested tier: <?= tier_badge($sug) ?></p>
    <div class="meter"><span data-w="<?= $pts ?>"></span></div>
  </div>
  <label>Criticality tier <span class="muted">(you may override the suggestion)</span></label>
  <select name="tier">
    <?php foreach (['critical','high','medium','low'] as $t): ?>
      <option value="<?= $t ?>" <?= $t === $sug ? 'selected' : '' ?>><?= ucfirst($t) ?><?= $t === $sug ? ' (suggested)' : '' ?></option>
    <?php endforeach; ?>
  </select>
<?php endif; ?>

  <div style="display:flex;gap:.7rem;margin-top:1.4rem">
    <?php if ($step > 1): ?>
      <button class="btn btn-ghost" name="_go" value="back">← Back</button>
    <?php endif; ?>
    <button class="btn btn-gold" name="_go" value="next">
      <?= $step === 4 ? '✓ Create vendor' : 'Continue →' ?>
    </button>
  </div>
</form>
</div>
<?php include __DIR__ . '/../partials/footer.php';
