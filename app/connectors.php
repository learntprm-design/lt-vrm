<?php
/**
 * LT-VRM — Intelligence Connectors
 *
 * Pattern: every scan tries LIVE sources first (when an API key / internet is
 * available) and falls back to clearly-labeled DEMO MODE data, so the platform
 * always works — even on an offline XAMPP laptop. Results are stored in the DB
 * with a `source` flag so the UI can show where data came from.
 */

/** Is outbound internet likely available? Cached per request. */
function net_available(): bool {
    static $ok = null;
    if ($ok === null) {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $ok = (bool)@get_headers('https://www.google.com', false, $ctx);
    }
    return $ok;
}

function http_get_json(string $url, array $headers = []): ?array {
    $opts = ['http' => ['method' => 'GET', 'timeout' => 8,
             'header' => implode("\r\n", array_merge(['User-Agent: LT-VRM'], $headers)),
             'ignore_errors' => true]];
    $body = @file_get_contents($url, false, stream_context_create($opts));
    if ($body === false) return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

/* ------------------------------------------------- Breach / dark web scan */

function scan_breaches(int $vendorId): array {
    $v = row('SELECT * FROM vendors WHERE id = ?', [$vendorId]);
    if (!$v) return ['mode' => 'error', 'count' => 0];
    q('DELETE FROM breach_findings WHERE vendor_id = ? AND source <> "manual"', [$vendorId]);

    $apiKey = setting('hibp_api_key');
    if ($apiKey && $v['domain'] && net_available()) {
        $data = http_get_json('https://haveibeenpwned.com/api/v3/breaches?domain=' . urlencode($v['domain']),
                              ['hibp-api-key: ' . $apiKey]);
        if (is_array($data)) {
            foreach (array_slice($data, 0, 25) as $b) {
                $sev = ($b['PwnCount'] ?? 0) > 10000000 ? 'critical' : (($b['PwnCount'] ?? 0) > 1000000 ? 'high' : 'medium');
                q('INSERT INTO breach_findings (vendor_id, source, breach_name, breach_date, records_exposed,
                   data_classes, severity, description) VALUES (?,?,?,?,?,?,?,?)',
                  [$vendorId, 'hibp', $b['Title'] ?? 'Unknown', $b['BreachDate'] ?? null,
                   $b['PwnCount'] ?? null, implode(', ', $b['DataClasses'] ?? []),
                   $sev, strip_tags($b['Description'] ?? '')]);
            }
            audit('scan_breach', 'vendor', $vendorId, 'Live HIBP scan');
            return ['mode' => 'live', 'count' => count($data)];
        }
    }

    // ----- Demo mode: deterministic per vendor so results are stable.
    $seed = crc32($v['name']);
    $catalog = [
        ['Credential Stuffing Exposure', 'Email addresses, Passwords', 'high', 2400000],
        ['Marketing Database Leak', 'Names, Email addresses, Phone numbers', 'medium', 310000],
        ['Legacy Forum Breach', 'Usernames, Passwords (hashed)', 'low', 85000],
        ['Cloud Storage Misconfiguration', 'Customer records, Internal documents', 'critical', 9800000],
        ['Third-Party Processor Incident', 'Payment card data (partial)', 'high', 1200000],
    ];
    $n = $seed % 4; // 0-3 findings per vendor
    for ($i = 0; $i < $n; $i++) {
        $c = $catalog[($seed + $i * 7) % count($catalog)];
        $date = date('Y-m-d', strtotime('-' . (($seed % 48) + $i * 11) . ' months'));
        q('INSERT INTO breach_findings (vendor_id, source, breach_name, breach_date, records_exposed,
           data_classes, severity, description, dark_web_mentions) VALUES (?,?,?,?,?,?,?,?,?)',
          [$vendorId, 'demo', $v['name'] . ' — ' . $c[0], $date, $c[3], $c[1], $c[2],
           'Demo-mode finding generated for evaluation. Configure a HaveIBeenPwned API key in Settings → Integrations for live data.',
           ($seed + $i) % 14]);
    }
    audit('scan_breach', 'vendor', $vendorId, 'Demo-mode scan');
    return ['mode' => 'demo', 'count' => $n];
}

/* ------------------------------------------------ Digital footprint scan */

function scan_footprint(int $vendorId): array {
    $v = row('SELECT * FROM vendors WHERE id = ?', [$vendorId]);
    if (!$v) return ['mode' => 'error', 'count' => 0];
    q('DELETE FROM footprint_findings WHERE vendor_id = ?', [$vendorId]);
    $domain = $v['domain'];
    $live = $domain && net_available() && function_exists('dns_get_record');
    $add = function (string $cat, string $item, string $status, string $detail) use ($vendorId) {
        q('INSERT INTO footprint_findings (vendor_id, category, item, status, detail) VALUES (?,?,?,?,?)',
          [$vendorId, $cat, $item, $status, $detail]);
    };

    if ($live) {
        // --- Live, strictly passive checks: DNS lookups + public CT logs only.
        $mx  = @dns_get_record($domain, DNS_MX) ?: [];
        $add('dns', 'MX records', $mx ? 'pass' : 'warn',
             $mx ? count($mx) . ' mail server(s) found' : 'No MX records — domain may not receive mail');
        $txt = @dns_get_record($domain, DNS_TXT) ?: [];
        $spf = false; $dmarcTxt = @dns_get_record('_dmarc.' . $domain, DNS_TXT) ?: [];
        foreach ($txt as $t) if (stripos($t['txt'] ?? '', 'v=spf1') === 0) $spf = true;
        $add('email_security', 'SPF record', $spf ? 'pass' : 'fail',
             $spf ? 'SPF policy published' : 'No SPF record — spoofing risk');
        $dmarc = false;
        foreach ($dmarcTxt as $t) if (stripos($t['txt'] ?? '', 'v=DMARC1') === 0) $dmarc = true;
        $add('email_security', 'DMARC policy', $dmarc ? 'pass' : 'fail',
             $dmarc ? 'DMARC policy published' : 'No DMARC record — phishing protection missing');
        $a = @dns_get_record($domain, DNS_A) ?: [];
        $add('dns', 'A records', $a ? 'pass' : 'warn', $a ? 'Resolves to ' . ($a[0]['ip'] ?? '?') : 'Domain does not resolve');
        $caa = @dns_get_record($domain, DNS_CAA) ?: [];
        $add('dns', 'CAA records', $caa ? 'pass' : 'warn',
             $caa ? 'Certificate authority restrictions in place' : 'No CAA record (optional hardening)');
        // Subdomains via certificate-transparency (public data, non-intrusive)
        $ct = http_get_json('https://crt.sh/?q=%25.' . urlencode($domain) . '&output=json');
        if (is_array($ct)) {
            $subs = [];
            foreach ($ct as $c) {
                foreach (explode("\n", $c['name_value'] ?? '') as $s) {
                    $s = strtolower(trim($s));
                    if ($s && $s !== $domain && substr($s, 0, 2) !== '*.') $subs[$s] = true;
                }
            }
            $cnt = count($subs);
            $add('subdomains', 'Exposed subdomains (CT logs)', $cnt > 40 ? 'warn' : 'info',
                 $cnt . ' unique subdomains in certificate-transparency logs' . ($cnt ? ': ' . implode(', ', array_slice(array_keys($subs), 0, 8)) . '…' : ''));
        }
        $add('whois', 'Domain', 'info', $domain . ' — registered domain (passive lookup)');
        audit('scan_footprint', 'vendor', $vendorId, 'Live passive scan of ' . $domain);
        $cnt = (int)scalar('SELECT COUNT(*) FROM footprint_findings WHERE vendor_id = ?', [$vendorId]);
        return ['mode' => 'live', 'count' => $cnt];
    }

    // ----- Demo mode (offline or no domain): deterministic plausible results.
    $seed = crc32($v['name']);
    $checks = [
        ['email_security', 'SPF record',        ['pass','pass','fail'],  'Sender Policy Framework controls who may send mail as this domain'],
        ['email_security', 'DKIM signing',      ['pass','warn','pass'],  'Cryptographic email signatures'],
        ['email_security', 'DMARC policy',      ['pass','fail','warn'],  'Tells receivers what to do with spoofed mail'],
        ['ssl',            'TLS certificate',   ['pass','pass','warn'],  'Certificate validity and strength'],
        ['ssl',            'Protocol versions', ['pass','warn','pass'],  'Old TLS versions should be disabled'],
        ['headers',        'Security headers',  ['warn','pass','fail'],  'HSTS, CSP, X-Frame-Options on the public site'],
        ['dns',            'CAA records',       ['warn','pass','warn'],  'Restricts which CAs can issue certificates'],
        ['subdomains',     'Exposed subdomains',['info','warn','info'],  'Public attack surface from certificate-transparency logs'],
        ['tech',           'Technology stack',  ['info','info','info'],  'CMS / frameworks visible from public pages'],
        ['social',         'Web & social presence', ['pass','pass','pass'], 'Active public presence and contactability'],
    ];
    foreach ($checks as $i => $c) {
        $status = $c[2][($seed + $i) % 3];
        $add($c[0], $c[1], $status, '[Demo] ' . $c[3]);
    }
    audit('scan_footprint', 'vendor', $vendorId, 'Demo-mode scan');
    return ['mode' => 'demo', 'count' => count($checks)];
}

/* -------------------------------------------------- Adverse media scan */

function scan_news(int $vendorId): array {
    $v = row('SELECT * FROM vendors WHERE id = ?', [$vendorId]);
    if (!$v) return ['mode' => 'error', 'count' => 0];

    $apiKey = setting('newsapi_key');
    if ($apiKey && net_available()) {
        $qstr = urlencode('"' . $v['name'] . '" AND (breach OR lawsuit OR fine OR fraud OR sanction OR bankruptcy)');
        $data = http_get_json('https://newsapi.org/v2/everything?q=' . $qstr . '&sortBy=publishedAt&pageSize=10&apiKey=' . urlencode($apiKey));
        if (($data['status'] ?? '') === 'ok') {
            q('DELETE FROM news_items WHERE vendor_id = ? AND relevant IS NULL', [$vendorId]);
            foreach (array_slice($data['articles'] ?? [], 0, 10) as $a) {
                q('INSERT INTO news_items (vendor_id, headline, source, url, published_date, category, severity, summary)
                   VALUES (?,?,?,?,?,?,?,?)',
                  [$vendorId, mb_substr($a['title'] ?? 'Untitled', 0, 255), $a['source']['name'] ?? null,
                   $a['url'] ?? null, substr($a['publishedAt'] ?? '', 0, 10) ?: null,
                   'adverse', 'medium', mb_substr($a['description'] ?? '', 0, 500)]);
            }
            audit('scan_news', 'vendor', $vendorId, 'Live news scan');
            return ['mode' => 'live', 'count' => count($data['articles'] ?? [])];
        }
    }

    // ----- Demo mode: up to 10 adverse items over the last 10 years.
    if ((int)scalar('SELECT COUNT(*) FROM news_items WHERE vendor_id = ?', [$vendorId]) > 0) {
        return ['mode' => 'demo', 'count' => 0]; // keep existing seeded news
    }
    $seed = crc32($v['name']);
    $catalog = [
        ['Data breach affects customer accounts at %s', 'breach', 'high'],
        ['%s faces class-action lawsuit over data handling practices', 'lawsuit', 'high'],
        ['Regulator fines %s for compliance failures', 'fine', 'medium'],
        ['%s reports weaker-than-expected quarterly results', 'financial', 'low'],
        ['Former executive of %s under investigation', 'leadership', 'medium'],
        ['%s named in vendor-related security incident', 'breach', 'medium'],
        ['Audit flags internal-control weaknesses at %s', 'financial', 'medium'],
        ['%s settles privacy complaint with consumer watchdog', 'fine', 'low'],
        ['Service outage at %s disrupts downstream customers', 'financial', 'medium'],
        ['Researchers disclose vulnerability in %s product line', 'breach', 'low'],
    ];
    $n = $seed % 11; // 0-10 items
    for ($i = 0; $i < $n; $i++) {
        $c = $catalog[($seed + $i * 3) % count($catalog)];
        $date = date('Y-m-d', strtotime('-' . (($seed + $i * 97) % 3650) . ' days'));
        q('INSERT INTO news_items (vendor_id, headline, source, url, published_date, category, severity, summary)
           VALUES (?,?,?,?,?,?,?,?)',
          [$vendorId, sprintf($c[0], $v['name']), ['Reuters','Bloomberg','TechCrunch','The Register','FT'][($seed + $i) % 5],
           null, $date, $c[1], $c[2],
           'Demo-mode adverse media item. Configure a NewsAPI key in Settings → Integrations for live monitoring.']);
    }
    audit('scan_news', 'vendor', $vendorId, 'Demo-mode scan');
    return ['mode' => 'demo', 'count' => $n];
}
