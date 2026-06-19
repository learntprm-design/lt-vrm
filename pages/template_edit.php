<?php
/** Template builder — sections, question types, weights, evidence flags. */
require_perm('view');
$tid = (int)($_GET['id'] ?? 0);
$t = row('SELECT * FROM assessment_templates WHERE id = ?', [$tid]);
if (!$t) { flash('error', 'Template not found.'); redirect('templates'); }
$GLOBALS['VA_PAGE_TITLE'] = $t['name'];
$readonly = $t['is_builtin'] || !can('manage_assessments');
// Data-integrity lock: once assessments use this template, its question set is
// frozen. Deleting a question would destroy vendors' submitted answers, and
// adding one would corrupt in-flight scores. Duplicate the template to evolve it.
$inUse = (int)scalar('SELECT COUNT(*) FROM assessments WHERE template_id = ?', [$tid]);
$structureLocked = $inUse > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$readonly) {
    $act = $_POST['_action'] ?? '';
    if ($structureLocked && in_array($act, ['add_q', 'del_q'], true)) {
        flash('error', 'This template is used by ' . $inUse . ' assessment(s), so its questions are frozen to protect '
            . 'submitted vendor answers and score integrity. Duplicate the template and edit the copy instead.');
        redirect('template_edit', ['id' => $tid]);
    }
    if ($act === 'meta') {
        q('UPDATE assessment_templates SET name = ?, description = ?, framework = ? WHERE id = ?',
          [trim((string)$_POST['name']) ?: $t['name'], trim((string)$_POST['description']) ?: null,
           trim((string)$_POST['framework']) ?: 'Custom', $tid]);
        flash('success', 'Template details saved.');
        redirect('template_edit', ['id' => $tid]);
    }
    if ($act === 'add_q') {
        $qtext = trim((string)($_POST['question'] ?? ''));
        if ($qtext === '') { flash('error', 'Question text is required.'); redirect('template_edit', ['id' => $tid]); }
        $qtype = in_array($_POST['qtype'] ?? '', ['yesno','text','choice','scale'], true) ? $_POST['qtype'] : 'yesno';
        $choices = $qtype === 'choice' ? trim((string)($_POST['choices'] ?? '')) : null;
        if ($qtype === 'choice' && ($choices === '' || strpos($choices, '|') === false)) {
            flash('error', 'Choice questions need at least two options separated by | (pipe).');
            redirect('template_edit', ['id' => $tid]);
        }
        $max = (int)scalar('SELECT COALESCE(MAX(sort_order), -1) FROM template_questions WHERE template_id = ?', [$tid]);
        q('INSERT INTO template_questions (template_id, section, question, qtype, choices, weight, evidence_required, sort_order)
           VALUES (?,?,?,?,?,?,?,?)',
          [$tid, trim((string)($_POST['section'] ?? '')) ?: 'General', $qtext, $qtype, $choices,
           max(1, min(10, (int)($_POST['weight'] ?? 5))), !empty($_POST['evidence_required']) ? 1 : 0, $max + 1]);
        flash('success', 'Question added.');
        redirect('template_edit', ['id' => $tid]);
    }
    if ($act === 'del_q') {
        q('DELETE FROM template_questions WHERE id = ? AND template_id = ?', [(int)($_POST['qid'] ?? 0), $tid]);
        flash('success', 'Question removed.');
        redirect('template_edit', ['id' => $tid]);
    }
    if ($act === 'move') {
        $qid = (int)($_POST['qid'] ?? 0); $dir = $_POST['dir'] === 'up' ? -1 : 1;
        $cur = row('SELECT * FROM template_questions WHERE id = ? AND template_id = ?', [$qid, $tid]);
        if ($cur) {
            $swap = row('SELECT * FROM template_questions WHERE template_id = ? AND sort_order ' . ($dir < 0 ? '<' : '>') . ' ?
                         ORDER BY sort_order ' . ($dir < 0 ? 'DESC' : 'ASC') . ' LIMIT 1', [$tid, $cur['sort_order']]);
            if ($swap) {
                q('UPDATE template_questions SET sort_order = ? WHERE id = ?', [$swap['sort_order'], $cur['id']]);
                q('UPDATE template_questions SET sort_order = ? WHERE id = ?', [$cur['sort_order'], $swap['id']]);
            }
        }
        redirect('template_edit', ['id' => $tid]);
    }
}

$qs = rows('SELECT * FROM template_questions WHERE template_id = ? ORDER BY sort_order', [$tid]);
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <h1><?= e($t['name']) ?> <?= $t['is_builtin'] ? '<span class="pill">Built-in (read-only)</span>' : '' ?></h1>
  <div class="spacer"></div>
  <a class="btn btn-ghost" href="<?= e(url('templates')) ?>">← All templates</a>
</div>

<?php if (!$readonly): ?>
<div class="card">
  <h2>Template details</h2>
  <form method="post"><?= csrf_field() ?>
    <input type="hidden" name="_action" value="meta">
    <div class="form-row">
      <div><label>Name</label><input type="text" name="name" value="<?= e($t['name']) ?>"></div>
      <div><label>Framework</label><input type="text" name="framework" value="<?= e($t['framework'] ?? '') ?>"></div>
    </div>
    <label>Description</label><textarea name="description"><?= e($t['description'] ?? '') ?></textarea>
    <button class="btn btn-outline btn-sm" style="margin-top:.7rem">Save details</button>
  </form>
</div>
<?php endif; ?>

<div class="grid <?= $readonly ? '' : 'c2' ?>">
  <div class="card">
    <h2>Questions (<?= count($qs) ?>)</h2>
    <?php if (!$qs): ?><p class="muted">No questions yet<?= $readonly ? '.' : ' — add your first on the right.' ?></p><?php endif; ?>
    <?php $lastSection = null; foreach ($qs as $i => $qq): ?>
      <?php if ($qq['section'] !== $lastSection): $lastSection = $qq['section']; ?>
        <h3 class="gold" style="margin-top:1rem"><?= e($qq['section']) ?></h3>
      <?php endif; ?>
      <div class="card tight" style="background:var(--bg-2);margin-bottom:.5rem">
        <div style="display:flex;gap:.6rem;align-items:flex-start">
          <div style="flex:1">
            <p style="margin:0"><strong>Q<?= $i + 1 ?>.</strong> <?= e($qq['question']) ?></p>
            <p class="small muted" style="margin:.25rem 0 0">
              <?= e(['yesno' => 'Yes/No', 'text' => 'Free text', 'choice' => 'Multiple choice', 'scale' => 'Scale 1-5'][$qq['qtype']]) ?>
              <?= $qq['choices'] ? ' · options: ' . e(str_replace('|', ' / ', $qq['choices'])) : '' ?>
              · weight <?= (int)$qq['weight'] ?>/10
              <?= $qq['evidence_required'] ? ' · <span class="gold">evidence required</span>' : '' ?></p>
          </div>
          <?php if (!$readonly): ?>
          <div style="display:flex;gap:.25rem">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="move">
              <input type="hidden" name="qid" value="<?= (int)$qq['id'] ?>"><input type="hidden" name="dir" value="up">
              <button class="btn btn-sm btn-ghost" <?= $i === 0 ? 'disabled' : '' ?>>↑</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="move">
              <input type="hidden" name="qid" value="<?= (int)$qq['id'] ?>"><input type="hidden" name="dir" value="down">
              <button class="btn btn-sm btn-ghost" <?= $i === count($qs) - 1 ? 'disabled' : '' ?>>↓</button></form>
            <?php if (!$structureLocked): ?>
            <form method="post" data-confirm="Remove this question?"><?= csrf_field() ?>
              <input type="hidden" name="_action" value="del_q"><input type="hidden" name="qid" value="<?= (int)$qq['id'] ?>">
              <button class="btn btn-sm btn-danger">✕</button></form>
            <?php else: ?><span class="muted small" title="Frozen — template is in use">🔒</span><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$readonly && $structureLocked): ?>
  <div class="card">
    <h2>Questions frozen 🔒</h2>
    <p class="muted small">This template is used by <?= $inUse ?> assessment(s). Its question set is locked to protect
      submitted vendor answers and keep historical scores honest. To evolve the questionnaire, press
      <strong>Duplicate</strong> on the Templates page and edit the copy — that's how versioning works in mature TPRM programs.</p>
    <a class="btn btn-outline btn-sm" href="<?= e(url('templates')) ?>">Go to Templates →</a>
  </div>
  <?php elseif (!$readonly): ?>
  <div class="card">
    <h2>Add a question</h2>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="_action" value="add_q">
      <label>Section</label><input type="text" name="section" placeholder="e.g. Access Control" list="sections">
      <datalist id="sections"><?php foreach (array_unique(array_column($qs, 'section')) as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
      <label>Question *</label><textarea name="question" required></textarea>
      <div class="form-row">
        <div><label>Answer type</label>
          <select name="qtype" onchange="document.getElementById('choices-row').style.display = this.value==='choice' ? '' : 'none'">
            <option value="yesno">Yes / No</option><option value="text">Free text</option>
            <option value="choice">Multiple choice</option><option value="scale">Scale 1–5</option>
          </select></div>
        <div><label>Weight (1–10)<?= hint('question_weight') ?></label><input type="number" name="weight" min="1" max="10" value="5">
          <div class="help">Heavier questions matter more in the assessment score.</div></div>
      </div>
      <div id="choices-row" style="display:none">
        <label>Choices <span class="muted">(separate with | pipe)</span></label>
        <input type="text" name="choices" placeholder="Best option|Good option|Weak option|Worst option">
        <div class="help">List options <strong>best first, worst last</strong> — the scoring engine grades by position
          (first = full credit, last = zero). Example: “72 hours|7 days|30 days|No defined SLA”.</div>
      </div>
      <div class="checkline"><input type="checkbox" id="ev" name="evidence_required" value="1">
        <label for="ev">Vendor must attach evidence</label><?= hint('evidence') ?></div>
      <button class="btn btn-gold" style="margin-top:1rem">+ Add question</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php';
