<?php
// ============================================================
//  ARAMS — Validate Research Data API (Admin only)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');

header('Content-Type: application/json');
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$dataId  = (int)($body['data_id'] ?? 0);
$action  = $body['action']  ?? ''; // 'approve' or 'reject'
$remarks = trim($body['remarks'] ?? '');

if (!$dataId || !in_array($action, ['approve','reject'])) {
    jsonResponse(false, 'Invalid request parameters.');
}

$user    = currentUser();
$adminId = $user['admin_id'];
$status  = $action === 'approve' ? 'Approved' : 'Rejected';

$db = getDB();

// Get the submission and lecturer user_id for notification
$st = $db->prepare("SELECT rd.lecturer_id, l.user_id AS lec_user_id
                    FROM Tbl_Research_Data rd
                    JOIN Tbl_Lecturer l ON l.lecturer_id = rd.lecturer_id
                    WHERE rd.data_id = ?");
$st->execute([$dataId]);
$row = $st->fetch();
if (!$row) jsonResponse(false, 'Record not found.');

// Update status
$db->prepare("UPDATE Tbl_Research_Data
              SET status = ?, remarks = ?, admin_id = ?, validated_at = NOW()
              WHERE data_id = ?")
   ->execute([$status, $remarks, $adminId, $dataId]);

// Log audit
$db->prepare("INSERT INTO Tbl_Audit_Log (user_id, action, target_id, target_type, details)
              VALUES (?, ?, ?, 'Research_Data', ?)")
   ->execute([$user['user_id'], ucfirst($action) . 'd Submission', $dataId,
              "data_id=$dataId status=$status" . ($remarks ? " remarks=$remarks" : '')]);

// Notify lecturer
$msg = $action === 'approve'
    ? 'Your research submission (ID: ' . $dataId . ') has been approved.'
    : 'Your research submission (ID: ' . $dataId . ') has been rejected.' . ($remarks ? ' Reason: ' . $remarks : '');

$db->prepare("INSERT INTO Tbl_Notification (user_id, message, data_id) VALUES (?, ?, ?)")
   ->execute([$row['lec_user_id'], $msg, $dataId]);

jsonResponse(true, 'Record ' . $status . ' successfully.');
