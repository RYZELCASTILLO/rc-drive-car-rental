<?php

session_start();
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please enter your username and password.")
    );
    exit;
}

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, username, email, password, role
        FROM users
        WHERE username = :username
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $username
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header(
            "Location: index.php?status=error&message=" .
            urlencode("Invalid username or password.")
        );
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        header(
            "Location: index.php?status=error&message=" .
            urlencode("Invalid username or password.")
        );
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'] ?? '';
    $_SESSION['role']     = $user['role'] ?? 'customer';

    /* =========================
       ROLE-BASED REDIRECT
       -------------------------
       Admin    → dashboard.php
       Customer → index.php (homepage)
    ========================= */

    if (($_SESSION['role'] ?? '') === 'admin') {
        header("Location: dashboard.php");
    } else {
        header(
            "Location: index.php?status=success&message=" .
            urlencode("Welcome back, " . $user['username'] . "!")
        );
    }
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header(
        "Location: index.php?status=error&message=" .
        urlencode("Login failed. Please try again.")
    );
    exit;
}
?>