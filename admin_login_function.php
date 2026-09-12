<?php

session_start();
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header(
        "Location: admin_login.php?status=error&message=" .
        urlencode("Please enter both username and password.")
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

    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header(
            "Location: admin_login.php?status=error&message=" .
            urlencode("Invalid administrator credentials.")
        );
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        header(
            "Location: admin_login.php?status=error&message=" .
            urlencode("Invalid administrator credentials.")
        );
        exit;
    }

    /* Reject non-admin accounts */
    if (($user['role'] ?? '') !== 'admin') {
        header(
            "Location: admin_login.php?status=error&message=" .
            urlencode("This account does not have administrator access.")
        );
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'] ?? '';
    $_SESSION['role']     = 'admin';

    header("Location: dashboard.php");
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header(
        "Location: admin_login.php?status=error&message=" .
        urlencode("Login failed. Please try again.")
    );
    exit;
}
?>