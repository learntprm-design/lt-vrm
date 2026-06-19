<?php
/**
 * Analyst review desk — per-question Approve / Reject / Request clarification,
 * then an overall decision. Clarifications go back to the vendor (new round),
 * unlimited times, with a full event trail.
 */
require_perm('view');
$aid = (int)($_GET['id'] ?? 0);
$a = row('SELECT a.*, v.name vendor_name, t.name tpl_name FROM assessments a
          JOIN vendors v ON v.id = a.vendor_id JOIN assessment_templates t ON t.id = a.template_id
          WHERE a.id = ?', [$aid]);
if (!$a) { flash('error', 'Assessment not found.'); redirect('assessments'); }
$GLOBALS['VA_PAGE_TITLE'] = $a['title'];

$questions = rows('SELECT * FROM template_questions WHERE template_id = ? ORDER BY sort_order', [$a['template_id']]);
$answers = [];
foreach (rows('SELECT * FROM assessment_answers WHERE assessment_id = ?', [$aid]) as $r) $answers[$r['question_id']] = $r;

/**
 * Domain-graded scoring (how a 25-year TPRM expert scores):
 * Approving an answer means "I accept this as TRUE" — it does NOT mean the
 * control is good. A truthful "We have no MFA" must still hurt the score.
 *
 *   question score = weight × analyst-accepted × answer credit
 *
 * Answer credit by type:
 *   Yes/No   — Yes = 1.00 · No = 0.25 (gap exists, honestly disclosed)
 *   Scale    — (value − 1) / 4   (1→0.0 … 5→1.0)
 *   Choice   — graded by position; templates list options best → worst
 *   Text     — 1.00 (the analyst's accept/reject IS the judgment)
 *
 * Rejected / unreviewed answers earn 0.
 */
function answer_credit(array $q, $ansVal): float {
    $v = trim((string)$ansVal);
    if ($v === '') return 0.0;
    switch ($q['qtype']) {
        case 'yesno':
            return $v === 'Yes' ? 1.0 : 0.25;
        case 'scale':
            return max(0.0, min(1.0, ((int)$v - 1) / 4));
        case 'choice':
            $opts = array_map('trim', explode('|', (string)$q['choices']));
            $i = array_search($v, $opts, true);
            $n = count($opts);
            return ($i === false || $n < 2) ? 1.0 : 1.0 - ($i / ($n - 1));
        default:
            return 1.0;
    }
}

/** Is this answer a risk-relevant control gap the analyst should notice? */
function is_control_gap(array $q, $ansVal): bool {
    return answer_credit($q, $ansVal) <= 0.34 && trim((string)$ansVal) !== '';
}

function compute_assessment_score(array $questions, array $answers): int {
    $tot = 0.0; $got = 0.0;
    foreach ($questions as $qq) {
        $w = max(1, (int)$qq['weight']);
        $tot += $w;
        $ans = $answers[$qq['id']] ?? null;
        if ($ans && $ans['review_status'] === 'approved') {
            $got += $w * answer_credit($qq, $ans['answer']);
        }
    }
    return $tot > 0 ? (int)round(100 * $got / $tot) : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('manage_assessments')) {
    $act = $_POST['_action'] ?? '';

    if ($act === 'review_q') {
        $qid = (int)($_POST['question_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        if (isset($answers[$qid]) && in_array($decision, ['approved','rejected','clarify','pending'], true)) {
            q('UPDATE assessment_answers SET review_status = ?, reviewer_comment = ? WHERE assessment_id = ? AND question_id = ?',
              [$decision, trim((string)($_POST['comment'] ?? '')) ?: null, $aid, $qid]);
            if ($a['status'] === 'submitted') q('UPDATE assessments SET status = "in_review" WHERE id = ?', [$aid]);
        }
        redirect('assessment_review', ['id' => $aid]);
    }

    if ($act === 'decide') {
        $decision = $_POST['decision'] ?? '';
        $byName = current_user()['name'];
        if ($decision === 'approve') {
            $score = compute_assessment_score($questions, $answers);
            q('UPDATE assessments SET status = "approved", score = ?, reviewed_by = ?, decided_at = NOW() WHERE id = ?',
              [$score, current_user()['id'], $aid]);
            q('INSERT INTO assessment_events (assessment_id, actor, event) VALUES (?,?,?)',
              [$aid, $byName, 'Approved with score ' . $score . '/100']);
            audit('assessment_approved', 'assessment', $aid, 'score ' . $score);
            // Expert behavior: approval ≠ ignoring gaps. Auto-raise issues for
            // accepted control gaps on heavyweight questions so they get remediated.
            $gapsRaised = 0;
            foreach ($questions as $qq) {
                $ans = $answers[$qq['id']] ?? null;
                if ($ans && $ans['review_status'] === 'approved'
                    && (int)$qq['weight'] >= 6 && is_control_gap($qq, $ans['answer'])) {
                    $sev = (int)$qq['weight'] >= 8 ? 'high' : 'medium';
                    q('INSERT INTO issues (vendor_id, title, description, severity, status, sla_due, remediation_plan, source)
                       VALUES (?,?,?,?,?,?,?,?)',
                      [(int)$a['vendor_id'], 'Control gap: ' . mb_substr($qq['question'], 0, 150),
                       'Disclosed in assessment "' . $a['title'] . '" — answer: "' . mb_substr((string)$ans['answer'], 0, 100)
                       . '". The vendor was honest; now the gap needs a remediation plan.',
                       $sev, 'open', date('Y-m-d', strtotime($sev === 'high' ? '+30 days' : '+60 days')),
                       'Ask the vendor for a remediation timeline and track it here.', 'assessment']);
                    $gapsRaised++;
                }
            }
            recompute_risk((int)$a['vendor_id']);
            // Lifecycle intelligence: approved due diligence completes onboarding.
            $lc = (string)scalar('SELECT lifecycle FROM vendors WHERE id = ?', [(int)$a['vendor_id']]);
            $promoted = false;
            if ($lc === 'onboarding') {
                q('UPDATE vendors SET lifecycle = "active" WHERE id = ?', [(int)$a['vendor_id']]);
                audit('lifecycle_change', 'vendor', (int)$a['vendor_id'], 'auto: onboarding → active (assessment approved)');
                $promoted = true;
            }
            flash('success', 'Assessment approved — score ' . $score . '/100. Vendor risk score updated.'
                . ($gapsRaised ? " $gapsRaised control-gap issue(s) auto-raised for remediation tracking." : '')
                . ($promoted ? ' Vendor promoted from Onboarding to Active — due diligence complete.' : ''));
        } elseif ($decision === 'reject') {
            $score = compute_assessment_score($questions, $answers);
            q('UPDATE assessments SET status = "rejected", score = ?, reviewed_by = ?, decided_at = NOW() WHERE id = ?',
              [$score, current_user()['id'], $aid]);
            q('INSERT INTO assessment_events (assessment_id, actor, event) VALUES (?,?,?)',
              [$aid, $byName, 'Rejected (score ' . $score . '/100)']);
            audit('assessment_rejected', 'assessment', $aid);
            // auto-raise a finding
            q('INSERT INTO issues (vendor_id, title, description, severity, status, sla_due, source) VALUES (?,?,?,?,?,?,?)',
              [(int)$a['vendor_id'], 'Failed assessment: ' . $a['title'],
               'The assessment was rejected by the analyst. Review answers and decide on remediation or exit.',
               'high', 'open', date('Y-m-d', strtotime('+30 days')), 'assessment']);
            recompute_risk((int)$a['vendor_id']);
            flash('success', 'Assessment rejected. A high-severity issue was raised automatically.');
        } elseif ($decision === 'clarify') {
            $flagged = (int)scalar('SELECT COUNT(*) FROM assessment_answers WHERE assessment_id = ? AND review_status = "clarify"', [$aid]);
            if (!$flagged) {
                flash('error', 'Mark at least one question "Request clarification" first — that is what the vendor will see.');
            } else {
                q('UPDATE assessments SET status = "clarification", round = round + 1 WHERE id = ?', [$aid]);
                q('INSERT INTO assessment_events (assessment_id, actor, event) VALUES (?,?,?)',
                  [$aid, $byName, 'Clarification requested on ' . $flagged . ' question(s) — round ' . ((int)$a['round'] + 1)]);
                audit('assessment_clarification', 'assessment', $aid, $flagged . ' questions');
                $link = app_url('portal.php') . '?t=' . $a['token'];
                $mailed = $a['sent_to_email']
                    ? send_mail($a['sent_to_email'], 'Clarification needed: ' . $a['title'],
                        '<p>The TPRM analyst needs clarification on ' . $flagged . ' question(s).</p>
                         <p><a href="' . e($link) . '">Open the questionnaire</a> — only the flagged questions are editable.</p>')
                    : false;
                flash('success', 'Sent back to the vendor for clarification (round ' . ((int)$a['round'] + 1) . ').' .
                      ($mailed ? ' Email sent.' : ' Share the portal link if needed.'));
            }
        }
        redirect('assessment_review', ['id' => $aid]);
    }

    if ($act === 'resend') {
        q('UPDATE assessments SET status = "sent" WHERE id = ? AND status = "draft"', [$aid]);
        q('INSERT INTO assessment_events (assessment_id, actor, event) VALUES (?,?,?)',
          [$aid, current_user()['name'], 'Re-sent to vendor']);
        flash('success', 'Assessment re-sent — the portal link is active again.');
        redirect('assessment_review', ['id' => $aid]);
    }

    if ($act === 'withdraw') {
        q('UPDATE assessments SET status = "draft" WHERE id = ?', [$aid]);
        q('INSERT INTO assessment_events (assessment_id, actor, event) VALUES (?,?,?)',
          [$aid, current_user()['name'], 'Withdrawn to draft']);
        flash('success', 'Assessment withdrawn — the vendor link is paused while in draft.');
        redirect('assessment_review', ['id' => $aid]);
    }
}

$events = rows('SELECT * FROM assessment_events WHERE assessment_id = ? ORDER BY created_at DESC', [$aid]);
$reviewable = in_array($a['status'], ['submitted','in_review'], true) && can('manage_assessments');
$liveScore = compute_assessment_score($questions, $answers);
$counts = ['approved' => 0, 'rejected' => 0, 'clarify' => 0, 'pending' => 0];
foreach ($answers as $ans) { $counts[$ans['review_status']] = ($counts[$ans['review_status']] ?? 0) + 1; }
$portalLink = app_url('portal.php') . '?t=' . $a['token'];
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar">
  <div>
    <h1><?= e($a['title']) ?> <?= status_badge($a['status']) ?></h1>
    <div class="muted small"><a href="<?= e(url('vendor_view', ['id' => $a['vendor_id']])) ?>"><?= e($a['vendor_name']) ?></a>
      · <?= e($a['tpl_name']) ?> · round <?= (int)$a['round'] ?> · due <?= fmt_date($a['due_date']) ?></div>
  </div>
  <div class="spacer"></div>
  <a class="btn btn-ghost" href="<?= e(url('assessments')) ?>">← Pipeline</a>
</div>

<div class="grid c4">
  <div class="card kpi tight"><div class="kpi-label">Approved</div><div class="kpi-value" style="color:var(--green);font-size:1.4rem"><?= $counts['approved'] ?></div></div>
  <div class="card kpi tight"><div class="kpi-label">Clarify</div><div class="kpi-value" style="color:var(--orange);font-size:1.4rem"><?= $counts['clarify'] ?></div></div>
  <div class="card kpi tight"><div class="kpi-label">Rejected</div><div class="kpi-value" style="color:var(--red);font-size:1.4rem"><?= $counts['rejected'] ?></div></div>
  <div class="card kpi tight"><div class="kpi-label">Score if approved now</div><div class="kpi-value" style="font-size:1.4rem"><?= $liveScore ?>/100</div></div>
</div>

<?php if (in_array($a['status'], ['sent','in_progress','clarification','draft'], true)): ?>
  <div class="card">
    <h2>Waiting on the vendor</h2>
    <p class="muted small">Status “<?= e(str_replace('_',' ',$a['status'])) ?>” — the questionnaire is with the vendor. Their secure portal link:<?= hint('portal_link') ?></p>
    <input type="text" readonly value="<?= e($portalLink) ?>" onclick="this.select()" style="font-family:monospace">
    <?php if (can('manage_assessments') && $a['status'] !== 'draft'): ?>
      <form method="post" style="margin-top:.7rem" data-confirm="Withdraw this assessment? The vendor link pauses until you re-send.">
        <?= csrf_field() ?><input type="hidden" name="_action" value="withdraw">
        <button class="btn btn-ghost btn-sm">Withdraw to draft</button>
      </form>
    <?php elseif (can('manage_assessments') && $a['status'] === 'draft'): ?>
      <form method="post" style="margin-top:.7rem">
        <?= csrf_field() ?><input type="hidden" name="_action" value="resend">
        <button class="btn btn-gold btn-sm">✉ Re-send to vendor</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php $num = 0; $lastSection = null;
foreach ($questions as $qq): $num++; $ans = $answers[$qq['id']] ?? null;
  if ($qq['section'] !== $lastSection) { $lastSection = $qq['section'];
    echo '<h3 class="gold" style="margin:1.2rem 0 .6rem">' . e($qq['section']) . '</h3>'; } ?>
  <div class="card">
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
      <div style="flex:1;min-width:280px">
        <p style="margin:0"><strong>Q<?= $num ?>.</strong> <?= e($qq['question']) ?>
          <span class="muted small">(weight <?= (int)$qq['weight'] ?>)</span></p>
        <?php if ($ans && $ans['answer'] !== null && $ans['answer'] !== ''): ?>
          <p style="margin:.5rem 0 0"><span class="badge badge-status">Answer</span> <strong><?= e($ans['answer']) ?></strong>
            <?php if (is_control_gap($qq, $ans['answer'])): ?>
              <span class="badge badge-high" title="Risk-relevant answer — the control is weak or missing. Approving accepts it as TRUE; a remediation issue will be auto-raised on approval for weighty questions.">⚠ Control gap</span>
            <?php endif; ?></p>
        <?php else: ?>
          <p class="muted small" style="margin:.5rem 0 0">Not answered yet.</p>
        <?php endif; ?>
        <?php if ($ans && $ans['vendor_comment']): ?>
          <p class="small muted" style="margin:.3rem 0 0">Vendor comment: <?= e($ans['vendor_comment']) ?></p>
        <?php endif; ?>
        <?php if ($ans && $ans['evidence_orig']): ?>
          <p class="small" style="margin:.3rem 0 0">📎 <a href="<?= e(url('download', ['type' => 'evidence', 'id' => $ans['id']])) ?>"><?= e($ans['evidence_orig']) ?></a></p>
        <?php elseif ($qq['evidence_required']): ?>
          <?php if ($ans && $qq['qtype'] === 'yesno' && $ans['answer'] === 'No'): ?>
            <p class="small muted" style="margin:.3rem 0 0">No evidence applicable — the answer is “No” (a disclosed gap, not a claim).</p>
          <?php elseif ($ans && mb_strlen(trim((string)$ans['vendor_comment'])) >= 20): ?>
            <p class="small" style="margin:.3rem 0 0;color:var(--blue)">No file — vendor explained why (see their comment). Judge the explanation with ✓ / ? / ✕.</p>
          <?php else: ?>
            <p class="small" style="margin:.3rem 0 0;color:var(--orange)">Evidence required — no file and no explanation.</p>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($ans && $ans['reviewer_comment']): ?>
          <p class="small" style="margin:.3rem 0 0;color:var(--blue)">Your note: <?= e($ans['reviewer_comment']) ?></p>
        <?php endif; ?>
      </div>
      <div style="min-width:230px">
        <?php if ($ans): ?>
          <p style="margin:0 0 .4rem"><?=
            ['approved' => '<span class="badge badge-low">✓ Approved</span>',
             'rejected' => '<span class="badge badge-critical">✕ Rejected</span>',
             'clarify'  => '<span class="badge badge-high">? Clarification requested</span>',
             'pending'  => '<span class="badge badge-status">Pending review</span>'][$ans['review_status']] ?></p>
          <?php if ($reviewable): ?>
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="_action" value="review_q">
            <input type="hidden" name="question_id" value="<?= (int)$qq['id'] ?>">
            <input type="text" name="comment" placeholder="Comment to vendor (for clarify/reject)"
                   value="<?= e($ans['reviewer_comment'] ?? '') ?>" style="margin-bottom:.4rem">
            <div style="display:flex;gap:.3rem;flex-wrap:wrap">
              <button class="btn btn-sm btn-outline" name="decision" value="approved">✓</button>
              <button class="btn btn-sm btn-ghost" name="decision" value="clarify">?</button>
              <button class="btn btn-sm btn-danger" name="decision" value="rejected">✕</button>
            </div>
          </form>
          <?php endif; ?>
        <?php else: ?><p class="muted small">—</p><?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($reviewable): ?>
<div class="card" style="border-color:var(--gold-2)">
  <h2>Overall decision<?= hint('review_actions') ?></h2>
  <p class="muted small">Approve (locks the score into the vendor's risk model), send back for clarification
    (only questions marked “?” reopen for the vendor), or reject (auto-raises a high-severity issue).</p>
  <form method="post" style="display:flex;gap:.7rem;flex-wrap:wrap"><?= csrf_field() ?>
    <input type="hidden" name="_action" value="decide">
    <button class="btn btn-gold" name="decision" value="approve"
            onclick="return confirm('Approve with score <?= $liveScore ?>/100?')">✓ Approve (<?= $liveScore ?>/100)</button>
    <button class="btn btn-outline" name="decision" value="clarify">? Request clarification (<?= $counts['clarify'] ?>)</button>
    <button class="btn btn-danger" name="decision" value="reject"
            onclick="return confirm('Reject this assessment? An issue will be raised automatically.')">✕ Reject</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>History</h2>
  <ul class="timeline">
    <?php foreach ($events as $ev): ?>
      <li><strong><?= e($ev['event']) ?></strong>
        <span class="muted small"> — <?= e($ev['actor']) ?>, <?= e(date('M j, Y H:i', strtotime($ev['created_at']))) ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php include __DIR__ . '/../partials/footer.php';
