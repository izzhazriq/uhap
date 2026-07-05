<?php
session_start();
require '../config/db.php';

// Guard: staff only
if (!isset($_SESSION['staff_id'])) {
    header("Location: ../auth/staff_login.php");
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
    <!-- jQuery + DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <!-- Shared motion / animation library -->
    <link rel="stylesheet" href="../assets/motion.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="../assets/motion.js" defer></script>
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
            display: flex; gap: 12px; align-items: center;
            padding: 20px 24px; flex-wrap: wrap;
            border-bottom: 1px solid var(--separator);
        }

        .search-input {
            flex: 1; min-width: 220px;
            padding: 11px 16px;
            background: var(--fill-1);
            border: 1.5px solid transparent;
            border-radius: 12px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px;
            color: var(--label-primary);
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .search-input::placeholder { color: var(--label-tertiary); }
        .search-input:focus { border-color: var(--blue); background: rgba(0,122,255,0.05); }

        .filter-select {
            padding: 11px 36px 11px 16px;
            background: var(--fill-1);
            border: 1.5px solid transparent;
            border-radius: 12px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px;
            color: var(--label-primary);
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2363666b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }
        .filter-select:focus { border-color: var(--blue); }

        .btn-search {
            background: var(--blue); color: #fff; border: none;
            padding: 11px 24px; border-radius: 12px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px; font-weight: 600; cursor: pointer;
            transition: background 0.2s; white-space: nowrap;
        }
        .btn-search:hover { background: var(--blue-hover); }

        .btn-reset {
            background: var(--fill-1); color: var(--label-secondary);
            border: none; padding: 11px 20px; border-radius: 12px;
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
            padding: 14px 20px; text-align: left;
            font-size: 11px; font-weight: 600; letter-spacing: 0.5px;
            text-transform: uppercase; color: var(--label-tertiary);
            border-bottom: 1px solid var(--separator);
        }
        tbody tr { transition: background 0.12s; }
        tbody tr:hover { background: var(--fill-2); }
        tbody td {
            padding: 18px 20px; font-size: 14px; color: var(--label-primary);
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
        .action-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        .status-select {
            padding: 8px 32px 8px 12px;
            background: var(--fill-1);
            border: 1.5px solid transparent;
            border-radius: 10px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px;
            color: var(--label-primary);
            outline: none; cursor: pointer;
            transition: border-color 0.2s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2363666b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
        .status-select:focus { border-color: var(--blue); }

        .btn-update {
            background: var(--blue); color: #fff; border: none;
            padding: 8px 18px; border-radius: 10px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-update:hover { background: var(--blue-hover); }

        .btn-delete {
            background: transparent; color: var(--red);
            border: 1.5px solid rgba(255,59,48,0.3);
            padding: 8px 16px; border-radius: 10px;
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

        /* ── DATATABLES - APPLE / iOS STYLE ── */
        .dataTables_wrapper {
            padding: 0 24px 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
        }

        /* Search & Length - iOS style inputs */
        .dataTables_wrapper .dataTables_length {
            float: left !important;
        }
        .dataTables_wrapper .dataTables_filter {
            float: right !important;
            text-align: right !important;
        }

        /* Force spacing with flexbox on the wrapper */
        .dataTables_wrapper .row:first-child {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .dataTables_wrapper .dataTables_length {
            margin-right: 0 !important;
        }

        .dataTables_length label,
        .dataTables_filter label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #1d1d1f;
            font-weight: 500;
        }
        .dataTables_length select,
        .dataTables_filter input {
            padding: 10px 14px;
            background: #f5f5f7;
            border: 1px solid #d2d2d7;
            border-radius: 12px;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
            font-size: 14px;
            color: #1d1d1f;
            outline: none;
            transition: all 0.2s ease;
        }

        .dataTables_length select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2363666b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 34px;
        }

        .dataTables_length select:focus,
        .dataTables_filter input:focus {
            border-color: #007aff;
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15);
        }

        .dataTables_filter input {
            width: 340px;
            margin-left: 8px;
        }
        .dataTables_filter input::placeholder { color: #86868b; }

        .dataTables_wrapper .dataTables_info {
            clear: both !important;
            float: left !important;
        }
        .dataTables_wrapper .dataTables_paginate {
            clear: both !important;
            float: right !important;
        }

        /* Info text */
        .dataTables_info {
            font-size: 13px;
            color: #86868b;
            padding: 20px 0 8px;
            font-weight: 500;
        }

        /* Pagination - iOS style */
        .dataTables_paginate {
            padding: 16px 0;
        }
        .dataTables_paginate .paginate_button {
            min-width: 38px;
            height: 38px;
            padding: 0 14px;
            border-radius: 12px;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: #1d1d1f;
            background: #ffffff;
            border: 1px solid #d2d2d7;
            margin: 0 5px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .dataTables_paginate .paginate_button:hover {
            background: #f5f5f7;
            border-color: #007aff;
            color: #007aff;
        }
        .dataTables_paginate .paginate_button.current {
            background: #007aff;
            color: #ffffff;
            border-color: #007aff;
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.25);
        }
        .dataTables_paginate .paginate_button.current:hover {
            background: #0062cc;
            border-color: #0062cc;
            color: #ffffff;
        }
        .dataTables_paginate .paginate_button.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            background: #f5f5f7;
        }
        .dataTables_paginate .paginate_button.disabled:hover {
            background: #f5f5f7;
            border-color: #d2d2d7;
            color: #86868b;
        }

        /* Table styling - iOS style */
        table.dataTable {
            border-collapse: separate;
            border-spacing: 0;
        }
        table.dataTable thead {
            box-shadow: 0 1px 0 #d2d2d7;
        }
        table.dataTable thead th {
            padding: 16px 20px;
            background: #ffffff;
            border-bottom: none;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #86868b;
        }
        table.dataTable thead th.sorting:hover,
        table.dataTable thead th.sorting_asc:hover,
        table.dataTable thead th.sorting_desc:hover {
            background: #f5f5f7;
        }
        table.dataTable thead th.sorting_asc:after,
        table.dataTable thead th.sorting_desc:after {
            font-family: -apple-system, sans-serif;
            font-size: 12px;
            opacity: 0.5;
            margin-left: 4px;
        }

        table.dataTable tbody tr {
            transition: all 0.15s ease;
        }
        table.dataTable tbody tr:hover {
            background: #f5f5f7 !important;
        }
        table.dataTable tbody td {
            padding: 20px;
            border-bottom: 1px solid #e8e8ed;
            background: #ffffff;
        }
        table.dataTable tbody tr:last-child td {
            border-bottom: none;
        }

        /* Responsive adjustments */
        @media (max-width: 900px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .navbar { padding: 0 16px; }
            .main   { padding: 24px 16px 48px; }
            .nav-greeting { display: none; }
            .page-header h1 { font-size: 24px; }
            .dataTables_wrapper { padding: 0 12px 16px; }
            .dataTables_length,
            .dataTables_filter {
                float: none !important;
                text-align: left !important;
                margin-bottom: 10px;
            }
            .dataTables_filter input {
                width: 100% !important;
                margin-left: 0 !important;
            }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .navbar { padding: 0 16px; }
            .main   { padding: 24px 16px 48px; }
            .nav-greeting { display: none; }
            .page-header h1 { font-size: 24px; }
            .dataTables_wrapper { padding: 0 12px 16px; }
            .dataTables_length,
            .dataTables_filter {
                float: none !important;
                text-align: left !important;
                margin-bottom: 8px;
            }
            .dataTables_filter input {
                width: 100% !important;
                margin-left: 0 !important;
            }
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
            <a href="../auth/staff_logout.php" class="btn-logout" id="logout-btn">Sign Out</a>
        </div>
    </div>
</nav>

<!-- MAIN -->
<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header motion-fade-up">
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
    <div class="stats-row motion-reveal">
        <div class="stat-card total motion-lift">
            <div class="stat-dot"></div>
            <div class="stat-count motion-count"><?= $counts['Total'] ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card scheduled motion-lift">
            <div class="stat-dot"></div>
            <div class="stat-count motion-count"><?= $counts['Scheduled'] ?></div>
            <div class="stat-label">Scheduled</div>
        </div>
        <div class="stat-card completed motion-lift">
            <div class="stat-dot"></div>
            <div class="stat-count motion-count"><?= $counts['Completed'] ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card cancelled motion-lift">
            <div class="stat-dot"></div>
            <div class="stat-count motion-count"><?= $counts['Cancelled'] ?></div>
            <div class="stat-label">Cancelled</div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="section motion-reveal">
        <div class="section-label">All Appointments</div>
        <div class="card">

            <!-- SEARCH & FILTER -->
            <form method="GET" action="../staff/staff_dashboard.php">
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
                    <a href="../staff/staff_dashboard.php" class="btn-reset">Reset</a>
                </div>
            </form>

            <div class="table-header">
                <span class="table-title">Appointment Records</span>
                <span class="badge-count"><?= $result->num_rows ?> found</span>
            </div>

            <table id="appointmentsTable" class="display responsive nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Date & Time</th>
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
                                        <form method="POST" action="../staff/staff_dashboard.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" style="display:flex;gap:6px;align-items:center;">
                                            <input type="hidden" name="appt_id" value="<?= $row['id'] ?>">
                                            <select name="new_status" class="status-select">
                                                <option value="Scheduled" <?= $row['status'] === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                                <option value="Completed" <?= $row['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="Cancelled" <?= $row['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn-update">Save</button>
                                        </form>

                                        <!-- Delete -->
                                        <form method="POST" action="../staff/staff_dashboard.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>"
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

    <script>
$(document).ready(function() {
    $('#appointmentsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            search: 'Search:',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ appointments',
            infoEmpty: 'No appointments found',
            infoFiltered: '(filtered from _MAX_ total)',
            paginate: {
                first: 'First',
                last: 'Last',
                next: 'Next',
                previous: 'Previous'
            }
        },
        columnDefs: [
            { width: '60px', targets: 0 },
            { width: '200px', targets: 1 },
            { width: '140px', targets: 2 },
            { width: '100px', targets: 3 },
            { width: '90px', targets: 4 },
            { width: '220px', targets: 5 }
        ],
        initComplete: function() {
            // Add spacing between Show entries and Search
            $('.dataTables_length').css({
                'margin-right': '120px',
                'padding-right': '20px'
            });
            $('.dataTables_filter').css({
                'padding-left': '20px'
            });

            // Style the search input
            $('.dataTables_filter input').css({
                'padding': '10px 14px',
                'background': 'rgba(120,120,128,0.12)',
                'border': '1.5px solid transparent',
                'border-radius': '10px',
                'font-family': "'Inter', -apple-system, sans-serif",
                'font-size': '14px',
                'color': '#1c1c1e',
                'outline': 'none',
                'transition': 'border-color 0.2s, background 0.2s'
            });
            $('.dataTables_filter input').focus(function() {
                $(this).css({
                    'border-color': '#007aff',
                    'background': 'rgba(0,122,255,0.05)'
                });
            }).blur(function() {
                $(this).css({
                    'border-color': 'transparent',
                    'background': 'rgba(120,120,128,0.12)'
                });
            });

            // Style the length select
            $('.dataTables_length select').css({
                'padding': '6px 10px',
                'background': 'rgba(120,120,128,0.12)',
                'border': '1.5px solid transparent',
                'border-radius': '8px',
                'font-family': "'Inter', -apple-system, sans-serif",
                'font-size': '13px',
                'color': '#1c1c1e',
                'outline': 'none',
                'cursor': 'pointer'
            });
            $('.dataTables_length select').focus(function() {
                $(this).css('border-color', '#007aff');
            }).blur(function() {
                $(this).css('border-color', 'transparent');
            });
        }
    });
});
</script>

</body>
</html>