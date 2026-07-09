<?php
session_start();
require 'db.php';

$message = "";
$message_class = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_id']   = $row['id'];
                $_SESSION['admin_name'] = $row['fullname'];
                header("Location: admin_dashboard.php");
                exit;
            } else {
                $message       = "Incorrect password. Please try again.";
                $message_class = "error";
            }
        } else {
            $message       = "Admin account not found.";
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
    <title>Admin Login — UiTM Health Unit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0f0f14;
            --surface:   #1a1a24;
            --border:    rgba(255,255,255,0.08);
            --accent:    #a855f7;
            --accent-glow: rgba(168,85,247,0.35);
            --text:      #f0f0f5;
            --muted:     #8888a0;
            --red:       #ff4d4d;
            --green:     #4dff91;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        /* subtle grid background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(168,85,247,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(168,85,247,0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .wrap {
            width: 100%;
            max-width: 420px;
            position: relative;
            animation: rise 0.4s cubic-bezier(.22,.68,0,1.2);
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* glow orb */
        .wrap::before {
            content: '';
            position: absolute;
            top: -60px; left: 50%;
            transform: translateX(-50%);
            width: 200px; height: 200px;
            background: var(--accent-glow);
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
            z-index: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
            z-index: 1;
        }

        .icon-ring {
            width: 68px; height: 68px;
            border-radius: 50%;
            border: 1.5px solid var(--accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 18px;
            box-shadow: 0 0 24px var(--accent-glow), inset 0 0 12px rgba(168,85,247,0.1);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .header p { font-size: 14px; color: var(--muted); }

        .badge {
            display: inline-block;
            margin-top: 10px;
            background: rgba(168,85,247,0.12);
            border: 1px solid rgba(168,85,247,0.3);
            color: var(--accent);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 4px 14px;
            border-radius: 20px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 30px 28px 26px;
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 22px;
            line-height: 1.5;
            animation: rise 0.25s ease;
        }
        .alert-dot {
            width: 18px; height: 18px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; flex-shrink: 0; margin-top: 1px;
        }
        .alert.error {
            background: rgba(255,77,77,0.10);
            border: 1px solid rgba(255,77,77,0.2);
            color: #ff9999;
        }
        .alert.error .alert-dot { background: var(--red); color: #fff; }

        .field { margin-bottom: 18px; }

        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 13px 16px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            color: var(--text);
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        input::placeholder { color: rgba(255,255,255,0.2); }
        input:focus {
            border-color: var(--accent);
            background: rgba(168,85,247,0.06);
            box-shadow: 0 0 0 3px rgba(168,85,247,0.15);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.2px;
            cursor: pointer;
            margin-top: 4px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 0 20px var(--accent-glow);
        }
        .btn:hover  { opacity: 0.9; box-shadow: 0 0 28px var(--accent-glow); }
        .btn:active { transform: scale(0.98); }

        .footer {
            text-align: center;
            font-size: 13px;
            color: var(--muted);
            position: relative;
            z-index: 1;
        }
        .footer a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="wrap">
    <div class="header">
        <div class="icon-ring">🔐</div>
        <h1>UiTM Health Unit</h1>
        <p>Administration Portal</p>
        <span class="badge">Admin Access Only</span>
    </div>

    <div class="card">
        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_class ?>">
                <div class="alert-dot">✕</div>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <form action="admin_login.php" method="POST">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       placeholder="Enter admin username" required
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>

    <div class="footer">
        Not an admin? <a href="staff_login.php">Staff Login</a> · <a href="login.php">Student Login</a>
    </div>
</div>

</body>
</html>
