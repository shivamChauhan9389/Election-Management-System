<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../logger.php';

// Ensure user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please log in again.'
    ]);
    exit();
}

// Validate all posted numeric fields are non-negative integers
$fields = [
    'perm_male','perm_female','perm_other',
    'out_upnl_male','out_upnl_female','out_upnl_other',
    'out_prd_male','out_prd_female','out_prd_other',
    'out_homeguard_male','out_homeguard_female','out_homeguard_other'
];

foreach ($fields as $f) {
    // Treat missing or empty as zero (allowed)
    if (!isset($_POST[$f]) || $_POST[$f] === '') {
        continue;
    }
    // Must be numeric and non-negative
    if (!is_numeric($_POST[$f])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid input for ' . $f . ': value must be a number'
        ]);
        exit();
    }
    if (intval($_POST[$f]) < 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid input for ' . $f . ': value cannot be negative'
        ]);
        exit();
    }
}

// --- PERMANENT ---
$perm_male = intval($_POST['perm_male'] ?? 0);
$perm_female = intval($_POST['perm_female'] ?? 0);
$perm_other = intval($_POST['perm_other'] ?? 0); // New field

// --- OUTSOURCE ---
$out_upnl_male = intval($_POST['out_upnl_male'] ?? 0);
$out_upnl_female = intval($_POST['out_upnl_female'] ?? 0);
$out_upnl_other = intval($_POST['out_upnl_other'] ?? 0); // New field

$out_prd_male = intval($_POST['out_prd_male'] ?? 0);
$out_prd_female = intval($_POST['out_prd_female'] ?? 0);
$out_prd_other = intval($_POST['out_prd_other'] ?? 0); // New field

$out_homeguard_male = intval($_POST['out_homeguard_male'] ?? 0);
$out_homeguard_female = intval($_POST['out_homeguard_female'] ?? 0);
$out_homeguard_other = intval($_POST['out_homeguard_other'] ?? 0); // New field

// --- CALCULATIONS ---
$perm_total = $perm_male + $perm_female + $perm_other; // Updated calculation
$out_total = $out_upnl_male + $out_upnl_female + $out_upnl_other +
             $out_prd_male + $out_prd_female + $out_prd_other +
             $out_homeguard_male + $out_homeguard_female + $out_homeguard_other; // Updated calculation
$grand_total = $perm_total + $out_total;

// --- PERSIST TO DATABASE ---
$user_id = intval($_SESSION['id']);

// Check if a record exists for this user
$exists = false;
if ($stmt = $conn->prepare("SELECT id FROM employee_strength WHERE user_id = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $exists = ($result && $result->num_rows > 0);
    }
    $stmt->close();
}

if ($exists) {
    // Update existing row
    $sql = "UPDATE employee_strength SET 
        perm_male = ?, perm_female = ?, perm_other = ?,
        out_upnl_male = ?, out_upnl_female = ?, out_upnl_other = ?,
        out_prd_male = ?, out_prd_female = ?, out_prd_other = ?,
        out_homeguard_male = ?, out_homeguard_female = ?, out_homeguard_other = ?,
        perm_total = ?, out_total = ?, grand_total = ?
        WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            "iiiiiiiiiiiiiiii",
            $perm_male, $perm_female, $perm_other,
            $out_upnl_male, $out_upnl_female, $out_upnl_other,
            $out_prd_male, $out_prd_female, $out_prd_other,
            $out_homeguard_male, $out_homeguard_female, $out_homeguard_other,
            $perm_total, $out_total, $grand_total,
            $user_id
        );
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error while updating.']);
            exit();
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare update statement.']);
        exit();
    }
} else {
    // Insert new row
    $sql = "INSERT INTO employee_strength (
        user_id,
        perm_male, perm_female, perm_other,
        out_upnl_male, out_upnl_female, out_upnl_other,
        out_prd_male, out_prd_female, out_prd_other,
        out_homeguard_male, out_homeguard_female, out_homeguard_other,
        perm_total, out_total, grand_total
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            "iiiiiiiiiiiiiiii",
            $user_id,
            $perm_male, $perm_female, $perm_other,
            $out_upnl_male, $out_upnl_female, $out_upnl_other,
            $out_prd_male, $out_prd_female, $out_prd_other,
            $out_homeguard_male, $out_homeguard_female, $out_homeguard_other,
            $perm_total, $out_total, $grand_total
        );
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error while inserting.']);
            exit();
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare insert statement.']);
        exit();
    }
}

log_action("Employee strength saved", $user_id, 'EMPLOYEE_STRENGTH', 'SUCCESS', [
    'perm_total' => $perm_total,
    'out_total' => $out_total,
    'grand_total' => $grand_total
]);

// Store the summary in the user's session
$_SESSION['employee_summary'] = [
    'perm_total' => $perm_total,
    'out_total' => $out_total,
    'grand_total' => $grand_total,
    'details' => $_POST 
];

// Send a success response back to the JavaScript
echo json_encode([
    'success' => true,
    'message' => 'Summary saved successfully!',
    'summary' => $_SESSION['employee_summary']
]);

exit();
?>