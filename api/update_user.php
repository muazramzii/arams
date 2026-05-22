<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('Admin');
header('Content-Type: application/json');
$userId  = (int)($_POST['user_id']      ?? 0);
$name    = sanitize($_POST['full_name'] ?? '');
$newPw   = $_POST['new_password']       ?? '';
if (!$userId || !$name) jsonResponse(false, 'Missing required fields.');
$db = getDB();
$db->prepare("UPDATE Tbl_Lecturer SET full_name=? WHERE user_id=?")->execute([$name, $userId]);
$db->prepare("UPDATE Tbl_Admin SET name=? WHERE user_id=?")->execute([$name, $userId]);
if ($newPw) {
    if (strlen($newPw) < 8) jsonResponse(false, 'Password must be at least 8 characters.');
    $db->prepare("UPDATE Tbl_User SET password=? WHERE user_id=?")
       ->execute([password_hash($newPw, PASSWORD_BCRYPT), $userId]);
}
jsonResponse(true, 'User updated successfully.');
