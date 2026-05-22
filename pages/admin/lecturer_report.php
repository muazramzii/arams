<?php
// ============================================================
//  ARAMS — Individual Lecturer Performance Report
// ============================================================
$pageTitle  = 'Lecturer Report';
$activePage = 'reports';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Fetch ALL faculties for filter dropdown
$faculties = $db->query(
    "SELECT faculty_id, faculty_code, faculty_name
     FROM Tbl_Faculty ORDER BY faculty_code"
)->fetchAll();

// Fetch ALL lecturers with faculty_id for JS filtering
$lecturers = $db->query(
    "SELECT l.lecturer_id, l.full_name, l.staff_no, l.position, l.grade,
            l.faculty_id,
            f.faculty_name, f.faculty_code, u.email
     FROM Tbl_Lecturer l
     JOIN Tbl_Faculty f ON f.faculty_id = l.faculty_id
     JOIN Tbl_User   u ON u.user_id    = l.user_id
     ORDER BY l.full_name"
)->fetchAll();

// Selected lecturer
$selectedId = (int)($_GET['lecturer_id'] ?? 0);
$lec = $kpi = null;
$pubs = $grants = $hindexes = $incomes = $awards = [];

if ($selectedId) {
    $st = $db->prepare(
        "SELECT l.*, f.faculty_name, f.faculty_code, u.email
         FROM Tbl_Lecturer l
         JOIN Tbl_Faculty f ON f.faculty_id = l.faculty_id
         JOIN Tbl_User   u ON u.user_id    = l.user_id
         WHERE l.lecturer_id = ?"
    );
    $st->execute([$selectedId]); $lec = $st->fetch();

    $st = $db->prepare("SELECT * FROM vw_lecturer_kpi WHERE lecturer_id = ?");
    $st->execute([$selectedId]); $kpi = $st->fetch() ?: [];

    $st = $db->prepare(
        "SELECT p.*, rd.submission_date FROM Tbl_Publication p
         JOIN Tbl_Research_Data rd ON p.data_id = rd.data_id
         WHERE rd.lecturer_id = ? AND rd.status = 'Approved'
         ORDER BY p.pub_year DESC"
    );
    $st->execute([$selectedId]); $pubs = $st->fetchAll();

    $st = $db->prepare(
        "SELECT g.* FROM Tbl_Grant g
         JOIN Tbl_Research_Data rd ON g.data_id = rd.data_id
         WHERE rd.lecturer_id = ? AND rd.status = 'Approved'
         ORDER BY g.start_date DESC"
    );
    $st->execute([$selectedId]); $grants = $st->fetchAll();

    $st = $db->prepare(
        "SELECT h.* FROM Tbl_HIndex h
         JOIN Tbl_Research_Data rd ON h.data_id = rd.data_id
         WHERE rd.lecturer_id = ? AND rd.status = 'Approved'
         ORDER BY h.record_year DESC"
    );
    $st->execute([$selectedId]); $hindexes = $st->fetchAll();

    $st = $db->prepare(
        "SELECT i.* FROM Tbl_Research_Income i
         JOIN Tbl_Research_Data rd ON i.data_id = rd.data_id
         WHERE rd.lecturer_id = ? AND rd.status = 'Approved'
         ORDER BY i.year_received DESC"
    );
    $st->execute([$selectedId]); $incomes = $st->fetchAll();

    $st = $db->prepare(
        "SELECT * FROM Tbl_Award WHERE lecturer_id = ? ORDER BY award_year DESC"
    );
    $st->execute([$selectedId]); $awards = $st->fetchAll();
}
?>

<!-- Buttons row -->
<div style="margin-bottom:1rem;display:flex;align-items:center;gap:1rem" class="no-print">
    <a href="/arams/pages/admin/reports.php" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Reports
    </a>
    <?php if ($lec): ?>
    <button class="btn btn-teal btn-sm" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
    </button>
    <?php endif; ?>
</div>

<!-- ── SELECTOR CARD — Badge faculty filter ──────────────── -->
<div class="card no-print" style="margin-bottom:1rem">
    <div class="card-title">
        <i class="fas fa-user-graduate" style="color:var(--blue)"></i>
        Select Lecturer
    </div>

    <!-- Step 1: Faculty badge filter — click to filter instantly -->
    <div style="margin-bottom:1rem">
        <label class="form-label" style="margin-bottom:.5rem;display:block">
            Step 1 — Filter by Faculty
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
            <button type="button" class="fac-badge active"
                    onclick="selectFaculty(this, '')">All</button>
            <?php foreach ($faculties as $fac): ?>
            <button type="button" class="fac-badge"
                    onclick="selectFaculty(this, '<?= $fac['faculty_id'] ?>')">
                <?= htmlspecialchars($fac['faculty_code']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Step 2: Lecturer select -->
    <form method="GET" action="">
        <div class="form-group" style="margin-bottom:.75rem">
            <label class="form-label">Step 2 — Choose Lecturer</label>
            <select class="form-control" name="lecturer_id" id="lecturerSelect">
                <option value="">— Select a Lecturer —</option>
                <?php foreach ($lecturers as $l): ?>
                <option value="<?= $l['lecturer_id'] ?>"
                        data-fac="<?= (int)$l['faculty_id'] ?>"
                        <?= $selectedId === (int)$l['lecturer_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['full_name']) ?>
                    (<?= htmlspecialchars($l['faculty_code']) ?>)
                    <?= $l['grade'] ? '— ' . htmlspecialchars($l['grade']) : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-teal">
            <i class="fas fa-search"></i> Load Report
        </button>
    </form>
</div>

<style>
.fac-badge {
    padding:5px 14px; border-radius:20px;
    border:1px solid var(--border);
    background:var(--grey); color:var(--text);
    font-size:12px; font-weight:500;
    cursor:pointer; transition:.15s;
}
.fac-badge:hover { border-color:var(--teal); color:var(--teal); }
.fac-badge.active { background:var(--teal); color:#fff; border-color:var(--teal); }
</style>

<?php if (!$selectedId): ?>
<!-- Empty state -->
<div style="text-align:center;padding:4rem;color:var(--muted)">
    <i class="fas fa-user-graduate"
       style="font-size:48px;opacity:.3;margin-bottom:1rem;display:block"></i>
    <p style="font-size:15px">Select a lecturer above to generate their performance report.</p>
</div>

<?php elseif (!$lec): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> Lecturer not found.
</div>

<?php else:
$photo    = $lec['profile_photo'] ?? '';
$photoUrl = ($photo && file_exists(__DIR__ . '/../../assets/images/profiles/' . $photo))
            ? '/arams/assets/images/profiles/' . htmlspecialchars($photo) : '';
$initials = strtoupper(substr($lec['full_name'], 0, 2));
?>

<!-- ══════════════════════════════════════
     PRINTABLE REPORT
════════════════════════════════════════ -->
<div id="reportContent">

    <!-- Profile Header -->
    <div class="card" style="margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
            <!-- Photo -->
            <?php if ($photoUrl): ?>
            <img src="<?= $photoUrl ?>"
                 style="width:80px;height:80px;border-radius:50%;object-fit:cover;
                        border:3px solid var(--teal);flex-shrink:0">
            <?php else: ?>
            <div style="width:80px;height:80px;border-radius:50%;flex-shrink:0;
                        background:linear-gradient(135deg,var(--blue),var(--teal));
                        display:flex;align-items:center;justify-content:center;
                        font-size:28px;font-weight:700;color:#fff;
                        border:3px solid var(--teal)">
                <?= $initials ?>
            </div>
            <?php endif; ?>

            <!-- Info -->
            <div style="flex:1">
                <h2 style="margin:0 0 4px;font-size:20px">
                    <?= htmlspecialchars($lec['full_name']) ?>
                </h2>
                <div style="font-size:13px;color:var(--muted);margin-bottom:8px">
                    <?= htmlspecialchars($lec['position'] ?? 'Lecturer') ?>
                    <?= $lec['grade'] ? '(' . htmlspecialchars($lec['grade']) . ')' : '' ?>
                    — <?= htmlspecialchars($lec['faculty_name']) ?>
                </div>
                <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:13px">
                    <span>
                        <i class="fas fa-envelope" style="color:var(--teal);margin-right:5px"></i>
                        <?= htmlspecialchars($lec['email']) ?>
                    </span>
                    <span>
                        <i class="fas fa-id-badge" style="color:var(--blue);margin-right:5px"></i>
                        <?= htmlspecialchars($lec['staff_no']) ?>
                    </span>
                    <?php if ($lec['department']): ?>
                    <span>
                        <i class="fas fa-building" style="color:var(--muted);margin-right:5px"></i>
                        <?= htmlspecialchars($lec['department']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($lec['research_centre']): ?>
                    <span>
                        <i class="fas fa-flask" style="color:var(--muted);margin-right:5px"></i>
                        <?= htmlspecialchars($lec['research_centre']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($lec['scopus_id'] || $lec['orcid_id'] || $lec['lens_id']): ?>
                <div style="margin-top:8px;display:flex;gap:1rem;flex-wrap:wrap;
                            font-size:12px;color:var(--muted)">
                    <?php if ($lec['scopus_id']): ?>
                    <span>Scopus: <strong><?= htmlspecialchars($lec['scopus_id']) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($lec['orcid_id']): ?>
                    <span>ORCID: <strong><?= htmlspecialchars($lec['orcid_id']) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($lec['lens_id']): ?>
                    <span>Lens: <strong><?= htmlspecialchars($lec['lens_id']) ?></strong></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Generated date -->
            <div style="text-align:right;font-size:11px;color:var(--muted)">
                <div>Report Generated</div>
                <div style="font-weight:600;color:var(--text)"><?= date('d M Y, H:i') ?></div>
                <div style="margin-top:4px">UTHM ARAMS</div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid" style="margin-bottom:1rem">
        <div class="kpi-card bg-blue">
            <i class="fas fa-file-alt"></i>
            <div class="kpi-val"><?= (int)($kpi['total_publications'] ?? 0) ?></div>
            <div class="kpi-label">Total Publications</div>
            <div class="kpi-chg">Q1: <?= (int)($kpi['q1_pubs'] ?? 0) ?> &nbsp; Q2: <?= (int)($kpi['q2_pubs'] ?? 0) ?></div>
        </div>
        <div class="kpi-card bg-purple">
            <i class="fas fa-trophy"></i>
            <div class="kpi-val"><?= (int)($kpi['total_grants'] ?? 0) ?></div>
            <div class="kpi-label">Total Grants</div>
            <div class="kpi-chg"><?= (int)($kpi['grants_as_pi'] ?? 0) ?> as PI</div>
        </div>
        <div class="kpi-card bg-teal">
            <i class="fas fa-chart-line"></i>
            <div class="kpi-val"><?= (int)($kpi['current_hindex'] ?? 0) ?></div>
            <div class="kpi-label">H-Index (Scopus)</div>
            <div class="kpi-chg">Citations: <?= number_format((int)($kpi['total_citations'] ?? 0)) ?></div>
        </div>
        <div class="kpi-card bg-green">
            <i class="fas fa-dollar-sign"></i>
            <div class="kpi-val">RM <?= number_format((float)($kpi['total_income_rm'] ?? 0) / 1000, 0) ?>K</div>
            <div class="kpi-label">Research Income</div>
        </div>
    </div>

    <!-- Publications -->
    <?php if (!empty($pubs)): ?>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-title">
            <i class="fas fa-file-alt" style="color:var(--blue)"></i>
            Publications (<?= count($pubs) ?>)
        </div>
        <div style="overflow-x:auto">
        <table class="arams-table" style="min-width:600px">
            <thead>
                <tr><th>#</th><th>Title</th><th>Type</th><th>Indexing</th>
                    <th>Quartile</th><th>Year</th><th>Journal</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pubs as $i => $p): ?>
            <tr>
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td style="font-size:12px;max-width:280px">
                    <?= htmlspecialchars(substr($p['title'], 0, 90)) ?><?= strlen($p['title']) > 90 ? '…' : '' ?>
                    <?php if ($p['doi']): ?>
                    <div style="font-size:10px;color:var(--teal)">DOI: <?= htmlspecialchars($p['doi']) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($p['pub_type']) ?></span></td>
                <td><span class="badge badge-grey" style="font-size:10px"><?= htmlspecialchars($p['indexing_type']) ?></span></td>
                <td>
                    <span class="badge <?= $p['quartile']==='Q1'?'badge-blue':($p['quartile']==='Q2'?'badge-teal':'badge-grey') ?>"
                          style="font-size:10px"><?= $p['quartile'] ?></span>
                </td>
                <td style="font-weight:600"><?= $p['pub_year'] ?></td>
                <td style="font-size:11px;color:var(--muted)">
                    <?= htmlspecialchars(substr($p['journal_name'] ?? '', 0, 40)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grants -->
    <?php if (!empty($grants)): ?>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-title">
            <i class="fas fa-trophy" style="color:#8b5cf6"></i>
            Grants (<?= count($grants) ?>)
        </div>
        <div style="overflow-x:auto">
        <table class="arams-table" style="min-width:560px">
            <thead>
                <tr><th>#</th><th>Grant Title</th><th>Code</th>
                    <th>Category</th><th>Role</th><th>Amount (RM)</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($grants as $i => $g): ?>
            <tr>
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td style="font-size:12px;max-width:220px">
                    <?= htmlspecialchars(substr($g['grant_title'], 0, 70)) ?><?= strlen($g['grant_title']) > 70 ? '…' : '' ?>
                </td>
                <td style="font-size:11px;font-weight:600"><?= htmlspecialchars($g['grant_code'] ?? '—') ?></td>
                <td style="font-size:11px"><?= htmlspecialchars($g['grant_category'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $g['role']==='PI'?'badge-blue':'badge-grey' ?>"
                          style="font-size:10px"><?= htmlspecialchars($g['role']) ?></span>
                </td>
                <td style="font-weight:600;color:var(--green)">
                    <?= $g['amount'] ? 'RM ' . number_format((float)$g['amount']) : '—' ?>
                </td>
                <td>
                    <span class="badge <?= $g['status']==='Active'?'badge-green':'badge-grey' ?>"
                          style="font-size:10px"><?= $g['status'] ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- H-Index + Income -->
    <div class="grid-2" style="margin-bottom:1rem">
        <div class="card">
            <div class="card-title">
                <i class="fas fa-chart-line" style="color:var(--teal)"></i>
                H-Index History
            </div>
            <?php if (empty($hindexes)): ?>
            <p style="color:var(--muted);font-size:13px">No records.</p>
            <?php else: ?>
            <table class="arams-table">
                <thead><tr><th>Year</th><th>H-Index</th><th>Citations</th><th>Source</th></tr></thead>
                <tbody>
                <?php foreach ($hindexes as $h): ?>
                <tr>
                    <td><?= $h['record_year'] ?></td>
                    <td style="font-weight:700;font-size:16px;color:var(--blue)"><?= $h['hindex_value'] ?></td>
                    <td><?= $h['citation_count'] !== null ? number_format($h['citation_count']) : '—' ?></td>
                    <td><?= htmlspecialchars($h['source']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-dollar-sign" style="color:var(--green)"></i>
                Research Income
            </div>
            <?php if (empty($incomes)): ?>
            <p style="color:var(--muted);font-size:13px">No records.</p>
            <?php else: ?>
            <table class="arams-table">
                <thead><tr><th>Year</th><th>Category</th><th>Amount (RM)</th></tr></thead>
                <tbody>
                <?php foreach ($incomes as $inc): ?>
                <tr>
                    <td><?= $inc['year_received'] ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($inc['income_category'] ?? $inc['source'] ?? '—') ?></td>
                    <td style="font-weight:600;color:var(--green)">
                        RM <?= number_format((float)($inc['amount'] ?? 0)) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Awards -->
    <?php if (!empty($awards)): ?>
    <div class="card" style="margin-bottom:1rem">
        <div class="card-title">
            <i class="fas fa-medal" style="color:#f59e0b"></i>
            Awards & Recognition (<?= count($awards) ?>)
        </div>
        <table class="arams-table">
            <thead><tr><th>#</th><th>Award Name</th><th>Type</th><th>Level</th><th>Year</th><th>Organiser</th></tr></thead>
            <tbody>
            <?php foreach ($awards as $i => $aw): ?>
            <tr>
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td style="font-size:12px;font-weight:600"><?= htmlspecialchars($aw['award_name']) ?></td>
                <td style="font-size:11px"><?= htmlspecialchars($aw['award_type'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $aw['level']==='International'?'badge-blue':($aw['level']==='National'?'badge-teal':'badge-grey') ?>"
                          style="font-size:10px"><?= $aw['level'] ?></span>
                </td>
                <td><?= $aw['award_year'] ?></td>
                <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($aw['organiser'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div style="text-align:center;font-size:11px;color:var(--muted);
                padding:1rem;border-top:1px solid var(--border);margin-top:1rem">
        Academic Research Analytics and Monitoring System (ARAMS) —
        Universiti Tun Hussein Onn Malaysia &nbsp;|&nbsp;
        Generated on <?= date('d M Y \a\t H:i') ?>
    </div>

</div><!-- #reportContent -->
<?php endif; ?>

<!-- ── PRINT STYLES ───────────────────────────────────────── -->
<style>
@media print {
    .no-print, .sidebar, .topbar, .sidebar-toggle, .btn { display:none !important; }
    .main-wrap  { margin:0 !important; }
    .page-content { padding:0 !important; }
    body { font-size:11px; color:#000; }
    .card { box-shadow:none !important; border:1px solid #ddd !important; break-inside:avoid; }
    .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; }
    table { width:100%; border-collapse:collapse; font-size:10px; }
    th { background:#0B3C5D !important; color:#fff !important;
         -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    td, th { padding:4px 6px; border:1px solid #ddd; }
    .arams-table thead th { background:#0B3C5D !important; color:white !important; }
    a  { color:inherit; text-decoration:none; }
    .badge { border:1px solid #ccc; padding:1px 5px; border-radius:10px; font-size:9px; }
}
</style>

<!-- ── FACULTY FILTER JS ──────────────────────────────────── -->
<script>
var allLecturerOptions = [];

document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('lecturerSelect');
    allLecturerOptions = Array.from(sel.options).map(function(o) {
        return { value: o.value, text: o.text, fac: o.getAttribute('data-fac') };
    });
});

function selectFaculty(btn, facultyId) {
    document.querySelectorAll('.fac-badge').forEach(function(b) {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    var sel = document.getElementById('lecturerSelect');
    var cur = sel.value;
    sel.innerHTML = '';

    allLecturerOptions.forEach(function(opt) {
        if (opt.value === '' || !facultyId || opt.fac === String(facultyId)) {
            var o = new Option(opt.text, opt.value);
            if (opt.fac) o.setAttribute('data-fac', opt.fac);
            sel.appendChild(o);
        }
    });

    sel.value = Array.from(sel.options).some(function(o) {
        return o.value === cur;
    }) ? cur : '';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>