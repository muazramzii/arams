<?php
$pageTitle  = 'All Lecturers';
$activePage = 'lecturers';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$lecturers = $db->query("SELECT k.*, l.staff_no, l.department, l.research_centre
                          FROM vw_lecturer_kpi k
                          JOIN Tbl_Lecturer l ON l.lecturer_id = k.lecturer_id
                          ORDER BY k.total_publications DESC")->fetchAll();
$faculties = $db->query("SELECT faculty_id, faculty_code, faculty_name FROM Tbl_Faculty ORDER BY faculty_code")->fetchAll();
?>

<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1>All Lecturers</h1>
        <p>Manage lecturer accounts and research profiles</p>
    </div>
    <button class="btn btn-teal" onclick="openAddLecturerModal()">
        <i class="fas fa-plus"></i> Add Lecturer
    </button>
</div>

<div class="search-row">
    <input type="text" class="search-input" placeholder="Search by name, faculty…"
           oninput="filterTable(this,'lecTable')">
    <select class="filter-select" onchange="filterBySelect(this,'lecTable',2)">
        <option value="">All Faculties</option>
        <?php foreach ($faculties as $f): ?>
        <option value="<?= htmlspecialchars($f['faculty_code']) ?>"><?= htmlspecialchars($f['faculty_code']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="arams-table" id="lecTable">
            <thead><tr>
                <th>#</th><th>Name</th><th>Faculty</th>
                <th>Pubs</th><th>H-Index</th><th>Grants</th><th>Income (RM)</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($lecturers as $i => $l): ?>
            <tr>
                <td style="color:var(--muted);font-size:12px"><?= $i+1 ?></td>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($l['full_name']) ?></div>
                    <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($l['staff_no']) ?></div>
                </td>
                <td><span class="badge badge-grey"><?= htmlspecialchars($l['faculty_code']) ?></span></td>
                <td>
                    <strong><?= (int)$l['total_publications'] ?></strong>
                    <div style="font-size:11px;color:var(--muted)">Q1: <?= (int)$l['q1_pubs'] ?> Q2: <?= (int)$l['q2_pubs'] ?></div>
                </td>
                <td style="font-weight:700;color:var(--blue)"><?= (int)$l['current_hindex'] ?></td>
                <td><?= (int)$l['total_grants'] ?></td>
                <td style="color:var(--green);font-weight:600">
                    <?= $l['total_income_rm'] ? number_format((float)$l['total_income_rm']) : '—' ?>
                </td>
                <td>
                    <a href="/arams/pages/admin/lecturer_detail.php?id=<?= $l['lecturer_id'] ?>"
                       class="btn btn-outline btn-sm">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Build faculty options from PHP into JS
const facultyOptions = `
    <?php foreach ($faculties as $f): ?>
    <option value="<?= $f['faculty_id'] ?>"><?= htmlspecialchars($f['faculty_code']) ?> — <?= htmlspecialchars($f['faculty_name']) ?></option>
    <?php endforeach; ?>
`;

function openAddLecturerModal() {
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Add Lecturer Account</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form id="addLecForm" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input class="form-control" name="full_name" required placeholder="Dr. / Pn. / En. ...">
                </div>
                <div class="form-group">
                    <label class="form-label">Staff No *</label>
                    <input class="form-control" name="staff_no" required placeholder="UTH...">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input class="form-control" name="email" type="email" required placeholder="name@uthm.edu.my">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone" placeholder="07-4533XXX">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Faculty *</label>
                    <select class="form-control" name="faculty_id" required>
                        ${facultyOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <select class="form-control" name="position">
                        <option>Lecturer</option>
                        <option>Senior Lecturer</option>
                        <option>Associate Professor</option>
                        <option>Professor</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Specialisation</label>
                <input class="form-control" name="specialisation" placeholder="e.g. Machine Learning, IoT">
            </div>

            <!-- Password with eye toggle -->
            <div class="form-group">
                <label class="form-label">Temporary Password *</label>
                <div style="position:relative">
                    <input class="form-control" type="password" name="password" id="lecPw"
                           required minlength="8" placeholder="Minimum 8 characters"
                           style="padding-right:42px">
                    <button type="button" onclick="toggleLecPw('lecPw','lecPwIcon')"
                            style="position:absolute;right:0;top:0;height:100%;width:40px;
                                   background:none;border:none;cursor:pointer;color:#94a3b8;
                                   display:flex;align-items:center;justify-content:center;
                                   border-radius:0 8px 8px 0;transition:.15s"
                            onmouseenter="this.style.color='#0B3C5D'"
                            onmouseleave="this.style.color='#94a3b8'"
                            title="Show/hide password">
                        <i class="fas fa-eye" id="lecPwIcon" style="font-size:14px"></i>
                    </button>
                </div>
            </div>

            <!-- Confirm Password with eye toggle -->
            <div class="form-group">
                <label class="form-label">Confirm Password *</label>
                <div style="position:relative">
                    <input class="form-control" type="password" name="confirm_password" id="lecPwConf"
                           required minlength="8" placeholder="Re-enter password"
                           style="padding-right:42px">
                    <button type="button" onclick="toggleLecPw('lecPwConf','lecPwConfIcon')"
                            style="position:absolute;right:0;top:0;height:100%;width:40px;
                                   background:none;border:none;cursor:pointer;color:#94a3b8;
                                   display:flex;align-items:center;justify-content:center;
                                   border-radius:0 8px 8px 0;transition:.15s"
                            onmouseenter="this.style.color='#0B3C5D'"
                            onmouseleave="this.style.color='#94a3b8'"
                            title="Show/hide password">
                        <i class="fas fa-eye" id="lecPwConfIcon" style="font-size:14px"></i>
                    </button>
                </div>
                <div id="lecPwError" style="font-size:12px;color:#dc2626;margin-top:4px;display:none">
                    <i class="fas fa-exclamation-circle"></i> Passwords do not match.
                </div>
            </div>
        </form>

        <button class="btn btn-teal btn-full" style="margin-top:1rem"
                onclick="submitAddLecturer()">
            <i class="fas fa-user-plus"></i> Create Account
        </button>`);
}

function toggleLecPw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
    input.focus();
}

function submitAddLecturer() {
    const form    = document.getElementById('addLecForm');
    const pw      = document.getElementById('lecPw');
    const pwConf  = document.getElementById('lecPwConf');
    const errDiv  = document.getElementById('lecPwError');

    // Clear previous error
    errDiv.style.display = 'none';
    pw.style.borderColor     = '';
    pwConf.style.borderColor = '';

    // Check form validity
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Check password match
    if (pw.value !== pwConf.value) {
        errDiv.style.display     = 'block';
        pw.style.borderColor     = '#dc2626';
        pwConf.style.borderColor = '#dc2626';
        pwConf.focus();
        return;
    }

    // Submit via fetch
    const data = new FormData(form);
    const btn  = event.target;
    btn.disabled    = true;
    btn.innerHTML   = '<i class="fas fa-spinner fa-spin"></i> Creating...';

    fetch('/arams/api/add_lecturer.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast(res.message, 'success');
                closeModal();
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'error'))
        .finally(() => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
        });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>