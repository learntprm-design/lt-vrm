<?php
/**
 * VendorAssess 360 — In-product Guidance Engine
 *
 * One registry powers three things:
 *   1. hint('key')        — small ⓘ icon, click to read "what is this & what can I do"
 *   2. page_hint_strip()  — automatic "About this page" bar on every screen
 *   3. journey_drawer()   — floating 🧭 Guide with a role-aware getting-started
 *                           journey whose steps tick themselves as you use the app
 *
 * Everything is plain English and practical, so nobody needs outside help.
 * CSS-only popovers (<details>) — works even with JavaScript disabled.
 */

/** Inline hint registry: component-level explanations. */
function va_hint_texts(): array {
    return [
        'risk_score' => ['The 0–1000 Risk Score',
            "One number that sums up how safe this vendor is. LOW = dangerous, HIGH = secure. It blends six things: their questionnaire results (25%), breach history (20%), how healthy their public internet setup looks (15%), paperwork & contract health (15%), how critical they are to you (15%), and negative news (10%).",
            ["Click the vendor → Risk Score tab to see exactly what is pulling the score down",
             "Press “Recalculate now” after you change anything important",
             "Red (0–399) means act now; green (900+) means relax"]],
        'tier' => ['Criticality tier',
            "How important this vendor is to YOUR business — not how risky they are. A tiny supplier of pens is Low even if their security is bad; your payment processor is Critical even if their security is great.",
            ["Let the onboarding wizard suggest the tier from 6 simple questions",
             "Give Critical and High vendors deeper assessments and more frequent reviews"]],
        'lifecycle' => ['Lifecycle stage',
            "Where the relationship stands: Onboarding (just added) → Active (working together) → Under review (something needs a look) → Offboarding (leaving) → Terminated (gone).",
            ["Change it with this dropdown any time",
             "Choosing “Offboarding” automatically creates an 8-step exit checklist for you"]],
        'demo_mode' => ['Demo Mode vs Live',
            "No API key or no internet? Scans still work using realistic practice data, clearly labeled DEMO. Add free/paid keys in Settings → Integrations and the same buttons fetch real data, labeled LIVE.",
            ["You can demo and learn the platform fully offline",
             "Demo results are stable per vendor, so screenshots stay consistent"]],
        'inherent_risk' => ['Inherent risk',
            "The risk that exists before looking at the vendor's defenses — driven by what data they touch, whether they reach your network, and how critical they are. Your 6 wizard answers each add points (0–100 total).",
            ["70+ points → Critical tier suggested; under 25 → Low",
             "You can always override the suggestion on the last step"]],
        'bulk_import' => ['Bulk upload rules',
            "One vendor per row. Only the “name” column is mandatory. Rows with problems are skipped while good rows still import — you never lose a whole file to one bad line.",
            ["Download the template first — it shows an example row",
             "After upload, download the error report to fix and re-upload only the failed rows"]],
        'doc_version' => ['Automatic versioning',
            "Upload a document with the SAME title for the SAME vendor and it becomes version 2, 3, 4… Older versions stay downloadable, so you keep history without renaming files.",
            ["Use expiry dates on certificates (SOC 2, ISO, insurance) — the platform chases renewals for you"]],
        'clauses' => ['The four protective clauses',
            "Right to audit (you may inspect them), Data protection (they must guard your data), Termination (you can leave), SLA (they promise service levels). Missing clauses lower the vendor's compliance score.",
            ["Tick what the signed contract actually contains",
             "Negotiate missing clauses at the next renewal — the score motivates it"]],
        'expiry_engine' => ['Expiry reminder engine',
            "One click scans all contracts, fixes their statuses (active/expiring/expired) and creates alerts for anything ending within your thresholds (Settings, default 90/60/30 days). With SMTP configured it also emails you.",
            ["Run it weekly — it takes one second",
             "Check the Calendar page to see everything with a date in one place"]],
        'assessment_status' => ['Assessment statuses',
            "Draft (paused, vendor link inactive) → Sent (waiting for vendor) → In progress (vendor started) → Submitted (ready for your review) → In review → Clarification (bounced back to vendor) → Approved or Rejected (final).",
            ["“Submitted” items are your to-do list",
             "Rejecting auto-raises a high-severity issue so the failure is tracked"]],
        'portal_link' => ['The secure portal link',
            "Vendors never need an account. This unique link IS their key — anyone with it can answer this one questionnaire and nothing else.",
            ["Email it (automatic with SMTP) or copy-paste it into your own email",
             "“Withdraw to draft” pauses the link; “Re-send” reactivates it"]],
        'review_actions' => ['✓ ? ✕ — your three review tools',
            "Per answer: ✓ Approve means “I accept this as TRUE” — not “this is good”. An accepted “No, we don't have MFA” still scores only 25% credit and gets a ⚠ Control gap tag. ? Request clarification asks again (your comment is shown to the vendor). ✕ Reject means you don't believe or accept the answer.",
            ["Mark all the ?s first, then press “Request clarification” — only those questions reopen for the vendor",
             "Watch for ⚠ Control gap tags — approving them on important questions auto-raises remediation issues",
             "“No evidence — vendor explained why” means judge the explanation, not the missing file"]],
        'question_weight' => ['Question weight (1–10)',
            "How much a question matters in the final 0–100 score. MFA enforcement (weight 9) moves the score far more than a policy-review question (weight 5). The score grades the ANSWER too: an approved “Yes” earns full credit, an approved “No” only 25% — honesty is accepted, but a missing control still costs points.",
            ["Set 8–10 only for make-or-break controls",
             "Multiple-choice options are graded by position — always list best first, worst last",
             "Approving a gap answer on a weight-6+ question auto-raises a remediation issue"]],
        'sla' => ['SLA due date',
            "The deadline you give the vendor (or yourself) to fix a problem. Overdue items get flagged and hurt the vendor's score until resolved or formally accepted.",
            ["Press “Flag SLA breaches” to mark everything past its deadline",
             "Use “accepted” when leadership consciously accepts a risk — it stops the penalty"]],
        'lxi' => ['Likelihood × Impact',
            "Score how likely the risk is (1–5) and how bad it would be (1–5). Multiply them: 1–6 low (green), 8–12 medium (amber), 15–25 high (red). The heat map shows your whole risk landscape at once.",
            ["Review red-zone risks monthly, amber quarterly",
             "Pick a treatment: mitigate (fix), accept (live with it), transfer (insure/contract it away), avoid (stop the activity)"]],
        'fourth_party' => ['Fourth parties',
            "Your vendor's own vendors (their cloud host, payment processor…). If THEY fail, you feel it — even with no direct contract. This is the risk most programs miss.",
            ["Ask for sub-processor lists in assessments (the built-in templates already do)",
             "Record critical ones here so concentration risk is visible"]],
        'offboarding_lock' => ['Why the button is locked',
            "You can only terminate a vendor after every exit task is ticked. This prevents the classic disaster: relationship ends but their system access doesn't.",
            ["Tick tasks as you complete them — each tick is timestamped for audit",
             "Add custom tasks for anything special about this vendor"]],
        'roles' => ['Choosing the right role',
            "Admin = full control including users & settings. TPRM Analyst = runs the day-to-day program. Viewer = read-only (perfect for auditors and bosses). Vendors never log in — they only get questionnaire links.",
            ["Give most teammates Analyst; keep 1–2 Admins",
             "No SMTP? The invite link appears on screen — copy it to them any way you like"]],
        'integrations' => ['API keys — optional superpowers',
            "Without keys everything works in Demo Mode. Add a HaveIBeenPwned key for real breach data, a free NewsAPI.org key for real negative news. Footprint scans need no key at all — just internet.",
            ["Keys take effect immediately — just press a scan button afterwards",
             "Use “Send a test email” after filling SMTP to confirm it works"]],
        'sanctions' => ['Sanctions / watchlist status',
            "Flags whether this vendor appears on government sanction or watch lists. “Flagged” is serious — doing business may be illegal. Set it after checking official lists (OFAC, EU, UN).",
            ["Treat Flagged as a stop-everything signal and involve legal",
             "Recheck at least yearly and at every contract renewal"]],
        'audit_trail' => ['Audit trail',
            "Every important action — who did it, when, from which IP. This is your proof for auditors and your answer to “who changed that?”.",
            ["Search by user, action or detail",
             "Export to CSV when an auditor asks for evidence"]],
        'evidence' => ['Evidence attachments',
            "Evidence proves a CLAIM. If a vendor answers “Yes, we have it”, they must attach proof or write a short explanation of why no document exists. If they answer “No”, no evidence applies — a No is an honest disclosure of a gap, and the analyst sees it flagged instead.",
            ["Attach the actual document, not a summary (max 15 MB)",
             "No document? Explain why in the comment (at least 20 characters) — the analyst will judge it",
             "Answering “No” never blocks submission — gaps are flagged to the analyst automatically"]],
        'save_vs_submit' => ['Save vs Submit',
            "“Save progress” keeps your answers and lets you come back later with the same link. “Submit” sends everything to the analyst — after that you can't edit unless they ask for clarification.",
            ["You can save as many times as you like",
             "Submit only when every answer is final"]],
    ];
}

/** Render a clickable ⓘ hint icon with a popover. CSS-only, accessible. */
function hint(string $key): string {
    $h = va_hint_texts()[$key] ?? null;
    if (!$h) return '';
    $out = '<details class="hint"><summary aria-label="Help: ' . e($h[0]) . '">ⓘ</summary><div class="hint-pop">'
         . '<strong>' . e($h[0]) . '</strong><p>' . e($h[1]) . '</p>';
    if (!empty($h[2])) {
        $out .= '<em>What you can do:</em><ul>';
        foreach ($h[2] as $d) $out .= '<li>' . e($d) . '</li>';
        $out .= '</ul>';
    }
    return $out . '</div></details>';
}

/** Page-level registry: what is this page + what can I do here. */
function va_page_hints(): array {
    return [
        'dashboard' => ['Your command center',
            "Everything important about your vendor-risk program on one screen. Every number is clickable and takes you to the detail.",
            ["Start your day here: review “Assessments awaiting review” and “Latest alerts”",
             "Worried? Sort out the “Highest-risk vendors” list first",
             "Click 🧭 Guide (bottom-right) any time for your step-by-step journey"]],
        'search' => ['Find anything in one box',
            "Searches vendors, documents, contracts, assessments and issues at the same time.",
            ["Type part of a name, tag or title — results group by type", "Click any result to jump straight to it"]],
        'vendors' => ['Your vendor inventory',
            "Every third party you work with, with their tier, live risk score and lifecycle stage. Built to stay fast at 1,000+ vendors.",
            ["Add one vendor with the guided wizard, or hundreds via Bulk upload",
             "Filter by tier/lifecycle or sort “Riskiest first” to find trouble",
             "Click a vendor's name to open their full 360° profile"]],
        'vendor_add' => ['Guided onboarding wizard',
            "Four short steps. Answer six plain-English risk questions and the platform calculates inherent risk and suggests the right tier — no guesswork.",
            ["Only the vendor name is mandatory — you can enrich everything later",
             "Add the website: the domain powers breach and footprint scans",
             "Override the suggested tier on the last step if you know better"]],
        'vendor_import' => ['Bulk upload (CSV)',
            "Onboard hundreds or thousands of vendors at once. Bad rows are skipped with exact reasons; good rows import anyway.",
            ["1) Download the template  2) Fill one vendor per row  3) Upload",
             "Download the error report, fix only the failed rows, upload again"]],
        'vendor_view' => ['The vendor 360° profile',
            "Everything about this vendor in tabs: identity & public info, risk score breakdown, breaches, digital footprint, negative news, questionnaires, paperwork, their subcontractors, and your notes.",
            ["Run the three scan buttons (Breach / Footprint / News) — takes seconds",
             "Send a questionnaire from the Assessments tab",
             "Use the lifecycle dropdown (top-right) when the relationship changes"]],
        'vendor_edit' => ['Edit vendor master data',
            "Update identity, public information, tier and review dates. Saving recalculates the risk score.",
            ["Keep certifications and sanctions status current — both feed the profile",
             "The danger zone at the bottom permanently deletes the vendor and ALL their records"]],
        'documents' => ['Documents Library',
            "One vault for every vendor's paperwork: SOC 2 reports, ISO certificates, insurance, policies, DPAs. Re-using a title creates a new version automatically.",
            ["Always set an expiry date on certificates — the platform reminds you",
             "Use the “Expiring ≤60d / expired” filter to chase renewals",
             "Records marked “demo” are sample metadata without a real file"]],
        'contracts' => ['Contract management',
            "Every agreement with values, dates, auto-renew flags and the four protective clauses. The reminder engine warns you before anything expires.",
            ["Press “🔔 Run expiry reminders” weekly — one click",
             "Tick only clauses that are really in the signed contract — honesty keeps the score meaningful"]],
        'assessments' => ['Assessment pipeline',
            "Every questionnaire across all vendors, grouped by status. “Submitted” means a vendor finished and is waiting for YOUR review.",
            ["Work the Submitted pile first — vendors are waiting",
             "Red “Overdue” tags show due dates that passed without a decision"]],
        'assessment_new' => ['Send a questionnaire',
            "Pick a vendor and a template — the platform creates a secure link the vendor opens without any account.",
            ["With SMTP configured the email goes out automatically; otherwise copy the link",
             "Set a realistic due date — 14 days is typical"]],
        'assessment_review' => ['Review desk',
            "Read every answer, judge it with ✓ / ? / ✕, then make the overall call: Approve, Request clarification, or Reject.",
            ["Hover the counters on top — “score if approved now” updates live",
             "Clarification reopens ONLY the questions you marked ? — unlimited rounds",
             "Reject auto-raises a high-severity issue so nothing gets forgotten"]],
        'templates' => ['Questionnaire template library',
            "Five expert-built templates ship in the box (security, ISO 27001, GDPR, continuity, financial). Build your own or duplicate and tweak a built-in.",
            ["Built-ins are read-only — press Duplicate to customize",
             "A template can't be deleted while assessments use it (your history is safe)"]],
        'template_edit' => ['Template builder',
            "Add questions in sections, choose answer types (Yes/No, text, choice, 1–5 scale), set weights and evidence flags, and reorder with the arrows.",
            ["Weight 8–10 = make-or-break controls; 1–4 = nice-to-know",
             "Tick “evidence required” where words alone shouldn't count"]],
        'risk_register' => ['Risk register',
            "Big-picture risks that aren't a single finding: concentration, geography, exit barriers. Scored likelihood × impact on a live heat map.",
            ["Record program-level risks without picking a vendor",
             "Set review dates — risks rot if nobody looks at them"]],
        'issues' => ['Issues & remediation',
            "Every problem that needs fixing, with severity, owner, plan and SLA deadline. Open issues drag the vendor's score down until handled.",
            ["“⏰ Flag SLA breaches” marks everything past deadline in one click",
             "Use “accepted” only for risks leadership consciously signed off"]],
        'offboarding' => ['Offboarding / exit checklists',
            "The most forgotten phase of vendor management. Each leaving vendor gets a tick-list (access revoked, data returned…) and can only be terminated when it's 100% done.",
            ["Set a vendor's lifecycle to “Offboarding” on their profile to start",
             "Every tick is timestamped — perfect audit evidence"]],
        'alerts' => ['Alerts center',
            "One feed of everything that changed: score drops, new breaches or news, expiring paperwork, submitted questionnaires.",
            ["Check Unread daily; “Mark all read” when caught up",
             "Click through any alert to act on it directly"]],
        'reports' => ['Reports & exports',
            "Board-ready reporting and raw data exports, plus a map of how the platform supports ISO 27001, SOC 2, GDPR, NIST, DORA and FFIEC expectations.",
            ["“Generate board report” then print or save as PDF from your browser",
             "CSV exports open straight in Excel"]],
        'board_report' => ['Executive board report',
            "A clean snapshot of the whole program — built for leadership and auditors.",
            ["Press 🖨 Print / Save as PDF — the layout is print-optimized",
             "The methodology note at the bottom explains the score model to your audience"]],
        'calendar' => ['Obligations calendar',
            "Everything with a date in the next 12 months: contract and document expiries, assessment due dates, issue SLAs, periodic reviews.",
            ["Scan the current month every Monday",
             "Click any row to jump to the vendor"]],
        'users' => ['Users & access',
            "Invite teammates, choose their role, and manage them end-to-end. The matrix shows exactly what each role can do.",
            ["Most teammates → TPRM Analyst; auditors/leadership → Viewer",
             "No SMTP? Copy the invite link shown under the user and send it yourself"]],
        'settings' => ['Platform settings',
            "Organization name, email (SMTP), reminder thresholds, and API keys that switch scans from Demo to Live.",
            ["Everything here is optional — the platform works fully without it",
             "After SMTP, press “Send a test email” to confirm"]],
        'audit' => ['Audit trail',
            "Who did what, when, from where — every important action, forever.",
            ["Search any user or action; export CSV for auditors"]],
        'learn' => ['Learn TPRM',
            "The whole TPRM lifecycle explained simply, a glossary, and your path to the TPRM Warrior certification on LearnTPRM.com.",
            ["New to TPRM? Read the six lifecycle cards top to bottom — 10 minutes",
             "Ready to prove yourself? Take the free Beginner exam"]],
    ];
}

/** Automatic "About this page" strip — rendered by the layout on every screen. */
function page_hint_strip(string $page): string {
    $h = va_page_hints()[$page] ?? null;
    if (!$h) return '';
    $out = '<details class="page-hint"><summary><span class="ph-ico">💡</span> <strong>'
         . e($h[0]) . '</strong> — <span class="muted">' . e($h[1])
         . '</span> <span class="ph-more">What can I do here? ▾</span></summary><ul>';
    foreach ($h[2] as $d) $out .= '<li>' . e($d) . '</li>';
    return $out . '</ul></details>';
}

/**
 * Role-aware getting-started journey. Steps auto-tick from real data,
 * so progress reflects what actually happened — no manual checkboxes.
 */
function journey_steps(): array {
    $role = current_user()['role'] ?? 'viewer';
    $done = function (string $sql, array $p = []): bool {
        try { return (bool)scalar($sql, $p); } catch (Throwable $e) { return false; }
    };
    $steps = [];
    if ($role === 'admin') {
        $steps[] = ['Name your organization', 'Settings → Organization. It appears in vendor emails and the board report.',
            url('settings'), setting('org_name', 'My Organization') !== 'My Organization'];
        $steps[] = ['Invite your team', 'Users & Access → Invite. Analysts run the program, Viewers watch.',
            url('users'), $done('SELECT COUNT(*) > 1 FROM users')];
    }
    if ($role !== 'viewer') {
        $steps[] = ['Onboard your first vendor', 'The 4-step wizard suggests the right tier from 6 easy questions.',
            url('vendor_add'), $done('SELECT COUNT(*) FROM vendors WHERE created_by IS NOT NULL')];
        $steps[] = ['Run an intelligence scan', 'Open any vendor → Breach, Footprint or News tab → “Run scan”. Works offline in Demo Mode.',
            url('vendors'), $done("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'scan_%' AND user_id IS NOT NULL")];
        $steps[] = ['Send your first questionnaire', 'Assessments → Send questionnaire. The vendor answers via a secure link — no account needed.',
            url('assessment_new'), $done('SELECT COUNT(*) FROM assessments WHERE created_by IS NOT NULL')];
        $steps[] = ['Review and decide', 'When a vendor submits, judge each answer with ✓ ? ✕ and make the overall call.',
            url('assessments', ['status' => 'submitted']), $done('SELECT COUNT(*) FROM assessments WHERE reviewed_by IS NOT NULL')];
        $steps[] = ['Set up expiry reminders', 'Contracts → “Run expiry reminders”. Never miss a renewal again.',
            url('contracts'), $done("SELECT COUNT(*) FROM audit_log WHERE action = 'contract_reminders'")];
    }
    $steps[] = ['Read your dashboard', 'Every number is clickable. Red means act, green means relax.',
        url('dashboard'), true];
    $steps[] = ['Generate a board report', 'Reports → board report → print or save as PDF for leadership.',
        url('board_report'), $done("SELECT COUNT(*) FROM audit_log WHERE action = 'export'")];
    $steps[] = ['Level up at LearnTPRM.com', 'Learn the lifecycle in-app, then take the free TPRM Warrior certification.',
        url('learn'), false];
    return $steps;
}

/** Floating 🧭 Guide button + slide-up journey panel. Rendered in the layout footer. */
function journey_drawer(): string {
    if (!is_logged_in()) return '';
    $steps = journey_steps();
    $doneCount = count(array_filter($steps, fn($s) => $s[3]));
    $total = count($steps);
    $pct = $total ? (int)round(100 * $doneCount / $total) : 0;
    $out = '<details class="journey" id="journey"><summary title="Your guided journey">🧭 Guide'
         . '<span class="j-progress">' . $doneCount . '/' . $total . '</span></summary>'
         . '<div class="j-panel"><h3>Your journey, step by step</h3>'
         . '<p class="muted small">Ticks happen automatically as you use the platform. Hover any 💡 or ⓘ icon anywhere for instant help — you never need to leave the product.</p>'
         . '<div class="meter" style="margin:.4rem 0 1rem"><span style="width:' . $pct . '%"></span></div>';
    foreach ($steps as $i => $s) {
        $out .= '<a class="j-step ' . ($s[3] ? 'done' : '') . '" href="' . e($s[2]) . '">'
              . '<span class="j-tick">' . ($s[3] ? '✓' : ($i + 1)) . '</span>'
              . '<span><strong>' . e($s[0]) . '</strong><br><span class="muted small">' . e($s[1]) . '</span></span></a>';
    }
    $out .= '<p class="small muted" style="margin-top:.8rem">Stuck anyway? The <a href="' . e(url('learn'))
          . '">Learn TPRM</a> section explains every concept in easy English.</p></div></details>';
    return $out;
}
