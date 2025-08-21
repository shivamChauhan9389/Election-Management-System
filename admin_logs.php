<?php
session_start();
require_once 'db.php';
require_once 'logger.php';

// Load configuration
$config = [];
if (file_exists(__DIR__ . '/config_local.php')) {
    $config = require __DIR__ . '/config_local.php';
} else {
    $config = require __DIR__ . '/config.example.php';
}

// Simple admin authentication (you can enhance this)
$admin_password = $config['admin']['password'] ?? 'change_this_admin_password';
$is_authenticated = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === $admin_password) {
        $_SESSION['admin_authenticated'] = true;
        $is_authenticated = true;
        log_action("Admin login successful", null, "ADMIN_LOGIN", "SUCCESS");
    } else {
        log_action("Admin login failed - incorrect password", null, "ADMIN_LOGIN", "FAILURE");
    }
}

if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
    $is_authenticated = true;
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_authenticated']);
    header("Location: admin_logs.php");
    exit;
}

// Get filter parameters
$action_type = $_GET['action_type'] ?? '';
$status = $_GET['status'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$ip_address = $_GET['ip_address'] ?? '';
$limit = $_GET['limit'] ?? 100;

// Build query
$where_conditions = [];
$params = [];
$types = "";

if (!empty($action_type)) {
    $where_conditions[] = "action_type = ?";
    $params[] = $action_type;
    $types .= "s";
}

if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($user_id)) {
    $where_conditions[] = "user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if (!empty($ip_address)) {
    $where_conditions[] = "ip_address = ?";
    $params[] = $ip_address;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get logs
$sql = "SELECT * FROM system_logs $where_clause ORDER BY timestamp DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$logs = [];
if ($is_authenticated) {
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Log the error but don't break the page
        error_log("Admin logs query error: " . $e->getMessage());
        $logs = [];
    }
}

// Get system stats
try {
    $stats = get_system_stats();
} catch (Exception $e) {
    $stats = [
        'total_logs' => 0,
        'last_24h' => 0,
        'by_status' => ['FAILURE' => 0, 'SUCCESS' => 0],
        'by_action' => []
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - System Logs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .filters {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            align-items: end;
        }
        .filters input, .filters select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filters button {
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filters button:hover {
            background: #0056b3;
        }
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .logs-table th, .logs-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .logs-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .status-success { color: #28a745; }
        .status-failure { color: #dc3545; }
        .status-warning { color: #ffc107; }
        .status-info { color: #17a2b8; }
        .login-form {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .login-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .login-form button {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .logout-btn {
            padding: 8px 15px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .logout-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <?php if (!$is_authenticated): ?>
        <div class="login-form">
            <h2>Admin Login</h2>
            <form method="POST">
                <input type="password" name="admin_password" placeholder="Admin Password" required>
                <button type="submit">Login</button>
            </form>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="header">
                <h1>System Logs Dashboard</h1>
                <a href="?logout=1" class="logout-btn">Logout</a>
            </div>

            <!-- System Stats -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_logs'] ?? 0; ?></div>
                    <div>Total Logs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['last_24h'] ?? 0; ?></div>
                    <div>Last 24 Hours</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['by_status']['FAILURE'] ?? 0; ?></div>
                    <div>Failed Actions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['by_status']['SUCCESS'] ?? 0; ?></div>
                    <div>Successful Actions</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET">
                    <input type="text" name="action_type" placeholder="Action Type" value="<?php echo htmlspecialchars($action_type); ?>">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="SUCCESS" <?php echo $status === 'SUCCESS' ? 'selected' : ''; ?>>Success</option>
                        <option value="FAILURE" <?php echo $status === 'FAILURE' ? 'selected' : ''; ?>>Failure</option>
                        <option value="WARNING" <?php echo $status === 'WARNING' ? 'selected' : ''; ?>>Warning</option>
                        <option value="INFO" <?php echo $status === 'INFO' ? 'selected' : ''; ?>>Info</option>
                    </select>
                    <input type="text" name="user_id" placeholder="User ID" value="<?php echo htmlspecialchars($user_id); ?>">
                    <input type="text" name="ip_address" placeholder="IP Address" value="<?php echo htmlspecialchars($ip_address); ?>">
                    <select name="limit">
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 Records</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 Records</option>
                        <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200 Records</option>
                        <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500 Records</option>
                    </select>
                    <button type="submit">Filter</button>
                </form>
            </div>

            <!-- Logs Table -->
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>IP Address</th>
                        <th>User ID</th>
                        <th>Action Type</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Session ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td><?php echo htmlspecialchars($log['user_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                            <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                            <td class="status-<?php echo strtolower($log['status']); ?>">
                                <?php echo htmlspecialchars($log['status']); ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($log['session_id'], 0, 10) . '...'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($logs)): ?>
                <p style="text-align: center; color: #666; margin-top: 20px;">No logs found matching the criteria.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
