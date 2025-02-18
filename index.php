<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';
require_once 'TaskManager.php';

$taskManager = new TaskManager($db);
$error = null;
$success = null;

// Define user mapping
$users = [
    'user1' => 'Rohit',
    'user2' => 'Ridhul',
    'user3' => 'Sachin',
    'user4' => 'Sai'
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    if ($taskManager->addTask($_POST)) {
                        $success = "Task added successfully!";
                        header("Location: index.php");
                        exit();
                    }
                    break;

                case 'delete':
                    if ($taskManager->deleteTask($_POST['task_id'])) {
                        $success = "Task deleted successfully!";
                        header("Location: index.php");
                        exit();
                    }
                    break;

                case 'update':
                    if ($taskManager->updateTask($_POST['task_id'], $_POST)) {
                        $success = "Task updated successfully!";
                        header("Location: index.php");
                        exit();
                    }
                    break;

                case 'updateProgress':
                    if ($taskManager->updateProgress($_POST['task_id'], $_POST['progress'])) {
                        $success = "Progress updated successfully!";
                        header("Location: index.php");
                        exit();
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch all tasks
try {
    $tasks = $taskManager->getAllTasks();
} catch (Exception $e) {
    $error = $e->getMessage();
    $tasks = [];
}

// Pagination settings
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Build the WHERE clause based on filters
$where_conditions = [];
$params = [];

if (!empty($_GET['progress'])) {
    $where_conditions[] = 'progress = :progress';
    $params[':progress'] = $_GET['progress'];
}

if (!empty($_GET['assigned_user'])) {
    $where_conditions[] = 'assigned_user = :assigned_user';
    $params[':assigned_user'] = $_GET['assigned_user'];
}

if (!empty($_GET['priority'])) {
    $where_conditions[] = 'priority = :priority';
    $params[':priority'] = $_GET['priority'];
}

if (!empty($_GET['search'])) {
    $where_conditions[] = '(task_name LIKE :search OR task_description LIKE :search)';
    $params[':search'] = '%' . $_GET['search'] . '%';
}

// Construct the final WHERE clause
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total number of records for pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM tasks $where_clause");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $items_per_page);

// Fetch filtered and paginated tasks
$query = "SELECT * FROM tasks $where_clause ORDER BY created_at DESC LIMIT :offset, :items_per_page";
$stmt = $db->prepare($query);

// Bind all filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add this after your existing SELECT query to fetch all tasks for dependencies dropdown
$all_tasks_stmt = $db->query("SELECT id, task_name FROM tasks ORDER BY task_name");
$all_tasks = $all_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
              * {
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #f6f9fc 0%, #eef2f7 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }

        h1 {
            font-size: 28px;
            margin-bottom: 24px;
            color: #1a1f36;
            font-weight: 600;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .search-box {
            padding: 12px 16px;
            border: 1px solid #e5e9f2;
            border-radius: 8px;
            width: 240px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 12px center;
            padding-left: 40px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-box:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .filter-select {
            padding: 10px 16px;
            border: 1px solid #e5e9f2;
            border-radius: 8px;
            background-color: white;
            font-size: 14px;
            color: #4b5563;
            cursor: pointer;
            min-width: 140px;
        }

        .filter-select:hover {
            border-color: #6366f1;
        }

        .add-new {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2);
        }

        .add-new:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(99, 102, 241, 0.3);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 24px;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e5e9f2;
        }

        th {
            color: #6b7280;
            font-weight: 500;
            font-size: 14px;
            background-color: #f8fafc;
        }

        td {
            font-size: 14px;
            color: #1f2937;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        .status {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        .completed {
            background-color: #dcfce7;
            color: #15803d;
        }

        .pending {
            background-color: #e0e7ff;
            color: #4338ca;
        }

        .rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .priority {
            font-weight: 500;
        }

        .priority-high {
            color: #dc2626;
        }

        .priority-medium {
            color: #d97706;
        }

        .priority-low {
            color: #059669;
        }

        .action-column {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            border: none;
            background: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            color: #6b7280;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background-color: #f3f4f6;
            color: #4b5563;
        }

        .delete-btn:hover {
            color: #dc2626;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #e5e9f2;
        }

        .page-info {
            color: #6b7280;
            font-size: 14px;
        }

        .page-numbers {
            display: flex;
            gap: 4px;
        }

        .page-numbers button {
            min-width: 36px;
            height: 36px;
            border: 1px solid #e5e9f2;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            color: #4b5563;
            transition: all 0.2s;
        }

        .page-numbers button:hover {
            border-color: #6366f1;
            color: #6366f1;
        }

        .page-numbers button.active {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 90%;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4b5563;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e9f2;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-submit {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
        }

        .form-submit:hover {
            box-shadow: 0 4px 8px rgba(99, 102, 241, 0.3);
        }

        select[multiple] {
            height: 100px;
            width: 100%;
            padding: 8px;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        <?php include 'styles.css'; ?>
        .error-message {
            color: red;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #fff3f3;
            border-radius: 4px;
        }
        .error {
            color: red;
            padding: 10px;
            margin: 10px 0;
            background-color: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 4px;
        }
        .success {
            color: green;
            padding: 10px;
            margin: 10px 0;
            background-color: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 4px;
        }
        form {
            margin-bottom: 20px;
        }
        form div {
            margin-bottom: 10px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }
        button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .status-select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .status-select.pending {
            background-color: #e0e7ff;
            color: #4338ca;
        }

        .status-select.completed {
            background-color: #dcfce7;
            color: #15803d;
        }

        .status-select.rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .delete-btn {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Task Management</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="header">
            <form method="GET" class="search-form" style="margin: 0;">
                <input type="text" name="search" class="search-box" placeholder="Search tasks..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </form>
            <button class="add-new" id="addNewTask">
                <i class="fas fa-plus"></i>
                Add New Task
            </button>
        </div>

        <div class="filters">
            <form method="GET" id="filterForm" style="display: flex; gap: 12px;">
                <select name="progress" class="filter-select" onchange="this.form.submit()">
                    <option value="">Filter by Progress</option>
                    <option value="completed" <?php echo (isset($_GET['progress']) && $_GET['progress'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="pending" <?php echo (isset($_GET['progress']) && $_GET['progress'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="rejected" <?php echo (isset($_GET['progress']) && $_GET['progress'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <select name="assigned_user" class="filter-select" onchange="this.form.submit()">
                    <option value="">Filter by Assigned User</option>
                    <option value="user1" <?php echo (isset($_GET['assigned_user']) && $_GET['assigned_user'] === 'user1') ? 'selected' : ''; ?>>Rohit</option>
                    <option value="user2" <?php echo (isset($_GET['assigned_user']) && $_GET['assigned_user'] === 'user2') ? 'selected' : ''; ?>>Ridhul</option>
                    <option value="user3" <?php echo (isset($_GET['assigned_user']) && $_GET['assigned_user'] === 'user3') ? 'selected' : ''; ?>>Sachin</option>
                    <option value="user4" <?php echo (isset($_GET['assigned_user']) && $_GET['assigned_user'] === 'user4') ? 'selected' : ''; ?>>Sai</option>
                </select>
                <select name="priority" class="filter-select" onchange="this.form.submit()">
                    <option value="">Filter by Priority</option>
                    <option value="high" <?php echo (isset($_GET['priority']) && $_GET['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo (isset($_GET['priority']) && $_GET['priority'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo (isset($_GET['priority']) && $_GET['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                </select>
                <?php if (!empty($_GET)): ?>
                    <button type="button" class="filter-select" onclick="window.location.href='index.php'">Clear Filters</button>
                <?php endif; ?>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Task Name</th>
                    <th>Assigned User</th>
                    <th>Task Description</th>
                    <th>Priority</th>
                    <th>Progress</th>
                    <th>Dependencies</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                foreach ($tasks as $task) : 
                    $task_dependencies = json_decode($task['dependencies'] ?? '[]', true);
                ?>
                    <tr>
                        <td><?php echo $counter++; ?>.</td>
                        <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                        <td><?php echo htmlspecialchars($users[$task['assigned_user']] ?? $task['assigned_user']); ?></td>
                        <td><?php echo htmlspecialchars($task['task_description']); ?></td>
                        <td><span class="priority priority-<?php echo strtolower($task['priority']); ?>"><?php echo ucfirst($task['priority']); ?></span></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="updateProgress">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <select name="progress" onchange="this.form.submit()" class="status-select <?php echo strtolower($task['progress']); ?>">
                                    <option value="pending" <?php echo $task['progress'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo $task['progress'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" <?php echo $task['progress'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <?php
                            if (!empty($task_dependencies)) {
                                $dependency_names = [];
                                foreach ($task_dependencies as $dep_id) {
                                    foreach ($all_tasks as $t) {
                                        if ($t['id'] == $dep_id) {
                                            $dependency_names[] = $t['task_name'];
                                            break;
                                        }
                                    }
                                }
                                echo htmlspecialchars(implode(', ', $dependency_names));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="action-column">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <button type="submit" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this task?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <div class="page-info">
                Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $items_per_page, $total_records); ?> 
                out of <?php echo $total_records; ?> entries
            </div>
            <div class="page-numbers">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?><?php echo isset($_GET['progress']) ? '&progress=' . htmlspecialchars($_GET['progress']) : ''; ?><?php echo isset($_GET['assigned_user']) ? '&assigned_user=' . htmlspecialchars($_GET['assigned_user']) : ''; ?><?php echo isset($_GET['priority']) ? '&priority=' . htmlspecialchars($_GET['priority']) : ''; ?>">
                        <button><i class="fas fa-chevron-left"></i></button>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?><?php echo isset($_GET['progress']) ? '&progress=' . htmlspecialchars($_GET['progress']) : ''; ?><?php echo isset($_GET['assigned_user']) ? '&assigned_user=' . htmlspecialchars($_GET['assigned_user']) : ''; ?><?php echo isset($_GET['priority']) ? '&priority=' . htmlspecialchars($_GET['priority']) : ''; ?>">
                        <button <?php echo $i === $current_page ? 'class="active"' : ''; ?>><?php echo $i; ?></button>
                    </a>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; ?><?php echo isset($_GET['progress']) ? '&progress=' . htmlspecialchars($_GET['progress']) : ''; ?><?php echo isset($_GET['assigned_user']) ? '&assigned_user=' . htmlspecialchars($_GET['assigned_user']) : ''; ?><?php echo isset($_GET['priority']) ? '&priority=' . htmlspecialchars($_GET['priority']) : ''; ?>">
                        <button><i class="fas fa-chevron-right"></i></button>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for adding new task -->
    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add New Task</h2>
            <form id="addTaskForm" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="taskName">Task Name</label>
                    <input type="text" id="taskName" name="taskName" required>
                </div>
                <div class="form-group">
                    <label for="assignedUser">Assigned User</label>
                    <select id="assignedUser" name="assignedUser" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $value => $name): ?>
                            <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="taskDescription">Task Description</label>
                    <textarea id="taskDescription" name="taskDescription" required></textarea>
                </div>
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" required>
                        <option value="">Select Priority</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="progress">Progress</label>
                    <select id="progress" name="progress" required>
                        <option value="">Select Progress</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dependencies">Dependencies</label>
                    <select id="dependencies" name="dependencies[]" multiple class="form-control">
                        <?php foreach ($all_tasks as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['task_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple tasks</small>
                </div>
                <button type="submit" class="form-submit">Add Task</button>
            </form>
        </div>
    </div>

    <script>
        // Get the modal
        var modal = document.getElementById("addTaskModal");
        var btn = document.getElementById("addNewTask");
        var span = document.getElementsByClassName("close")[0];

        btn.onclick = function() {
            modal.style.display = "block";
        }

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        document.getElementById('addTaskForm').addEventListener('submit', function(e) {
            // Remove any previous error messages
            const existingError = document.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }

            // Basic form validation
            const requiredFields = ['taskName', 'assignedUser', 'taskDescription', 'priority', 'progress'];
            let isValid = true;

            requiredFields.forEach(field => {
                const element = document.getElementById(field);
                if (!element.value) {
                    isValid = false;
                    element.style.borderColor = 'red';
                } else {
                    element.style.borderColor = '';
                }
            });

            if (!isValid) {
                e.preventDefault();
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.color = 'red';
                errorDiv.textContent = 'Please fill in all required fields';
                this.insertBefore(errorDiv, this.firstChild);
            }
        });

        // Clear form when modal is closed
        document.querySelector('.close').addEventListener('click', function() {
            document.getElementById('addTaskForm').reset();
        });
    </script>
</body>
</html> 