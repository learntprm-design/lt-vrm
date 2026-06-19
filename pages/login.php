<?php
/** Sign-in page (with brute-force lockout). */
if (is_logged_in()) redirect('dashboard');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $u = row('SELECT * FROM users WHERE email = ? AND status = "active"', [$email]);
    if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
        $error = 'Account temporarily locked after repeated failures. Try again in a few minutes.';
    } elseif ($u && password_verify($pass, $u['password_hash'])) {
        q('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login = NOW() WHERE id = ?', [$u['id']]);
        login_user($u);
        audit('login', 'user', (int)$u['id']);
        redirect('dashboard');
    } else {
        if ($u) {
            $fails = (int)$u['failed_logins'] + 1;
            $lock = $fails >= 5 ? date('Y-m-d H:i:s', time() + 600) : null;
            q('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?', [$fails, $lock, $u['id']]);
        }
        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · VendorAssess 360</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div style="min-height:100vh;display:grid;place-items:center;padding:1rem;
  background:radial-gradient(ellipse at 50% -20%, rgba(238,192,92,.08), transparent 55%), var(--bg)">
  <div style="width:min(410px,94vw)">
    <div style="text-align:center;margin-bottom:1.4rem">
      <div class="logo" style="font-family:var(--font-head);font-weight:700;font-size:1.6rem;color:#fff">
        Vendor<span style="color:var(--gold)">Assess</span> 360</div>
      <div class="small" style="color:var(--gold);letter-spacing:.05em">The World's Most Comprehensive Open-Source TPRM Platform</div>
      <div class="small muted" style="margin-top:.2rem">Developed by LearnTPRM.com</div>
    </div>
    <div class="card" style="padding:1.6rem 1.7rem">
      <h2 style="margin-bottom:1rem">Sign in</h2>
      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('login')) ?>">
        <?= csrf_field() ?>
        <label>Email</label>
        <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
        <label>Password</label>
        <input type="password" name="password" required>
        <button class="btn btn-gold" style="width:100%;justify-content:center;margin-top:1.2rem">Sign in →</button>
      </form>
      <p class="small muted" style="margin-top:1rem">Need an account? Ask your TPRM administrator for an invitation.</p>
    </div>
    <p class="small muted" style="text-align:center">
      Become a TPRM Warrior — <a href="https://learntprm.com" target="_blank" rel="noopener">learntprm.com</a>
    </p>
  </div>
</div>
</body>
</html>
