<?php
require_once 'db_connect.php';

try {
    // Create tasks table if it doesn't exist
    $query = "CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_name VARCHAR(255) NOT NULL,
        assigned_user VARCHAR(100) NOT NULL,
        task_description TEXT,
        priority VARCHAR(50) NOT NULL,
        progress VARCHAR(50) NOT NULL,
        dependencies JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $db->exec($query);
    echo "Database setup completed successfully!";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
?> 