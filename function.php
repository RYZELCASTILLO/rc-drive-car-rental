<?php

require 'config.php';
require 'validation.php';

if (!isset($_POST['add-student'])) {
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

    $sql = "INSERT INTO user (username, email, password, role) 
            VALUES (:username, :email, :password, :role)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':username', $result['data']['username']);
    $stmt->bindValue(':email', $result['data']['email']);
    
    // Hash the password first, then bind the hashed variable
    $hashedPassword = password_hash($result['data']['password'], PASSWORD_DEFAULT);
    $stmt->bindValue(':password', $hashedPassword);
    
    $stmt->bindValue(':role', 'customer');

    $stmt->execute();
    $newId = $pdo->lastInsertId();

    header("Location: index.php?status=success&id=" . $newId);
    exit;

} catch (PDOException $e) {
    header('Location: index.php?status=error&message=' . urlencode($e->getMessage()));
    exit;
}