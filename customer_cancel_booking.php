<?php

session_start();
require_once "config.php";

session_timeout_check();

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
        SELECT id, user_id, status, created_at, rental_date, vehicle_type
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

    if ($status !== 'Pending') {
        header("Location: dashboard.php?status=error&message=" .
               urlencode("This booking can no longer be self-cancelled because it has already been processed. Please chat with us if you still need to cancel."));
        exit;
    }

    $createdTs  = strtotime($booking['created_at']);
    $hoursSince = (time() - $createdTs) / 3600;

    if ($hoursSince > CANCEL_WINDOW_HOURS) {
        header("Location: dashboard.php?status=error&message=" .
               urlencode("The " . CANCEL_WINDOW_HOURS . "-hour self-cancellation window has passed. Please chat with us and an admin will assist you."));
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

    /* Notify admin */
    notifyAdmin(
        $pdo,
        $id,
        'cancel_requested',
        "Cancellation request from customer",
        "Booking #$id — {$booking['vehicle_type']}\n" .
        "Reason: $reason" . ($reason === 'Other' ? " ($reasonOther)" : "")
    );

    header("Location: dashboard.php?status=success&message=" . urlencode("Cancellation request submitted. Please wait for admin approval."));
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: dashboard.php?status=error&message=" . urlencode("Unable to submit cancellation request."));
    exit;
}