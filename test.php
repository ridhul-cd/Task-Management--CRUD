<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

try {
    // Test database connection
    echo "<h2>Testing Database Connection</h2>";
    if ($db) {
        echo "Database connection successful!<br>";
    }
    
    // Test data insertion
    echo "<h2>Testing Data Insertion</h2>";
    $stmt = $db->prepare('INSERT INTO tasks (task_name, assigned_user, task_description, priority, progress) 
                         VALUES (:task_name, :assigned_user, :task_description, :priority, :progress)');
    
    $result = $stmt->execute([
        ':task_name' => 'Test Task 2',
        ':assigned_user' => 'user1',
        ':task_description' => 'This is another test task',
        ':priority' => 'high',
        ':progress' => 'pending'
    ]);
    
    if ($result) {
        echo "Test data inserted successfully!<br>";
    }
    
    // Test data retrieval
    echo "<h2>Testing Data Retrieval</h2>";
    $stmt = $db->query('SELECT * FROM tasks');
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($tasks);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 