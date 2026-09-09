<?php

session_start();
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");
    exit;
}


$name =
    trim($_POST['name'] ?? '');

$email =
    trim($_POST['email'] ?? '');

$message =
    trim($_POST['message'] ?? '');


/* =========================
   VALIDATION
========================= */

if (
    $name === '' ||
    $email === '' ||
    $message === ''
) {

    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please complete all contact fields.")
    );

    exit;
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please enter a valid email address.")
    );

    exit;
}


if (strlen($message) < 5) {

    header(
        "Location: index.php?status=error&message=" .
        urlencode("Message is too short.")
    );

    exit;
}


try {

    $pdo = getConnection();


    $stmt = $pdo->prepare("
        INSERT INTO inquiries
        (
            name,
            email,
            message
        )
        VALUES
        (
            :name,
            :email,
            :message
        )
    ");


    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':message' => $message
    ]);


    header(
        "Location: index.php?status=success&message=" .
        urlencode("Your message has been sent successfully.")
    );

    exit;


} catch (PDOException $e) {

    error_log($e->getMessage());

    header(
        "Location: index.php?status=error&message=" .
        urlencode("Unable to send your message.")
    );

    exit;
}