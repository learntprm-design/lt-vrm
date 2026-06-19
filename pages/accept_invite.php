<?php
/** Invitation acceptance — user sets their password and becomes active. */
$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['token'] ?? ($_POST['token'] ?? '')));
$u = $token !== '' ? row('SELECT * FROM users WHERE invite_token = ? AND status = "invited"', [$token]) : null;
$error = '';

if ($u && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');
    if (strlen($p1) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($p1 !== $p2) $error = 'Passwords do not match.';
    else {
        q('UPDATE users SET password_hash = ?, status = "active", invite_token = NULL WHERE id = ?',
          [password_hash($p1, PASSWORD_DEFAULT), $u['id']]);
        audit('user_invite_accepted', 'user', (int)$u['id'], $u['email']);
        login_user(row('SELECT * FROM users WHERE id = ?', [$u['id']]));
        flash('success', 'Welcome aboard, ' . $u['name'] . '! Your account is active.');
        redirect('dashboard');
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Accept invitation · VendorAssess 360</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css"></head><body>
<div style="min-height:100vh;display:grid;place-items:center;padding:1rem;background:var(--bg)">
  <div class="card" style="width:min(420px,94vw);padding:1.6rem 1.7rem">
    <?php if (!$u): ?>
      <h2>Invitation not valid</h2>
      <p class="muted">This invitation link has already been used or withdrawn. Ask your administrator for a new one.</p>
      <a class="btn btn-gold" href="index.php?p=login">Go to sign in</a>
    <?php else: ?>
      <h2>Welcome, <?= e($u['name']) ?> 👋</h2>
      <p class="muted small">You're joining as <strong><?= e(ucfirst($u['role'])) ?></strong>. Set a password to activate your account.</p>
      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="index.php?p=accept_invite">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Password (min 8 chars)</label><input type="password" name="password" required minlength="8">
        <label>Repeat password</label><input type="password" name="password2" required minlength="8">
        <button class="btn btn-gold" style="width:100%;justify-content:center;margin-top:1.1rem">Activate account →</button>
      </form>
    <?php endif; ?>
  </div>
</div></body></html>
