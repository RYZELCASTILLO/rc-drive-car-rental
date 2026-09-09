<?php

require_once "config.php";
require_once "validation.php";

if (!isset($_POST['register'])) {
    header('Location: index.php');
    exit;
}

$result = validateStudentInput($_POST);
$errors = $result['errors'];

if (!empty($errors)) {
    $message = implode(' ', $errors);
    header('Location: index.php?status=error&message=' . urlencode($message));
    exit;
}

try {
    $pdo = getConnection();

    $sql = "INSERT INTO users (username, email, password, role) 
            VALUES (:username, :email, :password, :role)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':username', $result['data']['username']);
    $stmt->bindValue(':email', $result['data']['email']);

    $hashedPassword = password_hash($result['data']['password'], PASSWORD_DEFAULT);
    $stmt->bindValue(':password', $hashedPassword);

    $stmt->bindValue(':role', 'customer');

    $stmt->execute();

    header("Location: index.php?status=success&message=" . urlencode("Account created! Please log in."));
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: index.php?status=error&message=' . urlencode("Could not create account. Username or email may already be taken."));
    exit;
}