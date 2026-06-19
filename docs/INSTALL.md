# Installation Guide — VendorAssess 360

Written for people who have **never installed a web application before**. Total time: about 5 minutes.

## What you need

- A computer running Windows, macOS or Linux
- **XAMPP** (free) — it bundles Apache (the web server), PHP 8.1+ and MySQL/MariaDB (the database)

## Step 1 — Install XAMPP

1. Download XAMPP from https://www.apachefriends.org/ (pick the PHP 8.1+ build).
2. Install it with the default options.
3. Open the **XAMPP Control Panel** and press **Start** next to **Apache** and **MySQL**.
   Both should turn green. If Apache won't start, another program (often Skype or IIS) is using
   port 80 — close it or change Apache's port.

## Step 2 — Copy the platform

Copy the whole `vendorassess360` folder into XAMPP's web folder:

- **Windows:** `C:\xampp\htdocs\vendorassess360`
- **macOS:** `/Applications/XAMPP/htdocs/vendorassess360`
- **Linux:** `/opt/lampp/htdocs/vendorassess360`

## Step 3 — Run the installer

1. Open your browser at **http://localhost/vendorassess360/**
2. You're redirected to the installer automatically. It has 3 steps:
   - **Environment check** — verifies PHP version, required extensions, and folder permissions.
     Everything must say PASS (the page tells you exactly how to fix anything that fails).
   - **Database & admin** — XAMPP defaults are pre-filled (host `127.0.0.1`, user `root`, empty
     password). The database is created for you. Then set your own admin name, email and password.
     Leave **"Load the demo dataset"** checked the first time — you get 52 realistic vendors so every
     screen looks alive immediately.
   - **Finish** — the installer locks itself and sends you to the sign-in page.
3. Sign in with the admin email and password you just chose.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Database error … Is MySQL running?" | Start MySQL in the XAMPP Control Panel. |
| Environment check fails on `pdo_mysql` or `mbstring` | Edit `php.ini` (XAMPP Control Panel → Config) and remove the `;` before `extension=pdo_mysql` / `extension=mbstring`, then restart Apache. |
| "App folder writable" / "uploads/ folder writable" FAIL | macOS: `chmod 777 /Applications/XAMPP/xamppfiles/htdocs/vendorassess360 /Applications/XAMPP/xamppfiles/htdocs/vendorassess360/uploads` · Linux: same with `sudo` on `/opt/lampp/htdocs/...` · Windows: folder Properties → Security → Users → Full control. Full tutorial: [TROUBLESHOOTING.md](TROUBLESHOOTING.md). |
| "Could not write config.php" | Same permission fix as above, then retry the installer. |
| Page is unstyled | The Google Fonts CDN is unreachable (offline) — the app falls back to system fonts and still works fully. |
| Want to start over | Delete `config.php` in the app folder and drop the `vendorassess360` database in phpMyAdmin, then reload the site. |

## Moving to a real server

The same folder works on any shared host or VPS with Apache + PHP 8.1 + MySQL. Upload it, browse to
the URL, and the installer runs there too. After install, set `'debug' => false` in `config.php`
(default) and use HTTPS if available.
