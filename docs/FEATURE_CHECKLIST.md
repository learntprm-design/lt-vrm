# Feature Checklist — Requirement → Implementation → Verification

Every requirement from the build specification, where it lives, and how it was checked.

## §1 Technical constraints
| Requirement | Implementation | Verified by |
|---|---|---|
| Runs on stock XAMPP, PHP 8.1+, MySQL | Plain PHP, PDO/mysql, no exotic extensions; query-string routing (no mod_rewrite needed) | Installer environment check enforces PHP ≥8.1, pdo_mysql, mbstring, openssl |
| Zero build tools | No Composer/Node anywhere; only optional Google Fonts CDN with system-font fallback | Inspect repo — no package files |
| Guided installer, self-verifying, self-locking | `install/index.php` 3-step wizard; locks when `config.php` exists | Manual walkthrough of all 3 steps incl. failure paths (MySQL down, bad password, short admin password) |
| 1,000+ vendor scale | `paginate()` server-side everywhere; 9 indexes on vendors + indexed FKs/dates; bulk import committed in 200-row chunks; `set_time_limit(300)` | Schema review; bulk template tested with valid/invalid/duplicate rows |
| Security baseline | Prepared statements only (`q()` helper); `csrf_field()/csrf_check()` on every form incl. portal; `password_hash`; session cookie httponly/samesite + ID regeneration on login; 5-strikes login lockout; upload whitelist + 15 MB cap + randomized names + deny-all `.htaccess`; `e()` escaping on all dynamic output; audit log on all writes | Code review of every page; `uploads/.htaccess` = `Require all denied`; downloads via path-checked endpoint |
| Graceful degradation | Demo Mode on all connectors; friendly fatal-error page; SMTP optional with on-screen fallback links; demo docs flagged metadata-only | Exception handler in `app/bootstrap.php`; `net_available()` checks |

## §2 Core modules
| Requirement | Implementation |
|---|---|
| 2.1 Onboarding wizard with tier auto-suggest | `pages/vendor_add.php` — 4 steps, 6-question inherent-risk model (0–100) → suggested tier with override |
| 2.1 Bulk CSV with validation/dupes/error report/partial import | `pages/vendor_import.php` — template download, BOM-tolerant parser, row-level errors, error-report CSV, chunked commits |
| 2.2 Breach & dark-web + connector + Demo Mode + timestamps | `app/connectors.php scan_breaches()` (HIBP key in Settings), `vendor_view` Breach tab with DEMO/LIVE tags + last-scanned stamp; feeds risk factor (20%) |
| 2.3 Documents library | `pages/documents.php` — categories, tags, auto-versioning by title, expiry filter, global search, vendor tab view |
| 2.4 Contracts + expiry engine | `pages/contracts.php` — all clause/term fields; "Run expiry reminders" refreshes statuses, creates alerts + optional SMTP email at configurable thresholds |
| 2.5 Digital footprint (passive only) | `scan_footprint()` — live: DNS MX/A/CAA, SPF, DMARC, crt.sh subdomains; never touches vendor infra; demo fallback |
| 2.6 Reputation news, 10 items / 10 years, dispositioning | `scan_news()` + News tab; analyst Relevant/Not-relevant buttons; only relevant items hit the score |
| 2.7 Assessments full workflow | 5 built-in templates seeded; builder in `template_edit.php`; tokenized `portal.php` (no vendor account); per-question Approve/Reject/Clarify with comments; unlimited rounds (`round` counter); all 8 statuses; events trail; due dates |
| 2.8 Public information profile | `vendor_view` Overview tab — identity, registration, DUNS, leadership, certifications, sanctions status + aggregated intel counters |
| 2.9 Analyst dashboard + user management | `dashboard.php` (distributions, pipeline, expiries, alerts, news, drill-down); `users.php` (invite, 4-role matrix incl. Vendor portal-only, activate/deactivate, reset, delete, invite links) |
| 2.10 Risk score 0–1000 | `app/risk.php` — documented 6-factor weighted model, exact bands/colors, history chart, "what's dragging this down" |

## §3 LearnTPRM ecosystem
| Requirement | Implementation |
|---|---|
| Theme matched to learntprm.com | CSS variables in `assets/css/app.css`: #0a0e17 navy, #eec05c gold, gold-gradient CTAs, Poppins+Inter — taken from site screenshots |
| Developed-by branding | Sidebar footer, login page, installer, board report, README |
| Learn TPRM module | `pages/learn.php` — 6-phase lifecycle, resources, glossary, deep links to learntprm.com |
| TPRM Warrior certification CTA | Hero card in Learn + sidebar link + dashboard banner → learntprm.com Beginner/Professional |
| TPRM Jobs | Sidebar "TPRM Jobs ↗" + Learn card → learntprm.com/jobs (new tab) |

## §4 Design quality
Design-token system (one `:root` block re-themes everything) · animated counters · skeleton/shimmer
classes · meter-bar transitions · hover lifts · toasts · modals with blur · empty states ·
`prefers-reduced-motion` respected · print stylesheet for the board report · responsive ≤860px.

## §5 Competitive enhancements
Risk register + 5×5 heat map (`risk_register.php`) · issues & SLA remediation (`issues.php`) ·
fourth-party mapping (vendor tab) · alerts center (`alerts.php`) · offboarding checklists
(`offboarding.php`, gated completion) · framework mapping ISO/SOC 2/GDPR/NIST/DORA/FFIEC
(`reports.php`) · board report print/PDF (`board_report.php`) · CSV exports ×7 (`export.php`) ·
global search (`search.php`) · audit trail (`audit.php`) · obligations calendar (`calendar.php`).

## Domain-intelligent validation (TPRM-expert review pass)
| Expert rule | Implementation |
|---|---|
| Evidence proves claims, not disclosures — "No" answers never demand evidence | `portal.php` submit validation: yesno+No skips the evidence requirement entirely |
| Evidence-or-explanation — a missing document can be justified in writing (≥20 chars), judged by the analyst | Portal validation + review desk shows "No file — vendor explained why" in blue vs "no file and no explanation" in orange |
| Graded scoring — an accepted "No" earns 25% credit, scale maps 1–5→0–100%, choices grade by position (best→worst), text = analyst judgment | `answer_credit()` in `assessment_review.php`; builder documents the best-first convention |
| ⚠ Control-gap flags — risk-relevant answers highlighted for the reviewer | `is_control_gap()` (credit ≤ 0.34) badge on the review desk |
| Approval ≠ ignoring gaps — accepted gaps on weight-6+ questions auto-raise remediation issues (high for 8+, medium for 6–7, with SLA dates) | Approve handler in `assessment_review.php` |
| Tier-override sanity — tiering a vendor 2+ levels below the suggested tier triggers a documented heads-up + audit note | `vendor_add.php` step 4 |
| Sanctions flag = stop signal — flagged vendors show a ⛔ banner on their profile with legal guidance | `vendor_view.php` |

## Round-2 deep audit (10× depth) — flaws found & fixed
| # | Flaw found | Expert fix |
|---|---|---|
| 1 | Approved assessments never aged — a 3-year-old approval still gave full credit | Assessment factor now decays: full credit ≤12 months, linear decay to neutral-50 by month 24 (`app/risk.php`) |
| 2 | Approving due diligence left the vendor stuck in "Onboarding" | Approval auto-promotes onboarding → active, audited and announced |
| 3 | Parallel duplicate questionnaires could be sent to the same vendor | Creation blocked while an in-flight assessment of the same template exists |
| 4 | Deleting a template question CASCADE-deleted vendors' submitted answers | Question set freezes once a template is in use; UI directs to Duplicate (template versioning) |
| 5 | Terminated vendors polluted portfolio averages, bands and "riskiest" lists | Dashboard + board report compute on the active portfolio only |
| 6 | Vendor termination left contracts "active" | Completing offboarding auto-closes open contracts + alert |
| 7 | Reminders engine covered contracts only | One engine: contracts + expiring documents (≤30d) + assessment deadlines (≤7d/overdue) + overdue periodic reviews |
| 8 | Bulk-imported vendors had no next review date — invisible to the review cycle | Import sets next review +1 year (same as the wizard) |
| 9 | PII/PHI/PCI vendor saved with no data-protection clause — silently | Non-blocking TPRM heads-up on contract save |
| 10 | Clarification rounds bypassed answer/evidence validation | Validation now applies to reopened questions in every round |
| 11 | Scores went stale with time (expiring docs, aging approvals) until manual action | Vendor profile auto-recomputes scores >24h old; contract statuses refresh on page view |

## In-product guidance (self-driving journey)
| Element | Implementation |
|---|---|
| "About this page" strip on every screen | `page_hint_strip()` in `app/hints.php`, auto-rendered by the layout for all 25+ pages — title, plain-English purpose, expandable "What can I do here?" actions |
| ⓘ hint icons at decision points | `hint('key')` registry — risk score model, tiers, lifecycle, Demo/Live, inherent risk, bulk-import rules, doc versioning, contract clauses, expiry engine, assessment statuses, portal links, ✓?✕ review tools, question weights, SLAs, L×I, fourth parties, offboarding lock, roles, integrations, sanctions, audit trail, evidence, save-vs-submit. CSS-only popovers (work without JS) |
| 🧭 Journey Guide (floating, every page) | Role-aware getting-started checklist whose steps **auto-tick from real data** (org named, team invited, vendor added, scan run, assessment sent, review decided, reminders run, report exported). Auto-opens once for new users |
| Vendor-side guidance | "How this works" 3-step walkthrough + hints inside the tokenized portal — vendors need zero training |

Verified live: all pages render strip + journey + hints (HTTP check across 17 routes + portal).

## §6 Zero-maintenance quality gates
- **Syntax:** all 42 PHP files pass a full PHP-parser syntax check (0 errors).
- **Validation everywhere:** every form handles missing/invalid input with friendly flash messages;
  duplicate vendor names blocked in wizard, editor and bulk import; date-order checks on contracts;
  choice-template validation; portal submit blocks on missing answers/required evidence.
- **Failure paths:** DB down → friendly error page; no SMTP → on-screen links; no API key/offline →
  Demo Mode; missing demo file download → flash, not error; SLA/lock edge cases (can't delete
  built-in templates in use, can't deactivate yourself, can't terminate vendor with open checklist).
- **Demo dataset:** 52 vendors across industries/tiers/lifecycles with contacts, documents
  (incl. expired/expiring), contracts (incl. expiring), breaches, footprint checks, news,
  issues, register entries, fourth parties, 7-point score history, assessments in all 7 active states.
- **Docs:** README, INSTALL, USER_GUIDE, ADMIN_GUIDE, this checklist — all in plain language; help
  text and tooltips throughout the UI.
