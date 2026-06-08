<?php
session_start();
require 'db.php';

// Guard: staff only
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit;
}

$staff_name = $_SESSION['staff_name'];
$message = "";
$message_class = "";

// ── UPDATE STATUS ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $appt_id   = intval($_POST['appt_id']);
    $new_status = $_POST['new_status'];
    $allowed    = ['Scheduled', 'Completed', 'Cancelled'];

    if (in_array($new_status, $allowed)) {
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $appt_id);
        if ($stmt->execute()) {
            $message       = "Appointment #$appt_id status updated to \"$new_status\".";
            $message_class = "success";
        } else {
            $message       = "Failed to update status. Please try again.";
            $message_class = "error";
        }
    }
}

// ── DELETE APPOINTMENT ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_appt'])) {
    $appt_id = intval($_POST['appt_id']);
    $stmt    = $conn->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appt_id);
    if ($stmt->execute()) {
        $message       = "Appointment #$appt_id has been deleted.";
        $message_class = "success";
    } else {
        $message       = "Failed to delete appointment.";
        $message_class = "error";
    }
}

// ── SEARCH / FILTER ───────────────────────────────────────────────────────────
$search        = isset($_GET['search'])        ? trim($_GET['search'])        : '';
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';

$sql = "
    SELECT a.*, s.studentname, s.studentemailuitm
    FROM appointments a
    JOIN students s ON s.studentno = a.studentno
    WHERE 1=1
";
$params = [];
$types  = '';

if (!empty($search)) {
    $sql    .= " AND (s.studentname LIKE ? OR a.studentno LIKE ?)";
    $like    = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if (!empty($filter_status)) {
    $sql    .= " AND a.status = ?";
    $params[] = $filter_status;
    $types   .= 's';
}

$sql .= " ORDER BY a.appointment_datetime DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ── COUNTS FOR SUMMARY CARDS ──────────────────────────────────────────────────
$counts = ['Scheduled' => 0, 'Completed' => 0, 'Cancelled' => 0, 'Total' => 0];
$count_result = $conn->query("SELECT status, COUNT(*) as cnt FROM appointments GROUP BY status");
while ($row = $count_result->fetch_assoc()) {
    $counts[$row['status']] = $row['cnt'];
    $counts['Total'] += $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard — UiTM Health Unit</title>
    <meta name="description" content="Manage all student appointments at UiTM Health Unit.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue:            #007aff;
            --blue-hover:      #0062cc;
            --green:           #34c759;
            --red:             #ff3b30;
            --label-primary:   #1c1c1e;
            --label-secondary: #636366;
            --label-tertiary:  #aeaeb2;
            --fill-1:          rgba(120,120,128,0.12);
            --fill-2:          rgba(120,120,128,0.07);
            --separator:       rgba(60,60,67,0.12);
            --bg-primary:      #f2f2f7;
            --bg-secondary:    #ffffff;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif;
            background: var(--bg-primary);
            color: var(--label-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── NAVBAR ── */
        .navbar {
            background: rgba(255,255,255,0.85);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid var(--separator);
            position: sticky; top: 0; z-index: 100;
            height: 56px;
            display: flex; align-items: center;
            padding: 0 24px;
        }
        .navbar-inner {
            max-width: 1100px; width: 100%; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; }
        .nav-icon {
            width: 32px; height: 32px;
            background: var(--label-primary);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
        }
        .nav-title { font-size: 15px; font-weight: 600; color: var(--label-primary); letter-spacing: -0.2px; }
        .nav-role  {
            font-size: 11px; font-weight: 600; letter-spacing: 0.4px;
            text-transform: uppercase; color: var(--label-tertiary);
            background: var(--fill-1); padding: 3px 8px; border-radius: 6px;
        }
        .nav-right  { display: flex; align-items: center; gap: 12px; }
        .nav-greeting { font-size: 13px; color: var(--label-secondary); }
        .nav-greeting strong { color: var(--label-primary); font-weight: 600; }
        .btn-logout {
            font-size: 13px; font-weight: 500; color: var(--label-secondary);
            text-decoration: none; padding: 6px 14px; border-radius: 8px;
            background: var(--fill-1); transition: background 0.18s; white-space: nowrap;
        }
        .btn-logout:hover { background: var(--fill-2); }

        /* ── MAIN ── */
        .main { max-width: 1100px; margin: 0 auto; padding: 40px 24px 60px; }

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 32px; }
        .page-header h1 {
            font-size: 30px; font-weight: 700; letter-spacing: -0.6px;
            color: var(--label-primary); line-height: 1.1; margin-bottom: 6px;
        }
        .page-header p { font-size: 14px; color: var(--label-secondary); }

        /* ── ALERT ── */
        .alert {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 18px; border-radius: 14px;
            font-size: 14px; font-weight: 500;
            margin-bottom: 24px; line-height: 1.5;
            animation: fadeSlideIn 0.3s ease;
        }
        .alert-icon {
            width: 20px; height: 20px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0; margin-top: 1px;
        }
        .alert.success { background: rgba(52,199,89,0.10); color: #1a7a2e; }
        .alert.success .alert-icon { background: var(--green); color: #fff; }
        .alert.error   { background: rgba(255,59,48,0.08);  color: #c0251b; }
        .alert.error .alert-icon   { background: var(--red);   color: #fff; }
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── STAT CARDS ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 12px; margin-bottom: 20px;
        }
        .stat-card {
            background: var(--bg-secondary); border-radius: 18px;
            padding: 22px 20px; transition: transform 0.2s, box-shadow 0.2s; cursor: default;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .stat-dot { width: 8px; height: 8px; border-radius: 50%; margin-bottom: 14px; }
        .stat-card.total     .stat-dot { background: var(--label-primary); }
        .stat-card.scheduled .stat-dot { background: var(--blue); }
        .stat-card.completed .stat-dot { background: var(--green); }
        .stat-card.cancelled .stat-dot { background: var(--red); }
        .stat-count { font-size: 38px; font-weight: 700; letter-spacing: -1.5px; line-height: 1; margin-bottom: 6px; }
        .stat-card.total     .stat-count { color: var(--label-primary); }
        .stat-card.scheduled .stat-count { color: var(--blue); }
        .stat-card.completed .stat-count { color: var(--green); }
        .stat-card.cancelled .stat-count { color: var(--red); }
        .stat-label { font-size: 12px; font-weight: 500; color: var(--label-secondary); }

        /* ── SECTION LABELS ── */
        .section { margin-bottom: 20px; }
        .section-label {
            font-size: 11px; font-weight: 600; letter-spacing: 0.5px;
            text-transform: uppercase; color: var(--label-secondary);
            margin-bottom: 8px; padding-left: 4px;
        }

        /* ── CARD ── */
        .card { background: var(--bg-secondary); border-radius: 18px; overflow: hidden; }

        /* ── TOOLBAR ── */
        .toolbar {
            display: flex; gap: 10px; align-items: center;
            padding: 16px 20px; flex-wrap: wrap;
            border-bottom: 1px solid var(--separator);
        }

        .search-input {
            flex: 1; min-width: 200px;
            padding: 10px 14px;
            background: var(--fill-1);
            border: 1.5px solid transparent;
            border-radius: 10px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px;
            color: var(--label-primary);
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .search-input::placeholder { color: var(--label-tertiary); }
        .search-input:focus { border-color: var(--blue); background: rgba(0,122,255,0.05); }

        .filter-select {
            padding: 10px 12px;
            background: var(--fill-1);
            border: 1.5px solid transparent;
            border-radius: 10px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px;
            color: var(--label-primary);
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .filter-select:focus { border-color: var(--blue); }

        .btn-search {
            background: var(--blue); color: #fff; border: none;
            padding: 10px 20px; border-radius: 10px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px; font-weight: 600; cursor: pointer;
            transition: background 0.2s; white-space: nowrap;
        }
        .btn-search:hover { background: var(--blue-hover); }

        .btn-reset {
            background: var(--fill-1); color: var(--label-secondary);
            border: none; padding: 10px 16px; border-radius: 10px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px; cursor: pointer;
            text-decoration: none;
            transition: background 0.2s; white-space: nowrap;
        }
        .btn-reset:hover { background: var(--fill-2); }

        /* ── TABLE HEADER ── */
        .table-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 22px 14px; border-bottom: 1px solid var(--separator);
        }
        .table-title { font-size: 15px; font-weight: 600; color: var(--label-primary); letter-spacing: -0.2px; }
        .badge-count {
            font-size: 12px; font-weight: 600; color: var(--label-secondary);
            background: var(--fill-1); padding: 3px 10px; border-radius: 20px;
        }

        /* ── TABLE ── */
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 11px 18px; text-align: left;
            font-size: 11px; font-weight: 600; letter-spacing: 0.5px;
            text-transform: uppercase; color: var(--label-tertiary);
            border-bottom: 1px solid var(--separator);
        }
        tbody tr { transition: background 0.12s; }
        tbody tr:hover { background: var(--fill-2); }
        tbody td {
            padding: 13px 18px; font-size: 14px; color: var(--label-primary);
            border-bottom: 1px solid var(--separator); vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }

        .id-cell { font-size: 12px; color: var(--label-tertiary); font-variant-numeric: tabular-nums; }
        .student-name  { font-weight: 600; color: var(--label-primary); }
        .student-sub   { font-size: 12px; color: var(--label-secondary); margin-top: 2px; }

        /* ── STATUS BADGES ── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 11px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
        }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .badge-scheduled  { background: rgba(0,122,255,0.10); color: #005ecb; }
        .badge-scheduled::before  { background: var(--blue); }
        .badge-completed  { background: rgba(52,199,89,0.10); color: #1a7a2e; }
        .badge-completed::before  { background: var(--green); }
        .badge-cancelled  { background: rgba(255,59,48,0.10); color: #c0251b; }
        .badge-cancelled::before  { background: var(--red); }

        /* ── REMINDER ── */
        .reminder-sent    { font-size: 12px; font-weight: 500; color: var(--blue); }
        .reminder-pending { font-size: 12px; font-weight: 500; color: var(--label-tertiary); }

        /* ── ACTION CONTROLS ── */
        .action-row { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }

        .status-select {
            padding: 6px 10px;
            background: var(--fill-1);
            border: 1.5px solid transparent;
            border-radius: 8px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px;
            color: var(--label-primary);
            outline: none; cursor: pointer;
            transition: border-color 0.2s;
        }
        .status-select:focus { border-color: var(--blue); }

        .btn-update {
            background: var(--blue); color: #fff; border: none;
            padding: 6px 14px; border-radius: 8px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-update:hover { background: var(--blue-hover); }

        .btn-delete {
            background: transparent; color: var(--red);
            border: 1.5px solid rgba(255,59,48,0.3);
            padding: 6px 12px; border-radius: 8px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }
        .btn-delete:hover { background: var(--red); color: #fff; border-color: var(--red); }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 56px 24px; }
        .empty-icon  { font-size: 40px; margin-bottom: 14px; opacity: 0.35; }
        .empty-title { font-size: 15px; font-weight: 600; color: var(--label-secondary); margin-bottom: 4px; }
        .empty-sub   { font-size: 13px; color: var(--label-tertiary); }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .navbar { padding: 0 16px; }
            .main   { padding: 24px 16px 48px; }
            .nav-greeting { display: none; }
            .page-header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-inner">
        <div class="nav-brand">
            <div class="nav-icon">🏥</div>
            <span class="nav-title">Health Unit</span>
            <span class="nav-role">Staff</span>
        </div>
        <div class="nav-right">
            <span class="nav-greeting">Logged in as <strong><?= htmlspecialchars($staff_name) ?></strong></span>
            <a href="staff_logout.php" class="btn-logout" id="logout-btn">Sign Out</a>
        </div>
    </div>
</nav>

<!-- MAIN -->
<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>Appointment Management</h1>
        <p>View, update, and manage all student clinic appointments.</p>
    </div>

    <!-- ALERT -->
    <?php if (!empty($message)): ?>
        <div class="alert <?= $message_class ?>" role="alert">
            <div class="alert-icon"><?= $message_class === 'success' ? '✓' : '✕' ?></div>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card total">
            <div class="stat-dot"></div>
            <div class="stat-count"><?= $counts['Total'] ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card scheduled">
            <div class="stat-dot"></div>
            <div class="stat-count"><?= $counts['Scheduled'] ?></div>
            <div class="stat-label">Scheduled</div>
        </div>
        <div class="stat-card completed">
            <div class="stat-dot"></div>
            <div class="stat-count"><?= $counts['Completed'] ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card cancelled">
            <div class="stat-dot"></div>
            <div class="stat-count"><?= $counts['Cancelled'] ?></div>
            <div class="stat-label">Cancelled</div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="section">
        <div class="section-label">All Appointments</div>
        <div class="card">

            <!-- SEARCH & FILTER -->
            <form method="GET" action="staff_dashboard.php">
                <div class="toolbar">
                    <input class="search-input" type="text" name="search"
                           placeholder="🔍  Search by student name or ID..."
                           value="<?= htmlspecialchars($search) ?>">
                    <select class="filter-select" name="filter_status">
                        <option value="">All Statuses</option>
                        <option value="Scheduled"  <?= $filter_status === 'Scheduled'  ? 'selected' : '' ?>>Scheduled</option>
                        <option value="Completed"  <?= $filter_status === 'Completed'  ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled"  <?= $filter_status === 'Cancelled'  ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn-search">Search</button>
                    <a href="staff_dashboard.php" class="btn-reset">Reset</a>
                </div>
            </form>

            <div class="table-header">
                <span class="table-title">Appointment Records</span>
                <span class="badge-count"><?= $result->num_rows ?> found</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                        <th>Reminder</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="id-cell"><?= $row['id'] ?></td>

                                <td>
                                    <div class="student-name"><?= htmlspecialchars($row['studentname']) ?></div>
                                    <div class="student-sub"><?= htmlspecialchars($row['studentno']) ?></div>
                                    <div class="student-sub"><?= htmlspecialchars($row['studentemailuitm']) ?></div>
                                </td>

                                <td>
                                    <?php $dt = strtotime($row['appointment_datetime']); ?>
                                    <div><?= date('d M Y', $dt) ?></div>
                                    <div class="student-sub"><?= date('g:i a', $dt) ?></div>
                                </td>

                                <td>
                                    <?php $cls = strtolower($row['status']); ?>
                                    <span class="status-badge badge-<?= $cls ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($row['reminder_sent'] == 1): ?>
                                        <span class="reminder-sent">Email sent</span>
                                    <?php else: ?>
                                        <span class="reminder-pending">Pending</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="action-row">
                                        <!-- Update Status -->
                                        <form method="POST" action="staff_dashboard.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" style="display:flex;gap:6px;align-items:center;">
                                            <input type="hidden" name="appt_id" value="<?= $row['id'] ?>">
                                            <select name="new_status" class="status-select">
                                                <option value="Scheduled" <?= $row['status'] === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                                <option value="Completed" <?= $row['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="Cancelled" <?= $row['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn-update">Save</button>
                                        </form>

                                        <!-- Delete -->
                                        <form method="POST" action="staff_dashboard.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>"
                                              onsubmit="return confirm('Delete appointment #<?= $row['id'] ?> for <?= htmlspecialchars(addslashes($row['studentname'])) ?>? This cannot be undone.');">
                                            <input type="hidden" name="appt_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete_appt" class="btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon">🗓</div>
                                    <div class="empty-title">No appointments found<?= !empty($search) ? " for \"" . htmlspecialchars($search) . "\"" : "" ?></div>
                                    <div class="empty-sub">Try adjusting your search or filter</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>