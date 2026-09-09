<?php

session_start();
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

/* =========================
   VALIDATION
========================= */

if ($username === '' || $password === '') {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please enter your username and password.")
    );
    exit;
}

try {

    $pdo = getConnection();

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    | Your project has used both "user" and "users".
    | We first try the current "users" table.
    */

    $user = null;

    try {

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

    } catch (PDOException $e) {

        /*
        |--------------------------------------------------------------------------
        | FALLBACK TO "user" TABLE
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id, username, email, password, role
            FROM user
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /* =========================
       CHECK USER
    ========================= */

    if (!$user) {

        header(
            "Location: index.php?status=error&message=" .
            urlencode("Invalid username or password.")
        );

        exit;
    }


    /* =========================
       CHECK PASSWORD
    ========================= */

    if (!password_verify($password, $user['password'])) {

        header(
            "Location: index.php?status=error&message=" .
            urlencode("Invalid username or password.")
        );

        exit;
    }


    /* =========================
       CREATE SESSION
    ========================= */

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['role'] = $user['role'] ?? 'customer';


    /* =========================
       SUCCESS
    ========================= */

    header(
        "Location: index.php?status=success&message=" .
        urlencode("Login successful! Welcome, " . $user['username'] . ".")
    );

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