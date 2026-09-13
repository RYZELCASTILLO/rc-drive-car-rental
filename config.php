<?php

/* =========================================================================
   TIMEZONE — Force Philippine Time (Asia/Manila, UTC+8)
   ========================================================================= */

date_default_timezone_set('Asia/Manila');


/* =========================================================================
   BOOKING RULES
   ========================================================================= */

define('MAX_RENTAL_DAYS', 7);
define('MIN_RENTAL_DAYS', 1);


function getConnection()
{
    $host     = "localhost";
    $dbname   = "rc_drive";
    $username = "root";
    $password = "";

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        /* Force MySQL session to use Philippine time too */
        $pdo->exec("SET time_zone = '+08:00'");

        return $pdo;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        die("Database connection failed. Please contact the administrator.");
    }
}


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
          AND status IN ('Approved','Picked Up','Cancel Requested')
          AND return_date >= CURDATE()
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


function calculateRentalFee(PDO $pdo, int $vehicleId, string $startDate, string $endDate, int $days): float
{
    $stmt = $pdo->prepare("SELECT price_per_day FROM vehicles WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $vehicleId]);
    $dailyRate = (float) $stmt->fetchColumn();

    if ($dailyRate <= 0) {
        return 0.0;
    }

    return round($dailyRate * max(1, $days), 2);
}