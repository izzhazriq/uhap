<?php
session_start();
require 'db.php';

$message = "";
$message_class = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM staff WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($password === $row['password']) {
                $_SESSION['staff_id']   = $row['id'];
                $_SESSION['staff_name'] = $row['fullname'];
                header("Location: staff_dashboard.php");
                exit;
            } else {
                $message       = "Incorrect password. Please try again.";
                $message_class = "error";
            }
        } else {
            $message       = "Staff account not found.";
            $message_class = "error";
        }
    } else {
        $message       = "Please fill in all fields.";
        $message_class = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — UiTM Health Unit</title>
    <meta name="description" content="Staff login for the UiTM Health Unit appointment management system.">
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        .login-wrap {
            width: 100%;
            max-width: 400px;
            animation: fadeUp 0.35s ease;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .app-icon {
            width: 64px; height: 64px;
            background: var(--label-primary);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px rgba(28,28,30,0.18);
        }

        .login-header h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.4px;
            color: var(--label-primary);
            margin-bottom: 4px;
        }

        .login-header p {
            font-size: 14px;
            color: var(--label-secondary);
        }

        /* Staff badge */
        .role-badge {
            display: inline-block;
            background: var(--fill-1);
            color: var(--label-secondary);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
            margin-top: 10px;
        }

        .card {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 28px 28px 24px;
            margin-bottom: 16px;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            line-height: 1.5;
            animation: fadeUp 0.25s ease;
        }
        .alert-icon {
            width: 18px; height: 18px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; flex-shrink: 0; margin-top: 1px;
        }
        .alert.success { background: rgba(52,199,89,0.10); color: #1a7a2e; }
        .alert.success .alert-icon { background: var(--green); color: #fff; }
        .alert.error   { background: rgba(255,59,48,0.08);  color: #c0251b; }
        .alert.error .alert-icon   { background: var(--red);   color: #fff; }

        .field-group { margin-bottom: 16px; }

        .field-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--label-secondary);
            margin-bottom: 7px;
        }

        .field-input {
            width: 100%;
            padding: 12px 14px;
            background: var(--fill-1);
            border: 1.5px solid transparent;
            border-radius: 12px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 15px;
            color: var(--label-primary);
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .field-input::placeholder { color: var(--label-tertiary); }
        .field-input:focus {
            border-color: var(--blue);
            background: rgba(0,122,255,0.05);
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: var(--label-primary);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            margin-top: 6px;
            letter-spacing: -0.1px;
        }
        .btn-primary:hover  { background: #2c2c2e; }
        .btn-primary:active { transform: scale(0.98); }

        .footer-link {
            text-align: center;
            font-size: 13px;
            color: var(--label-secondary);
        }
        .footer-link a {
            color: var(--blue);
            font-weight: 600;
            text-decoration: none;
        }
        .footer-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="login-header">
        <div class="app-icon">🏥</div>
        <h1>UiTM Health Unit</h1>
        <p>Appointment Management System</p>
        <span class="role-badge">Staff Access</span>
    </div>

    <div class="card">
        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_class ?>" role="alert">
                <div class="alert-icon"><?= $message_class === 'success' ? '✓' : '✕' ?></div>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <form action="staff_login.php" method="POST">
            <div class="field-group">
                <label class="field-label" for="username">Username</label>
                <input class="field-input" type="text" id="username" name="username"
                       placeholder="Enter staff username" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="password">Password</label>
                <input class="field-input" type="password" id="password" name="password"
                       placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn-primary" id="staff-login-btn">Sign In</button>
        </form>
    </div>

    <div class="footer-link">
        Not staff? <a href="login.php">← Student Login</a>
    </div>
</div>

</body>
</html>
