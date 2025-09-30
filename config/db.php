<?php
$host = "localhost";
$db   = "game_zone_decor";
$user = "root";   // XAMPP ปกติ
$pass = "";       // XAMPP ปกติเป็นค่าว่าง

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo "Database connection failed.";
    exit;
}
