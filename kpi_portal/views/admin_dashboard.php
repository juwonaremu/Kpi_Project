<?php
declare(strict_types=1);
// admin_dashboard.php (C3 modern dashboard)
// Place in /kpi_portal/views/admin_dashboard.php
// Expects: require_once __DIR__ . '/../app/config.php'; providing $pdo
// Session/login should set $_SESSION['user_id'], $_SESSION['role'], $_SESSION['full_name']

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../app/config.php';

// --- simple auth ---
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin','super_admin'], true)) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $projectRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    header('Location: ' . $proto . '://' . $host . $projectRoot . '/views/login.php');
    exit();
}
header_remove('X-Powered-By');
function send_json($d){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($d); exit; }
function safe_int($v){ return is_numeric($v) ? (int)$v : null; }

// ------------------ API endpoints ------------------

// Add staff (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    $department_id = safe_int($_POST['department_id'] ?? '');
    $unit = safe_int($_POST['unit'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        send_json(['success'=>false,'message'=>'Missing required fields']);
    }

    // check email or staff_id exists
    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR staff_id = ?");
    $chk->execute([$email, $staff_id]);
    if ((int)$chk->fetchColumn() > 0) send_json(['success'=>false,'message'=>'Email or Staff ID already exists']);

    $pw = password_hash($password, PASSWORD_DEFAULT);
    $full = $first . ' ' . $last;
    $ins = $pdo->prepare("INSERT INTO users (first_name,last_name,full_name,staff_id,email,password,role,department_id,unit,is_approved) VALUES (?,?,?,?,?,?,?,?,?,0)");
    $ins->execute([$first,$last,$full,$staff_id,$email,$pw,'staff',$department_id,$unit]);
    send_json(['success'=>true,'id'=>$pdo->lastInsertId()]);
}

// Edit staff (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_staff'])) {
    $id = safe_int($_POST['edit_staff'] ?? 0);
    if (!$id) send_json(['success'=>false,'message'=>'Invalid id']);
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $staff_id = trim($_POST['staff_id'] ?? '');
    $department_id = safe_int($_POST['department_id'] ?? '');
    $unit = safe_int($_POST['unit'] ?? '');
    $role = trim($_POST['role'] ?? 'staff');

    if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json(['success'=>false,'message'=>'Missing required fields']);
    }

    // prevent duplicate email/staff_id (other users)
    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (email = ? OR staff_id = ?) AND id != ?");
    $chk->execute([$email,$staff_id,$id]);
    if ((int)$chk->fetchColumn() > 0) send_json(['success'=>false,'message'=>'Email or Staff ID used by another user']);

    $full = $first . ' ' . $last;
    $upd = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, full_name=?, staff_id=?, email=?, role=?, department_id=?, unit=? WHERE id=?");
    $upd->execute([$first,$last,$full,$staff_id,$email,$role,$department_id,$unit,$id]);
    send_json(['success'=>true]);
}

// Delete staff (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff'])) {
    $id = safe_int($_POST['delete_staff'] ?? 0);
    if (!$id) send_json(['success'=>false,'message'=>'Invalid id']);
    if ((int)$_SESSION['user_id'] === $id) send_json(['success'=>false,'message'=>'Cannot delete yourself']);
    $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $del->execute([$id]);
    send_json(['success'=>true]);
}

// Approve user (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user'])) {
    $id = safe_int($_POST['approve_user'] ?? 0);
    if (!$id) send_json(['success'=>false,'message'=>'Invalid id']);
    $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?")->execute([$id]);
    send_json(['success'=>true]);
}

// Get units for a department (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_units_by_dept'])) {
    $dept = safe_int($_GET['get_units_by_dept'] ?? 0);
    if (!$dept) send_json([]);
    $stmt = $pdo->prepare("SELECT id, name FROM units WHERE department_id = ? ORDER BY name ASC");
    $stmt->execute([$dept]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send_json($rows);
}

// User summary (GET) — modal when staff name clicked
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user_summary']) && $_GET['user_summary'] == '1' && isset($_GET['user_id'])) {
    $userId = safe_int($_GET['user_id'] ?? 0);
    $month = max(1,(int)($_GET['month'] ?? date('n')));
    $year = max(2000,(int)($_GET['year'] ?? date('Y')));
    if (!$userId) send_json(['error'=>'invalid_user']);

    $stmt = $pdo->prepare("SELECT id, first_name, last_name, full_name, staff_id, email, department_id, unit FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) send_json(['error'=>'not_found']);

    // metrics and totals for this user in month/year (join metrics to show all dept metrics)
    $stmt = $pdo->prepare("
        SELECT m.id AS metric_id, m.name AS metric_name, COALESCE(m.target_value,0) AS target_value,
               COALESCE(SUM(r.total_value),0) AS total_value, COUNT(r.id) AS times_reported
        FROM metrics m
        LEFT JOIN reports r ON r.metric_id = m.id AND r.user_id = ? AND r.month = ? AND r.year = ?
        WHERE m.department_id = COALESCE(?, m.department_id) OR m.department_id IS NULL
        GROUP BY m.id, m.name, m.target_value
        ORDER BY m.name ASC
    ");
    $deptId = $user['department_id'] ?? null;
    $stmt->execute([$userId, $month, $year, $deptId]);
    $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send_json(['user'=>$user,'month'=>$month,'year'=>$year,'metrics'=>$metrics]);
}

// Department summary (AJAX & CSV)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['ajax_dept']) || isset($_GET['ajax_dept_csv']))) {
    $filterDept = safe_int($_GET['department'] ?? 0);
    $filterMonth = (int)($_GET['month'] ?? date('n'));
    $filterYear = (int)($_GET['year'] ?? date('Y'));

    if (!$filterDept || !$filterMonth || !$filterYear) {
        if (isset($_GET['ajax_dept'])) send_json([]);
        if (isset($_GET['ajax_dept_csv'])) {
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="dept_empty.csv"'); echo "metric_id,metric,target_value,staff_count,expected,total_entered,performance\n"; exit;
        }
    }

    // staff count in dept
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
    $stmt->execute([$filterDept]);
    $staffCount = (int)$stmt->fetchColumn();

    // metrics and totals where reports belong to users in this department
    $sql = "
        SELECT m.id AS metric_id, m.name AS metric_name, COALESCE(m.target_value,0) AS target_value,
               COALESCE(SUM(r.total_value),0) AS total_entered
        FROM metrics m
        LEFT JOIN reports r ON r.metric_id = m.id AND r.month = ? AND r.year = ?
        LEFT JOIN users ru ON ru.id = r.user_id AND ru.department_id = ?
        WHERE m.department_id = ?
        GROUP BY m.id, m.name, m.target_value
        ORDER BY m.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filterMonth, $filterYear, $filterDept, $filterDept]);
    $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($metrics as $m) {
        $expected = ((float)$m['target_value']) * $staffCount;
        $entered = (float)$m['total_entered'];
        $perf = ($expected > 0) ? round(($entered / $expected) * 100,2) : null;
        $rows[] = [
            'metric_id'=>(int)$m['metric_id'],
            'metric_name'=>$m['metric_name'],
            'target_value'=> (float)$m['target_value'],
            'staff_count'=>$staffCount,
            'expected_kpi'=>$expected,
            'total_entered'=>$entered,
            'performance_percent'=>$perf
        ];
    }

    if (isset($_GET['ajax_dept'])) {
        send_json(['department_id'=>$filterDept,'month'=>$filterMonth,'year'=>$filterYear,'staff_count'=>$staffCount,'metrics'=>$rows]);
    }

    if (isset($_GET['ajax_dept_csv'])) {
        $deptName = $pdo->prepare("SELECT name FROM departments WHERE id = ? LIMIT 1");
        $deptName->execute([$filterDept]);
        $dn = $deptName->fetchColumn() ?: 'dept_'.$filterDept;
        $fn = sprintf('dept_summary_%s_%04d_%02d.csv', preg_replace('/[^a-z0-9\-_]/i','_',$dn), $filterYear, $filterMonth);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'. $fn .'"');
        $out = fopen('php://output','w');
        fputcsv($out, ['metric_id','metric','target_value','staff_count','expected_kpi','total_entered','performance_percent']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['metric_id'],$r['metric_name'],$r['target_value'],$r['staff_count'],$r['expected_kpi'],$r['total_entered'],$r['performance_percent']]);
        }
        fclose($out); exit;
    }
}

// Unit summary (AJAX & CSV & trend)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['ajax_unit']) || isset($_GET['ajax_unit_csv']) || isset($_GET['unit_trend']))) {
    $filterDept = safe_int($_GET['department'] ?? 0);
    $filterUnit = safe_int($_GET['unit'] ?? 0);
    $filterMonth = (int)($_GET['month'] ?? date('n'));
    $filterYear = (int)($_GET['year'] ?? date('Y'));

    if (!$filterDept || !$filterUnit || !$filterMonth || !$filterYear) {
        if (isset($_GET['ajax_unit'])) send_json([]);
        if (isset($_GET['ajax_unit_csv'])) { header('Content-Type:text/csv'); header('Content-Disposition: attachment; filename="unit_empty.csv"'); echo "metric_id,metric,target_value,expected,total_entered\n"; exit; }
    }

    // staff count in unit
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND unit = ?");
    $stmt->execute([$filterDept, $filterUnit]);
    $staffCount = (int)$stmt->fetchColumn();

    // metrics for department and totals for users in that unit
    $sql = "
        SELECT m.id AS metric_id, m.name AS metric_name, COALESCE(m.target_value,0) AS target_value,
               COALESCE(SUM(r.total_value),0) AS total_entered
        FROM metrics m
        LEFT JOIN reports r ON r.metric_id = m.id AND r.month = ? AND r.year = ?
        LEFT JOIN users ru ON ru.id = r.user_id AND ru.department_id = ? AND ru.unit = ?
        WHERE m.department_id = ?
        GROUP BY m.id, m.name, m.target_value
        ORDER BY m.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filterMonth, $filterYear, $filterDept, $filterUnit, $filterDept]);
    $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($metrics as $m) {
        $expected = ((float)$m['target_value']) * $staffCount;
        $entered = (float)$m['total_entered'];
        $perf = ($expected > 0) ? round(($entered / $expected) * 100,2) : null;
        $rows[] = [
            'metric_id'=>(int)$m['metric_id'],
            'metric_name'=>$m['metric_name'],
            'target_value'=> (float)$m['target_value'],
            'staff_count'=>$staffCount,
            'expected_kpi'=>$expected,
            'total_entered'=>$entered,
            'performance_percent'=>$perf
        ];
    }

    if (isset($_GET['ajax_unit'])) {
        send_json(['department_id'=>$filterDept,'unit_id'=>$filterUnit,'month'=>$filterMonth,'year'=>$filterYear,'staff_count'=>$staffCount,'metrics'=>$rows]);
    }

    if (isset($_GET['ajax_unit_csv'])) {
        $unitName = $pdo->prepare("SELECT name FROM units WHERE id = ? LIMIT 1"); $unitName->execute([$filterUnit]); $un = $unitName->fetchColumn() ?: 'unit_'.$filterUnit;
        $fn = sprintf('unit_summary_%s_%04d_%02d.csv', preg_replace('/[^a-z0-9\-_]/i','_',$un), $filterYear, $filterMonth);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="'. $fn .'"');
        $out = fopen('php://output','w');
        fputcsv($out, ['metric_id','metric','target_value','staff_count','expected_kpi','total_entered','performance_percent']);
        foreach ($rows as $r) fputcsv($out, [$r['metric_id'],$r['metric_name'],$r['target_value'],$r['staff_count'],$r['expected_kpi'],$r['total_entered'],$r['performance_percent']]);
        fclose($out); exit;
    }

    // unit trend across months (if requested)
    if (isset($_GET['unit_trend'])) {
        // expects metric_id param
        $metricId = safe_int($_GET['metric_id'] ?? 0);
        if (!$metricId) send_json([]);
        // return last 6 months totals for that metric for users in unit
        $months = [];
        $labels = [];
        for ($i=5;$i>=0;$i--) {
            $dt = new DateTime("first day of -{$i} months");
            $m = (int)$dt->format('n'); $y = (int)$dt->format('Y');
            $labels[] = $dt->format('M Y');
            $months[] = ['m'=>$m,'y'=>$y];
        }
        $data = [];
        $q = $pdo->prepare("SELECT COALESCE(SUM(total_value),0) FROM reports r INNER JOIN users u ON u.id = r.user_id WHERE r.metric_id = ? AND r.month = ? AND r.year = ? AND u.department_id = ? AND u.unit = ?");
        foreach ($months as $mm) {
            $q->execute([$metricId,$mm['m'],$mm['y'],$filterDept,$filterUnit]);
            $data[] = (float)$q->fetchColumn();
        }
        send_json(['labels'=>$labels,'data'=>$data]);
    }
}

// ------------------ End API ------------------
// ---------- Page rendering: fetch lists used by page ----------
$departments = $pdo->query("SELECT id,name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$units_all = $pdo->query("SELECT id,name,department_id FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$metrics = $pdo->query("SELECT id,name,target_value,department_id FROM metrics ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Staff list simplified (name clickable, staff_id, email, department)
// Note: no global department filter in header per your request — each section has own filters.
$stmt = $pdo->query("
    SELECT u.id, COALESCE(u.full_name, CONCAT(u.first_name,' ',u.last_name)) AS full_name, u.staff_id, u.email, d.name AS department
    FROM users u LEFT JOIN departments d ON u.department_id = d.id
    WHERE (u.role IN ('staff','employee','user') OR u.role IS NOT NULL)
    ORDER BY full_name ASC
");
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Unit summary initial (empty) - will be requested via AJAX when filters are chosen
$unitSummary = []; // fetched client-side

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Dashboard - KPI Portal</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--accent:#3b82f6;--muted:#6b7280}
body{font-family:Inter,Segoe UI,Arial,Helvetica;background:#f3f4f6;color:#111}
.navbar-brand{font-weight:700;color:var(--accent)}
.app-shell{display:flex;min-height:100vh}
.sidebar{width:72px;background:#0f172a;color:#fff;padding-top:14px;display:flex;flex-direction:column;align-items:center;gap:8px}
.sidebar .nav-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#94a3b8}
.sidebar .nav-icon.active{background:#0b1220;color:#fff;box-shadow:0 4px 14px rgba(2,6,23,0.6)}
.main-area{flex:1;padding:18px}
.header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}
.cards-row{display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.card-stat{flex:1;min-width:180px;border-radius:12px;background:#fff;padding:12px;box-shadow:0 6px 18px rgba(15,23,42,0.06)}
.content-section{background:#fff;border-radius:12px;padding:14px;box-shadow:0 6px 18px rgba(15,23,42,0.04);margin-bottom:14px}
.table a{cursor:pointer}
.small-muted{color:var(--muted);font-size:.95rem}
.form-inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
@media(max-width:900px){.sidebar{display:none}}
</style>
</head>
<body>

<div class="app-shell">
    <!-- thin icon sidebar -->
    <aside class="sidebar" role="navigation" aria-label="Main nav">
        <div class="nav-icon" data-target="staffListSection" title="Manage Staff">👥</div>
        <div class="nav-icon" data-target="deptSummarySection" title="Department Summary">📊</div>
        <div class="nav-icon" data-target="unitSummarySection" title="Unit Summary">🏷️</div>
        <div class="nav-icon" data-target="manageKpisSection" title="Manage KPIs">⚙️</div>
        <div style="flex:1"></div>
        <a class="nav-icon" href="../logout.php" title="Logout">🚪</a>
    </aside>

    <main class="main-area">
        <div class="header">
            <div style="display:flex;align-items:center;gap:12px">
                <div class="navbar-brand">KPI Portal</div>
                <div class="small-muted">Admin Dashboard</div>
            </div>
            <div style="display:flex;align-items:center;gap:12px">
                <div class="small-muted">Signed in as: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Admin') ?></div>
            </div>
        </div>

        <!-- top cards -->
        <div class="cards-row">
            <div class="card-stat">
                <div class="small-muted">Total Staff</div>
                <?php $totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?>
                <h3><?= $totalStaff ?></h3>
            </div>
            <div class="card-stat">
                <div class="small-muted">Pending Approvals</div>
                <?php $pending = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_approved = 0")->fetchColumn(); ?>
                <h3><?= $pending ?></h3>
            </div>
            <div class="card-stat">
                <div class="small-muted">Defined KPIs</div>
                <?php $kpiCount = (int)$pdo->query("SELECT COUNT(*) FROM metrics")->fetchColumn(); ?>
                <h3><?= $kpiCount ?></h3>
            </div>
        </div>

        <!-- MANAGE STAFF SECTION -->
        <section id="staffListSection" class="content-section">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h5 class="mb-0">Manage Staff</h5>
                <div class="small-muted">Manage staff entries, approve and edit roles</div>
            </div>

            <div style="display:flex;gap:14px;margin-bottom:12px;flex-wrap:wrap">
                <!-- Add staff form (compact) -->
                <form id="addStaffForm" class="form-inline">
                    <input name="first_name" class="form-control form-control-sm" placeholder="First" required>
                    <input name="last_name" class="form-control form-control-sm" placeholder="Last" required>
                    <input name="staff_id" class="form-control form-control-sm" placeholder="Staff ID (e.g. cu/24/033)" required>
                    <input name="email" class="form-control form-control-sm" placeholder="Email" required>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">Dept (optional)</option>
                        <?php foreach($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="unit" class="form-select form-select-sm">
                        <option value="">Unit (optional)</option>
                        <?php foreach($units_all as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" data-dept="<?= (int)$u['department_id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="password" type="password" class="form-control form-control-sm" placeholder="Password" required>
                    <button class="btn btn-primary btn-sm" type="submit" name="add_staff">Add</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Name</th><th>Staff ID</th><th>Email</th><th>Department</th><th>Actions</th></tr></thead>
                    <tbody id="staffTableBody">
                        <?php if (empty($staff)): ?>
                            <tr><td colspan="5" class="small-muted text-center">No staff found.</td></tr>
                        <?php else: foreach($staff as $s): ?>
                            <tr data-id="<?= (int)$s['id'] ?>">
                                <td><a class="view-user" data-user-id="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></a></td>
                                <td><?= htmlspecialchars($s['staff_id'] ?? '') ?></td>
                                <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($s['department'] ?? '') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="<?= (int)$s['id'] ?>">Edit</button>
                                    <button class="btn btn-sm btn-danger btn-delete" data-id="<?= (int)$s['id'] ?>">Delete</button>
                                    <button class="btn btn-sm btn-success btn-approve" data-id="<?= (int)$s['id'] ?>">Approve</button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- DEPARTMENT SUMMARY SECTION -->
        <section id="deptSummarySection" class="content-section d-none">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <h5 class="mb-0">Department Summary</h5>
                <div class="small-muted">Select department, month & year — export CSV or view charts</div>
            </div>
            <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <select id="dept_filter" class="form-select form-select-sm" style="width:auto">
                    <option value="">Select Dept</option>
                    <?php foreach($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                </select>
                <select id="dept_month" class="form-select form-select-sm" style="width:auto"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>"><?= date('F', mktime(0,0,0,$m,1)) ?></option><?php endfor; ?></select>
                <select id="dept_year" class="form-select form-select-sm" style="width:auto"><?php for($y=2023;$y<=date('Y');$y++): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?></select>
                <button id="deptApply" class="btn btn-primary btn-sm">Apply</button>
                <a id="deptCsv" class="btn btn-outline-secondary btn-sm" href="#" target="_blank">Export CSV</a>
                <button id="deptChartsBtn" class="btn btn-outline-info btn-sm">Charts</button>
            </div>
            <div id="deptResults" style="margin-top:12px"></div>
        </section>

        <!-- UNIT SUMMARY SECTION -->
        <section id="unitSummarySection" class="content-section d-none">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <h5 class="mb-0">Unit Summary</h5>
                <div class="small-muted">Select department & unit, month & year — export CSV or view trends</div>
            </div>
            <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <select id="unit_dept" class="form-select form-select-sm" style="width:auto">
                    <option value="">Select Dept</option>
                    <?php foreach($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                </select>
                <select id="unit_unit" class="form-select form-select-sm" style="width:auto"><option value="">Select Unit</option></select>
                <select id="unit_month" class="form-select form-select-sm" style="width:auto"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>"><?= date('F', mktime(0,0,0,$m,1)) ?></option><?php endfor; ?></select>
                <select id="unit_year" class="form-select form-select-sm" style="width:auto"><?php for($y=2023;$y<=date('Y');$y++): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?></select>
                <button id="unitApply" class="btn btn-primary btn-sm">Apply</button>
                <a id="unitCsv" class="btn btn-outline-secondary btn-sm" href="#" target="_blank">Export CSV</a>
                <button id="unitChartsBtn" class="btn btn-outline-info btn-sm">Charts / Trend</button>
            </div>
            <div id="unitResults" style="margin-top:12px"></div>
        </section>

        <!-- MANAGE KPIS SECTION (kept simple) -->
        <section id="manageKpisSection" class="content-section d-none">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <h5 class="mb-0">Manage KPIs</h5>
                <div class="small-muted">Add or review KPI definitions</div>
            </div>
            <div style="margin-top:10px">
                <form id="addMetricForm" class="form-inline">
                    <input name="name" class="form-control form-control-sm" placeholder="KPI Name" required>
                    <input name="target_value" class="form-control form-control-sm" placeholder="Target (numeric)">
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">Department</option>
                        <?php foreach($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm" type="submit">Add KPI</button>
                </form>

                <div style="margin-top:12px" class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Name</th><th>Dept</th><th>Target</th></tr></thead>
                        <tbody>
                            <?php if (empty($metrics)): ?><tr><td colspan="3" class="small-muted">No metrics</td></tr>
                            <?php else: foreach($metrics as $m): $dname=''; foreach($departments as $d){ if($d['id']==$m['department_id']){$dname=$d['name'];break;} } ?>
                                <tr><td><?= htmlspecialchars($m['name']) ?></td><td><?= htmlspecialchars($dname) ?></td><td><?= htmlspecialchars($m['target_value'] ?? '') ?></td></tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>
</div>

<!-- Modals -->
<!-- User summary modal -->
<div class="modal fade" id="userSummaryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="userSummaryTitle">User Summary</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="userSummaryBody">Loading…</div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>

<!-- Dept Charts modal -->
<div class="modal fade" id="deptChartsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Department Charts</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <canvas id="deptBarCanvas" style="max-height:420px"></canvas>
        <canvas id="deptPieCanvas" style="max-height:420px;display:none"></canvas>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-primary" id="toggleDeptChart">Toggle Pie/Bar</button><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>

<!-- Unit Charts / Trend modal -->
<div class="modal fade" id="unitChartsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Unit Charts & Trends</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <canvas id="unitBarCanvas" style="max-height:420px"></canvas>
        <div style="margin-top:12px">
            <label class="small-muted">Select metric for trend:</label>
            <select id="unitTrendMetric" class="form-select form-select-sm" style="width:auto;display:inline-block"></select>
            <button id="unitLoadTrend" class="btn btn-sm btn-primary">Load Trend</button>
        </div>
        <canvas id="unitTrendCanvas" style="max-height:240px;margin-top:12px"></canvas>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    'use strict';
    // helpers
    const $ = s => document.querySelector(s);
    const $$ = s => Array.from(document.querySelectorAll(s));
    function qs(q,ctx){ return (ctx||document).querySelector(q) }
    function qsa(q,ctx){ return Array.from((ctx||document).querySelectorAll(q)) }
    function escapeHtml(t){ if (t===null||t===undefined) return ''; return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // Sidebar nav icons
    qsa('.sidebar .nav-icon').forEach(ic=>{
        ic.addEventListener('click', ()=> {
            const tgt = ic.getAttribute('data-target');
            if (!tgt) return;
            document.querySelectorAll('.content-section').forEach(s=>s.classList.add('d-none'));
            const sec = document.getElementById(tgt);
            if (sec) sec.classList.remove('d-none');
            qsa('.sidebar .nav-icon').forEach(n=>n.classList.remove('active'));
            ic.classList.add('active');
        });
    });
    // default: show staff
    qsa('.sidebar .nav-icon')[0].click();

    // ---- Manage Staff: add ----
    const addStaffForm = document.getElementById('addStaffForm');
    addStaffForm && addStaffForm.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(addStaffForm);
        fd.append('add_staff', '1');
        fetch(location.pathname, { method:'POST', body: fd })
            .then(r=>r.json()).then(j=>{
                if (j.success) {
                    alert('Added');
                    location.reload();
                } else alert(j.message || 'Error');
            }).catch(()=>alert('Request failed'));
    });

    // Edit / Delete / Approve - delegated using click on buttons
    document.body.addEventListener('click', function(e){
        const ed = e.target.closest('.btn-edit');
        if (ed) {
            const id = ed.getAttribute('data-id');
            openEditModal(id);
            return;
        }
        const del = e.target.closest('.btn-delete');
        if (del) {
            if (!confirm('Delete this user?')) return;
            const id = del.getAttribute('data-id');
            const fd = new FormData(); fd.append('delete_staff', id);
            fetch(location.pathname, { method:'POST', body:fd }).then(r=>r.json()).then(j=>{ if(j.success) location.reload(); else alert(j.message||'Error')}).catch(()=>alert('Request failed'));
            return;
        }
        const ap = e.target.closest('.btn-approve');
        if (ap) {
            const id = ap.getAttribute('data-id');
            const fd = new FormData(); fd.append('approve_user', id);
            fetch(location.pathname, { method:'POST', body:fd }).then(r=>r.json()).then(j=>{ if(j.success) location.reload(); else alert(j.message||'Error')}).catch(()=>alert('Request failed'));
            return;
        }
    });

    // view user summary modal
    qsa('.view-user').forEach(a => {
        a.addEventListener('click', function(ev){
            ev.preventDefault();
            const id = this.getAttribute('data-user-id');
            openUserSummary(id);
        });
    });

    function openUserSummary(userId) {
        const month = document.getElementById('dept_month') ? document.getElementById('dept_month').value : (new Date()).getMonth()+1;
        const year = document.getElementById('dept_year') ? document.getElementById('dept_year').value : (new Date()).getFullYear();
        const url = new URL(location.href);
        url.searchParams.set('user_summary','1'); url.searchParams.set('user_id', userId);
        url.searchParams.set('month', month); url.searchParams.set('year', year);
        const modalEl = document.getElementById('userSummaryModal'); const modal = new bootstrap.Modal(modalEl);
        document.getElementById('userSummaryTitle').textContent = 'Loading…'; document.getElementById('userSummaryBody').innerHTML = 'Loading…';
        modal.show();
        fetch(url.toString(), { credentials:'same-origin' }).then(r=>r.json()).then(j=>{
            if (!j.user) { document.getElementById('userSummaryBody').innerHTML = '<div class="text-danger">No data</div>'; return; }
            let html = '<p><strong>'+ escapeHtml(j.user.full_name || j.user.email) +'</strong> — ' + (j.month) + '/' + j.year + '</p>';
            html += '<p><strong>Staff ID:</strong> ' + (j.user.staff_id||'') + ' &nbsp; <strong>Email:</strong> ' + (j.user.email||'') + '</p>';
            html += '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Metric</th><th>Target</th><th>Submitted Total</th><th>Times</th></tr></thead><tbody>';
            (j.metrics || []).forEach(m => { html += '<tr><td>'+escapeHtml(m.metric_name)+'</td><td>'+ (Number(m.target_value)||0) +'</td><td>'+ (Number(m.total_value)||0) +'</td><td>'+ (m.times_reported||0) +'</td></tr>'; });
            html += '</tbody></table></div>';
            document.getElementById('userSummaryBody').innerHTML = html;
        }).catch(()=>{ document.getElementById('userSummaryBody').innerHTML = '<div class="text-danger">Failed to load</div>'; });
    }

    // ---- Edit modal (simple prompt sequence) ----
    function openEditModal(id) {
        // fetch current user data inline
        fetch(location.pathname + '?user_summary=1&user_id=' + encodeURIComponent(id))
            .then(r=>r.json()).then(j=>{
                if (!j.user) { alert('User not found'); return; }
                // simple edit via prompt for brevity (expand into a proper modal if desired)
                const first = prompt('First name', j.user.first_name || '');
                if (first === null) return;
                const last = prompt('Last name', j.user.last_name || '');
                if (last === null) return;
                const email = prompt('Email', j.user.email || '');
                if (email === null) return;
                // department selection: choose id from list
                const dept = prompt('Department ID (blank to keep):', j.user.department_id || '');
                const unit = prompt('Unit ID (blank to keep):', j.user.unit || '');
                const role = prompt('Role (staff/admin):', j.user.role || 'staff');
                const fd = new FormData();
                fd.append('edit_staff', id);
                fd.append('first_name', first);
                fd.append('last_name', last);
                fd.append('email', email);
                fd.append('staff_id', j.user.staff_id || '');
                fd.append('department_id', dept);
                fd.append('unit', unit);
                fd.append('role', role);
                fetch(location.pathname, { method:'POST', body: fd}).then(r=>r.json()).then(resp=>{
                    if (resp.success) { alert('Saved'); location.reload(); } else alert(resp.message||'Failed');
                }).catch(()=>alert('Request failed'));
            }).catch(()=>alert('Failed to fetch user'));
    }

    // ---- Dept Summary AJAX + Charts + CSV link ----
    const deptApply = document.getElementById('deptApply');
    deptApply && deptApply.addEventListener('click', function(){
        const dept = document.getElementById('dept_filter').value;
        const month = document.getElementById('dept_month').value;
        const year = document.getElementById('dept_year').value;
        if (!dept || !month || !year) { document.getElementById('deptResults').innerHTML = '<div class="small-muted">Select dept, month & year</div>'; return; }
        const url = location.pathname + '?ajax_dept=1&department='+encodeURIComponent(dept)+'&month='+encodeURIComponent(month)+'&year='+encodeURIComponent(year);
        fetch(url).then(r=>r.json()).then(j=>{
            if (!j.metrics || j.metrics.length===0) { document.getElementById('deptResults').innerHTML = '<div class="small-muted">No metrics found for selection</div>'; return; }
            let html = '<table class="table table-sm"><thead><tr><th>Metric</th><th>Target</th><th>Staff Count</th><th>Expected</th><th>Total Entered</th><th>Perf %</th></tr></thead><tbody>';
            j.metrics.forEach(m => {
                html += '<tr><td>'+escapeHtml(m.metric_name)+'</td><td>'+Number(m.target_value).toFixed(2)+'</td><td>'+escapeHtml(m.staff_count)+'</td><td>'+Number(m.expected_kpi).toFixed(2)+'</td><td>'+Number(m.total_entered).toFixed(2)+'</td><td>'+(m.performance_percent===null?'—':m.performance_percent+'%')+'</td></tr>';
            });
            html += '</tbody></table>';
            document.getElementById('deptResults').innerHTML = html;
            // set csv link
            document.getElementById('deptCsv').href = location.pathname + '?ajax_dept_csv=1&department='+dept+'&month='+month+'&year='+year;
            // prepare charts
            window._lastDeptData = j;
        }).catch(()=>{ document.getElementById('deptResults').innerHTML = '<div class="text-danger">Failed to load</div>'; });
    });

    // Dept Charts modal show
    const deptChartsBtn = document.getElementById('deptChartsBtn');
    let deptBarChart=null, deptPieChart=null, deptChartMode='bar';
    deptChartsBtn && deptChartsBtn.addEventListener('click', function(){
        const data = window._lastDeptData;
        if (!data || !data.metrics) { alert('Run the Department filter first'); return; }
        const labels = data.metrics.map(m=>m.metric_name);
        const expected = data.metrics.map(m=>m.expected_kpi);
        const achieved = data.metrics.map(m=>m.total_entered);
        const ctxBar = document.getElementById('deptBarCanvas').getContext('2d');
        if (deptBarChart) deptBarChart.destroy();
        deptBarChart = new Chart(ctxBar, { type:'bar', data: { labels:labels, datasets:[{label:'Expected', data:expected},{label:'Achieved', data:achieved}] }, options:{responsive:true,scales:{y:{beginAtZero:true}}} });
        // pie (contribution)
        const ctxPie = document.getElementById('deptPieCanvas').getContext('2d');
        if (deptPieChart) deptPieChart.destroy();
        deptPieChart = new Chart(ctxPie, { type:'pie', data:{ labels:labels, datasets:[{data: achieved}] }, options:{responsive:true} });
        // show modal
        const modal = new bootstrap.Modal(document.getElementById('deptChartsModal')); modal.show();
        document.getElementById('deptPieCanvas').style.display='none';
        document.getElementById('deptBarCanvas').style.display='block';
        deptChartMode='bar';
    });
    document.getElementById('toggleDeptChart') && document.getElementById('toggleDeptChart').addEventListener('click', function(){
        if (deptChartMode==='bar') {
            document.getElementById('deptBarCanvas').style.display='none';
            document.getElementById('deptPieCanvas').style.display='block';
            deptChartMode='pie';
        } else {
            document.getElementById('deptBarCanvas').style.display='block';
            document.getElementById('deptPieCanvas').style.display='none';
            deptChartMode='bar';
        }
    });

    // ---- Unit Summary: dept -> unit dependent dropdown ----
    const unitDept = document.getElementById('unit_dept'), unitUnit = document.getElementById('unit_unit');
    unitDept && unitDept.addEventListener('change', function(){
        const dept = this.value;
        unitUnit.innerHTML = '<option value="">Loading...</option>';
        if (!dept) { unitUnit.innerHTML = '<option value="">Select Unit</option>'; return; }
        fetch(location.pathname + '?get_units_by_dept=' + encodeURIComponent(dept)).then(r=>r.json()).then(rows=>{
            let html = '<option value="">Select Unit</option>';
            rows.forEach(rw => html += '<option value="'+rw.id+'">'+rw.name+'</option>');
            unitUnit.innerHTML = html;
        }).catch(()=>{ unitUnit.innerHTML = '<option value="">Failed</option>'; });
    });

    // Unit apply
    document.getElementById('unitApply') && document.getElementById('unitApply').addEventListener('click', function(){
        const dept = unitDept.value, unit = unitUnit.value, month = document.getElementById('unit_month').value, year = document.getElementById('unit_year').value;
        if (!dept || !unit || !month || !year) { document.getElementById('unitResults').innerHTML = '<div class="small-muted">Select dept, unit, month & year</div>'; return; }
        fetch(location.pathname + '?ajax_unit=1&department='+encodeURIComponent(dept)+'&unit='+encodeURIComponent(unit)+'&month='+encodeURIComponent(month)+'&year='+encodeURIComponent(year)).then(r=>r.json()).then(j=>{
            if (!j.metrics || j.metrics.length===0) { document.getElementById('unitResults').innerHTML = '<div class="small-muted">No data for selection</div>'; return; }
            let html = '<table class="table table-sm"><thead><tr><th>Metric</th><th>Target</th><th>Staff Count</th><th>Expected</th><th>Total Entered</th><th>Perf %</th></tr></thead><tbody>';
            j.metrics.forEach(m=>{ html += '<tr><td>'+escapeHtml(m.metric_name)+'</td><td>'+Number(m.target_value).toFixed(2)+'</td><td>'+escapeHtml(m.staff_count)+'</td><td>'+Number(m.expected_kpi).toFixed(2)+'</td><td>'+Number(m.total_entered).toFixed(2)+'</td><td>'+(m.performance_percent===null?'—':m.performance_percent+'%')+'</td></tr>'; });
            html += '</tbody></table>';
            document.getElementById('unitResults').innerHTML = html;
            document.getElementById('unitCsv').href = location.pathname + '?ajax_unit_csv=1&department='+dept+'&unit='+unit+'&month='+month+'&year='+year;
            window._lastUnitData = j;
            // populate metric selector for trend
            const sel = document.getElementById('unitTrendMetric'); sel.innerHTML = '<option value="">Select metric</option>';
            j.metrics.forEach(m=> sel.innerHTML += '<option value="'+m.metric_id+'">'+m.metric_name+'</option>');
        }).catch(()=>document.getElementById('unitResults').innerHTML = '<div class="text-danger">Failed</div>');
    });

    // Unit charts modal (bar of expected vs achieved)
    document.getElementById('unitChartsBtn') && document.getElementById('unitChartsBtn').addEventListener('click', function(){
        const data = window._lastUnitData;
        if (!data || !data.metrics) return alert('Run filters first');
        const labels = data.metrics.map(m=>m.metric_name);
        const expected = data.metrics.map(m=>m.expected_kpi);
        const achieved = data.metrics.map(m=>m.total_entered);
        const ctx = document.getElementById('unitBarCanvas').getContext('2d');
        if (window._unitBarChart) window._unitBarChart.destroy();
        window._unitBarChart = new Chart(ctx, { type:'bar', data:{ labels:labels, datasets:[{label:'Expected',data:expected},{label:'Achieved',data:achieved}] }, options:{responsive:true,scales:{y:{beginAtZero:true}}} });
        // show modal
        new bootstrap.Modal(document.getElementById('unitChartsModal')).show();
    });

    // Unit trend load
    document.getElementById('unitLoadTrend') && document.getElementById('unitLoadTrend').addEventListener('click', function(){
        const dept = unitDept.value, unit = unitUnit.value;
        const metric = document.getElementById('unitTrendMetric').value;
        if (!dept || !unit || !metric) return alert('Choose dept, unit and metric');
        fetch(location.pathname + '?unit_trend=1&department='+encodeURIComponent(dept)+'&unit='+encodeURIComponent(unit)+'&metric_id='+encodeURIComponent(metric)).then(r=>r.json()).then(j=>{
            const ctx = document.getElementById('unitTrendCanvas').getContext('2d');
            if (window._unitTrendChart) window._unitTrendChart.destroy();
            window._unitTrendChart = new Chart(ctx, { type:'line', data:{ labels: j.labels || [], datasets:[{label:'Total entered', data:j.data || [], fill:true}] }, options:{responsive:true,scales:{y:{beginAtZero:true}}} });
        }).catch(()=>alert('Failed to load trend'));
    });

    // manage KPI add (simple)
    document.getElementById('addMetricForm') && document.getElementById('addMetricForm').addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this); // name, target_value, department_id
        fd.append('add_metric', '1');
        fetch(location.pathname, { method:'POST', body: fd }).then(r=>r.json()).then(j=>{ if (j.success) { alert('Added'); location.reload(); } else alert(j.message||'Error'); }).catch(()=>alert('Request failed'));
    });

    // dependent unit options for add staff small form: show only units where dept matches
    const addDeptSelect = addStaffForm.querySelector('select[name="department_id"]');
    addDeptSelect && addDeptSelect.addEventListener('change', function(){
        const dept = this.value;
        const unitSel = addStaffForm.querySelector('select[name="unit"]');
        Array.from(unitSel.options).forEach(opt=>{
            const d = opt.getAttribute('data-dept') || '';
            if (!dept) opt.style.display = 'block';
            else opt.style.display = (d === dept) ? 'block' : 'none';
        });
    });

})();
</script>
</body>
</html>
n