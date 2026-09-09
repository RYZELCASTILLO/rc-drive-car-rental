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

$userId = (int) $_SESSION['user_id'];
$customerName = trim($_SESSION['username'] ?? '');
$vehicleType = trim($_POST['vehicle_type'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$rentalDate = trim($_POST['rental_date'] ?? '');
$returnDate = trim($_POST['return_date'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');

$allowedPayments = ['GCash', 'PayMaya', 'Credit/Debit Card', 'Cash on Pickup'];

if (
    $vehicleType === '' ||
    $phoneNumber === '' ||
    $rentalDate === '' ||
    $returnDate === '' ||
    $paymentMethod === ''
) {
    header(
        "Location: index.php?status=error&message=" .
        urlencode("Please complete all rental fields, including payment method.")
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

if ($days < 1) {
    $days = 1;
}

try {

    $pdo = getConnection();

    $vehicleStmt = $pdo->prepare("
        SELECT id, vehicle_name, price_per_day, availability
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

    $vehicleId = (int) $vehicle['id'];
    $vehicleName = $vehicle['vehicle_name'];
    $dailyRate = (float) $vehicle['price_per_day'];

    if (
        isset($vehicle['availability']) &&
        strtolower(trim($vehicle['availability'])) !== 'available'
    ) {
        header(
            "Location: index.php?status=error&message=" .
            urlencode("This vehicle is currently unavailable.")
        );
        exit;
    }

    $totalFee = $dailyRate * $days;

    $check = $pdo->prepare("
        SELECT id
        FROM rentals
        WHERE vehicle_id = :vehicle_id
        AND status IN ('Pending', 'Approved')
        AND rental_date <= :return_date
        AND return_date >= :rental_date
        LIMIT 1
    ");

    $check->execute([
        ':vehicle_id'  => $vehicleId,
        ':rental_date' => $rentalDate,
        ':return_date' => $returnDate
    ]);

    if ($check->fetch()) {
        header(
            "Location: index.php?status=error&message=" .
            urlencode("This vehicle is already reserved for the selected dates.")
        );
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO rentals
        (user_id, customer_name, phone_number, vehicle_id, vehicle_type,
         rental_date, return_date, payment_method, total_fee, status)
        VALUES
        (:user_id, :customer_name, :phone_number, :vehicle_id, :vehicle_type,
         :rental_date, :return_date, :payment_method, :total_fee, 'Pending')
    ");

    $stmt->execute([
        ':user_id'        => $userId,
        ':customer_name'  => $customerName,
        ':phone_number'   => $phoneNumber,
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