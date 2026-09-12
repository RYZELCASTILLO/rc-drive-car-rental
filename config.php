<?php

/* =========================================================================
   BOOKING RULES
   ========================================================================= */

define('MAX_RENTAL_DAYS', 7);
define('MIN_RENTAL_DAYS', 1);


function getConnection()
{
    $host = "localhost";
    $dbname = "rc_drive";
    $username = "root";
    $password = "";

    try {

        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password
        );

        $pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );

        return $pdo;

    } catch (PDOException $e) {

        error_log($e->getMessage());

        die("Database connection failed. Please contact the administrator.");
    }
}


/* =========================================================================
   VEHICLE STOCK HELPERS
   Only APPROVED bookings reduce availability.
   ========================================================================= */

function syncVehicleStock(PDO $pdo, int $vehicleId): void
{
    $v = $pdo->prepare("SELECT total_units FROM vehicles WHERE id = :id LIMIT 1");
    $v->execute([':id' => $vehicleId]);
    $row = $v->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return;
    }

    $total = (int) $row['total_units'];

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM rentals
        WHERE vehicle_id = :vid
        AND status = 'Approved'
    ");
    $countStmt->execute([':vid' => $vehicleId]);
    $booked = (int) $countStmt->fetchColumn();

    $available = max(0, $total - $booked);

    $upd = $pdo->prepare("
        UPDATE vehicles 
        SET available_units = :a 
        WHERE id = :id
    ");
    $upd->execute([
        ':a'  => $available,
        ':id' => $vehicleId
    ]);
}


function syncAllVehicleStock(PDO $pdo): void
{
    $ids = $pdo->query("SELECT id FROM vehicles")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        syncVehicleStock($pdo, (int)$id);
    }
}