<?php
/**
 * LT-VRM — Risk Scoring Engine (0–1000)
 *
 * Low score = dangerous · High score = secure.
 *
 * The composite score is a transparent weighted model over six factors,
 * each first normalized to 0–100 (100 = best possible):
 *
 *   Factor                  Weight   Where it comes from
 *   ---------------------   ------   -------------------------------------
 *   Assessment results        25%    Latest approved assessment score
 *   Breach exposure           20%    Count/severity/recency of breaches
 *   Digital footprint         15%    pass/warn/fail hygiene checks
 *   Adverse media             10%    Relevant negative news (severity-weighted)
 *   Compliance health         15%    Valid docs + contract clauses + open issues
 *   Inherent criticality      15%    Onboarding inherent-risk questionnaire
 *
 * Composite = round( Σ(factor × weight) × 10 )  →  0–1000.
 * Bands: 0–399 Critical · 400–599 High · 600–749 Moderate · 750–899 Good · 900–1000 Excellent.
 */

function risk_weights(): array {
    return ['assessment' => 0.25, 'breach' => 0.20, 'footprint' => 0.15,
            'news' => 0.10, 'compliance' => 0.15, 'inherent' => 0.15];
}

/** Compute all factor scores (0-100 each) for a vendor. */
function risk_factors(int $vendorId): array {
    $v = row('SELECT * FROM vendors WHERE id = ?', [$vendorId]);
    if (!$v) return [];

    // 1) Assessment: latest approved score WITH AGE DECAY.
    //    Real TPRM: due diligence has a shelf life. Full credit for 12 months,
    //    then the factor decays linearly toward neutral-50 ("unknown") by month 24.
    //    A 3-year-old approval should never still vouch for a vendor.
    $asRow = row("SELECT score, decided_at FROM assessments WHERE vendor_id = ? AND status = 'approved'
                  AND score IS NOT NULL ORDER BY decided_at DESC LIMIT 1", [$vendorId]);
    if ($asRow) {
        $score = (float)$asRow['score'];
        $months = $asRow['decided_at'] ? (time() - strtotime($asRow['decided_at'])) / 2629800 : 0;
        if ($months <= 12)      $assessment = $score;
        elseif ($months >= 24)  $assessment = 50.0;
        else                    $assessment = $score + (($months - 12) / 12) * (50.0 - $score);
    } else {
        $assessment = 50.0; // never assessed = unknown, not good
    }

    // 2) Breach exposure: start at 100, subtract per finding by severity, recency-weighted.
    $breach = 100.0;
    foreach (rows('SELECT severity, breach_date, dark_web_mentions FROM breach_findings WHERE vendor_id = ?', [$vendorId]) as $b) {
        $penalty = ['low' => 5, 'medium' => 12, 'high' => 22, 'critical' => 35][$b['severity']] ?? 12;
        $ageYears = $b['breach_date'] ? max(0, (time() - strtotime($b['breach_date'])) / 31557600) : 1;
        $recency = $ageYears < 1 ? 1.0 : ($ageYears < 3 ? 0.7 : 0.4);   // old breaches hurt less
        $breach -= $penalty * $recency;
        $breach -= min(10, (int)$b['dark_web_mentions'] * 0.5);
    }
    $breach = max(0, $breach);

    // 3) Footprint hygiene: pass=1, warn=0.5, fail=0 over all checks; no data → neutral 60.
    $fp = row("SELECT SUM(status='pass') p, SUM(status='warn') w, SUM(status='fail') f,
               COUNT(*) t FROM footprint_findings WHERE vendor_id = ? AND status <> 'info'", [$vendorId]);
    $footprint = ($fp && (int)$fp['t'] > 0)
        ? (((int)$fp['p'] + 0.5 * (int)$fp['w']) / (int)$fp['t']) * 100
        : 60.0;

    // 4) Adverse media: 100 minus severity-weighted relevant items (analyst-dismissed items ignored).
    $news = 100.0;
    foreach (rows("SELECT severity FROM news_items WHERE vendor_id = ? AND (relevant = 1 OR relevant IS NULL)", [$vendorId]) as $n) {
        $news -= ['low' => 4, 'medium' => 8, 'high' => 15][$n['severity']] ?? 8;
    }
    $news = max(0, $news);

    // 5) Compliance health: documents valid + contract clauses + open issues.
    $score = 100.0;
    $expiredDocs = (int)scalar('SELECT COUNT(*) FROM documents WHERE vendor_id = ? AND expiry_date IS NOT NULL AND expiry_date < CURDATE()', [$vendorId]);
    $score -= min(30, $expiredDocs * 10);
    $con = row('SELECT clause_right_to_audit+clause_data_protection+clause_termination+clause_sla AS clauses
                FROM contracts WHERE vendor_id = ? AND status IN ("active","expiring")
                ORDER BY end_date DESC LIMIT 1', [$vendorId]);
    if ($con) { $score -= (4 - (int)$con['clauses']) * 5; }            // missing key clauses
    else      { $score -= 15; }                                        // no active contract at all
    $openIssues = rows("SELECT severity FROM issues WHERE vendor_id = ? AND status IN ('open','in_remediation','overdue')", [$vendorId]);
    foreach ($openIssues as $i) {
        $score -= ['low' => 3, 'medium' => 6, 'high' => 12, 'critical' => 20][$i['severity']] ?? 6;
    }
    $compliance = max(0, $score);

    // 6) Inherent criticality: invert the 0-100 inherent risk captured at onboarding.
    $inherent = max(0, 100 - (int)$v['inherent_risk']);

    return [
        'assessment' => round($assessment, 1), 'breach' => round($breach, 1),
        'footprint'  => round($footprint, 1),  'news'   => round($news, 1),
        'compliance' => round($compliance, 1), 'inherent' => round($inherent, 1),
    ];
}

/** Recompute, persist and return the composite 0-1000 score. */
function recompute_risk(int $vendorId): int {
    $factors = risk_factors($vendorId);
    if (!$factors) return 500;
    $total = 0.0;
    foreach (risk_weights() as $k => $w) $total += $factors[$k] * $w;
    $composite = (int)max(0, min(1000, round($total * 10)));

    $old = (int)scalar('SELECT risk_score FROM vendors WHERE id = ?', [$vendorId]);
    q('UPDATE vendors SET risk_score = ? WHERE id = ?', [$composite, $vendorId]);
    q('INSERT INTO risk_scores (vendor_id, score, breakdown) VALUES (?,?,?)',
      [$vendorId, $composite, json_encode($factors)]);

    if ($old && $composite < $old - 75) {
        $name = (string)scalar('SELECT name FROM vendors WHERE id = ?', [$vendorId]);
        add_alert($vendorId, 'score_drop', $name . ' risk score dropped from ' . $old . ' to ' . $composite, 'critical');
    }
    return $composite;
}

/** Human explanations of what is dragging a score down (worst factors first). */
function risk_drivers(array $factors): array {
    $labels = [
        'assessment' => 'Assessment results', 'breach' => 'Breach & dark-web exposure',
        'footprint' => 'Digital footprint hygiene', 'news' => 'Adverse media',
        'compliance' => 'Contract & document compliance', 'inherent' => 'Inherent criticality',
    ];
    $out = [];
    asort($factors);
    foreach ($factors as $k => $v) {
        if ($v < 70) $out[] = ['label' => $labels[$k] ?? $k, 'value' => $v];
    }
    return array_slice($out, 0, 3);
}
