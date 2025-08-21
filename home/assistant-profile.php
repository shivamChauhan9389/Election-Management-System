<?php
session_start();
require_once '../logger.php';
require_once '../db.php';
require_once '../role_manager.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has access to assistant profile (assistant role)
require_role('assistant', 'home/home.php');

log_action("Accessed Assistant profile form", $_SESSION['id'] ?? null);

// Fetch existing assistant details if they exist
$existing_assistant = [];
if (isset($_SESSION['id'])) {
    $user_id = $_SESSION['id'];
    $sql = "SELECT * FROM user_details WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $existing_assistant = $result->fetch_assoc();
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistant Profile - Uttarakhand Election Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Theme variables are inherited from parent home.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-container {
            background: rgba(0,0,0,0.1);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-white);
            color: var(--text-primary);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(56, 81, 133, 0.1);
        }

        .form-group input.error,
        .form-group select.error,
        .form-group textarea.error {
            border-color: var(--error-color);
        }

        .error-message {
            color: var(--error-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

        .form-actions {
            text-align: center;
            margin-top: 2rem;
        }

        .form-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-submit:hover {
            background: var(--primary-hover);
        }

        .form-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .message-container {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            display: none;
        }

        .message-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .message-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .form-group input[readonly],
        .form-group select[readonly],
        .form-group textarea[readonly] {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            cursor: not-allowed;
            opacity: 0.8;
        }

        /* New styles for summary overview */
        .summary-overview-container {
            background: rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .summary-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .summary-subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .summary-card {
            background: var(--bg-white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .summary-card .icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
        }

        .summary-card .label {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
        }

        .summary-card .sub-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .edit-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .edit-button:hover {
            background: var(--primary-hover);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($existing_assistant): ?>
        <!-- Show existing assistant details summary -->
        <div class="summary-overview-container fade-in">
            <h2 class="summary-title">Assistant Profile Overview</h2>
            <p class="summary-subtitle">Your assistant profile details are already registered. You can update them below.</p>
            
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-user-tie"></i></div>
                    <div class="label"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></div>
                    <div class="sub-label"><?php echo htmlspecialchars($existing_assistant['post_position'] ?? ''); ?></div>
                </div>
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-building"></i></div>
                    <div class="label"><?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($existing_assistant['department'] ?? ''))); ?></div>
                    <div class="sub-label"><?php 
                        if (($existing_assistant['address_type'] ?? '') === 'urban') {
                            echo 'Urban Area';
                        } else {
                            echo 'Rural Area';
                        }
                    ?></div>
                </div>
                <div class="summary-card">
                    <div class="icon"><i class="fas fa-envelope"></i></div>
                    <div class="label"><?php echo htmlspecialchars($existing_assistant['email'] ?? ''); ?></div>
                    <div class="sub-label"><?php 
                        $phone = $_SESSION['phn'] ?? '';
                        // Remove +91 prefix for display
                        echo htmlspecialchars(preg_replace('/^\+91/', '', $phone));
                    ?></div>
                </div>
            </div>
            
            <button id="editAssistantBtn" class="edit-button"><i class="fas fa-edit"></i> Edit Details</button>
        </div>

        <!-- Form container (initially hidden) -->
        <div id="assistantFormContainer" class="form-container fade-in" style="display: none; opacity: 0; visibility: hidden;">
        <?php else: ?>
        <!-- Form container (visible by default) -->
        <div id="assistantFormContainer" class="form-container fade-in">
        <?php endif; ?>
            <h1 class="form-title">Assistant Profile Form</h1>
            <p class="form-subtitle">Please fill in your complete details as Assistant</p>
            
            <form id="assistantProfileForm" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">Full Name *</label>
                        <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" <?php echo $existing_assistant ? 'readonly' : ''; ?> required>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" value="<?php 
                            $phone = $_SESSION['phn'] ?? '';
                            echo htmlspecialchars(preg_replace('/^\+91/', '', $phone));
                        ?>" placeholder="9876543210" oninput="validatePhoneInput(this)" <?php echo $existing_assistant ? 'readonly' : ''; ?> required>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($existing_assistant['email'] ?? ''); ?>">
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($existing_assistant['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($existing_assistant['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($existing_assistant['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dateOfBirth">Date of Birth *</label>
                        <input type="date" id="dateOfBirth" name="dateOfBirth" value="<?php echo htmlspecialchars($existing_assistant['date_of_birth'] ?? ''); ?>" required>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="postSearch">Post/Position *</label>
                        <select id="postSearch" name="post" required>
                            <option value="">Select Post/Position</option>
                            <option value="Assistant" <?php echo ($existing_assistant['post_position'] ?? '') === 'Assistant' ? 'selected' : ''; ?>>Assistant</option>
                            <option value="Senior Assistant" <?php echo ($existing_assistant['post_position'] ?? '') === 'Senior Assistant' ? 'selected' : ''; ?>>Senior Assistant</option>
                            <option value="Junior Assistant" <?php echo ($existing_assistant['post_position'] ?? '') === 'Junior Assistant' ? 'selected' : ''; ?>>Junior Assistant</option>
                            <option value="Field Assistant" <?php echo ($existing_assistant['post_position'] ?? '') === 'Field Assistant' ? 'selected' : ''; ?>>Field Assistant</option>
                            <option value="Office Assistant" <?php echo ($existing_assistant['post_position'] ?? '') === 'Office Assistant' ? 'selected' : ''; ?>>Office Assistant</option>
                            <option value="Other" <?php echo ($existing_assistant['post_position'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="law-order" <?php echo ($existing_assistant['department'] ?? '') === 'law-order' ? 'selected' : ''; ?>>Law & Order</option>
                            <option value="cid" <?php echo ($existing_assistant['department'] ?? '') === 'cid' ? 'selected' : ''; ?>>CID</option>
                            <option value="traffic" <?php echo ($existing_assistant['department'] ?? '') === 'traffic' ? 'selected' : ''; ?>>Traffic</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="addressType">Address Type *</label>
                        <select id="addressType" name="addressType" required>
                            <option value="">Select Address Type</option>
                            <option value="urban" <?php echo ($existing_assistant['address_type'] ?? '') === 'urban' ? 'selected' : ''; ?>>Urban</option>
                            <option value="rural" <?php echo ($existing_assistant['address_type'] ?? '') === 'rural' ? 'selected' : ''; ?>>Rural</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="form-submit">
                        <i class="fas fa-save"></i> Submit Assistant Details
                    </button>
                    <?php if ($existing_assistant): ?>
                    <button type="button" id="backToSummaryBtn" class="form-submit" style="background: var(--text-secondary); margin-top: 1rem;">
                        <i class="fas fa-arrow-left"></i> Back to Summary
                    </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Success/Error Message Container -->
            <div id="messageContainer" class="message-container"></div>
        </div>
    </div>

    <script>
        console.log('Assistant profile page loaded - events handled by home.php');
        
        // Phone number input validation
        function validatePhoneInput(input) {
            // Remove any non-digit characters
            let value = input.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            // Check if first digit is valid (6, 7, 8, or 9)
            if (value.length > 0 && !/^[6789]/.test(value)) {
                value = value.substring(1);
            }
            
            input.value = value;
        }
        
        // Edit and Back functionality (handled by home.php)
        // All event listeners are now handled by the parent home.php file
    </script>
</body>
</html>
