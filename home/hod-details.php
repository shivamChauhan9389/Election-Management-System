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
            background: #D3D3D3;
            color: black;
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
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .summary-subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 2rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
            max-width: 100%;
        }

        .summary-card {
            background: rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .summary-card .icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .summary-card .label {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
            max-width: 100%;
            line-height: 1.4;
            min-height: 1.4em;
        }

        .summary-card .sub-label {
            font-size: 1rem;
            color: var(--text-secondary);
            font-weight: 500;
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

         /* Responsive adjustments for long email addresses */
         @media (max-width: 768px) {
             .summary-card .label {
                 font-size: 1rem;
                 line-height: 1.3;
             }
             
             .summary-grid {
                 grid-template-columns: 1fr;
                 gap: 1.5rem;
             }
             
             .summary-card {
                 min-height: 180px;
                 padding: 1.5rem;
             }
         }

         @media (max-width: 480px) {
             .summary-card .label {
                 font-size: 0.9rem;
                 word-break: break-all;
             }
             
             .summary-card {
                 min-height: 160px;
                 padding: 1rem;
             }
         }

         .form-group input:disabled,
         .form-group select:disabled,
         .form-group textarea:disabled {
             opacity: 0.6;
             cursor: not-allowed;
             background: var(--bg-secondary);
         }

         .form-group input[readonly],
         .form-group select[readonly],
         .form-group textarea[readonly] {
             background: var(--bg-secondary);
             color: var(--text-secondary);
             cursor: not-allowed;
             opacity: 0.8;
         }
    </style>
</head>
<body>
    <div class="container">
                 <?php if ($existing_hod): ?>
         <!-- Show existing HOD details summary -->
         <div class="summary-overview-container fade-in">
             <h2 class="summary-title">HOD Details Overview</h2>
             <p class="summary-subtitle">Your HOD details are already registered. You can update them below.</p>
             
             <div class="summary-grid">
                 <div class="summary-card">
                     <div class="icon"><i class="fas fa-user-tie"></i></div>
                     <div class="label"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></div>
                     <div class="sub-label"><?php echo htmlspecialchars($existing_hod['post_position'] ?? ''); ?></div>
                 </div>
                 <div class="summary-card">
                     <div class="icon"><i class="fas fa-building"></i></div>
                     <div class="label"><?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($existing_hod['department'] ?? ''))); ?></div>
                     <div class="sub-label"><?php 
                         if (($existing_hod['address_type'] ?? '') === 'urban') {
                             echo ucfirst(htmlspecialchars($existing_hod['urban_city'] ?? ''));
                         } else {
                             echo ucfirst(str_replace('-', ' ', htmlspecialchars($existing_hod['rural_district'] ?? '')));
                         }
                     ?></div>
                 </div>
                                   <div class="summary-card">
                      <div class="icon"><i class="fas fa-envelope"></i></div>
                      <div class="label"><?php echo htmlspecialchars($existing_hod['email'] ?? ''); ?></div>
                      <div class="sub-label"><?php 
                          $phone = $_SESSION['phn'] ?? '';
                          // Remove +91 prefix for display
                          echo htmlspecialchars(preg_replace('/^\+91/', '', $phone));
                      ?></div>
                  </div>
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
                        <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" <?php echo $existing_hod ? 'readonly' : ''; ?> required>
                        <div class="error-message"></div>
                    </div>
                                         <div class="form-group">
                         <label for="phone">Phone Number *</label>
                         <input type="tel" id="phone" name="phone" value="<?php 
                             $phone = $_SESSION['phn'] ?? '';
                             // Remove +91 prefix if present for display
                             echo htmlspecialchars(preg_replace('/^\+91/', '', $phone));
                         ?>" placeholder="9876543210" oninput="validatePhoneInput(this)" <?php echo $existing_hod ? 'readonly' : ''; ?> required>
                         <div class="error-message"></div>
                     </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($existing_hod['email'] ?? ''); ?>">
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
                         <select id="postSearch" name="post" required>
                             <option value="">Select Post/Position</option>
                             <option value="Superintendent of Police" <?php echo ($existing_hod['post_position'] ?? '') === 'Superintendent of Police' ? 'selected' : ''; ?>>Superintendent of Police</option>
                             <option value="Deputy Superintendent of Police" <?php echo ($existing_hod['post_position'] ?? '') === 'Deputy Superintendent of Police' ? 'selected' : ''; ?>>Deputy Superintendent of Police</option>
                             <option value="Assistant Superintendent of Police" <?php echo ($existing_hod['post_position'] ?? '') === 'Assistant Superintendent of Police' ? 'selected' : ''; ?>>Assistant Superintendent of Police</option>
                             <option value="Inspector General of Police" <?php echo ($existing_hod['post_position'] ?? '') === 'Inspector General of Police' ? 'selected' : ''; ?>>Inspector General of Police</option>
                             <option value="Deputy Inspector General of Police" <?php echo ($existing_hod['post_position'] ?? '') === 'Deputy Inspector General of Police' ? 'selected' : ''; ?>>Deputy Inspector General of Police</option>
                             <option value="Senior Superintendent of Police" <?php echo ($existing_hod['post_position'] ?? '') === 'Senior Superintendent of Police' ? 'selected' : ''; ?>>Senior Superintendent of Police</option>
                             <option value="Additional Superintendent of Police" <?php echo ($existing_hod['post_position'] ?? '') === 'Additional Superintendent of Police' ? 'selected' : ''; ?>>Additional Superintendent of Police</option>
                             <option value="Circle Officer" <?php echo ($existing_hod['post_position'] ?? '') === 'Circle Officer' ? 'selected' : ''; ?>>Circle Officer</option>
                             <option value="Station House Officer" <?php echo ($existing_hod['post_position'] ?? '') === 'Station House Officer' ? 'selected' : ''; ?>>Station House Officer</option>
                             <option value="Sub-Inspector" <?php echo ($existing_hod['post_position'] ?? '') === 'Sub-Inspector' ? 'selected' : ''; ?>>Sub-Inspector</option>
                             <option value="Head Constable" <?php echo ($existing_hod['post_position'] ?? '') === 'Head Constable' ? 'selected' : ''; ?>>Head Constable</option>
                             <option value="Senior Constable" <?php echo ($existing_hod['post_position'] ?? '') === 'Senior Constable' ? 'selected' : ''; ?>>Senior Constable</option>
                             <option value="Constable" <?php echo ($existing_hod['post_position'] ?? '') === 'Constable' ? 'selected' : ''; ?>>Constable</option>
                             <option value="Traffic Inspector" <?php echo ($existing_hod['post_position'] ?? '') === 'Traffic Inspector' ? 'selected' : ''; ?>>Traffic Inspector</option>
                             <option value="Traffic Sub-Inspector" <?php echo ($existing_hod['post_position'] ?? '') === 'Traffic Sub-Inspector' ? 'selected' : ''; ?>>Traffic Sub-Inspector</option>
                             <option value="CID Inspector" <?php echo ($existing_hod['post_position'] ?? '') === 'CID Inspector' ? 'selected' : ''; ?>>CID Inspector</option>
                             <option value="CID Sub-Inspector" <?php echo ($existing_hod['post_position'] ?? '') === 'CID Sub-Inspector' ? 'selected' : ''; ?>>CID Sub-Inspector</option>
                             <option value="Other" <?php echo ($existing_hod['post_position'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                         </select>
                         <div class="error-message"></div>
                     </div>
                     
                     <!-- Custom Post/Position Input (shown when "Other" is selected) -->
                     <div id="customPostContainer" class="form-group" style="display: none;">
                         <label for="customPost">Specify Post/Position *</label>
                         <input type="text" id="customPost" name="customPost" placeholder="Enter your specific post/position" value="<?php 
                             $post_position = $existing_hod['post_position'] ?? '';
                             echo ($post_position !== 'Other' && !empty($post_position) && !in_array($post_position, [
                                 'Superintendent of Police', 'Deputy Superintendent of Police', 'Assistant Superintendent of Police',
                                 'Inspector General of Police', 'Deputy Inspector General of Police', 'Senior Superintendent of Police',
                                 'Additional Superintendent of Police', 'Circle Officer', 'Station House Officer', 'Sub-Inspector',
                                 'Head Constable', 'Senior Constable', 'Constable', 'Traffic Inspector', 'Traffic Sub-Inspector',
                                 'CID Inspector', 'CID Sub-Inspector'
                             ])) ? htmlspecialchars($post_position) : '';
                         ?>">
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
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="state">State</label>
                        <select id="state" name="state">
                            <option value="">Select State</option>
                            <option value="Uttarakhand">Uttarakhand</option>
                            
                        </select>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="district">District</label>
                        <select id="district" name="district">
                            <option value="">Select District</option>
                            <option value="Dehradun">Dehradun</option>
                            <option value="Haridwar">Haridwar</option>
                            <option value="Nainital">Nainital</option>
                            <option value="Udham Singh Nagar">Udham Singh Nagar</option>
                            <option value="Chamoli">Chamoli</option>
                            <option value="Rudraprayag">Rudraprayag</option>
                            <option value="Tehri Garhwal">Tehri Garhwal</option>
                            <option value="Uttarkashi">Uttarkashi</option>
                            <option value="Pithoragarh">Pithoragarh</option>
                            <option value="Bageshwar">Bageshwar</option>
                            <option value="Almora">Almora</option>
                            <option value="Champawat">Champawat</option>
                            <option value="Pauri Garhwal">Pauri Garhwal</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                </div>

                                 <!-- Urban Details -->
                 <div id="urbanDetails" class="form-row" style="display: <?php echo ($existing_hod['address_type'] ?? '') === 'urban' ? 'grid' : 'none'; ?>;">
                     <div class="form-group">
                         <label for="urbanCity">City *</label>
                         <select id="urbanCity" name="urbanCity" <?php echo ($existing_hod['address_type'] ?? '') === 'urban' ? 'required' : ''; ?>>
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
                         <select id="urbanWard" name="urbanWard" <?php echo ($existing_hod['address_type'] ?? '') === 'urban' ? 'required' : ''; ?>>
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
                         <select id="ruralDistrict" name="ruralDistrict" <?php echo ($existing_hod['address_type'] ?? '') === 'rural' ? 'required' : ''; ?>>
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
                         <select id="ruralTehsil" name="ruralTehsil" <?php echo ($existing_hod['address_type'] ?? '') === 'rural' ? 'required' : ''; ?>>
                             <option value="">Select Tehsil</option>
                             <!-- Options will be populated based on district selection -->
                             <option value="Pauri Garhwal">Pauri Garhwal</option>
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
             if (!phone || !/^[6789]\d{9}$/.test(phone)) {
                 return 'Please enter a valid 10-digit phone number starting with 6, 7, 8, or 9 (e.g., 9876543210).';
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
             if (!post || post.trim() === '') {
                 return 'Please select a post/position.';
             }
             
             const validPositions = [
                 'Superintendent of Police', 'Deputy Superintendent of Police', 'Assistant Superintendent of Police',
                 'Inspector General of Police', 'Deputy Inspector General of Police', 'Senior Superintendent of Police',
                 'Additional Superintendent of Police', 'Circle Officer', 'Station House Officer', 'Sub-Inspector',
                 'Head Constable', 'Senior Constable', 'Constable', 'Traffic Inspector', 'Traffic Sub-Inspector',
                 'CID Inspector', 'CID Sub-Inspector', 'Other'
             ];
             
             if (post === 'Other') {
                 const customPost = document.getElementById('customPost')?.value?.trim();
                 if (!customPost) {
                     return 'Please specify your post/position when selecting "Other".';
                 }
                 if (customPost.length < 3 || customPost.length > 100) {
                     return 'Custom post/position must be between 3 and 100 characters.';
                 }
             } else if (!validPositions.includes(post)) {
                 return 'Please select a valid post/position.';
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

         // Handle post/position dropdown change
         function handlePostPositionChange() {
             const postSelect = document.getElementById('postSearch');
             const customContainer = document.getElementById('customPostContainer');
             const customInput = document.getElementById('customPost');
             
             if (postSelect && customContainer && customInput) {
                 if (postSelect.value === 'Other') {
                     customContainer.style.display = 'block';
                     customInput.setAttribute('required', 'required');
                     postSelect.removeAttribute('required');
                 } else {
                     customContainer.style.display = 'none';
                     customInput.removeAttribute('required');
                     postSelect.setAttribute('required', 'required');
                     customInput.value = '';
                 }
             }
         }

         // Initialize post/position handling on page load
         document.addEventListener('DOMContentLoaded', function() {
             handlePostPositionChange();
             
             // Add change event listener to post/position dropdown
             const postSelect = document.getElementById('postSearch');
             if (postSelect) {
                 postSelect.addEventListener('change', handlePostPositionChange);
             }
         });
        
        // Form submission handler (handled by home.php)
        // All event listeners are now handled by the parent home.php file
    </script>
</body>
</html>
