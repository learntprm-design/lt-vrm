# Troubleshooting Tutorial — Fixing Installer "FAIL" Checks
*A step-by-step guide in plain language. The most common issue (folder permissions) takes one minute to fix.*

---

## ❌ "App folder writable (for config.php)" FAIL and/or "uploads/ folder writable" FAIL

### Why this happens (simple explanation)
When you copy the `vendorassess360` folder into `htdocs`, **you** own those files.
But the web server (Apache) runs as a **different user** on macOS and Linux —
so it's not allowed to create `config.php` or save uploaded files. We just need to
give it permission on two folders. That's it.

### 🍎 Fix on macOS (XAMPP)
1. Open **Terminal** (press `Cmd + Space`, type "Terminal", press Enter).
2. Paste this single line and press Enter:
   ```
   chmod 777 /Applications/XAMPP/xamppfiles/htdocs/vendorassess360 /Applications/XAMPP/xamppfiles/htdocs/vendorassess360/uploads
   ```
3. Go back to the browser and click **"I fixed it — re-check ⟳"**. Both checks turn **PASS**.

> Using XAMPP-VM (the virtual-machine version)? Click **Open Terminal** inside the XAMPP app
> and run: `chmod 777 /opt/lampp/htdocs/vendorassess360 /opt/lampp/htdocs/vendorassess360/uploads`

### 🐧 Fix on Linux (XAMPP/LAMPP)
1. Open a terminal.
2. Run:
   ```
   sudo chmod 777 /opt/lampp/htdocs/vendorassess360 /opt/lampp/htdocs/vendorassess360/uploads
   ```
3. Re-check in the browser.

### 🪟 Fix on Windows (XAMPP)
Windows XAMPP normally passes both checks out of the box. If you still see FAIL:
1. Open `C:\xampp\htdocs\` in File Explorer.
2. Right-click the `vendorassess360` folder → **Properties** → **Security** tab → **Edit**.
3. Select **Users**, tick **Full control**, click **OK** twice.
4. Re-check in the browser.

### Is `chmod 777` safe?
On a **local XAMPP machine** (your laptop, `localhost`) — yes, this is the standard approach,
and it only affects two folders. On a **public internet server**, use the tighter version instead:
```
sudo chown daemon:daemon /path/to/vendorassess360 /path/to/vendorassess360/uploads
sudo chmod 755 /path/to/vendorassess360 /path/to/vendorassess360/uploads
```
(`daemon` is XAMPP's Apache user; on Ubuntu Apache it's `www-data`.)

---

## ❌ Other installer FAILs

| Check | Fix |
|---|---|
| **PHP version ≥ 8.1** | Install a current XAMPP build (it bundles PHP 8.1+). |
| **PDO MySQL / mbstring** | XAMPP Control Panel → Apache **Config** → `php.ini` → remove the `;` in front of `extension=pdo_mysql` and `extension=mbstring` → save → restart Apache. |
| **openssl** | Same as above for `extension=openssl` (enabled by default in XAMPP). |

## ❌ "Database error … Is MySQL running?"
Open the XAMPP Control Panel and press **Start** next to **MySQL**. On macOS XAMPP-VM, also make
sure the volume is mounted (XAMPP app → Volumes → Mount).

## ❌ Page loads without styling
You're offline and the Google Fonts CDN can't load — purely cosmetic, the app falls back to
system fonts and works fully.

## 🔁 Starting completely over
1. Delete `config.php` inside the `vendorassess360` folder.
2. In phpMyAdmin (http://localhost/phpmyadmin), drop the `vendorassess360` database.
3. Reload http://localhost/vendorassess360/ — the installer reopens.

---
*VendorAssess 360 — developed by [LearnTPRM.com](https://learntprm.com)*
