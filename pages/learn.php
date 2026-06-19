<?php
/** Learn TPRM — lifecycle education, certification CTA, jobs. All roads lead to LearnTPRM.com. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Learn TPRM';
include __DIR__ . '/../partials/header.php';

$lifecycle = [
  ['01 · Planning & Scoping',
   'Define what "third party" means for your organization, set risk appetite, and decide which vendors deserve deep diligence. Tier vendors by criticality so effort follows risk — exactly what the onboarding wizard here does for you.',
   'https://learntprm.com'],
  ['02 · Due Diligence & Selection',
   'Before signing, assess security, privacy, financial health and resilience. Send questionnaires (SIG-style, ISO-aligned), verify certifications like SOC 2 and ISO 27001, and check breach history and adverse media — all built into this platform.',
   'https://learntprm.com'],
  ['03 · Contracting',
   'Bake protection into the deal: right-to-audit, data-protection terms, breach-notification SLAs, termination rights. Track every clause in the Contracts module and never miss a renewal with expiry reminders.',
   'https://learntprm.com'],
  ['04 · Ongoing Monitoring',
   'Risk changes after signature. Continuously watch breach exposure, digital footprint, negative news and expiring evidence. The 0–1000 risk score keeps your whole portfolio comparable at a glance.',
   'https://learntprm.com'],
  ['05 · Issue Management & Remediation',
   'When assessments fail or findings emerge, track them to closure with owners and SLA dates. Reject-and-clarify loops with vendors are first-class citizens in the Assessments module.',
   'https://learntprm.com'],
  ['06 · Offboarding & Exit',
   'The most forgotten phase: revoke access, retrieve data, settle obligations, archive evidence. Use the built-in exit checklist so nothing slips when a relationship ends.',
   'https://learntprm.com'],
];
?>
<div class="topbar"><h1>Learn TPRM 🎓</h1></div>

<div class="card" style="background:linear-gradient(120deg, rgba(238,192,92,.12), transparent 55%), var(--card);text-align:center;padding:2.2rem">
  <div class="pill" style="margin-bottom:.8rem">Free Certification — <?= date('Y') ?></div>
  <h1 style="font-size:1.9rem">Become a <span class="gold">TPRM Warrior</span></h1>
  <p class="muted" style="max-width:560px;margin:.5rem auto">The world's hardest free TPRM certification.
    Beginner: 50 questions in 10 minutes. Professional: 100 questions in 25 minutes.
    No second chances on each question. Are you tough enough?</p>
  <div style="display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;margin-top:1.1rem">
    <a class="btn btn-gold" href="https://learntprm.com" target="_blank" rel="noopener">⚔ Get TPRM Warrior Certification ↗</a>
    <a class="btn btn-outline" href="https://learntprm.com" target="_blank" rel="noopener">Beginner exam ↗</a>
    <a class="btn btn-outline" href="https://learntprm.com" target="_blank" rel="noopener">Professional exam ↗</a>
  </div>
  <p class="small muted" style="margin-top:.8rem">No credit card required · 100% free · Instant certificate · Public verification URL</p>
</div>

<h2 style="margin:1.4rem 0 .8rem">The TPRM lifecycle — six phases every program needs</h2>
<div class="grid c2">
  <?php foreach ($lifecycle as $L): ?>
    <div class="card learn-card">
      <div class="learn-num"><?= e($L[0]) ?></div>
      <p class="muted" style="margin:.5rem 0 .8rem"><?= e($L[1]) ?></p>
      <a class="btn btn-sm btn-outline" href="<?= e($L[2]) ?>" target="_blank" rel="noopener">Deep-dive on LearnTPRM.com ↗</a>
    </div>
  <?php endforeach; ?>
</div>

<h2 style="margin:1.4rem 0 .8rem">Practical resources</h2>
<div class="grid c3">
  <div class="card learn-card">
    <h3>📚 TPRM Knowledge Hub</h3>
    <p class="muted small">Concepts, frameworks (ISO 27001, NIST, DORA, GDPR), real-world scenarios and interview prep.</p>
    <a class="btn btn-sm btn-outline" href="https://learntprm.com" target="_blank" rel="noopener">Open Knowledge Hub ↗</a>
  </div>
  <div class="card learn-card">
    <h3>💼 TPRM Jobs</h3>
    <p class="muted small">Live third-party-risk, GRC and vendor-management roles. Your next career move starts here.</p>
    <a class="btn btn-sm btn-gold" href="https://learntprm.com/jobs" target="_blank" rel="noopener">Browse TPRM Jobs ↗</a>
  </div>
  <div class="card learn-card">
    <h3>🛡 Verify a certificate</h3>
    <p class="muted small">Every TPRM Warrior certificate has a unique ID and public verification URL — check any candidate's claim.</p>
    <a class="btn btn-sm btn-outline" href="https://learntprm.com" target="_blank" rel="noopener">Verify Certificate ↗</a>
  </div>
</div>

<h2 style="margin:1.4rem 0 .8rem">Mini glossary <span class="muted small">(the terms you'll meet in this platform)</span></h2>
<div class="card">
  <div class="grid c2" style="gap:.2rem 2rem">
    <?php
    $glossary = [
      ['Third party', 'Any external organization you rely on — vendors, suppliers, service providers, partners.'],
      ['Fourth party', "Your vendor's vendor. You inherit their risk even without a direct contract."],
      ['Inherent risk', 'Risk that exists before any controls — driven by data access, criticality and network access.'],
      ['Residual risk', 'Risk that remains after controls and contracts are applied. The 0–1000 score approximates it.'],
      ['Due diligence', 'Structured investigation of a vendor before and during the relationship.'],
      ['SIG', 'Standardized Information Gathering questionnaire — an industry-standard set of vendor questions.'],
      ['DPA', 'Data Processing Agreement — the GDPR-mandated contract for personal-data processors.'],
      ['Right to audit', 'A contract clause letting you inspect a vendor\'s controls — priceless after an incident.'],
      ['Concentration risk', 'Too much reliance on a single vendor, region or technology.'],
      ['Exit strategy', 'Your documented plan to leave a vendor without business disruption.'],
    ];
    foreach ($glossary as $g): ?>
      <p class="small" style="margin:.35rem 0"><strong class="gold"><?= e($g[0]) ?></strong><br>
        <span class="muted"><?= e($g[1]) ?></span></p>
    <?php endforeach; ?>
  </div>
</div>

<div class="card" style="text-align:center">
  <p class="muted small">LT-VRM is developed and maintained by
    <a href="https://learntprm.com" target="_blank" rel="noopener"><strong>LearnTPRM.com</strong></a> —
    World's Hardest Free TPRM Certification. This platform and the certification share one mission:
    making world-class third-party risk management accessible to everyone.</p>
</div>
<?php include __DIR__ . '/../partials/footer.php';
