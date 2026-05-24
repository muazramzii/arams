<?php
// ============================================================
//  ARAMS — API: Analytics Drill-Down Detail
//  Returns records matching a clicked chart segment.
//  Used by both Admin and TDPP analytics pages.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$user    = currentUser();
$role    = $_SESSION['role'] ?? '';
$db      = getDB();

// Determine scope
$facId = 0;     // 0 = all faculties (admin)
$lecId = 0;     // 0 = not a single lecturer
if ($role === 'TDPP') {
    $t = $db->prepare("SELECT faculty_id FROM Tbl_TDPP WHERE user_id=?");
    $t->execute([$user['user_id']]);
    $facId = (int)$t->fetchColumn();
} elseif ($role === 'Lecturer') {
    $lecId = (int)($user['lecturer_id'] ?? 0);
}

$type  = $_GET['type']  ?? '';   // year | quartile | pubtype | grantcat | grantrole
$value = $_GET['value'] ?? '';

$rows  = [];
$title = '';
$kind  = 'publication'; // or 'grant'

// Helper to add scope filter
function scopeJoin($facId, $lecId) {
    if ($lecId > 0) return [" AND rd.lecturer_id = ?", [$lecId]];
    if ($facId > 0) return [" AND l.faculty_id = ?", [$facId]];
    return ["", []];
}
[$scopeSql, $scopeParams] = scopeJoin($facId, $lecId);

switch ($type) {
    case 'year':
        $title = "Publications in $value";
        $sql = "SELECT p.title, p.authors, p.journal_name, p.pub_year, p.pub_type,
                       p.indexing_type, p.quartile, rd.status
                FROM Tbl_Publication p
                JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
                JOIN Tbl_Lecturer l ON l.lecturer_id=rd.lecturer_id
                WHERE rd.status='Approved' AND p.pub_year = ?$scopeSql
                ORDER BY p.title";
        $params = array_merge([(int)$value], $scopeParams);
        break;

    case 'quartile':
        $title = "Publications — Quartile $value";
        $sql = "SELECT p.title, p.authors, p.journal_name, p.pub_year, p.pub_type,
                       p.indexing_type, p.quartile, rd.status
                FROM Tbl_Publication p
                JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
                JOIN Tbl_Lecturer l ON l.lecturer_id=rd.lecturer_id
                WHERE rd.status='Approved' AND p.quartile = ?$scopeSql
                ORDER BY p.pub_year DESC";
        $params = array_merge([$value], $scopeParams);
        break;

    case 'pubtype':
        $title = "Publications — $value";
        $sql = "SELECT p.title, p.authors, p.journal_name, p.pub_year, p.pub_type,
                       p.indexing_type, p.quartile, rd.status
                FROM Tbl_Publication p
                JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
                JOIN Tbl_Lecturer l ON l.lecturer_id=rd.lecturer_id
                WHERE rd.status='Approved' AND p.pub_type = ?$scopeSql
                ORDER BY p.pub_year DESC";
        $params = array_merge([$value], $scopeParams);
        break;

    case 'grantcat':
        $kind = 'grant';
        $title = "Grants — $value";
        $sql = "SELECT g.grant_title, g.grant_code, g.funder, g.grant_category,
                       g.grant_level, g.role, g.amount, g.status
                FROM Tbl_Grant g
                JOIN Tbl_Research_Data rd ON g.data_id=rd.data_id
                JOIN Tbl_Lecturer l ON l.lecturer_id=rd.lecturer_id
                WHERE rd.status='Approved' AND g.grant_category = ?$scopeSql
                ORDER BY g.grant_title";
        $params = array_merge([$value], $scopeParams);
        break;

    case 'grantrole':
        $kind = 'grant';
        $title = "Grants — Role: $value";
        $sql = "SELECT g.grant_title, g.grant_code, g.funder, g.grant_category,
                       g.grant_level, g.role, g.amount, g.status
                FROM Tbl_Grant g
                JOIN Tbl_Research_Data rd ON g.data_id=rd.data_id
                JOIN Tbl_Lecturer l ON l.lecturer_id=rd.lecturer_id
                WHERE rd.status='Approved' AND g.role = ?$scopeSql
                ORDER BY g.grant_title";
        $params = array_merge([$value], $scopeParams);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Invalid filter']); exit;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'title'   => $title,
    'kind'    => $kind,
    'count'   => count($rows),
    'rows'    => $rows,
]);