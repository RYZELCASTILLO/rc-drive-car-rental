<?php

session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?status=error&message=" . urlencode("Please login first."));
    exit;
}

$role = $_SESSION['role'] ?? 'customer';
$uid  = (int) $_SESSION['user_id'];
$id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {

    $pdo = getConnection();

    if ($id) {
        /* Mark a single notification as read */
        if ($role === 'admin') {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = :id
                  AND recipient_role = 'admin'
            ");
            $stmt->execute([':id' => $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = :id
                  AND recipient_role = 'customer'
                  AND recipient_id = :uid
            ");
            $stmt->execute([':id' => $id, ':uid' => $uid]);
        }
    } else {
        /* Mark ALL notifications for this user as read */
        if ($role === 'admin') {
            $pdo->exec("
                UPDATE notifications
                SET is_read = 1
                WHERE recipient_role = 'admin'
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE recipient_role = 'customer'
                  AND recipient_id = :uid
            ");
            $stmt->execute([':uid' => $uid]);
        }
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
}

header("Location: dashboard.php");
exit;