<?php
$pageTitle  = 'My Faculty Lecturers';
$activePage = 'lecturers';
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

$tdpp = $db->prepare("SELECT t.*, f.faculty_code, f.faculty_name FROM Tbl_TDPP t JOIN Tbl_Faculty f ON f.faculty_id=t.faculty_id WHERE t.user_id=?");
$tdpp->execute([$_SESSION['user_id']]);
$tdpp = $tdpp->fetch();
$facId = $tdpp['faculty_id'];

$lecturers = $db->prepare(
    "SELECT l.*, u.email,
        (SELECT COUNT(*) FROM Tbl_Publication p JOIN Tbl_Research_Data rd ON p.data_id=rd.data_id WHERE rd.lecturer_id=l.lecturer_id AND rd.status='Approved') AS pubs,
        (SELECT COUNT(*) FROM Tbl_Grant g JOIN Tbl_Research_Data rd ON g.data_id=rd.data_id WHERE rd.lecturer_id=l.lecturer_id AND rd.status='Approved') AS grants,
        (SELECT COUNT(*) FROM Tbl_KPI_Task kt WHERE kt.lecturer_id=l.lecturer_id) AS tasks,
        (SELECT COUNT(*) FROM Tbl_KPI_Task kt WHERE kt.lecturer_id=l.lecturer_id AND kt.status IN ('Completed','Completed (Late)')) AS done
     FROM Tbl_Lecturer l
     JOIN Tbl_User u ON u.user_id=l.user_id
     WHERE l.faculty_id=?
     ORDER BY l.full_name"
);
$lecturers->execute([$facId]);
$lecturers = $lecturers->fetchAll();
?>
<div style="margin-bottom:1rem">
    <h2 style="margin:0;font-size:20px"><?= htmlspecialchars($tdpp['faculty_name']) ?> — Lecturers</h2>
    <p style="margin:4px 0 0;color:var(--muted);font-size:13px"><?= count($lecturers) ?> lecturers under your monitoring</p>
</div>
<div class="card">
    <div class="table-wrap">
        <table class="arams-table">
            <thead><tr><th>Name</th><th>Position</th><th>Email</th><th>Pubs</th><th>Grants</th><th>KPI Progress</th></tr></thead>
            <tbody>
            <?php foreach ($lecturers as $l):
                $rate = $l['tasks'] > 0 ? round($l['done']/$l['tasks']*100) : 0;
            ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($l['full_name']) ?>
                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($l['staff_no']) ?></div>
                </td>
                <td style="font-size:12px"><?= htmlspecialchars($l['position'] ?? '—') ?>
                    <?= $l['grade'] ? '('.htmlspecialchars($l['grade']).')' : '' ?></td>
                <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['email']) ?></td>
                <td><?= (int)$l['pubs'] ?></td>
                <td><?= (int)$l['grants'] ?></td>
                <td>
                    <span class="badge <?= $rate>=70?'badge-green':($rate>=40?'badge-yellow':'badge-grey') ?>">
                        <?= (int)$l['done'] ?>/<?= (int)$l['tasks'] ?> (<?= $rate ?>%)
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lecturers)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No lecturers in your faculty.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>