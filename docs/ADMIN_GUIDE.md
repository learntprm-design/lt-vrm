# Admin Guide — VendorAssess 360
*For the person who owns the platform. Plain language, no surprises.*

## Settings → Organization
- **Organization name** — appears in emails sent to vendors and on the board report.
- **Reminder thresholds** — comma-separated days (default `90,60,30`) used by the contract
  expiry engine ("Run expiry reminders" on the Contracts page).

## Settings → Email (SMTP)
Email is **optional**. Without it, everything still works: reminders become in-app alerts and
portal/invite links are shown on screen for you to copy.

To enable email, enter your SMTP details (examples):

| Provider | Host | Port | Security |
|---|---|---|---|
| Gmail* | smtp.gmail.com | 587 | STARTTLS |
| Outlook 365 | smtp.office365.com | 587 | STARTTLS |
| Mailgun/SendGrid/etc. | per provider | 587/465 | STARTTLS/SSL |

\* Gmail requires an "App password" (Google Account → Security → 2-Step Verification → App passwords).

Use **"Send a test email"** after saving. The mailer is a built-in zero-dependency SMTP client
(STARTTLS/SSL/none + AUTH LOGIN).

## Settings → Integrations (Demo Mode vs Live)
| Connector | Key needed | What changes when you add it |
|---|---|---|
| HaveIBeenPwned | `hibp_api_key` (paid, haveibeenpwned.com/API/Key) | Breach scans query real breach data for the vendor's domain |
| NewsAPI.org | `newsapi_key` (free tier available) | Reputation scans pull live adverse media |
| Digital footprint | none | Goes live automatically when the server has internet (passive DNS + crt.sh certificate-transparency lookups) |

No key / no internet → scans run in clearly-labeled **Demo Mode** with deterministic sample data,
so demos and training always work. Demo findings are flagged `demo` in the database and UI.

## Users
- Invite via **Users & Access** (name, email, role). Status `invited` until they accept and set a password.
- Roles: **admin** (everything) · **analyst** (program work, no user/settings access) · **viewer** (read-only + exports).
- Lockout: 5 failed logins = 10-minute lock. "Reset password" issues a temporary password (emailed, or shown once).
- You cannot deactivate/delete/demote yourself — prevents locking everyone out.

## Backups (the only maintenance there is)
Back up two things:
1. **The database** — phpMyAdmin → `vendorassess360` → Export (SQL). Or:
   `mysqldump -u root vendorassess360 > backup.sql`
2. **The `uploads/` folder** — all evidence, documents and contract files.

Restore = import the SQL + copy `uploads/` back. `config.php` holds the DB credentials; keep a copy.

## Security notes
- `config.php` is blocked from web access by `.htaccess`; uploads are stored under randomized
  names in `uploads/` with a deny-all `.htaccess` — downloads go through an authenticated,
  path-checked endpoint only.
- All write actions are recorded in **Audit Trail** with user, IP and timestamp.
- To reset the entire platform: delete `config.php` and drop the database; the installer reopens.
- Set `'debug' => true` in `config.php` only while diagnosing a problem; it prints detailed errors.

## Performance at 1,000+ vendors
All list pages are server-side paginated and the schema is indexed on every hot column
(name, tier, lifecycle, score, dates, FKs). Bulk import commits in chunks of 200 rows.
On a default XAMPP laptop, 1,000 vendors stay snappy. If MySQL ever feels slow, give it more
memory in `my.ini` (`innodb_buffer_pool_size = 256M`).
