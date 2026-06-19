# Contributing to LT-VRM

First off — **thank you.** LT-VRM is a free gift to the third‑party‑risk community, and
every issue, idea, and pull request makes it better for the next risk analyst who installs it.

You do **not** need to be a professional developer to help. Reporting a confusing screen, fixing a
typo, or suggesting a feature are all hugely valuable.

---

## Ways to contribute

- 🐛 **Report a bug** — open an [issue](../../issues/new/choose) with steps to reproduce.
- 💡 **Suggest a feature** — tell us the TPRM problem you're trying to solve, not just the solution.
- 📖 **Improve the docs** — clearer install steps, better screenshots, translations.
- 🧑‍💻 **Send code** — bug fixes and new features via pull request (see below).

## Quick start for developers

This project is intentionally **dependency‑free**: stock XAMPP (Apache + PHP 8.1+ + MySQL/MariaDB).
No Composer, no Node, no build step.

1. **Fork** this repository and clone your fork.
2. Drop the folder into your XAMPP `htdocs/` directory.
3. Start Apache + MySQL, open `http://localhost/lt-vrm/`, run the guided installer
   (keep **"Load the demo dataset"** checked for instant test data).
4. Create a branch: `git checkout -b fix/clear-description`.
5. Make your change, test it locally against the demo dataset.
6. Commit with a clear message, push, and open a Pull Request.

## Coding guidelines

- **Match the existing style.** Plain PHP, small focused files, no frameworks.
- **Security is non‑negotiable.** Use prepared statements (never string‑concatenated SQL), escape all
  output with the existing `h()` helper, and keep CSRF tokens on every form.
- **Keep it XAMPP‑friendly.** Don't add anything that requires Composer, Node, or a build pipeline.
- **One change per PR.** Small, reviewable pull requests get merged faster.
- **Document user‑facing changes** in the relevant file under `docs/`.

## Pull request checklist

- [ ] Tested locally on a fresh install with the demo dataset.
- [ ] No SQL built by string concatenation; output is escaped.
- [ ] No secrets, API keys, or personal data committed.
- [ ] Docs updated if behavior changed.

## Reporting security issues

Please **do not** open a public issue for security vulnerabilities. See [SECURITY.md](SECURITY.md)
for responsible‑disclosure instructions.

## Code of Conduct

By participating you agree to uphold our [Code of Conduct](CODE_OF_CONDUCT.md). Be kind. We're all
here to make TPRM easier.

---

Built with ❤️ for the community by [LearnTPRM.com](https://learntprm.com).
