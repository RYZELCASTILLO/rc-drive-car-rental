<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?status=error&message=" . urlencode("Authentication required."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_purchase'])) {
    $pdo = getConnection();
    
    $customerName = trim($_POST['customer_name']);
    $vehicleName = trim($_POST['vehicle_name']);
    $price = floatval($_POST['price']);
    $userId = $_SESSION['user_id'];

    // If Admin inputs a purchase for another customer, assign to that user's ID if found
    if (($_SESSION['role'] ?? '') === 'admin') {
        $stmtUser = $pdo->prepare("SELECT id FROM user WHERE LOWER(username) = LOWER(:username) LIMIT 1");
        $stmtUser->execute([':username' => $customerName]);
        $targetUser = $stmtUser->fetch();
        if ($targetUser) {
            $userId = $targetUser['id'];
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO purchases (user_id, customer_name, vehicle_name, price) 
            VALUES (:user_id, :customer_name, :vehicle_name, :price)
        ");
        $stmt->execute([
            ':user_id'       => $userId,
            ':customer_name' => $customerName,
            ':vehicle_name'  => $vehicleName,
            ':price'         => $price
        ]);

        header("Location: index.php?status=success&message=" . urlencode("Car purchase recorded successfully!"));
        exit();
    } catch (PDOException $e) {
        header("Location: index.php?status=error&message=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}