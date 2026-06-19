<?php
/** Users & access control — invite, roles, activate/deactivate, reset password. */
require_perm('*');
$GLOBALS['VA_PAGE_TITLE'] = 'Users & Access';
$ROLES = ['admin' => 'Administrator — full control incl. users & settings',
          'analyst' => 'TPRM Analyst — manage vendors, assessments, documents, contracts, issues',
          'viewer' => 'Viewer — read-only access plus exports'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    $me = (int)current_user()['id'];

    if ($act === 'invite') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = array_key_exists($_POST['role'] ?? '', $ROLES) ? $_POST['role'] : 'viewer';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a name and a valid email.');
        } elseif (row('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('error', 'A user with that email already exists.');
        } else {
            $token = bin2hex(random_bytes(24));
            q('INSERT INTO users (name, email, password_hash, role, status, invite_token) VALUES (?,?,?,?,?,?)',
              [$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $role, 'invited', $token]);
            $uid = (int)db()->lastInsertId();
            audit('user_invited', 'user', $uid, "$email as $role");
            $link = url('accept_invite', ['token' => $token]);
            $mailed = send_mail($email, 'You are invited to LT-VRM',
                '<p>Hello ' . e($name) . ',</p><p>You\'ve been invited to the TPRM platform as <strong>' . e($role) . '</strong>.</p>
                 <p><a href="' . e($link) . '">Click here to set your password and activate your account</a>.</p>');
            flash('success', 'Invitation created.' . ($mailed ? ' Email sent.' : ' SMTP not configured — share this link manually: ' . $link));
        }
        redirect('users');
    }
    if ($act === 'role') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $role = array_key_exists($_POST['role'] ?? '', $ROLES) ? $_POST['role'] : null;
        if ($uid === $me) { flash('error', 'You cannot change your own role.'); }
        elseif ($role) {
            q('UPDATE users SET role = ? WHERE id = ?', [$role, $uid]);
            audit('user_role_changed', 'user', $uid, $role);
            flash('success', 'Role updated.');
        }
        redirect('users');
    }
    if ($act === 'toggle') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === $me) { flash('error', 'You cannot deactivate yourself.'); }
        else {
            $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
            if ($u) {
                $new = $u['status'] === 'active' ? 'inactive' : 'active';
                q('UPDATE users SET status = ?, invite_token = NULL WHERE id = ?', [$new, $uid]);
                audit('user_' . ($new === 'active' ? 'activated' : 'deactivated'), 'user', $uid, $u['email']);
                flash('success', $u['name'] . ' is now ' . $new . '.');
            }
        }
        redirect('users');
    }
    if ($act === 'reset_pw') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
        if ($u) {
            $temp = bin2hex(random_bytes(5)); // 10-char temp password
            q('UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?',
              [password_hash($temp, PASSWORD_DEFAULT), $uid]);
            audit('user_password_reset', 'user', $uid, $u['email']);
            $mailed = send_mail($u['email'], 'Your LT-VRM password was reset',
                '<p>Your temporary password is: <strong>' . e($temp) . '</strong></p><p>Please sign in and change it.</p>');
            flash('success', 'Password reset for ' . $u['name'] . '.' .
                ($mailed ? ' Emailed to them.' : ' Temporary password (share securely): ' . $temp));
        }
        redirect('users');
    }
    if ($act === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === $me) { flash('error', 'You cannot delete yourself.'); }
        else {
            $u = row('SELECT * FROM users WHERE id = ?', [$uid]);
            if ($u) {
                q('DELETE FROM users WHERE id = ?', [$uid]);
                audit('user_deleted', 'user', $uid, $u['email']);
                flash('success', 'User removed.');
            }
        }
        redirect('users');
    }
}

$users = rows('SELECT * FROM users ORDER BY status = "active" DESC, name');
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Users &amp; Access</h1>
  <div class="spacer"></div>
  <button class="btn btn-gold" data-open-modal="invite-modal">+ Invite user</button>
</div>

<div class="card">
  <h2>Role permission matrix<?= hint('roles') ?></h2>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Capability</th><th>Admin</th><th>TPRM Analyst</th><th>Viewer</th><th>Vendor (portal link)</th></tr></thead>
    <tbody>
      <?php
      $matrix = [
        ['View dashboards, vendors & reports', 1, 1, 1, 0],
        ['Export CSV / board reports', 1, 1, 1, 0],
        ['Add / edit / import vendors', 1, 1, 0, 0],
        ['Run breach / footprint / news scans', 1, 1, 0, 0],
        ['Send & review assessments', 1, 1, 0, 0],
        ['Manage documents & contracts', 1, 1, 0, 0],
        ['Manage issues, risks, offboarding', 1, 1, 0, 0],
        ['Answer assigned questionnaires', 0, 0, 0, 1],
        ['Invite & manage users', 1, 0, 0, 0],
        ['Platform settings & integrations', 1, 0, 0, 0],
        ['View audit trail', 1, 0, 0, 0],
      ];
      foreach ($matrix as $m): ?>
      <tr><td><?= e($m[0]) ?></td>
        <?php for ($i = 1; $i <= 4; $i++): ?><td><?= $m[$i] ? '<span style="color:var(--green)">✓</span>' : '<span class="muted">—</span>' ?></td><?php endfor; ?></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <p class="help">Vendors never get accounts — they interact only through secure, tokenized portal links scoped to a single questionnaire.</p>
</div>

<div class="card">
  <h2>Team (<?= count($users) ?>)</h2>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last login</th><th style="min-width:260px">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): $isMe = (int)$u['id'] === (int)current_user()['id']; ?>
      <tr>
        <td><strong><?= e($u['name']) ?></strong><?= $isMe ? ' <span class="pill" style="font-size:.65rem">you</span>' : '' ?>
          <div class="small muted"><?= e($u['email']) ?></div></td>
        <td>
          <?php if ($isMe): ?><span class="badge badge-status"><?= e(ucfirst($u['role'])) ?></span>
          <?php else: ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="role"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <select name="role" onchange="this.form.submit()" style="width:auto;padding:.3rem .5rem">
              <?php foreach (array_keys($ROLES) as $r): ?>
                <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
              <?php endforeach; ?></select>
          </form>
          <?php endif; ?>
        </td>
        <td><?= status_badge($u['status'] === 'invited' ? 'sent' : ($u['status'] === 'active' ? 'active' : 'terminated')) ?>
          <?= $u['status'] === 'invited' ? '<div class="small muted">awaiting acceptance</div>' : '' ?></td>
        <td class="muted small"><?= $u['last_login'] ? e(date('M j, Y H:i', strtotime($u['last_login']))) : 'never' ?></td>
        <td>
          <?php if (!$isMe): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm btn-ghost"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="reset_pw"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm btn-ghost">Reset password</button></form>
          <form method="post" style="display:inline" data-confirm="Remove this user entirely?"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm btn-danger">✕</button></form>
          <?php if ($u['status'] === 'invited' && $u['invite_token']): ?>
            <div class="small muted" style="margin-top:.3rem">Invite link:
              <input type="text" readonly onclick="this.select()" style="font-family:monospace;font-size:.7rem"
                     value="<?= e(url('accept_invite', ['token' => $u['invite_token']])) ?>"></div>
          <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="modal-back" id="invite-modal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0">Invite a user</h2><button class="btn btn-ghost btn-sm" data-close-modal>✕</button></div>
    <form method="post" action="<?= e(url('users')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="invite">
      <label>Full name *</label><input type="text" name="name" required>
      <label>Email *</label><input type="email" name="email" required>
      <label>Role</label>
      <select name="role">
        <?php foreach ($ROLES as $r => $desc): ?><option value="<?= $r ?>"><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
      </select>
      <div class="help" style="margin-top:.5rem">
        <?php foreach ($ROLES as $r => $desc): ?><div><strong><?= e(ucfirst($r)) ?>:</strong> <?= e($desc) ?></div><?php endforeach; ?>
      </div>
      <button class="btn btn-gold" style="margin-top:1.1rem">✉ Send invitation</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php';
