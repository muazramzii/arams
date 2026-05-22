<?php
// ============================================================
//  ARAMS — Admin Dashboard
// ============================================================
$pageTitle  = 'Admin Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Institution-wide KPIs
$totals = $db->query(
    "SELECT
        (SELECT COUNT(*) FROM Tbl_Publication p JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id WHERE rd.status='Approved') AS total_pubs,
        (SELECT COUNT(*) FROM Tbl_Grant g JOIN Tbl_Research_Data rd ON g.data_id=rd.data_id WHERE rd.status='Approved') AS total_grants,
        (SELECT AVG(h.hindex_value) FROM Tbl_HIndex h JOIN Tbl_Research_Data rd ON h.data_id=rd.data_id WHERE rd.status='Approved') AS avg_hindex,
        (SELECT SUM(inc.amount) FROM Tbl_Research_Income inc JOIN Tbl_Research_Data rd ON inc.data_id=rd.data_id WHERE rd.status='Approved') AS total_income,
        (SELECT COUNT(*) FROM Tbl_Lecturer) AS total_lecturers,
        (SELECT COUNT(*) FROM Tbl_Research_Data WHERE status='Pending') AS pending_count"
)->fetch();

// Top 5 lecturers
$top5 = $db->query(
    "SELECT * FROM vw_lecturer_kpi ORDER BY total_publications DESC LIMIT 5"
)->fetchAll();

// Pending validations
$pending = $db->query("SELECT * FROM vw_pending_validation LIMIT 6")->fetchAll();

// Status distribution
$statusDist = $db->query(
    "SELECT status, COUNT(*) AS cnt FROM Tbl_Research_Data GROUP BY status"
)->fetchAll();
$distMap = array_column($statusDist, 'cnt', 'status');

// Submission status counts for premium card layout
$approvedCount = (int)($distMap['Approved'] ?? 0);
$pendingCount  = (int)($distMap['Pending']  ?? 0);
$rejectedCount = (int)($distMap['Rejected'] ?? 0);
$totalSubmissions = $approvedCount + $pendingCount + $rejectedCount;
$approvalRate     = $totalSubmissions > 0 ? round($approvedCount / $totalSubmissions * 100) : 0;

// Publications by year (last 6 years)
$pubTrend = $db->query(
    "SELECT p.pub_year, COUNT(*) AS cnt
     FROM Tbl_Publication p
     JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
     WHERE rd.status='Approved' AND p.pub_year >= YEAR(NOW())-5
     GROUP BY p.pub_year ORDER BY p.pub_year"
)->fetchAll();

$rankStyle = ['🥇','🥈','🥉','4','5'];
?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card bg-blue">
        <i class="fas fa-file-alt"></i>
        <div class="kpi-val"><?= number_format((int)$totals['total_pubs']) ?></div>
        <div class="kpi-label">Total Publications</div>
    </div>
    <div class="kpi-card bg-purple">
        <i class="fas fa-trophy"></i>
        <div class="kpi-val"><?= number_format((int)$totals['total_grants']) ?></div>
        <div class="kpi-label">Total Grants</div>
    </div>
    <div class="kpi-card bg-teal">
        <i class="fas fa-chart-line"></i>
        <div class="kpi-val"><?= number_format((float)($totals['avg_hindex'] ?? 0), 1) ?></div>
        <div class="kpi-label">Avg H-Index</div>
        <div class="kpi-chg"><?= (int)$totals['total_lecturers'] ?> active lecturers</div>
    </div>
    <div class="kpi-card bg-green">
        <i class="fas fa-dollar-sign"></i>
        <div class="kpi-val">RM <?= number_format((float)($totals['total_income'] ?? 0)/1000, 0) ?>K</div>
        <div class="kpi-label">Total Research Income</div>
    </div>
</div>

<div class="grid-2-1" style="margin-bottom:1rem">
    <!-- Top Lecturers -->
    <div class="card">
        <div class="card-title" style="justify-content:space-between">
            <span><i class="fas fa-trophy" style="color:#f59e0b"></i> Top Lecturers Ranking</span>
            <a href="/arams/pages/admin/lecturers.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-wrap">
            <table class="arams-table">
                <thead><tr><th>#</th><th>Name</th><th>Faculty</th><th>Pubs</th><th>H-Index</th></tr></thead>
                <tbody>
                <?php foreach ($top5 as $i => $l): ?>
                <tr>
                    <td style="font-size:18px"><?= $rankStyle[$i] ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($l['full_name']) ?></td>
                    <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['faculty_code']) ?></td>
                    <td><?= (int)$l['total_publications'] ?></td>
                    <td><?= (int)$l['current_hindex'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── PREMIUM Submission Status Card ──────────────────── -->
    <div class="card" style="display:flex;flex-direction:column">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:var(--teal)"></i>
            Submission Status
        </div>

        <!-- Donut chart -->
        <div id="statusDonut" style="display:flex;justify-content:center;margin:.5rem 0"></div>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            renderDonut('statusDonut', [
                { label:'Approved', value:<?= $approvedCount ?>, color:'#22c55e' },
                { label:'Pending',  value:<?= $pendingCount  ?>, color:'#f59e0b' },
                { label:'Rejected', value:<?= $rejectedCount ?>, color:'#ef4444' },
            ]);
        });
        </script>

        <!-- Stat tiles -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;padding:0 .5rem;margin-top:.75rem">
            <div style="text-align:center;padding:.6rem;
                        background:rgba(34,197,94,.08);
                        border-radius:8px;
                        border:1px solid rgba(34,197,94,.2)">
                <div style="font-size:18px;font-weight:700;color:#16a34a"><?= $approvedCount ?></div>
                <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.5px">
                    Approved
                </div>
            </div>
            <div style="text-align:center;padding:.6rem;
                        background:rgba(251,146,60,.08);
                        border-radius:8px;
                        border:1px solid rgba(251,146,60,.2)">
                <div style="font-size:18px;font-weight:700;color:#ea580c"><?= $pendingCount ?></div>
                <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.5px">
                    Pending
                </div>
            </div>
            <div style="text-align:center;padding:.6rem;
                        background:rgba(239,68,68,.08);
                        border-radius:8px;
                        border:1px solid rgba(239,68,68,.2)">
                <div style="font-size:18px;font-weight:700;color:#dc2626"><?= $rejectedCount ?></div>
                <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.5px">
                    Rejected
                </div>
            </div>
        </div>

        <!-- Approval Rate banner -->
        <div style="margin-top:.75rem;padding:.75rem 1rem;
                    background:linear-gradient(90deg,rgba(27,153,139,.12),rgba(11,60,93,.06));
                    border-radius:8px;
                    display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:11px;color:#64748b;
                         text-transform:uppercase;letter-spacing:.5px;font-weight:600">
                Approval Rate
            </span>
            <span style="font-size:18px;font-weight:700;color:var(--teal)">
                <?= $approvalRate ?>%
            </span>
        </div>
    </div>
</div>

<!-- ── Publications Trend with year-by-year colors ──────────── -->
<div class="card" style="margin-bottom:1rem">
    <div class="card-title">
        <i class="fas fa-chart-bar" style="color:var(--blue)"></i>
        Publication Trend (Last 6 Years)
    </div>
    <div class="bar-chart" style="height:160px">
        <?php
        // UTHM premium gradient — navy → teal progression
        $yearGradients = [
            'linear-gradient(180deg, #0B3C5D 0%, #1B5A8A 100%)', // 2021 — UTHM navy
            'linear-gradient(180deg, #144D6E 0%, #246A99 100%)', // 2022
            'linear-gradient(180deg, #1A6B7E 0%, #2A8AA3 100%)', // 2023
            'linear-gradient(180deg, #1B998B 0%, #2BB8A8 100%)', // 2024 — UTHM teal
            'linear-gradient(180deg, #2B8B7E 0%, #3FAB9C 100%)', // 2025
            'linear-gradient(180deg, #3FAB9C 0%, #5FCEC0 100%)', // 2026 — light mint
        ];
        $maxPub = max(array_column($pubTrend, 'cnt') ?: [1]);
        foreach ($pubTrend as $i => $row):
            $pct  = round(($row['cnt'] / $maxPub) * 100);
            $grad = $yearGradients[$i % count($yearGradients)];
        ?>
        <div class="bar-col">
            <div class="bar-val"><?= $row['cnt'] ?></div>
            <div class="bar" style="height:<?= $pct ?>%;background:<?= $grad ?>"></div>
            <div class="bar-label"><?= $row['pub_year'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pending Validations -->
<div class="card">
    <div class="card-title" style="justify-content:space-between">
        <span>
            <i class="fas fa-clock" style="color:var(--amber)"></i>
            Pending Validations
            <?php if ($totals['pending_count'] > 0): ?>
            <span class="badge badge-yellow" style="margin-left:6px"><?= (int)$totals['pending_count'] ?></span>
            <?php endif; ?>
        </span>
        <a href="/arams/pages/admin/validation.php" class="btn btn-primary btn-sm">View All →</a>
    </div>
    <div class="table-wrap">
        <table class="arams-table">
            <thead><tr><th>Lecturer</th><th>Faculty</th><th>Type</th><th>Title</th><th>Submitted</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $p): ?>
            <tr id="prow-<?= $p['data_id'] ?>">
                <td style="font-weight:600;font-size:13px"><?= htmlspecialchars($p['lecturer_name']) ?></td>
                <td><span class="badge badge-grey"><?= htmlspecialchars($p['faculty_code']) ?></span></td>
                <td><span class="badge badge-blue"><?= htmlspecialchars($p['record_type']) ?></span></td>
                <td style="font-size:13px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars($p['record_title'] ?? '') ?>">
                    <?= htmlspecialchars(substr($p['record_title'] ?? '', 0, 50)) ?><?= strlen($p['record_title']??'') > 50 ? '…' : '' ?>
                </td>
                <td style="font-size:12px;color:var(--muted)"><?= $p['submission_date'] ?></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-success btn-sm"
                                onclick="approveRecord(<?= $p['data_id'] ?>, '/arams/api/validate.php', document.getElementById('prow-<?= $p['data_id'] ?>'))">
                            Approve
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="rejectRecord(<?= $p['data_id'] ?>, '/arams/api/validate.php', document.getElementById('prow-<?= $p['data_id'] ?>'))">
                            Reject
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pending)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">
                <i class="fas fa-check-circle" style="color:var(--green);font-size:24px;display:block;margin-bottom:8px"></i>
                All submissions are reviewed. No pending validations.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>