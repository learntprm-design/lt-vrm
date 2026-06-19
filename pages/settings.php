<?php
/** Platform settings — organization, email (SMTP), integrations, reminders. */
require_perm('*');
$GLOBALS['VA_PAGE_TITLE'] = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'save') {
        $fields = ['org_name','smtp_host','smtp_port','smtp_user','smtp_from','smtp_security','hibp_api_key','newsapi_key','reminder_days'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) save_setting($f, trim((string)$_POST[$f]));
        }
        if (($_POST['smtp_pass'] ?? '') !== '') save_setting('smtp_pass', (string)$_POST['smtp_pass']);
        if (($_POST['smtp_security'] ?? '') && !in_array($_POST['smtp_security'], ['tls','ssl','none'], true)) save_setting('smtp_security', 'tls');
        $rd = preg_replace('/[^0-9,]/', '', (string)($_POST['reminder_days'] ?? '90,60,30'));
        save_setting('reminder_days', $rd ?: '90,60,30');
        audit('settings_saved', 'settings');
        flash('success', 'Settings saved.');
        redirect('settings');
    }
    if ($act === 'test_mail') {
        $ok = send_mail(current_user()['email'], 'VendorAssess 360 — SMTP test',
            '<p>If you can read this, your SMTP settings work. 🎉</p>');
        flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . current_user()['email'] . '.'
            : 'Test email failed — check host/port/credentials, and that your network allows outbound SMTP.');
        redirect('settings');
    }
}
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar"><h1>Settings</h1></div>

<form method="post" action="<?= e(url('settings')) ?>"><?= csrf_field() ?>
<input type="hidden" name="_action" value="save">
<div class="grid c2">
  <div class="card">
    <h2>Organization</h2>
    <label>Organization name <span class="muted">(appears in vendor emails)</span></label>
    <input type="text" name="org_name" value="<?= e(setting('org_name', 'My Organization')) ?>">
    <label>Contract & document reminder thresholds (days, comma-separated)</label>
    <input type="text" name="reminder_days" value="<?= e(setting('reminder_days', '90,60,30')) ?>">
    <div class="help">Used by the “Run expiry reminders” engine on the Contracts page.</div>
  </div>

  <div class="card">
    <h2>Email (SMTP)</h2>
    <p class="muted small">Optional. Without SMTP everything still works — reminders appear as in-app alerts and you copy portal links manually.</p>
    <div class="form-row">
      <div><label>Host</label><input type="text" name="smtp_host" placeholder="smtp.gmail.com" value="<?= e(setting('smtp_host', '')) ?>"></div>
      <div><label>Port</label><input type="number" name="smtp_port" value="<?= e(setting('smtp_port', '587')) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Username</label><input type="text" name="smtp_user" value="<?= e(setting('smtp_user', '')) ?>"></div>
      <div><label>Password <span class="muted">(leave blank to keep)</span></label><input type="password" name="smtp_pass" value=""></div>
    </div>
    <div class="form-row">
      <div><label>From address</label><input type="email" name="smtp_from" value="<?= e(setting('smtp_from', 'tprm@localhost')) ?>"></div>
      <div><label>Security</label>
        <select name="smtp_security">
          <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None (25)'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= setting('smtp_security', 'tls') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?></select></div>
    </div>
  </div>
</div>

<div class="card">
  <h2>Integrations <span class="muted small">(connectors switch from Demo Mode to Live automatically when a key is present)</span><?= hint('integrations') ?></h2>
  <div class="form-row">
    <div>
      <label>HaveIBeenPwned API key — breach &amp; dark-web scans</label>
      <input type="text" name="hibp_api_key" value="<?= e(setting('hibp_api_key', '')) ?>">
      <div class="help">Get one at haveibeenpwned.com/API/Key. Without it, breach scans use clearly-labeled demo data.</div>
    </div>
    <div>
      <label>NewsAPI.org key — adverse media monitoring</label>
      <input type="text" name="newsapi_key" value="<?= e(setting('newsapi_key', '')) ?>">
      <div class="help">Free tier available at newsapi.org. Without it, reputation scans use demo data.</div>
    </div>
  </div>
  <p class="small muted">Digital-footprint scans need no key — they use passive DNS and public certificate-transparency
    logs whenever the server has internet access, and fall back to Demo Mode offline.</p>
</div>
<button class="btn btn-gold">✓ Save settings</button>
</form>

<div class="card" style="margin-top:1.1rem">
  <h2>Test email delivery</h2>
  <form method="post"><?= csrf_field() ?>
    <input type="hidden" name="_action" value="test_mail">
    <button class="btn btn-outline">✉ Send a test email to <?= e(current_user()['email']) ?></button>
  </form>
</div>
<?php include __DIR__ . '/../partials/footer.php';
