<?php
/**
 * google_auth.php — Google Sign-In via OAuth2 redirect flow
 *
 * SETUP: Fill in your Client ID, Client Secret, and folder name below.
 */

session_start();
require 'db.php'; 

// 2. Pure PHP environment loader (Safe, lightweight, no JavaScript needed)
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments or lines without an equals sign
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// 3. Map your credentials from the .env file
$client_id     = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
$client_secret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
$redirect_uri  = 'http://localhost/uhap/config/google_auth.php'; 

// Safety gate check
if (empty($client_id) || empty($client_secret)) {
    die("Configuration Error: Missing Google API Client Keys in your .env file.");
}

// ── STEP 1: No code yet → build Google OAuth URL and redirect ─────────────────
if (!isset($_GET['code'])) {
    $params = http_build_query([
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
        'hd'            => 'student.uitm.edu.my',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// ── STEP 2: Google sent back a code → exchange it for an access token ─────────
$token_response = file_get_contents('https://oauth2.googleapis.com/token', false,
    stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]),
        'timeout' => 10,
    ]])
);

if ($token_response === false) {
    $error = urlencode("Could not connect to Google. Please try again.");
    header("Location: ../auth/login.php?google_error=$error");
    exit;
}

$token_data   = json_decode($token_response, true);
$access_token = $token_data['access_token'] ?? '';

if (empty($access_token)) {
    $error = urlencode("Google Sign-In failed. Please try again.");
    header("Location: ../auth/login.php?google_error=$error");
    exit;
}

// ── STEP 3: Use the access token to get the user's email ──────────────────────
$user_response = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false,
    stream_context_create(['http' => [
        'header'  => 'Authorization: Bearer ' . $access_token,
        'timeout' => 10,
    ]])
);

if ($user_response === false) {
    $error = urlencode("Could not retrieve your Google profile. Please try again.");
    header("Location: ../auth/login.php?google_error=$error");
    exit;
}

$user_info      = json_decode($user_response, true);
$google_email   = $user_info['email']          ?? '';
$email_verified = $user_info['verified_email'] ?? false;

// ── STEP 4: Validate the email ────────────────────────────────────────────────
if (empty($google_email) || !$email_verified) {
    $error = urlencode("Your Google email could not be verified.");
    header("Location: ../auth/login.php?google_error=$error");
    exit;
}

// Only allow UiTM student emails
if (!str_ends_with($google_email, '@student.uitm.edu.my')) {
    $error = urlencode("Please sign in with your UiTM student email (@student.uitm.edu.my). You used: $google_email");
    header("Location: ../auth/login.php?google_error=$error");
    exit;
}

// ── STEP 5: Check if student exists in the database ───────────────────────────
$stmt = $conn->prepare("SELECT * FROM students WHERE studentemailuitm = ?");
$stmt->bind_param("s", $google_email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // ✅ Found — log them in
    $_SESSION['studentno']    = $row['studentno'];
    $_SESSION['studentname']  = $row['studentname'];
    $_SESSION['studentemail'] = $row['studentemailuitm'];
    header("Location: ../student/dashboard.php");
    exit;
} else {
    // ❌ Email not in UiTM student database
    $error = urlencode("The Google account ($google_email) was not found in the UiTM student database.");
    header("Location: ../auth/login.php?google_error=$error");
    exit;
}
?>
