<?php
session_start();
require_once '../role_manager.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has access to employee details
require_role('employee', 'home/home.php');

// This action is used to force the form to show again
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    unset($_SESSION['employee_summary']);
}

// Check if summary data exists in the session
$summary_exists = isset($_SESSION['employee_summary']);
$summary = $summary_exists ? $_SESSION['employee_summary'] : null;

// Prefill values from database employee_strength for current user
$prefill = [
    'perm_male' => 0,
    'perm_female' => 0,
    'perm_other' => 0,
    'out_upnl_male' => 0,
    'out_upnl_female' => 0,
    'out_upnl_other' => 0,
    'out_prd_male' => 0,
    'out_prd_female' => 0,
    'out_prd_other' => 0,
    'out_homeguard_male' => 0,
    'out_homeguard_female' => 0,
    'out_homeguard_other' => 0,
];

if (isset($_SESSION['id'])) {
    require_once '../db.php';
    if ($stmt = $conn->prepare("SELECT perm_male, perm_female, perm_other, out_upnl_male, out_upnl_female, out_upnl_other, out_prd_male, out_prd_female, out_prd_other, out_homeguard_male, out_homeguard_female, out_homeguard_other FROM employee_strength WHERE user_id = ? LIMIT 1")) {
        $stmt->bind_param("i", $_SESSION['id']);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                foreach ($row as $key => $val) {
                    if (array_key_exists($key, $prefill)) {
                        $prefill[$key] = (int)$val;
                    }
                }
            }
        }
        $stmt->close();
    }
}

// Determine default outsource category to show based on saved values
$default_out_category = '';
$upnl_sum = $prefill['out_upnl_male'] + $prefill['out_upnl_female'] + $prefill['out_upnl_other'];
$prd_sum = $prefill['out_prd_male'] + $prefill['out_prd_female'] + $prefill['out_prd_other'];
$home_sum = $prefill['out_homeguard_male'] + $prefill['out_homeguard_female'] + $prefill['out_homeguard_other'];
if ($upnl_sum > 0) {
    $default_out_category = 'upnl';
} elseif ($prd_sum > 0) {
    $default_out_category = 'prd';
} elseif ($home_sum > 0) {
    $default_out_category = 'homeguard';
}

?>

<style>
    /* Styles for the new overview section */
    .summary-overview-container { text-align: center; }
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin: 2rem 0; }
    .summary-card { background: rgba(0,0,0,0.1); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; transition: all 0.3s ease; }
    .summary-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .summary-card .icon { font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem; }
    .summary-card .number { font-size: 3.5rem; font-weight: 700; color: var(--text-primary); }
    .summary-card .label { font-size: 1.1rem; color: var(--text-secondary); font-weight: 500; }
    .edit-button { background: var(--primary-hover); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
    .edit-button:hover { background: var(--primary-color); box-shadow: var(--shadow); }
    .form-alert { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 500; }
    .form-alert.error { background: rgba(239, 68, 68, 0.1); color: var(--error-color); border: 1px solid rgba(239, 68, 68, 0.2); }
    
    /* NEW: Style for 3-column grid */
    .form-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    /* Outsource category select and tables */
    .out-category-select { margin: 0.5rem 0 1rem; }
    .out-table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
    .out-table th, .out-table td { border: 1px solid var(--border-color); padding: 10px; text-align: left; }
    .out-table th { background: rgba(0,0,0,0.05); }
</style>

<!-- This container shows the OVERVIEW if data exists -->
<div id="summaryOverviewContainer" class="summary-overview-container fade-in" <?php if (!$summary_exists) echo 'style="display:none;"'; ?>>
    <h2 class="form-title">Employee Strength Overview</h2>
    <p class="form-subtitle">This is the current summary of all registered employees.</p>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="icon"><i class="fas fa-user-tie"></i></div>
            <div id="overview_perm_total" class="number"><?php echo $summary['perm_total'] ?? '0'; ?></div>
            <div class="label">Permanent Employees</div>
        </div>
        <div class="summary-card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div id="overview_out_total" class="number"><?php echo $summary['out_total'] ?? '0'; ?></div>
            <div class="label">Outsource Employees</div>
        </div>
        <div class="summary-card">
            <div class="icon"><i class="fas fa-stream"></i></div>
            <div id="overview_grand_total" class="number"><?php echo $summary['grand_total'] ?? '0'; ?></div>
            <div class="label">Grand Total Employees</div>
        </div>
    </div>
    
    <button id="editSummaryBtn" class="edit-button"><i class="fas fa-edit"></i> Edit Details</button>
</div>


<!-- This container shows the FORM if no data exists -->
<div id="summaryFormContainer" class="form-container fade-in" <?php if ($summary_exists) echo 'style="display:none;"'; ?>>
    <div id="formAlert" style="display:none;"></div>
    <h2 class="form-title">Employee Strength Registration</h2>
    <p class="form-subtitle">Enter the number of employees in each category.</p>
    
    <form id="employeeSummaryForm" novalidate>
        <div class="fieldset-container">
            <h3 class="fieldset-title"><i class="fas fa-user-tie"></i> Number of Permanent Employees</h3>
            <div class="form-grid-3">
                <div class="form-group"><label for="perm_male" class="form-label">Male</label><input type="number" id="perm_male" name="perm_male" class="form-input" min="0" placeholder="0" required value="<?php echo htmlspecialchars((string)$prefill['perm_male']); ?>"></div>
                <div class="form-group"><label for="perm_female" class="form-label">Female</label><input type="number" id="perm_female" name="perm_female" class="form-input" min="0" placeholder="0" required value="<?php echo htmlspecialchars((string)$prefill['perm_female']); ?>"></div>
                <div class="form-group"><label for="perm_other" class="form-label">Other</label><input type="number" id="perm_other" name="perm_other" class="form-input" min="0" placeholder="0" required value="<?php echo htmlspecialchars((string)$prefill['perm_other']); ?>"></div>
            </div>
        </div>
        <div class="fieldset-container">
            <h3 class="fieldset-title"><i class="fas fa-users"></i> Number of Outsource Employees</h3>
            <div class="form-group">
                <label for="out_category" class="form-label">Select Category</label>
                <select id="out_category" class="form-select out-category-select" onchange="document.getElementById('out_table_upnl').style.display=(this.value==='upnl'?'block':'none');document.getElementById('out_table_prd').style.display=(this.value==='prd'?'block':'none');document.getElementById('out_table_homeguard').style.display=(this.value==='homeguard'?'block':'none');">
                    <option value="" disabled <?php echo $default_out_category === '' ? 'selected' : ''; ?>>-- Select --</option>
                    <option value="upnl" <?php echo $default_out_category === 'upnl' ? 'selected' : ''; ?>>UPNL</option>
                    <option value="prd" <?php echo $default_out_category === 'prd' ? 'selected' : ''; ?>>PRD</option>
                    <option value="homeguard" <?php echo $default_out_category === 'homeguard' ? 'selected' : ''; ?>>Homeguard</option>
                </select>
            </div>

            <div id="out_table_upnl" class="sub-category" style="<?php echo ($default_out_category === 'upnl' ? 'display: block;' : 'display: none;'); ?>">
                <h4 class="sub-category-title">UPNL</h4>
                <table class="out-table">
                    <thead>
                        <tr><th>Male</th><th>Female</th><th>Other</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="number" id="out_upnl_male" name="out_upnl_male" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_upnl_male']); ?>"></td>
                            <td><input type="number" id="out_upnl_female" name="out_upnl_female" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_upnl_female']); ?>"></td>
                            <td><input type="number" id="out_upnl_other" name="out_upnl_other" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_upnl_other']); ?>"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="out_table_prd" class="sub-category" style="<?php echo ($default_out_category === 'prd' ? 'display: block;' : 'display: none;'); ?>">
                <h4 class="sub-category-title">PRD</h4>
                <table class="out-table">
                    <thead>
                        <tr><th>Male</th><th>Female</th><th>Other</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="number" id="out_prd_male" name="out_prd_male" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_prd_male']); ?>"></td>
                            <td><input type="number" id="out_prd_female" name="out_prd_female" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_prd_female']); ?>"></td>
                            <td><input type="number" id="out_prd_other" name="out_prd_other" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_prd_other']); ?>"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="out_table_homeguard" class="sub-category" style="<?php echo ($default_out_category === 'homeguard' ? 'display: block;' : 'display: none;'); ?>">
                <h4 class="sub-category-title">Homeguard</h4>
                <table class="out-table">
                    <thead>
                        <tr><th>Male</th><th>Female</th><th>Other</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="number" id="out_homeguard_male" name="out_homeguard_male" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_homeguard_male']); ?>"></td>
                            <td><input type="number" id="out_homeguard_female" name="out_homeguard_female" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_homeguard_female']); ?>"></td>
                            <td><input type="number" id="out_homeguard_other" name="out_homeguard_other" class="form-input" min="0" placeholder="0" value="<?php echo htmlspecialchars((string)$prefill['out_homeguard_other']); ?>"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <button type="submit" class="form-submit"><i class="fas fa-paper-plane"></i> Submit & View Summary</button>
    </form>
</div>

<script>
// This script now only handles the "Edit" button.
// The main home.php script handles the form submission.
(function() {
    const editBtn = document.getElementById('editSummaryBtn');
    if (editBtn) {
        editBtn.addEventListener('click', () => {
            // This calls the global loadSection function in the parent window (home.php)
            // It passes a special query to tell the PHP to clear the session data.
            window.parent.loadSection('employee?action=clear');
        });
    }

    // Client-side validation: prevent negative numbers in the employee form
    const form = document.getElementById('employeeSummaryForm');
    if (form) {
        const numberInputs = form.querySelectorAll('input[type="number"]');
        numberInputs.forEach((input) => {
            // Enforce non-negative integers at the browser level
            input.setAttribute('min', '0');
            input.setAttribute('step', '1');
            input.addEventListener('input', function() {
                if (this.value === '') return;
                const num = Number(this.value);
                if (isNaN(num) || num < 0) {
                    this.value = 0;
                }
            });
        });

        form.addEventListener('submit', function(e) {
            let hasNegative = false;
            numberInputs.forEach((input) => {
                const val = Number(input.value || '0');
                if (isNaN(val) || val < 0) {
                    hasNegative = true;
                }
            });
            if (hasNegative) {
                e.preventDefault();
                e.stopPropagation();
                const alertBox = document.getElementById('formAlert');
                if (alertBox) {
                    alertBox.className = 'form-alert error';
                    alertBox.style.display = 'block';
                    alertBox.textContent = 'Values cannot be negative. Please correct the inputs.';
                }
            }
        });
    }

    // Outsource category toggle
    const outCategory = document.getElementById('out_category');
    const tables = {
        upnl: document.getElementById('out_table_upnl'),
        prd: document.getElementById('out_table_prd'),
        homeguard: document.getElementById('out_table_homeguard')
    };

    function showOutTable(key) {
        Object.values(tables).forEach(el => { if (el) el.style.display = 'none'; });
        if (tables[key]) tables[key].style.display = 'block';
    }

    if (outCategory) {
        // Fallback init on DOM load
        const defaultFromPhp = '<?php echo $default_out_category; ?>';
        const selected = outCategory.value || defaultFromPhp;
        if (selected) {
            showOutTable(selected);
            outCategory.value = selected;
        }
    }
})();
</script>