# Security Policy

VendorAssess 360 is a security tool, so we take the security of the platform itself seriously.

## Reporting a vulnerability

If you discover a security vulnerability, please report it **privately** so we can fix it before it
is publicly disclosed.

- **Preferred:** open a [GitHub Security Advisory](../../security/advisories/new) (private).
- **Or:** email **security@learntprm.com** with the details.

Please include:

- A description of the issue and its potential impact.
- Step‑by‑step instructions to reproduce it.
- Affected files or endpoints, and any proof‑of‑concept.

We aim to acknowledge reports within **72 hours** and to ship a fix or mitigation as quickly as is
reasonably possible. We're happy to credit you in the release notes unless you'd prefer to remain
anonymous.

## Please do not

- Publicly disclose the issue before a fix is available.
- Run automated scanners against systems you don't own.
- Access, modify, or delete data that isn't yours.

## Hardening already built in

VendorAssess 360 ships secure by default: prepared statements everywhere, CSRF tokens on every form,
`password_hash()` for credentials, session hardening and regeneration, login rate‑limiting with
lockout, an upload whitelist with randomized filenames and a deny‑all `.htaccess` on `uploads/`,
output escaping, role‑based access control, and a full audit log.

For production deployment guidance (HTTPS, SMTP, API keys, backups), see
[docs/ADMIN_GUIDE.md](docs/ADMIN_GUIDE.md).
