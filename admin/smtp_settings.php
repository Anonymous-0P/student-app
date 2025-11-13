<?php
include('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_smtp'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $smtp_host = trim($_POST['smtp_host']);
        $smtp_port = trim($_POST['smtp_port']);
        $smtp_username = trim($_POST['smtp_username']);
        $smtp_password = trim($_POST['smtp_password']);
        $smtp_encryption = trim($_POST['smtp_encryption']);
        $from_email = trim($_POST['from_email']);
        $from_name = trim($_POST['from_name']);
        
        // Read current config file
        $config_file = '../config/mail_config.php';
        
        // Create new config content
        $config_content = "<?php\n";
        $config_content .= "// SMTP Configuration\n";
        $config_content .= "define('SMTP_HOST', " . var_export($smtp_host, true) . ");\n";
        $config_content .= "define('SMTP_PORT', " . var_export($smtp_port, true) . ");\n";
        $config_content .= "define('SMTP_USERNAME', " . var_export($smtp_username, true) . ");\n";
        $config_content .= "define('SMTP_PASSWORD', " . var_export($smtp_password, true) . ");\n";
        $config_content .= "define('SMTP_ENCRYPTION', " . var_export($smtp_encryption, true) . ");\n";
        $config_content .= "define('FROM_EMAIL', " . var_export($from_email, true) . ");\n";
        $config_content .= "define('FROM_NAME', " . var_export($from_name, true) . ");\n";
        $config_content .= "?>";
        
        // Write to file
        if (file_put_contents($config_file, $config_content)) {
            $success = "SMTP settings saved successfully!";
        } else {
            $error = "Failed to save settings. Please check file permissions.";
        }
    }
}

// Load current settings
$mail_config_file = '../config/mail_config.php';
if (file_exists($mail_config_file)) {
    include($mail_config_file);
}

// Set defaults if constants not defined
$current_smtp_host = defined('SMTP_HOST') ? SMTP_HOST : '';
$current_smtp_port = defined('SMTP_PORT') ? SMTP_PORT : '587';
$current_smtp_username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
$current_smtp_password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
$current_smtp_encryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';
$current_from_email = defined('FROM_EMAIL') ? FROM_EMAIL : '';
$current_from_name = defined('FROM_NAME') ? FROM_NAME : 'ThetaExams';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../moderator/css/moderator-style.css">
</head>
<body style="background: #f9fafb;">

<?php include('../includes/header.php'); ?>

<div class="container-fluid py-4" style="padding-left: 50px; padding-right: 50px;">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2"><i class="fas fa-envelope-open-text text-primary"></i> SMTP Configuration</h1>
                    <p class="text-muted mb-0">Manage email server settings for system notifications</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>SMTP Server Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="smtp_host" class="form-label fw-semibold">SMTP Host</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                       value="<?= htmlspecialchars($current_smtp_host) ?>" 
                                       placeholder="e.g., smtp.gmail.com" required>
                                <small class="text-muted">Your email provider's SMTP server address</small>
                            </div>
                            <div class="col-md-4">
                                <label for="smtp_port" class="form-label fw-semibold">Port</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                       value="<?= htmlspecialchars($current_smtp_port) ?>" 
                                       placeholder="587" required>
                                <small class="text-muted">Usually 587 or 465</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="smtp_encryption" class="form-label fw-semibold">Encryption Type</label>
                            <select class="form-select" id="smtp_encryption" name="smtp_encryption" required>
                                <option value="tls" <?= $current_smtp_encryption == 'tls' ? 'selected' : '' ?>>TLS (Recommended)</option>
                                <option value="ssl" <?= $current_smtp_encryption == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="" <?= $current_smtp_encryption == '' ? 'selected' : '' ?>>None</option>
                            </select>
                            <small class="text-muted">TLS is recommended for port 587, SSL for port 465</small>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label for="smtp_username" class="form-label fw-semibold">SMTP Username</label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                   value="<?= htmlspecialchars($current_smtp_username) ?>" 
                                   placeholder="your-email@example.com" required>
                            <small class="text-muted">Usually your full email address</small>
                        </div>

                        <div class="mb-3">
                            <label for="smtp_password" class="form-label fw-semibold">SMTP Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                       value="<?= htmlspecialchars($current_smtp_password) ?>" 
                                       placeholder="Enter your SMTP password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">For Gmail, use an App Password instead of your regular password</small>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label for="from_email" class="form-label fw-semibold">From Email Address</label>
                            <input type="email" class="form-control" id="from_email" name="from_email" 
                                   value="<?= htmlspecialchars($current_from_email) ?>" 
                                   placeholder="noreply@thetaexams.com" required>
                            <small class="text-muted">Email address that will appear as sender</small>
                        </div>

                        <div class="mb-4">
                            <label for="from_name" class="form-label fw-semibold">From Name</label>
                            <input type="text" class="form-control" id="from_name" name="from_name" 
                                   value="<?= htmlspecialchars($current_from_name) ?>" 
                                   placeholder="ThetaExams" required>
                            <small class="text-muted">Name that will appear as sender</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="save_smtp" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                                <i class="fas fa-paper-plane me-2"></i>Send Test Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Setup Guide</h6>
                </div>
                <div class="card-body">
                    <h6 class="fw-semibold">Gmail Setup:</h6>
                    <ul class="small mb-3">
                        <li>Host: <code>smtp.gmail.com</code></li>
                        <li>Port: <code>587</code></li>
                        <li>Encryption: <code>TLS</code></li>
                        <li>Username: Your Gmail address</li>
                        <li>Password: <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a></li>
                    </ul>

                    <h6 class="fw-semibold">Outlook/Office 365:</h6>
                    <ul class="small mb-3">
                        <li>Host: <code>smtp.office365.com</code></li>
                        <li>Port: <code>587</code></li>
                        <li>Encryption: <code>TLS</code></li>
                    </ul>

                    <h6 class="fw-semibold">Yahoo Mail:</h6>
                    <ul class="small">
                        <li>Host: <code>smtp.mail.yahoo.com</code></li>
                        <li>Port: <code>587</code></li>
                        <li>Encryption: <code>TLS</code></li>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body">
                    <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Important Notes</h6>
                    <ul class="small mb-0">
                        <li>Gmail requires an App Password if 2FA is enabled</li>
                        <li>Test your settings before use</li>
                        <li>Keep credentials secure</li>
                        <li>Some hosts may block certain SMTP servers</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Send Test Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="testEmailForm">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Recipient Email</label>
                        <input type="email" class="form-control" id="test_email" name="test_email" 
                               value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" required>
                        <small class="text-muted">A test email will be sent to this address</small>
                    </div>
                    <div id="testResult"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="sendTestEmail()">
                    <i class="fas fa-paper-plane me-2"></i>Send Test
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('smtp_password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

// Send test email
function sendTestEmail() {
    const email = document.getElementById('test_email').value;
    const resultDiv = document.getElementById('testResult');
    
    if (!email) {
        resultDiv.innerHTML = '<div class="alert alert-danger">Please enter an email address</div>';
        return;
    }
    
    resultDiv.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm me-2"></div>Sending...</div>';
    
    fetch('send_test_email.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'test_email=' + encodeURIComponent(email)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
    });
}
</script>

</body>
</html>
