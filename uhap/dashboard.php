<?php
session_start();
require 'db.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require the PHPMailer file paths
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// Redirect back to login if student is not logged in
if (!isset($_SESSION['studentno'])) {
    header("Location: login.php");
    exit;
}

$studentno   = $_SESSION['studentno'];
$studentname = $_SESSION['studentname'];
$studentemail = $_SESSION['studentemail'];

$message       = "";
$message_class = "";
$receipt_html  = "";

// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPER: builds the PHPMailer instance (reused for both email types)
// ─────────────────────────────────────────────────────────────────────────────
function createMailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug  = 2;
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'aqilfaris20062@gmail.com';   // ← your Gmail
    $mail->Password   = 'aufcuiagnyyutnme';           // ← your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
            'cafile'           => 'C:/xampp/php/extras/ssl/cacert.pem',
        
        ],
    ];
    $mail->setFrom('aqilfaris20062@gmail.com', 'UiTM Health Unit');
    return $mail;
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPER: builds the HTML receipt card used in both email + screen
// ─────────────────────────────────────────────────────────────────────────────
function buildReceiptHTML(string $studentname, string $studentno, string $studentemail, string $formatted_time): string
{
    return "
        <div style='font-family: Arial, sans-serif; padding: 25px; border: 2px dashed #330066;
                    border-radius: 12px; max-width: 500px; margin: 20px auto;
                    background-color: #fdfbfe; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>

            <h2 style='color: #330066; text-align: center; margin-top: 0;
                       font-size: 24px; letter-spacing: 1px;'>UiTM Health Unit</h2>
            <h4 style='color: #666; text-align: center; margin-bottom: 20px;
                       text-transform: uppercase; font-size: 11px;
                       border-bottom: 1px solid #ddd; padding-bottom: 10px;
                       letter-spacing: 2px;'>Official Booking Receipt</h4>

            <p style='margin: 8px 0;'><strong>Student Name:</strong>
                <span style='color: #333;'>{$studentname}</span></p>
            <p style='margin: 8px 0;'><strong>Student ID:</strong>
                <span style='color: #333;'>{$studentno}</span></p>
            <p style='margin: 8px 0;'><strong>Registered Email:</strong>
                <span style='color: #333;'>{$studentemail}</span></p>

            <hr style='border: 0; border-top: 1px solid #eee; margin: 15px 0;'>

            <p style='margin-bottom: 5px; font-weight: bold; color: #330066;'>
                Scheduled Consultation Slot:</p>
            <div style='background-color: #330066; color: white; padding: 15px;
                        text-align: center; border-radius: 6px; font-weight: bold;
                        font-size: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                {$formatted_time}
            </div>

            <p style='color: #ff0000; font-size: 12px; margin-top: 20px;
                      text-align: center; font-style: italic;'>
                * Please arrive at the health unit reception counter 10 minutes prior to your time.
            </p>
        </div>
    ";
}

// ─────────────────────────────────────────────────────────────────────────────
// HANDLE APPOINTMENT BOOKING
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    $appointment_time = $_POST['appointment_time'];

    if (!empty($appointment_time)) {

        // 1. Insert into database (reminder_sent starts at 0)
        $stmt = $conn->prepare(
            "INSERT INTO appointments (studentno, appointment_datetime, status, reminder_sent)
             VALUES (?, ?, 'Scheduled', 0)"
        );
        $stmt->bind_param("ss", $studentno, $appointment_time);

        if ($stmt->execute()) {

            $formatted_time  = date('F j, Y, g:i a', strtotime($appointment_time));
            $receipt_template = buildReceiptHTML($studentname, $studentno, $studentemail, $formatted_time);

            // 2. Send confirmation email
            try {
                $mail = createMailer();
                $mail->addAddress($studentemail, $studentname);
                $mail->isHTML(true);
                $mail->Subject = 'UiTM Health Unit – Appointment Confirmed ✅';
                $mail->Body    = $receipt_template;
                $mail->send();

                $message       = "Appointment booked! A confirmation email has been sent to " . htmlspecialchars($studentemail) . ".";
                $message_class = "success";

            } catch (Exception $e) {
                // DB save succeeded — show receipt on-screen as fallback
                $message       = "Appointment saved! Note: Email could not be sent (network issue). Your receipt is shown below.";
                $message_class = "success";
                $receipt_html  = $receipt_template;
            }

        } else {
            $message       = "Something went wrong saving your appointment. Please try again.";
            $message_class = "error";
        }

    } else {
        $message       = "Please select a valid date and time.";
        $message_class = "error";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// FETCH EXISTING APPOINTMENTS FOR THIS STUDENT
// ─────────────────────────────────────────────────────────────────────────────
$query = "SELECT * FROM appointments WHERE studentno = ? ORDER BY appointment_datetime DESC";
$stmt  = $conn->prepare($query);
$stmt->bind_param("s", $studentno);
$stmt->execute();
$appointments_result = $stmt->get_result();

// Count appointments for stats
$total_appts = $appointments_result->num_rows;
$counts = ['Scheduled' => 0, 'Completed' => 0, 'Cancelled' => 0];
$appointments_result->data_seek(0);
while ($r = $appointments_result->fetch_assoc()) {
    if (isset($counts[$r['status']])) $counts[$r['status']]++;
}
$appointments_result->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — UiTM Health Unit</title>
    <meta name="description" content="Manage and schedule your health unit appointments at UiTM.">
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
            max-width: 860px; width: 100%; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; }
        .nav-icon {
            width: 32px; height: 32px; background: var(--blue);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 16px; flex-shrink: 0;
        }
        .nav-title { font-size: 15px; font-weight: 600; color: var(--label-primary); letter-spacing: -0.2px; }
        .nav-right  { display: flex; align-items: center; gap: 12px; }
        .nav-greeting { font-size: 13px; color: var(--label-secondary); }
        .nav-greeting strong { color: var(--label-primary); font-weight: 600; }
        .btn-logout {
            font-size: 13px; font-weight: 500; color: var(--blue);
            text-decoration: none; padding: 6px 14px; border-radius: 8px;
            background: var(--fill-1); transition: background 0.18s; white-space: nowrap;
        }
        .btn-logout:hover { background: var(--fill-2); }

        /* ── MAIN ── */
        .main { max-width: 860px; margin: 0 auto; padding: 40px 24px 60px; }

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 32px; }
        .page-header h1 {
            font-size: 30px; font-weight: 700; letter-spacing: -0.6px;
            color: var(--label-primary); line-height: 1.1; margin-bottom: 6px;
        }
        .page-meta { font-size: 13px; color: var(--label-secondary); display: flex; align-items: center; gap: 8px; }
        .page-meta .dot { width: 3px; height: 3px; background: var(--label-tertiary); border-radius: 50%; display: inline-block; }

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
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 12px; margin-bottom: 20px;
        }
        .stat-card {
            background: var(--bg-secondary); border-radius: 18px;
            padding: 22px 20px; transition: transform 0.2s, box-shadow 0.2s; cursor: default;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .stat-dot { width: 8px; height: 8px; border-radius: 50%; margin-bottom: 14px; }
        .stat-card.scheduled .stat-dot { background: var(--blue); }
        .stat-card.completed .stat-dot { background: var(--green); }
        .stat-card.cancelled .stat-dot { background: var(--red); }
        .stat-count { font-size: 38px; font-weight: 700; letter-spacing: -1.5px; line-height: 1; margin-bottom: 6px; }
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

        /* ── FORM ── */
        .form-body { padding: 20px 22px 24px; }
        .form-description { font-size: 14px; color: var(--label-secondary); margin-bottom: 20px; line-height: 1.5; }
        .field-group { margin-bottom: 18px; }
        .field-label { font-size: 12px; font-weight: 600; color: var(--label-secondary); margin-bottom: 8px; display: block; }
        .datetime-input {
            width: 100%; max-width: 340px;
            padding: 12px 14px; background: var(--fill-1);
            border: 1.5px solid transparent; border-radius: 12px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 15px; color: var(--label-primary);
            outline: none; transition: border-color 0.2s, background 0.2s;
        }
        .datetime-input:focus { border-color: var(--blue); background: rgba(0,122,255,0.05); }
        .btn-primary {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--blue); color: #fff; border: none;
            border-radius: 12px; padding: 13px 26px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 15px; font-weight: 600; cursor: pointer;
            transition: background 0.2s, transform 0.15s; letter-spacing: -0.1px;
        }
        .btn-primary:hover  { background: var(--blue-hover); }
        .btn-primary:active { transform: scale(0.97); }

        /* ── TABLE ── */
        .table-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 22px 14px; border-bottom: 1px solid var(--separator);
        }
        .table-title { font-size: 15px; font-weight: 600; color: var(--label-primary); letter-spacing: -0.2px; }
        .badge-count {
            font-size: 12px; font-weight: 600; color: var(--label-secondary);
            background: var(--fill-1); padding: 3px 10px; border-radius: 20px;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 11px 22px; text-align: left;
            font-size: 11px; font-weight: 600; letter-spacing: 0.5px;
            text-transform: uppercase; color: var(--label-tertiary);
            border-bottom: 1px solid var(--separator);
        }
        tbody tr { transition: background 0.12s; }
        tbody tr:hover { background: var(--fill-2); }
        tbody td {
            padding: 14px 22px; font-size: 14px; color: var(--label-primary);
            border-bottom: 1px solid var(--separator); vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        .td-date { font-weight: 500; }
        .td-time { display: block; font-size: 12px; color: var(--label-secondary); margin-top: 2px; }

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

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 56px 24px; }
        .empty-icon  { font-size: 40px; margin-bottom: 14px; opacity: 0.35; }
        .empty-title { font-size: 15px; font-weight: 600; color: var(--label-secondary); margin-bottom: 4px; }
        .empty-sub   { font-size: 13px; color: var(--label-tertiary); }

        /* ── RECEIPT WRAP ── */
        .receipt-wrap { margin-bottom: 20px; }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .stats-row .stat-card:last-child { grid-column: span 2; }
            .navbar { padding: 0 16px; }
            .main   { padding: 28px 16px 48px; }
            .page-header h1 { font-size: 24px; }
            .nav-greeting   { display: none; }
            .datetime-input { max-width: 100%; }
        }
        @media (max-width: 400px) {
            .stats-row { grid-template-columns: 1fr; }
            .stats-row .stat-card:last-child { grid-column: span 1; }
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
        </div>
        <div class="nav-right">
            <span class="nav-greeting">Welcome, <strong><?= htmlspecialchars($studentname) ?></strong></span>
            <a href="logout.php" class="btn-logout" id="logout-btn">Sign Out</a>
        </div>
    </div>
</nav>

<!-- MAIN -->
<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>My Appointments</h1>
        <div class="page-meta">
            <span><?= htmlspecialchars($studentno) ?></span>
            <span class="dot"></span>
            <span><?= htmlspecialchars($studentemail) ?></span>
        </div>
    </div>

    <!-- ALERT -->
    <?php if (!empty($message)): ?>
        <div class="alert <?= $message_class ?>" role="alert">
            <div class="alert-icon"><?= $message_class === 'success' ? '✓' : '✕' ?></div>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- RECEIPT -->
    <?php if (!empty($receipt_html)): ?>
        <div class="receipt-wrap"><?= $receipt_html ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
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

    <!-- BOOKING FORM -->
    <div class="section">
        <div class="section-label">New Appointment</div>
        <div class="card">
            <div class="form-body">
                <p class="form-description">
                    Select your preferred date and time to visit the UiTM Health Unit clinic.
                    A confirmation receipt will be sent to your registered email.
                </p>
                <form action="dashboard.php" method="POST">
                    <div class="field-group">
                        <label class="field-label" for="appointment_time">Date &amp; Time</label>
                        <input class="datetime-input" type="datetime-local"
                               id="appointment_time" name="appointment_time" required>
                    </div>
                    <button type="submit" name="book_appointment" class="btn-primary" id="book-btn">
                        Confirm Appointment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- APPOINTMENTS TABLE -->
    <div class="section">
        <div class="section-label">History</div>
        <div class="card">
            <div class="table-header">
                <span class="table-title">Appointment Records</span>
                <span class="badge-count"><?= $total_appts ?> total</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                        <th>Reminder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($appointments_result->num_rows > 0): ?>
                        <?php while ($row = $appointments_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="td-date"><?= date('d M Y', strtotime($row['appointment_datetime'])) ?></span>
                                    <span class="td-time"><?= date('g:i a', strtotime($row['appointment_datetime'])) ?></span>
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
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">
                                <div class="empty-state">
                                    <div class="empty-icon">🗓</div>
                                    <div class="empty-title">No appointments yet</div>
                                    <div class="empty-sub">Schedule your first visit above</div>
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
    document.querySelector('form').addEventListener('submit', function () {
        const btn = document.getElementById('book-btn');
        btn.style.opacity = '0.7';
        btn.style.transform = 'scale(0.97)';
    });
</script>

</body>
</html>