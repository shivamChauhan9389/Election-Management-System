<?php

// Set the default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

/**
 * Comprehensive database logging function
 * 
 * @param string $action_description - Description of the action
 * @param int|null $user_id - User ID (optional)
 * @param string $action_type - Type of action (LOGIN, REGISTRATION, PASSWORD_RESET, etc.)
 * @param string $status - Status of the action (SUCCESS, FAILURE, WARNING, INFO)
 * @param array $additional_data - Additional data to store as JSON
 */
function log_action($action_description, $user_id = null, $action_type = 'INFO', $status = 'INFO', $additional_data = []) {
    try {
        // Use global database connection
        global $conn;
        
        // Check if database connection exists
        if (!isset($conn)) {
            require_once 'db.php';
        }
        
        // Get comprehensive request information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
        $request_method = $_SERVER['REQUEST_METHOD'] ?? 'N/A';
        $request_uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
        $session_id = session_id() ?? 'N/A';
        
        // Prepare additional data
        $json_data = !empty($additional_data) ? json_encode($additional_data) : null;
        
        // Prepare and execute the insert statement
        $sql = "INSERT INTO system_logs (ip_address, user_id, action_type, action_description, status, session_id, user_agent, request_method, request_uri, additional_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sissssssss", 
                $ip_address, 
                $user_id, 
                $action_type, 
                $action_description, 
                $status, 
                $session_id, 
                $user_agent, 
                $request_method, 
                $request_uri, 
                $json_data
            );
            
            $stmt->execute();
            $stmt->close();
        } else {
            // Fallback to file logging if database fails
            error_log("Database logging failed: " . $conn->error . " - Action: " . $action_description, 3, 'logs.log');
        }
        
    } catch (Exception $e) {
        // Fallback to file logging if database connection fails
        $timestamp = date('Y-m-d H:i:s');
        $fallback_message = sprintf("[%s] [IP: %s] [User ID: %s] %s - DB Error: %s\n", 
            $timestamp, 
            $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            $user_id ?? 'N/A',
            $action_description,
            $e->getMessage()
        );
        error_log($fallback_message, 3, 'logs.log');
    }
}

/**
 * Get recent logs for a specific user
 * 
 * @param int $user_id - User ID
 * @param int $limit - Number of logs to retrieve (default 50)
 * @return array - Array of log entries
 */
function get_user_logs($user_id, $limit = 50) {
    try {
        global $conn;
        if (!isset($conn)) {
            require_once 'db.php';
        }
        
        $sql = "SELECT * FROM system_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $stmt->close();
        return $logs;
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get failed login attempts for an IP address
 * 
 * @param string $ip_address - IP address
 * @param int $hours - Hours to look back (default 1)
 * @return int - Number of failed attempts
 */
function get_failed_login_attempts($ip_address, $hours = 1) {
    try {
        global $conn;
        if (!isset($conn)) {
            require_once 'db.php';
        }
        
        $sql = "SELECT COUNT(*) as count FROM system_logs 
                WHERE ip_address = ? 
                AND action_type = 'LOGIN' 
                AND status = 'FAILURE' 
                AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $ip_address, $hours);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stmt->close();
        return $row['count'] ?? 0;
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Clean old logs (older than specified days)
 * 
 * @param int $days - Days to keep (default 90)
 * @return int - Number of deleted records
 */
function clean_old_logs($days = 90) {
    try {
        global $conn;
        if (!isset($conn)) {
            require_once 'db.php';
        }
        
        $sql = "DELETE FROM system_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $deleted_count = $stmt->affected_rows;
        $stmt->close();
        
        return $deleted_count;
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get system statistics
 * 
 * @return array - System statistics
 */
function get_system_stats() {
    try {
        global $conn;
        if (!isset($conn)) {
            require_once 'db.php';
        }
        
        $stats = [];
        
        // Total logs
        $sql = "SELECT COUNT(*) as total FROM system_logs";
        $result = $conn->query($sql);
        $stats['total_logs'] = $result->fetch_assoc()['total'];
        
        // Logs by status
        $sql = "SELECT status, COUNT(*) as count FROM system_logs GROUP BY status";
        $result = $conn->query($sql);
        $stats['by_status'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // Logs by action type
        $sql = "SELECT action_type, COUNT(*) as count FROM system_logs GROUP BY action_type ORDER BY count DESC";
        $result = $conn->query($sql);
        $stats['by_action'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_action'][$row['action_type']] = $row['count'];
        }
        
        // Recent activity (last 24 hours)
        $sql = "SELECT COUNT(*) as count FROM system_logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = $conn->query($sql);
        $stats['last_24h'] = $result->fetch_assoc()['count'];
        
        return $stats;
        
    } catch (Exception $e) {
        return [];
    }
}

?>
