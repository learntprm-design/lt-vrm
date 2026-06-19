<?php
/**
 * VendorAssess 360 — Core helpers
 * Database, security (CSRF/escaping), auth/RBAC, settings, audit log,
 * flash messages, pagination, uploads, mail (raw SMTP, zero dependencies).
 */

/* ---------------------------------------------------------------- Database */

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = $GLOBALS['VA_CONFIG'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['db_host'], $cfg['db_port'], $cfg['db_name']);
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Run a prepared query; returns PDOStatement. */
function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array {
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array {
    return q($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = []) {
    return q($sql, $params)->fetchColumn();
}

/* ---------------------------------------------------------------- Security */

/** HTML-escape (the one function used on every echo of dynamic data). */
function e($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Abort POST handling unless the CSRF token matches. */
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tok = $_POST['_csrf'] ?? '';
        if (!is_string($tok) || !hash_equals(csrf_token(), $tok)) {
            http_response_code(419);
            exit('Security check failed (invalid CSRF token). Go back, refresh the page and try again.');
        }
    }
}

/* -------------------------------------------------------------------- Auth */

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

/**
 * Role-based permission matrix.
 * admin   — everything
 * analyst — everything except user management & settings
 * viewer  — read only
 */
function can(string $perm): bool {
    $u = current_user();
    if (!$u) return false;
    $role = $u['role'];
    $matrix = [
        'admin'   => ['*'],
        'analyst' => ['view', 'manage_vendors', 'manage_assessments', 'manage_documents',
                      'manage_contracts', 'manage_issues', 'run_scans', 'export'],
        'viewer'  => ['view', 'export'],
    ];
    $perms = $matrix[$role] ?? [];
    return in_array('*', $perms, true) || in_array($perm, $perms, true);
}

/** Gate a page: redirect to login or show 403. */
function require_perm(string $perm): void {
    if (!is_logged_in()) { redirect('login'); }
    if (!can($perm)) {
        http_response_code(403);
        $GLOBALS['VA_PAGE_TITLE'] = 'Access denied';
        include __DIR__ . '/../partials/header.php';
        echo '<div class="card empty-state"><div class="empty-icon">🔒</div><h2>Access denied</h2>
              <p class="muted">Your role (' . e(current_user()['role']) . ') does not allow this action.
              Ask an Administrator to upgrade your access.</p></div>';
        include __DIR__ . '/../partials/footer.php';
        exit;
    }
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'], 'name' => $user['name'],
        'email' => $user['email'], 'role' => $user['role'],
    ];
}

/* ------------------------------------------------------------ Misc helpers */

function app_url(string $path = ''): string {
    return rtrim($GLOBALS['VA_CONFIG']['app_url'], '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function url(string $page, array $params = []): string {
    $qs = http_build_query(array_merge(['p' => $page], $params));
    return app_url('index.php') . '?' . $qs;
}

function redirect(string $page, array $params = []): void {
    header('Location: ' . url($page, $params));
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (rows('SELECT skey, svalue FROM settings') as $r) $cache[$r['skey']] = $r['svalue'];
        } catch (Throwable $e) { /* settings table missing pre-install */ }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
}

function save_setting(string $key, string $value): void {
    q('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
      [$key, $value]);
}

function audit(string $action, ?string $entity = null, ?int $entityId = null, string $detail = ''): void {
    $u = current_user();
    try {
        q('INSERT INTO audit_log (user_id, user_name, action, entity, entity_id, detail, ip) VALUES (?,?,?,?,?,?,?)',
          [$u['id'] ?? null, $u['name'] ?? 'System', $action, $entity, $entityId,
           mb_substr($detail, 0, 500), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { /* auditing must never break the app */ }
}

function add_alert(?int $vendorId, string $type, string $message, string $severity = 'info'): void {
    q('INSERT INTO alerts (vendor_id, type, message, severity) VALUES (?,?,?,?)',
      [$vendorId, $type, mb_substr($message, 0, 500), $severity]);
}

/** Format a date or show a dash. */
function fmt_date(?string $d): string {
    if (!$d || $d === '0000-00-00') return '—';
    $t = strtotime($d);
    return $t ? date('M j, Y', $t) : '—';
}

function days_until(?string $date): ?int {
    if (!$date) return null;
    $t = strtotime($date . ' 00:00:00');
    if (!$t) return null;
    return (int)floor(($t - strtotime(date('Y-m-d') . ' 00:00:00')) / 86400);
}

function fmt_bytes(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b / 1024) . ' KB';
    return $b . ' B';
}

/* ------------------------------------------------------------- Risk bands */

function risk_band(int $score): array {
    if ($score <= 399) return ['label' => 'Critical Risk', 'class' => 'band-critical'];
    if ($score <= 599) return ['label' => 'High Risk',     'class' => 'band-high'];
    if ($score <= 749) return ['label' => 'Moderate',      'class' => 'band-moderate'];
    if ($score <= 899) return ['label' => 'Good',          'class' => 'band-good'];
    return ['label' => 'Excellent', 'class' => 'band-excellent'];
}

function score_badge(int $score): string {
    $b = risk_band($score);
    return '<span class="score-badge ' . $b['class'] . '" title="' . e($b['label']) . '">'
         . $score . '<small>/1000</small></span>';
}

function tier_badge(string $tier): string {
    $map = ['critical' => 'badge-critical', 'high' => 'badge-high',
            'medium' => 'badge-medium', 'low' => 'badge-low'];
    return '<span class="badge ' . ($map[$tier] ?? 'badge-medium') . '">' . e(ucfirst($tier)) . '</span>';
}

function status_badge(string $status): string {
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge badge-status status-' . e($status) . '">' . e($label) . '</span>';
}

/* ------------------------------------------------------------- Pagination */

/**
 * Server-side pagination helper.
 * Returns [items, total, page, pages, perPage]. Built for 1,000+ vendor scale.
 */
function paginate(string $countSql, string $listSql, array $params, int $perPage = 25): array {
    $page  = max(1, (int)($_GET['pg'] ?? 1));
    $total = (int)scalar($countSql, $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min($page, $pages);
    $off   = ($page - 1) * $perPage;
    // LIMIT/OFFSET cannot be bound params with all MySQL drivers; both are safe ints.
    $items = rows($listSql . ' LIMIT ' . $perPage . ' OFFSET ' . $off, $params);
    return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $perPage];
}

function pagination_links(array $pg, array $keep = []): string {
    if ($pg['pages'] <= 1) return '';
    $base = array_merge($keep, ['p' => $_GET['p'] ?? 'dashboard']);
    $html = '<nav class="pagination">';
    $mk = function ($n, $label, $cls = '') use ($base) {
        $qs = http_build_query(array_merge($base, ['pg' => $n]));
        return '<a class="page-link ' . $cls . '" href="index.php?' . e($qs) . '">' . $label . '</a>';
    };
    if ($pg['page'] > 1) $html .= $mk($pg['page'] - 1, '&laquo;');
    $start = max(1, $pg['page'] - 2);
    $end   = min($pg['pages'], $pg['page'] + 2);
    if ($start > 1) $html .= $mk(1, '1') . ($start > 2 ? '<span class="page-dots">…</span>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $html .= $mk($i, (string)$i, $i === $pg['page'] ? 'active' : '');
    }
    if ($end < $pg['pages']) {
        $html .= ($end < $pg['pages'] - 1 ? '<span class="page-dots">…</span>' : '') . $mk($pg['pages'], (string)$pg['pages']);
    }
    if ($pg['page'] < $pg['pages']) $html .= $mk($pg['page'] + 1, '&raquo;');
    return $html . '</nav>';
}

/* ---------------------------------------------------------------- Uploads */

const VA_ALLOWED_EXT = ['pdf','doc','docx','xls','xlsx','csv','png','jpg','jpeg','gif','txt','ppt','pptx','zip'];
const VA_MAX_UPLOAD  = 15728640; // 15 MB

/**
 * Validate + store an uploaded file with a randomized name.
 * Returns [storedName, origName, mime, size] or throws RuntimeException with a friendly message.
 */
function handle_upload(string $field): ?array {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . (int)$f['error'] . '). The file may exceed the server limit.');
    }
    if ($f['size'] > VA_MAX_UPLOAD) {
        throw new RuntimeException('File is too large. Maximum allowed size is ' . fmt_bytes(VA_MAX_UPLOAD) . '.');
    }
    $orig = basename((string)$f['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, VA_ALLOWED_EXT, true)) {
        throw new RuntimeException('File type ".' . e($ext) . '" is not allowed. Allowed: ' . implode(', ', VA_ALLOWED_EXT));
    }
    $stored = date('Ymd') . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) {
        throw new RuntimeException('Could not save the file. Check that the "uploads" folder is writable.');
    }
    $mime = function_exists('mime_content_type') ? (mime_content_type($dir . '/' . $stored) ?: 'application/octet-stream')
                                                 : 'application/octet-stream';
    return ['stored' => $stored, 'orig' => $orig, 'mime' => $mime, 'size' => (int)$f['size']];
}

/* ------------------------------------------------------------------- Mail */

/**
 * Minimal dependency-free SMTP client (AUTH LOGIN, optional TLS).
 * Configured under Settings → Email. Returns true on success.
 * When SMTP is not configured, callers fall back to in-app alerts.
 */
function send_mail(string $to, string $subject, string $htmlBody): bool {
    $host = setting('smtp_host');
    if (!$host) return false;
    $port = (int)setting('smtp_port', 587);
    $user = setting('smtp_user', '');
    $pass = setting('smtp_pass', '');
    $from = setting('smtp_from', 'tprm@localhost');
    $sec  = setting('smtp_security', 'tls'); // tls | ssl | none

    $remote = ($sec === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 10);
    if (!$fp) return false;
    stream_set_timeout($fp, 10);

    $read = function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $say = function (string $cmd, array $okCodes) use ($fp, $read): bool {
        fwrite($fp, $cmd . "\r\n");
        $resp = $read();
        return in_array((int)substr($resp, 0, 3), $okCodes, true);
    };

    try {
        $read();
        if (!$say('EHLO vendorassess360', [250])) return false;
        if ($sec === 'tls') {
            if (!$say('STARTTLS', [220])) return false;
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return false;
            if (!$say('EHLO vendorassess360', [250])) return false;
        }
        if ($user !== '') {
            if (!$say('AUTH LOGIN', [334])) return false;
            if (!$say(base64_encode($user), [334])) return false;
            if (!$say(base64_encode($pass), [235])) return false;
        }
        if (!$say('MAIL FROM:<' . $from . '>', [250])) return false;
        if (!$say('RCPT TO:<' . $to . '>', [250, 251])) return false;
        if (!$say('DATA', [354])) return false;
        $headers = 'From: VendorAssess 360 <' . $from . ">\r\n"
                 . 'To: <' . $to . ">\r\n"
                 . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
                 . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
        $bodySafe = preg_replace('/^\./m', '..', $htmlBody);
        if (!$say($headers . $bodySafe . "\r\n.", [250])) return false;
        $say('QUIT', [221]);
        return true;
    } catch (Throwable $e) {
        return false;
    } finally {
        @fclose($fp);
    }
}

/* --------------------------------------------------------------- CSV out */

function csv_download(string $filename, array $header, array $dataRows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 correctly
    fputcsv($out, $header);
    foreach ($dataRows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}
