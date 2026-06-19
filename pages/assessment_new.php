<?php
/** Send a questionnaire — creates the assessment + secure vendor portal link. */
require_perm('manage_assessments');
$GLOBALS['VA_PAGE_TITLE'] = 'Send questionnaire';

$preVendor = (int)($_GET['vendor'] ?? 0);
$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vid = (int)($_POST['vendor_id'] ?? 0);
    $tid = (int)($_POST['template_id'] ?? 0);
    $v = row('SELECT * FROM vendors WHERE id = ?', [$vid]);
    $t = row('SELECT * FROM assessment_templates WHERE id = ?', [$tid]);
    $email = trim((string)($_POST['sent_to_email'] ?? ''));
    $due = trim((string)($_POST['due_date'] ?? '')) ?: null;
    $errors = [];
    if (!$v) $errors[] = 'Choose a vendor.';
    if (!$t) $errors[] = 'Choose a questionnaire template.';
    if ($t && !(int)scalar('SELECT COUNT(*) FROM template_questions WHERE template_id = ?', [$tid])) {
        $errors[] = 'That template has no questions yet — add some first.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Recipient email is invalid.';
    // Expert rule: no parallel duplicates. Two in-flight copies of the same
    // questionnaire confuse the vendor and split your audit trail.
    if ($v && $t) {
        $open = row("SELECT id, status FROM assessments WHERE vendor_id = ? AND template_id = ?
                     AND status NOT IN ('approved','rejected')", [$vid, $tid]);
        if ($open) {
            $errors[] = 'This vendor already has an in-flight "' . $t['name'] . '" assessment (status: '
                . str_replace('_', ' ', $open['status']) . '). Finish, withdraw or decide that one first — '
                . 'running parallel duplicates is bad TPRM practice.';
        }
    }
    if (!$errors) {
        $token = bin2hex(random_bytes(24));
        $title = trim((string)($_POST['title'] ?? '')) ?: ($t['name'] . ' — ' . $v['name']);
        q('INSERT INTO assessments (vendor_id, template_id, title, status, token, due_date, sent_to_email, created_by)
           VALUES (?,?,?,?,?,?,?,?)',
          [$vid, $tid, $title, 'sent', $token, $due, $email ?: null, current_user()['id']]);
        $aid = (int)db()->lastInsertId();
        q('INSERT INTO assessment_events (assessment_id, actor, event) VALUES (?,?,?)',
          [$aid, current_user()['name'], 'Assessment created and sent']);
        audit('assessment_sent', 'assessment', $aid, $title);

        $link = app_url('portal.php') . '?t=' . $token;
        $mailed = false;
        if ($email !== '') {
            $mailed = send_mail($email, 'Security questionnaire from ' . setting('org_name', 'our TPRM team'),
                '<p>Hello,</p><p>You have been asked to complete a security questionnaire:
                 <strong>' . e($title) . '</strong>' . ($due ? ' (due ' . e(fmt_date($due)) . ')' : '') . '.</p>
                 <p><a href="' . e($link) . '">Open the secure questionnaire portal</a></p>
                 <p>No account is needed — the link is your secure access.</p>');
        }
        $created = ['link' => $link, 'mailed' => $mailed, 'email' => $email, 'id' => $aid];
        flash('success', 'Assessment created.' . ($mailed ? ' Email sent to ' . $email . '.' : ''));
    } else {
        foreach ($errors as $er) flash('error', $er);
    }
}

$vendors = rows('SELECT id, name FROM vendors WHERE lifecycle <> "terminated" ORDER BY name LIMIT 1100');
$tpls = rows('SELECT t.id, t.name, (SELECT COUNT(*) FROM template_questions q WHERE q.template_id = t.id) qc
              FROM assessment_templates t ORDER BY t.name');
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Send a questionnaire</h1>
  <div class="spacer"></div>
  <a class="btn btn-ghost" href="<?= e(url('assessments')) ?>">← All assessments</a>
</div>

<?php if ($created): ?>
<div class="card" style="border-color:rgba(52,211,153,.45)">
  <h2 style="color:var(--green)">✓ Questionnaire is live</h2>
  <p class="muted small">Share this secure portal link with the vendor — no account needed on their side:<?= hint('portal_link') ?></p>
  <input type="text" readonly value="<?= e($created['link']) ?>" onclick="this.select()" style="font-family:monospace">
  <?php if ($created['email'] && !$created['mailed']): ?>
    <p class="small" style="color:var(--orange)">⚠ Email could not be sent (SMTP not configured or unreachable) — copy the link above and send it manually.</p>
  <?php endif; ?>
  <p style="margin-top:.8rem">
    <a class="btn btn-outline btn-sm" href="<?= e(url('assessment_review', ['id' => $created['id']])) ?>">Track this assessment →</a>
  </p>
</div>
<?php endif; ?>

<div class="card" style="max-width:700px">
  <form method="post"><?= csrf_field() ?>
    <label>Vendor *</label>
    <select name="vendor_id" required><option value="">— choose —</option>
      <?php foreach ($vendors as $vo): ?>
        <option value="<?= (int)$vo['id'] ?>" <?= $preVendor === (int)$vo['id'] ? 'selected' : '' ?>><?= e($vo['name']) ?></option>
      <?php endforeach; ?></select>
    <label>Questionnaire template *</label>
    <select name="template_id" required><option value="">— choose —</option>
      <?php foreach ($tpls as $to): ?>
        <option value="<?= (int)$to['id'] ?>"><?= e($to['name']) ?> (<?= (int)$to['qc'] ?> questions)</option>
      <?php endforeach; ?></select>
    <label>Title <span class="muted">(optional — auto-generated if blank)</span></label>
    <input type="text" name="title" placeholder="Annual Due Diligence <?= date('Y') ?> — Vendor name">
    <div class="form-row">
      <div><label>Send to email <span class="muted">(optional)</span></label>
        <input type="email" name="sent_to_email" placeholder="security@vendor.com">
        <div class="help">With SMTP configured the link is emailed automatically; otherwise copy it manually.</div></div>
      <div><label>Due date</label><input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>"></div>
    </div>
    <button class="btn btn-gold" style="margin-top:1.2rem">✉ Create &amp; send</button>
  </form>
</div>
<?php include __DIR__ . '/../partials/footer.php';
