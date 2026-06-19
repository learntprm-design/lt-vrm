<?php
/**
 * VendorAssess 360 — Vendor Portal (public, tokenized)
 * Vendors open this via the secure link — no account required.
 * They answer the questionnaire, attach evidence, and submit.
 * In clarification rounds only the flagged questions are editable.
 */
require __DIR__ . '/app/bootstrap.php';

$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$a = $token !== '' ? row('SELECT * FROM assessments WHERE token = ?', [$token]) : null;

function portal_shell(string $title, string $body): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . ' · VendorAssess 360</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css"></head><body>
<div style="min-height:100vh;background:radial-gradient(ellipse at 50% -20%, rgba(238,192,92,.07), transparent 55%), var(--bg);padding:2rem 1rem">
<div style="max-width:840px;margin:0 auto">
<div style="text-align:center;margin-bottom:1.4rem">
  <div style="font-family:var(--font-head);font-weight:700;font-size:1.4rem;color:#fff">Vendor<span style="color:var(--gold)">Assess</span> 360</div>
  <div class="small muted">Secure Vendor Portal · powered by LearnTPRM.com</div>
</div>' . $body . '
<p class="small muted" style="text-align:center;margin-top:1.6rem">This page is protected by a unique secure token. Do not share the link.</p>
</div></div><script src="assets/js/app.js"></script></body></html>';
    exit;
}

if (!$a) {
    portal_shell('Invalid link', '<div class="card empty-state"><div class="empty-icon">🔒</div>
        <h2>Link not valid</h2><p class="muted">This questionnaire link is invalid or has been withdrawn.
        Please contact the organization that sent it to you.</p></div>');
}

$v = row('SELECT name FROM vendors WHERE id = ?', [$a['vendor_id']]);
$questions = rows('SELECT * FROM template_questions WHERE template_id = ? ORDER BY sort_order', [$a['template_id']]);
$answers = [];
foreach (rows('SELECT * FROM assessment_answers WHERE assessment_id = ?', [$a['id']]) as $r) $answers[$r['question_id']] = $r;
$isClarRound = $a['status'] === 'clarification';
$editable = in_array($a['status'], ['sent', 'in_progress', 'clarification'], true);

/** In a clarification round only flagged questions may change. */
function q_editable(array $a, ?array $ans, bool $isClarRound): bool {
    if (!$isClarRound) return true;
    return $ans !== null && $ans['review_status'] === 'clarify';
}

/* ------------------------------ POST: save / submit ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editable) {
    csrf_check();
    $submitNow = ($_POST['_go'] ?? '') === 'submit';
    $missing = [];

    foreach ($questions as $qq) {
        $ans = $answers[$qq['id']] ?? null;
        if (!q_editable($a, $ans, $isClarRound)) continue;
        $val = trim((string)($_POST['q' . $qq['id']] ?? ''));
        $vendorComment = trim((string)($_POST['c' . $qq['id']] ?? ''));

        // evidence upload per question
        $evFile = null; $evOrig = null;
        if (!empty($_FILES['e' . $qq['id']]['name'])) {
            try {
                $up = handle_upload('e' . $qq['id']);
                if ($up) { $evFile = $up['stored']; $evOrig = $up['orig']; }
            } catch (RuntimeException $ex) {
                flash('error', 'Q' . $qq['id'] . ' evidence: ' . $ex->getMessage());
            }
        }

        if ($ans) {
            $sql = 'UPDATE assessment_answers SET answer = ?, vendor_comment = ?';
            $p = [$val, $vendorComment ?: $ans['vendor_comment']];
            if ($evFile) { $sql .= ', evidence_file = ?, evidence_orig = ?'; $p[] = $evFile; $p[] = $evOrig; }
            if ($isClarRound) { $sql .= ', review_status = "pending"'; } // re-submit for review
            $sql .= ' WHERE id = ?'; $p[] = $ans['id'];
            q($sql, $p);
        } else {
            q('INSERT INTO assessment_answers (assessment_id, question_id, answer, vendor_comment, evidence_file, evidence_orig)
               VALUES (?,?,?,?,?,?)', [$a['id'], $qq['id'], $val, $vendorComment ?: null, $evFile, $evOrig]);
        }
        if ($submitNow && $val === '') $missing[] = $qq['question'];
        /*
         * Domain-aware evidence rule (how a real TPRM program works):
         *  - Evidence proves a CLAIM. Answering "No" to a Yes/No control question
         *    is a disclosed gap, not a claim — no evidence is applicable.
         *  - For affirmative answers, the requirement is satisfied by a file OR a
         *    written explanation (≥20 chars) of why no document exists. The analyst
         *    sees the explanation and judges it — that's their call, not a form's.
         */
        if ($submitNow && $qq['evidence_required']) {
            $isNegative = $qq['qtype'] === 'yesno' && $val === 'No';
            $hasEvidence = $evFile || ($ans['evidence_file'] ?? null);
            $explanation = trim($vendorComment !== '' ? $vendorComment : (string)($ans['vendor_comment'] ?? ''));
            if (!$isNegative && !$hasEvidence && mb_strlen($explanation) < 20) {
                $missing[] = '[Evidence or a short explanation needed] ' . $qq['question'];
            }
        }
    }

    if ($submitNow) {
        // Validation applies in clarification rounds too — but only to the
        // reopened questions (locked ones are skipped above), so the analyst's
        // explicit request for evidence can't be answered with silence.
        if ($missing) {
            flash('error', count($missing) . ' item(s) still need attention before you can submit. They are highlighted below.');
            if (!$isClarRound) q('UPDATE assessments SET status = "in_progress" WHERE id = ?', [$a['id']]);
        } else {
            q('UPDATE assessments SET status = "submitted", submitted_at = NOW() WHERE id = ?', [$a['id']]);
            q('INSERT INTO assessment_events (assessment_id, actor, event) VALUES (?,?,?)',
              [$a['id'], 'Vendor', $isClarRound ? 'Clarifications submitted (round ' . $a['round'] . ')' : 'Questionnaire submitted']);
            add_alert((int)$a['vendor_id'], 'assessment_due', 'Assessment "' . $a['title'] . '" was submitted and awaits review', 'info');
        }
    } else {
        if ($a['status'] === 'sent') q('UPDATE assessments SET status = "in_progress" WHERE id = ?', [$a['id']]);
        flash('success', 'Progress saved. You can return any time with the same link.');
    }
    header('Location: portal.php?t=' . $token);
    exit;
}

/* ------------------------------ render ------------------------------ */
$flashHtml = '';
foreach (flashes() as $f) {
    $flashHtml .= '<div class="flash flash-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
}

if (in_array($a['status'], ['submitted', 'in_review'], true)) {
    portal_shell('Submitted', $flashHtml . '<div class="card empty-state"><div class="empty-icon">📨</div>
        <h2>Thank you — questionnaire submitted</h2>
        <p class="muted">Your answers for <strong>' . e($a['title']) . '</strong> are with the TPRM analyst team.
        If anything needs clarification, you\'ll receive this link again with the open points flagged.</p></div>');
}
if ($a['status'] === 'approved') {
    portal_shell('Approved', '<div class="card empty-state"><div class="empty-icon">🏆</div>
        <h2>Assessment approved</h2><p class="muted">Your assessment <strong>' . e($a['title']) . '</strong> was reviewed and approved'
        . ($a['score'] !== null ? ' with a score of <strong class="gold">' . (int)$a['score'] . '/100</strong>' : '') . '. No further action is needed.</p></div>');
}
if ($a['status'] === 'rejected') {
    portal_shell('Decision', '<div class="card empty-state"><div class="empty-icon">📋</div>
        <h2>Assessment closed</h2><p class="muted">The review of <strong>' . e($a['title']) . '</strong> has been completed.
        Your contact at the requesting organization will be in touch about next steps.</p></div>');
}
if ($a['status'] === 'draft') {
    portal_shell('Not ready', '<div class="card empty-state"><div class="empty-icon">⏳</div>
        <h2>Not available yet</h2><p class="muted">This questionnaire has not been released. Please check back later.</p></div>');
}

$body = $flashHtml . '<div class="card">
  <h2>' . e($a['title']) . '</h2>
  <p class="muted small">For <strong>' . e($v['name'] ?? 'your organization') . '</strong>'
  . ($a['due_date'] ? ' · due <strong>' . e(fmt_date($a['due_date'])) . '</strong>' : '') .
  ($isClarRound ? ' · <span class="badge badge-high">Clarification round ' . (int)$a['round'] . '</span>' : '') . '</p>'
  . ($isClarRound
     ? '<div class="flash flash-info">The analyst needs clarification on the highlighted questions only. Other answers are locked.</div>'
     : '<p class="muted small">Answer every question. Questions marked <span class="gold">evidence required</span> need an attachment. You can save and come back any time — same link.</p>')
  . '</div>'
  . '<details class="page-hint"><summary><span class="ph-ico">💡</span> <strong>First time here? How this works</strong> '
  . '<span class="ph-more">Show me ▾</span></summary><ul>'
  . '<li><strong>Step 1 — Answer:</strong> Go question by question. Yes/No and dropdowns take seconds; text boxes want short, honest answers. Use the comment field to add context for the analyst.</li>'
  . '<li><strong>Step 2 — Attach proof:</strong> Where it says “evidence required”, attach the real document (policy PDF, certificate, screenshot). Max 15 MB per file.</li>'
  . '<li><strong>Step 3 — Save or Submit:</strong> “Save progress” keeps everything so you can return later with this same link. “Submit” sends it for review — after that, editing reopens only if the analyst asks for clarification.</li>'
  . '<li><strong>Privacy:</strong> This link shows only this one questionnaire. No account, no password, nothing else is visible to you.</li>'
  . '</ul></details>';

$body .= '<form method="post" enctype="multipart/form-data" action="portal.php?t=' . e($token) . '">'
       . csrf_field();

$lastSection = null; $num = 0;
foreach ($questions as $qq) {
    $num++;
    $ans = $answers[$qq['id']] ?? null;
    $canEdit = q_editable($a, $ans, $isClarRound);
    if ($qq['section'] !== $lastSection) {
        $lastSection = $qq['section'];
        $body .= '<h3 class="gold" style="margin:1.3rem 0 .6rem">' . e($qq['section']) . '</h3>';
    }
    $highlight = $isClarRound && $canEdit ? 'border-color:var(--gold);' : '';
    $dim = !$canEdit && $isClarRound ? 'opacity:.55;' : '';
    $body .= '<div class="card" style="' . $highlight . $dim . '">';
    $body .= '<p style="margin:0 0 .5rem"><strong>Q' . $num . '.</strong> ' . e($qq['question'])
           . ($qq['evidence_required']
              ? ' <span class="gold small">· evidence required</span>'
                . '<span class="small muted" style="display:block;margin-top:.15rem">Attach a file, or explain in the comment why no document exists.'
                . ($qq['qtype'] === 'yesno' ? ' If your answer is “No”, no evidence is needed — a No is a disclosure, not a claim.' : '')
                . '</span>'
              : '') . '</p>';
    if ($ans && $ans['review_status'] === 'clarify' && $ans['reviewer_comment']) {
        $body .= '<div class="flash flash-info" style="margin:.5rem 0"><strong>Analyst asks:</strong> ' . e($ans['reviewer_comment']) . '</div>';
    }
    $val = $ans['answer'] ?? '';
    $dis = $canEdit ? '' : 'disabled';
    if ($qq['qtype'] === 'yesno') {
        foreach (['Yes', 'No'] as $opt) {
            $body .= '<label class="checkline" style="display:inline-flex;margin-right:1.4rem">
                <input type="radio" name="q' . $qq['id'] . '" value="' . $opt . '" ' . ($val === $opt ? 'checked' : '') . ' ' . $dis . '> ' . $opt . '</label>';
        }
    } elseif ($qq['qtype'] === 'scale') {
        $body .= '<select name="q' . $qq['id'] . '" ' . $dis . ' style="max-width:220px"><option value="">— select —</option>';
        for ($i = 1; $i <= 5; $i++) {
            $body .= '<option value="' . $i . '" ' . ((string)$i === $val ? 'selected' : '') . '>' . $i . ($i === 1 ? ' (lowest)' : ($i === 5 ? ' (highest)' : '')) . '</option>';
        }
        $body .= '</select>';
    } elseif ($qq['qtype'] === 'choice') {
        $body .= '<select name="q' . $qq['id'] . '" ' . $dis . ' style="max-width:380px"><option value="">— select —</option>';
        foreach (explode('|', (string)$qq['choices']) as $opt) {
            $opt = trim($opt);
            $body .= '<option ' . ($opt === $val ? 'selected' : '') . '>' . e($opt) . '</option>';
        }
        $body .= '</select>';
    } else {
        $body .= '<textarea name="q' . $qq['id'] . '" ' . $dis . ' placeholder="Your answer…">' . e($val) . '</textarea>';
    }
    if ($canEdit) {
        $body .= '<label class="small">Attach evidence' . ($ans && $ans['evidence_orig'] ? ' <span class="muted">(current: ' . e($ans['evidence_orig']) . ')</span>' : '') . '</label>
                  <input type="file" name="e' . $qq['id'] . '">';
        $body .= '<label class="small">Comment to the analyst (optional)</label>
                  <input type="text" name="c' . $qq['id'] . '" value="' . e($ans['vendor_comment'] ?? '') . '">';
    } elseif ($ans && $ans['evidence_orig']) {
        $body .= '<p class="small muted">Evidence on file: ' . e($ans['evidence_orig']) . '</p>';
    }
    $body .= '</div>';
}

$body .= '<div class="card" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">
  <button class="btn btn-ghost" name="_go" value="save">💾 Save progress</button>
  <button class="btn btn-gold" name="_go" value="submit">📨 Submit ' . ($isClarRound ? 'clarifications' : 'questionnaire') . '</button>'
  . hint('save_vs_submit') . '
</div></form>';

portal_shell($a['title'], $body);
