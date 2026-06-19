# UAT Test Report — Admin ↔ Vendor Assessment Workflow
*Executed against a live XAMPP installation via real HTTP requests (two separate browser sessions,
real CSRF tokens, real multipart evidence uploads) — exactly as human users would click through.*

**Date:** June 10, 2026 · **Environment:** macOS XAMPP, PHP 8.2.4, MariaDB · **Result: ALL PASS ✅**

---

## Test Case 1 — Happy path: send → fill → approve (score goes UP)

**Vendor:** PayStream Global (Critical tier) · **Template:** Security Essentials (12 questions, evidence-required items included)

| # | Step (actor) | Expected | Actual | Result |
|---|---|---|---|---|
| 1 | Analyst logs in via login form | Session established, lockout counters untouched | HTTP 302 → dashboard shows analyst name | ✅ |
| 2 | Analyst sends questionnaire | Assessment created in **sent**, secure portal token issued | Token issued, status `sent` | ✅ |
| 3 | Vendor opens tokenized portal (separate session, no account) | Questionnaire renders with all 12 questions | Rendered | ✅ |
| 4 | Vendor answers all 12 + attaches evidence + submits | Status **submitted**, 12 answers stored, thank-you screen | `submitted`, 12/12 saved, "Thank you — questionnaire submitted" | ✅ |
| 5 | Analyst approves each answer (12×) | Per-question status `approved` | 12/12 approved | ✅ |
| 6 | Analyst overall **Approve** | Status **approved**, weighted score locked, vendor risk score recomputed | `approved`, score **100/100**, round 1 | ✅ |
| 7 | Risk score impact | Score rises (assessment factor 50 → 100 at 25% weight) | **615 → 781** (High Risk → Good, +166) | ✅ |

Factor breakdown after approval: assessment 100, breach 86, footprint 56, news 100, compliance 95, inherent 21.

## Test Case 2 — Clarification round + rejection (score goes DOWN)

**Vendor:** DataVault Analytics (Critical tier) · Same template, deliberately weak vendor answers (several "No", worst choices)

| # | Step (actor) | Expected | Actual | Result |
|---|---|---|---|---|
| 1 | Analyst sends questionnaire | `sent` | ✅ | ✅ |
| 2 | Vendor submits weak answers | `submitted` | ✅ | ✅ |
| 3 | Analyst approves 10, flags 2 **Request clarification** with comments, overall decision **Clarify** | Status **clarification**, round increments to 2 | `clarification`, round 2 | ✅ |
| 4 | Vendor reopens same link | Round-2 banner, analyst comments visible, **only the 2 flagged questions editable** (rest locked) | Banner shown, comments shown, non-flagged controls disabled | ✅ |
| 5 | Vendor answers the 2 flagged questions + evidence, submits clarifications | Status back to **submitted**, event logged as round 2 | ✅ | ✅ |
| 6 | Analyst approves one, rejects one, overall **Reject** | Status **rejected**, partial score recorded, **high-severity issue auto-raised** | `rejected`, score 93/100, issue "Failed assessment…" [high/open] | ✅ |
| 7 | Risk score impact | Score falls (no approved assessment + new open high issue) | **581 → 557** (−24) | ✅ |
| 8 | Vendor's portal after decision | Neutral closure screen (no internal details leaked) | "The review … has been completed" | ✅ |

## Event trails captured (audit evidence)
TC1: created/sent → vendor submitted → approved 100/100.
TC2: created/sent → vendor submitted → clarification requested (2 questions, round 2) → vendor clarified → rejected 93/100.

## How to reproduce manually (2-minute demo script)
1. Sign in → open any vendor → **Assessments** tab → **+ Send questionnaire** → pick *Security Essentials* → create. Copy the portal link.
2. Open the link in a **private/incognito window** (you're now the vendor — no login). Answer, attach any small file where evidence is required, **Submit**.
3. Back as analyst: Assessments → open it → use **✓ / ? / ✕** per answer → overall **Approve** (or **Request clarification** to bounce it back, or **Reject** to auto-raise an issue).
4. Watch the vendor's **Risk Score** tab — the assessment factor and composite move immediately, with the change recorded in score history.

*Test analyst account created for this run: `qa@test.local` (role: analyst). Remove it in Users & Access if not wanted.*
