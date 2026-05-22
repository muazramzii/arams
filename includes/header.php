<?php
// ============================================================
//  ARAMS — Shared Page Header
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user        = currentUser();
$unreadCount = getUnreadNotifCount($user['user_id']);
$isAdmin     = ($user['role'] === 'Admin');
$initials    = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($user['name'])))));
$initials    = substr($initials, 0, 2);

// ── Load profile photo from DB ────────────────────────────
$sidebarPhoto = '';
if (!$isAdmin) {
    $photoSt = (getDB())->prepare(
        "SELECT profile_photo FROM Tbl_Lecturer WHERE user_id = ?"
    );
    $photoSt->execute([$user['user_id']]);
    $photoRow  = $photoSt->fetch();
    $photoFile = $photoRow['profile_photo'] ?? '';
    if ($photoFile) {
        $photoPath = __DIR__ . '/../assets/images/profiles/' . $photoFile;
        if (file_exists($photoPath)) {
            $sidebarPhoto = '/arams/assets/images/profiles/' . htmlspecialchars($photoFile);
            $_SESSION['profile_photo'] = $photoFile;
        }
    }
}

$lecNav = [
    ['id' => 'dashboard', 'label' => 'Dashboard',          'icon' => 'tachometer-alt', 'url' => '/arams/pages/lecturer/dashboard.php'],
    ['id' => 'profile',   'label' => 'My Profile',          'icon' => 'user-circle',    'url' => '/arams/pages/lecturer/profile.php'],
    ['id' => 'research',  'label' => 'Research Management', 'icon' => 'flask',          'url' => '/arams/pages/lecturer/research.php'],
    ['id' => 'timeline',  'label' => 'Timeline',            'icon' => 'history',        'url' => '/arams/pages/lecturer/timeline.php'],
    ['id' => 'analytics', 'label' => 'Analytics',           'icon' => 'chart-line',     'url' => '/arams/pages/lecturer/analytics.php'],
];
$admNav = [
    ['id' => 'dashboard',  'label' => 'Dashboard',       'icon' => 'tachometer-alt', 'url' => '/arams/pages/admin/dashboard.php'],
    ['id' => 'lecturers',  'label' => 'All Lecturers',   'icon' => 'users',          'url' => '/arams/pages/admin/lecturers.php'],
    ['id' => 'validation', 'label' => 'Validation',      'icon' => 'check-circle',   'url' => '/arams/pages/admin/validation.php'],
    ['id' => 'analytics',  'label' => 'Analytics',       'icon' => 'chart-line',     'url' => '/arams/pages/admin/analytics.php'],
    ['id' => 'reports',    'label' => 'Reports',         'icon' => 'file-alt',       'url' => '/arams/pages/admin/reports.php'],
    ['id' => 'users',      'label' => 'User Management', 'icon' => 'users-cog',      'url' => '/arams/pages/admin/users.php'],
];
$navItems   = $isAdmin ? $admNav : $lecNav;
$dashUrl    = $isAdmin ? '/arams/pages/admin/dashboard.php' : '/arams/pages/lecturer/dashboard.php';
$profileUrl = '/arams/pages/lecturer/profile.php';
$logoPath   = __DIR__ . '/../assets/images/uthm_logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'ARAMS') ?> — UTHM ARAMS</title>
    <link rel="stylesheet" href="/arams/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="app-shell">

<!-- ── SIDEBAR ───────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">

    <!-- ① LOGO — top, clickable to dashboard -->
    <a href="<?= $dashUrl ?>" style="text-decoration:none;color:inherit" title="Go to Dashboard">
        <div class="sidebar-logo" style="cursor:pointer">
            <div class="sidebar-logo-icon" style="background:transparent;padding:0;flex-shrink:0">
                <?php if (file_exists($logoPath)): ?>
                <img src="/arams/assets/images/uthm_logo.png"
                     alt="UTHM Logo"
                     style="width:38px;height:38px;object-fit:contain;border-radius:6px">
                <?php else: ?>
                <i class="fas fa-chart-bar"></i>
                <?php endif; ?>
            </div>
            <div class="sidebar-logo-text">
                <span class="sidebar-title">UTHM ARAMS</span>
                <span class="sidebar-subtitle"><?= $isAdmin ? 'Admin Panel' : 'Lecturer Portal' ?></span>
            </div>
        </div>
    </a>

    <!-- ② USER PROFILE — directly below logo, same original style -->
    <div class="sidebar-user" style="border-top:none;border-bottom:1px solid rgba(255,255,255,.1)">
        <div class="sidebar-user-row">
            <?php if ($sidebarPhoto): ?>
            <img src="<?= $sidebarPhoto ?>"
                 alt="Profile photo"
                 style="width:46px;height:46px;border-radius:50%;
                        object-fit:cover;flex-shrink:0;
                        border:2px solid rgba(255,255,255,.3)">
            <?php else: ?>
            <div class="sidebar-avatar"><?= $initials ?></div>
            <?php endif; ?>
            <div class="sidebar-user-info">
                <p><?= htmlspecialchars($user['name']) ?></p>
                <span><?= htmlspecialchars($user['email']) ?></span>
            </div>
        </div>
    </div>

    <!-- ③ NAV ITEMS — below profile, scrollable -->
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= $item['url'] ?>"
           class="nav-link <?= ($activePage ?? '') === $item['id'] ? 'active' : '' ?>">
            <i class="fas fa-<?= $item['icon'] ?> nav-icon"></i>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- ④ LOGOUT — very bottom, same original style -->
    <div class="sidebar-user" style="border-top:1px solid rgba(255,255,255,.1)">
        <a href="/arams/api/logout.php" class="sidebar-logout">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

</aside>

<!-- ── MAIN AREA ─────────────────────────────────────── -->
<div class="main-wrap">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h2 class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h2>
                <p class="topbar-sub">Welcome back, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <!-- Notifications -->
            <div class="notif-wrap" id="notifWrap">
                <button class="topbar-btn" onclick="toggleNotif()" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>Notifications</span>
                        <a href="/arams/api/mark_notif_read.php" class="notif-mark-all">Mark all read</a>
                    </div>
                    <div id="notif-list"><p class="notif-empty">Loading...</p></div>
                    <div class="notif-footer"><a href="#">View all</a></div>
                </div>
            </div>
            <!-- Topbar avatar -->
            <?php if ($sidebarPhoto): ?>
            <img src="<?= $sidebarPhoto ?>"
                 alt="Profile"
                 style="width:34px;height:34px;border-radius:50%;object-fit:cover;
                        border:2px solid var(--border);cursor:pointer"
                 onclick="window.location='<?= $profileUrl ?>'"
                 title="My Profile">
            <?php else: ?>
            <div class="topbar-avatar"
                 <?= !$isAdmin ? "onclick=\"window.location='{$profileUrl}'\" style=\"cursor:pointer\" title=\"My Profile\"" : '' ?>>
                <?= $initials ?>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Page Content -->
    <main class="page-content">