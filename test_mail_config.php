<?php
/**
 * PHPMailer Configuration Test
 * Use this file to test your email configuration
 */

require_once('config/config.php');
require_once('includes/mail_helper.php');

// Prevent direct access in production
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['confirm'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Configuration Test - ThetaExams</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">📧 PHPMailer Configuration Test</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>📋 Before Testing:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Open <code>config/mail_config.php</code></li>
                                    <li>Update your SMTP settings (host, username, password)</li>
                                    <li>If using Gmail, enable 2FA and create an App Password</li>
                                    <li>Save the file and return here</li>
                                </ol>
                            </div>

                            <div class="alert alert-warning">
                                <strong>⚠️ Current Configuration:</strong>
                                <ul class="mb-0 mt-2">
                                    <li><strong>Host:</strong> <?= MAIL_HOST ?></li>
                                    <li><strong>Port:</strong> <?= MAIL_PORT ?></li>
                                    <li><strong>Username:</strong> <?= MAIL_USERNAME ?></li>
                                    <li><strong>From:</strong> <?= MAIL_FROM_ADDRESS ?> (<?= MAIL_FROM_NAME ?>)</li>
                                    <li><strong>Encryption:</strong> <?= MAIL_ENCRYPTION ?></li>
                                </ul>
                            </div>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="test_email" class="form-label">Test Email Address</label>
                                    <input type="email" class="form-control" id="test_email" name="test_email" 
                                           placeholder="Enter your email to receive a test message" required>
                                    <small class="text-muted">A test email will be sent to this address</small>
                                </div>

                                <div class="mb-3">
                                    <label for="test_name" class="form-label">Recipient Name</label>
                                    <input type="text" class="form-control" id="test_name" name="test_name" 
                                           placeholder="Enter recipient name" value="Test User">
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane"></i> Send Test Email
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-home"></i> Back to Home
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">📚 Available Email Functions</h5>
                        </div>
                        <div class="card-body">
                            <ul>
                                <li><code>sendEmail($to, $subject, $body, $name, $attachments)</code> - Generic email sender</li>
                                <li><code>sendWelcomeEmail($email, $name, $role)</code> - Welcome new users</li>
                                <li><code>sendEvaluationNotification($email, $name, $code, $subject, $marks, $max)</code> - Notify evaluation complete</li>
                                <li><code>sendAssignmentNotification($email, $name, $code, $subject, $student)</code> - Notify evaluator of assignment</li>
                                <li><code>sendPasswordResetEmail($email, $name, $token)</code> - Password reset link</li>
                                <li><code>sendPaymentConfirmationEmail($email, $name, $paymentId, $items, $total)</code> - Payment confirmation</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://kit.fontawesome.com/your-kit-id.js" crossorigin="anonymous"></script>
    </body>
    </html>
    <?php
    exit;
}

// Process test email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    $testName = htmlspecialchars($_POST['test_name'] ?? 'Test User');
    
    if (!$testEmail) {
        $error = "Invalid email address";
    } else {
        // Send test email
        $subject = "Test Email from ThetaExams";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Test Email Successful!</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($testName) . ",</h2>
                    <div class='success'>
                        <strong>✅ Success!</strong> Your PHPMailer configuration is working correctly.
                    </div>
                    <p>This is a test email from ThetaExams to verify that your email configuration is set up properly.</p>
                    <p><strong>Configuration Details:</strong></p>
                    <ul>
                        <li>SMTP Host: " . MAIL_HOST . "</li>
                        <li>SMTP Port: " . MAIL_PORT . "</li>
                        <li>Encryption: " . MAIL_ENCRYPTION . "</li>
                        <li>From: " . MAIL_FROM_NAME . " &lt;" . MAIL_FROM_ADDRESS . "&gt;</li>
                    </ul>
                    <p>Sent at: " . date('F j, Y g:i:s A') . "</p>
                    <p>If you received this email, your email system is configured correctly and ready to use!</p>
                    <p>Best regards,<br>The ThetaExams Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $result = sendEmail($testEmail, $subject, $body, $testName);
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Test Result - ThetaExams</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <h4 class="alert-heading">❌ Error</h4>
                            <p><?= $error ?></p>
                        </div>
                    <?php elseif ($result['success']): ?>
                        <div class="alert alert-success">
                            <h4 class="alert-heading">✅ Success!</h4>
                            <p><?= $result['message'] ?></p>
                            <hr>
                            <p class="mb-0">Test email sent to: <strong><?= htmlspecialchars($testEmail) ?></strong></p>
                            <p class="mb-0 mt-2"><small>Check your inbox (and spam folder) for the test email.</small></p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h4 class="alert-heading">❌ Failed</h4>
                            <p><?= $result['message'] ?></p>
                            <hr>
                            <p class="mb-0"><strong>Common Issues:</strong></p>
                            <ul>
                                <li>Incorrect SMTP credentials</li>
                                <li>Gmail: Need to enable 2FA and use App Password</li>
                                <li>Firewall blocking SMTP port</li>
                                <li>Wrong SMTP host or port</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <a href="test_mail_config.php" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Try Again
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
