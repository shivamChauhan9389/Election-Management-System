<?php
session_start();
require_once '../logger.php';
require_once '../db.php';
require_once '../role_manager.php';

// Simple test to see if PHP is working
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has access to HOD details
require_role('hod', 'home/home.php');

log_action("Accessed HOD details form", $_SESSION['id'] ?? null);

// Fetch existing HOD details if they exist
$existing_hod = [];
if (isset($_SESSION['id'])) {
    $user_id = $_SESSION['id'];
    $sql = "SELECT * FROM user_details WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $existing_hod = $result->fetch_assoc();
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
    <title>HOD Details - Uttarakhand Election Portal</title>
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

        .summary-overview-container {
            background: rgba(0,0,0,0.1);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .summary-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-align: center;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-item {
            background: var(--bg-white);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .summary-label {
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .summary-value {
            color: var(--text-primary);
            font-size: 1rem;
        }

        .edit-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .edit-button:hover {
            background: var(--primary-hover);
        }

        .fade-in {
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .spinner {
            border: 3px solid rgba(56, 81, 133, 0.1);
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($existing_hod): ?>
        <!-- Show existing HOD details summary -->
        <div class="summary-overview-container fade-in">
            <h2 class="summary-title">HOD Details Summary</h2>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Full Name</div>
                    <div class="summary-value"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Phone Number</div>
                    <div class="summary-value"><?php echo htmlspecialchars($_SESSION['phn'] ?? ''); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Email</div>
                    <div class="summary-value"><?php echo htmlspecialchars($existing_hod['email'] ?? ''); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Gender</div>
                    <div class="summary-value"><?php echo ucfirst(htmlspecialchars($existing_hod['gender'] ?? '')); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Date of Birth</div>
                    <div class="summary-value"><?php echo htmlspecialchars($existing_hod['date_of_birth'] ?? ''); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Post/Position</div>
                    <div class="summary-value"><?php echo htmlspecialchars($existing_hod['post_position'] ?? ''); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Department</div>
                    <div class="summary-value"><?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($existing_hod['department'] ?? ''))); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Address Type</div>
                    <div class="summary-value"><?php echo ucfirst(htmlspecialchars($existing_hod['address_type'] ?? '')); ?></div>
                </div>
                <?php if (($existing_hod['address_type'] ?? '') === 'urban'): ?>
                <div class="summary-item">
                    <div class="summary-label">City</div>
                    <div class="summary-value"><?php echo ucfirst(htmlspecialchars($existing_hod['urban_city'] ?? '')); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Ward</div>
                    <div class="summary-value"><?php echo ucfirst(htmlspecialchars($existing_hod['urban_ward'] ?? '')); ?></div>
                </div>
                <?php else: ?>
                <div class="summary-item">
                    <div class="summary-label">District</div>
                    <div class="summary-value"><?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($existing_hod['rural_district'] ?? ''))); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Tehsil</div>
                    <div class="summary-value"><?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($existing_hod['rural_tehsil'] ?? ''))); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <button id="editHodBtn" class="edit-button"><i class="fas fa-edit"></i> Edit Details</button>
        </div>

        <!-- Form container (initially hidden) -->
        <div id="hodFormContainer" class="form-container fade-in" style="display: none; opacity: 0; visibility: hidden;">
        <?php else: ?>
        <!-- Form container (visible by default) -->
        <div id="hodFormContainer" class="form-container fade-in">
        <?php endif; ?>
            <h1 class="form-title">HOD Details Form</h1>
            <p class="form-subtitle">Please fill in your complete details as Head of Department</p>
            
            <form id="hodForm" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">Full Name *</label>
                        <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($existing_hod['full_name'] ?? ''); ?>" required>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($existing_hod['phone'] ?? ''); ?>" required>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($existing_hod['email'] ?? ''); ?>">
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($existing_hod['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($existing_hod['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($existing_hod['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dateOfBirth">Date of Birth *</label>
                        <input type="date" id="dateOfBirth" name="dateOfBirth" value="<?php echo htmlspecialchars($existing_hod['date_of_birth'] ?? ''); ?>" required>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="postSearch">Post/Position *</label>
                        <input type="text" id="postSearch" name="postSearch" value="<?php echo htmlspecialchars($existing_hod['post_position'] ?? ''); ?>" required>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="law-order" <?php echo ($existing_hod['department'] ?? '') === 'law-order' ? 'selected' : ''; ?>>Law & Order</option>
                            <option value="cid" <?php echo ($existing_hod['department'] ?? '') === 'cid' ? 'selected' : ''; ?>>CID</option>
                            <option value="traffic" <?php echo ($existing_hod['department'] ?? '') === 'traffic' ? 'selected' : ''; ?>>Traffic</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="addressType">Address Type *</label>
                        <select id="addressType" name="addressType" required>
                            <option value="">Select Address Type</option>
                            <option value="urban" <?php echo ($existing_hod['address_type'] ?? '') === 'urban' ? 'selected' : ''; ?>>Urban</option>
                            <option value="rural" <?php echo ($existing_hod['address_type'] ?? '') === 'rural' ? 'selected' : ''; ?>>Rural</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <!-- Urban Details -->
                <div id="urbanDetails" class="form-row" style="display: <?php echo ($existing_hod['address_type'] ?? '') === 'urban' ? 'grid' : 'none'; ?>;">
                    <div class="form-group">
                        <label for="urbanCity">City *</label>
                        <select id="urbanCity" name="urbanCity" required>
                            <option value="">Select City</option>
                            <option value="dehradun" <?php echo ($existing_hod['urban_city'] ?? '') === 'dehradun' ? 'selected' : ''; ?>>Dehradun</option>
                            <option value="haridwar" <?php echo ($existing_hod['urban_city'] ?? '') === 'haridwar' ? 'selected' : ''; ?>>Haridwar</option>
                            <option value="roorkee" <?php echo ($existing_hod['urban_city'] ?? '') === 'roorkee' ? 'selected' : ''; ?>>Roorkee</option>
                            <option value="haldwani" <?php echo ($existing_hod['urban_city'] ?? '') === 'haldwani' ? 'selected' : ''; ?>>Haldwani</option>
                            <option value="rudrapur" <?php echo ($existing_hod['urban_city'] ?? '') === 'rudrapur' ? 'selected' : ''; ?>>Rudrapur</option>
                            <option value="kashipur" <?php echo ($existing_hod['urban_city'] ?? '') === 'kashipur' ? 'selected' : ''; ?>>Kashipur</option>
                            <option value="rishikesh" <?php echo ($existing_hod['urban_city'] ?? '') === 'rishikesh' ? 'selected' : ''; ?>>Rishikesh</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="urbanWard">Ward *</label>
                        <select id="urbanWard" name="urbanWard" required>
                            <option value="">Select Ward</option>
                            <option value="zone1" <?php echo ($existing_hod['urban_ward'] ?? '') === 'zone1' ? 'selected' : ''; ?>>Zone 1</option>
                            <option value="zone2" <?php echo ($existing_hod['urban_ward'] ?? '') === 'zone2' ? 'selected' : ''; ?>>Zone 2</option>
                            <option value="zone3" <?php echo ($existing_hod['urban_ward'] ?? '') === 'zone3' ? 'selected' : ''; ?>>Zone 3</option>
                            <option value="zone4" <?php echo ($existing_hod['urban_ward'] ?? '') === 'zone4' ? 'selected' : ''; ?>>Zone 4</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <!-- Rural Details -->
                <div id="ruralDetails" class="form-row" style="display: <?php echo ($existing_hod['address_type'] ?? '') === 'rural' ? 'grid' : 'none'; ?>;">
                    <div class="form-group">
                        <label for="ruralDistrict">District *</label>
                        <select id="ruralDistrict" name="ruralDistrict" required>
                            <option value="">Select District</option>
                            <option value="dehradun" <?php echo ($existing_hod['rural_district'] ?? '') === 'dehradun' ? 'selected' : ''; ?>>Dehradun</option>
                            <option value="haridwar" <?php echo ($existing_hod['rural_district'] ?? '') === 'haridwar' ? 'selected' : ''; ?>>Haridwar</option>
                            <option value="nainital" <?php echo ($existing_hod['rural_district'] ?? '') === 'nainital' ? 'selected' : ''; ?>>Nainital</option>
                            <option value="udham-singh-nagar" <?php echo ($existing_hod['rural_district'] ?? '') === 'udham-singh-nagar' ? 'selected' : ''; ?>>Udham Singh Nagar</option>
                            <option value="chamoli" <?php echo ($existing_hod['rural_district'] ?? '') === 'chamoli' ? 'selected' : ''; ?>>Chamoli</option>
                            <option value="rudraprayag" <?php echo ($existing_hod['rural_district'] ?? '') === 'rudraprayag' ? 'selected' : ''; ?>>Rudraprayag</option>
                            <option value="tehri-garhwal" <?php echo ($existing_hod['rural_district'] ?? '') === 'tehri-garhwal' ? 'selected' : ''; ?>>Tehri Garhwal</option>
                            <option value="uttarkashi" <?php echo ($existing_hod['rural_district'] ?? '') === 'uttarkashi' ? 'selected' : ''; ?>>Uttarkashi</option>
                            <option value="pithoragarh" <?php echo ($existing_hod['rural_district'] ?? '') === 'pithoragarh' ? 'selected' : ''; ?>>Pithoragarh</option>
                            <option value="bageshwar" <?php echo ($existing_hod['rural_district'] ?? '') === 'bageshwar' ? 'selected' : ''; ?>>Bageshwar</option>
                            <option value="almora" <?php echo ($existing_hod['rural_district'] ?? '') === 'almora' ? 'selected' : ''; ?>>Almora</option>
                            <option value="champawat" <?php echo ($existing_hod['rural_district'] ?? '') === 'champawat' ? 'selected' : ''; ?>>Champawat</option>
                            <option value="pauri-garhwal" <?php echo ($existing_hod['rural_district'] ?? '') === 'pauri-garhwal' ? 'selected' : ''; ?>>Pauri Garhwal</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="ruralTehsil">Tehsil *</label>
                        <select id="ruralTehsil" name="ruralTehsil" required>
                            <option value="">Select Tehsil</option>
                            <!-- Options will be populated based on district selection -->
                        </select>
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="form-submit">
                        <i class="fas fa-save"></i> Submit HOD Details
                    </button>
                    <?php if ($existing_hod): ?>
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
        console.log('HOD details page loaded - events handled by home.php');
        
        // Client-side validation functions
        function validateFullName(name) {
            if (!name || name.trim().length < 2 || name.trim().length > 100) {
                return 'Full name must be between 2 and 100 characters.';
            }
            if (!/^[a-zA-Z\s\.]+$/.test(name.trim())) {
                return 'Full name can only contain letters, spaces, and dots.';
            }
            return '';
        }

        function validatePhone(phone) {
            if (!phone || !/^\+91\d{10}$/.test(phone)) {
                return 'Please enter a valid phone number with +91 format (e.g., +919876543210).';
            }
            return '';
        }

        function validateEmail(email) {
            if (email && email.trim() !== '') {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    return 'Please enter a valid email address.';
                }
                if (email.length > 100) {
                    return 'Email address cannot exceed 100 characters.';
                }
            }
            return '';
        }

        function validateGender(gender) {
            if (!gender || !['male', 'female', 'other'].includes(gender)) {
                return 'Please select a valid gender.';
            }
            return '';
        }

        function validateDateOfBirth(dob) {
            if (!dob) {
                return 'Date of birth is required.';
            }
            
            const birthDate = new Date(dob);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if (age < 18) {
                return `You must be at least 18 years old to register. Current age: ${age} years.`;
            }
            if (age > 100) {
                return 'Please enter a valid date of birth. Age cannot exceed 100 years.';
            }
            return '';
        }

        function validatePostPosition(post) {
            if (!post || post.trim().length < 3 || post.trim().length > 100) {
                return 'Post/Position must be between 3 and 100 characters.';
            }
            return '';
        }

        function validateDepartment(dept) {
            if (!dept || !['law-order', 'cid', 'traffic'].includes(dept)) {
                return 'Please select a valid department.';
            }
            return '';
        }

        function validateAddressType(addressType) {
            if (!addressType || !['urban', 'rural'].includes(addressType)) {
                return 'Please select a valid address type.';
            }
            return '';
        }

        function validateLocationDetails(addressType, district, constituency) {
            if (!district || !constituency) {
                return 'Location details are required.';
            }
            
            if (addressType === 'urban') {
                const validCities = ['dehradun', 'haridwar', 'roorkee', 'haldwani', 'rudrapur', 'kashipur', 'rishikesh'];
                const validWards = ['zone1', 'zone2', 'zone3', 'zone4'];
                
                if (!validCities.includes(constituency)) {
                    return 'Please select a valid city.';
                }
                if (!validWards.includes(district)) {
                    return 'Please select a valid ward.';
                }
            } else if (addressType === 'rural') {
                const validDistricts = ['dehradun', 'haridwar', 'nainital', 'udham-singh-nagar', 'chamoli', 'rudraprayag', 'tehri-garhwal', 'uttarkashi', 'pithoragarh', 'bageshwar', 'almora', 'champawat', 'pauri-garhwal'];
                const validTehsils = ['dehradun', 'vikasnagar', 'kalsi', 'tyuni', 'chakrata', 'haridwar', 'roorkee', 'laksar', 'bahadrabad', 'nainital', 'kaladhungi', 'ramnagar', 'udham-singh-nagar', 'rudrapur', 'kashipur', 'jaspur', 'bajpur', 'gadarpur', 'khatima', 'sitarganj', 'chamoli', 'karnaprayag', 'gairsain', 'tharali', 'rudraprayag', 'ukhimath', 'agastyamuni', 'tehri-garhwal', 'tehri', 'ghansali', 'pratapnagar', 'uttarkashi', 'bhatwari', 'purola', 'mori', 'pithoragarh', 'munsiari', 'dharchula', 'berinag', 'gangolihat', 'bageshwar', 'kapkot', 'garur', 'almora', 'ranikhet', 'bhawali', 'champawat', 'lohaghat', 'pauri-garhwal', 'kotdwara', 'lansdowne', 'srinagar'];
                
                if (!validDistricts.includes(district)) {
                    return 'Please select a valid district.';
                }
                if (!validTehsils.includes(constituency)) {
                    return 'Please select a valid tehsil.';
                }
            }
            return '';
        }

        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.add('error');
                const errorDiv = field.parentNode.querySelector('.error-message');
                if (errorDiv) {
                    errorDiv.textContent = message;
                    errorDiv.style.display = 'block';
                }
            }
        }

        function clearError(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.remove('error');
                const errorDiv = field.parentNode.querySelector('.error-message');
                if (errorDiv) {
                    errorDiv.style.display = 'none';
                }
            }
        }

        function showLocationDetails() {
            const addressType = document.getElementById('addressType').value;
            const urbanDetails = document.getElementById('urbanDetails');
            const ruralDetails = document.getElementById('ruralDetails');
            
            if (urbanDetails && ruralDetails) {
                urbanDetails.style.display = (addressType === 'urban') ? 'grid' : 'none';
                ruralDetails.style.display = (addressType === 'rural') ? 'grid' : 'none';
            }
        }
        
        // Form submission handler (handled by home.php)
        // All event listeners are now handled by the parent home.php file
    </script>
</body>
</html>

