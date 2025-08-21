<?php
session_start();
require_once 'db.php';
require_once 'role_helper.php';
require_once 'logger.php';

// Check if user is authenticated and has multiple roles
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_username'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['temp_user_id'];
$username = $_SESSION['temp_username'];

// Get available roles for the user
$available_roles = get_available_roles_for_selection($user_id);

if (count($available_roles) <= 1) {
    // User has only one role, redirect directly
    $_SESSION['loggedin'] = true;
    $_SESSION['id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $available_roles[0]['value'];
    
    // Get user details
    $sql = "SELECT fullname, mobile FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $_SESSION['name'] = $user['fullname'];
    $_SESSION['phn'] = $user['mobile'];
    
    // Clear temporary session data
    unset($_SESSION['temp_user_id']);
    unset($_SESSION['temp_username']);
    
    log_action("Role selection: Auto-selected single role: " . $available_roles[0]['value'], $user_id);
    header("Location: home/home.php");
    exit;
}

// Handle role selection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selected_role'])) {
    $selected_role = $_POST['selected_role'];
    
    // Verify the selected role is valid for this user
    $valid_roles = array_column($available_roles, 'value');
    if (in_array($selected_role, $valid_roles)) {
        // Set session with selected role
        $_SESSION['loggedin'] = true;
        $_SESSION['id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $selected_role;
        
        // Get user details
        $sql = "SELECT fullname, mobile FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        $_SESSION['name'] = $user['fullname'];
        $_SESSION['phn'] = $user['mobile'];
        
        // Clear temporary session data
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_username']);
        
        log_action("Role selection: User selected role: " . $selected_role, $user_id);
        header("Location: home/home.php");
        exit;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Role - Uttarakhand Election Portal</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #000 0%, #385185 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .role-selection-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 700;
            color: #385185;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .welcome-text {
            color: #333;
            margin-bottom: 30px;
            font-size: 18px;
            font-weight: 500;
        }
        
        .role-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .role-option {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }
        
        .role-option:hover {
            border-color: #385185;
            background: #e3f2fd;
            transform: translateY(-2px);
        }
        
        .role-option.selected {
            border-color: #385185;
            background: #385185;
            color: white;
        }
        
        .role-option.selected .role-description {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .role-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .role-description {
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }
        
        .role-icon {
            font-size: 20px;
            color: #385185;
        }
        
        .role-option.selected .role-icon {
            color: white;
        }
        
        .continue-btn {
            background: #385185;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .continue-btn:hover {
            background: #22396d;
            transform: translateY(-2px);
        }
        
        .continue-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-link {
            margin-top: 20px;
            display: block;
            color: #385185;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .role-selection-container {
                padding: 30px 20px;
            }
            
            .logo {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="role-selection-container">
        <div class="logo">
            <i class="fas fa-vote-yea"></i> Uttarakhand Election Portal
        </div>
        <div class="subtitle">Welcome back, <?php echo htmlspecialchars($username); ?></div>
        <div class="welcome-text">Please select the role you want to login with:</div>
        
        <form id="roleForm" method="POST">
            <div class="role-options">
                <?php foreach ($available_roles as $role): ?>
                <div class="role-option" data-role="<?php echo htmlspecialchars($role['value']); ?>">
                    <div class="role-title">
                        <i class="fas fa-<?php echo get_role_icon($role['value']); ?> role-icon"></i>
                        <?php echo htmlspecialchars($role['display']); ?>
                    </div>
                    <div class="role-description">
                        <?php echo get_role_description($role['value']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <input type="hidden" name="selected_role" id="selectedRole" value="">
            <button type="submit" class="continue-btn" id="continueBtn" disabled>
                Continue with Selected Role
            </button>
        </form>
        
        <a href="logout.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>

    <script>
        // Role selection functionality
        const roleOptions = document.querySelectorAll('.role-option');
        const selectedRoleInput = document.getElementById('selectedRole');
        const continueBtn = document.getElementById('continueBtn');
        
        roleOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove previous selection
                roleOptions.forEach(opt => opt.classList.remove('selected'));
                
                // Select current option
                this.classList.add('selected');
                
                // Set hidden input value
                const role = this.dataset.role;
                selectedRoleInput.value = role;
                
                // Enable continue button
                continueBtn.disabled = false;
            });
        });
        
        // Form submission
        document.getElementById('roleForm').addEventListener('submit', function(e) {
            if (!selectedRoleInput.value) {
                e.preventDefault();
                alert('Please select a role to continue.');
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions for role display
function get_role_icon($role) {
    $icons = [
        'admin' => 'user-shield',
        'hod' => 'user-tie',
        'employee' => 'user',
        'assistant' => 'user-graduate'
    ];
    return $icons[$role] ?? 'user';
}

function get_role_description($role) {
    $descriptions = [
        'admin' => 'Full system access with user management capabilities',
        'hod' => 'Department head with employee management access',
        'employee' => 'Standard employee with basic form access',
        'assistant' => 'Assistant with limited form access'
    ];
    return $descriptions[$role] ?? 'No description available';
}
?>

