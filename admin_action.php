<?php

session_start();
require_once "config.php";

session_timeout_check();

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
    'force_cancel',
];

if (!$id || !in_array($action, $allowedActions, true)) {
    header("Location: dashboard.php?status=error&message=" . urlencode("Invalid request."));
    exit;
}

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, status, vehicle_id, user_id, vehicle_type, total_fee
        FROM rentals
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header("Location: dashboard.php?status=error&message=" . urlencode("Booking not found."));
        exit;
    }

    $current = $booking['status'] ?? 'Pending';
    $message = '';
    $customerId = (int)($booking['user_id'] ?? 0);
    $vehicleName = $booking['vehicle_type'] ?? 'vehicle';

    switch ($action) {

        case 'approve':
            if ($current !== 'Pending') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only Pending bookings can be approved."));
                exit;
            }
            $upd = $pdo->prepare("UPDATE rentals SET status = 'Approved' WHERE id = :id");
            $upd->execute([':id' => $id]);
            $message = 'Booking approved.';

            if ($customerId) {
                notifyCustomer(
                    $pdo, $id, $customerId,
                    'booking_approved',
                    'Your booking was approved!',
                    "Your reservation for $vehicleName has been approved.\n\n" .
                    "Pickup Location: " . COMPANY_ADDRESS . "\n" .
                    "Opening Hours: " . COMPANY_HOURS . "\n" .
                    "Phone: " . COMPANY_PHONE . "\n" .
                    "Email: " . COMPANY_EMAIL . "\n\n" .
                    "Please contact the admin or use the chat box to confirm your pickup schedule."
                );
            }
            break;

        case 'reject':
            if ($current !== 'Pending') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only Pending bookings can be rejected."));
                exit;
            }
            $upd = $pdo->prepare("UPDATE rentals SET status = 'Rejected' WHERE id = :id");
            $upd->execute([':id' => $id]);
            $message = 'Booking rejected.';

            if ($customerId) {
                notifyCustomer(
                    $pdo, $id, $customerId,
                    'booking_rejected',
                    'Your booking was rejected',
                    "Unfortunately, your booking for $vehicleName was not approved.\n" .
                    "Please contact us at " . COMPANY_PHONE . " for more information."
                );
            }
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

            if ($customerId) {
                notifyCustomer(
                    $pdo, $id, $customerId,
                    'booking_cancelled',
                    'Your cancellation was approved',
                    "Your booking for $vehicleName has been cancelled.\n" .
                    "If you have any questions, please contact us at " . COMPANY_PHONE . "."
                );
            }
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

            if ($customerId) {
                notifyCustomer(
                    $pdo, $id, $customerId,
                    'cancel_rejected',
                    'Your cancellation request was declined',
                    "Your request to cancel booking for $vehicleName was declined by our team.\n" .
                    "Your booking remains active. Please contact us at " . COMPANY_PHONE . " if you need to discuss this further."
                );
            }
            break;

        case 'pickup':
            if ($current !== 'Approved') {
                header("Location: dashboard.php?status=error&message=" . urlencode("Only Approved bookings can be marked as Picked Up."));
                exit;
            }
            $upd = $pdo->prepare("UPDATE rentals SET status = 'Picked Up' WHERE id = :id");
            $upd->execute([':id' => $id]);
            $message = 'Booking marked as Picked Up.';

            if ($customerId) {
                notifyCustomer(
                    $pdo, $id, $customerId,
                    'booking_picked_up',
                    'Vehicle picked up',
                    "You have picked up the $vehicleName. Drive safely!\n" .
                    "Remember to return it on or before the agreed return date to avoid a ₱" .
                    number_format(LATE_FEE_PER_DAY, 0) . " per day late fee."
                );
            }
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

            if ($customerId) {
                notifyCustomer(
                    $pdo, $id, $customerId,
                    'booking_cancelled',
                    'Your booking was cancelled by RC Drive',
                    "Your booking for $vehicleName was cancelled by our team.\n" .
                    "If you have questions, please contact us at " . COMPANY_PHONE . "."
                );
            }
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