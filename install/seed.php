<?php
/**
 * VendorAssess 360 — Seeder
 * va_seed_core(): default settings + built-in questionnaire templates (always runs)
 * va_seed_demo(): 52 realistic vendors with full module data (optional)
 * Uses raw PDO because it runs inside the installer, before the app boots.
 */

function va_seed_core(PDO $pdo): void {
    $set = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?,?)');
    foreach ([
        ['org_name', 'My Organization'],
        ['reminder_days', '90,60,30'],
        ['smtp_host', ''], ['smtp_port', '587'], ['smtp_user', ''], ['smtp_pass', ''],
        ['smtp_from', 'tprm@localhost'], ['smtp_security', 'tls'],
        ['hibp_api_key', ''], ['newsapi_key', ''],
    ] as $s) $set->execute($s);

    // ---- Built-in questionnaire templates
    $tpl = $pdo->prepare('INSERT INTO assessment_templates (name, description, framework, is_builtin) VALUES (?,?,?,1)');
    $qst = $pdo->prepare('INSERT INTO template_questions (template_id, section, question, qtype, choices, weight, evidence_required, sort_order) VALUES (?,?,?,?,?,?,?,?)');

    $templates = [
        ['Security Essentials (SIG-Lite style)', 'Core vendor security due-diligence — a fast, broad screen of essential controls.', 'SIG-Lite', [
            ['Governance', 'Do you have a documented information security policy approved by management?', 'yesno', null, 8, 1],
            ['Governance', 'Is there a named individual accountable for information security (e.g. CISO)?', 'yesno', null, 6, 0],
            ['Access Control', 'Is multi-factor authentication enforced for all remote and administrative access?', 'yesno', null, 9, 1],
            ['Access Control', 'Are user access rights reviewed at least quarterly?', 'yesno', null, 6, 0],
            ['Data Protection', 'Is customer data encrypted at rest and in transit?', 'yesno', null, 9, 1],
            ['Data Protection', 'Where is customer data stored and processed (regions/countries)?', 'text', null, 5, 0],
            ['Vulnerability Mgmt', 'Do you run penetration tests at least annually?', 'yesno', null, 7, 1],
            ['Vulnerability Mgmt', 'What is your maximum SLA for patching critical vulnerabilities?', 'choice', '72 hours|7 days|30 days|No defined SLA', 7, 0],
            ['Incident Response', 'Do you have a documented and tested incident response plan?', 'yesno', null, 8, 1],
            ['Incident Response', 'Within how many hours will you notify customers of a confirmed breach?', 'choice', '24|48|72|No commitment', 8, 0],
            ['Resilience', 'Do you maintain tested business continuity and disaster recovery plans?', 'yesno', null, 7, 1],
            ['Subcontractors', 'Do you use subcontractors (fourth parties) to deliver this service?', 'yesno', null, 5, 0],
        ]],
        ['ISO 27001 Alignment Check', 'Maps vendor controls against key ISO/IEC 27001 Annex A domains.', 'ISO 27001', [
            ['Certification', 'Do you hold a current ISO/IEC 27001 certificate?', 'yesno', null, 9, 1],
            ['Certification', 'What is the scope of your ISO 27001 certification?', 'text', null, 6, 0],
            ['A.5 Policies', 'Are security policies reviewed at planned intervals?', 'yesno', null, 5, 0],
            ['A.8 Asset Mgmt', 'Do you maintain an inventory of information assets?', 'yesno', null, 6, 0],
            ['A.9 Access', 'Is access provisioning based on least privilege?', 'yesno', null, 7, 0],
            ['A.12 Operations', 'Are systems protected by anti-malware and monitored logging?', 'yesno', null, 6, 0],
            ['A.17 Continuity', 'Is information security continuity embedded in your BCP?', 'yesno', null, 6, 0],
            ['A.18 Compliance', 'How do you ensure ongoing legal/regulatory compliance?', 'text', null, 5, 0],
        ]],
        ['GDPR & Privacy Readiness', 'Privacy-focused diligence for vendors that process personal data.', 'GDPR', [
            ['Lawfulness', 'Do you act as a processor, controller, or joint controller for our data?', 'choice', 'Processor|Controller|Joint controller|Not applicable', 7, 0],
            ['Contracts', 'Will you sign a Data Processing Agreement (DPA) with Standard Contractual Clauses where needed?', 'yesno', null, 9, 1],
            ['Rights', 'Can you support data-subject rights requests (access, erasure, portability) within 30 days?', 'yesno', null, 7, 0],
            ['Transfers', 'Is personal data transferred outside the EEA/UK? If yes, on what safeguard?', 'text', null, 7, 0],
            ['Breach', 'Will you notify us within 48 hours of a personal-data breach?', 'yesno', null, 9, 0],
            ['DPO', 'Have you appointed a Data Protection Officer or privacy lead?', 'yesno', null, 5, 0],
            ['Retention', 'Do you have defined retention and secure-deletion schedules for our data?', 'yesno', null, 6, 0],
        ]],
        ['Business Continuity & Resilience', 'Operational-resilience diligence (aligned with DORA expectations).', 'DORA', [
            ['Strategy', 'Do you maintain a business continuity plan covering this service?', 'yesno', null, 8, 1],
            ['Testing', 'When was the BCP/DR plan last tested?', 'choice', 'Within 6 months|Within 12 months|Over a year ago|Never', 8, 0],
            ['Targets', 'What is the Recovery Time Objective (RTO) for this service?', 'choice', '< 4 hours|< 24 hours|< 72 hours|Undefined', 7, 0],
            ['Dependencies', 'List critical third parties your service depends on (your fourth parties).', 'text', null, 6, 0],
            ['Capacity', 'Can you operate from an alternate site/region during a regional outage?', 'yesno', null, 6, 0],
            ['Comms', 'Do you have a customer-communication protocol for major incidents?', 'yesno', null, 5, 0],
        ]],
        ['Financial Health Check', 'Lightweight screen of vendor financial viability.', 'Custom', [
            ['Viability', 'Have you been profitable in the last two financial years?', 'yesno', null, 7, 0],
            ['Viability', 'Rate your current financial stability.', 'scale', null, 6, 0],
            ['Funding', 'Describe your ownership/funding structure (public, PE, VC, founder-owned).', 'text', null, 4, 0],
            ['Insurance', 'Do you carry cyber-liability insurance? State the coverage amount.', 'text', null, 7, 1],
            ['Concentration', 'Does any single customer represent more than 30% of revenue?', 'yesno', null, 5, 0],
        ]],
    ];
    foreach ($templates as $t) {
        $tpl->execute([$t[0], $t[1], $t[2]]);
        $tid = (int)$pdo->lastInsertId();
        foreach ($t[3] as $i => $qd) {
            $qst->execute([$tid, $qd[0], $qd[1], $qd[2], $qd[3], $qd[4], $qd[5], $i]);
        }
    }
}

function va_seed_demo(PDO $pdo): void {
    mt_srand(360360); // deterministic demo data

    $vendors = [
        // name, domain, industry, country, city, tier, data, services
        ['CloudNimbus Hosting','cloudnimbus.example','Cloud Infrastructure','United States','Austin','critical','PII, Confidential','IaaS hosting for production workloads'],
        ['PayStream Global','paystream.example','Payments','United Kingdom','London','critical','PCI, PII','Payment processing and settlement'],
        ['DataVault Analytics','datavault.example','Data & Analytics','United States','San Jose','critical','PII, Confidential','Customer data warehouse and BI'],
        ['SecureID Systems','secureid.example','Identity & Access','Germany','Berlin','critical','PII','SSO and identity federation'],
        ['MediClaim Partners','mediclaim.example','Healthcare Services','United States','Nashville','critical','PHI, PII','Claims processing'],
        ['NetGuard MSSP','netguard.example','Managed Security','Israel','Tel Aviv','high','Confidential','24/7 SOC monitoring'],
        ['TalentBridge HR','talentbridge.example','HR & Payroll','United States','Chicago','high','PII','Payroll and benefits administration'],
        ['LogiTrack Freight','logitrack.example','Logistics','Netherlands','Rotterdam','high','None','Freight management platform'],
        ['AdSphere Media','adsphere.example','Marketing','United States','New York','medium','PII','Digital advertising'],
        ['CodeForge DevTools','codeforge.example','Software Tools','Canada','Toronto','high','Confidential','CI/CD pipeline tooling'],
        ['HelpDesk Heroes','helpdeskheroes.example','Customer Support','Philippines','Manila','high','PII','Outsourced tier-1 support'],
        ['GreenLeaf Facilities','greenleaf.example','Facilities','United States','Denver','low','None','Office cleaning and maintenance'],
        ['PrintWorks Pro','printworks.example','Print Services','United States','Phoenix','low','None','Marketing collateral printing'],
        ['LegalEase Counsel','legalease.example','Legal Services','United Kingdom','Manchester','medium','Confidential','Contract review services'],
        ['TransLingua','translingua.example','Translation','Spain','Madrid','low','Confidential','Document translation'],
        ['ByteBackup Co','bytebackup.example','Backup & DR','United States','Salt Lake City','critical','PII, Confidential','Offsite backup and disaster recovery'],
        ['FormFlow eSign','formflow.example','E-Signature','United States','Seattle','high','PII, Confidential','Electronic signatures'],
        ['MetricPulse APM','metricpulse.example','Observability','United States','San Francisco','medium','Confidential','Application performance monitoring'],
        ['ShieldMail Gateway','shieldmail.example','Email Security','France','Paris','high','PII','Email filtering gateway'],
        ['CallCast VoIP','callcast.example','Telecom','United States','Dallas','medium','PII','Cloud telephony'],
        ['InvoiceHub AP','invoicehub.example','Finance SaaS','Australia','Sydney','high','PII, Financial','Accounts-payable automation'],
        ['RecruitRocket ATS','recruitrocket.example','HR Tech','United States','Boston','medium','PII','Applicant tracking'],
        ['SurveyLoop','surveyloop.example','Research','Canada','Vancouver','low','PII','Customer surveys'],
        ['FleetSense GPS','fleetsense.example','Fleet Mgmt','United States','Detroit','low','None','Vehicle tracking'],
        ['CryptoSafe Escrow','cryptosafe.example','Fintech','Switzerland','Zug','high','Financial, PII','Digital asset escrow'],
        ['TrainSmart LMS','trainsmart.example','EdTech','United States','Atlanta','medium','PII','Employee training platform'],
        ['BadgePoint Access','badgepoint.example','Physical Security','United States','Minneapolis','medium','PII','Badge and access control'],
        ['CaterCraft Events','catercraft.example','Catering','United States','Portland','low','None','Corporate catering'],
        ['ZenDesk Cleaners','zencleaners.example','Facilities','United States','Miami','low','None','Janitorial services'],
        ['ParcelPro Couriers','parcelpro.example','Courier','United Kingdom','Birmingham','low','PII','Document courier'],
        ['StackHosting CDN','stackhosting.example','CDN','United States','Los Angeles','high','None','Content delivery network'],
        ['VeriCheck KYC','vericheck.example','Compliance Tech','Ireland','Dublin','critical','PII, Financial','KYC identity verification'],
        ['OfficeOasis Supplies','officeoasis.example','Office Supplies','United States','Columbus','low','None','Office supplies'],
        ['PixelPerfect Design','pixelperfect.example','Design Agency','Portugal','Lisbon','low','Confidential','Brand and UI design'],
        ['RoboTest QA','robotest.example','QA Services','India','Bengaluru','medium','Confidential','Automated testing services'],
        ['ClearBooks Audit','clearbooks.example','Accounting','United States','Charlotte','high','Financial, Confidential','External audit support'],
        ['SnapShip Fulfilment','snapship.example','Fulfilment','United States','Memphis','medium','PII','Order fulfilment'],
        ['TerraScan GIS','terrascan.example','Geospatial','Norway','Oslo','low','None','Mapping services'],
        ['VividVideo Prod','vividvideo.example','Media Production','United States','Burbank','low','None','Video production'],
        ['QuantumLeap AI','quantumleap.example','AI/ML Services','United States','Palo Alto','high','Confidential, PII','ML model development'],
        ['SafeHarbor Archive','safeharbor.example','Records Mgmt','United States','Kansas City','medium','PII, Confidential','Records archiving'],
        ['BrightDesk IT','brightdesk.example','IT Services','India','Pune','high','Confidential','IT helpdesk outsourcing'],
        ['PolyglotBots','polyglotbots.example','Chatbots','Estonia','Tallinn','medium','PII','Customer chatbot platform'],
        ['EverGreen Energy','evergreen.example','Utilities','United States','Houston','medium','None','Renewable energy supply'],
        ['MarketMinds Research','marketminds.example','Market Research','United Kingdom','Edinburgh','low','PII','Market research panels'],
        ['CloudPBX Connect','cloudpbx.example','Telecom','Canada','Montreal','medium','PII','Hosted PBX'],
        ['IronGate Colo','irongate.example','Data Center','United States','Ashburn','critical','None','Colocation facility'],
        ['SwiftSign Notary','swiftsign.example','Legal Tech','United States','Las Vegas','low','PII','Remote notarization'],
        ['EchoCRM Suite','echocrm.example','CRM SaaS','United States','Salt Lake City','high','PII','Customer relationship management'],
        ['NovaPatch MSP','novapatch.example','Managed IT','United States','Tampa','high','Confidential','Patch management services'],
        ['BlueOrbit Travel','blueorbit.example','Travel Mgmt','United States','Orlando','low','PII, Financial','Corporate travel booking'],
        ['Aegis DDoS Shield','aegisddos.example','Network Security','Singapore','Singapore','high','None','DDoS mitigation'],
    ];

    $insV = $pdo->prepare('INSERT INTO vendors (name, legal_name, website, domain, industry, country, hq_city,
        description, services_provided, data_accessed, employees_band, revenue_band, year_founded, registration_no,
        duns_number, leadership, certifications, sanctions_status, tier, lifecycle, inherent_risk, risk_score,
        next_review_date, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insC = $pdo->prepare('INSERT INTO vendor_contacts (vendor_id, name, title, email, phone, is_primary) VALUES (?,?,?,?,?,?)');
    $insD = $pdo->prepare('INSERT INTO documents (vendor_id, title, category, orig_name, mime, size_bytes, version, tags, expiry_date, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $insK = $pdo->prepare('INSERT INTO contracts (vendor_id, title, contract_type, value_amount, currency, start_date, end_date,
        auto_renew, notice_period_days, clause_right_to_audit, clause_data_protection, clause_termination, clause_sla, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insB = $pdo->prepare('INSERT INTO breach_findings (vendor_id, source, breach_name, breach_date, records_exposed, data_classes, severity, description, dark_web_mentions) VALUES (?,?,?,?,?,?,?,?,?)');
    $insF = $pdo->prepare('INSERT INTO footprint_findings (vendor_id, category, item, status, detail) VALUES (?,?,?,?,?)');
    $insN = $pdo->prepare('INSERT INTO news_items (vendor_id, headline, source, published_date, category, severity, relevant, summary) VALUES (?,?,?,?,?,?,?,?)');
    $insI = $pdo->prepare('INSERT INTO issues (vendor_id, title, description, severity, status, sla_due, remediation_plan, source) VALUES (?,?,?,?,?,?,?,?)');
    $insR = $pdo->prepare('INSERT INTO risk_register (vendor_id, title, description, likelihood, impact, treatment, treatment_plan, status, review_date) VALUES (?,?,?,?,?,?,?,?,?)');
    $insP = $pdo->prepare('INSERT INTO fourth_parties (vendor_id, name, service, criticality, notes) VALUES (?,?,?,?,?)');
    $insS = $pdo->prepare('INSERT INTO risk_scores (vendor_id, score, breakdown, created_at) VALUES (?,?,?,?)');
    $insA = $pdo->prepare('INSERT INTO assessments (vendor_id, template_id, title, status, token, due_date, sent_to_email, score, round, created_at, submitted_at, decided_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $insAA = $pdo->prepare('INSERT INTO assessment_answers (assessment_id, question_id, answer, review_status, reviewer_comment) VALUES (?,?,?,?,?)');
    $insAE = $pdo->prepare('INSERT INTO assessment_events (assessment_id, actor, event) VALUES (?,?,?)');
    $insAl = $pdo->prepare('INSERT INTO alerts (vendor_id, type, message, severity) VALUES (?,?,?,?)');

    $first = ['Ava','Liam','Maya','Noah','Zara','Ethan','Ivy','Lucas','Nina','Omar','Priya','Ravi','Sofia','Tom','Lena','Marco'];
    $last  = ['Patel','Kim','Garcia','Smith','Mueller','Tanaka','Okafor','Rossi','Novak','Berg','Khan','Lopez','Chen','Dubois'];
    $docCats = [['SOC 2 Type II Report','SOC 2'],['ISO 27001 Certificate','ISO 27001'],['Cyber Insurance Certificate','Insurance'],
                ['Information Security Policy','Policy'],['Data Processing Agreement','DPA'],['Mutual NDA','NDA'],['Pen Test Summary','Other']];
    $fourthNames = ['AWS','Azure','Google Cloud','Twilio','Stripe','SendGrid','Cloudflare','Snowflake','Datadog','Okta'];
    $newsCat = [['Data breach affects customer accounts at %s','breach','high'],
                ['%s faces class-action lawsuit over data practices','lawsuit','high'],
                ['Regulator fines %s for compliance failures','fine','medium'],
                ['%s reports weaker-than-expected quarterly results','financial','low'],
                ['Former executive of %s under investigation','leadership','medium'],
                ['Audit flags internal-control weaknesses at %s','financial','medium'],
                ['Service outage at %s disrupts customers','financial','medium'],
                ['Researchers disclose vulnerability in %s product','breach','low']];
    $tplIds = $pdo->query('SELECT id FROM assessment_templates ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $qByTpl = [];
    foreach ($tplIds as $tid) {
        $qByTpl[$tid] = $pdo->query('SELECT id, qtype, choices FROM template_questions WHERE template_id = ' . (int)$tid)->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($vendors as $idx => $vd) {
        [$name,$domain,$industry,$country,$city,$tier,$data,$services] = $vd;
        $inherent = ['critical'=>mt_rand(65,90),'high'=>mt_rand(50,75),'medium'=>mt_rand(30,55),'low'=>mt_rand(10,35)][$tier];
        $lifecycle = $idx % 17 === 0 ? 'under_review' : ($idx % 23 === 0 ? 'offboarding' : ($idx % 11 === 0 ? 'onboarding' : 'active'));
        $created = date('Y-m-d H:i:s', strtotime('-' . mt_rand(60, 900) . ' days'));
        $lead = json_encode([
            ['name' => $first[$idx % 16] . ' ' . $last[$idx % 14], 'title' => 'Chief Executive Officer'],
            ['name' => $first[($idx + 5) % 16] . ' ' . $last[($idx + 3) % 14], 'title' => 'Chief Information Security Officer'],
        ]);
        $certPool = ['ISO 27001','SOC 2 Type II','PCI DSS','ISO 22301','Cyber Essentials'];
        $certs = implode(', ', array_slice($certPool, $idx % 3, ($idx % 3) + 1));
        $insV->execute([$name, $name . ' Inc.', 'https://' . $domain, $domain, $industry, $country, $city,
            $name . ' provides ' . strtolower($services) . ' for enterprise customers worldwide.',
            $services, $data, ['1-50','51-200','201-1000','1000+'][$idx % 4], ['<$10M','$10M-$50M','$50M-$250M','$250M+'][$idx % 4],
            1990 + ($idx * 7) % 33, 'REG-' . str_pad((string)(184000 + $idx * 137), 7, '0', STR_PAD_LEFT),
            (string)(600000000 + $idx * 9173), $lead, $certs, $idx % 19 === 0 ? 'flagged' : 'clear',
            $tier, $lifecycle, $inherent, 500,
            date('Y-m-d', strtotime('+' . mt_rand(10, 320) . ' days')), $created]);
        $vid = (int)$pdo->lastInsertId();

        // Contacts
        for ($c = 0; $c < 2; $c++) {
            $cn = $first[($idx + $c * 4) % 16] . ' ' . $last[($idx + $c * 6) % 14];
            $insC->execute([$vid, $cn, $c === 0 ? 'Account Director' : 'Security Lead',
                strtolower(str_replace(' ', '.', $cn)) . '@' . $domain, '+1-555-' . str_pad((string)(($idx * 37 + $c * 11) % 10000), 4, '0', STR_PAD_LEFT), $c === 0 ? 1 : 0]);
        }

        // Documents (2-4, mixed expiry incl. some expired/expiring soon)
        $nd = 2 + ($idx % 3);
        for ($d = 0; $d < $nd; $d++) {
            $dc = $docCats[($idx + $d) % count($docCats)];
            $exp = null;
            if (in_array($dc[1], ['SOC 2','ISO 27001','Insurance'], true)) {
                $exp = date('Y-m-d', strtotime(($idx % 7 === 0 && $d === 0 ? '-' : '+') . mt_rand(5, 300) . ' days'));
            }
            $insD->execute([$vid, $dc[0] . ' — ' . date('Y'), $dc[1],
                strtolower(str_replace(' ', '_', $dc[0])) . '.pdf', 'application/pdf', mt_rand(120000, 4200000), 1,
                strtolower($dc[1]), $exp, $created]);
        }

        // Contract (one active; some expiring soon to light up reminders)
        $months = [12, 24, 36][$idx % 3];
        $start = date('Y-m-d', strtotime('-' . mt_rand(2, $months - 1) . ' months'));
        $end = $idx % 6 === 0 ? date('Y-m-d', strtotime('+' . mt_rand(7, 55) . ' days'))
                              : date('Y-m-d', strtotime($start . ' +' . $months . ' months'));
        $insK->execute([$vid, 'Master Services Agreement — ' . $name, 'MSA',
            mt_rand(20, 900) * 1000, 'USD', $start, $end, $idx % 2, [30,60,90][$idx % 3],
            (int)($idx % 3 !== 0), 1, (int)($idx % 4 !== 0), (int)($idx % 2 === 0),
            strtotime($end) < strtotime('+60 days') ? 'expiring' : 'active',
            'Auto-generated demo contract record.']);

        // Breach findings (deterministic count 0-3)
        $seed = crc32($name);
        $bCat = [['Credential Stuffing Exposure','Email addresses, Passwords','high',2400000],
                 ['Marketing Database Leak','Names, Email addresses, Phone numbers','medium',310000],
                 ['Legacy Forum Breach','Usernames, Passwords (hashed)','low',85000],
                 ['Cloud Storage Misconfiguration','Customer records, Internal documents','critical',9800000]];
        for ($b = 0; $b < $seed % 4; $b++) {
            $bc = $bCat[($seed + $b * 7) % 4];
            $insB->execute([$vid, 'demo', $name . ' — ' . $bc[0],
                date('Y-m-d', strtotime('-' . (($seed % 48) + $b * 11) . ' months')), $bc[3], $bc[1], $bc[2],
                'Demo-mode finding. Configure a HaveIBeenPwned API key in Settings → Integrations for live data.',
                ($seed + $b) % 14]);
        }

        // Footprint checks
        $fpChecks = [['email_security','SPF record'],['email_security','DKIM signing'],['email_security','DMARC policy'],
                     ['ssl','TLS certificate'],['ssl','Protocol versions'],['headers','Security headers'],
                     ['dns','CAA records'],['subdomains','Exposed subdomains'],['tech','Technology stack'],['social','Web & social presence']];
        foreach ($fpChecks as $fi => $fc) {
            $status = ['pass','pass','warn','pass','fail','warn'][($seed + $fi) % 6];
            if (in_array($fc[0], ['tech','social'], true)) $status = 'info';
            $insF->execute([$vid, $fc[0], $fc[1], $status, '[Demo] Passive check result for evaluation.']);
        }

        // News (0-6 items, last 10 years)
        for ($n = 0; $n < ($seed % 7); $n++) {
            $nc = $newsCat[($seed + $n * 3) % count($newsCat)];
            $insN->execute([$vid, sprintf($nc[0], $name), ['Reuters','Bloomberg','TechCrunch','The Register','FT'][($seed + $n) % 5],
                date('Y-m-d', strtotime('-' . (($seed + $n * 97) % 3650) . ' days')), $nc[1], $nc[2],
                $n % 3 === 0 ? 1 : null, 'Demo-mode adverse media item for evaluation.']);
        }

        // Issues (some vendors)
        if ($idx % 4 === 0) {
            $insI->execute([$vid, 'MFA not enforced for admin portal', 'Assessment answer indicated multi-factor authentication is not enforced for administrative access.',
                'high', $idx % 8 === 0 ? 'in_remediation' : 'open', date('Y-m-d', strtotime('+' . mt_rand(-10, 45) . ' days')),
                'Vendor to enforce MFA on all admin accounts and provide evidence.', 'assessment']);
        }
        if ($idx % 9 === 0) {
            $insI->execute([$vid, 'Expired SOC 2 report on file', 'The SOC 2 Type II report has passed its period end date.',
                'medium', 'open', date('Y-m-d', strtotime('+21 days')), 'Request current report.', 'manual']);
        }

        // Risk register entries
        if ($idx % 5 === 0) {
            $insR->execute([$vid, 'Concentration risk — single region hosting',
                'Vendor hosts all production workloads in a single cloud region.',
                3, 4, 'mitigate', 'Contract addendum requiring multi-region DR by next renewal.',
                'open', date('Y-m-d', strtotime('+90 days'))]);
        }

        // Fourth parties
        for ($f = 0; $f < ($idx % 4); $f++) {
            $insP->execute([$vid, $fourthNames[($idx + $f * 3) % 10], 'Infrastructure / sub-processing',
                ['critical','high','medium','low'][($idx + $f) % 4], 'Disclosed sub-processor.']);
        }

        // Score history (6 monthly points trending to current) + composite
        $factors = [
            'assessment' => mt_rand(40, 95), 'breach' => max(5, 100 - ($seed % 4) * 18),
            'footprint' => mt_rand(45, 95), 'news' => max(10, 100 - ($seed % 7) * 9),
            'compliance' => mt_rand(50, 95), 'inherent' => 100 - $inherent,
        ];
        $w = ['assessment'=>.25,'breach'=>.20,'footprint'=>.15,'news'=>.10,'compliance'=>.15,'inherent'=>.15];
        $comp = 0; foreach ($w as $k=>$wt) $comp += $factors[$k] * $wt;
        $comp = (int)round($comp * 10);
        for ($m = 5; $m >= 0; $m--) {
            $insS->execute([$vid, max(0, min(1000, $comp + mt_rand(-70, 70))),
                json_encode($factors), date('Y-m-d H:i:s', strtotime("-$m months"))]);
        }
        $insS->execute([$vid, $comp, json_encode($factors), date('Y-m-d H:i:s')]);
        $pdo->prepare('UPDATE vendors SET risk_score = ? WHERE id = ?')->execute([$comp, $vid]);

        // Assessments in varied workflow states
        if ($idx % 2 === 0) {
            $tid = (int)$tplIds[$idx % count($tplIds)];
            $statusPool = ['approved','submitted','in_review','sent','clarification','rejected','in_progress'];
            $status = $statusPool[$idx % count($statusPool)];
            $token = bin2hex(random_bytes(24));
            $score = $status === 'approved' ? mt_rand(55, 96) : ($status === 'rejected' ? mt_rand(20, 50) : null);
            $sub = in_array($status, ['submitted','in_review','approved','rejected','clarification'], true)
                 ? date('Y-m-d H:i:s', strtotime('-' . mt_rand(3, 60) . ' days')) : null;
            $dec = in_array($status, ['approved','rejected'], true) ? date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 20) . ' days')) : null;
            $insA->execute([$vid, $tid, 'Annual Due Diligence ' . date('Y') . ' — ' . $name, $status, $token,
                date('Y-m-d', strtotime('+' . mt_rand(5, 60) . ' days')),
                'security@' . $domain, $score, $status === 'clarification' ? 2 : 1, $created, $sub, $dec]);
            $aid = (int)$pdo->lastInsertId();
            $insAE->execute([$aid, 'System', 'Assessment created (demo data)']);
            if ($sub) {
                $insAE->execute([$aid, 'Vendor', 'Questionnaire submitted']);
                foreach ($qByTpl[$tid] as $qi => $qrow) {
                    $ans = $qrow['qtype'] === 'yesno' ? (($seed + $qi) % 5 === 0 ? 'No' : 'Yes')
                         : ($qrow['qtype'] === 'scale' ? (string)mt_rand(2, 5)
                         : ($qrow['qtype'] === 'choice' ? (explode('|', (string)$qrow['choices'])[($seed + $qi) % max(1, count(explode('|', (string)$qrow['choices'])))])
                         : 'Provided in attached documentation (demo answer).'));
                    $rv = $status === 'approved' ? 'approved'
                        : ($status === 'rejected' ? (($qi % 4 === 0) ? 'rejected' : 'approved')
                        : ($status === 'clarification' ? (($qi % 5 === 0) ? 'clarify' : 'approved') : 'pending'));
                    $insAA->execute([$aid, (int)$qrow['id'], $ans, $rv,
                        $rv === 'clarify' ? 'Please provide supporting evidence for this control.' : null]);
                }
                if ($dec) $insAE->execute([$aid, 'TPRM Analyst', 'Final decision: ' . $status]);
            }
        }
    }

    // A few program alerts so the bell isn't empty
    $insAl->execute([null, 'assessment_due', '7 assessments are awaiting analyst review', 'warning']);
    $insAl->execute([null, 'contract_expiry', 'Multiple contracts expire within 60 days — see Calendar', 'warning']);
    $insAl->execute([null, 'news', 'New adverse media detected across your vendor portfolio', 'info']);
}
