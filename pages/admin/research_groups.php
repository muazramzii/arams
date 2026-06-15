<?php
// ============================================================
//  ARAMS — Admin: Lecturers by Research Group (accordion view)
//  A review tool: see who belongs to each research group, who is
//  in an External/Others group, and who is still unassigned.
// ============================================================
$pageTitle  = 'Research Groups';
$activePage = 'researchgroups';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Master research groups (always shown, even when empty)
$groups = $db->query(
    "SELECT rg.group_id, rg.group_code, rg.group_name, f.faculty_code
     FROM tbl_research_group rg
     JOIN tbl_faculty f ON f.faculty_id = rg.faculty_id
     WHERE rg.is_active = 1
     ORDER BY f.faculty_code, rg.group_name"
)->fetchAll();

// All lecturers with their grouping fields
$lecturers = $db->query(
    "SELECT l.lecturer_id, l.full_name, l.staff_no, l.grade,
            l.research_group_id, l.research_group_category, l.research_centre,
            f.faculty_code
     FROM tbl_lecturer l
     JOIN tbl_faculty f ON f.faculty_id = l.faculty_id
     ORDER BY l.full_name"
)->fetchAll();

// Bucket lecturers: master group → external/others (by centre) → unassigned
$byMaster = [];      // group_id  => [lecturers]
$byCentre = [];      // centre    => [lecturers]
$unassigned = [];
$masterIds = array_column($groups, 'group_id');

foreach ($lecturers as $l) {
    if (!empty($l['research_group_id']) && in_array($l['research_group_id'], $masterIds)) {
        $byMaster[$l['research_group_id']][] = $l;
    } elseif (!empty($l['research_centre'])) {
        $byCentre[$l['research_centre']][] = $l;
    } else {
        $unassigned[] = $l;
    }
}
ksort($byCentre);

$totalAssigned = count($lecturers) - count($unassigned);

// Small helper to render a lecturer row
function lecRow(array $l): string {
    $cat = $l['research_group_category'] ?: '—';
    $catColor = $cat === 'FG' ? 'badge-teal' : ($cat === 'External' ? 'badge-blue' : 'badge-grey');
    return '<tr>'
        . '<td style="font-weight:600">' . htmlspecialchars($l['full_name']) . '</td>'
        . '<td>' . htmlspecialchars($l['staff_no'] ?: '—') . '</td>'
        . '<td>' . htmlspecialchars($l['faculty_code']) . '</td>'
        . '<td>' . htmlspecialchars($l['grade'] ?: '—') . '</td>'
        . '<td><span class="badge ' . $catColor . '" style="font-size:10px">' . htmlspecialchars($cat) . '</span></td>'
        . '<td><a class="btn btn-outline btn-sm" href="/arams/pages/admin/lecturer_detail.php?id=' . (int)$l['lecturer_id'] . '">View</a></td>'
        . '</tr>';
}

function lecTable(array $lecs): string {
    if (empty($lecs)) {
        return '<p style="color:var(--muted);font-size:13px;padding:.5rem 0">No lecturers in this group yet.</p>';
    }
    $h = '<div class="table-wrap"><table class="arams-table"><thead><tr>'
       . '<th>Lecturer</th><th>Staff No</th><th>Faculty</th><th>Grade</th><th>Category</th><th></th>'
       . '</tr></thead><tbody>';
    foreach ($lecs as $l) $h .= lecRow($l);
    return $h . '</tbody></table></div>';
}
?>

<style>
.rg-acc{border:1px solid var(--border);border-radius:10px;margin-bottom:10px;overflow:hidden;background:#fff}
.rg-acc>summary{list-style:none;cursor:pointer;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;font-weight:600}
.rg-acc>summary::-webkit-details-marker{display:none}
.rg-acc>summary:hover{background:var(--grey)}
.rg-acc[open]>summary{border-bottom:1px solid var(--border)}
.rg-acc .acc-body{padding:8px 16px 16px}
.rg-acc .chev{transition:.2s;color:var(--muted)}
.rg-acc[open] .chev{transform:rotate(90deg)}
.rg-meta{display:flex;align-items:center;gap:10px}
.rg-count{background:var(--grey-mid);border-radius:20px;padding:2px 10px;font-size:12px;font-weight:700;color:var(--text)}
</style>

<div class="page-header">
    <h1>Research Groups</h1>
    <p>Lecturers grouped by research group — <?= $totalAssigned ?> assigned, <?= count($unassigned) ?> unassigned</p>
</div>

<!-- Master research groups -->
<div class="card" style="margin-bottom:1rem">
    <div class="card-title"><i class="fas fa-sitemap" style="color:var(--teal)"></i> Faculty Research Groups (FG)</div>
    <?php if (empty($groups)): ?>
    <p style="color:var(--muted);font-size:13px">No research groups defined. Create them via User Management → Manage Groups.</p>
    <?php else: ?>
    <?php foreach ($groups as $g): $members = $byMaster[$g['group_id']] ?? []; ?>
    <details class="rg-acc">
        <summary>
            <span class="rg-meta">
                <i class="fas fa-chevron-right chev"></i>
                <span><?= htmlspecialchars($g['group_name']) ?>
                    <span style="color:var(--muted);font-weight:400;font-size:12px">· <?= htmlspecialchars($g['group_code']) ?> · <?= htmlspecialchars($g['faculty_code']) ?></span>
                </span>
            </span>
            <span class="rg-count"><?= count($members) ?> <?= count($members) === 1 ? 'member' : 'members' ?></span>
        </summary>
        <div class="acc-body"><?= lecTable($members) ?></div>
    </details>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- External / Others groups (free-text research centre) -->
<?php if (!empty($byCentre)): ?>
<div class="card" style="margin-bottom:1rem">
    <div class="card-title"><i class="fas fa-network-wired" style="color:var(--blue)"></i> External / Other Groups</div>
    <?php foreach ($byCentre as $centre => $members): ?>
    <details class="rg-acc">
        <summary>
            <span class="rg-meta">
                <i class="fas fa-chevron-right chev"></i>
                <span><?= htmlspecialchars($centre) ?></span>
            </span>
            <span class="rg-count"><?= count($members) ?> <?= count($members) === 1 ? 'member' : 'members' ?></span>
        </summary>
        <div class="acc-body"><?= lecTable($members) ?></div>
    </details>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Unassigned -->
<div class="card">
    <div class="card-title"><i class="fas fa-user-slash" style="color:#dc2626"></i> Unassigned Lecturers</div>
    <?php if (empty($unassigned)): ?>
    <p style="color:var(--muted);font-size:13px">Everyone is assigned to a research group. 🎉</p>
    <?php else: ?>
    <p style="color:var(--muted);font-size:12px;margin-bottom:8px">These lecturers have no research group set. Assign them via User Management or their detail page.</p>
    <?= lecTable($unassigned) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>