<?php
// ============================================================
//  ARAMS — Lecturer Research Management
// ============================================================
$pageTitle  = 'Research Management';
$activePage = 'research';
require_once __DIR__ . '/../../includes/header.php';

$db    = getDB();
$lecId = (int)$user['lecturer_id'];
$tab   = $_GET['tab'] ?? 'publications';

// Fetch all records per type
$pubs = $db->prepare(
    "SELECT p.*, rd.status, rd.submission_date, rd.remarks
     FROM tbl_publication p JOIN tbl_research_data rd ON p.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY p.pub_year DESC, rd.submission_date DESC"
);
$pubs->execute([$lecId]); $publications = $pubs->fetchAll();

$grts = $db->prepare(
    "SELECT g.*, rd.status, rd.submission_date, rd.remarks
     FROM tbl_grant g JOIN tbl_research_data rd ON g.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY g.start_date DESC"
);
$grts->execute([$lecId]); $grants = $grts->fetchAll();

$hidx = $db->prepare(
    "SELECT h.*, rd.status, rd.submission_date
     FROM tbl_hindex h JOIN tbl_research_data rd ON h.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY h.record_year DESC"
);
$hidx->execute([$lecId]); $hindexes = $hidx->fetchAll();

$ips = $db->prepare(
    "SELECT ip.*, rd.status, rd.submission_date
     FROM tbl_ip_record ip JOIN tbl_research_data rd ON ip.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY ip.filing_date DESC"
);
$ips->execute([$lecId]); $iprecs = $ips->fetchAll();

$incs = $db->prepare(
    "SELECT inc.*, rd.status, rd.submission_date
     FROM tbl_research_income inc JOIN tbl_research_data rd ON inc.data_id = rd.data_id
     WHERE rd.lecturer_id = ? ORDER BY inc.year_received DESC"
);
$incs->execute([$lecId]); $incomes = $incs->fetchAll();

$counts = [
    'publications' => count($publications),
    'grants'       => count($grants),
    'hindex'       => count($hindexes),
    'ip'           => count($iprecs),
    'income'       => count($incomes),
];

$badgeMap = ['Approved'=>'badge-green','Pending'=>'badge-yellow','Rejected'=>'badge-red'];
?>

<div class="page-header-row">
    <div class="page-header" style="margin:0">
        <h1>Research Management</h1>
        <p>Manage all your research submissions</p>
    </div>
    <button class="btn btn-teal" onclick="openAddModal()">
        <i class="fas fa-plus"></i> Add Record
    </button>
</div>

<!-- Tabs -->
<div class="tabs" id="researchTabs">
    <?php
    $tabs = [
        'publications' => ['icon'=>'fas fa-file-alt',   'label'=>'Publications'],
        'grants'       => ['icon'=>'fas fa-trophy',      'label'=>'Grants'],
        'hindex'       => ['icon'=>'fas fa-chart-line',  'label'=>'H-Index'],
        'ip'           => ['icon'=>'fas fa-lightbulb',   'label'=>'IP Records'],
        'income'       => ['icon'=>'fas fa-dollar-sign', 'label'=>'Income'],
    ];
    foreach ($tabs as $tid => $tinfo): ?>
    <button class="tab-btn <?= $tab === $tid ? 'active' : '' ?>"
            onclick="switchResTab('<?= $tid ?>', this)">
        <i class="<?= $tinfo['icon'] ?>"></i>
        <?= $tinfo['label'] ?>
        <span class="tab-count"><?= $counts[$tid] ?></span>
    </button>
    <?php endforeach; ?>
</div>

<div class="search-row">
    <input type="text" class="search-input" placeholder="Search records…" oninput="filterActiveTab(this)">
    <select class="filter-select" onchange="filterActiveStatus(this)">
        <option value="">All Status</option>
        <option>Approved</option><option>Pending</option><option>Rejected</option>
    </select>
</div>

<!-- PUBLICATIONS TAB -->
<div class="tab-panel" id="panel-publications" style="<?= $tab==='publications' ? '' : 'display:none' ?>">
    <?php if (empty($publications)): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> No publications yet. Click "Add Record" to submit your first publication.</div>
    <?php else: ?>
    <?php foreach ($publications as $p): ?>
    <div class="pub-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem">
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:5px;flex-wrap:wrap">
                    <span class="badge badge-blue"><?= htmlspecialchars($p['pub_type']) ?></span>
                    <span class="badge badge-teal"><?= htmlspecialchars($p['indexing_type']) ?></span>
                    <?php if ($p['quartile'] !== 'N/A'): ?>
                    <span class="badge badge-purple"><?= $p['quartile'] ?></span>
                    <?php endif; ?>
                    <span class="badge <?= $badgeMap[$p['status']] ?>"><?= $p['status'] ?></span>
                </div>
                <div class="pub-title"><?= htmlspecialchars($p['title']) ?></div>
                <div class="pub-meta">
                    <?= htmlspecialchars($p['journal_name'] ?? '') ?>
                    <?= $p['pub_year'] ? ' • ' . $p['pub_year'] : '' ?>
                    <?= $p['doi'] ? ' • <a href="https://doi.org/' . htmlspecialchars($p['doi']) . '" target="_blank" style="color:var(--teal)">DOI</a>' : '' ?>
                </div>
                <?php if ($p['status'] === 'Rejected' && $p['remarks']): ?>
                <div class="alert alert-danger" style="margin-top:6px;padding:5px 10px;font-size:12px">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($p['remarks']) ?>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- GRANTS TAB -->
<div class="tab-panel" id="panel-grants" style="<?= $tab==='grants' ? '' : 'display:none' ?>">
    <div class="card">
        <div class="table-wrap">
            <table class="arams-table" id="resTable">
                <thead><tr>
                    <th>Grant Title</th><th>Funder</th><th>Amount (RM)</th>
                    <th>Role</th><th>Period</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($grants as $g): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($g['grant_title']) ?></div>
                        <?php if ($g['grant_code']): ?>
                        <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($g['grant_code']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($g['funder'] ?? '') ?></td>
                    <td><?= $g['amount'] ? number_format((float)$g['amount'],2) : '—' ?></td>
                    <td><span class="badge <?= $g['role']==='PI' ? 'badge-blue' : 'badge-grey' ?>"><?= $g['role'] ?></span></td>
                    <td style="font-size:12px"><?= $g['start_date'] ?? '—' ?><br><?= $g['end_date'] ?? '' ?></td>
                    <td><span class="badge <?= $badgeMap[$g['status']] ?? 'badge-grey' ?>"><?= $g['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($grants)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No grants yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- H-INDEX TAB -->
<div class="tab-panel" id="panel-hindex" style="<?= $tab==='hindex' ? '' : 'display:none' ?>">
    <?php if (!empty($hindexes)): ?>
    <div class="kpi-card bg-uthm" style="margin-bottom:1rem;display:inline-block;min-width:180px">
        <i class="fas fa-chart-line"></i>
        <div class="kpi-val"><?= $hindexes[0]['hindex_value'] ?></div>
        <div class="kpi-label">Current H-Index (<?= $hindexes[0]['source'] ?>)</div>
        <div class="kpi-chg">As of <?= $hindexes[0]['record_year'] ?></div>
    </div>
    <?php endif; ?>
    <div class="card">
        <div class="table-wrap">
            <table class="arams-table">
                <thead><tr><th>Year</th><th>H-Index</th><th>Citations</th><th>Source</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($hindexes as $h): ?>
                <tr>
                    <td><?= $h['record_year'] ?></td>
                    <td style="font-weight:700;font-size:16px;color:var(--blue)"><?= $h['hindex_value'] ?></td>
                    <td><?= $h['citation_count'] !== null ? number_format($h['citation_count']) : '—' ?></td>
                    <td><?= htmlspecialchars($h['source']) ?></td>
                    <td><span class="badge <?= $badgeMap[$h['status']] ?>"><?= $h['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($hindexes)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No H-Index records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- IP TAB -->
<div class="tab-panel" id="panel-ip" style="<?= $tab==='ip' ? '' : 'display:none' ?>">
    <div class="card">
        <div class="table-wrap">
            <table class="arams-table">
                <thead><tr><th>IP Title</th><th>Type</th><th>IP Number</th><th>Country</th><th>Registration</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($iprecs as $ip): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($ip['ip_title']) ?></td>
                    <td><span class="badge badge-orange"><?= htmlspecialchars($ip['ip_type']) ?></span></td>
                    <td><?= htmlspecialchars($ip['ip_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($ip['country'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($ip['registration_status']) ?></td>
                    <td><span class="badge <?= $badgeMap[$ip['status']] ?>"><?= $ip['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($iprecs)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No IP records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- INCOME TAB -->
<div class="tab-panel" id="panel-income" style="<?= $tab==='income' ? '' : 'display:none' ?>">
    <div class="card">
        <div class="table-wrap">
            <table class="arams-table">
                <thead><tr><th>Source</th><th>Category</th><th>Amount (RM)</th><th>Year</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($incomes as $inc): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($inc['source']) ?></td>
                    <td><?= htmlspecialchars($inc['income_category']) ?></td>
                    <td style="font-weight:600;color:var(--green)"><?= number_format((float)$inc['amount'],2) ?></td>
                    <td><?= $inc['year_received'] ?></td>
                    <td><span class="badge <?= $badgeMap[$inc['status']] ?>"><?= $inc['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($incomes)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No income records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($incomes)): ?>
        <div class="stat-row" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
            <div class="stat-item">
                <span>Total Income</span>
                <strong>RM <?= number_format(array_sum(array_column($incomes,'amount')),2) ?></strong>
            </div>
            <div class="stat-item">
                <span>Records</span>
                <strong><?= count($incomes) ?></strong>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Show correct tab panel based on server-rendered initial tab
const panels = document.querySelectorAll('.tab-panel');

let activeResTab = '<?= $tab ?>';

function switchResTab(tabId, btn) {
    activeResTab = tabId;
    document.querySelectorAll('#researchTabs .tab-btn').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    panels.forEach(p => p.style.display = 'none');
    const target = document.getElementById('panel-' + tabId);
    if (target) target.style.display = 'block';
    // reset search when switching tabs
    const s = document.querySelector('.search-input'); if (s) s.value = '';
    const sel = document.querySelector('.filter-select'); if (sel) sel.value = '';
}

function _activePanel() { return document.getElementById('panel-' + activeResTab); }

function filterActiveTab(input) {
    const q = input.value.toLowerCase();
    const panel = _activePanel(); if (!panel) return;
    const cards = panel.querySelectorAll('.pub-card');
    if (cards.length) {
        cards.forEach(c => { c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none'; });
    } else {
        panel.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }
}

function filterActiveStatus(sel) {
    const q = sel.value.toLowerCase();
    const panel = _activePanel(); if (!panel) return;
    const match = el => !q || el.textContent.toLowerCase().includes(q);
    const cards = panel.querySelectorAll('.pub-card');
    if (cards.length) {
        cards.forEach(c => { c.style.display = match(c) ? '' : 'none'; });
    } else {
        panel.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.querySelector('td[colspan]')) return;
            tr.style.display = match(tr) ? '' : 'none';
        });
    }
}

function openAddModal() {
    openModal(`
        <div class="modal-header">
            <h3 class="modal-title">Add Research Record</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="tabs" style="margin-bottom:1rem" id="addTabs">
            <button class="tab-btn active" onclick="switchAddForm('pub',this)"><i class="fas fa-file-alt"></i> Publication</button>
            <button class="tab-btn" onclick="switchAddForm('grant',this)"><i class="fas fa-trophy"></i> Grant</button>
            <button class="tab-btn" onclick="switchAddForm('hindex',this)"><i class="fas fa-chart-line"></i> H-Index</button>
            <button class="tab-btn" onclick="switchAddForm('ip',this)"><i class="fas fa-lightbulb"></i> IP</button>
            <button class="tab-btn" onclick="switchAddForm('income',this)"><i class="fas fa-dollar-sign"></i> Income</button>
        </div>
        <div id="addFormArea">${pubForm()}</div>
        <button class="btn btn-teal btn-full" style="margin-top:1rem" onclick="submitAddForm()">
            <i class="fas fa-paper-plane"></i> Submit for Validation
        </button>`);
}

let currentFormType = 'pub';
function switchAddForm(type, btn) {
    currentFormType = type;
    document.querySelectorAll('#addTabs .tab-btn').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    const forms = { pub:pubForm, grant:grantForm, hindex:hindexForm, ip:ipForm, income:incomeForm };
    document.getElementById('addFormArea').innerHTML = (forms[type] || pubForm)();
}

function submitAddForm() {
    const typeMap = { pub:'publication', grant:'grant', hindex:'hindex', ip:'ip', income:'income' };
    const form = document.getElementById('addForm');
    if (!form || !form.checkValidity()) { form && form.reportValidity(); return; }
    const data = new FormData(form);
    data.append('type', typeMap[currentFormType]);
    fetch('/arams/api/submit_research.php', { method:'POST', body:data })
        .then(r=>r.json())
        .then(res => {
            if (res.success) { showToast(res.message,'success'); closeModal(); setTimeout(()=>location.reload(),1200); }
            else showToast(res.message,'error');
        });
}

function pubForm() { return `<form id="addForm" method="POST">
    <div class="form-group"><label class="form-label">Publication Title *</label>
    <input class="form-control" name="title" required placeholder="Full publication title"></div>
    <div class="form-group"><label class="form-label">Authors</label>
    <input class="form-control" name="authors" placeholder="Author 1, Author 2, ..."></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Journal / Conference *</label>
        <input class="form-control" name="journal_name" required></div>
        <div class="form-group"><label class="form-label">Year *</label>
        <input class="form-control" name="pub_year" type="number" value="${new Date().getFullYear()}" min="1990" max="2030" required></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Author Role</label>
        <select class="form-control" name="author_role">
            <option>UTHM - First Author</option>
            <option>Corresponding Author</option>
            <option>Penulis Dalam Bab</option>
            <option>Editor</option>
            <option selected>Co-Author</option>
        </select></div>
        <div class="form-group"><label class="form-label">Indexing</label>
        <select class="form-control" name="indexing_type">
            <option>Scopus</option><option>WoS</option><option>Scopus,WoS</option><option>MyCite</option><option>Others</option>
        </select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Quartile</label>
        <select class="form-control" name="quartile">
            <option>Q1</option><option>Q2</option><option>Q3</option><option>Q4</option><option selected>N/A</option>
        </select></div>
        <div class="form-group"><label class="form-label">Type</label>
        <select class="form-control" name="pub_type">
            <option>Journal</option>
            <option>Proceeding / Seminar</option>
            <option>Book Chapter</option>
            <option>Book</option>
        </select></div>
    </div>
    <div class="form-group"><label class="form-label">DOI</label>
    <input class="form-control" name="doi" placeholder="e.g. 10.1109/..."></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Student as Author?</label>
        <select class="form-control" name="student_author">
            <option value="0">No</option><option value="1">Yes</option>
        </select></div>
        <div class="form-group"><label class="form-label">Industries Collaboration?</label>
        <select class="form-control" name="industries_collaboration">
            <option value="0">No</option><option value="1">Yes</option>
        </select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">National Collaboration?</label>
        <select class="form-control" name="national_collaboration">
            <option value="0">No</option><option value="1">Yes</option>
        </select></div>
        <div class="form-group"><label class="form-label">International Collaboration?</label>
        <select class="form-control" name="international_collaboration">
            <option value="0">No</option><option value="1">Yes</option>
        </select></div>
    </div>
</form>`; }

function grantForm() { return `<form id="addForm" method="POST">
    <div class="form-group"><label class="form-label">Grant Title *</label>
    <input class="form-control" name="grant_title" required></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Grant Code</label>
        <input class="form-control" name="grant_code" placeholder="e.g. FRGS/1/2024/..."></div>
        <div class="form-group"><label class="form-label">Funder</label>
        <input class="form-control" name="funder" placeholder="e.g. MOHE, MOSTI"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Role</label>
        <select class="form-control" name="role"><option>PI</option><option>Co-I</option><option>Member</option></select></div>
        <div class="form-group"><label class="form-label">Amount (RM)</label>
        <input class="form-control" name="amount" type="number" step="0.01" min="0"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Start Date</label>
        <input class="form-control" name="start_date" type="date"></div>
        <div class="form-group"><label class="form-label">End Date</label>
        <input class="form-control" name="end_date" type="date"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Grant Level</label>
        <select class="form-control" name="grant_level">
            <option value="">— Select —</option>
            <option>Universiti</option>
            <option>National</option>
            <option>International</option>
            <option>NGO</option>
            <option>Industries</option>
        </select></div>
        <div class="form-group"><label class="form-label">Status</label>
        <select class="form-control" name="grant_status">
            <option>Active</option><option>Completed</option><option>Pending Approval</option>
        </select></div>
    </div>
    <div class="form-group"><label class="form-label">Grant Type / Category</label>
    <select class="form-control" name="grant_category">
        <optgroup label="Geran Universiti">
            <option>Tier 1</option><option>RE-GG</option><option>Contract</option>
            <option>GPPS</option><option>GPP</option><option>ICI</option>
            <option>UTHM Internal (VoT)</option>
        </optgroup>
        <optgroup label="Geran Kebangsaan">
            <option>FRGS</option><option>PRGS</option><option>TRGS</option>
            <option>LRGS</option><option>Geran Kontrak Kementerian</option>
            <option>Lain-Lain Geran Kebangsaan</option>
            <option>KKP</option><option>PPRN</option>
            <option>Sepadan RESIP</option><option>Sepadan MTUN</option>
        </optgroup>
        <optgroup label="Other Grants">
            <option>NGO</option><option>International</option><option>Industries</option><option>Others</option>
        </optgroup>
    </select></div>
</form>`; }

function hindexForm() { return `<form id="addForm" method="POST">
    <div class="form-row">
        <div class="form-group"><label class="form-label">Year *</label>
        <input class="form-control" name="record_year" type="number" value="${new Date().getFullYear()}" required></div>
        <div class="form-group"><label class="form-label">H-Index Value *</label>
        <input class="form-control" name="hindex_value" type="number" min="0" required></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Total Citations</label>
        <input class="form-control" name="citation_count" type="number" min="0"></div>
        <div class="form-group"><label class="form-label">Source</label>
        <select class="form-control" name="source"><option>Scopus</option><option>WoS</option><option>Google Scholar</option></select></div>
    </div>
    <div class="alert alert-info" style="font-size:12px"><i class="fas fa-info-circle"></i> Upload a screenshot from Scopus/WoS as proof. Admin will verify before approval.</div>
</form>`; }

function ipForm() { return `<form id="addForm" method="POST">
    <div class="form-group"><label class="form-label">IP Title *</label>
    <input class="form-control" name="ip_title" required></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Type</label>
        <select class="form-control" name="ip_type"><option>Patent</option><option>Copyright</option><option>Trademark</option><option>Industrial Design</option></select></div>
        <div class="form-group"><label class="form-label">IP Number (MyIPO)</label>
        <input class="form-control" name="ip_number" placeholder="e.g. PI2024XXXXXX"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Filing Date</label>
        <input class="form-control" name="filing_date" type="date"></div>
        <div class="form-group"><label class="form-label">Country</label>
        <select class="form-control" name="country">
            <option>Afghanistan</option><option>Albania</option><option>Algeria</option><option>Argentina</option><option>Australia</option><option>Austria</option><option>Bangladesh</option><option>Belgium</option><option>Brazil</option><option>Brunei</option><option>Cambodia</option><option>Canada</option><option>Chile</option><option>China</option><option>Colombia</option><option>Denmark</option><option>Egypt</option><option>Finland</option><option>France</option><option>Germany</option><option>Greece</option><option>Hong Kong</option><option>India</option><option>Indonesia</option><option>Iran</option><option>Iraq</option><option>Ireland</option><option>Italy</option><option>Japan</option><option>Jordan</option><option>Kenya</option><option>Kuwait</option><option>Laos</option><option selected>Malaysia</option><option>Maldives</option><option>Mexico</option><option>Myanmar</option><option>Nepal</option><option>Netherlands</option><option>New Zealand</option><option>Nigeria</option><option>Norway</option><option>Oman</option><option>Pakistan</option><option>Philippines</option><option>Poland</option><option>Portugal</option><option>Qatar</option><option>Russia</option><option>Saudi Arabia</option><option>Singapore</option><option>South Africa</option><option>South Korea</option><option>Spain</option><option>Sri Lanka</option><option>Sweden</option><option>Switzerland</option><option>Taiwan</option><option>Thailand</option><option>Turkey</option><option>United Arab Emirates</option><option>United Kingdom</option><option>United States</option><option>Vietnam</option><option>Yemen</option>
        </select></div>
    </div>
    <div class="form-group"><label class="form-label">Registration Status</label>
    <select class="form-control" name="registration_status"><option>Filed</option><option>Pending</option><option>Granted</option><option>Registered</option></select></div>
</form>`; }

function incomeForm() { return `<form id="addForm" method="POST">
    <div class="form-group"><label class="form-label">Income Source *</label>
    <input class="form-control" name="source" required placeholder="e.g. MOHE Research Grant, Industry Collaboration"></div>
    <div class="form-row">
        <div class="form-group"><label class="form-label">Amount (RM) *</label>
        <input class="form-control" name="amount" type="number" step="0.01" min="0" required></div>
        <div class="form-group"><label class="form-label">Year Received *</label>
        <input class="form-control" name="year_received" type="number" value="${new Date().getFullYear()}" required></div>
    </div>
    <div class="form-group"><label class="form-label">Category</label>
    <select class="form-control" name="income_category">
        <option>Research Grant</option><option>Consultancy</option><option>Contract Research</option>
        <option>Commercialisation</option><option>Training</option><option>Others</option>
    </select></div>
</form>`; }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>