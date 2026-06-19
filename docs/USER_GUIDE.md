# User Guide — VendorAssess 360
*Every feature, in super simple language. No jargon without an explanation.*

> **You may not even need this guide.** The platform explains itself as you use it:
>
> - **💡 About this page** — the gold strip at the top of every screen tells you what the page is
>   for; click "What can I do here?" for practical actions.
> - **ⓘ hint icons** — next to anything technical (risk score, tiers, clauses, weights, SLAs…).
>   Click one for a plain-English explanation and what to do about it.
> - **🧭 Guide button** (bottom-right, every page) — your personal step-by-step journey. Steps tick
>   themselves automatically as you actually do them, and each step links straight to the right page.
>   Admins, analysts and viewers each see their own journey; vendors get a "How this works" walkthrough
>   inside their questionnaire portal.

---

## 1. Dashboard
**What it is:** Your TPRM program at a glance.
**Why it matters:** You instantly see how many vendors you manage, the average risk score, who is in
the danger zone, what's waiting for your review, and what expires soon.
**How to use it:** Click any number or list item — everything drills down to the detail page.
The gold banner at the bottom links to Learn TPRM and the TPRM Warrior certification.

## 2. Adding vendors

### Single vendor (the smart way)
Vendors → **+ Add vendor**. A 4-step wizard asks:
1. **Company profile** — name (the only mandatory field), website, industry, location.
2. **Primary contact** — who receives questionnaires.
3. **Services & inherent risk** — six plain-English questions (what data do they touch? are they
   business-critical? do they reach your network? regulated? subcontractors? hard to replace?).
4. **Tiering** — the platform converts your answers into an **inherent-risk score (0–100)** and
   suggests a tier (Critical / High / Medium / Low). You can override it.

*Inherent risk* = how risky the relationship is **before** looking at the vendor's controls.

### Bulk upload (up to 1,000+ at once)
Vendors → **Bulk upload** → download the CSV template → fill one vendor per row → upload.
Every row is validated (missing names, bad emails, bad tiers, duplicates). Good rows import,
bad rows are skipped, and you get a downloadable error report saying exactly which row failed and why.

## 3. The vendor profile (360° view)
Open any vendor. Tabs across the top:

- **Overview** — the *public information* page: legal identity, registration number, founded year,
  HQ, size, leadership, claimed certifications, sanctions/watchlist status, contacts, plus live counters.
- **Risk Score** — the 0–1000 gauge, the six factor bars, what's dragging the score down, and history.
- **Breach & Dark Web** — known breaches, exposed records, data classes, dark-web mentions. Press
  **Run scan** any time. The "last scanned" stamp and a DEMO/LIVE tag tell you where data came from.
- **Digital Footprint** — non-intrusive checks of their public posture: SPF/DKIM/DMARC (email
  spoofing protection), TLS certificate, security headers, exposed subdomains. PASS/WARN/FAIL per check.
- **Reputation News** — up to 10 most relevant negative news items from the last 10 years (breaches,
  lawsuits, fines, financial trouble, leadership scandals). Mark each item **Relevant** or
  **Not relevant** — only relevant items hurt the risk score.
- **Assessments** — all questionnaires for this vendor; send a new one in two clicks.
- **Documents / Contracts** — what's on file for this vendor, with expiry warnings.
- **Fourth Parties** — your vendor's vendors. You inherit their risk; record them here.
- **Notes & Activity** — analyst notes and a full audit timeline of who did what, when.

Use the **lifecycle dropdown** (top right) to move a vendor through
Onboarding → Active → Under review → Offboarding → Terminated.

## 4. The 0–1000 risk score
**Lower = dangerous. Higher = secure.**

| Band | Range | Meaning |
|---|---|---|
| 🔴 Critical Risk | 0–399 | Act now — escalate, restrict, or exit |
| 🟠 High Risk | 400–599 | Needs remediation plan and close monitoring |
| 🟡 Moderate | 600–749 | Acceptable with monitoring |
| 🟢 Good | 750–899 | Healthy relationship |
| 💚 Excellent | 900–1000 | Model vendor |

The score combines six factors (weights in brackets): approved assessment results (25%), breach
exposure (20%), digital footprint hygiene (15%), compliance health — valid documents, contract
clauses, open issues (15%), inherent criticality (15%), adverse media (10%). Each factor is 0–100;
the composite is the weighted sum × 10. Every recalculation is stored, so you get a trend chart.

## 5. Assessments (questionnaires)
The complete workflow:

1. **Send:** Assessments → *Send questionnaire* (or from a vendor profile). Pick one of the 5
   built-in templates (Security Essentials, ISO 27001, GDPR & Privacy, Business Continuity,
   Financial Health) or your own. The platform creates a **secure portal link** — the vendor needs
   no account; the link itself is the key. With SMTP configured it's emailed automatically.
2. **Vendor answers** in the portal: yes/no, multiple-choice, scale and free-text questions,
   save-and-resume any time. **Evidence works like real TPRM, not like a dumb form:** evidence proves
   a *claim* — so it's only demanded for affirmative answers. Answering **"No"** never blocks
   submission (a No is an honest disclosure, and the analyst sees it flagged). If a vendor claims
   "Yes" but has no document, they can write a short explanation instead — the analyst judges it.
3. **You review:** every answer gets **✓ Approve**, **? Request clarification** (with a comment the
   vendor sees), or **✕ Reject**. Risk-relevant answers ("No", lowest scale values, worst choice
   options) carry a **⚠ Control gap** tag so nothing weak slips past you. Remember: Approve means
   *"I accept this as true"* — not *"this is good"*.
4. **Decide:**
   - **Approve** → the score locks in and feeds the risk score. Approved control gaps on important
     questions (weight 6+) **auto-raise remediation issues** — honesty is accepted, gaps still get tracked.
   - **Request clarification** → only flagged questions reopen for the vendor. Unlimited rounds; each round is numbered and logged.
   - **Reject** → a high-severity issue is raised automatically so the failure cannot be forgotten.

**How the 0–100 assessment score works (expert-graded):** each question contributes
`weight × accepted × answer credit`. Answer credit: Yes = 100%, **No = 25%** (disclosed gap),
scale 1–5 maps to 0–100%, multiple-choice is graded by position (options are listed best → worst),
free text = analyst's accept/reject. So a vendor who honestly admits missing controls scores lower
than one who has them — exactly as it should be.

Build your own questionnaires under **Questionnaire Templates** (sections, 4 answer types, weights
1–10, evidence-required flags, reordering). Built-ins are read-only — duplicate them to customize.

## 6. Documents Library
Upload anything (PDF, Word, Excel, images — max 15 MB): SOC 2 reports, ISO certificates, insurance,
policies, DPAs, NDAs. Set categories, tags and **expiry dates**. Re-uploading with the same title
creates **version 2, 3, …** automatically. Filter "Expiring ≤60d / expired" to chase renewals.
Demo-seeded records are metadata-only (no physical file) and marked "demo".

## 7. Contracts
Track every agreement: type (MSA, SOW, DPA, NDA, SLA…), value, start/end dates, auto-renew flag,
notice period, and the four clauses that save you in a crisis — **right to audit, data protection,
termination rights, SLA commitments** (missing clauses lower the vendor's compliance factor).
Press **🔔 Run expiry reminders** to refresh statuses and generate alerts (and emails, if SMTP is
configured) for everything expiring inside your thresholds (Settings, default 90/60/30 days).

## 8. Issues & Remediation
Findings from failed assessments, scans or manual entry live here with severity, owner, remediation
plan and an **SLA due date**. Use **⏰ Flag SLA breaches** to mark overdue items. Open issues reduce
the vendor's risk score until resolved or formally accepted.

## 9. Risk Register
For risks bigger than a single finding: concentration risk, geographic exposure, exit barriers.
Score **likelihood × impact** (1–5 each), shown on a live 5×5 heat map. Choose a treatment —
**mitigate, accept, transfer, avoid** — document the plan, set a review date.

## 10. Offboarding
Set a vendor's lifecycle to **Offboarding** and an 8-step exit checklist appears automatically
(revoke access, retrieve data, settle invoices, archive evidence…). Add custom tasks. The
**Complete offboarding** button stays locked until every box is ticked — so nothing slips.

## 11. Alerts, Calendar, Search, Reports
- **Alerts** — one feed for score drops, new breaches/news, expiries, submissions. Filter by severity/unread.
- **Calendar** — every dated obligation for the next 12 months grouped by month: contract & document
  expiries, assessment due dates, issue SLAs, periodic reviews.
- **Search** — one box across vendors, documents, contracts, assessments and issues.
- **Reports** — one-click **executive board report** (print or save as PDF from the browser) and
  Excel-ready CSV exports of everything, plus a mapping of platform features to ISO 27001, SOC 2,
  GDPR, NIST, DORA and FFIEC expectations.

## 12. Users & access (Admins)
Invite teammates by email and pick a role:
- **Admin** — everything, including users, settings, audit trail.
- **TPRM Analyst** — runs the program: vendors, scans, assessments, documents, contracts, issues.
- **Viewer** — read-only + exports (perfect for auditors and leadership).
- **Vendors** never get accounts — only scoped, tokenized portal links.

If SMTP isn't configured the invite link is shown on screen to share manually. You can deactivate,
reactivate, reset passwords, change roles, or remove users — every action is audit-logged.

## 13. Learn TPRM & TPRM Warrior 🥷
The **Learn TPRM** section teaches the full lifecycle (planning → due diligence → contracting →
monitoring → remediation → offboarding) with practical explanations and a glossary, and links deeper
into **LearnTPRM.com**. Ready to prove yourself? **Get TPRM Warrior Certification** — Beginner and
Professional exams, free forever. Hunting for a role? **TPRM Jobs** (sidebar) takes you to
learntprm.com/jobs.
