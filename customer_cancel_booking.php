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

$userId = (int) $_SESSION['user_id'];
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$allowedReasons = [
    'Change of date',
    'Change of plans',
    'No longer needed',
    'Found another vehicle',
    'Emergency',
    'Other',
];

$reason      = trim($_POST['reason'] ?? '');
$reasonOther = trim($_POST['reason_other'] ?? '');

if (!$id || !in_array($reason, $allowedReasons, true)) {
    header("Location: dashboard.php?status=error&message=" . urlencode("Please select a valid reason."));
    exit;
}

if ($reason === 'Other' && $reasonOther === '') {
    header("Location: dashboard.php?status=error&message=" . urlencode("Please specify your reason."));
    exit;
}

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, user_id, status, created_at, rental_date
        FROM rentals
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking || (int)$booking['user_id'] !== $userId) {
        header("Location: dashboard.php?status=error&message=" . urlencode("Booking not found."));
        exit;
    }

    $status = $booking['status'] ?? 'Pending';

    /* RULE 1 — Only Pending bookings can be self-cancelled */
    if ($status !== 'Pending') {
        header(
            "Location: dashboard.php?status=error&message=" .
            urlencode("This booking can no longer be self-cancelled because it has already been processed. Please chat with us if you still need to cancel.")
        );
        exit;
    }

    /* RULE 2 — Only within 2 hours of booking */
    $createdTs  = strtotime($booking['created_at']);
    $hoursSince = (time() - $createdTs) / 3600;

    if ($hoursSince > 2) {
        header(
            "Location: dashboard.php?status=error&message=" .
            urlencode("The 2-hour self-cancellation window has passed. Please chat with us and an admin will assist you.")
        );
        exit;
    }

    $upd = $pdo->prepare("
        UPDATE rentals
        SET status                = 'Cancel Requested',
            cancel_requested_at   = NOW(),
            cancel_reason         = :reason,
            cancel_reason_other   = :other
        WHERE id = :id
    ");
    $upd->execute([
        ':reason' => $reason,
        ':other'  => ($reason === 'Other' ? $reasonOther : null),
        ':id'     => $id
    ]);

    header("Location: dashboard.php?status=success&message=" . urlencode("Cancellation request submitted. Please wait for admin approval."));
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: dashboard.php?status=error&message=" . urlencode("Unable to submit cancellation request."));
    exit;
}