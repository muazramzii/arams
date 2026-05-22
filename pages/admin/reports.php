<?php
// ============================================================
//  ARAMS — Admin Report Generation
// ============================================================
$pageTitle  = 'Report Generation';
$activePage = 'reports';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Recent reports
$recentReports = $db->query(
    "SELECT r.*, a.name AS admin_name
     FROM Tbl_Report r JOIN Tbl_Admin a ON a.admin_id = r.admin_id
     ORDER BY r.date_generated DESC LIMIT 10"
)->fetchAll();

// Stats
$stats = $db->query(
    "SELECT COUNT(*) AS total FROM Tbl_Report"
)->fetchColumn();

$faculties = $db->query("SELECT faculty_id, faculty_code, faculty_name FROM Tbl_Faculty ORDER BY faculty_code")->fetchAll();

$allLecturers = $db->query(
    "SELECT l.lecturer_id, l.full_name, l.staff_no, f.faculty_code
     FROM Tbl_Lecturer l
     JOIN Tbl_Faculty f ON f.faculty_id = l.faculty_id
     ORDER BY l.full_name"
)->fetchAll();
?>

<div class="page-header">
    <h1>Report Generation</h1>
    <p>Generate and export research analytics reports</p>
</div>

<div class="grid-2-1">
    <!-- Left: Config -->
    <div style="display:flex;flex-direction:column;gap:1rem">

        <!-- Report Type Selection -->
        <div class="card">
            <div class="card-title"><i class="fas fa-file-alt" style="color:var(--blue)"></i> Select Report Type</div>
            <div class="report-grid" id="reportGrid">
                <?php
                $templates = [
                    ['id'=>'comprehensive', 'name'=>'Comprehensive Research Report', 'desc'=>'Complete overview of all research activities', 'sel'=>true],
                    ['id'=>'publications',  'name'=>'Publications Report',           'desc'=>'Detailed publication statistics and analysis'],
                    ['id'=>'grants',        'name'=>'Grants & Funding Report',       'desc'=>'Grant awards and funding breakdown'],
                    ['id'=>'faculty',       'name'=>'Faculty Performance Report',    'desc'=>'Faculty-wise performance comparison'],
                    ['id'=>'individual',    'name'=>'Individual Lecturer Report',    'desc'=>'Detailed report for specific lecturer'],
                    ['id'=>'awards',        'name'=>'Awards & IP Report',            'desc'=>'Awards, recognition and IP records'],
                    ['id'=>'hindex',        'name'=>'H-Index & Citations Report',    'desc'=>'Citation impact and H-index trends'],
                    ['id'=>'lecturer',      'name'=>'Lecturer Performance Report',   'desc'=>'Full performance report for a specific lecturer'],
                ];
                foreach ($templates as $t): ?>
                <div class="report-card <?= ($t['sel']??false)?'selected':'' ?>"
                     onclick="selectReport(this, '<?= $t['id'] ?>')">
                    <div class="report-card-icon">
                        <i class="fas fa-file-chart-bar"></i>
                    </div>
                    <div class="report-card-name"><?= $t['name'] ?></div>
                    <div class="report-card-desc"><?= $t['desc'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="selectedReportType" value="comprehensive">
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-title"><i class="fas fa-filter" style="color:var(--teal)"></i> Report Filters</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <select class="form-control" id="filterYear">
                        <option value="all">All Years</option>
                        <?php for ($y = date('Y'); $y >= 2015; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Faculty</label>
                    <select class="form-control" id="filterFaculty">
                        <option value="all">All Faculties</option>
                        <?php foreach ($faculties as $f): ?>
                        <option value="<?= $f['faculty_id'] ?>"><?= htmlspecialchars($f['faculty_code']) ?> — <?= htmlspecialchars($f['faculty_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Lecturer selector — only shown when Individual Lecturer Report is selected -->
            <div class="form-group" id="lecturerSelectGroup" style="display:none">
                <label class="form-label">Select Lecturer *</label>
                <select class="form-control" id="filterLecturer">
                    <option value="">— Choose a Lecturer —</option>
                    <?php foreach ($allLecturers as $l): ?>
                    <option value="<?= $l['lecturer_id'] ?>">
                        <?= htmlspecialchars($l['full_name']) ?> (<?= htmlspecialchars($l['faculty_code']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Export Format</label>
                <select class="form-control" id="exportFormat">
                    <option value="Excel">Excel (.xlsx)</option>
                    <option value="PDF">PDF Document</option>
                    <option value="CSV">CSV File</option>
                </select>
            </div>
            <button class="btn btn-teal btn-full" onclick="generateReport()">
                <i class="fas fa-download"></i> Generate &amp; Download Report
            </button>
        </div>
    </div>

    <!-- Right: Sidebar stats + recent reports -->
    <div style="display:flex;flex-direction:column;gap:1rem">

        <!-- Stats Card -->
        <div class="card" style="background:linear-gradient(135deg,var(--blue),var(--teal));color:#fff;border:none">
            <div style="font-size:28px;margin-bottom:.5rem"><i class="fas fa-calendar-alt"></i></div>
            <h3 style="font-size:15px;margin:0 0 1rem;font-family:inherit">Report Statistics</h3>
            <?php
            $monthCount = $db->query("SELECT COUNT(*) FROM Tbl_Report WHERE date_generated >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
            ?>
            <div style="font-size:13px;display:flex;justify-content:space-between;margin-bottom:6px;opacity:.9">
                <span>Generated this month</span><strong><?= $monthCount ?></strong>
            </div>
            <div style="font-size:13px;display:flex;justify-content:space-between;margin-bottom:6px;opacity:.9">
                <span>Total reports</span><strong><?= $stats ?></strong>
            </div>
            <div style="font-size:13px;display:flex;justify-content:space-between;opacity:.9">
                <span>Total lecturers</span>
                <strong><?= $db->query("SELECT COUNT(*) FROM Tbl_Lecturer")->fetchColumn() ?></strong>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="card">
            <div class="card-title"><i class="fas fa-history" style="color:var(--blue)"></i> Recent Reports</div>
            <?php if (empty($recentReports)): ?>
            <p style="color:var(--muted);font-size:13px">No reports generated yet.</p>
            <?php endif; ?>
            <?php foreach ($recentReports as $r): ?>
            <div style="padding:.75rem;background:var(--grey);border-radius:var(--radius-sm);margin-bottom:8px">
                <div style="display:flex;align-items:flex-start;justify-content:space-between">
                    <div style="flex:1">
                        <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($r['report_type']) ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px">
                            <?= $r['report_year'] ?? 'All years' ?> •
                            <?= date('d M Y', strtotime($r['date_generated'])) ?>
                        </div>
                    </div>
                    <span class="badge <?= $r['format']==='PDF' ? 'badge-red' : ($r['format']==='CSV' ? 'badge-teal' : 'badge-green') ?>"
                          style="font-size:10px;margin-left:6px"><?= $r['format'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Help Box -->
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius-sm);padding:1rem">
            <h4 style="font-size:13px;color:#1e40af;margin:0 0 5px">Need Help?</h4>
            <p style="font-size:12px;color:#1e40af;margin:0 0 8px;opacity:.8">Reports are generated based on approved data only. Pending submissions are excluded.</p>
        </div>
    </div>
</div>

<!-- Hidden form for file download -->
<form id="reportForm" method="POST" action="/arams/api/generate_report.php"
      target="_blank" style="display:none">
    <input type="hidden" name="type"       id="f_type">
    <input type="hidden" name="year"       id="f_year">
    <input type="hidden" name="faculty_id" id="f_fac">
    <input type="hidden" name="format"     id="f_format">
</form>

<script>
function selectReport(el, type) {
    document.querySelectorAll('.report-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedReportType').value = type;
    document.querySelectorAll('.report-card-icon').forEach(i => {
        i.style.background = 'var(--grey)'; i.style.color = 'var(--muted)';
    });
    el.querySelector('.report-card-icon').style.background = 'var(--teal)';
    el.querySelector('.report-card-icon').style.color = '#fff';

    // Show/hide lecturer dropdown
    const lecGroup = document.getElementById('lecturerSelectGroup');
    if (lecGroup) lecGroup.style.display = type === 'lecturer' ? 'block' : 'none';
}

function generateReport() {
    const type   = document.getElementById('selectedReportType').value;
    const year   = document.getElementById('filterYear').value;
    const fac    = document.getElementById('filterFaculty').value;
    const format = document.getElementById('exportFormat').value;
    const btn    = event.target;

    // If lecturer performance report — redirect to lecturer_report.php
    if (type === 'lecturer') {
        const lecId = document.getElementById('filterLecturer').value;
        if (!lecId) {
            showToast('Please select a lecturer first.', 'error');
            return;
        }
        window.open('/arams/pages/admin/lecturer_report.php?lecturer_id=' + lecId, '_blank');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';

    // Use form POST so browser can download the file directly
    document.getElementById('f_type').value   = type;
    document.getElementById('f_year').value   = year;
    document.getElementById('f_fac').value    = fac;
    document.getElementById('f_format').value = format;
    document.getElementById('reportForm').submit();

    // Re-enable button and reload after short delay
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download"></i> Generate &amp; Download Report';
        location.reload();
    }, 3000);
}

// Set initial icon state
document.addEventListener('DOMContentLoaded', function(){
    const first = document.querySelector('.report-card.selected .report-card-icon');
    if (first) { first.style.background = 'var(--teal)'; first.style.color = '#fff'; }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>