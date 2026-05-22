<?php
// ============================================================
//  ARAMS — Analytics (Lecturer + Admin shared)
// ============================================================
$pageTitle  = 'Institutional Analytics';
$activePage = 'analytics';
require_once __DIR__ . '/../../includes/header.php';

$db      = getDB();
$isAdmin = ($user['role'] === 'Admin');
$lecId   = (int)($user['lecturer_id'] ?? 0);

if ($isAdmin) {
    $kpiRow = $db->query(
        "SELECT SUM(total_publications) AS pubs, SUM(total_grants) AS grants,
                AVG(current_hindex) AS hindex, SUM(total_citations) AS citations
         FROM vw_lecturer_kpi"
    )->fetch();

    $pubTrend = $db->query(
        "SELECT p.pub_year AS yr, COUNT(*) AS cnt
         FROM Tbl_Publication p JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
         WHERE rd.status='Approved' AND p.pub_year >= YEAR(NOW())-5
         GROUP BY p.pub_year ORDER BY p.pub_year"
    )->fetchAll();

    $quartileDist = $db->query(
        "SELECT quartile, COUNT(*) AS cnt FROM Tbl_Publication p
         JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
         WHERE rd.status='Approved' GROUP BY quartile"
    )->fetchAll();

    $pubTypes = $db->query(
        "SELECT pub_type, COUNT(*) AS cnt FROM Tbl_Publication p
         JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
         WHERE rd.status='Approved' GROUP BY pub_type ORDER BY cnt DESC"
    )->fetchAll();

    $grantCats = $db->query(
        "SELECT grant_category, COUNT(*) AS cnt FROM Tbl_Grant g
         JOIN Tbl_Research_Data rd ON g.data_id=rd.data_id
         WHERE rd.status='Approved' GROUP BY grant_category ORDER BY cnt DESC"
    )->fetchAll();

    $grantRoles = $db->query(
        "SELECT role, COUNT(*) AS cnt FROM Tbl_Grant g
         JOIN Tbl_Research_Data rd ON g.data_id=rd.data_id
         WHERE rd.status='Approved' GROUP BY role ORDER BY cnt DESC"
    )->fetchAll();

    $facPerf = $db->query(
        "SELECT f.faculty_code, SUM(k.total_publications) AS pubs,
                SUM(k.total_grants) AS grants, AVG(k.current_hindex) AS hindex
         FROM vw_lecturer_kpi k
         JOIN Tbl_Lecturer l ON l.lecturer_id=k.lecturer_id
         JOIN Tbl_Faculty f ON f.faculty_id=l.faculty_id
         GROUP BY f.faculty_id ORDER BY pubs DESC LIMIT 8"
    )->fetchAll();

} else {
    $kpiRow = $db->prepare(
        "SELECT total_publications AS pubs, total_grants AS grants,
                current_hindex AS hindex, total_citations AS citations
         FROM vw_lecturer_kpi WHERE lecturer_id = ?"
    );
    $kpiRow->execute([$lecId]); $kpiRow = $kpiRow->fetch();

    $pubTrend = $db->prepare(
        "SELECT p.pub_year AS yr, COUNT(*) AS cnt
         FROM Tbl_Publication p JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved'
           AND p.pub_year >= YEAR(NOW())-5
         GROUP BY p.pub_year ORDER BY p.pub_year"
    );
    $pubTrend->execute([$lecId]); $pubTrend = $pubTrend->fetchAll();

    $quartileDist = $db->prepare(
        "SELECT quartile, COUNT(*) AS cnt FROM Tbl_Publication p
         JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved' GROUP BY quartile"
    );
    $quartileDist->execute([$lecId]); $quartileDist = $quartileDist->fetchAll();

    $pubTypes = $db->prepare(
        "SELECT pub_type, COUNT(*) AS cnt FROM Tbl_Publication p
         JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved'
         GROUP BY pub_type ORDER BY cnt DESC"
    );
    $pubTypes->execute([$lecId]); $pubTypes = $pubTypes->fetchAll();

    $grantCats = $db->prepare(
        "SELECT grant_category, COUNT(*) AS cnt FROM Tbl_Grant g
         JOIN Tbl_Research_Data rd ON g.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved'
         GROUP BY grant_category ORDER BY cnt DESC"
    );
    $grantCats->execute([$lecId]); $grantCats = $grantCats->fetchAll();

    $grantRoles = $db->prepare(
        "SELECT role, COUNT(*) AS cnt FROM Tbl_Grant g
         JOIN Tbl_Research_Data rd ON g.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved'
         GROUP BY role ORDER BY cnt DESC"
    );
    $grantRoles->execute([$lecId]); $grantRoles = $grantRoles->fetchAll();

    $facPerf = [];
}

// ── Pre-calculate all percentages in PHP ──────────────────
$qMap    = array_column($quartileDist, 'cnt', 'quartile');
$pubMax  = max(array_column($pubTrend, 'cnt') ?: [1]);
$typeMax = max(array_column($pubTypes, 'cnt') ?: [1]);
$facMax  = $facPerf ? max(array_column($facPerf, 'pubs') ?: [1]) : 1;

$barPcts  = [];
foreach ($pubTrend as $r) $barPcts[]  = round(($r['cnt'] / $pubMax)  * 100);
$typePcts = [];
foreach ($pubTypes as $r) $typePcts[] = round(($r['cnt'] / $typeMax) * 100);
$facPcts  = [];
foreach ($facPerf  as $r) $facPcts[]  = $facMax > 0 ? round(($r['pubs'] / $facMax) * 100) : 0;

// ── Colour palettes for donut charts ─────────────────────
$pubTypeColors  = ['#0B3C5D','#1B998B','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#22c55e','#ec4899'];
$grantCatColors = ['#0B3C5D','#1B998B','#3b82f6','#f59e0b','#8b5cf6','#ef4444','#22c55e'];
$grantRoleColors= ['#0B3C5D','#1B998B','#f59e0b'];
$quartileColors = ['#0B3C5D','#1B998B','#3b82f6','#8b5cf6','#e2e8f0'];
?>

<!-- Page Header -->
<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1><?= $isAdmin ? 'Institutional Analytics' : 'Personal Analytics' ?></h1>
        <p><?= $isAdmin ? 'System-wide research performance metrics' : 'Your research performance overview' ?></p>
    </div>
    <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-download"></i> Export Report
    </button>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card bg-blue">
        <i class="fas fa-file-alt"></i>
        <div class="kpi-val"><?= number_format((int)($kpiRow['pubs']     ?? 0)) ?></div>
        <div class="kpi-label">Total Publications</div>
    </div>
    <div class="kpi-card bg-purple">
        <i class="fas fa-trophy"></i>
        <div class="kpi-val"><?= number_format((int)($kpiRow['grants']   ?? 0)) ?></div>
        <div class="kpi-label">Total Grants</div>
    </div>
    <div class="kpi-card bg-teal">
        <i class="fas fa-chart-line"></i>
        <div class="kpi-val"><?= number_format((float)($kpiRow['hindex'] ?? 0), $isAdmin ? 1 : 0) ?></div>
        <div class="kpi-label"><?= $isAdmin ? 'Average' : 'Your' ?> H-Index</div>
    </div>
    <div class="kpi-card bg-green">
        <i class="fas fa-quote-left"></i>
        <div class="kpi-val"><?= number_format((int)($kpiRow['citations'] ?? 0)) ?></div>
        <div class="kpi-label">Total Citations</div>
    </div>
</div>

<!-- Row 1: Trend + Quartile -->
<div class="grid-2" style="margin-bottom:1rem">

    <!-- Publication Trend Bar Chart -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-bar" style="color:var(--blue)"></i>
            Publications by Year
        </div>
        <?php if (empty($pubTrend)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No approved publications yet.</p>
        <?php else: ?>
        <div class="bar-chart" style="height:160px">
            <?php foreach ($pubTrend as $i => $row):
                $barStyle = 'height:' . $barPcts[$i] . '%;background:linear-gradient(0deg,var(--blue),var(--blue-light))';
            ?>
            <div class="bar-col">
                <div class="bar-val"><?= $row['cnt'] ?></div>
                <div class="bar" style="<?= $barStyle ?>"></div>
                <div class="bar-label"><?= $row['yr'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="font-size:10px;color:var(--muted);text-align:center;margin-top:6px">
            Approved publications per year
        </div>
        <?php endif; ?>
    </div>

    <!-- Quartile Distribution Donut -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:var(--teal)"></i>
            Quartile Distribution
        </div>
        <div id="quartileDonut"></div>
    </div>
</div>

<!-- Row 2: Publication Type Donut + Grant Category Donut -->
<div class="grid-2" style="margin-bottom:1rem">

    <!-- Publication Type Donut — like UEMAS -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:#3b82f6"></i>
            Publication Types
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">
                (<?= array_sum(array_column($pubTypes,'cnt')) ?> total)
            </span>
        </div>
        <?php if (empty($pubTypes)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No data yet.</p>
        <?php else: ?>
        <div id="pubTypeDonut"></div>
        <?php endif; ?>
    </div>

    <!-- Grant Category Donut — like UEMAS -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-pie" style="color:#8b5cf6"></i>
            Grant Categories
            <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">
                (<?= array_sum(array_column($grantCats,'cnt')) ?> total)
            </span>
        </div>
        <?php if (empty($grantCats)): ?>
        <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No data yet.</p>
        <?php else: ?>
        <div id="grantCatDonut"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Row 3: Publication Type Breakdown bars + Grant Role breakdown -->
<div class="grid-2" style="margin-bottom:1rem">

    <!-- Publication breakdown bars -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-layer-group" style="color:var(--blue)"></i>
            Publication Breakdown
        </div>
        <?php if (empty($pubTypes)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: ?>
        <?php foreach ($pubTypes as $i => $pt):
            $widthStyle = 'width:' . $typePcts[$i] . '%';
            $col = $pubTypeColors[$i % count($pubTypeColors)];
        ?>
        <div style="margin-bottom:.75rem">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
                    <span><?= htmlspecialchars($pt['pub_type']) ?></span>
                </div>
                <strong><?= $pt['cnt'] ?></strong>
            </div>
            <div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                <div style="<?= $widthStyle ?>;height:100%;border-radius:4px;background:<?= $col ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Grant Role breakdown -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-user-tag" style="color:#8b5cf6"></i>
            Grant by Role (PI / Co-I / Member)
        </div>
        <?php if (empty($grantRoles)): ?>
        <p style="color:var(--muted);font-size:13px">No data yet.</p>
        <?php else: ?>
        <div id="grantRoleDonut" style="margin-bottom:1rem"></div>
        <?php
        $totalGrants = array_sum(array_column($grantRoles,'cnt')) ?: 1;
        foreach ($grantRoles as $i => $gr):
            $col = $grantRoleColors[$i % count($grantRoleColors)];
            $pct = round($gr['cnt'] / $totalGrants * 100);
            $wStyle = 'width:' . $pct . '%';
        ?>
        <div style="margin-bottom:.75rem">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
                    <span><?= htmlspecialchars($gr['role']) ?></span>
                </div>
                <span><strong><?= $gr['cnt'] ?></strong> <span style="color:var(--muted);font-size:11px">(<?= $pct ?>%)</span></span>
            </div>
            <div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                <div style="<?= $wStyle ?>;height:100%;border-radius:4px;background:<?= $col ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Row 4: Faculty Comparison (Admin only) -->
<?php if ($isAdmin && !empty($facPerf)): ?>
<div class="card">
    <div class="card-title">
        <i class="fas fa-university" style="color:var(--blue)"></i>
        Faculty Performance Comparison
    </div>
    <div style="overflow-x:auto">
        <table class="arams-table" style="min-width:520px">
            <thead>
                <tr>
                    <th>Faculty</th><th>Publications</th><th>Grants</th>
                    <th>Avg H-Index</th><th style="min-width:180px">Performance</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($facPerf as $i => $fac):
                $sc = $facPcts[$i];
                $barW = 'height:100%;width:' . $sc . '%;border-radius:4px;background:linear-gradient(90deg,var(--blue),var(--teal))';
            ?>
            <tr>
                <td><span class="badge badge-grey"><?= htmlspecialchars($fac['faculty_code']) ?></span></td>
                <td style="font-weight:700"><?= (int)$fac['pubs'] ?></td>
                <td><?= (int)$fac['grants'] ?></td>
                <td><?= number_format((float)$fac['hindex'], 1) ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="flex:1;height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">
                            <div style="<?= $barW ?>"></div>
                        </div>
                        <span style="font-size:12px;font-weight:600;min-width:32px"><?= $sc ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Quartile Donut ────────────────────────────────────
    renderDonut('quartileDonut', [
        { label:'Q1',  value:<?= (int)($qMap['Q1']  ?? 0) ?>, color:'#0B3C5D' },
        { label:'Q2',  value:<?= (int)($qMap['Q2']  ?? 0) ?>, color:'#1B998B' },
        { label:'Q3',  value:<?= (int)($qMap['Q3']  ?? 0) ?>, color:'#3b82f6' },
        { label:'Q4',  value:<?= (int)($qMap['Q4']  ?? 0) ?>, color:'#8b5cf6' },
        { label:'N/A', value:<?= (int)($qMap['N/A'] ?? 0) ?>, color:'#e2e8f0' },
    ]);

    // ── Publication Type Donut ────────────────────────────
    <?php if (!empty($pubTypes)): ?>
    renderDonut('pubTypeDonut', [
        <?php foreach ($pubTypes as $i => $pt):
            $col = $pubTypeColors[$i % count($pubTypeColors)];
        ?>
        { label: '<?= addslashes($pt['pub_type']) ?>', value: <?= (int)$pt['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    // ── Grant Category Donut ──────────────────────────────
    <?php if (!empty($grantCats)): ?>
    renderDonut('grantCatDonut', [
        <?php foreach ($grantCats as $i => $gc):
            $col = $grantCatColors[$i % count($grantCatColors)];
        ?>
        { label: '<?= addslashes($gc['grant_category']) ?>', value: <?= (int)$gc['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    // ── Grant Role Donut ──────────────────────────────────
    <?php if (!empty($grantRoles)): ?>
    renderDonut('grantRoleDonut', [
        <?php foreach ($grantRoles as $i => $gr):
            $col = $grantRoleColors[$i % count($grantRoleColors)];
        ?>
        { label: '<?= addslashes($gr['role']) ?>', value: <?= (int)$gr['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>