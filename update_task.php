<?php
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dependencies = !empty($_POST['dependencies']) ? json_encode($_POST['dependencies']) : null;
    
    $stmt = $db->prepare('UPDATE tasks SET 
        task_name = :task_name,
        assigned_user = :assigned_user,
        task_description = :task_description,
        priority = :priority,
        progress = :progress,
        dependencies = :dependencies
        WHERE id = :id');
    
    $stmt->execute([
        ':task_name' => $_POST['taskName'],
        ':assigned_user' => $_POST['assignedUser'],
        ':task_description' => $_POST['taskDescription'],
        ':priority' => $_POST['priority'],
        ':progress' => $_POST['progress'],
        ':dependencies' => $dependencies,
        ':id' => $_POST['task_id']
    ]);
    
    header('Location: index.php');
    exit;
}
?> 