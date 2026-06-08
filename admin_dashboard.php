<?php
session_start();
require 'db.php';

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name    = $_SESSION['admin_name'];
$message       = "";
$message_class = "";

// ── ADD STAFF ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($fullname) && !empty($password)) {
        // Check duplicate username
        $check = $conn->prepare("SELECT id FROM staff WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message       = "Username already exists. Please choose a different one.";
            $message_class = "error";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $conn->prepare("INSERT INTO staff (username, password, fullname) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashed, $fullname);

            if ($stmt->execute()) {
                $message       = "Staff account for '{$fullname}' has been created successfully.";
                $message_class = "success";
            } else {
                $message       = "Database error: " . $conn->error;
                $message_class = "error";
            }
        }
    } else {
        $message       = "Please fill in all fields.";
        $message_class = "error";
    }
}

// ── DELETE STAFF ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $staff_id = (int) $_POST['staff_id'];

    if ($staff_id > 0) {
        $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
        $stmt->bind_param("i", $staff_id);

        if ($stmt->execute()) {
            $message       = "Staff account has been deleted.";
            $message_class = "success";
        } else {
            $message       = "Failed to delete: " . $conn->error;
            $message_class = "error";
        }
    }
}

// ── RESET PASSWORD ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $staff_id    = (int) $_POST['staff_id'];
    $new_password = trim($_POST['new_password']);

    if ($staff_id > 0 && !empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("UPDATE staff SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $staff_id);

        if ($stmt->execute()) {
            $message       = "Password has been reset successfully.";
            $message_class = "success";
        } else {
            $message       = "Failed to reset password: " . $conn->error;
            $message_class = "error";
        }
    } else {
        $message       = "Please provide a valid new password.";
        $message_class = "error";
    }
}

// ── FETCH ALL STAFF ───────────────────────────────────────────────────────────
$staff_result = $conn->query("SELECT id, username, fullname, created_at FROM staff ORDER BY created_at DESC");
$staff_count  = $staff_result ? $staff_result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — UiTM Health Unit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0f0f14;
            --surface:     #1a1a24;
            --surface2:    #22222e;
            --border:      rgba(255,255,255,0.07);
            --accent:      #a855f7;
            --accent-dim:  rgba(168,85,247,0.12);
            --accent-glow: rgba(168,85,247,0.25);
            --text:        #f0f0f5;
            --muted:       #8888a0;
            --green:       #4ade80;
            --green-dim:   rgba(74,222,128,0.10);
            --red:         #f87171;
            --red-dim:     rgba(248,113,113,0.10);
            --yellow:      #fbbf24;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(168,85,247,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(168,85,247,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(15,15,20,0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            height: 60px;
        }
        .navbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 28px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-icon {
            width: 32px; height: 32px;
            background: var(--accent-dim);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .nav-title {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .nav-badge {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--accent);
            background: var(--accent-dim);
            border: 1px solid rgba(168,85,247,0.25);
            padding: 3px 10px;
            border-radius: 20px;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .nav-greeting {
            font-size: 13px;
            color: var(--muted);
        }
        .btn-logout {
            font-size: 13px;
            font-weight: 600;
            color: var(--red);
            background: var(--red-dim);
            border: 1px solid rgba(248,113,113,0.2);
            padding: 7px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn-logout:hover { opacity: 0.8; }

        /* ── MAIN ── */
        .main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 28px 60px;
            position: relative;
            z-index: 1;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.6px;
            margin-bottom: 4px;
        }
        .page-header p {
            font-size: 14px;
            color: var(--muted);
        }

        /* ── ALERT ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 28px;
            line-height: 1.5;
            animation: fadeUp 0.25s ease;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-icon {
            width: 20px; height: 20px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0; margin-top: 1px;
        }
        .alert.success {
            background: var(--green-dim);
            border: 1px solid rgba(74,222,128,0.2);
            color: var(--green);
        }
        .alert.success .alert-icon { background: var(--green); color: #000; }
        .alert.error {
            background: var(--red-dim);
            border: 1px solid rgba(248,113,113,0.2);
            color: var(--red);
        }
        .alert.error .alert-icon { background: var(--red); color: #fff; }

        /* ── LAYOUT ── */
        .layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 860px) {
            .layout { grid-template-columns: 1fr; }
        }

        /* ── CARD ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
        }
        .card-header {
            padding: 20px 24px 0;
            border-bottom: 1px solid var(--border);
            padding-bottom: 16px;
            margin-bottom: 0;
        }
        .card-header h2 {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.2px;
        }
        .card-header p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }
        .card-body { padding: 24px; }

        /* ── FORM FIELDS ── */
        .field { margin-bottom: 16px; }
        .field:last-of-type { margin-bottom: 0; }

        label.field-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 7px;
        }

        .field-input {
            width: 100%;
            padding: 11px 14px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: var(--text);
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .field-input::placeholder { color: rgba(255,255,255,0.18); }
        .field-input:focus {
            border-color: var(--accent);
            background: rgba(168,85,247,0.06);
            box-shadow: 0 0 0 3px rgba(168,85,247,0.12);
        }

        .btn-add {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 0 18px var(--accent-glow);
        }
        .btn-add:hover  { opacity: 0.88; }
        .btn-add:active { transform: scale(0.98); }

        /* ── STAFF TABLE ── */
        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border);
        }
        .table-title {
            font-size: 15px;
            font-weight: 700;
        }
        .count-pill {
            font-size: 12px;
            font-weight: 600;
            color: var(--accent);
            background: var(--accent-dim);
            padding: 3px 12px;
            border-radius: 20px;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            padding: 12px 24px;
            text-align: left;
            background: rgba(255,255,255,0.02);
        }
        tbody tr {
            border-top: 1px solid var(--border);
            transition: background 0.15s;
        }
        tbody tr:hover { background: rgba(255,255,255,0.025); }
        tbody td {
            padding: 14px 24px;
            font-size: 13px;
            vertical-align: middle;
        }
        .staff-name {
            font-weight: 600;
            color: var(--text);
        }
        .staff-username {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }
        .staff-date {
            font-size: 12px;
            color: var(--muted);
        }

        /* ── ACTION BUTTONS ── */
        .actions { display: flex; gap: 8px; align-items: center; }

        .btn-reset {
            font-size: 12px;
            font-weight: 600;
            color: var(--yellow);
            background: rgba(251,191,36,0.10);
            border: 1px solid rgba(251,191,36,0.2);
            padding: 6px 12px;
            border-radius: 7px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: opacity 0.2s;
        }
        .btn-reset:hover { opacity: 0.75; }

        .btn-delete {
            font-size: 12px;
            font-weight: 600;
            color: var(--red);
            background: var(--red-dim);
            border: 1px solid rgba(248,113,113,0.2);
            padding: 6px 12px;
            border-radius: 7px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: opacity 0.2s;
        }
        .btn-delete:hover { opacity: 0.75; }

        /* ── EMPTY STATE ── */
        .empty {
            text-align: center;
            padding: 56px 24px;
        }
        .empty-icon { font-size: 36px; margin-bottom: 14px; opacity: 0.3; }
        .empty-title { font-size: 14px; font-weight: 600; color: var(--muted); margin-bottom: 4px; }
        .empty-sub { font-size: 12px; color: rgba(136,136,160,0.6); }

        /* ── MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(6px);
            z-index: 200;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-overlay.active { display: flex; }

        .modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 28px;
            width: 100%;
            max-width: 380px;
            animation: fadeUp 0.25s ease;
        }
        .modal h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .modal p {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 20px;
        }
        .modal .field-input { margin-bottom: 16px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button {
            flex: 1;
            padding: 11px;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .btn-cancel-modal {
            background: var(--surface2);
            color: var(--muted);
        }
        .btn-confirm-reset {
            background: var(--yellow);
            color: #000;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-inner">
        <div class="nav-brand">
            <div class="nav-icon">🔐</div>
            <span class="nav-title">Health Unit</span>
            <span class="nav-badge">Admin</span>
        </div>
        <div class="nav-right">
            <span class="nav-greeting">Signed in as <strong><?= htmlspecialchars($admin_name) ?></strong></span>
            <a href="admin_logout.php" class="btn-logout">Sign Out</a>
        </div>
    </div>
</nav>

<!-- MAIN -->
<div class="main">

    <div class="page-header">
        <h1>Staff Management</h1>
        <p>Add, delete, or reset passwords for staff accounts.</p>
    </div>

    <!-- ALERT -->
    <?php if (!empty($message)): ?>
        <div class="alert <?= $message_class ?>">
            <div class="alert-icon"><?= $message_class === 'success' ? '✓' : '✕' ?></div>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <div class="layout">

        <!-- LEFT: ADD STAFF FORM -->
        <div class="card">
            <div class="card-header">
                <h2>➕ Add Staff Account</h2>
                <p>New credentials are hashed before storage.</p>
            </div>
            <div class="card-body">
                <form action="admin_dashboard.php" method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="field">
                        <label class="field-label" for="fullname">Full Name</label>
                        <input class="field-input" type="text" id="fullname" name="fullname"
                               placeholder="e.g. Nurse Fatimah" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="username">Username</label>
                        <input class="field-input" type="text" id="username" name="username"
                               placeholder="e.g. nurse_fatimah" required
                               autocomplete="off">
                    </div>
                    <div class="field">
                        <label class="field-label" for="password">Password</label>
                        <input class="field-input" type="password" id="password" name="password"
                               placeholder="Minimum 8 characters" required
                               autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-add">Create Staff Account</button>
                </form>
            </div>
        </div>

        <!-- RIGHT: STAFF TABLE -->
        <div class="card">
            <div class="table-header">
                <span class="table-title">All Staff Accounts</span>
                <span class="count-pill"><?= $staff_count ?> account<?= $staff_count !== 1 ? 's' : '' ?></span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($staff_count > 0): ?>
                            <?php while ($row = $staff_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="staff-name"><?= htmlspecialchars($row['fullname']) ?></div>
                                        <div class="staff-username">@<?= htmlspecialchars($row['username']) ?></div>
                                    </td>
                                    <td>
                                        <span class="staff-date">
                                            <?= date('d M Y', strtotime($row['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <!-- Reset Password Button -->
                                            <button class="btn-reset"
                                                    onclick="openResetModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['fullname'])) ?>')">
                                                Reset PW
                                            </button>

                                            <!-- Delete Button -->
                                            <form action="admin_dashboard.php" method="POST"
                                                  onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($row['fullname'])) ?>? This cannot be undone.')">
                                                <input type="hidden" name="action"   value="delete">
                                                <input type="hidden" name="staff_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn-delete">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">
                                    <div class="empty">
                                        <div class="empty-icon">👤</div>
                                        <div class="empty-title">No staff accounts yet</div>
                                        <div class="empty-sub">Add one using the form on the left</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-overlay" id="resetModal">
    <div class="modal">
        <h3>🔑 Reset Password</h3>
        <p id="resetModalLabel">Enter a new password for this staff member.</p>
        <form action="admin_dashboard.php" method="POST">
            <input type="hidden" name="action"   value="reset_password">
            <input type="hidden" name="staff_id" id="resetStaffId">
            <input class="field-input" type="password" name="new_password"
                   placeholder="New password" required autocomplete="new-password">
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeResetModal()">Cancel</button>
                <button type="submit" class="btn-confirm-reset">Reset</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openResetModal(id, name) {
        document.getElementById('resetStaffId').value = id;
        document.getElementById('resetModalLabel').textContent =
            'Enter a new password for ' + name + '.';
        document.getElementById('resetModal').classList.add('active');
    }
    function closeResetModal() {
        document.getElementById('resetModal').classList.remove('active');
    }
    // Close modal on overlay click
    document.getElementById('resetModal').addEventListener('click', function(e) {
        if (e.target === this) closeResetModal();
    });
</script>

</body>
</html>
