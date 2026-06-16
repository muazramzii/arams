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
         FROM tbl_publication p JOIN tbl_research_data rd ON p.data_id=rd.data_id
         WHERE rd.status='Approved' AND rd.is_deleted=0 AND p.pub_year >= YEAR(NOW())-5
         GROUP BY p.pub_year ORDER BY p.pub_year"
    )->fetchAll();

    $quartileDist = $db->query(
        "SELECT quartile, COUNT(*) AS cnt FROM tbl_publication p
         JOIN tbl_research_data rd ON p.data_id=rd.data_id
         WHERE rd.status='Approved' AND rd.is_deleted=0 GROUP BY quartile"
    )->fetchAll();

    $pubTypes = $db->query(
        "SELECT pub_type, COUNT(*) AS cnt FROM tbl_publication p
         JOIN tbl_research_data rd ON p.data_id=rd.data_id
         WHERE rd.status='Approved' AND rd.is_deleted=0 GROUP BY pub_type ORDER BY cnt DESC"
    )->fetchAll();

    $grantCats = $db->query(
        "SELECT grant_category, COUNT(*) AS cnt FROM tbl_grant g
         JOIN tbl_research_data rd ON g.data_id=rd.data_id
         WHERE rd.status='Approved' AND rd.is_deleted=0 GROUP BY grant_category ORDER BY cnt DESC"
    )->fetchAll();

    $grantRoles = $db->query(
        "SELECT role, COUNT(*) AS cnt FROM tbl_grant g
         JOIN tbl_research_data rd ON g.data_id=rd.data_id
         WHERE rd.status='Approved' AND rd.is_deleted=0 GROUP BY role ORDER BY cnt DESC"
    )->fetchAll();

    $grantStatus = $db->query(
        "SELECT
            SUM(CASE WHEN g.end_date IS NULL OR g.end_date >= CURDATE() THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN g.end_date IS NOT NULL AND g.end_date < CURDATE() THEN 1 ELSE 0 END) AS nonactive
         FROM tbl_grant g JOIN tbl_research_data rd ON g.data_id=rd.data_id
         WHERE rd.status='Approved' AND rd.is_deleted=0"
    )->fetch();

    $facPerf = $db->query(
        "SELECT f.faculty_code, SUM(k.total_publications) AS pubs,
                SUM(k.total_grants) AS grants, AVG(k.current_hindex) AS hindex
         FROM vw_lecturer_kpi k
         JOIN tbl_lecturer l ON l.lecturer_id=k.lecturer_id
         JOIN tbl_faculty f ON f.faculty_id=l.faculty_id
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
         FROM tbl_publication p JOIN tbl_research_data rd ON p.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0
           AND p.pub_year >= YEAR(NOW())-5
         GROUP BY p.pub_year ORDER BY p.pub_year"
    );
    $pubTrend->execute([$lecId]); $pubTrend = $pubTrend->fetchAll();

    $quartileDist = $db->prepare(
        "SELECT quartile, COUNT(*) AS cnt FROM tbl_publication p
         JOIN tbl_research_data rd ON p.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0 GROUP BY quartile"
    );
    $quartileDist->execute([$lecId]); $quartileDist = $quartileDist->fetchAll();

    $pubTypes = $db->prepare(
        "SELECT pub_type, COUNT(*) AS cnt FROM tbl_publication p
         JOIN tbl_research_data rd ON p.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0
         GROUP BY pub_type ORDER BY cnt DESC"
    );
    $pubTypes->execute([$lecId]); $pubTypes = $pubTypes->fetchAll();

    $grantCats = $db->prepare(
        "SELECT grant_category, COUNT(*) AS cnt FROM tbl_grant g
         JOIN tbl_research_data rd ON g.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0
         GROUP BY grant_category ORDER BY cnt DESC"
    );
    $grantCats->execute([$lecId]); $grantCats = $grantCats->fetchAll();

    $grantRoles = $db->prepare(
        "SELECT role, COUNT(*) AS cnt FROM tbl_grant g
         JOIN tbl_research_data rd ON g.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0
         GROUP BY role ORDER BY cnt DESC"
    );
    $grantRoles->execute([$lecId]); $grantRoles = $grantRoles->fetchAll();

    $grantStatus = $db->prepare(
        "SELECT
            SUM(CASE WHEN g.end_date IS NULL OR g.end_date >= CURDATE() THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN g.end_date IS NOT NULL AND g.end_date < CURDATE() THEN 1 ELSE 0 END) AS nonactive
         FROM tbl_grant g JOIN tbl_research_data rd ON g.data_id=rd.data_id
         WHERE rd.lecturer_id=? AND rd.status='Approved' AND rd.is_deleted=0"
    );
    $grantStatus->execute([$lecId]); $grantStatus = $grantStatus->fetch();

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
            <div class="bar-col" style="cursor:pointer" onclick="drillDown('year', '<?= $row['yr'] ?>')" title="Click to view <?= $row['yr'] ?> publications">
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
        <div style="margin-bottom:.75rem;cursor:pointer" onclick="drillDown('pubtype', '<?= addslashes($pt['pub_type']) ?>')" title="Click to view records">
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
        <div style="margin-bottom:.75rem;cursor:pointer" onclick="drillDown('grantrole', '<?= addslashes($gr['role']) ?>')" title="Click to view grants">
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

<!-- Grant Status: Active vs Non-Active (clickable) -->
<div class="card" style="margin-bottom:1rem">
    <div class="card-title">
        <i class="fas fa-circle-play" style="color:#22c55e"></i>
        Grant Status — Active vs Non-Active
        <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:4px">(click a segment for details)</span>
    </div>
    <?php if (((int)($grantStatus['active'] ?? 0) + (int)($grantStatus['nonactive'] ?? 0)) === 0): ?>
    <p style="color:var(--muted);font-size:13px;text-align:center;padding:2rem 0">No grants yet.</p>
    <?php else: ?>
    <div id="grantStatusDonut"></div>
    <?php endif; ?>
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

<!-- ── Drill-Down Detail Panel (auto-appears at bottom) ── -->
<div class="card" id="detailPanel" style="display:none;margin-top:1rem;scroll-margin-top:20px">
    <div class="card-title" style="justify-content:space-between">
        <span><i class="fas fa-list" style="color:var(--teal)"></i> <span id="detailTitle">Records</span>
            <span id="detailCount" style="font-size:12px;color:var(--muted);font-weight:400;margin-left:6px"></span>
        </span>
        <button class="btn btn-outline btn-sm" onclick="closeDrill()"><i class="fas fa-times"></i> Close</button>
    </div>
    <div class="table-wrap">
        <table class="arams-table" id="detailTable">
            <thead id="detailHead"></thead>
            <tbody id="detailBody"></tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    renderDonut('quartileDonut', [
        { label:'Q1',  value:<?= (int)($qMap['Q1']  ?? 0) ?>, color:'#0B3C5D' },
        { label:'Q2',  value:<?= (int)($qMap['Q2']  ?? 0) ?>, color:'#1B998B' },
        { label:'Q3',  value:<?= (int)($qMap['Q3']  ?? 0) ?>, color:'#3b82f6' },
        { label:'Q4',  value:<?= (int)($qMap['Q4']  ?? 0) ?>, color:'#8b5cf6' },
        { label:'N/A', value:<?= (int)($qMap['N/A'] ?? 0) ?>, color:'#e2e8f0' },
    ]);

    <?php if (!empty($pubTypes)): ?>
    renderDonut('pubTypeDonut', [
        <?php foreach ($pubTypes as $i => $pt): $col = $pubTypeColors[$i % count($pubTypeColors)]; ?>
        { label: '<?= addslashes($pt['pub_type']) ?>', value: <?= (int)$pt['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    <?php if (!empty($grantCats)): ?>
    renderDonut('grantCatDonut', [
        <?php foreach ($grantCats as $i => $gc): $col = $grantCatColors[$i % count($grantCatColors)]; ?>
        { label: '<?= addslashes($gc['grant_category']) ?>', value: <?= (int)$gc['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    <?php if (!empty($grantRoles)): ?>
    renderDonut('grantRoleDonut', [
        <?php foreach ($grantRoles as $i => $gr): $col = $grantRoleColors[$i % count($grantRoleColors)]; ?>
        { label: '<?= addslashes($gr['role']) ?>', value: <?= (int)$gr['cnt'] ?>, color: '<?= $col ?>' },
        <?php endforeach; ?>
    ]);
    <?php endif; ?>

    <?php if (((int)($grantStatus['active'] ?? 0) + (int)($grantStatus['nonactive'] ?? 0)) > 0): ?>
    renderDonut('grantStatusDonut', [
        { label: 'Active',     value: <?= (int)($grantStatus['active'] ?? 0) ?>,    color: '#22c55e' },
        { label: 'Non-Active', value: <?= (int)($grantStatus['nonactive'] ?? 0) ?>, color: '#ef4444' }
    ]);
    <?php endif; ?>

    // Attach donut hover tooltips + legend clicks after render
    setTimeout(function() {
        ['quartileDonut','pubTypeDonut','grantCatDonut','grantRoleDonut','grantStatusDonut'].forEach(function(id){
            attachDonutHovers(id);
        });
        attachDonutClicks('quartileDonut', 'quartile');
        attachDonutClicks('pubTypeDonut',  'pubtype');
        attachDonutClicks('grantCatDonut', 'grantcat');
        attachDonutClicks('grantRoleDonut','grantrole');
        attachDonutClicks('grantStatusDonut','grantactive');
    }, 300);
});

// ── Hover tooltips on donut segments ───────────────────────────────────
// Matches each SVG segment to its legend entry by stroke colour, then shows
// a floating tooltip (e.g. "Q3 19") and thickens the segment on hover.
function attachDonutHovers(donutId) {
    var d = document.getElementById(donutId);
    if (!d) return;
    var svg = d.querySelector('svg');
    if (!svg) return;

    // shared tooltip element (created once)
    var tip = document.getElementById('donutTip');
    if (!tip) {
        tip = document.createElement('div');
        tip.id = 'donutTip';
        tip.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;background:#0f172a;'
            + 'color:#fff;padding:6px 10px;border-radius:6px;font-size:12px;font-weight:600;'
            + 'box-shadow:0 4px 12px rgba(0,0,0,.25);display:none;white-space:nowrap';
        document.body.appendChild(tip);
    }

    // build colour -> "label value" map from the legend
    var map = {};
    d.querySelectorAll('.legend-item').forEach(function(li){
        var sw = li.querySelector('[style*="background"]');
        if (!sw) return;
        var m = (sw.getAttribute('style') || '').match(/background:\s*([^;]+)/);
        if (m) map[m[1].trim().toLowerCase()] = li.textContent.replace(/\s+/g,' ').trim();
    });

    svg.querySelectorAll('circle').forEach(function(c){
        var stroke = (c.getAttribute('stroke') || '').toLowerCase();
        var label = map[stroke];
        if (!label) return;                 // skip the grey track circle
        var baseW = c.getAttribute('stroke-width');
        c.style.cursor = 'pointer';
        c.style.transition = 'stroke-width .15s';
        c.addEventListener('mouseenter', function(){
            tip.textContent = label;
            tip.style.display = 'block';
            c.setAttribute('stroke-width', (parseFloat(baseW) + 4));
        });
        c.addEventListener('mousemove', function(e){
            tip.style.left = (e.clientX + 14) + 'px';
            tip.style.top  = (e.clientY - 10) + 'px';
        });
        c.addEventListener('mouseleave', function(){
            tip.style.display = 'none';
            c.setAttribute('stroke-width', baseW);
        });
    });
}

// Tells the front-end whether we are an Admin (institution-wide) view.
// For non-admins the API is already scoped to one faculty/self, so the
// faculty layer is skipped automatically (API returns mode:"records").
var ARAMS_IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

// Remember the current selection so the "Back" button can re-open the
// faculty list without re-clicking the chart.
var drillState = { type:'', value:'' };

function attachDonutClicks(donutId, filterType) {
    var container = document.getElementById(donutId);
    if (!container) return;
    var items = container.querySelectorAll('.legend-item');
    items.forEach(function(item){
        item.style.cursor = 'pointer';
        item.title = 'Click to view records';
        item.addEventListener('mouseenter', function(){ item.style.opacity = '0.7'; });
        item.addEventListener('mouseleave', function(){ item.style.opacity = '1'; });
        item.addEventListener('click', function(){
            var label = '';
            var spans = item.querySelectorAll('span');
            for (var i=0; i<spans.length; i++) {
                if (!spans[i].classList.contains('legend-val')) { label = spans[i].textContent.trim(); break; }
            }
            if (label) drillDown(filterType, label);
        });
    });
}

// ── Entry point: a chart segment was clicked ───────────────────────────
// Admin  -> show the faculty breakdown first.
// Others -> API is scoped, so it returns records directly.
function drillDown(type, value) {
    drillState.type = type;
    drillState.value = value;

    var panel = document.getElementById('detailPanel');
    var body  = document.getElementById('detailBody');
    document.getElementById('detailTitle').textContent = 'Loading...';
    document.getElementById('detailCount').textContent = '';
    body.innerHTML = '<tr><td style="padding:1.5rem;text-align:center;color:var(--muted)">Loading...</td></tr>';
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior:'smooth', block:'start' });

    // No faculty_id yet → API decides: Admin gets a faculty summary,
    // TDPP/Lecturer get records directly.
    fetch('/arams/api/analytics_detail.php?type=' + encodeURIComponent(type) + '&value=' + encodeURIComponent(value))
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showDrillError(res.message); return; }
            if (res.mode === 'faculty') { renderFacultyList(res); }
            else { renderRecords(res); }
        })
        .catch(function(){ showDrillError('Error loading data.'); });
}

// ── Level 1: faculty breakdown (Admin) ─────────────────────────────────
function renderFacultyList(res) {
    var head = document.getElementById('detailHead');
    var body = document.getElementById('detailBody');

    document.getElementById('detailTitle').textContent = res.title + ' — by Faculty';
    document.getElementById('detailCount').textContent =
        '(' + res.total + ' record' + (res.total!=1?'s':'') + ' across ' + res.faculties.length + ' facult' + (res.faculties.length!=1?'ies':'y') + ')';

    if (!res.faculties.length) {
        head.innerHTML = '';
        body.innerHTML = '<tr><td style="padding:1.5rem;text-align:center;color:var(--muted)">No records found for this selection.</td></tr>';
        return;
    }

    head.innerHTML = '<tr><th>Faculty</th><th>Records</th><th style="width:40%">Share</th><th></th></tr>';
    var max = res.faculties[0].cnt || 1;
    body.innerHTML = res.faculties.map(function(f){
        var pct = Math.round((f.cnt / max) * 100);
        return '<tr class="fac-row" style="cursor:pointer" ' +
                'onclick="drillFaculty(' + f.faculty_id + ', \'' + esc(f.faculty_name).replace(/\'/g,"") + '\')" ' +
                'title="Click to see lecturers & records in ' + esc(f.faculty_name) + '">' +
            '<td style="font-weight:600">' +
                '<span class="badge badge-grey" style="font-size:10px;margin-right:6px">' + esc(f.faculty_code) + '</span>' +
                esc(f.faculty_name) + '</td>' +
            '<td style="font-weight:700">' + f.cnt + '</td>' +
            '<td><div style="height:7px;background:var(--grey-mid);border-radius:4px;overflow:hidden">' +
                '<div style="width:' + pct + '%;height:100%;border-radius:4px;background:linear-gradient(90deg,var(--blue),var(--teal))"></div>' +
                '</div></td>' +
            '<td style="text-align:right;color:var(--muted)"><i class="fas fa-chevron-right"></i></td>' +
            '</tr>';
    }).join('');
}

// ── Level 2: click a faculty → fetch its records ───────────────────────
function drillFaculty(facId, facName) {
    var body = document.getElementById('detailBody');
    document.getElementById('detailTitle').textContent = 'Loading ' + facName + '...';
    document.getElementById('detailCount').textContent = '';
    body.innerHTML = '<tr><td style="padding:1.5rem;text-align:center;color:var(--muted)">Loading records...</td></tr>';

    fetch('/arams/api/analytics_detail.php?type=' + encodeURIComponent(drillState.type) +
          '&value=' + encodeURIComponent(drillState.value) +
          '&faculty_id=' + encodeURIComponent(facId))
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showDrillError(res.message); return; }
            renderRecords(res, true);
        })
        .catch(function(){ showDrillError('Error loading records.'); });
}

// ── Render the record list (shared by all roles) ───────────────────────
// showBack = true only when an Admin drilled into a faculty.
function renderRecords(res, showBack) {
    var head = document.getElementById('detailHead');
    var body = document.getElementById('detailBody');

    var backBtn = (showBack && ARAMS_IS_ADMIN)
        ? '<button class="btn btn-outline btn-sm" style="margin-bottom:10px" onclick="drillDown(drillState.type, drillState.value)"><i class="fas fa-arrow-left"></i> Back to faculties</button>'
        : '';
    document.getElementById('detailTitle').innerHTML =
        '<i class="fas fa-list" style="color:var(--teal)"></i> ' + esc(res.title);
    document.getElementById('detailCount').textContent =
        '(' + res.count + ' record' + (res.count!=1?'s':'') + ')';

    // Inject/refresh the back button row above the table
    var panel = document.getElementById('detailPanel');
    var existing = document.getElementById('drillBackBar');
    if (existing) existing.remove();
    if (backBtn) {
        var bar = document.createElement('div');
        bar.id = 'drillBackBar';
        bar.innerHTML = backBtn;
        var wrap = panel.querySelector('.table-wrap');
        panel.insertBefore(bar, wrap);
    }

    if (res.count === 0) {
        head.innerHTML = '';
        body.innerHTML = '<tr><td style="padding:1.5rem;text-align:center;color:var(--muted)">No records found for this selection.</td></tr>';
        return;
    }

    // Whether the API gave us a lecturer column (it does in v2)
    var hasLecturer = res.rows.length && (res.rows[0].lecturer_name !== undefined);
    var lecHead = hasLecturer ? '<th>Lecturer</th>' : '';

    if (res.kind === 'publication') {
        head.innerHTML = '<tr>' + lecHead + '<th>Title</th><th>Authors</th><th>Journal</th><th>Year</th><th>Quartile</th><th>Indexing</th><th>Status</th></tr>';
        body.innerHTML = res.rows.map(function(r){
            var lecCell = hasLecturer
                ? '<td style="font-size:11px;font-weight:600;white-space:nowrap">' + esc((r.lecturer_title? r.lecturer_title+' ':'') + r.lecturer_name) + '</td>'
                : '';
            return '<tr>' + lecCell +
                '<td style="font-weight:600;font-size:12px;max-width:240px">' + esc(r.title) + '</td>' +
                '<td style="font-size:11px;color:var(--muted);max-width:160px">' + esc(r.authors) + '</td>' +
                '<td style="font-size:11px">' + esc(r.journal_name) + '</td>' +
                '<td>' + esc(r.pub_year) + '</td>' +
                '<td><span class="badge badge-blue" style="font-size:10px">' + esc(r.quartile) + '</span></td>' +
                '<td style="font-size:11px">' + esc(r.indexing_type) + '</td>' +
                '<td><span class="badge badge-green" style="font-size:10px">' + esc(r.status) + '</span></td>' +
                '</tr>';
        }).join('');
    } else {
        head.innerHTML = '<tr>' + lecHead + '<th>Grant Title</th><th>Code</th><th>Funder</th><th>Category</th><th>Level</th><th>Role</th><th>Amount</th><th>Status</th></tr>';
        body.innerHTML = res.rows.map(function(r){
            var lecCell = hasLecturer
                ? '<td style="font-size:11px;font-weight:600;white-space:nowrap">' + esc((r.lecturer_title? r.lecturer_title+' ':'') + r.lecturer_name) + '</td>'
                : '';
            return '<tr>' + lecCell +
                '<td style="font-weight:600;font-size:12px;max-width:220px">' + esc(r.grant_title) + '</td>' +
                '<td style="font-size:11px">' + esc(r.grant_code) + '</td>' +
                '<td style="font-size:11px">' + esc(r.funder) + '</td>' +
                '<td style="font-size:11px">' + esc(r.grant_category) + '</td>' +
                '<td><span class="badge badge-grey" style="font-size:10px">' + esc(r.grant_level) + '</span></td>' +
                '<td><span class="badge badge-blue" style="font-size:10px">' + esc(r.role) + '</span></td>' +
                '<td style="font-size:11px">RM ' + Number(r.amount||0).toLocaleString() + '</td>' +
                '<td><span class="badge badge-green" style="font-size:10px">' + esc(r.status) + '</span></td>' +
                '</tr>';
        }).join('');
    }
}

function showDrillError(msg) {
    document.getElementById('detailHead').innerHTML = '';
    document.getElementById('detailBody').innerHTML =
        '<tr><td style="padding:1rem;color:#dc2626">' + esc(msg || 'No data') + '</td></tr>';
}

function closeDrill() {
    document.getElementById('detailPanel').style.display = 'none';
    var bar = document.getElementById('drillBackBar');
    if (bar) bar.remove();
}

function esc(s) { if (s===null||s===undefined) return '—'; return String(s).replace(/[&<>"]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>