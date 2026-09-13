<?php

/* =========================================================================
   TIMEZONE — Force Philippine Time (Asia/Manila, UTC+8)
   ========================================================================= */

date_default_timezone_set('Asia/Manila');


/* =========================================================================
   COMPANY INFO — Centralized so we never hardcode these again
   ========================================================================= */

define('COMPANY_NAME',    'RC Drive Car Rental Services');
define('COMPANY_PHONE',   '+63 930 222 9696');
define('COMPANY_EMAIL',   'support@rcdrive.com');
define('COMPANY_ADDRESS', '123 Rizal Boulevard, Dumaguete City, Negros Oriental');
define('COMPANY_HOURS',   'Monday – Sunday, 7:00 AM – 9:00 PM');


/* =========================================================================
   BOOKING RULES
   ========================================================================= */

define('MAX_RENTAL_DAYS', 7);
define('MIN_RENTAL_DAYS', 1);

define('LATE_FEE_PER_DAY', 1000);
define('CANCEL_WINDOW_HOURS', 2);
define('MAX_CHAT_MESSAGE_LENGTH', 2000);

define('SESSION_TIMEOUT_SECONDS', 1800); // 30 minutes of inactivity


/* =========================================================================
   DATABASE
   ========================================================================= */

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

        $pdo->exec("SET time_zone = '+08:00'");

        return $pdo;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        die("Database connection failed. Please contact the administrator.");
    }
}


/* =========================================================================
   SESSION TIMEOUT — Fix #5
   Call session_timeout_check() at the top of every authenticated page.
   ========================================================================= */

function session_timeout_check(): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $now = time();

    if (isset($_SESSION['last_activity']) &&
        ($now - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {

        $_SESSION = [];
        session_destroy();

        header("Location: index.php?status=error&message=" .
               urlencode("Your session expired due to inactivity. Please login again."));
        exit;
    }

    $_SESSION['last_activity'] = $now;
}


/* =========================================================================
   VEHICLE STOCK
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


/* =========================================================================
   PRICING
   ========================================================================= */

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


function calculateLateFee(int $lateDays): float
{
    if ($lateDays <= 0) {
        return 0.0;
    }

    return round($lateDays * LATE_FEE_PER_DAY, 2);
}


/* =========================================================================
   NOTIFICATIONS — Fix #2
   Every admin action sends a notification to the customer.
   ========================================================================= */

function notifyCustomer(
    PDO $pdo,
    int $rentalId,
    int $customerId,
    string $type,
    string $title,
    string $message
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications
                (recipient_role, recipient_id, rental_id, type, title, message)
            VALUES
                ('customer', :uid, :rental_id, :type, :title, :message)
        ");
        $stmt->execute([
            ':uid'       => $customerId,
            ':rental_id' => $rentalId,
            ':type'      => $type,
            ':title'     => $title,
            ':message'   => $message
        ]);
    } catch (PDOException $e) {
        error_log("notifyCustomer failed: " . $e->getMessage());
    }
}


function notifyAdmin(
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
        error_log("notifyAdmin failed: " . $e->getMessage());
    }
}