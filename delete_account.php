<?php

session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?status=error&message=" . urlencode("Please login first."));
    exit;
}

try {

    $pdo = getConnection();

    $userId = (int) $_SESSION['user_id'];

    // Delete user (rentals auto-delete via CASCADE)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);

    // Destroy session
    $_SESSION = [];
    session_destroy();

    header("Location: index.php?status=success&message=" . urlencode("Your account has been deleted."));
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header("Location: index.php?status=error&message=" . urlencode("Unable to delete account."));
    exit;
}
?>