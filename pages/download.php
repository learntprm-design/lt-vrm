<?php
/** Secure file download — files live in /uploads behind a deny-all .htaccess. */
require_perm('view');
$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$map = [
    'document' => ['documents', 'orig_name'],
    'contract' => ['contracts', 'orig_name'],
    'evidence' => ['assessment_answers', 'evidence_orig'],
];
if (!isset($map[$type])) { http_response_code(400); exit('Unknown download type.'); }

if ($type === 'evidence') {
    $rec = row('SELECT evidence_file AS filename, evidence_orig AS orig FROM assessment_answers WHERE id = ?', [$id]);
} else {
    $rec = row('SELECT filename, orig_name AS orig FROM ' . $map[$type][0] . ' WHERE id = ?', [$id]);
}
if (!$rec || !$rec['filename']) {
    flash('error', 'This record has no file attached (demo records are metadata-only).');
    redirect($type === 'contract' ? 'contracts' : 'documents');
}
$path = realpath(__DIR__ . '/../uploads/' . $rec['filename']);
$base = realpath(__DIR__ . '/../uploads');
if (!$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) {
    flash('error', 'File missing from the uploads folder.');
    redirect($type === 'contract' ? 'contracts' : 'documents');
}
audit('file_download', $type, $id, (string)$rec['orig']);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)$rec['orig']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
