<?php
try {
    // Create PDO connection
    $db = new PDO(
        'mysql:host=localhost;dbname=login_pg;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return $db;
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?> 