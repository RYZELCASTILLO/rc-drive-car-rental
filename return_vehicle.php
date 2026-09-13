<?php

session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php?status=error&message=" . urlencode("Admin access required."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$id               = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$actualReturnDate = trim($_POST['actual_return_date'] ?? '');

if (!$id || $actualReturnDate === '') {
    header("Location: dashboard.php?status=error&message=" . urlencode("Invalid request."));
    exit;
}

$dateObj = DateTime::createFromFormat('Y-m-d', $actualReturnDate);

if (!$dateObj || $dateObj->format('Y-m-d') !== $actualReturnDate) {
    header("Location: dashboard.php?status=error&message=" . urlencode("Invalid return date."));
    exit;
}

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, status, vehicle_id, return_date, total_fee
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

    if (($booking['status'] ?? '') !== 'Picked Up') {
        header("Location: dashboard.php?status=error&message=" . urlencode("Only Picked Up bookings can be returned."));
        exit;
    }

    // Compute late days
    $agreedReturn = new DateTime($booking['return_date']);
    $actualReturn = new DateTime($actualReturnDate);

    $lateDays = 0;
    if ($actualReturn > $agreedReturn) {
        $lateDays = $agreedReturn->diff($actualReturn)->days;
    }

    // Flat ₱1,000 per day
    $lateFee  = calculateLateFee($lateDays);
    $newTotal = (float)$booking['total_fee'] + $lateFee;
    $vehicleId = (int)$booking['vehicle_id'];

    $upd = $pdo->prepare("
        UPDATE rentals
        SET status             = 'Returned',
            actual_return_date = :actual_date,
            late_days          = :late_days,
            late_fee           = :late_fee,
            total_fee          = :new_total
        WHERE id = :id
    ");
    $upd->execute([
        ':actual_date' => $actualReturnDate,
        ':late_days'   => $lateDays,
        ':late_fee'    => $lateFee,
        ':new_total'   => $newTotal,
        ':id'          => $id
    ]);

    syncVehicleStock($pdo, $vehicleId);

    if ($lateDays > 0) {
        $message = "Vehicle returned. Late by $lateDays day(s). Late fee: ₱" .
                   number_format($lateFee, 2) . " (₱" .
                   number_format(LATE_FEE_PER_DAY, 2) . "/day). " .
                   "New total: ₱" . number_format($newTotal, 2);
    } else {
        $message = "Vehicle returned on time. No late fee.";
    }

    header("Location: dashboard.php?status=success&message=" . urlencode($message));
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: dashboard.php?status=error&message=" . urlencode("Unable to process return."));
    exit;
}