<?php
// ============================================================
//  ARAMS — SMTP test (TEMPORARY). Delete this file after testing.
//  Usage:  http://localhost/arams/test_mail.php?to=youremail@gmail.com
// ============================================================

require_once __DIR__ . '/includes/mailer.php';

$to = $_GET['to'] ?? '';
header('Content-Type: text/html; charset=utf-8');

if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo '<p style="font-family:Arial">Add a valid recipient, e.g. '
       . '<code>test_mail.php?to=you@gmail.com</code></p>';
    exit;
}

$res = aramsSendOtp($to, '123456');   // sends a sample code

if ($res['success']) {
    echo '<p style="font-family:Arial;color:#0d9488;font-size:16px">'
       . '✅ Sent! Check the inbox of <strong>' . htmlspecialchars($to) . '</strong> '
       . '(also the Spam folder).</p>'
       . '<p style="font-family:Arial;color:#64748b">SMTP is working. You can delete test_mail.php now.</p>';
} else {
    echo '<p style="font-family:Arial;color:#b91c1c;font-size:16px">❌ Failed to send.</p>'
       . '<pre style="font-family:Consolas;background:#f8fafc;border:1px solid #e2e8f0;padding:12px;'
       . 'border-radius:8px;white-space:pre-wrap">' . htmlspecialchars($res['error']) . '</pre>'
       . '<p style="font-family:Arial;color:#64748b">Common fixes: make sure the App Password in '
       . '<code>config/mail.php</code> is correct, 2-Step Verification is ON, and the PHP '
       . '<code>openssl</code> extension is enabled in XAMPP.</p>';
}