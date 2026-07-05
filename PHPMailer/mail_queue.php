 <?php
/**
 * mail_queue.php — Process pending emails via CLI (bypasses Apache firewall issues)
 * 
 * Run this every minute via Task Scheduler or manually:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\uhap\mail_queue.php
 * 
 * It picks up unsent emails from the queue and sends them one by one.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

// CLI mode — connect to local database directly
$conn = new mysqli('127.0.0.1', 'root', '12345', 'dbstudentsphg', 3306);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error . "\n");
}

// Process up to 5 emails per run
$stmt = $conn->prepare(
    "SELECT * FROM email_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5"
);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit(0);
}

while ($row = $result->fetch_assoc()) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug   = 0;
        $mail->Host        = 'smtp.gmail.com';
        $mail->SMTPAuth    = true;
        $mail->Username    = 'aqilfaris20062@gmail.com';
        $mail->Password    = 'aufcuiagnyyutnme';
        $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = 587;
        $mail->Timeout     = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->setFrom('aqilfaris20062@gmail.com', 'UiTM Health Unit');
        $mail->addAddress($row['recipient_email'], $row['recipient_name']);
        $mail->isHTML(true);
        $mail->Subject = $row['subject'];
        $mail->Body    = $row['body'];
        $mail->send();

        // Mark as sent
        $upd = $conn->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $upd->bind_param("i", $row['id']);
        $upd->execute();

        echo "[OK] Sent to {$row['recipient_email']} (ID: {$row['id']})\n";

    } catch (Exception $e) {
        echo "[FAIL] {$row['recipient_email']}: " . $mail->ErrorInfo . "\n";
        // Increment retry count
        $retry = (int)$row['retries'] + 1;
        $status = $retry >= 3 ? 'failed' : 'pending';
        $upd = $conn->prepare("UPDATE email_queue SET retries = ?, status = ? WHERE id = ?");
        $upd->bind_param("isi", $retry, $status, $row['id']);
        $upd->execute();
    }
}