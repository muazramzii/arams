<?php
// ============================================================
//  ARAMS — Submit Research Data API (Lecturer)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Lecturer');

header('Content-Type: application/json');
$user      = currentUser();
$lecId     = (int)$user['lecturer_id'];
$type      = $_POST['type'] ?? ''; // publication|grant|hindex|ip|income

if (!$lecId || !$type) jsonResponse(false, 'Missing required fields.');

$db = getDB();
$db->beginTransaction();
try {
    // 1. Insert parent Research_Data row
    $db->prepare("INSERT INTO tbl_research_data (submission_date, status, lecturer_id)
                  VALUES (CURDATE(), 'Pending', ?)")
       ->execute([$lecId]);
    $dataId = (int)$db->lastInsertId();

    // 2. Insert specific record
    switch ($type) {
        case 'publication':
            $st = $db->prepare(
                "INSERT INTO tbl_publication
                 (title, authors, author_role, student_author, journal_name, country, issn, pub_year,
                  volume, issue, pages, pub_type, indexing_type, quartile, impact_factor,
                  doi, url, national_collaboration, international_collaboration,
                  industries_collaboration, data_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                sanitize($_POST['title']                    ?? ''),
                sanitize($_POST['authors']                  ?? ''),
                $_POST['author_role']                       ?? 'Co-Author',
                (int)($_POST['student_author']              ?? 0),
                sanitize($_POST['journal_name']             ?? ''),
                sanitize($_POST['country']                  ?? ''),
                sanitize($_POST['issn']                     ?? ''),
                (int)($_POST['pub_year']                    ?? date('Y')),
                sanitize($_POST['volume']                   ?? ''),
                sanitize($_POST['issue']                    ?? ''),
                sanitize($_POST['pages']                    ?? ''),
                $_POST['pub_type']                          ?? 'Journal',
                $_POST['indexing_type']                     ?? 'Others',
                $_POST['quartile']                          ?? 'N/A',
                !empty($_POST['impact_factor'])             ? (float)$_POST['impact_factor'] : null,
                sanitize($_POST['doi']                      ?? ''),
                sanitize($_POST['url']                      ?? ''),
                (int)($_POST['national_collaboration']      ?? 0),
                (int)($_POST['international_collaboration'] ?? 0),
                (int)($_POST['industries_collaboration']    ?? 0),
                $dataId
            ]);
            break;

        case 'grant':
            $st = $db->prepare(
                "INSERT INTO tbl_grant
                 (grant_title, grant_code, funder, grant_category, grant_level,
                  role, amount, start_date, end_date, status, mygrants_id, data_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                sanitize($_POST['grant_title']   ?? ''),
                sanitize($_POST['grant_code']    ?? ''),
                sanitize($_POST['funder']        ?? ''),
                $_POST['grant_category']         ?? 'Others',
                $_POST['grant_level']            ?? null,
                $_POST['role']                   ?? 'Member',
                !empty($_POST['amount'])         ? (float)$_POST['amount'] : null,
                $_POST['start_date']             ?? null,
                $_POST['end_date']               ?? null,
                $_POST['grant_status']           ?? 'Active',
                sanitize($_POST['mygrants_id']   ?? ''),
                $dataId
            ]);
            break;

        case 'hindex':
            $st = $db->prepare(
                "INSERT INTO tbl_hindex (record_year, hindex_value, citation_count, source, data_id)
                 VALUES (?,?,?,?,?)"
            );
            $st->execute([
                (int)($_POST['record_year']    ?? date('Y')),
                (int)($_POST['hindex_value']   ?? 0),
                !empty($_POST['citation_count']) ? (int)$_POST['citation_count'] : null,
                $_POST['source']               ?? 'Scopus',
                $dataId
            ]);
            break;

        case 'ip':
            $st = $db->prepare(
                "INSERT INTO tbl_ip_record
                 (ip_title, ip_type, ip_number, inventors, country, filing_date, grant_date, registration_status, data_id)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                sanitize($_POST['ip_title']           ?? ''),
                $_POST['ip_type']                     ?? 'Patent',
                sanitize($_POST['ip_number']          ?? ''),
                sanitize($_POST['inventors']          ?? ''),
                sanitize($_POST['country']            ?? 'Malaysia'),
                $_POST['filing_date']                 ?? null,
                !empty($_POST['grant_date']) ? $_POST['grant_date'] : null,
                $_POST['registration_status']         ?? 'Filed',
                $dataId
            ]);
            break;

        case 'income':
            $st = $db->prepare(
                "INSERT INTO tbl_research_income
                 (source, income_category, amount, year_received, data_id)
                 VALUES (?,?,?,?,?)"
            );
            $st->execute([
                sanitize($_POST['source']         ?? ''),
                $_POST['income_category']         ?? 'Research Grant',
                (float)($_POST['amount']          ?? 0),
                (int)($_POST['year_received']     ?? date('Y')),
                $dataId
            ]);
            break;

        default:
            throw new Exception('Invalid record type: ' . $type);
    }

    // Notify admin
    $db->prepare("INSERT INTO tbl_notification (user_id, message, data_id)
                  SELECT u.user_id, CONCAT('New ', ?, ' submission pending validation from ', l.full_name), ?
                  FROM tbl_admin a JOIN tbl_user u ON u.user_id = a.user_id
                  JOIN tbl_lecturer l ON l.lecturer_id = ?")
       ->execute([ucfirst($type), $dataId, $lecId]);

    $db->commit();
    jsonResponse(true, 'Submitted successfully. Pending admin validation.', ['data_id' => $dataId]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Submission failed: ' . $e->getMessage());
}