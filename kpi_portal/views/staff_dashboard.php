<?php
require_once __DIR__ . '/../app/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Return metric monthly target (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['metric_target']) && isset($_GET['metric_id'])) {
    $metricId = (int)$_GET['metric_id'];
    // return unified target_value (prefer target_value column)
    $stmt = $pdo->prepare("SELECT COALESCE(target_value, 0) AS target_value FROM metrics WHERE id = ?");
    $stmt->execute([$metricId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['target_value' => $row ? (float)$row['target_value'] : 0]);
    exit;
}

// Fetch user's full name and email
$stmt = $pdo->prepare("SELECT full_name, email, department_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$full_name = $user['full_name'] ?? ($_SESSION['full_name'] ?? 'User');
$email = $user['email'] ?? ($_SESSION['email'] ?? '');
$dept_id = $user['department_id'] ?? ($_SESSION['department_id'] ?? null);

// Fetch available metrics for staff’s department
$stmt = $pdo->prepare("SELECT id, name, COALESCE(target_value, 0) AS target_value FROM metrics WHERE department_id = ?");
$stmt->execute([$dept_id]);
$metrics = $stmt->fetchAll();

// Also prepare assigned_kpis (same data, convenient for UI)
$assignedKpis = $metrics;

// Handle weekly KPI submission (with proof upload/link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_weekly_kpi'])) {
    $metric_id = (int)($_POST['metric_id'] ?? 0);
    $month = (int)($_POST['month'] ?? 0);
    $year = (int)($_POST['year'] ?? 0);
    $week = (int)($_POST['week'] ?? 0);
    $value = $_POST['value'] ?? null;
    $proof_link = trim($_POST['proof_link'] ?? '');

    // basic validations
    if (!$metric_id || !$month || !$year || !$week || $value === null || $value === '') {
        $message = "Please fill all required fields.";
    } else {
        // prevent duplicate
        $check = $pdo->prepare("SELECT id FROM weekly_kpis WHERE user_id = ? AND metric_id = ? AND month = ? AND year = ? AND week = ?");
        $check->execute([$user_id, $metric_id, $month, $year, $week]);

        if ($check->fetch()) {
            $message = "You’ve already submitted for Week $week of this month.";
        } else {
            // handle file upload (optional)
            $proof_file_path = null;
            if (!empty($_FILES['proof_file']) && $_FILES['proof_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['proof_file'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $allowedExt = ['pdf','png','jpg','jpeg','xls','xlsx','doc','docx'];
                    $origName = $file['name'];
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExt)) {
                        $message = "Invalid file type. Allowed: pdf, png, jpg, jpeg, xls, xlsx, doc, docx.";
                    } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
                        $message = "File too large. Max 10MB allowed.";
                    } else {
                        $uploadDir = __DIR__ . '/uploads/proofs';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', pathinfo($origName, PATHINFO_FILENAME));
                        $newName = $user_id . '_' . time() . '_' . $safeName . '.' . $ext;
                        $dest = $uploadDir . '/' . $newName;
                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            $proof_file_path = 'uploads/proofs/' . $newName; // store relative path
                        } else {
                            $message = "Failed to move uploaded file.";
                        }
                    }
                } else {
                    $message = "File upload error.";
                }
            }

            // decide what to store in DB 'proof' column: file path if uploaded else link (if provided)
            $proof = null;
            if (!empty($proof_file_path)) {
                $proof = $proof_file_path;
            } elseif (!empty($proof_link)) {
                $proof = $proof_link;
            }

            // if no error so far, insert record
            if (!isset($message) || strpos($message, 'Invalid') === false) {
                $insert = $pdo->prepare("
                    INSERT INTO weekly_kpis (user_id, metric_id, month, year, week, value, proof)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $ok = $insert->execute([$user_id, $metric_id, $month, $year, $week, $value, $proof]);
                if ($ok) {
                    $message = "Week $week KPI submitted successfully.";
                    header("Location: staff_dashboard.php");
                    exit();
                } else {
                    $message = "Error submitting KPI.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - Covenant University KPI Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cu-purple: #4B0082;
            --cu-green: #228B22;
        }

        body {
            background-color: #f7f9fc;
            font-family: 'Segoe UI', sans-serif;
        }

        .navbar {
            background-color: var(--cu-purple);
        }

        .navbar .nav-link,
        .navbar .navbar-brand {
            color: #fff !important;
        }

        .navbar .nav-link:hover {
            color: var(--cu-green) !important;
        }

        h3 {
            color: var(--cu-purple);
            font-weight: 600;
        }

        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card-header {
            font-weight: 600;
        }

        .card-header.bg-primary {
            background-color: var(--cu-purple) !important;
        }

        .card-header.bg-secondary {
            background-color: var(--cu-green) !important;
        }

        .card-header.bg-info {
            background-color: #69359c !important;
        }

        .btn-success {
            background-color: var(--cu-green);
            border: none;
        }

        .btn-success:hover {
            background-color: #1a6f1a;
        }

        .table thead {
            background-color: var(--cu-purple);
            color: white;
        }

        .table tbody tr:hover {
            background-color: #f0f0f8;
        }

        .alert-info {
            background-color: #e9d8fd;
            border-left: 5px solid var(--cu-purple);
            color: #3a0066;
        }

        footer {
            background: var(--cu-purple);
            color: #fff;
            text-align: center;
            padding: 1rem;
            margin-top: 3rem;
        }

        /* ICON NAVIGATION STYLES */
        .icon-nav { display:flex; gap:12px; margin:18px 0; flex-wrap:wrap; }
        .icon-card { flex:0 0 auto; width:150px; text-align:center; padding:12px; border-radius:10px; cursor:pointer; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:transform .12s, box-shadow .12s; }
        .icon-card:hover { transform:translateY(-4px); box-shadow:0 6px 18px rgba(0,0,0,0.09); }
        .icon-card.active { border:2px solid var(--cu-purple); }
        .icon-emoji { font-size:28px; display:block; margin-bottom:6px; }
        @media(max-width:600px){ .icon-card { width:48%; } }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">Covenant University KPI Portal</a>
        <div class="navbar-nav ms-auto">
            <span class="nav-link">👤 <?= htmlspecialchars($full_name) ?></span>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h3>Welcome, <?= htmlspecialchars($full_name) ?> 👋</h3>

    <?php if (isset($message)): ?>
        <div class="alert alert-info mt-3"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- ICON NAVIGATION (clickable sections) -->
    <style>
        .icon-nav { display:flex; gap:12px; margin:18px 0; flex-wrap:wrap; }
        .icon-card { flex:0 0 auto; width:150px; text-align:center; padding:12px; border-radius:10px; cursor:pointer; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:transform .12s, box-shadow .12s; }
        .icon-card:hover { transform:translateY(-4px); box-shadow:0 6px 18px rgba(0,0,0,0.09); }
        .icon-card.active { border:2px solid var(--cu-purple); }
        .icon-emoji { font-size:28px; display:block; margin-bottom:6px; }
        @media(max-width:600px){ .icon-card { width:48%; } }
    </style>

    <div class="icon-nav" role="tablist" aria-label="Dashboard sections">
        <div class="icon-card active" data-target="assignedKpisSection" role="tab" tabindex="0">
            <span class="icon-emoji">📋</span>
            <strong>Your KPIs</strong>
            <div class="small text-muted">Monthly targets</div>
        </div>
        <div class="icon-card" data-target="submitWeeklySection" role="tab" tabindex="0">
            <span class="icon-emoji">📅</span>
            <strong>Submit Weekly</strong>
            <div class="small text-muted">Add weekly entry & proof</div>
        </div>
        <div class="icon-card" data-target="weeklyEntriesSection" role="tab" tabindex="0">
            <span class="icon-emoji">📝</span>
            <strong>Your Entries</strong>
            <div class="small text-muted">Weekly records</div>
        </div>
        <div class="icon-card" data-target="monthlySummarySection" role="tab" tabindex="0">
            <span class="icon-emoji">📈</span>
            <strong>Monthly Summary</strong>
            <div class="small text-muted">Progress vs targets</div>
        </div>
    </div>

    <!-- MONTH/YEAR FILTER (for Weekly Entries & Monthly Summary) -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <select id="month_select" class="form-select w-auto">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>>
                    <?= date('F', mktime(0,0,0,$m,1)) ?>
                </option>
            <?php endfor; ?>
        </select>

        <select id="year_select" class="form-select w-auto">
            <?php for ($y = 2023; $y <= date('Y') + 1; $y++): // include next year ?>
                <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button id="apply_month_filter" class="btn btn-outline-primary">Apply</button>
        <div class="text-muted small ms-2">Filter entries shown in "Your Entries" and "Monthly Summary".</div>
    </div>

    <!-- SECTIONS (Assigned KPIs first) -->
    <div id="assignedKpisSection" class="dashboard-section">
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">📋 Your Assigned KPIs (Monthly Targets)</div>
            <div class="card-body">
                <?php if (!empty($assignedKpis)): ?>
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Monthly Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedKpis as $kpi): ?>
                                <tr>
                                    <td><?= htmlspecialchars($kpi['name']) ?></td>
                                    <td><?= htmlspecialchars(number_format((float)$kpi['target_value'], 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">No KPIs assigned to your department yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="submitWeeklySection" class="dashboard-section d-none">
        <!-- Submit Weekly KPI Form -->
        <div class="card mt-4 mb-4">
            <div class="card-header bg-primary text-white">📅 Submit Weekly KPI</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Metric</label>
                            <select id="metric_select" name="metric_id" class="form-select" required>
                                <option value="">Select Metric</option>
                                <?php foreach ($metrics as $metric): ?>
                                    <option value="<?= $metric['id'] ?>" data-target="<?= isset($metric['monthly_target']) ? (float)$metric['monthly_target'] : 0 ?>">
                                        <?= htmlspecialchars($metric['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-select" required>
                                <?php for ($m=1; $m<=12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0,0,0,$m,1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Year</label>
                            <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Week</label>
                            <select name="week" class="form-select" required>
                                <option value="">Select</option>
                                <option value="1">Week 1</option>
                                <option value="2">Week 2</option>
                                <option value="3">Week 3</option>
                                <option value="4">Week 4</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Value</label>
                            <input type="number" step="0.01" name="value" class="form-control" required>
                        </div>

                        <div class="col-md-1">
                            <button type="submit" name="submit_weekly_kpi" class="btn btn-success w-100">Save</button>
                        </div>

                        <!-- Proof inputs -->
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Proof (file)</label>
                            <input type="file" name="proof_file" accept=".pdf,.png,.jpg,.jpeg,.xls,.xlsx,.doc,.docx" class="form-control">
                            <div class="form-text">Allowed types: pdf, png, jpg, jpeg, xls, xlsx, doc, docx. Max 10MB.</div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <label class="form-label">Proof (link)</label>
                            <input type="url" name="proof_link" class="form-control" placeholder="Optional web link to proof (e.g. Google Drive link)">
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="weeklyEntriesSection" class="dashboard-section d-none">
        <!-- Weekly KPI Table -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">Your Weekly KPI Entries</div>
            <div class="card-body">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Month/Year</th>
                            <th>Week</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody id="weekly_kpis_body">
                        <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="monthlySummarySection" class="dashboard-section d-none">
        <!-- Monthly Summary -->
        <div class="card">
            <div class="card-header bg-info text-white">📈 Monthly KPI Summary</div>
            <div class="card-body">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Month/Year</th>
                            <th>Total Achieved</th>
                            <th>Target</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="monthly_summary_body">
                        <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<footer>
    © <?= date('Y') ?> Covenant University | KPI Portal
</footer>

<!-- SECTION TOGGLING SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // wire icon nav to show/hide sections
    const icons = document.querySelectorAll('.icon-card');
    const sections = document.querySelectorAll('.dashboard-section');

    function showSection(id) {
        sections.forEach(s => s.classList.add('d-none'));
        const el = document.getElementById(id);
        if (el) el.classList.remove('d-none');
        icons.forEach(ic => ic.classList.toggle('active', ic.getAttribute('data-target') === id));
        // reload data when showing entries/summary
        if (id === 'weeklyEntriesSection' || id === 'monthlySummarySection') {
            const month = document.getElementById('month_select')?.value || new Date().getMonth()+1;
            loadKpiData(month);
        }
    }

    icons.forEach(ic => {
        ic.addEventListener('click', () => showSection(ic.getAttribute('data-target')));
        ic.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showSection(ic.getAttribute('data-target')); } });
    });

    // default shown section (Assigned KPIs)
    showSection('assignedKpisSection');

    // existing KPI loader and month select logic (kept unchanged)...
    const monthSelect = document.getElementById('month_select');
    if (monthSelect) {
        monthSelect.addEventListener('change', function() {
            const active = document.querySelector('.icon-card.active')?.getAttribute('data-target');
            if (active === 'weeklyEntriesSection' || active === 'monthlySummarySection') {
                loadKpiData(this.value);
            }
        });
    }

    // existing loadKpiData function is already present lower in the file and will be used as-is.
});
</script>

<!-- AJAX Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthSelect = document.getElementById('month_select');
    const currentMonth = monthSelect.value || new Date().getMonth() + 1;
    loadKpiData(currentMonth);

    monthSelect.addEventListener('change', function() {
        if (this.value) loadKpiData(this.value);
    });

    function loadKpiData(month) {
        fetch(`app/fetch_kpi_data.php?month=${month}`)
            .then(res => res.json())
            .then(data => {
                // Weekly KPIs
                const weeklyBody = document.getElementById('weekly_kpis_body');
                weeklyBody.innerHTML = '';
                if (data.weekly && data.weekly.length > 0) {
                    data.weekly.forEach(row => {
                        weeklyBody.innerHTML += `
                            <tr>
                                <td>${row.metric_name}</td>
                                <td>${row.month_name} ${row.year}</td>
                                <td>Week ${row.week}</td>
                                <td>${row.value}</td>
                            </tr>`;
                    });
                } else {
                    weeklyBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No entries found for this month.</td></tr>`;
                }

                // Monthly Summary
                const monthlyBody = document.getElementById('monthly_summary_body');
                monthlyBody.innerHTML = '';
                if (data.monthly && data.monthly.length > 0) {
                    data.monthly.forEach(row => {
                        const status = (parseFloat(row.total_value) >= parseFloat(row.target_value)) ? 
                            '<span class="text-success fw-bold">✅ Met</span>' : 
                            '<span class="text-danger fw-bold">⚠️ Below</span>';
                        monthlyBody.innerHTML += `
                            <tr>
                                <td>${row.metric_name}</td>
                                <td>${row.month_name} ${row.year}</td>
                                <td>${row.total_value}</td>
                                <td>${row.target_value}</td>
                                <td>${status}</td>
                            </tr>`;
                    });
                } else {
                    monthlyBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No summary for this month.</td></tr>`;
                }
            })
            .catch(err => console.error(err));
    }

    // auto-fetch metric monthly target when metric changes
    // (removed — monthly target input removed from submit form)
    // if you still need to fetch a target via AJAX for other UI, use:
    // fetch(`staff_dashboard.php?metric_target=1&metric_id=${metricId}`).then(...
});
</script>
</body>
</html>
