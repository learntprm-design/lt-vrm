<div align="center">

<img src="assets/img/banner.png" alt="VendorAssess 360 — The World's Most Comprehensive Open-Source TPRM Platform" width="100%">

# VendorAssess 360

### The world's most comprehensive **open-source** Third-Party Risk Management platform

Run a complete TPRM / Vendor Risk Management program on a stock **XAMPP** server.
**No Composer. No Node. No build step.** Drop it in `htdocs`, run the installer, done.

[![License: MIT](https://img.shields.io/badge/License-MIT-E8B54D.svg?style=flat-square)](LICENSE)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL / MariaDB](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-ready-00758F.svg?style=flat-square&logo=mysql&logoColor=white)](https://mariadb.org/)
[![Runs on XAMPP](https://img.shields.io/badge/Runs%20on-XAMPP-FB7A24.svg?style=flat-square)](https://www.apachefriends.org/)
[![No build step](https://img.shields.io/badge/Build%20step-none-2ea44f.svg?style=flat-square)](#-5-minute-install)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-2ea44f.svg?style=flat-square)](CONTRIBUTING.md)
[![Free for the community](https://img.shields.io/badge/Free%20for%20the-community-E8B54D.svg?style=flat-square)](https://learntprm.com)

**[⚡ Quick Install](#-5-minute-install) · [✨ Features](#-features) · [📚 Docs](#-documentation) · [🤝 Contribute](CONTRIBUTING.md) · [🥷 Learn TPRM](https://learntprm.com)**

*A free gift to the third-party-risk community by [LearnTPRM.com](https://learntprm.com).*

</div>

---

## 📑 Table of contents

- [Why VendorAssess 360](#-why-vendorassess-360)
- [Features](#-features)
- [5-minute install](#-5-minute-install)
- [Live data vs Demo Mode](#-live-data-vs-demo-mode)
- [The 0–1000 risk score](#️-the-01000-risk-score)
- [Security baked in](#-security-baked-in)
- [Documentation](#-documentation)
- [Contributing & community](#-contributing--community)
- [License](#-license)

---

## 🎯 Why VendorAssess 360

Most TPRM tools are either eye-wateringly expensive SaaS or thin checklists that fall apart past a
handful of vendors. VendorAssess 360 is different:

- **🏢 Enterprise-grade, zero cost.** A full vendor-risk lifecycle — onboarding, assessments,
  intelligence, scoring, remediation, offboarding — built to manage **1,000+ vendors** smoothly.
- **🪶 Absurdly easy to run.** If you can install XAMPP, you can run this. No dependencies, no DevOps,
  no cloud bill. It works **fully offline** out of the box.
- **🔍 Real intelligence, not just forms.** Breach & dark-web signals, passive digital-footprint
  scanning, and adverse-media monitoring — each with a built-in Demo Mode so every feature works
  before you add a single API key.
- **🔒 Secure by default.** Prepared statements, CSRF protection, hashed passwords, RBAC, and a full
  audit log ship on day one.
- **🎁 Truly open.** MIT-licensed, no telemetry, no upsell. Yours to use, study, and extend.

---

## ✨ Features

| Feature | What you get | Where |
|---|---|---|
| **Intelligent vendor onboarding** | Guided wizard with inherent-risk scoring & auto tier suggestion, plus bulk CSV upload (validated row-by-row, partial import, error report) | Vendors → Add / Bulk upload |
| **Breach & dark-web signals** | Pluggable HaveIBeenPwned connector + always-working Demo Mode | Vendor profile → Breach & Dark Web |
| **Documents library** | Global vault with categories (SOC 2, ISO 27001, DPA…), versioning, tags, and expiry alerts | Documents Library |
| **Contract management** | Terms, value, auto-renew, notice periods, 4 key clauses, and an expiry reminder engine (in-app + email) | Contracts |
| **Digital footprint (non-intrusive)** | Passive DNS, SPF/DKIM/DMARC, TLS, security headers, CT-log subdomains | Vendor profile → Digital Footprint |
| **Reputation & adverse media** | Last-10-years adverse media (up to 10 items) with analyst relevant / not-relevant dispositioning | Vendor profile → Reputation News |
| **Assessments** | 5 built-in templates + custom builder, secure tokenized vendor portal (no vendor accounts), per-question Approve / Reject / Request-Clarification, unlimited rounds, full event trail | Assessments |
| **360° vendor profile** | Legal identity, registration, leadership, certifications, sanctions status — all intelligence in one tabbed view | Vendor profile → Overview |
| **Analyst dashboard + user management** | Invite users, role matrix (Admin / Analyst / Viewer / Vendor-portal), activate/deactivate, password resets, audit trail | Dashboard, Users & Access |
| **Transparent 0–1000 risk score** | 6-factor weighted model with bands, history chart, and a "what's dragging this down" breakdown | Vendor profile → Risk Score |
| **Competitive extras** | Risk register with 5×5 heat map, issues & SLA remediation, fourth-party mapping, offboarding checklists, alerts center, obligations calendar, board report, CSV exports, global search, framework mapping (ISO / SOC 2 / GDPR / NIST / DORA / FFIEC) | Sidebar |
| **Premium UI** | Dark navy + gold theme, Poppins/Inter, fluid animated interface that feels like paid software | Everywhere |
| **Learn TPRM** | Full-lifecycle education, glossary, and a path to the free **TPRM Warrior** certification | Learn TPRM |

---

## 🚀 5-minute install

> **Requirements:** [XAMPP](https://www.apachefriends.org/) (Apache + PHP 8.1+ + MySQL/MariaDB). Nothing else.

1. **Install XAMPP** and start **Apache** and **MySQL** from the control panel.
2. **Copy** the `vendorassess360` folder into your web root:
   - Windows → `C:\xampp\htdocs\`
   - macOS → `/Applications/XAMPP/htdocs/`
3. **Open** **http://localhost/vendorassess360/** — the installer starts automatically.
4. **Follow the 3 steps** (environment check → database & admin account → done). Keep
   **“Load the demo dataset”** checked to get **52 realistic vendors** with documents, contracts,
   assessments, and scores instantly.
5. **Sign in.** You're now running a full TPRM program. 🎉

📖 Full walkthrough: **[docs/INSTALL.md](docs/INSTALL.md)** · Stuck on a permission check?
**[docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)** fixes it in a minute.

---

## 🔌 Live data vs Demo Mode

Every intelligence feature works immediately — even fully offline.

| | Without keys / offline | With free or paid keys |
|---|---|---|
| **Breach & dark web** | Demo Mode (deterministic sample data) | Live via HaveIBeenPwned |
| **Adverse media** | Demo Mode | Live via NewsAPI.org |
| **Digital footprint** | Goes **Live** with nothing but an internet connection (passive DNS + public certificate-transparency logs — never intrusive) | Same |

Add keys anytime in **Settings → Integrations** and scans switch to **Live** automatically.

---

## ⚖️ The 0–1000 risk score

A transparent, explainable model — **lower = more dangerous, higher = more secure.**

| Factor | Weight |
|---|---|
| Assessment results | 25% |
| Breach & dark-web exposure | 20% |
| Digital footprint hygiene | 15% |
| Compliance health (docs, clauses, issues) | 15% |
| Inherent criticality | 15% |
| Adverse media | 10% |

`Composite = Σ(factor 0–100 × weight) × 10`

**Bands:** 0–399 Critical · 400–599 High · 600–749 Moderate · 750–899 Good · 900–1000 Excellent.

---

## 🔒 Security baked in

Prepared statements everywhere · CSRF tokens on every form · `password_hash()` · session hardening &
regeneration · login rate-limiting with lockout · file-upload whitelist + randomized names +
deny-all `.htaccess` on uploads · output escaping · role-based access control · full audit log.

Found a vulnerability? Please report it responsibly — see **[SECURITY.md](SECURITY.md)**.

---

## 📚 Documentation

| Guide | For |
|---|---|
| [Installation Guide](docs/INSTALL.md) | Step-by-step setup with troubleshooting |
| [Troubleshooting](docs/TROUBLESHOOTING.md) | Fix installer FAIL checks (permissions, etc.) fast |
| [User Guide](docs/USER_GUIDE.md) | Every feature explained in plain language |
| [Admin Guide](docs/ADMIN_GUIDE.md) | SMTP, API keys, backups, production security |
| [Feature Checklist](docs/FEATURE_CHECKLIST.md) | Requirement → implementation → how it was tested |

---

## 🤝 Contributing & community

VendorAssess 360 is built **for** the community, **by** the community. You don't need to be a
developer to help — reporting a confusing screen or fixing a typo counts.

- 🐛 [Report a bug](../../issues/new/choose) · 💡 [Request a feature](../../issues/new/choose)
- 🧑‍💻 Read the [Contributing Guide](CONTRIBUTING.md) and open a pull request
- 🤝 We follow a [Code of Conduct](CODE_OF_CONDUCT.md) — be kind

If this project saves you time, please ⭐ **star the repo** — it helps other risk teams find it.

---

## 🔎 Looking for a free / open-source TPRM tool?

You're in the right place. **VendorAssess 360** is a **free, open-source, self-hosted alternative**
to expensive commercial vendor-risk platforms. If you searched for any of the following, this is it:

**Free TPRM software / platform / tool** · **open-source TPRM platform** ·
**free third-party risk management software** · **open-source third-party risk management** ·
**free vendor risk management platform / software / system** · **open-source vendor risk management platform** ·
**free vendor risk platform** · **free vendor risk assessment tool** ·
**free risk rating platform** · **open-source security ratings / risk scoring** ·
**free security questionnaire tool** · **free contract management software** ·
**open-source GRC tool** · **self-hosted vendor risk management** · **vendor onboarding & offboarding software** ·
**free breach & dark-web monitoring for vendors** · **PHP / XAMPP TPRM application**.

It's also a **free, open-source alternative to** OneTrust, BitSight, SecurityScorecard, UpGuard,
Whistic, Venminder, Prevalent, ProcessUnity, and Archer — no license fees, no per-vendor pricing,
no cloud lock-in. Self-host it on stock XAMPP (PHP + MySQL) in five minutes.

---

## 🥷 Become a TPRM Warrior

This platform is part of the **[LearnTPRM.com](https://learntprm.com)** ecosystem — home of the
world's hardest **free** TPRM certification (Beginner: 50 questions / 10 min · Professional:
100 questions / 25 min).

**[Accept the challenge →](https://learntprm.com)** · **[TPRM Jobs →](https://learntprm.com/jobs)**

---

## 📄 License

Open source under the **MIT License** — free to use, modify, and distribute. See [LICENSE](LICENSE).

<div align="center">

---

**Built with ❤️ for the third-party-risk community by [LearnTPRM.com](https://learntprm.com)**

*If you find it useful, give it a ⭐ and share it with a fellow risk analyst.*

</div>
