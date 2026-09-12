<?php

session_start();
require_once "config.php";

header('Content-Type: application/json');

$vehicleName = $_GET['vehicle'] ?? '';

if ($vehicleName === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Vehicle name is required.'
    ]);
    exit;
}

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, vehicle_name, total_units, available_units, price_per_day
        FROM vehicles
        WHERE vehicle_name = :vehicle_name
        LIMIT 1
    ");

    $stmt->execute([':vehicle_name' => $vehicleName]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found.'
        ]);
        exit;
    }

    $vehicleId  = (int) $vehicle['id'];
    $totalUnits = (int) $vehicle['total_units'];

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM rentals
        WHERE vehicle_id = :vid
        AND status = 'Approved'
    ");
    $countStmt->execute([':vid' => $vehicleId]);
    $bookedTotal = (int) $countStmt->fetchColumn();

    $currentlyAvailable = max(0, $totalUnits - $bookedTotal);

    $bookingsStmt = $pdo->prepare("
        SELECT rental_date, return_date
        FROM rentals
        WHERE vehicle_id = :vehicle_id
        AND status = 'Approved'
        AND return_date >= CURDATE()
        ORDER BY rental_date ASC
    ");

    $bookingsStmt->execute([':vehicle_id' => $vehicleId]);
    $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

    $bookedCountByDate = [];

    foreach ($bookings as $booking) {
        $start = new DateTime($booking['rental_date']);
        $end   = new DateTime($booking['return_date']);
        $end->modify('+1 day');

        $interval = new DateInterval('P1D');
        $period   = new DatePeriod($start, $interval, $end);

        foreach ($period as $date) {
            $dateKey = $date->format('Y-m-d');
            if (!isset($bookedCountByDate[$dateKey])) {
                $bookedCountByDate[$dateKey] = 0;
            }
            $bookedCountByDate[$dateKey]++;
        }
    }

    $unavailableDates = [];

    foreach ($bookedCountByDate as $date => $count) {
        if ($count >= $totalUnits) {
            $unavailableDates[] = $date;
        }
    }

    echo json_encode([
        'success'             => true,
        'vehicle_id'          => $vehicleId,
        'vehicle_name'        => $vehicle['vehicle_name'],
        'total_stock'         => $totalUnits,
        'currently_available' => $currentlyAvailable,
        'unavailable_dates'   => $unavailableDates
    ]);

} catch (PDOException $e) {

    error_log($e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Database error.'
    ]);
}
?>