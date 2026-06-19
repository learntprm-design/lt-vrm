<?php
/** Reports hub — board report + CSV exports + compliance framework mapping. */
require_perm('view');
$GLOBALS['VA_PAGE_TITLE'] = 'Reports';
include __DIR__ . '/../partials/header.php';
?>
<div class="topbar"><h1>Reports &amp; Exports</h1></div>

<div class="grid c2">
  <div class="card learn-card">
    <h2>📄 Executive board report</h2>
    <p class="muted small">A polished one-click report of your entire TPRM program: portfolio risk, distributions,
      top risks, assessment pipeline, expiring obligations. Print it or save as PDF straight from the browser
      (it has a dedicated print layout).</p>
    <a class="btn btn-gold" href="<?= e(url('board_report')) ?>">Generate board report →</a>
  </div>
  <div class="card learn-card">
    <h2>🧾 CSV exports</h2>
    <p class="muted small">Excel-ready CSV (UTF-8 with BOM). Respect your role's export permission.</p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <a class="btn btn-outline btn-sm" href="<?= e(url('export', ['what' => 'vendors'])) ?>">Vendors</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('export', ['what' => 'contracts'])) ?>">Contracts</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('export', ['what' => 'documents'])) ?>">Documents</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('export', ['what' => 'assessments'])) ?>">Assessments</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('export', ['what' => 'issues'])) ?>">Issues</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('export', ['what' => 'risks'])) ?>">Risk register</a>
      <?php if (can('*')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('export', ['what' => 'audit'])) ?>">Audit log</a><?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h2>Compliance framework mapping</h2>
  <p class="muted small">How VendorAssess 360 features support common third-party-risk requirements.</p>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Framework / regulation</th><th>Requirement theme</th><th>Where it lives in the platform</th></tr></thead>
    <tbody>
      <tr><td><strong>ISO 27001 (A.15 / 5.19-5.23)</strong></td><td>Supplier security policy, monitoring &amp; review</td>
        <td>Assessments (ISO template), Documents Library, periodic reviews on Calendar</td></tr>
      <tr><td><strong>SOC 2 (CC9.2)</strong></td><td>Vendor risk identification &amp; assessment</td>
        <td>Risk score, onboarding inherent-risk tiering, Risk Register</td></tr>
      <tr><td><strong>GDPR (Art. 28)</strong></td><td>Processor due diligence &amp; DPAs</td>
        <td>GDPR questionnaire template, DPA category in Documents, contract DP clause tracking</td></tr>
      <tr><td><strong>NIST CSF / 800-161</strong></td><td>Supply-chain risk management</td>
        <td>Fourth-party mapping, continuous monitoring alerts, breach exposure scans</td></tr>
      <tr><td><strong>DORA (EU)</strong></td><td>ICT third-party resilience &amp; exit strategies</td>
        <td>Business-continuity template, Offboarding exit checklists, concentration risks in Register</td></tr>
      <tr><td><strong>OCC / FFIEC guidance</strong></td><td>Lifecycle management of third parties</td>
        <td>Full lifecycle states (onboarding → active → review → offboarding → terminated) with audit trail</td></tr>
    </tbody></table></div>
</div>
<?php include __DIR__ . '/../partials/footer.php';
