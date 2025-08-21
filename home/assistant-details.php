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

// Check if user has access to assistant details (HOD or admin)
require_role('hod', 'home/home.php');

log_action("Accessed Assistant details", $_SESSION['id'] ?? null);

// Fetch existing assistants
$assistants = [];
$viewer_role = $_SESSION['role'] ?? '';
if ($viewer_role === 'hod') {
    $sql = "SELECT u.id as user_id, u.fullname as name, u.mobile as phone, u.email, ud.assistant_type, ud.assigned_location, ud.status 
            FROM users u 
            LEFT JOIN user_details ud ON u.id = ud.user_id 
            WHERE (u.role = 'assistant' OR EXISTS(SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role='assistant' AND ur.is_active=1))
              AND ud.supervisor_id = ?
              AND ud.status = 'active'
            ORDER BY u.id ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $_SESSION['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $assistants[] = $row;
            }
        }
        $stmt->close();
    }
} else {
    $sql = "SELECT u.id as user_id, u.fullname as name, u.mobile as phone, u.email, ud.assistant_type, ud.assigned_location, ud.status 
            FROM users u 
            LEFT JOIN user_details ud ON u.id = ud.user_id 
            WHERE (u.role = 'assistant' OR EXISTS(SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role='assistant' AND ur.is_active=1))
            ORDER BY u.id ASC"; 
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $assistants[] = $row;
        }
    }
}

// Fetch pending assistant registrations
$pending_assistants = [];
$sql_pending = "SELECT fullname, email, mobile, status, created_at FROM pending_assistant_registrations WHERE added_by_hod_id = ? AND status IN ('pending','expired') ORDER BY created_at DESC";
$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param("i", $_SESSION['id']);
$stmt_pending->execute();
$result_pending = $stmt_pending->get_result();
if ($result_pending && $result_pending->num_rows > 0) {
    while ($row = $result_pending->fetch_assoc()) {
        $pending_assistants[] = $row;
    }
}
$stmt_pending->close();
$conn->close();
?>

<!-- Display Existing Assistants -->
<div class="assistants-overview fade-in">
    <h2 class="form-title">Assistant Management</h2>
    <p class="form-subtitle">Manage assistant registrations and view pending assistants</p>
    
    <!-- Pending Assistants Section -->
    <?php if (!empty($pending_assistants)): ?>
        <div class="section-container">
            <h3 class="section-title"><i class="fas fa-clock"></i> Pending Assistant Registrations</h3>
            <div class="content-grid">
                <?php foreach ($pending_assistants as $assistant): ?>
                    <div class="content-card <?php echo $assistant['status'] === 'completed' ? 'completed' : ($assistant['status'] === 'expired' ? 'expired' : 'pending'); ?>">
                        <h3 class="card-title" style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($assistant['fullname']); ?></h3>
                        <p class="card-description" style="line-height: 1.6;">
                            <i class="fas fa-phone fa-fw"></i> <?php echo htmlspecialchars($assistant['mobile']); ?><br>
                            <i class="fas fa-envelope fa-fw"></i> <?php echo htmlspecialchars($assistant['email']); ?><br>
                            <i class="fas fa-calendar fa-fw"></i> Added: <?php echo date('M d, Y', strtotime($assistant['created_at'])); ?>
                        </p>
                        <div class="status-badge <?php echo $assistant['status']; ?>">
                            <?php echo ucfirst($assistant['status']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Registered Assistants Section -->
    <?php if (!empty($assistants)): ?>
        <div class="section-container">
            <h3 class="section-title"><i class="fas fa-check-circle"></i> Registered Assistants</h3>
            <div class="content-grid">
                <?php foreach ($assistants as $assistant): ?>
                    <div class="content-card registered">
                        <h3 class="card-title" style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($assistant['name']); ?></h3>
                        <p class="card-description" style="line-height: 1.6;">
                            <i class="fas fa-phone fa-fw"></i> <?php echo htmlspecialchars($assistant['phone']); ?><br>
                            <i class="fas fa-envelope fa-fw"></i> <?php echo htmlspecialchars($assistant['email']); ?>
                        </p>
                        <div class="status-badge registered">
                            Status: <?php echo htmlspecialchars($assistant['status'] ?? 'active'); ?>
                        </div>
                        <?php if (($assistant['status'] ?? 'active') === 'active'): ?>
                        <button class="form-submit btn-deactivate-assistant" style="width:auto; margin-top:10px; background:#ef4444" data-assistant-id="<?php echo (int)$assistant['user_id']; ?>">
                            <i class="fas fa-user-slash"></i> Deactivate
                        </button>
                        <?php else: ?>
                        <button class="form-submit" style="width:auto; margin-top:10px; background:#6b7280" disabled>
                            Inactive
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Add Assistant Button -->
    <button id="showAssistantFormBtn" class="form-submit" style="width: auto; margin: 2rem 0 0 0;">
        <i class="fas fa-plus"></i> Add New Assistant
    </button>
</div>

<!-- Registration Form (Initially Hidden) -->
<div id="assistantFormContainer" style="display: none; margin-top: 2rem;">
    <div class="form-container fade-in">
        <h2 class="form-title">Add New Assistant</h2>
        <p class="form-subtitle">Enter assistant details. They will receive an email to set their password and complete registration.</p>
        
        <form id="assistantForm" action="javascript:void(0);">
            <div class="form-group">
                <label for="fullname" class="form-label"><i class="fas fa-user"></i> Full Name *</label>
                <input type="text" id="fullname" name="fullname" class="form-input" required placeholder="Enter full name">
                <div class="error-message" id="fullnameError"></div>
            </div>
            
            <div class="form-group">
                <label for="mobile" class="form-label"><i class="fas fa-phone"></i> Phone Number *</label>
                <input type="tel" id="mobile" name="mobile" class="form-input" required placeholder="Enter 10-digit phone number (e.g., 9876543210)" maxlength="10" pattern="[6789][0-9]{9}" oninput="validatePhoneInput(this)">
                <div class="error-message" id="mobileError"></div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label"><i class="fas fa-envelope"></i> Email Address *</label>
                <input type="email" id="email" name="email" class="form-input" required placeholder="Enter email address">
                <div class="error-message" id="emailError"></div>
            </div>
            
            <button type="submit" class="form-submit" style="margin-top: 1rem;">
                <i class="fas fa-save"></i> Add Assistant
            </button>
        </form>
        
        <!-- Success/Error Message Container -->
        <div id="messageContainer" style="display: none; margin-top: 1rem; padding: 1rem; border-radius: 8px; font-weight: 500;"></div>
    </div>
</div>

<style>
.section-container {
    margin-bottom: 2rem;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    margin-top: 0.5rem;
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-badge.expired {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.status-badge.registered {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.content-card.pending {
    border-left: 4px solid #f59e0b;
}

.content-card.completed {
    border-left: 4px solid #10b981;
}

.content-card.expired {
    border-left: 4px solid #ef4444;
}

.content-card.registered {
    border-left: 4px solid #3b82f6;
}
</style>

<script>
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

document.addEventListener('DOMContentLoaded', function() {
    const showFormBtn = document.getElementById('showAssistantFormBtn');
    const formContainer = document.getElementById('assistantFormContainer');
    const form = document.getElementById('assistantForm');
    const messageContainer = document.getElementById('messageContainer');
    
    // Add phone input validation
    const phoneInput = document.getElementById('mobile');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            validatePhoneInput(this);
        });
    }
    
    // Show/hide form
    showFormBtn.addEventListener('click', function() {
        formContainer.style.display = 'block';
        showFormBtn.style.display = 'none';
    });
    
    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('Form submission started');
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        submitBtn.disabled = true;
        
        // Hide any existing messages
        messageContainer.style.display = 'none';
        
        // Get form data
        const formData = new FormData(form);
        
        // Debug: Log form data
        console.log('Form data being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
        
        // Send AJAX request
        fetch('process-assistant-registration.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Show message
            messageContainer.style.display = 'block';
            messageContainer.style.backgroundColor = data.success ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
            messageContainer.style.color = data.success ? '#10b981' : '#ef4444';
            messageContainer.style.border = data.success ? '1px solid rgba(16, 185, 129, 0.2)' : '1px solid rgba(239, 68, 68, 0.2)';
            messageContainer.textContent = data.message;
            
            if (data.success) {
                // Reset form and reload page to show new assistant
                form.reset();
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageContainer.style.display = 'block';
            messageContainer.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
            messageContainer.style.color = '#ef4444';
            messageContainer.style.border = '1px solid rgba(239, 68, 68, 0.2)';
            messageContainer.textContent = 'An error occurred while adding the assistant. Please try again.';
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
});

// Delegate click for deactivate buttons
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-deactivate-assistant');
    if (!btn) return;
    const assistantId = btn.getAttribute('data-assistant-id');
    if (!assistantId) return;
    if (!confirm('Are you sure you want to deactivate this assistant?')) return;
    fetch('deactivate-assistant.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'assistant_id=' + encodeURIComponent(assistantId)
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message || (data.success ? 'Assistant deactivated.' : 'Failed to deactivate'));
        if (data.success) window.location.reload();
    })
    .catch(() => alert('Network error while deactivating assistant'));
});
</script>
