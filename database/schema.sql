-- VendorAssess 360 by LearnTPRM.com — Database Schema
-- MySQL / MariaDB (XAMPP compatible). Engine: InnoDB, charset utf8mb4.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','analyst','viewer') NOT NULL DEFAULT 'analyst',
  status ENUM('active','inactive','invited') NOT NULL DEFAULT 'active',
  invite_token VARCHAR(64) DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(100) NOT NULL PRIMARY KEY,
  svalue TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  legal_name VARCHAR(190) DEFAULT NULL,
  website VARCHAR(190) DEFAULT NULL,
  domain VARCHAR(190) DEFAULT NULL,
  industry VARCHAR(120) DEFAULT NULL,
  country VARCHAR(80) DEFAULT NULL,
  hq_city VARCHAR(120) DEFAULT NULL,
  description TEXT,
  services_provided TEXT,
  data_accessed VARCHAR(255) DEFAULT NULL,         -- e.g. PII, PCI, PHI, None
  employees_band VARCHAR(40) DEFAULT NULL,          -- 1-50, 51-500, ...
  revenue_band VARCHAR(40) DEFAULT NULL,
  year_founded SMALLINT UNSIGNED DEFAULT NULL,
  registration_no VARCHAR(80) DEFAULT NULL,
  duns_number VARCHAR(20) DEFAULT NULL,
  leadership TEXT,                                  -- JSON [{name,title}]
  certifications VARCHAR(255) DEFAULT NULL,         -- comma list: ISO 27001, SOC 2...
  sanctions_status ENUM('clear','flagged','unchecked') NOT NULL DEFAULT 'unchecked',
  tier ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  lifecycle ENUM('onboarding','active','under_review','offboarding','terminated') NOT NULL DEFAULT 'onboarding',
  inherent_risk TINYINT UNSIGNED NOT NULL DEFAULT 50,  -- 0-100 from onboarding answers
  risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 500,   -- composite 0-1000
  owner_user_id INT UNSIGNED DEFAULT NULL,
  next_review_date DATE DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vendor_name (name),
  INDEX idx_vendor_tier (tier),
  INDEX idx_vendor_lifecycle (lifecycle),
  INDEX idx_vendor_score (risk_score),
  INDEX idx_vendor_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendor_contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  title VARCHAR(120) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_vc_vendor (vendor_id),
  CONSTRAINT fk_vc_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Other',   -- SOC 2, ISO 27001, Insurance, Policy, DPA, NDA, Other
  filename VARCHAR(255) DEFAULT NULL,              -- stored randomized name in /uploads
  orig_name VARCHAR(255) DEFAULT NULL,
  mime VARCHAR(120) DEFAULT NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  tags VARCHAR(255) DEFAULT NULL,
  expiry_date DATE DEFAULT NULL,
  uploaded_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_doc_vendor (vendor_id),
  INDEX idx_doc_expiry (expiry_date),
  INDEX idx_doc_cat (category),
  CONSTRAINT fk_doc_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contracts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  contract_type VARCHAR(80) DEFAULT 'MSA',         -- MSA, SOW, DPA, NDA, SLA, Other
  value_amount DECIMAL(14,2) DEFAULT NULL,
  currency VARCHAR(8) DEFAULT 'USD',
  start_date DATE DEFAULT NULL,
  end_date DATE DEFAULT NULL,
  auto_renew TINYINT(1) NOT NULL DEFAULT 0,
  notice_period_days SMALLINT UNSIGNED DEFAULT NULL,
  clause_right_to_audit TINYINT(1) NOT NULL DEFAULT 0,
  clause_data_protection TINYINT(1) NOT NULL DEFAULT 0,
  clause_termination TINYINT(1) NOT NULL DEFAULT 0,
  clause_sla TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','active','expiring','expired','terminated') NOT NULL DEFAULT 'active',
  filename VARCHAR(255) DEFAULT NULL,
  orig_name VARCHAR(255) DEFAULT NULL,
  notes TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_con_vendor (vendor_id),
  INDEX idx_con_end (end_date),
  INDEX idx_con_status (status),
  CONSTRAINT fk_con_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  description TEXT,
  framework VARCHAR(120) DEFAULT NULL,             -- ISO 27001, SOC 2, GDPR, NIST, DORA, Custom
  is_builtin TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS template_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  section VARCHAR(150) NOT NULL DEFAULT 'General',
  question TEXT NOT NULL,
  qtype ENUM('yesno','text','choice','scale') NOT NULL DEFAULT 'yesno',
  choices VARCHAR(500) DEFAULT NULL,               -- pipe-separated for choice type
  weight TINYINT UNSIGNED NOT NULL DEFAULT 5,      -- 1-10
  evidence_required TINYINT(1) NOT NULL DEFAULT 0,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_tq_tpl (template_id),
  CONSTRAINT fk_tq_tpl FOREIGN KEY (template_id) REFERENCES assessment_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  status ENUM('draft','sent','in_progress','submitted','in_review','clarification','approved','rejected') NOT NULL DEFAULT 'draft',
  token VARCHAR(64) NOT NULL,                      -- secure portal token
  due_date DATE DEFAULT NULL,
  sent_to_email VARCHAR(190) DEFAULT NULL,
  score TINYINT UNSIGNED DEFAULT NULL,             -- 0-100 computed on review
  round SMALLINT UNSIGNED NOT NULL DEFAULT 1,      -- clarification rounds
  created_by INT UNSIGNED DEFAULT NULL,
  reviewed_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME DEFAULT NULL,
  decided_at DATETIME DEFAULT NULL,
  UNIQUE KEY uniq_token (token),
  INDEX idx_as_vendor (vendor_id),
  INDEX idx_as_status (status),
  CONSTRAINT fk_as_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_as_tpl FOREIGN KEY (template_id) REFERENCES assessment_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  answer TEXT,
  evidence_file VARCHAR(255) DEFAULT NULL,
  evidence_orig VARCHAR(255) DEFAULT NULL,
  review_status ENUM('pending','approved','rejected','clarify') NOT NULL DEFAULT 'pending',
  reviewer_comment TEXT,
  vendor_comment TEXT,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_aq (assessment_id, question_id),
  CONSTRAINT fk_aa_as FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  CONSTRAINT fk_aa_q FOREIGN KEY (question_id) REFERENCES template_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT UNSIGNED NOT NULL,
  actor VARCHAR(120) NOT NULL,                     -- user name or 'Vendor'
  event VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ae_as (assessment_id),
  CONSTRAINT fk_ae_as FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS breach_findings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  source ENUM('demo','hibp','manual') NOT NULL DEFAULT 'demo',
  breach_name VARCHAR(190) NOT NULL,
  breach_date DATE DEFAULT NULL,
  records_exposed BIGINT UNSIGNED DEFAULT NULL,
  data_classes VARCHAR(500) DEFAULT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  description TEXT,
  dark_web_mentions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_bf_vendor (vendor_id),
  CONSTRAINT fk_bf_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS footprint_findings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  category VARCHAR(60) NOT NULL,                   -- dns, email_security, ssl, headers, subdomains, tech, social, whois
  item VARCHAR(190) NOT NULL,
  status ENUM('pass','warn','fail','info') NOT NULL DEFAULT 'info',
  detail TEXT,
  scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ff_vendor (vendor_id),
  CONSTRAINT fk_ff_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS news_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  headline VARCHAR(255) NOT NULL,
  source VARCHAR(120) DEFAULT NULL,
  url VARCHAR(500) DEFAULT NULL,
  published_date DATE DEFAULT NULL,
  category VARCHAR(60) DEFAULT 'adverse',          -- breach, lawsuit, fine, financial, sanctions, leadership
  severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  relevant TINYINT(1) DEFAULT NULL,                -- analyst disposition; NULL = not reviewed
  summary TEXT,
  INDEX idx_ni_vendor (vendor_id),
  CONSTRAINT fk_ni_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS risk_scores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  score SMALLINT UNSIGNED NOT NULL,
  breakdown TEXT,                                  -- JSON of factor scores
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rs_vendor (vendor_id),
  CONSTRAINT fk_rs_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS issues (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','in_remediation','resolved','accepted','overdue') NOT NULL DEFAULT 'open',
  sla_due DATE DEFAULT NULL,
  owner_user_id INT UNSIGNED DEFAULT NULL,
  remediation_plan TEXT,
  source VARCHAR(80) DEFAULT 'manual',             -- assessment, breach, footprint, news, manual
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  INDEX idx_is_vendor (vendor_id),
  INDEX idx_is_status (status),
  CONSTRAINT fk_is_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS risk_register (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED DEFAULT NULL,             -- NULL = program-level risk
  title VARCHAR(190) NOT NULL,
  description TEXT,
  likelihood TINYINT UNSIGNED NOT NULL DEFAULT 3,  -- 1-5
  impact TINYINT UNSIGNED NOT NULL DEFAULT 3,      -- 1-5
  treatment ENUM('mitigate','accept','transfer','avoid') NOT NULL DEFAULT 'mitigate',
  treatment_plan TEXT,
  status ENUM('open','monitoring','closed') NOT NULL DEFAULT 'open',
  owner_user_id INT UNSIGNED DEFAULT NULL,
  review_date DATE DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rr_vendor (vendor_id),
  CONSTRAINT fk_rr_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fourth_parties (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  service VARCHAR(190) DEFAULT NULL,
  criticality ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  notes TEXT,
  INDEX idx_fp_vendor (vendor_id),
  CONSTRAINT fk_fp_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS offboarding_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  task VARCHAR(255) NOT NULL,
  done TINYINT(1) NOT NULL DEFAULT 0,
  done_by INT UNSIGNED DEFAULT NULL,
  done_at DATETIME DEFAULT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_ob_vendor (vendor_id),
  CONSTRAINT fk_ob_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alerts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED DEFAULT NULL,
  type VARCHAR(60) NOT NULL,                       -- contract_expiry, doc_expiry, breach, news, score_drop, assessment_due
  message VARCHAR(500) NOT NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_al_read (is_read),
  INDEX idx_al_vendor (vendor_id),
  CONSTRAINT fk_al_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  user_name VARCHAR(120) DEFAULT NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(60) DEFAULT NULL,
  entity_id INT UNSIGNED DEFAULT NULL,
  detail VARCHAR(500) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_au_created (created_at),
  INDEX idx_au_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vendor_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  user_name VARCHAR(120) DEFAULT NULL,
  note TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vn_vendor (vendor_id),
  CONSTRAINT fk_vn_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
