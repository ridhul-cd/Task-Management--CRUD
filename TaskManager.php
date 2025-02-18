<?php
class TaskManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function addTask($data) {
        try {
            $dependencies = !empty($data['dependencies']) ? json_encode($data['dependencies']) : null;
            
            $stmt = $this->db->prepare('INSERT INTO tasks (
                task_name, 
                assigned_user, 
                task_description, 
                priority, 
                progress,
                dependencies
            ) VALUES (
                :task_name, 
                :assigned_user, 
                :task_description, 
                :priority, 
                :progress,
                :dependencies
            )');
            
            return $stmt->execute([
                ':task_name' => $data['taskName'],
                ':assigned_user' => $data['assignedUser'],
                ':task_description' => $data['taskDescription'],
                ':priority' => $data['priority'],
                ':progress' => $data['progress'],
                ':dependencies' => $dependencies
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error adding task: " . $e->getMessage());
        }
    }

    public function getAllTasks() {
        try {
            $stmt = $this->db->query('SELECT * FROM tasks ORDER BY created_at DESC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching tasks: " . $e->getMessage());
        }
    }

    public function getTaskById($id) {
        try {
            $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = :id');
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching task: " . $e->getMessage());
        }
    }

    public function deleteTask($id) {
        try {
            // First check if any tasks depend on this one
            $stmt = $this->db->prepare('SELECT id FROM tasks WHERE JSON_CONTAINS(dependencies, :id)');
            $stmt->execute([':id' => json_encode($id)]);
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("Cannot delete task: other tasks depend on it");
            }
            
            $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            throw new Exception("Error deleting task: " . $e->getMessage());
        }
    }

    public function updateTask($id, $data) {
        try {
            $dependencies = !empty($data['dependencies']) ? json_encode($data['dependencies']) : null;
            
            // Check for circular dependencies
            if ($dependencies && $this->hasCircularDependency($id, json_decode($dependencies, true))) {
                throw new Exception("Circular dependency detected");
            }
            
            $stmt = $this->db->prepare('UPDATE tasks SET 
                task_name = :task_name,
                assigned_user = :assigned_user,
                task_description = :task_description,
                priority = :priority,
                progress = :progress,
                dependencies = :dependencies
                WHERE id = :id');
            
            return $stmt->execute([
                ':id' => $id,
                ':task_name' => $data['taskName'],
                ':assigned_user' => $data['assignedUser'],
                ':task_description' => $data['taskDescription'],
                ':priority' => $data['priority'],
                ':progress' => $data['progress'],
                ':dependencies' => $dependencies
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error updating task: " . $e->getMessage());
        }
    }

    private function hasCircularDependency($taskId, $dependencies, $visited = []) {
        if (empty($dependencies)) {
            return false;
        }

        if (in_array($taskId, $dependencies)) {
            return true;
        }

        foreach ($dependencies as $depId) {
            if (in_array($depId, $visited)) {
                continue;
            }
            
            $visited[] = $depId;
            $depTask = $this->getTaskById($depId);
            
            if ($depTask && !empty($depTask['dependencies'])) {
                $depDependencies = json_decode($depTask['dependencies'], true);
                if ($this->hasCircularDependency($taskId, $depDependencies, $visited)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    public function getDependencyNames($taskId) {
        try {
            $task = $this->getTaskById($taskId);
            if (!$task || empty($task['dependencies'])) {
                return [];
            }

            $dependencies = json_decode($task['dependencies'], true);
            $names = [];

            foreach ($dependencies as $depId) {
                $depTask = $this->getTaskById($depId);
                if ($depTask) {
                    $names[] = $depTask['task_name'];
                }
            }

            return $names;
        } catch (PDOException $e) {
            throw new Exception("Error fetching dependency names: " . $e->getMessage());
        }
    }

    public function updateProgress($taskId, $newProgress) {
        try {
            $stmt = $this->db->prepare('UPDATE tasks SET progress = :progress WHERE id = :id');
            return $stmt->execute([
                ':id' => $taskId,
                ':progress' => $newProgress
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error updating progress: " . $e->getMessage());
        }
    }
}
?> 