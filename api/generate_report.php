<?php
// ============================================================
//  ARAMS — Generate Report API (Admin only)
//  Generates real Excel (CSV), PDF-ready HTML, or CSV files
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');

// Accept both form POST and JSON body
if (!empty($_POST)) {
    $type      = $_POST['type']       ?? 'comprehensive';
    $year      = $_POST['year']       ?? 'all';
    $facultyId = $_POST['faculty_id'] ?? 'all';
    $format    = $_POST['format']     ?? 'Excel';
} else {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $type      = $body['type']       ?? 'comprehensive';
    $year      = $body['year']       ?? 'all';
    $facultyId = $body['faculty_id'] ?? 'all';
    $format    = $body['format']     ?? 'Excel';
}

$user    = currentUser();
$adminId = (int)$user['admin_id'];
$db      = getDB();

// ── Build WHERE clauses ───────────────────────────────────
$yearWhere    = $year !== 'all'      ? " AND p.pub_year = " . (int)$year : '';
$facWhere     = $facultyId !== 'all' ? " AND l.faculty_id = " . (int)$facultyId : '';
$yearWhereG   = $year !== 'all'      ? " AND YEAR(g.start_date) = " . (int)$year : '';

// ── Fetch data based on report type ──────────────────────
$rows  = [];
$title = '';
$cols  = [];

switch ($type) {

    case 'publications':
        $title = 'Publications Report';
        $cols  = ['#','Lecturer','Faculty','Title','Type','Indexing','Quartile','Year','Journal','DOI'];
        $sql   = "SELECT l.full_name, f.faculty_code, p.title, p.pub_type,
                         p.indexing_type, p.quartile, p.pub_year, p.journal_name, p.doi
                  FROM Tbl_Publication p
                  JOIN Tbl_Research_Data rd ON p.data_id = rd.data_id
                  JOIN Tbl_Lecturer l ON l.lecturer_id = rd.lecturer_id
                  JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                  WHERE rd.status = 'Approved' $yearWhere $facWhere
                  ORDER BY p.pub_year DESC, l.full_name";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'], $r['title'],
                       $r['pub_type'], $r['indexing_type'], $r['quartile'],
                       $r['pub_year'], $r['journal_name'], $r['doi']];
        }
        break;

    case 'grants':
        $title = 'Grants & Funding Report';
        $cols  = ['#','Lecturer','Faculty','Grant Title','Code','Category','Level','Role','Amount (RM)','Status','Start','End'];
        $sql   = "SELECT l.full_name, f.faculty_code, g.grant_title, g.grant_code,
                         g.grant_category, g.grant_level, g.role, g.amount,
                         g.status, g.start_date, g.end_date
                  FROM Tbl_Grant g
                  JOIN Tbl_Research_Data rd ON g.data_id = rd.data_id
                  JOIN Tbl_Lecturer l ON l.lecturer_id = rd.lecturer_id
                  JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                  WHERE rd.status = 'Approved' $yearWhereG $facWhere
                  ORDER BY g.start_date DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'], $r['grant_title'],
                       $r['grant_code'], $r['grant_category'], $r['grant_level'],
                       $r['role'], $r['amount'] ? number_format((float)$r['amount'],2) : '',
                       $r['status'], $r['start_date'], $r['end_date']];
        }
        break;

    case 'hindex':
        $title = 'H-Index & Citations Report';
        $cols  = ['#','Lecturer','Faculty','Year','H-Index','Citations','Source'];
        $sql   = "SELECT l.full_name, f.faculty_code, h.record_year,
                         h.hindex_value, h.citation_count, h.source
                  FROM Tbl_HIndex h
                  JOIN Tbl_Research_Data rd ON h.data_id = rd.data_id
                  JOIN Tbl_Lecturer l ON l.lecturer_id = rd.lecturer_id
                  JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                  WHERE rd.status = 'Approved' $facWhere
                  ORDER BY h.record_year DESC, l.full_name";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'],
                       $r['record_year'], $r['hindex_value'], $r['citation_count'], $r['source']];
        }
        break;

    case 'faculty':
        $title = 'Faculty Performance Report';
        $cols  = ['#','Faculty','Lecturers','Total Pubs','Q1 Pubs','Q2 Pubs','Total Grants','Avg H-Index','Total Income (RM)'];
        $sql   = "SELECT f.faculty_code, f.faculty_name,
                         COUNT(DISTINCT l.lecturer_id) AS lec_count,
                         SUM(k.total_publications) AS pubs,
                         SUM(k.q1_pubs) AS q1, SUM(k.q2_pubs) AS q2,
                         SUM(k.total_grants) AS grants,
                         AVG(k.current_hindex) AS hindex,
                         SUM(k.total_income_rm) AS income
                  FROM vw_lecturer_kpi k
                  JOIN Tbl_Lecturer l ON l.lecturer_id = k.lecturer_id
                  JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                  GROUP BY f.faculty_id ORDER BY pubs DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['faculty_code'], $r['lec_count'],
                       (int)$r['pubs'], (int)$r['q1'], (int)$r['q2'],
                       (int)$r['grants'], number_format((float)$r['hindex'],1),
                       $r['income'] ? number_format((float)$r['income'],2) : '0.00'];
        }
        break;

    case 'individual':
        $title = 'Individual Lecturer Report';
        $cols  = ['#','Lecturer','Faculty','Scopus ID','Total Pubs','Q1','Q2','Total Grants','Grants as PI','H-Index','Citations','Income (RM)'];
        $sql   = "SELECT l.full_name, f.faculty_code, l.scopus_id,
                         k.total_publications, k.q1_pubs, k.q2_pubs,
                         k.total_grants, k.grants_as_pi,
                         k.current_hindex, k.total_citations, k.total_income_rm
                  FROM vw_lecturer_kpi k
                  JOIN Tbl_Lecturer l ON l.lecturer_id = k.lecturer_id
                  JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                  $( $facultyId !== 'all' ? 'WHERE l.faculty_id = ' . (int)$facultyId : '' )
                  ORDER BY k.total_publications DESC";
        // Use proper conditional
        $whereInd = $facultyId !== 'all' ? 'WHERE l.faculty_id = ' . (int)$facultyId : '';
        $sql = "SELECT l.full_name, f.faculty_code, l.scopus_id,
                       k.total_publications, k.q1_pubs, k.q2_pubs,
                       k.total_grants, k.grants_as_pi,
                       k.current_hindex, k.total_citations, k.total_income_rm
                FROM vw_lecturer_kpi k
                JOIN Tbl_Lecturer l ON l.lecturer_id = k.lecturer_id
                JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                $whereInd
                ORDER BY k.total_publications DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'], $r['scopus_id'],
                       (int)$r['total_publications'], (int)$r['q1_pubs'], (int)$r['q2_pubs'],
                       (int)$r['total_grants'], (int)$r['grants_as_pi'],
                       (int)$r['current_hindex'], (int)$r['total_citations'],
                       $r['total_income_rm'] ? number_format((float)$r['total_income_rm'],2) : '0.00'];
        }
        break;

    case 'awards':
        $title = 'Awards & IP Report';
        $cols  = ['#','Lecturer','Faculty','Type','Name','Level/IP Type','Year/Filing Date','Organiser/Status'];
        $sql   = "SELECT l.full_name, f.faculty_code, 'Award' AS rec_type,
                         a.award_name AS rec_name, a.level AS rec_level,
                         a.award_year AS rec_year, a.organiser AS rec_org
                  FROM Tbl_Award a
                  JOIN Tbl_Lecturer l ON l.lecturer_id = a.lecturer_id
                  JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                  $facWhere
                  UNION ALL
                  SELECT l.full_name, f.faculty_code, ip.ip_type AS rec_type,
                         ip.ip_title AS rec_name, ip.ip_type AS rec_level,
                         ip.filing_date AS rec_year, ip.status AS rec_org
                  FROM Tbl_IP_Record ip
                  JOIN Tbl_Research_Data rd ON ip.data_id = rd.data_id
                  JOIN Tbl_Lecturer l ON l.lecturer_id = rd.lecturer_id
                  JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                  WHERE rd.status = 'Approved' $facWhere
                  ORDER BY rec_year DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'],
                       $r['rec_type'], $r['rec_name'], $r['rec_level'],
                       $r['rec_year'], $r['rec_org'] ?? '—'];
        }
        break;

    default: // comprehensive
        $title = 'Comprehensive Research Report';
        $cols  = ['#','Lecturer','Faculty','Pubs','Q1','Q2','Grants','PI Grants','H-Index','Citations','Income (RM)'];
        $sql   = "SELECT l.full_name, f.faculty_code,
                         k.total_publications, k.q1_pubs, k.q2_pubs,
                         k.total_grants, k.grants_as_pi,
                         k.current_hindex, k.total_citations, k.total_income_rm
                  FROM vw_lecturer_kpi k
                  JOIN Tbl_Lecturer l ON l.lecturer_id = k.lecturer_id
                  JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                  $( $facultyId !== 'all' ? 'WHERE l.faculty_id = ?' : '' )
                  ORDER BY k.total_publications DESC";
        $whereComp = $facultyId !== 'all' ? 'WHERE l.faculty_id = ' . (int)$facultyId : '';
        $sql = "SELECT l.full_name, f.faculty_code,
                       k.total_publications, k.q1_pubs, k.q2_pubs,
                       k.total_grants, k.grants_as_pi,
                       k.current_hindex, k.total_citations, k.total_income_rm
                FROM vw_lecturer_kpi k
                JOIN Tbl_Lecturer l ON l.lecturer_id = k.lecturer_id
                JOIN Tbl_Faculty  f ON f.faculty_id  = l.faculty_id
                $whereComp
                ORDER BY k.total_publications DESC";
        $st = $db->prepare($sql); $st->execute(); $data = $st->fetchAll();
        foreach ($data as $i => $r) {
            $rows[] = [$i+1, $r['full_name'], $r['faculty_code'],
                       (int)$r['total_publications'], (int)$r['q1_pubs'], (int)$r['q2_pubs'],
                       (int)$r['total_grants'], (int)$r['grants_as_pi'],
                       (int)$r['current_hindex'], (int)$r['total_citations'],
                       $r['total_income_rm'] ? number_format((float)$r['total_income_rm'],2) : '0.00'];
        }
        break;
}

// ── Save report record to DB ──────────────────────────────
$db->prepare(
    "INSERT INTO Tbl_Report (report_type, report_year, faculty_filter, format, admin_id)
     VALUES (?,?,?,?,?)"
)->execute([
    $title,
    $year === 'all' ? null : (int)$year,
    $facultyId === 'all' ? null : $facultyId,
    $format,
    $adminId
]);

// ── Audit log ─────────────────────────────────────────────
$db->prepare("INSERT INTO Tbl_Audit_Log (user_id, action, target_type, details) VALUES (?,?,?,?)")
   ->execute([$user['user_id'], 'Generated Report', 'Report',
              "type=$type year=$year faculty=$facultyId format=$format"]);

// ── Generate file content ─────────────────────────────────
$yearLabel = $year === 'all' ? 'All Years' : $year;
$filename  = strtolower(str_replace(' ', '_', $title)) . '_' . $yearLabel . '_' . date('Ymd');

if ($format === 'CSV') {
    // ── CSV output ────────────────────────────────────────
    $csv = '';
    $csv .= implode(',', array_map(fn($c) => '"' . str_replace('"','""',$c) . '"', $cols)) . "\n";
    foreach ($rows as $row) {
        $csv .= implode(',', array_map(fn($c) => '"' . str_replace('"','""', (string)$c) . '"', $row)) . "\n";
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;

} elseif ($format === 'Excel') {
    // ── Excel XML (opens in Excel) ────────────────────────
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
    $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    $xml .= '<Worksheet ss:Name="' . htmlspecialchars($title) . '">' . "\n";
    $xml .= '<Table>' . "\n";

    // Title row
    $xml .= '<Row><Cell ss:MergeAcross="' . (count($cols)-1) . '">';
    $xml .= '<Data ss:Type="String">UTHM ARAMS — ' . htmlspecialchars($title) . ' (' . $yearLabel . ')</Data></Cell></Row>' . "\n";
    $xml .= '<Row><Cell><Data ss:Type="String">Generated: ' . date('d M Y H:i') . '</Data></Cell></Row>' . "\n";
    $xml .= '<Row/>' . "\n";

    // Header row
    $xml .= '<Row>';
    foreach ($cols as $col) {
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($col) . '</Data></Cell>';
    }
    $xml .= '</Row>' . "\n";

    // Data rows
    foreach ($rows as $row) {
        $xml .= '<Row>';
        foreach ($row as $val) {
            $type_cell = is_numeric($val) && $val !== '' ? 'Number' : 'String';
            $xml .= '<Cell><Data ss:Type="' . $type_cell . '">' . htmlspecialchars((string)$val) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";
    }

    $xml .= '</Table></Worksheet></Workbook>';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Content-Length: ' . strlen($xml));
    echo $xml;
    exit;

} else {
    // ── PDF — generate printable HTML ────────────────────
    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<title>' . htmlspecialchars($title) . '</title>';
    $html .= '<style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
        h1 { font-size: 16px; color: #0B3C5D; margin-bottom: 4px; }
        .meta { font-size: 11px; color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0B3C5D; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .footer { margin-top: 20px; font-size: 10px; color: #94a3b8; text-align: center; }
        @media print { body { margin: 10px; } }
    </style></head><body>';
    $html .= '<h1>UTHM ARAMS — ' . htmlspecialchars($title) . '</h1>';
    $html .= '<div class="meta">Year: ' . $yearLabel . ' &nbsp;|&nbsp; Generated: ' . date('d M Y H:i') . ' &nbsp;|&nbsp; Total records: ' . count($rows) . '</div>';
    $html .= '<table><thead><tr>';
    foreach ($cols as $col) {
        $html .= '<th>' . htmlspecialchars($col) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $val) {
            $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<div class="footer">UTHM Academic Research Analytics and Monitoring System (ARAMS) &copy; ' . date('Y') . '</div>';
    $html .= '<script>window.onload = function(){ window.print(); }</script>';
    $html .= '</body></html>';

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '.html"');
    echo $html;
    exit;
}