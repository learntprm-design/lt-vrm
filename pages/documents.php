<?php
/** Documents Library — global vault across all vendors, with upload/version/expiry. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Documents Library';
$CATS = ['SOC 2','ISO 27001','Insurance','Policy','DPA','NDA','Contract','Financial','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('manage_documents')) {
    $act = $_POST['_action'] ?? '';
    if ($act === 'upload') {
        try {
            $vid = (int)($_POST['vendor_id'] ?? 0);
            if (!row('SELECT id FROM vendors WHERE id = ?', [$vid])) throw new RuntimeException('Choose a vendor.');
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') throw new RuntimeException('Document title is required.');
            $up = handle_upload('file');
            if (!$up) throw new RuntimeException('Choose a file to upload.');
            $cat = in_array($_POST['category'] ?? '', $CATS, true) ? $_POST['category'] : 'Other';
            // versioning: same title+vendor bumps version
            $prev = (int)scalar('SELECT MAX(version) FROM documents WHERE vendor_id = ? AND title = ?', [$vid, $title]);
            q('INSERT INTO documents (vendor_id, title, category, filename, orig_name, mime, size_bytes, version, tags, expiry_date, uploaded_by)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)',
              [$vid, $title, $cat, $up['stored'], $up['orig'], $up['mime'], $up['size'], $prev + 1,
               trim((string)($_POST['tags'] ?? '')) ?: null, trim((string)($_POST['expiry_date'] ?? '')) ?: null,
               current_user()['id']]);
            audit('document_uploaded', 'document', (int)db()->lastInsertId(), $title);
            recompute_risk($vid);
            flash('success', 'Document uploaded' . ($prev ? ' as version ' . ($prev + 1) : '') . '.');
        } catch (RuntimeException $ex) { flash('error', $ex->getMessage()); }
        redirect('documents');
    }
    if ($act === 'delete') {
        $d = row('SELECT * FROM documents WHERE id = ?', [(int)($_POST['doc_id'] ?? 0)]);
        if ($d) {
            if ($d['filename']) @unlink(__DIR__ . '/../uploads/' . $d['filename']);
            q('DELETE FROM documents WHERE id = ?', [$d['id']]);
            audit('document_deleted', 'document', (int)$d['id'], $d['title']);
            flash('success', 'Document deleted.');
        }
        redirect('documents');
    }
}

$catF = in_array($_GET['cat'] ?? '', $CATS, true) ? $_GET['cat'] : '';
$vidF = (int)($_GET['vendor'] ?? 0);
$qF   = trim((string)($_GET['q'] ?? ''));
$expF = ($_GET['exp'] ?? '') === '1';

$where = []; $params = [];
if ($catF) { $where[] = 'd.category = ?'; $params[] = $catF; }
if ($vidF) { $where[] = 'd.vendor_id = ?'; $params[] = $vidF; }
if ($qF !== '') { $where[] = '(d.title LIKE ? OR d.tags LIKE ? OR v.name LIKE ?)'; array_push($params, "%$qF%", "%$qF%", "%$qF%"); }
if ($expF) { $where[] = 'd.expiry_date IS NOT NULL AND d.expiry_date < DATE_ADD(CURDATE(), INTERVAL 60 DAY)'; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$pg = paginate("SELECT COUNT(*) FROM documents d JOIN vendors v ON v.id = d.vendor_id$w",
               "SELECT d.*, v.name vendor_name FROM documents d JOIN vendors v ON v.id = d.vendor_id$w ORDER BY d.created_at DESC",
               $params, 25);
$vendorOpts = rows('SELECT id, name FROM vendors ORDER BY name LIMIT 1100');
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Documents Library <span class="muted" style="font-size:1rem">(<?= (int)$pg['total'] ?>)</span></h1>
  <div class="spacer"></div>
  <?php if (can('manage_documents')): ?>
    <button class="btn btn-gold" data-open-modal="upload-modal">⇪ Upload document</button>
  <?php endif; ?>
</div>

<div class="card tight">
  <form method="get" action="index.php" style="display:flex;gap:.7rem;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="p" value="documents">
    <div style="flex:1;min-width:180px"><label>Search</label>
      <input type="text" name="q" value="<?= e($qF) ?>" placeholder="Title, tag or vendor…"></div>
    <div><label>Category</label><select name="cat"><option value="">All</option>
      <?php foreach ($CATS as $c): ?><option <?= $catF === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div><label>Vendor</label><select name="vendor"><option value="0">All vendors</option>
      <?php foreach ($vendorOpts as $vo): ?>
        <option value="<?= (int)$vo['id'] ?>" <?= $vidF === (int)$vo['id'] ? 'selected' : '' ?>><?= e($vo['name']) ?></option>
      <?php endforeach; ?></select></div>
    <div class="checkline" style="margin-bottom:.4rem"><input type="checkbox" id="exp" name="exp" value="1" <?= $expF ? 'checked' : '' ?>>
      <label for="exp">Expiring ≤60d / expired</label></div>
    <button class="btn btn-gold">Filter</button>
    <a class="btn btn-ghost" href="<?= e(url('documents')) ?>">Reset</a>
  </form>
</div>

<div class="card">
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Document</th><th>Vendor</th><th>Category</th><th>Ver</th><th>Size</th><th>Expiry</th><th></th></tr></thead>
    <tbody>
    <?php if (!$pg['items']): ?><tr><td colspan="7" class="muted">No documents match.</td></tr><?php endif; ?>
    <?php foreach ($pg['items'] as $d): $du = days_until($d['expiry_date']); ?>
      <tr>
        <td><strong><?= e($d['title']) ?></strong>
          <div class="small muted"><?= e($d['orig_name'] ?? '') ?><?= $d['tags'] ? ' · ' . e($d['tags']) : '' ?></div></td>
        <td><a href="<?= e(url('vendor_view', ['id' => $d['vendor_id'], 'tab' => 'documents'])) ?>"><?= e($d['vendor_name']) ?></a></td>
        <td><span class="badge badge-status"><?= e($d['category']) ?></span></td>
        <td class="muted">v<?= (int)$d['version'] ?></td>
        <td class="muted"><?= fmt_bytes((int)$d['size_bytes']) ?></td>
        <td><?= fmt_date($d['expiry_date']) ?>
          <?php if ($du !== null && $du < 0): ?> <span class="badge badge-critical">Expired</span>
          <?php elseif ($du !== null && $du <= 30): ?> <span class="badge badge-high"><?= $du ?>d</span>
          <?php elseif ($du !== null && $du <= 60): ?> <span class="badge badge-medium"><?= $du ?>d</span><?php endif; ?></td>
        <td style="white-space:nowrap">
          <?php if ($d['filename']): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('download', ['type' => 'document', 'id' => $d['id']])) ?>">⇓</a>
          <?php else: ?><span class="muted small" title="Demo record — no physical file">demo</span><?php endif; ?>
          <?php if (can('manage_documents')): ?>
          <form method="post" style="display:inline" data-confirm="Delete this document?"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="delete"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
            <button class="btn btn-sm btn-danger">✕</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?= pagination_links($pg, array_filter(['cat' => $catF, 'vendor' => $vidF ?: '', 'q' => $qF, 'exp' => $expF ? '1' : ''], fn($x) => $x !== '')) ?>
</div>

<div class="modal-back" id="upload-modal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0">Upload document</h2>
      <button class="btn btn-ghost btn-sm" data-close-modal>✕</button>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('documents')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="upload">
      <label>Vendor *</label>
      <select name="vendor_id" required><option value="">— choose —</option>
        <?php foreach ($vendorOpts as $vo): ?><option value="<?= (int)$vo['id'] ?>" <?= $vidF === (int)$vo['id'] ? 'selected' : '' ?>><?= e($vo['name']) ?></option><?php endforeach; ?>
      </select>
      <label>Title * <span class="muted">(re-using a title creates a new version)</span><?= hint('doc_version') ?></label>
      <input type="text" name="title" required>
      <div class="form-row">
        <div><label>Category</label><select name="category"><?php foreach ($CATS as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div><label>Expiry date <span class="muted">(optional)</span></label><input type="date" name="expiry_date"></div>
      </div>
      <label>Tags <span class="muted">(comma separated)</span></label><input type="text" name="tags">
      <label>File * <span class="muted">(pdf, docx, xlsx, png… max 15 MB)</span></label>
      <input type="file" name="file" required>
      <button class="btn btn-gold" style="margin-top:1.1rem">⇪ Upload</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php';
