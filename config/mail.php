
<?php
// ============================================================
//  ARAMS — Mail (SMTP) Configuration
//  Universiti Tun Hussein Onn Malaysia (UTHM)
// ------------------------------------------------------------
//  Uses Gmail SMTP + an App Password.
//  This is NOT Google Cloud and NOT your normal Gmail password.
//
//  How to get the App Password (one time):
//    1) Sign in to arams.uthm@gmail.com
//    2) Turn ON 2-Step Verification
//       (myaccount.google.com -> Security -> 2-Step Verification)
//    3) Then open "App passwords", generate a 16-character code
//    4) Paste those 16 characters into MAIL_PASSWORD below
//       (spaces are fine, e.g. 'abcd efgh ijkl mnop')
// ============================================================

define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);                    // 587 = TLS (recommended). Use 465 for SSL.
define('MAIL_SECURE',    'tls');                  // 'tls' for port 587, 'ssl' for port 465
define('MAIL_USERNAME',  'arams.uthm@gmail.com'); // the ARAMS Gmail account
define('MAIL_PASSWORD',  'zlgn sdfd qxgw nwxb'); // <-- App Password goes here
define('MAIL_FROM',      'arams.uthm@gmail.com'); // shown as the sender
define('MAIL_FROM_NAME', 'ARAMS UTHM');

// On localhost (XAMPP) Gmail's TLS certificate often can't be verified,
// which makes sending fail. Keep TRUE for localhost demo; set FALSE in production.
define('MAIL_ALLOW_INSECURE', true);

// Set to 2 or 3 temporarily if you need to see why sending fails (verbose log).
define('MAIL_DEBUG', 0);