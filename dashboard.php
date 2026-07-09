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

$studentno    = $_SESSION['studentno'];
$studentname  = $_SESSION['studentname'];
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
    $mail->SMTPDebug  = 0;
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
            'cafile'            => 'C:/xampp/php/extras/ssl/cacert.pem',
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
// SHARED HELPER: builds the HTML cancellation receipt (email + screen)
// ─────────────────────────────────────────────────────────────────────────────
function buildCancellationHTML(string $studentname, string $studentno, string $studentemail, string $formatted_time): string
{
    return "
        <div style='font-family: Arial, sans-serif; padding: 25px; border: 2px dashed #cc0000;
                    border-radius: 12px; max-width: 500px; margin: 20px auto;
                    background-color: #fffafa; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>

            <h2 style='color: #cc0000; text-align: center; margin-top: 0;
                       font-size: 24px; letter-spacing: 1px;'>UiTM Health Unit</h2>
            <h4 style='color: #666; text-align: center; margin-bottom: 20px;
                       text-transform: uppercase; font-size: 11px;
                       border-bottom: 1px solid #ddd; padding-bottom: 10px;
                       letter-spacing: 2px;'>Appointment Cancellation</h4>

            <p style='color: #333; margin: 8px 0;'>Dear <strong>{$studentname}</strong>,</p>
            <p style='color: #555; margin: 8px 0;'>
                Your appointment at the <strong>UiTM Health Unit</strong> has been
                <strong style='color:#cc0000;'>cancelled</strong> as requested.
            </p>

            <p style='margin: 8px 0;'><strong>Student ID:</strong>
                <span style='color: #333;'>{$studentno}</span></p>
            <p style='margin: 8px 0;'><strong>Registered Email:</strong>
                <span style='color: #333;'>{$studentemail}</span></p>

            <hr style='border: 0; border-top: 1px solid #eee; margin: 15px 0;'>

            <p style='margin-bottom: 5px; font-weight: bold; color: #cc0000;'>
                Cancelled Slot:</p>
            <div style='background-color: #cc0000; color: white; padding: 15px;
                        text-align: center; border-radius: 6px; font-weight: bold;
                        font-size: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        text-decoration: line-through;'>
                {$formatted_time}
            </div>

            <p style='color: #555; font-size: 12px; margin-top: 20px;
                      text-align: center; font-style: italic;'>
                Need to see us? You can book a new appointment any time from your dashboard.
            </p>
        </div>
    ";
}

// ─────────────────────────────────────────────────────────────────────────────
// VALIDATION HELPER: check if a datetime falls within allowed working hours
// ─────────────────────────────────────────────────────────────────────────────
function isWithinWorkingHours(string $datetime_str): array
{
    $dt       = new DateTime($datetime_str);
    $day      = (int)$dt->format('w');   // 0=Sun … 6=Sat
    $hour     = (int)$dt->format('G');   // 0-23
    $minute   = (int)$dt->format('i');
    $time_min = $hour * 60 + $minute;    // minutes since midnight

    // Saturday (6) or Sunday (0)
    if ($day === 0 || $day === 6) {
        return [false, "The health unit is closed on weekends. Please choose a weekday."];
    }

    // Friday (5): 8:00–12:00 AND 15:00–17:00
    if ($day === 5) {
        $in_morning   = ($time_min >= 480 && $time_min < 720);   // 8:00–11:59
        $in_afternoon = ($time_min >= 900 && $time_min < 1020);  // 15:00–16:59
        if (!$in_morning && !$in_afternoon) {
            return [false, "Friday hours are 8:00 AM–12:00 PM and 3:00–5:00 PM (closed 12–3 PM for Jumaat prayer)."];
        }
        return [true, ""];
    }

    // Monday–Thursday (1-4): 8:00–17:00
    if ($time_min < 480 || $time_min >= 1020) {
        return [false, "Appointments are available from 8:00 AM to 5:00 PM, Monday–Thursday."];
    }

    return [true, ""];
}

// ─────────────────────────────────────────────────────────────────────────────
// VALIDATION HELPER: check for overlapping appointment (same date+time, not cancelled)
// ─────────────────────────────────────────────────────────────────────────────
function isSlotTaken(mysqli $conn, string $datetime_str): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM appointments
         WHERE appointment_datetime = ? AND status != 'Cancelled'"
    );
    $stmt->bind_param("s", $datetime_str);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return ($result['cnt'] > 0);
}

// ─────────────────────────────────────────────────────────────────────────────
// HANDLE APPOINTMENT BOOKING
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    // The date-picker sends "YYYY-MM-DDTHH:MM" (ISO, with a 'T' and no seconds).
    // Normalise to MySQL's canonical "YYYY-MM-DD HH:MM:SS" so the INSERT and the
    // overlap check work reliably (strict SQL mode rejects the raw 'T' format).
    $raw_time         = $_POST['appointment_time'] ?? '';
    $ts               = strtotime($raw_time);
    $appointment_time = $ts ? date('Y-m-d H:i:s', $ts) : '';

    if (!empty($appointment_time)) {

        // ── Validation 1: On-the-hour only (1-hour booking interval) ──
        [$hours_ok, $hours_msg] = isWithinWorkingHours($appointment_time);
        $check_dt = new DateTime($appointment_time);

        if ((int)$check_dt->format('i') !== 0) {
            $message       = "Appointments can only be booked on the hour (e.g. 9:00, 10:00). Please choose a valid time slot.";
            $message_class = "error";

        // ── Validation 2: Working hours ──
        } elseif (!$hours_ok) {
            $message       = $hours_msg;
            $message_class = "error";

        // ── Validation 3: No past dates ──
        } elseif (new DateTime($appointment_time) < new DateTime()) {
            $message       = "You cannot book an appointment in the past. Please select a future date and time.";
            $message_class = "error";

        // ── Validation 4: Overlap check ──
        } elseif (isSlotTaken($conn, $appointment_time)) {
            $message       = "This time slot is already booked by another student. Please choose a different time.";
            $message_class = "error";

        } else {

            // All checks passed — insert into database
            $stmt = $conn->prepare(
                "INSERT INTO appointments (studentno, appointment_datetime, status, reminder_sent)
                 VALUES (?, ?, 'Scheduled', 0)"
            );
            $stmt->bind_param("ss", $studentno, $appointment_time);

            if ($stmt->execute()) {

                $formatted_time   = date('F j, Y, g:i a', strtotime($appointment_time));
                $receipt_template = buildReceiptHTML($studentname, $studentno, $studentemail, $formatted_time);

                // Send confirmation email
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
                    $message       = "Appointment saved! Note: Email could not be sent (network issue). Your receipt is shown below.";
                    $message_class = "success";
                    $receipt_html  = $receipt_template;
                }

            } else {
                $message       = "Something went wrong saving your appointment. Please try again.";
                $message_class = "error";
            }
        }

    } else {
        $message       = "Please select a valid date and time.";
        $message_class = "error";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HANDLE APPOINTMENT CANCELLATION (student cancels their own appointment)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_appointment'])) {
    $appt_id = (int)($_POST['appointment_id'] ?? 0);

    if ($appt_id > 0) {
        // Fetch the appointment — but ONLY if it belongs to THIS logged-in student
        $stmt = $conn->prepare(
            "SELECT id, appointment_datetime, status
             FROM appointments
             WHERE id = ? AND studentno = ?"
        );
        $stmt->bind_param("is", $appt_id, $studentno);
        $stmt->execute();
        $appt = $stmt->get_result()->fetch_assoc();

        if (!$appt) {
            $message       = "Appointment not found.";
            $message_class = "error";

        } elseif ($appt['status'] !== 'Scheduled') {
            $message       = "Only scheduled appointments can be cancelled.";
            $message_class = "error";

        } elseif (new DateTime($appt['appointment_datetime']) < new DateTime()) {
            $message       = "This appointment has already passed and can no longer be cancelled.";
            $message_class = "error";

        } else {
            // Ownership + eligibility verified — mark as cancelled
            $upd = $conn->prepare(
                "UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND studentno = ?"
            );
            $upd->bind_param("is", $appt_id, $studentno);

            if ($upd->execute()) {
                $formatted_time  = date('F j, Y, g:i a', strtotime($appt['appointment_datetime']));
                $cancel_template = buildCancellationHTML($studentname, $studentno, $studentemail, $formatted_time);

                // Send cancellation confirmation email
                try {
                    $mail = createMailer();
                    $mail->addAddress($studentemail, $studentname);
                    $mail->isHTML(true);
                    $mail->Subject = 'UiTM Health Unit – Appointment Cancelled';
                    $mail->Body    = $cancel_template;
                    $mail->send();

                    $message       = "Your appointment has been cancelled. A confirmation has been sent to " . htmlspecialchars($studentemail) . ".";
                    $message_class = "success";

                } catch (Exception $e) {
                    $message       = "Your appointment has been cancelled. Note: the confirmation email could not be sent (network issue). Your cancellation receipt is shown below.";
                    $message_class = "success";
                    $receipt_html  = $cancel_template;
                }

            } else {
                $message       = "Something went wrong cancelling your appointment. Please try again.";
                $message_class = "error";
            }
        }
    } else {
        $message       = "Invalid appointment selected.";
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
            --orange:          #ff9500;
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

        /* ── DATE INPUT ── */
        .date-input {
            width: 100%; max-width: 340px;
            padding: 12px 14px; background: var(--fill-1);
            border: 1.5px solid transparent; border-radius: 12px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 15px; color: var(--label-primary);
            outline: none; transition: border-color 0.2s, background 0.2s;
        }
        .date-input:focus { border-color: var(--blue); background: rgba(0,122,255,0.05); }

        /* ── TIME SLOT PICKER ── */
        .slot-picker { margin-top: 18px; }
        .slot-picker-label { font-size: 12px; font-weight: 600; color: var(--label-secondary); margin-bottom: 10px; display: block; }
        .slot-hint {
            font-size: 12px; color: var(--label-tertiary); margin-bottom: 12px;
            display: flex; align-items: center; gap: 6px;
        }
        .slot-hint-icon { font-size: 14px; }

        .slot-period-label {
            font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.4px; color: var(--label-tertiary);
            margin-bottom: 8px; margin-top: 14px;
        }
        .slot-period-label:first-child { margin-top: 0; }

        .slots-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 8px;
        }
        .slot-btn {
            padding: 10px 6px; border-radius: 10px; border: 1.5px solid var(--separator);
            background: var(--bg-secondary); font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px; font-weight: 500; color: var(--label-primary);
            cursor: pointer; transition: all 0.18s; text-align: center;
        }
        .slot-btn:hover:not(.slot-taken):not(.slot-selected) {
            border-color: var(--blue); background: rgba(0,122,255,0.04);
        }
        .slot-btn.slot-selected {
            border-color: var(--blue); background: var(--blue); color: #fff; font-weight: 600;
        }
        .slot-btn.slot-taken {
            background: var(--fill-2); color: var(--label-tertiary);
            border-color: transparent; cursor: not-allowed;
            text-decoration: line-through; opacity: 0.55;
        }
        .slot-btn.slot-past {
            background: var(--fill-2); color: var(--label-tertiary);
            border-color: transparent; cursor: not-allowed; opacity: 0.4;
        }

        .slot-loading {
            padding: 24px; text-align: center; font-size: 13px;
            color: var(--label-tertiary);
        }
        .slot-empty {
            padding: 24px; text-align: center; font-size: 13px;
            color: var(--label-tertiary);
        }

        /* hidden real input */
        #appointment_time { display: none; }

        /* ── HOURS INFO ── */
        .hours-info {
            display: flex; gap: 8px; flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .hours-chip {
            font-size: 11px; font-weight: 500; padding: 5px 10px;
            border-radius: 8px; background: var(--fill-1); color: var(--label-secondary);
        }
        .hours-chip strong { color: var(--label-primary); font-weight: 600; }

        .btn-primary {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--blue); color: #fff; border: none;
            border-radius: 12px; padding: 13px 26px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 15px; font-weight: 600; cursor: pointer;
            transition: background 0.2s, transform 0.15s, opacity 0.2s; letter-spacing: -0.1px;
        }
        .btn-primary:hover  { background: var(--blue-hover); }
        .btn-primary:active { transform: scale(0.97); }
        .btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }

        /* ── CANCEL BUTTON ── */
        .btn-cancel {
            background: rgba(255,59,48,0.10); color: #c0251b;
            border: 1px solid rgba(255,59,48,0.18);
            border-radius: 8px; padding: 6px 14px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 12px; font-weight: 600; cursor: pointer;
            transition: background 0.18s, transform 0.15s;
        }
        .btn-cancel:hover  { background: rgba(255,59,48,0.18); }
        .btn-cancel:active { transform: scale(0.96); }
        .cancel-form { margin: 0; }
        .action-none { color: var(--label-tertiary); }

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

        /* ── SLOT LEGEND ── */
        .slot-legend {
            display: flex; gap: 16px; margin-top: 14px; flex-wrap: wrap;
        }
        .legend-item {
            display: flex; align-items: center; gap: 6px;
            font-size: 11px; color: var(--label-tertiary);
        }
        .legend-swatch {
            width: 12px; height: 12px; border-radius: 4px; flex-shrink: 0;
        }
        .legend-swatch.avail  { border: 1.5px solid var(--separator); background: var(--bg-secondary); }
        .legend-swatch.booked { background: var(--fill-2); opacity: 0.55; }
        .legend-swatch.chosen { background: var(--blue); }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .stats-row .stat-card:last-child { grid-column: span 2; }
            .navbar { padding: 0 16px; }
            .main   { padding: 28px 16px 48px; }
            .page-header h1 { font-size: 24px; }
            .nav-greeting   { display: none; }
            .date-input     { max-width: 100%; }
            .slots-grid     { grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); }
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

                <!-- Working hours reference -->
                <div class="hours-info">
                    <span class="hours-chip"><strong>Mon–Thu</strong>&nbsp; 8 AM – 5 PM</span>
                    <span class="hours-chip"><strong>Fri</strong>&nbsp; 8 AM–12 PM &amp; 3–5 PM</span>
                    <span class="hours-chip"><strong>Sat–Sun</strong>&nbsp; Closed</span>
                </div>

                <form action="dashboard.php" method="POST" id="booking-form">
                    <!-- Hidden field that holds the final datetime value -->
                    <input type="hidden" id="appointment_time" name="appointment_time">
                    <!-- Keeps the booking flag in the POST data even though the submit
                         button (which also carries this name) gets disabled on submit -->
                    <input type="hidden" name="book_appointment" value="1">

                    <!-- Step 1: Pick a date -->
                    <div class="field-group">
                        <label class="field-label" for="date_picker">1. Choose a Date</label>
                        <input class="date-input" type="date" id="date_picker" required>
                    </div>

                    <!-- Step 2: Pick a time slot (rendered by JS) -->
                    <div class="slot-picker" id="slot-picker" style="display:none;">
                        <label class="slot-picker-label">2. Choose a Time Slot</label>
                        <div id="slot-container"></div>
                        <div class="slot-legend">
                            <div class="legend-item"><div class="legend-swatch avail"></div> Available</div>
                            <div class="legend-item"><div class="legend-swatch booked"></div> Booked</div>
                            <div class="legend-item"><div class="legend-swatch chosen"></div> Selected</div>
                        </div>
                    </div>

                    <button type="submit" name="book_appointment" class="btn-primary" id="book-btn"
                            disabled style="margin-top: 22px;">
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
                        <th>Action</th>
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
                                <td>
                                    <?php
                                        $is_future  = (strtotime($row['appointment_datetime']) > time());
                                        $can_cancel = ($row['status'] === 'Scheduled' && $is_future);
                                    ?>
                                    <?php if ($can_cancel): ?>
                                        <form action="dashboard.php" method="POST" class="cancel-form"
                                              onsubmit="return confirm('Cancel this appointment? This cannot be undone.');">
                                            <input type="hidden" name="appointment_id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" name="cancel_appointment" class="btn-cancel">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="action-none">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">
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
(function () {
    const datePicker     = document.getElementById('date_picker');
    const slotPicker     = document.getElementById('slot-picker');
    const slotContainer  = document.getElementById('slot-container');
    const hiddenInput    = document.getElementById('appointment_time');
    const bookBtn        = document.getElementById('book-btn');

    // Set min date to today
    const today = new Date();
    const yyyy  = today.getFullYear();
    const mm    = String(today.getMonth() + 1).padStart(2, '0');
    const dd    = String(today.getDate()).padStart(2, '0');
    datePicker.setAttribute('min', `${yyyy}-${mm}-${dd}`);

    let selectedSlot = null;

    // ── Working-hours definition ──
    // Returns array of allowed hours for a given day-of-week (0=Sun…6=Sat)
    function getAllowedSlots(dayOfWeek) {
        const slots = [];
        if (dayOfWeek === 0 || dayOfWeek === 6) return slots; // Weekend

        if (dayOfWeek === 5) {
            // Friday: hourly 8:00–11:00 (last before the 12:00 cutoff)
            //         and 15:00–16:00 (last before the 17:00 cutoff)
            for (let h = 8; h < 12; h++) {
                slots.push({ hour: h, min: 0 });
            }
            for (let h = 15; h < 17; h++) {
                slots.push({ hour: h, min: 0 });
            }
        } else {
            // Mon–Thu: hourly 8:00–16:00 (last before the 17:00 cutoff)
            for (let h = 8; h < 17; h++) {
                slots.push({ hour: h, min: 0 });
            }
        }
        return slots;
    }

    // Format 24h → 12h display
    function formatTime(h, m) {
        const suffix = h >= 12 ? 'PM' : 'AM';
        const h12    = h % 12 || 12;
        return `${h12}:${String(m).padStart(2, '0')} ${suffix}`;
    }

    // Format hour:min → "HH:MM" for comparison with booked slots
    function toHHMM(h, m) {
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    }

    // ── When user picks a date ──
    datePicker.addEventListener('change', async function () {
        selectedSlot = null;
        hiddenInput.value = '';
        bookBtn.disabled  = true;

        const dateVal = this.value; // "YYYY-MM-DD"
        if (!dateVal) {
            slotPicker.style.display = 'none';
            return;
        }

        // Check weekend on client side
        const parts   = dateVal.split('-');
        const dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        const dow     = dateObj.getDay();

        if (dow === 0 || dow === 6) {
            slotPicker.style.display = 'block';
            slotContainer.innerHTML =
                '<div class="slot-empty">The health unit is closed on weekends. Please choose a weekday.</div>';
            return;
        }

        // Show loading
        slotPicker.style.display = 'block';
        slotContainer.innerHTML = '<div class="slot-loading">Loading available slots…</div>';

        // Fetch booked times from server
        let bookedSlots = [];
        try {
            const res  = await fetch(`get_booked_slots.php?date=${dateVal}`);
            bookedSlots = await res.json();
        } catch (err) {
            console.error('Failed to fetch booked slots:', err);
        }

        const allowedSlots = getAllowedSlots(dow);
        if (allowedSlots.length === 0) {
            slotContainer.innerHTML =
                '<div class="slot-empty">No available slots for this date.</div>';
            return;
        }

        // Current time for "past" check (only matters if selected date is today)
        const now        = new Date();
        const isToday    = (dateVal === `${yyyy}-${mm}-${dd}`);

        // Group into morning / afternoon
        let morningHTML   = '';
        let afternoonHTML = '';
        let hasMorning    = false;
        let hasAfternoon  = false;

        allowedSlots.forEach(slot => {
            const hhmm    = toHHMM(slot.hour, slot.min);
            const display = formatTime(slot.hour, slot.min);
            const isTaken = bookedSlots.includes(hhmm);

            // Check if this slot is in the past (for today)
            let isPast = false;
            if (isToday) {
                const slotDate = new Date(dateObj);
                slotDate.setHours(slot.hour, slot.min, 0, 0);
                if (slotDate <= now) isPast = true;
            }

            let classes = 'slot-btn';
            let disabled = '';
            let title = '';
            if (isTaken) {
                classes += ' slot-taken';
                disabled = 'disabled';
                title = 'title="Already booked"';
            } else if (isPast) {
                classes += ' slot-past';
                disabled = 'disabled';
                title = 'title="Time has passed"';
            }

            const btnHTML = `<button type="button" class="${classes}" ${disabled} ${title}
                data-time="${hhmm}" data-date="${dateVal}">${display}</button>`;

            if (slot.hour < 12) {
                hasMorning = true;
                morningHTML += btnHTML;
            } else {
                hasAfternoon = true;
                afternoonHTML += btnHTML;
            }
        });

        let html = '';
        if (hasMorning) {
            html += `<div class="slot-period-label">Morning</div>
                     <div class="slots-grid">${morningHTML}</div>`;
        }
        if (hasAfternoon) {
            html += `<div class="slot-period-label">Afternoon</div>
                     <div class="slots-grid">${afternoonHTML}</div>`;
        }

        slotContainer.innerHTML = html;

        // Attach click handlers to available slot buttons
        slotContainer.querySelectorAll('.slot-btn:not(.slot-taken):not(.slot-past)').forEach(btn => {
            btn.addEventListener('click', function () {
                // Deselect previous
                slotContainer.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('slot-selected'));
                // Select this one
                this.classList.add('slot-selected');
                selectedSlot = this.dataset.time;
                hiddenInput.value = `${this.dataset.date}T${selectedSlot}`;
                bookBtn.disabled  = false;
            });
        });
    });

    // ── Form submission guard ──
    document.getElementById('booking-form').addEventListener('submit', function (e) {
        if (!hiddenInput.value) {
            e.preventDefault();
            alert('Please select both a date and a time slot.');
            return;
        }
        bookBtn.disabled = true;
        bookBtn.style.opacity = '0.7';
    });
})();
</script>

</body>
</html>
