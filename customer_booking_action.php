<?php

session_start();
require_once "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?status=error&message=" . urlencode("Please login first."));
    exit;
}

$userId   = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Customer';
$role     = $_SESSION['role'] ?? 'customer';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT)
       ?: filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$allowedActions = ['cancel', 'reschedule'];

if (!$id || !in_array($action, $allowedActions, true)) {
    header("Location: dashboard.php?status=error&message=" . urlencode("Invalid request."));
    exit;
}

function hoursUntilPickup(string $rentalDate): float
{
    $pickup  = new DateTime($rentalDate . ' 08:00:00');
    $now     = new DateTime();
    return ($pickup->getTimestamp() - $now->getTimestamp()) / 3600;
}

function calculateCancellationFee(float $hoursUntil, float $totalFee): array
{
    if ($hoursUntil > 48) {
        return ['fee' => 0.0, 'refund' => $totalFee, 'policy' => 'free'];
    }
    if ($hoursUntil >= 24) {
        $fee = round($totalFee * 0.5, 2);
        return ['fee' => $fee, 'refund' => $totalFee - $fee, 'policy' => 'half'];
    }
    return ['fee' => $totalFee, 'refund' => 0.0, 'policy' => 'none'];
}

function notifyAdmins(
    PDO $pdo,
    ?int $rentalId,
    string $type,
    string $title,
    string $message
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications
                (recipient_role, recipient_id, rental_id, type, title, message)
            VALUES
                ('admin', NULL, :rental_id, :type, :title, :message)
        ");
        $stmt->execute([
            ':rental_id' => $rentalId,
            ':type'      => $type,
            ':title'     => $title,
            ':message'   => $message
        ]);
    } catch (PDOException $e) {
        error_log("notifyAdmins failed: " . $e->getMessage());
    }
}

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT * FROM rentals WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        header("Location: dashboard.php?status=error&message=" . urlencode("Booking not found."));
        exit;
    }

    $isOwner = ((int)($rental['user_id'] ?? 0) === $userId);
    if (!$isOwner && $role !== 'admin') {
        header("Location: dashboard.php?status=error&message=" . urlencode("You can only manage your own bookings."));
        exit;
    }

    $currentStatus = $rental['status'] ?? 'Pending';
    $rentalDate    = $rental['rental_date'];
    $returnDate    = $rental['return_date'];
    $totalFee      = (float)($rental['total_fee'] ?? 0);
    $vehicleId     = (int)($rental['vehicle_id'] ?? 0);
    $reschedCount  = (int)($rental['rescheduled_count'] ?? 0);

    if (!in_array($currentStatus, ['Pending', 'Approved'], true)) {
        header(
            "Location: dashboard.php?status=error&message=" .
            urlencode("This booking can no longer be modified (status: $currentStatus).")
        );
        exit;
    }

    $hoursUntil = hoursUntilPickup($rentalDate);

    /* =========================================================
       CANCEL
    ========================================================= */
    if ($action === 'cancel') {

        if ($hoursUntil < 24) {
            header(
                "Location: dashboard.php?status=error&message=" .
                urlencode("Bookings cannot be self-cancelled within 24 hours of pickup. Please contact RC Drive directly at +63 930 222 9696.")
            );
            exit;
        }

        $tier   = calculateCancellationFee($hoursUntil, $totalFee);
        $reason = trim($_POST['reason'] ?? 'Customer cancelled');

        $pdo->beginTransaction();

        $upd = $pdo->prepare("
            UPDATE rentals
            SET status              = 'Cancelled',
                cancelled_by        = 'customer',
                cancelled_at        = NOW(),
                cancellation_reason = :reason,
                cancellation_fee    = :fee,
                refund_amount       = :refund
            WHERE id = :id
        ");
        $upd->execute([
            ':reason' => $reason,
            ':fee'    => $tier['fee'],
            ':refund' => $tier['refund'],
            ':id'     => $id
        ]);

        if ($vehicleId > 0) {
            syncVehicleStock($pdo, $vehicleId);
        }

        $pdo->commit();

        $policyLabel = [
            'free' => 'Free cancellation (full refund)',
            'half' => 'Cancelled within 48h — 50% fee applied',
            'none' => 'Cancelled within 24h — no refund'
        ][$tier['policy']];

        notifyAdmins(
            $pdo,
            $id,
            'booking_cancelled',
            "Booking #$id cancelled by $username",
            "Vehicle: {$rental['vehicle_type']}\n" .
            "Original pickup: $rentalDate\n" .
            "Reason: $reason\n" .
            "$policyLabel\n" .
            "Fee: PHP " . number_format($tier['fee'], 2) . "\n" .
            "Refund: PHP " . number_format($tier['refund'], 2)
        );

        header(
            "Location: dashboard.php?status=success&message=" .
            urlencode("Booking cancelled. $policyLabel. Refund: PHP " . number_format($tier['refund'], 2))
        );
        exit;
    }

    /* =========================================================
       RESCHEDULE
    ========================================================= */
    if ($action === 'reschedule') {

        if ($hoursUntil < 24) {
            header(
                "Location: dashboard.php?status=error&message=" .
                urlencode("Bookings cannot be rescheduled within 24 hours of pickup. Please contact RC Drive directly.")
            );
            exit;
        }

        if ($reschedCount >= 3) {
            header(
                "Location: dashboard.php?status=error&message=" .
                urlencode("This booking has been rescheduled too many times. Please contact support.")
            );
            exit;
        }

        $newStart = trim($_POST['new_rental_date'] ?? '');
        $newEnd   = trim($_POST['new_return_date'] ?? '');

        if ($newStart === '' || $newEnd === '') {
            header(
                "Location: dashboard.php?status=error&message=" .
                urlencode("Please provide both new pickup and return dates.")
            );
            exit;
        }

        $start = DateTime::createFromFormat('Y-m-d', $newStart);
        $end   = DateTime::createFromFormat('Y-m-d', $newEnd);

        if (!$start || !$end) {
            header("Location: dashboard.php?status=error&message=" . urlencode("Invalid dates."));
            exit;
        }

        $today = new DateTime('today');

        if ($start < $today) {
            header("Location: dashboard.php?status=error&message=" . urlencode("New pickup date cannot be in the past."));
            exit;
        }

        if ($end < $start) {
            header("Location: dashboard.php?status=error&message=" . urlencode("Return date cannot be before pickup date."));
            exit;
        }

        $days = $start->diff($end)->days;
        if ($days < MIN_RENTAL_DAYS) $days = MIN_RENTAL_DAYS;

        if ($days > MAX_RENTAL_DAYS) {
            header(
                "Location: dashboard.php?status=error&message=" .
                urlencode("Rentals are limited to " . MAX_RENTAL_DAYS . " days.")
            );
            exit;
        }

        $pdo->beginTransaction();

        $lock = $pdo->prepare("
            SELECT id, total_units FROM vehicles WHERE id = :id FOR UPDATE
        ");
        $lock->execute([':id' => $vehicleId]);
        $vehicleRow = $lock->fetch(PDO::FETCH_ASSOC);

        if (!$vehicleRow) {
            $pdo->rollBack();
            header("Location: dashboard.php?status=error&message=" . urlencode("Vehicle not found."));
            exit;
        }

        $totalUnits = (int)$vehicleRow['total_units'];

        $check = $pdo->prepare("
            SELECT rental_date, return_date
            FROM rentals
            WHERE vehicle_id = :vid
              AND id <> :exclude_id
              AND status IN ('Approved','Picked Up')
              AND rental_date <= :return_date
              AND return_date  >= :rental_date
        ");
        $check->execute([
            ':vid'         => $vehicleId,
            ':exclude_id'  => $id,
            ':rental_date' => $newStart,
            ':return_date' => $newEnd
        ]);
        $overlaps = $check->fetchAll(PDO::FETCH_ASSOC);

        $bookedCountByDate = [];
        $interval = new DateInterval('P1D');

        foreach ($overlaps as $o) {
            $bStart = new DateTime($o['rental_date']);
            $bEnd   = new DateTime($o['return_date']);
            $bEnd->modify('+1 day');
            $period = new DatePeriod($bStart, $interval, $bEnd);

            foreach ($period as $date) {
                $key = $date->format('Y-m-d');
                $bookedCountByDate[$key] = ($bookedCountByDate[$key] ?? 0) + 1;
            }
        }

        $conflict = null;
        $cursor   = clone $start;
        $stop     = clone $end;

        while ($cursor <= $stop) {
            $key = $cursor->format('Y-m-d');
            if (($bookedCountByDate[$key] ?? 0) >= $totalUnits) {
                $conflict = $key;
                break;
            }
            $cursor->modify('+1 day');
        }

        if ($conflict) {
            $pdo->rollBack();
            header(
                "Location: dashboard.php?status=error&message=" .
                urlencode("The new date range is fully booked on $conflict. Please pick different dates.")
            );
            exit;
        }

        $newFee = calculateRentalFee($pdo, $vehicleId, $newStart, $newEnd, $days);

        $upd = $pdo->prepare("
            UPDATE rentals
            SET rental_date            = :new_start,
                return_date            = :new_end,
                total_fee              = :new_fee,
                rescheduled_count      = rescheduled_count + 1,
                original_rental_date   = COALESCE(original_rental_date, :old_start),
                original_return_date   = COALESCE(original_return_date, :old_end),
                last_rescheduled_at    = NOW()
            WHERE id = :id
        ");
        $upd->execute([
            ':new_start' => $newStart,
            ':new_end'   => $newEnd,
            ':new_fee'   => $newFee,
            ':old_start' => $rentalDate,
            ':old_end'   => $returnDate,
            ':id'        => $id
        ]);

        if ($vehicleId > 0) {
            syncVehicleStock($pdo, $vehicleId);
        }

        $pdo->commit();

        notifyAdmins(
            $pdo,
            $id,
            'booking_rescheduled',
            "Booking #$id rescheduled by $username",
            "Vehicle: {$rental['vehicle_type']}\n" .
            "Old dates: $rentalDate -> $returnDate\n" .
            "New dates: $newStart -> $newEnd\n" .
            "New total: PHP " . number_format($newFee, 2)
        );

        header(
            "Location: dashboard.php?status=success&message=" .
            urlencode("Booking rescheduled to $newStart -> $newEnd. New total: PHP " . number_format($newFee, 2))
        );
        exit;
    }

    header("Location: dashboard.php?status=error&message=" . urlencode("Unsupported action."));
    exit;

} catch (PDOException $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    header("Location: dashboard.php?status=error&message=" . urlencode("Unable to complete request."));
    exit;
}