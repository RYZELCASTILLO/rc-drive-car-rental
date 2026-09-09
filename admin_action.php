<?php

session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php?status=error&message=" . urlencode("Admin access required."));
    exit;
}

$action = $_GET['action'] ?? '';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    header("Location: index.php?status=error&message=" . urlencode("Invalid request."));
    exit;
}

$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare("UPDATE rentals SET status = :status WHERE id = :id");
    $stmt->execute([
        ':status' => $newStatus,
        ':id'     => $id
    ]);

    header("Location: index.php?status=success&message=" . urlencode("Booking $newStatus."));
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: index.php?status=error&message=" . urlencode("Unable to update booking."));
    exit;
}