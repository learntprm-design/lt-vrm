<?php
/**
 * VendorAssess 360 — Guided Installer
 * Step 1: environment checks → Step 2: database + admin → Step 3: done.
 * Locks itself after a successful install (config.php exists).
 */
declare(strict_types=1);
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
$installed = is_file($root . '/config.php');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---- Environment checks ---- */
$uploadsDir = $root . '/uploads';
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
$checks = [
    ['PHP version ≥ 8.1', PHP_VERSION_ID >= 80100, 'You have ' . PHP_VERSION],
    ['PDO MySQL extension', extension_loaded('pdo_mysql'), 'Enable extension=pdo_mysql in php.ini'],
    ['mbstring extension', extension_loaded('mbstring'), 'Enable extension=mbstring in php.ini'],
    ['openssl extension', extension_loaded('openssl'), 'Needed for secure tokens & SMTP TLS'],
    ['App folder writable (for config.php)', is_writable($root), 'The web server cannot write here — see the fix box below'],
    ['uploads/ folder writable', is_dir($uploadsDir) && is_writable($uploadsDir),
        is_dir($uploadsDir) ? 'Folder exists but the web server cannot write to it — see the fix box below'
                            : 'Could not create the folder — see the fix box below'],
];
$allOk = true;
foreach ($checks as $c) if (!$c[1]) $allOk = false;
$permFail = !is_writable($root) || !is_writable($uploadsDir);

$step = $installed ? 3 : (int)($_GET['step'] ?? 1);
$errors = [];

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST' && $allOk) {
    $dbHost = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim((string)($_POST['db_name'] ?? 'vendorassess360'));
    $dbUser = trim((string)($_POST['db_user'] ?? 'root'));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $admName  = trim((string)($_POST['admin_name'] ?? ''));
    $admEmail = trim((string)($_POST['admin_email'] ?? ''));
    $admPass  = (string)($_POST['admin_pass'] ?? '');
    $seedDemo = !empty($_POST['seed_demo']);

    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) $errors[] = 'Database name may only contain letters, numbers and underscores.';
    if ($admName === '')                            $errors[] = 'Please enter the administrator name.';
    if (!filter_var($admEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid administrator email.';
    if (strlen($admPass) < 8)                       $errors[] = 'Administrator password must be at least 8 characters.';

    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            // Run schema (split on semicolons at line ends — schema has no stored procedures).
            $schema = (string)file_get_contents($root . '/database/schema.sql');
            foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $schema))) as $stmt) {
                if ($stmt !== '' && stripos($stmt, 'SET NAMES') !== 0) $pdo->exec($stmt);
            }

            // First admin account
            $st = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?,?,?,"admin","active")');
            $st->execute([$admName, $admEmail, password_hash($admPass, PASSWORD_DEFAULT)]);

            // Default settings + built-in questionnaire templates (+ optional demo data)
            require __DIR__ . '/seed.php';
            va_seed_core($pdo);
            if ($seedDemo) va_seed_demo($pdo);

            // Write config.php
            $cfg = "<?php\nreturn [\n"
                 . "    'db_host' => " . var_export($dbHost, true) . ",\n"
                 . "    'db_port' => " . var_export($dbPort, true) . ",\n"
                 . "    'db_name' => " . var_export($dbName, true) . ",\n"
                 . "    'db_user' => " . var_export($dbUser, true) . ",\n"
                 . "    'db_pass' => " . var_export($dbPass, true) . ",\n"
                 . "    'app_url' => " . var_export(va_guess_url(), true) . ",\n"
                 . "    'debug'   => false,\n];\n";
            if (file_put_contents($root . '/config.php', $cfg) === false) {
                throw new RuntimeException('Could not write config.php — check folder permissions.');
            }
            header('Location: index.php?step=3');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage() . ' — Is MySQL running in the XAMPP Control Panel?';
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
    $step = 2;
}

function va_guess_url(): string {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path  = preg_replace('#/install/?.*$#', '', $_SERVER['REQUEST_URI'] ?? '/');
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($path, '/');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · VendorAssess 360</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<div style="min-height:100vh;display:grid;place-items:center;padding:2rem 1rem;
  background:radial-gradient(ellipse at 50% -20%, rgba(238,192,92,.08), transparent 55%), var(--bg)">
<div style="width:min(640px,95vw)">
  <div style="text-align:center;margin-bottom:1.3rem">
    <div style="font-family:var(--font-head);font-weight:700;font-size:1.7rem;color:#fff">
      Vendor<span style="color:var(--gold)">Assess</span> 360</div>
    <div class="small" style="color:var(--gold)">Guided Installation · by LearnTPRM.com</div>
  </div>

  <div class="steps">
    <div class="step <?= $step === 1 ? 'now' : 'done' ?>">1 · Environment</div>
    <div class="step <?= $step === 2 ? 'now' : ($step > 2 ? 'done' : '') ?>">2 · Database &amp; Admin</div>
    <div class="step <?= $step === 3 ? 'now' : '' ?>">3 · Finish</div>
  </div>

<?php if ($step === 1): ?>
  <div class="card">
    <h2>Environment check</h2>
    <p class="muted small">We verify your XAMPP / PHP setup before installing anything.</p>
    <table class="data"><tbody>
    <?php foreach ($checks as $c): ?>
      <tr>
        <td><?= h($c[0]) ?></td>
        <td style="width:90px"><?= $c[1] ? '<span class="badge badge-low">PASS</span>' : '<span class="badge badge-critical">FAIL</span>' ?></td>
        <td class="muted small"><?= $c[1] ? '' : h($c[2]) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php if ($permFail): ?>
    <div class="card tight" style="background:var(--bg-2);margin-top:1rem">
      <h3 class="gold">How to fix the permission failures (1 minute)</h3>
      <p class="muted small">Your operating system runs the web server as a different user than you,
        so it can't write into this folder. Run the one-liner for your system, then press re-check.
        Full tutorial: <code>docs/TROUBLESHOOTING.md</code> in the app folder.</p>
      <p class="small"><strong>macOS (XAMPP)</strong> — open Terminal and paste:</p>
      <input type="text" readonly onclick="this.select()" style="font-family:monospace"
             value="chmod 777 &quot;<?= h($root) ?>&quot; &quot;<?= h($root) ?>/uploads&quot;">
      <p class="small" style="margin-top:.6rem"><strong>Linux (XAMPP/LAMPP)</strong> — Terminal:</p>
      <input type="text" readonly onclick="this.select()" style="font-family:monospace"
             value="sudo chmod 777 &quot;<?= h($root) ?>&quot; &quot;<?= h($root) ?>/uploads&quot;">
      <p class="small" style="margin-top:.6rem"><strong>Windows (XAMPP)</strong> — usually already writable.
        If not: right-click the <code>vendorassess360</code> folder → Properties → Security → Edit →
        select <em>Users</em> → tick <em>Full control</em> → OK.</p>
      <p class="help">This only loosens two folders on your local server so Apache can create
        <code>config.php</code> and store uploads — standard practice for XAMPP installs.</p>
    </div>
    <?php endif; ?>
    <div style="margin-top:1.2rem">
      <?php if ($allOk): ?>
        <a class="btn btn-gold" href="index.php?step=2">Everything passed — continue →</a>
      <?php else: ?>
        <a class="btn btn-gold" href="index.php?step=1">I fixed it — re-check ⟳</a>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($step === 2): ?>
  <div class="card">
    <h2>Database &amp; administrator</h2>
    <?php foreach ($errors as $er): ?><div class="flash flash-error"><?= h($er) ?></div><?php endforeach; ?>
    <form method="post" action="index.php">
      <h3 style="margin-top:.6rem">MySQL connection <span class="muted small">(XAMPP defaults are pre-filled)</span></h3>
      <div class="form-row">
        <div><label>Host</label><input type="text" name="db_host" value="<?= h($_POST['db_host'] ?? '127.0.0.1') ?>"></div>
        <div><label>Port</label><input type="number" name="db_port" value="<?= h($_POST['db_port'] ?? 3306) ?>"></div>
      </div>
      <div class="form-row">
        <div><label>Database name <span class="muted">(created if missing)</span></label>
          <input type="text" name="db_name" value="<?= h($_POST['db_name'] ?? 'vendorassess360') ?>"></div>
        <div><label>User</label><input type="text" name="db_user" value="<?= h($_POST['db_user'] ?? 'root') ?>"></div>
      </div>
      <label>Password <span class="muted">(blank on default XAMPP)</span></label>
      <input type="password" name="db_pass" value="">

      <h3 style="margin-top:1.4rem">Your administrator account</h3>
      <div class="form-row">
        <div><label>Full name</label><input type="text" name="admin_name" required value="<?= h($_POST['admin_name'] ?? '') ?>"></div>
        <div><label>Email</label><input type="email" name="admin_email" required value="<?= h($_POST['admin_email'] ?? '') ?>"></div>
      </div>
      <label>Password (min 8 characters)</label>
      <input type="password" name="admin_pass" required minlength="8">

      <div class="checkline" style="margin-top:1.1rem">
        <input type="checkbox" id="seed" name="seed_demo" value="1" checked>
        <label for="seed">Load the demo dataset (52 realistic vendors with documents, contracts, assessments &amp; scores) — recommended for first impressions</label>
      </div>
      <button class="btn btn-gold" style="margin-top:1.2rem">Install VendorAssess 360 →</button>
    </form>
  </div>

<?php else: ?>
  <div class="card" style="text-align:center;padding:2.4rem">
    <div style="font-size:2.6rem">🏯</div>
    <h2>Installation complete</h2>
    <?php if ($installed): ?>
      <p class="muted">VendorAssess 360 is ready. The installer is now locked — to reinstall, delete <code>config.php</code> and the database.</p>
      <p style="margin-top:1.2rem"><a class="btn btn-gold" href="../index.php?p=login">Open the platform →</a></p>
    <?php else: ?>
      <p class="muted">Something is off — config.php was not found. <a href="index.php">Restart the installer</a>.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

  <p class="small muted" style="text-align:center">Developed by <a href="https://learntprm.com" target="_blank" rel="noopener">LearnTPRM.com</a> · The World's Most Comprehensive Open-Source TPRM Platform</p>
</div></div>
</body>
</html>
