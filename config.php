<?php

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