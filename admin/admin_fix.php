<?php
require '../config/db.php';

// ── Step 1: Check if admin table exists and has a row ─────────────────────────
$result = $conn->query("SELECT id, username, password, fullname FROM admin");

if (!$result) {
    echo "<p style='color:red'><b>ERROR:</b> admin table does not exist or query failed: " . $conn->error . "</p>";
    exit;
}

echo "<h3>Rows in admin table:</h3>";
if ($result->num_rows === 0) {
    echo "<p style='color:red'>No rows found in admin table. The INSERT never ran.</p>";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "<pre>";
        echo "ID       : " . $row['id'] . "\n";
        echo "Username : " . $row['username'] . "\n";
        echo "Fullname : " . $row['fullname'] . "\n";
        echo "Password : " . $row['password'] . "\n";
        echo "</pre>";
    }
}

// ── Step 2: Generate a fresh hash and update it right now ─────────────────────
$new_password = "Admin1234";
$new_hash     = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE admin SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $new_hash);

if ($stmt->execute()) {
    echo "<p style='color:green'><b>SUCCESS:</b> Password has been reset to: <b>{$new_password}</b></p>";
    echo "<p>New hash stored: <code>{$new_hash}</code></p>";
} else {
    echo "<p style='color:red'><b>UPDATE FAILED:</b> " . $conn->error . "</p>";
}

// ── Step 3: Verify the hash works ─────────────────────────────────────────────
$verify_result = $conn->query("SELECT password FROM admin WHERE username = 'admin'");
$verify_row    = $verify_result->fetch_assoc();

if ($verify_row) {
    $check = password_verify($new_password, $verify_row['password']);
    echo "<p>Hash verification test: <b>" . ($check ? "✅ PASS — login will work now" : "❌ FAIL") . "</b></p>";
} else {
    echo "<p style='color:red'>Could not re-fetch the admin row after update.</p>";
}

echo "<br><a href='../auth/admin_login.php'>→ Go to Admin Login</a>";
?>
