<?php
/**
 * reminder.php — UiTM Health Unit Appointment Reminder Script
 * ─────────────────────────────────────────────────────────────
 * PURPOSE:
 *   Runs on a schedule (every 1 minute via cron / Windows Task Scheduler).
 *   Finds all appointments whose time is between NOW+4min and NOW+6min
 *   and whose reminder_sent = 0, then sends a reminder email to each student
 *   and marks reminder_sent = 1 so the email is never sent twice.
 *
 * HOW TO SCHEDULE (see cron_setup.md for full instructions):
 *   Linux/Mac cron:   * * * * * php /path/to/your/project/reminder.php
 *   Windows Task:     Run php.exe reminder.php every 1 minute
 */

// ─── Load PHPMailer ───────────────────────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer/Exception.php';
require __DIR__ . '/../PHPMailer/PHPMailer.php';
require __DIR__ . '/../PHPMailer/SMTP.php';

// ─── Database connection ──────────────────────────────────────────────────────
require __DIR__ . '/../config/db.php';   // provides $conn (mysqli)

// ─── Configuration ───────────────────────────────────────────────────────────
define('SMTP_USER',     'aqilfaris20062@gmail.com');  // ← your Gmail address
define('SMTP_PASSWORD', 'emhydeahurvmvoii');           // ← your Gmail App Password
define('REMINDER_WINDOW_MIN', 4);   // send when appointment is between 4 …
define('REMINDER_WINDOW_MAX', 6);   // … and 6 minutes away (catches the 5-min mark)
define('EMAIL_FROM',    'noreply@uitm.edu.my');
define('EMAIL_FROM_NAME', 'UiTM Health Unit');

// ─── Find appointments that need a reminder ───────────────────────────────────
// We join with the students table to get the student name and email
$sql = "
    SELECT
        a.id,
        a.studentno,
        a.appointment_datetime,
        s.studentname,
        s.studentemailuitm AS studentemail
    FROM appointments a
    JOIN students s ON s.studentno = a.studentno
    WHERE
        a.status        = 'Scheduled'
        AND a.reminder_sent = 0
        AND a.appointment_datetime BETWEEN
            DATE_ADD(NOW(), INTERVAL " . REMINDER_WINDOW_MIN . " MINUTE)
            AND
            DATE_ADD(NOW(), INTERVAL " . REMINDER_WINDOW_MAX . " MINUTE)
";

$result = $conn->query($sql);

if (!$result) {
    echo "[ERROR] Query failed: " . $conn->error . "\n";
    exit(1);
}

if ($result->num_rows === 0) {
    echo "[INFO] " . date('Y-m-d H:i:s') . " — No reminders to send.\n";
    exit(0);
}

// ─── Loop through each appointment and send reminder ─────────────────────────
while ($row = $result->fetch_assoc()) {

    $appt_id       = $row['id'];
    $studentno     = $row['studentno'];
    $studentname   = $row['studentname'];
    $studentemail  = $row['studentemail'];
    $appt_datetime = $row['appointment_datetime'];
    $formatted_time = date('F j, Y, g:i a', strtotime($appt_datetime));

    // ── Build the reminder email HTML body ───────────────────────────────────
    $email_body = "
        <div style='font-family: Arial, sans-serif; padding: 25px; border: 2px solid #330066;
                    border-radius: 12px; max-width: 500px; margin: 20px auto;
                    background-color: #fdfbfe; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>

            <h2 style='color: #330066; text-align: center; margin-top: 0;
                       font-size: 24px; letter-spacing: 1px;'>UiTM Health Unit</h2>
            <h4 style='color: #666; text-align: center; margin-bottom: 20px;
                       text-transform: uppercase; font-size: 11px;
                       border-bottom: 1px solid #ddd; padding-bottom: 10px;
                       letter-spacing: 2px;'>Appointment Reminder</h4>

            <p style='color: #333; margin: 8px 0;'>Dear <strong>{$studentname}</strong>,</p>
            <p style='color: #555; margin: 8px 0;'>
                This is a friendly reminder that your appointment at the
                <strong>UiTM Health Unit</strong> is scheduled to begin in
                <strong>approximately 5 minutes</strong>.
            </p>

            <hr style='border: 0; border-top: 1px solid #eee; margin: 15px 0;'>

            <p style='margin: 8px 0;'><strong>Student ID:</strong>
                <span style='color: #333;'>{$studentno}</span></p>

            <p style='margin-bottom: 5px; font-weight: bold; color: #330066;'>Your Appointment Time:</p>
            <div style='background-color: #330066; color: white; padding: 15px;
                        text-align: center; border-radius: 6px; font-weight: bold;
                        font-size: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                {$formatted_time}
            </div>

            <p style='color: #555; font-size: 13px; margin-top: 20px;
                      text-align: center;'>
                Please proceed to the health unit reception counter now.
            </p>
            <p style='color: #888; font-size: 12px; margin-top: 10px;
                      text-align: center; font-style: italic;'>
                If you are unable to attend, please notify the health unit as soon as possible.
            </p>
        </div>
    ";

    // ── Send the reminder email via PHPMailer ────────────────────────────────
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug  = 0;
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($studentemail, $studentname);
        $mail->isHTML(true);
        $mail->Subject = 'UiTM Health Unit  Appointment Reminder';
        $mail->Body    = $email_body;
        $mail->send();

        // ── Mark reminder as sent so it is NEVER sent again ──────────────────
        $update = $conn->prepare(
            "UPDATE appointments SET reminder_sent = 1 WHERE id = ?"
        );
        $update->bind_param("i", $appt_id);
        $update->execute();

        echo "[OK]    " . date('Y-m-d H:i:s') . " — Reminder sent to {$studentemail} (Appt #{$appt_id} at {$formatted_time})\n";

    } catch (Exception $e) {
        echo "[FAIL]  " . date('Y-m-d H:i:s') . " — Could not send to {$studentemail}: " . $mail->ErrorInfo . "\n";
    }
}

echo "[DONE]  " . date('Y-m-d H:i:s') . " — Reminder cycle complete.\n";