<?php

session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?status=error&message=" . urlencode("Please login first."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$inquiryId = filter_input(INPUT_POST, 'inquiry_id', FILTER_VALIDATE_INT);
$message   = trim($_POST['message'] ?? '');

if (!$inquiryId || $message === '') {
    header("Location: dashboard.php?status=error&message=" . urlencode("Message cannot be empty."));
    exit;
}

if (mb_strlen($message) > MAX_CHAT_MESSAGE_LENGTH) {
    header("Location: dashboard.php?status=error&message=" .
           urlencode("Message is too long. Max " . MAX_CHAT_MESSAGE_LENGTH . " characters."));
    exit;
}

$role     = $_SESSION['role'] ?? 'customer';
$userId   = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, user_id, name, email
        FROM inquiries
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $inquiryId]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inquiry) {
        header("Location: dashboard.php?status=error&message=" . urlencode("Inquiry not found."));
        exit;
    }

    if ($role !== 'admin') {
        if ((int)($inquiry['user_id'] ?? 0) !== $userId) {
            header("Location: dashboard.php?status=error&message=" . urlencode("You can only reply to your own inquiries."));
            exit;
        }
    }

    $insert = $pdo->prepare("
        INSERT INTO inquiry_messages
        (inquiry_id, sender_role, sender_name, message)
        VALUES
        (:inquiry_id, :sender_role, :sender_name, :message)
    ");

    $insert->execute([
        ':inquiry_id'  => $inquiryId,
        ':sender_role' => $role === 'admin' ? 'admin' : 'customer',
        ':sender_name' => $username,
        ':message'     => $message
    ]);

    if ($role === 'admin') {
        $update = $pdo->prepare("
            UPDATE inquiries
            SET admin_reply = :reply,
                replied_at  = NOW()
            WHERE id = :id
        ");
        $update->execute([
            ':reply' => $message,
            ':id'    => $inquiryId
        ]);
    }

    header("Location: dashboard.php?status=success&message=" . urlencode("Reply sent.") . "#inquiry-" . $inquiryId);
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header("Location: dashboard.php?status=error&message=" . urlencode("Unable to send reply."));
    exit;
}