<?php

session_start();
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

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
        (user_id, name, email, message)
        VALUES
        (:user_id, :name, :email, :message)
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':name'    => $name,
        ':email'   => $email,
        ':message' => $message
    ]);

    $inquiryId = (int) $pdo->lastInsertId();

    // Insert as first message in the conversation thread
    $msgStmt = $pdo->prepare("
        INSERT INTO inquiry_messages
        (inquiry_id, sender_role, sender_name, message)
        VALUES
        (:inquiry_id, 'customer', :sender_name, :message)
    ");

    $msgStmt->execute([
        ':inquiry_id'  => $inquiryId,
        ':sender_name' => $name,
        ':message'     => $message
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
?>