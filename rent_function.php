<?php

session_start();
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please login before renting a vehicle.")
    );
    exit;
}

$userId        = (int) $_SESSION['user_id'];
$customerName  = trim($_SESSION['username'] ?? '');
$vehicleType   = trim($_POST['vehicle_type'] ?? '');
$phoneNumber   = trim($_POST['phone_number'] ?? '');
$driverLicense = trim($_POST['driver_license'] ?? '');
$rentalDate    = trim($_POST['rental_date'] ?? '');
$returnDate    = trim($_POST['return_date'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');

$allowedPayments = ['GCash', 'PayMaya', 'Credit/Debit Card', 'Cash on Pickup'];

if (
    $vehicleType === '' ||
    $phoneNumber === '' ||
    $driverLicense === '' ||
    $rentalDate === '' ||
    $returnDate === '' ||
    $paymentMethod === ''
) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please complete all rental fields, including driver's license and payment method.")
    );
    exit;
}

if (!in_array($paymentMethod, $allowedPayments, true)) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please select a valid payment method.")
    );
    exit;
}

if (!preg_match('/^[0-9+\-\s]{10,15}$/', $phoneNumber)) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please enter a valid phone number.")
    );
    exit;
}

if (strlen($driverLicense) < 5) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please enter a valid driver's license number.")
    );
    exit;
}

$start = DateTime::createFromFormat('Y-m-d', $rentalDate);
$end   = DateTime::createFromFormat('Y-m-d', $returnDate);

if (!$start || !$end) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Invalid rental dates.")
    );
    exit;
}

if (
    $start->format('Y-m-d') !== $rentalDate ||
    $end->format('Y-m-d') !== $returnDate
) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Invalid rental date format.")
    );
    exit;
}

$today = new DateTime('today');

if ($start < $today) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Pickup date cannot be in the past.")
    );
    exit;
}

if ($end < $start) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Return date cannot be earlier than pickup date.")
    );
    exit;
}

$days = $start->diff($end)->days;

if ($days < MIN_RENTAL_DAYS) {
    $days = MIN_RENTAL_DAYS;
}

if ($days > MAX_RENTAL_DAYS) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Rentals are limited to a maximum of " . MAX_RENTAL_DAYS . " day(s). Please adjust your dates.")
    );
    exit;
}

try {

    $pdo = getConnection();

    $vehicleStmt = $pdo->prepare("
        SELECT id, vehicle_name, price_per_day, availability, total_units, available_units
        FROM vehicles
        WHERE vehicle_name = :vehicle_name
        LIMIT 1
    ");

    $vehicleStmt->execute([':vehicle_name' => $vehicleType]);
    $vehicle = $vehicleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        header(
            "Location: index.php?status=error&message=" .
            urlencode("Invalid vehicle selected.")
        );
        exit;
    }

    $vehicleId   = (int) $vehicle['id'];
    $vehicleName = $vehicle['vehicle_name'];
    $dailyRate   = (float) $vehicle['price_per_day'];
    $totalUnits  = (int) $vehicle['total_units'];

    if (
        isset($vehicle['availability']) &&
        strtolower(trim($vehicle['availability'])) !== 'available'
    ) {
        header(
            "Location: index.php?status=error&message=" .
            urlencode("Sorry, $vehicleName is currently marked as unavailable.")
        );
        exit;
    }

    /* Check total available units — only Approved bookings count */

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM rentals
        WHERE vehicle_id = :vid
        AND status = 'Approved'
    ");
    $countStmt->execute([':vid' => $vehicleId]);
    $bookedTotal = (int) $countStmt->fetchColumn();

    if ($bookedTotal >= $totalUnits) {
        header(
            "Location: index.php?status=error&message=" .
            urlencode("Sorry, all $totalUnits unit(s) of $vehicleName are currently rented. Please choose a different vehicle or try again later.")
        );
        exit;
    }

    /* Per-date overbooking check — only Approved bookings block dates */

    $requestedStart = new DateTime($rentalDate);
    $requestedEnd   = new DateTime($returnDate);

    $dateRange = [];
    $interval  = new DateInterval('P1D');
    $period    = new DatePeriod($requestedStart, $interval, $requestedEnd->modify('+1 day'));

    foreach ($period as $date) {
        $dateRange[] = $date->format('Y-m-d');
    }

    $checkStmt = $pdo->prepare("
        SELECT rental_date, return_date
        FROM rentals
        WHERE vehicle_id = :vehicle_id
        AND status = 'Approved'
        AND rental_date <= :return_date
        AND return_date >= :rental_date
    ");

    $checkStmt->execute([
        ':vehicle_id'   => $vehicleId,
        ':rental_date'  => $rentalDate,
        ':return_date'  => $returnDate
    ]);

    $overlappingBookings = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

    $bookedCountByDate = [];

    foreach ($overlappingBookings as $booking) {
        $bStart = new DateTime($booking['rental_date']);
        $bEnd   = new DateTime($booking['return_date']);
        $bEnd->modify('+1 day');

        $bPeriod = new DatePeriod($bStart, $interval, $bEnd);

        foreach ($bPeriod as $date) {
            $dateKey = $date->format('Y-m-d');
            if (!isset($bookedCountByDate[$dateKey])) {
                $bookedCountByDate[$dateKey] = 0;
            }
            $bookedCountByDate[$dateKey]++;
        }
    }

    foreach ($dateRange as $date) {
        $bookedCount = $bookedCountByDate[$date] ?? 0;

        if ($bookedCount >= $totalUnits) {
            header(
                "Location: index.php?status=error&message=" .
                urlencode("Sorry, $vehicleName has no more available units for $date. Please select different dates.")
            );
            exit;
        }
    }

    /* Save booking as Pending — stock does NOT change yet */

    $totalFee = $dailyRate * $days;

    $stmt = $pdo->prepare("
        INSERT INTO rentals
        (user_id, customer_name, phone_number, driver_license, vehicle_id, vehicle_type,
         rental_date, return_date, payment_method, total_fee, status)
        VALUES
        (:user_id, :customer_name, :phone_number, :driver_license, :vehicle_id, :vehicle_type,
         :rental_date, :return_date, :payment_method, :total_fee, 'Pending')
    ");

    $stmt->execute([
        ':user_id'        => $userId,
        ':customer_name'  => $customerName,
        ':phone_number'   => $phoneNumber,
        ':driver_license' => $driverLicense,
        ':vehicle_id'     => $vehicleId,
        ':vehicle_type'   => $vehicleName,
        ':rental_date'    => $rentalDate,
        ':return_date'    => $returnDate,
        ':payment_method' => $paymentMethod,
        ':total_fee'      => $totalFee
    ]);

    header(
        "Location: index.php?status=success&message=" .
        urlencode("Booking submitted! " . $vehicleName . " for " . $days . " day(s) via " . $paymentMethod . ". Total: ₱" . number_format($totalFee, 2) . ". Wait for admin approval.")
    );
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Unable to save your booking. Please try again.")
    );
    exit;
}
?>