<?php
/** CSV exports (Excel-ready, UTF-8 BOM). */
require_perm('export');
$what = $_GET['what'] ?? '';

switch ($what) {
    case 'vendors':
        $data = rows('SELECT name, legal_name, domain, industry, country, tier, lifecycle, risk_score,
                      inherent_risk, data_accessed, next_review_date, created_at FROM vendors ORDER BY name');
        audit('export', 'vendor', null, 'vendors.csv');
        csv_download('vendors.csv',
            ['Name','Legal name','Domain','Industry','Country','Tier','Lifecycle','Risk score (0-1000)','Inherent risk','Data accessed','Next review','Created'],
            array_map('array_values', $data));
        break;
    case 'contracts':
        $data = rows('SELECT v.name, c.title, c.contract_type, c.value_amount, c.currency, c.start_date, c.end_date,
                      c.auto_renew, c.notice_period_days, c.status FROM contracts c JOIN vendors v ON v.id = c.vendor_id ORDER BY c.end_date');
        audit('export', 'contract', null, 'contracts.csv');
        csv_download('contracts.csv',
            ['Vendor','Title','Type','Value','Currency','Start','End','Auto-renew','Notice days','Status'],
            array_map('array_values', $data));
        break;
    case 'documents':
        $data = rows('SELECT v.name, d.title, d.category, d.version, d.size_bytes, d.expiry_date, d.created_at
                      FROM documents d JOIN vendors v ON v.id = d.vendor_id ORDER BY v.name');
        audit('export', 'document', null, 'documents.csv');
        csv_download('documents.csv', ['Vendor','Title','Category','Version','Size (bytes)','Expiry','Uploaded'],
            array_map('array_values', $data));
        break;
    case 'assessments':
        $data = rows('SELECT v.name, a.title, t.name, a.status, a.round, a.score, a.due_date, a.submitted_at, a.decided_at
                      FROM assessments a JOIN vendors v ON v.id = a.vendor_id
                      JOIN assessment_templates t ON t.id = a.template_id ORDER BY a.created_at DESC');
        audit('export', 'assessment', null, 'assessments.csv');
        csv_download('assessments.csv', ['Vendor','Title','Template','Status','Round','Score','Due','Submitted','Decided'],
            array_map('array_values', $data));
        break;
    case 'issues':
        $data = rows('SELECT v.name, i.title, i.severity, i.status, i.sla_due, i.source, i.created_at, i.resolved_at
                      FROM issues i JOIN vendors v ON v.id = i.vendor_id ORDER BY i.created_at DESC');
        audit('export', 'issue', null, 'issues.csv');
        csv_download('issues.csv', ['Vendor','Title','Severity','Status','SLA due','Source','Created','Resolved'],
            array_map('array_values', $data));
        break;
    case 'risks':
        $data = rows('SELECT COALESCE(v.name, "Program-level"), r.title, r.likelihood, r.impact, r.treatment, r.status, r.review_date
                      FROM risk_register r LEFT JOIN vendors v ON v.id = r.vendor_id ORDER BY (r.likelihood*r.impact) DESC');
        audit('export', 'risk', null, 'risk_register.csv');
        csv_download('risk_register.csv', ['Vendor','Risk','Likelihood','Impact','Treatment','Status','Review date'],
            array_map('array_values', $data));
        break;
    case 'audit':
        require_perm('*');
        $data = rows('SELECT created_at, user_name, action, entity, entity_id, detail, ip FROM audit_log ORDER BY created_at DESC LIMIT 20000');
        csv_download('audit_log.csv', ['When (UTC)','User','Action','Entity','Entity ID','Detail','IP'],
            array_map('array_values', $data));
        break;
    default:
        flash('error', 'Unknown export type.');
        redirect('reports');
}
