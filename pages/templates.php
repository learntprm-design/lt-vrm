<?php
/** Questionnaire template library — built-ins + custom builder. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Questionnaire Templates';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('manage_assessments')) {
    $act = $_POST['_action'] ?? '';
    if ($act === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { flash('error', 'Template name is required.'); redirect('templates'); }
        q('INSERT INTO assessment_templates (name, description, framework, is_builtin, created_by) VALUES (?,?,?,0,?)',
          [$name, trim((string)($_POST['description'] ?? '')) ?: null,
           trim((string)($_POST['framework'] ?? '')) ?: 'Custom', current_user()['id']]);
        $tid = (int)db()->lastInsertId();
        audit('template_created', 'template', $tid, $name);
        flash('success', 'Template created — now add questions.');
        redirect('template_edit', ['id' => $tid]);
    }
    if ($act === 'duplicate') {
        $tid = (int)($_POST['template_id'] ?? 0);
        $t = row('SELECT * FROM assessment_templates WHERE id = ?', [$tid]);
        if ($t) {
            q('INSERT INTO assessment_templates (name, description, framework, is_builtin, created_by) VALUES (?,?,?,0,?)',
              [$t['name'] . ' (copy)', $t['description'], $t['framework'], current_user()['id']]);
            $nid = (int)db()->lastInsertId();
            foreach (rows('SELECT * FROM template_questions WHERE template_id = ? ORDER BY sort_order', [$tid]) as $qq) {
                q('INSERT INTO template_questions (template_id, section, question, qtype, choices, weight, evidence_required, sort_order)
                   VALUES (?,?,?,?,?,?,?,?)',
                  [$nid, $qq['section'], $qq['question'], $qq['qtype'], $qq['choices'], $qq['weight'], $qq['evidence_required'], $qq['sort_order']]);
            }
            flash('success', 'Template duplicated — the copy is editable.');
            redirect('template_edit', ['id' => $nid]);
        }
        redirect('templates');
    }
    if ($act === 'delete') {
        $tid = (int)($_POST['template_id'] ?? 0);
        $t = row('SELECT * FROM assessment_templates WHERE id = ? AND is_builtin = 0', [$tid]);
        $inUse = (int)scalar('SELECT COUNT(*) FROM assessments WHERE template_id = ?', [$tid]);
        if (!$t) { flash('error', 'Built-in templates cannot be deleted (duplicate them instead).'); }
        elseif ($inUse) { flash('error', 'Template is used by ' . $inUse . ' assessment(s) and cannot be deleted.'); }
        else { q('DELETE FROM assessment_templates WHERE id = ?', [$tid]); flash('success', 'Template deleted.'); }
        redirect('templates');
    }
}

$tpls = rows('SELECT t.*, (SELECT COUNT(*) FROM template_questions q WHERE q.template_id = t.id) qcount,
              (SELECT COUNT(*) FROM assessments a WHERE a.template_id = t.id) used
              FROM assessment_templates t ORDER BY t.is_builtin DESC, t.name');
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1>Questionnaire Templates</h1>
  <div class="spacer"></div>
  <?php if (can('manage_assessments')): ?><button class="btn btn-gold" data-open-modal="tpl-modal">+ New template</button><?php endif; ?>
</div>

<div class="grid c3">
<?php foreach ($tpls as $t): ?>
  <div class="card learn-card">
    <div style="display:flex;gap:.5rem;align-items:center">
      <h3 style="margin:0;flex:1"><?= e($t['name']) ?></h3>
      <?= $t['is_builtin'] ? '<span class="pill" style="font-size:.66rem">Built-in</span>' : '' ?>
    </div>
    <p class="muted small" style="min-height:2.4em"><?= e($t['description'] ?? '') ?></p>
    <p class="small"><span class="badge badge-status"><?= e($t['framework'] ?? 'Custom') ?></span>
      <span class="muted"> · <?= (int)$t['qcount'] ?> questions · used <?= (int)$t['used'] ?>×</span></p>
    <div style="display:flex;gap:.4rem;margin-top:.6rem;flex-wrap:wrap">
      <a class="btn btn-sm btn-outline" href="<?= e(url('template_edit', ['id' => $t['id']])) ?>"><?= $t['is_builtin'] ? 'View' : 'Edit' ?></a>
      <?php if (can('manage_assessments')): ?>
        <form method="post" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="_action" value="duplicate"><input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
          <button class="btn btn-sm btn-ghost">Duplicate</button></form>
        <?php if (!$t['is_builtin']): ?>
          <form method="post" style="display:inline" data-confirm="Delete this template?"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="delete"><input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
            <button class="btn btn-sm btn-danger">✕</button></form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="modal-back" id="tpl-modal">
  <div class="modal">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0">New template</h2><button class="btn btn-ghost btn-sm" data-close-modal>✕</button></div>
    <form method="post" action="<?= e(url('templates')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="create">
      <label>Name *</label><input type="text" name="name" required>
      <label>Framework</label><input type="text" name="framework" placeholder="Custom, ISO 27001, NIST…">
      <label>Description</label><textarea name="description"></textarea>
      <button class="btn btn-gold" style="margin-top:1rem">Create &amp; add questions →</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php';
