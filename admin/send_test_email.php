<?php
include('../config/config.php');
require_once('../includes/functions.php');

checkLogin('admin');

header('Content-Type: application/json');

// Check if PHPMailer exists first
if (!file_exists('../phpmailer/PHPMailer.php')) {
    echo json_encode(['success' => false, 'message' => 'PHPMailer library not found']);
    exit;
}

require '../phpmailer/PHPMailer.php';
require '../phpmailer/SMTP.php';
require '../phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_email'])) {
    $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    
    if (!$test_email) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Load mail configuration
    $mail_config_file = '../config/mail_config.php';
    if (!file_exists($mail_config_file)) {
        echo json_encode(['success' => false, 'message' => 'Mail configuration not found. Please save SMTP settings first.']);
        exit;
    }
    
    include($mail_config_file);
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($test_email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Test Email from ThetaExams';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #4f46e5;">SMTP Configuration Test</h2>
                <p>This is a test email from your ThetaExams admin panel.</p>
                <p>If you received this email, your SMTP settings are configured correctly!</p>
                <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">
                <p style="color: #6b7280; font-size: 14px;">
                    <strong>Configuration Details:</strong><br>
                    SMTP Host: ' . SMTP_HOST . '<br>
                    SMTP Port: ' . SMTP_PORT . '<br>
                    Encryption: ' . SMTP_ENCRYPTION . '<br>
                    From: ' . FROM_NAME . ' &lt;' . FROM_EMAIL . '&gt;
                </p>
                <p style="color: #6b7280; font-size: 12px; margin-top: 20px;">
                    Sent on: ' . date('F j, Y g:i A') . '
                </p>
            </div>
        ';
        $mail->AltBody = 'This is a test email from ThetaExams. If you received this, your SMTP settings are working correctly!';
        
        $mail->send();
        echo json_encode(['success' => true, 'message' => 'Test email sent successfully! Check your inbox.']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
