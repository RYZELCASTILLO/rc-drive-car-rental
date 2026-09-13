<?php

session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php?status=error&message=" . urlencode("Admin access required."));
    exit;
}

$action = $_GET['action'] ?? '';
$id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$allowedActions = [
    'approve',
    'reject',
    'approve_cancel',
    'reject_cancel',
    'pickup',
    'return',
    'force_cancel',
];

if (!$id || !in_array($action, $allowedActions, true)) {
    header("Location: dashboard.php?status=error&message=" . urlencode("Invalid request."));
    exit;
}

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT status, vehicle_id FROM rentals WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header("Location: dashboard.php?status=error&message=" . urlencode("Booking not found."));
        exit;
    }

    $current = $booking['status'] ?? 'Pending';
    $message = '';

    switch ($action) {

        case 'approve':
            if ($current !== 'Pending') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only Pending bookings can be approved."));
                exit;
            }
            $upd = $pdo->prepare("UPDATE rentals SET status = 'Approved' WHERE id = :id");
            $upd->execute([':id' => $id]);
            $message = 'Booking approved.';
            break;

        case 'reject':
            if ($current !== 'Pending') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only Pending bookings can be rejected."));
                exit;
            }
            $upd = $pdo->prepare("UPDATE rentals SET status = 'Rejected' WHERE id = :id");
            $upd->execute([':id' => $id]);
            $message = 'Booking rejected.';
            break;

        case 'approve_cancel':
            if ($current !== 'Cancel Requested') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only cancel requests can be approved here."));
                exit;
            }
            $upd = $pdo->prepare("
                UPDATE rentals
                SET status             = 'Cancelled',
                    cancel_decision    = 'Approved',
                    cancel_decided_at  = NOW()
                WHERE id = :id
            ");
            $upd->execute([':id' => $id]);
            $message = 'Cancellation approved.';
            break;

        case 'reject_cancel':
            if ($current !== 'Cancel Requested') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only cancel requests can be rejected here."));
                exit;
            }
            $upd = $pdo->prepare("
                UPDATE rentals
                SET status             = 'Approved',
                    cancel_decision    = 'Rejected',
                    cancel_decided_at  = NOW()
                WHERE id = :id
            ");
            $upd->execute([':id' => $id]);
            $message = 'Cancellation rejected. Booking remains approved.';
            break;

        case 'pickup':
            if ($current !== 'Approved') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only Approved bookings can be marked as Picked Up."));
                exit;
            }
            $upd = $pdo->prepare("UPDATE rentals SET status = 'Picked Up' WHERE id = :id");
            $upd->execute([':id' => $id]);
            $message = 'Booking marked as Picked Up.';
            break;

        case 'return':
            if ($current !== 'Picked Up') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only Picked Up bookings can be marked as Returned."));
                exit;
            }
            $upd = $pdo->prepare("UPDATE rentals SET status = 'Returned' WHERE id = :id");
            $upd->execute([':id' => $id]);
            $message = 'Booking marked as Returned.';
            break;

        case 'force_cancel':
            $blockedStatuses = ['Cancelled', 'Returned', 'Rejected'];
            if (in_array($current, $blockedStatuses, true)) {
                header("Location: dashboard.php?status=error&message=" . urlencode("This booking is already closed ($current)."));
                exit;
            }
            $upd = $pdo->prepare("
                UPDATE rentals
                SET status             = 'Cancelled',
                    cancel_decision    = 'Approved',
                    cancel_decided_at  = NOW()
                WHERE id = :id
            ");
            $upd->execute([':id' => $id]);
            $message = 'Booking cancelled by admin.';
            break;
    }

    if (!empty($booking['vehicle_id'])) {
        syncVehicleStock($pdo, (int)$booking['vehicle_id']);
    }

    header("Location: dashboard.php?status=success&message=" . urlencode($message));
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header("Location: dashboard.php?status=error&message=" . urlencode("Unable to update booking."));
    exit;
}