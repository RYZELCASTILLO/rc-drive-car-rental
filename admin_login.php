<?php

session_start();
require_once 'config.php';

/* If already logged in as admin, go straight to dashboard */
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header("Location: dashboard.php");
    exit;
}

/* If a customer is logged in, DO NOT log them out.
   Just show the admin form. They can log in here as admin;
   their customer session will be replaced on success. */

$error = '';
$flash = '';

if (isset($_GET['status'], $_GET['message'])) {
    if ($_GET['status'] === 'error') {
        $error = $_GET['message'];
    } else {
        $flash = $_GET['message'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Login - RC Drive</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #0b0c0e;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            background-image:
                radial-gradient(circle at 20% 20%, rgba(252, 193, 19, 0.06), transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(220, 53, 69, 0.06), transparent 40%);
        }

        .admin-login-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .admin-brand {
            text-align: center;
            margin-bottom: 30px;
        }

        .admin-brand img {
            height: 60px;
            width: auto;
            margin-bottom: 18px;
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid #dc3545;
            color: #ff5560;
            border-radius: 4px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .admin-card {
            background: #161a20;
            border: 1px solid #222730;
            border-top: 4px solid #dc3545;
            border-radius: 6px;
            padding: 40px 36px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
        }

        .admin-card h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .admin-card .subtitle {
            font-size: 0.9rem;
            color: #8a8f9d;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #a0a5b1;
            margin-bottom: 8px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 0.95rem;
            pointer-events: none;
        }

        .form-group input {
            width: 100%;
            background: #0b0c0e;
            border: 1px solid #2a2f38;
            border-radius: 4px;
            color: #ffffff;
            padding: 14px 16px 14px 44px;
            font-size: 0.95rem;
            outline: none;
            transition: 0.25s;
        }

        .form-group input:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15);
        }

        .form-group input::placeholder {
            color: #6c757d;
        }

        .login-btn {
            width: 100%;
            background: #dc3545;
            color: #ffffff;
            border: 2px solid #dc3545;
            border-radius: 4px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            font-weight: 900;
            letter-spacing: 1px;
            padding: 16px;
            cursor: pointer;
            transition: 0.25s;
            margin-top: 8px;
        }

        .login-btn:hover {
            background: transparent;
            color: #dc3545;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
            color: #ff8590;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
            color: #7eec9a;
        }

        .admin-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .admin-footer a {
            color: #FCC113;
            text-decoration: none;
            font-weight: 700;
            transition: 0.2s;
        }

        .admin-footer a:hover {
            color: #ffffff;
        }

        .security-note {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #222730;
            font-size: 0.75rem;
            color: #6c757d;
        }

        .security-note i {
            color: #dc3545;
        }

    </style>

</head>

<body>

<div class="admin-login-wrapper">

    <div class="admin-brand">

        <img src="images/Asset 1.png" alt="RC Drive Logo">

        <div>
            <span class="admin-badge">
                <i class="fa-solid fa-shield-halved"></i>
                Administrator Access
            </span>
        </div>

    </div>

    <div class="admin-card">

        <h1>Admin Sign In</h1>

        <p class="subtitle">
            Authorized personnel only. All access is logged.
        </p>

        <?php if ($error): ?>

            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($flash): ?>

            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($flash) ?>
            </div>

        <?php endif; ?>

        <form action="admin_login_function.php" method="POST" autocomplete="off">

            <div class="form-group">

                <label for="adminUsername">
                    Admin Username
                </label>

                <div class="input-wrap">
                    <i class="fa-solid fa-user-shield"></i>
                    <input
                        type="text"
                        id="adminUsername"
                        name="username"
                        placeholder="Enter admin username"
                        required
                        autofocus
                    >
                </div>

            </div>

            <div class="form-group">

                <label for="adminPassword">
                    Password
                </label>

                <div class="input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input
                        type="password"
                        id="adminPassword"
                        name="password"
                        placeholder="Enter admin password"
                        required
                    >
                </div>

            </div>

            <button type="submit" class="login-btn">
                <i class="fa-solid fa-right-to-bracket"></i>
                ACCESS ADMIN DASHBOARD
            </button>

        </form>

        <div class="security-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Unauthorized access attempts are recorded.</span>
        </div>

    </div>

    <div class="admin-footer">
        Not an administrator?
        <a href="index.php">Return to RC Drive homepage</a>
    </div>

</div>

</body>

</html>