<?php
/**
 * Email Utility Functions using PHPMailer
 * This file contains helper functions for sending emails
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';
require_once __DIR__ . '/../config/mail_config.php';

/**
 * Send an email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $recipientName Recipient name (optional)
 * @param array $attachments Array of file paths to attach (optional)
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $body, $recipientName = '', $attachments = []) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = MAIL_CHARSET;
        
        // Debug settings
        if (MAIL_DEBUG > 0) {
            $mail->SMTPDebug = MAIL_DEBUG;
            $mail->Debugoutput = 'html';
        }
        
        // Recipients
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to, $recipientName);
        $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        
        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // Plain text version
        
        $mail->send();
        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"
        ];
    }
}

/**
 * Send welcome email to new user
 */
function sendWelcomeEmail($email, $name, $role) {
    $subject = "Welcome to ThetaExams - Account Created Successfully! 🎓";
    
    $roleSpecificContent = '';
    if ($role === 'Student' || $role === 'student') {
        $roleSpecificContent = "
            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #667eea; margin-top: 0;'>🚀 Get Started with ThetaExams</h3>
                <ul style='line-height: 2;'>
                    <li>📚 Browse and purchase exam papers</li>
                    <li>📝 Download question papers for practice</li>
                    <li>📤 Submit your answer sheets for evaluation</li>
                    <li>📊 Track your performance and grades</li>
                    <li>🎯 Get detailed feedback from evaluators</li>
                </ul>
            </div>
        ";
    }
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 14px 35px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; font-weight: 600; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3); }
            .button:hover { background: #5568d3; }
            .info-box { background: #e0e7ff; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 4px; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            ul { padding-left: 20px; }
            li { margin-bottom: 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0;'>🎓 Welcome to ThetaExams!</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Your Gateway to Excellence</p>
            </div>
            <div class='content'>
                <h2 style='color: #667eea;'>Hello " . htmlspecialchars($name) . ",</h2>
                <p style='font-size: 16px;'>Thank you for joining <strong>ThetaExams</strong>! 🎉</p>
                <p>We're excited to have you as a <strong>" . htmlspecialchars($role) . "</strong> on our platform. Your account has been created successfully and is ready to use.</p>
                
                " . $roleSpecificContent . "
                
                <div class='info-box'>
                    <strong>📧 Your Login Credentials:</strong><br>
                    <strong>Email:</strong> " . htmlspecialchars($email) . "<br>
                    <strong>Password:</strong> The password you set during registration
                </div>
                
                <p style='text-align: center; margin-top: 30px;'>
                    <a href='http://localhost/student-app/auth/login.php' class='button'>🔐 Login to Your Dashboard</a>
                </p>
                
                <div style='margin-top: 30px; padding: 20px; background: white; border-radius: 8px;'>
                    <h4 style='color: #667eea; margin-top: 0;'>📞 Need Help?</h4>
                    <p style='margin: 0;'>If you have any questions or need assistance, our support team is here to help!</p>
                </div>
                
                <p style='margin-top: 30px;'>Best regards,<br><strong>The ThetaExams Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " ThetaExams. All rights reserved.</p>
                <p style='color: #999; margin-top: 10px;'>
                    <a href='http://localhost/student-app' style='color: #667eea; text-decoration: none;'>Visit Website</a> | 
                    <a href='http://localhost/student-app/auth/login.php' style='color: #667eea; text-decoration: none;'>Login</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, $name);
}

/**
 * Send evaluation notification to student
 */
function sendEvaluationNotification($email, $name, $subjectCode, $subjectName, $marks, $maxMarks) {
    $percentage = ($marks / $maxMarks) * 100;
    $grade = $percentage >= 90 ? 'A+' : 
             ($percentage >= 80 ? 'A' : 
             ($percentage >= 70 ? 'B' : 
             ($percentage >= 60 ? 'C' : 
             ($percentage >= 50 ? 'D' : 'F'))));
    
    $subject = "Your Answer Sheet Has Been Evaluated - $subjectCode";
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .marks-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981; }
            .grade { font-size: 48px; font-weight: bold; color: #10b981; }
            .button { display: inline-block; padding: 12px 30px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Evaluation Complete!</h1>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($name) . ",</h2>
                <p>Your answer sheet for <strong>" . htmlspecialchars($subjectName) . " (" . htmlspecialchars($subjectCode) . ")</strong> has been evaluated.</p>
                
                <div class='marks-box'>
                    <h3 style='margin-top: 0;'>Your Results:</h3>
                    <p><strong>Marks Obtained:</strong> " . number_format($marks, 2) . " / " . number_format($maxMarks, 2) . "</p>
                    <p><strong>Percentage:</strong> " . number_format($percentage, 2) . "%</p>
                    <p><strong>Grade:</strong> <span class='grade'>" . $grade . "</span></p>
                </div>
                
                <p>You can view detailed feedback and the annotated answer sheet by logging into your account.</p>
                <p style='text-align: center;'>
                    <a href='http://localhost/student-app/student/view_submissions.php' class='button'>View Evaluation</a>
                </p>
                <p>Keep up the great work!</p>
                <p>Best regards,<br>The ThetaExams Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, $name);
}

/**
 * Send assignment notification to evaluator
 */
function sendAssignmentNotification($email, $name, $subjectCode, $subjectName, $studentName) {
    $subject = "New Evaluation Assignment - $subjectCode";
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .info-box { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📋 New Assignment</h1>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($name) . ",</h2>
                <p>A new answer sheet has been assigned to you for evaluation.</p>
                
                <div class='info-box'>
                    <p><strong>Subject:</strong> " . htmlspecialchars($subjectName) . "</p>
                    <p><strong>Subject Code:</strong> " . htmlspecialchars($subjectCode) . "</p>
                    <p><strong>Student:</strong> " . htmlspecialchars($studentName) . "</p>
                </div>
                
                <p>Please login to your evaluator dashboard to start the evaluation.</p>
                <p style='text-align: center;'>
                    <a href='http://localhost/student-app/evaluator/assignments.php' class='button'>View Assignment</a>
                </p>
                <p>Best regards,<br>The ThetaExams Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, $name);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $name, $resetToken) {
    $resetLink = "http://localhost/student-app/auth/reset_password.php?token=" . urlencode($resetToken);
    $subject = "Password Reset Request - ThetaExams";
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Password Reset Request</h1>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($name) . ",</h2>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <p style='text-align: center;'>
                    <a href='" . $resetLink . "' class='button'>Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #eee; padding: 10px; border-radius: 4px;'>" . $resetLink . "</p>
                
                <div class='warning'>
                    <strong>⚠️ Important:</strong> This link will expire in 1 hour. If you didn't request a password reset, please ignore this email.
                </div>
                
                <p>Best regards,<br>The ThetaExams Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, $name);
}

/**
 * Send payment confirmation email
 */
function sendPaymentConfirmationEmail($email, $name, $paymentId, $items, $total) {
    $subject = "Payment Confirmation - ThetaExams";
    
    $itemsList = '';
    foreach ($items as $item) {
        $itemsList .= "
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($item['code']) . "</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($item['name']) . "</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>₹" . number_format($item['price'], 2) . "</td>
            </tr>
        ";
    }
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            table { width: 100%; background: white; border-radius: 8px; overflow: hidden; margin: 20px 0; }
            .total-row { background: #10b981; color: white; font-weight: bold; }
            .button { display: inline-block; padding: 12px 30px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Payment Successful!</h1>
            </div>
            <div class='content'>
                <h2>Hello " . htmlspecialchars($name) . ",</h2>
                <p>Thank you for your purchase! Your payment has been processed successfully.</p>
                <p><strong>Payment ID:</strong> " . htmlspecialchars($paymentId) . "</p>
                
                <table cellpadding='0' cellspacing='0'>
                    <thead>
                        <tr style='background: #f3f4f6;'>
                            <th style='padding: 10px; text-align: left;'>Code</th>
                            <th style='padding: 10px; text-align: left;'>Subject</th>
                            <th style='padding: 10px; text-align: right;'>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        " . $itemsList . "
                    </tbody>
                    <tfoot>
                        <tr class='total-row'>
                            <td colspan='2' style='padding: 15px;'>Total</td>
                            <td style='padding: 15px; text-align: right;'>₹" . number_format($total, 2) . "</td>
                        </tr>
                    </tfoot>
                </table>
                
                <p>You can now access your purchased subjects from your dashboard.</p>
                <p style='text-align: center;'>
                    <a href='http://localhost/student-app/student/dashboard.php' class='button'>Go to Dashboard</a>
                </p>
                <p>Best regards,<br>The ThetaExams Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, $name);
}

/**
 * Send purchase confirmation email with invoice
 * 
 * @param string $email Customer email
 * @param string $name Customer name
 * @param string $paymentId Payment transaction ID
 * @param array $items Array of purchased items
 * @param float $total Total amount
 * @param string $invoicePath Path to invoice PDF (optional)
 * @return array Result of email sending
 */
function sendPurchaseConfirmationEmail($email, $name, $paymentId, $items, $total, $invoicePath = null) {
    $subject = "Purchase Confirmation - ThetaExams Order #" . $paymentId;
    
    // Build items list for email
    $itemsList = '';
    foreach ($items as $item) {
        $itemsList .= "
            <tr>
                <td style='padding: 15px; border-bottom: 1px solid #e0e0e0;'>
                    <strong>" . htmlspecialchars($item['name']) . "</strong><br>
                    <small style='color: #666;'>Subject Code: " . htmlspecialchars($item['code']) . "</small>
                </td>
                <td style='padding: 15px; border-bottom: 1px solid #e0e0e0; text-align: center;'>
                    " . $item['duration_days'] . " days
                </td>
                <td style='padding: 15px; border-bottom: 1px solid #e0e0e0; text-align: right;'>
                    ₹" . number_format($item['price'], 2) . "
                </td>
            </tr>
        ";
    }
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 650px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 14px 35px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: 600; }
            .invoice-box { background: white; border: 2px solid #667eea; border-radius: 8px; padding: 20px; margin: 20px 0; }
            .invoice-header { border-bottom: 2px solid #667eea; padding-bottom: 15px; margin-bottom: 15px; }
            .order-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; border-radius: 8px; overflow: hidden; }
            .order-table th { background: #667eea; color: white; padding: 15px; text-align: left; font-weight: 600; }
            .order-table td { padding: 15px; border-bottom: 1px solid #e0e0e0; }
            .total-row { background: #f0f4ff; font-weight: bold; font-size: 18px; }
            .info-box { background: #e0e7ff; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 4px; }
            .success-badge { display: inline-block; background: #10b981; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0;'>🎉 Purchase Successful!</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>Thank you for your order</p>
            </div>
            <div class='content'>
                <h2 style='color: #667eea;'>Hello " . htmlspecialchars($name) . ",</h2>
                <p style='font-size: 16px;'>Thank you for purchasing from <strong>ThetaExams</strong>! 🎓</p>
                <p>Your payment has been successfully processed and your exams are now available in your dashboard.</p>
                
                <div class='invoice-box'>
                    <div class='invoice-header'>
                        <h3 style='margin: 0; color: #667eea;'>📄 Invoice Details</h3>
                    </div>
                    <table style='width: 100%; margin-bottom: 15px;'>
                        <tr>
                            <td style='padding: 8px 0; color: #666;'><strong>Order ID:</strong></td>
                            <td style='padding: 8px 0; text-align: right;'>" . htmlspecialchars($paymentId) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #666;'><strong>Date:</strong></td>
                            <td style='padding: 8px 0; text-align: right;'>" . date('d M Y, h:i A') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #666;'><strong>Status:</strong></td>
                            <td style='padding: 8px 0; text-align: right;'>
                                <span class='success-badge'>✓ Paid</span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <h3 style='color: #667eea;'>📚 Purchased Items</h3>
                <table class='order-table'>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th style='text-align: center;'>Access Duration</th>
                            <th style='text-align: right;'>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        " . $itemsList . "
                    </tbody>
                    <tfoot>
                        <tr class='total-row'>
                            <td colspan='2' style='padding: 15px;'>Total Amount</td>
                            <td style='padding: 15px; text-align: right;'>₹" . number_format($total, 2) . "</td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class='info-box'>
                    <strong>📝 What's Next?</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>Access your purchased exams from the dashboard</li>
                        <li>Download question papers</li>
                        <li>Submit your answer sheets for evaluation</li>
                        <li>Track your performance and get detailed feedback</li>
                    </ul>
                </div>
                
                " . ($invoicePath ? "<p style='margin-top: 20px;'><strong>📎 Note:</strong> Your detailed invoice is attached to this email for your records.</p>" : "") . "
                
                <p style='text-align: center; margin-top: 30px;'>
                    <a href='http://localhost/student-app/student/dashboard.php' class='button'>📖 Go to Dashboard</a>
                </p>
                
                <div style='margin-top: 30px; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e0e0e0;'>
                    <h4 style='color: #667eea; margin-top: 0;'>💡 Need Help?</h4>
                    <p style='margin: 0;'>If you have any questions about your purchase or need assistance, feel free to contact our support team.</p>
                </div>
                
                <p style='margin-top: 30px;'>Best regards,<br><strong>The ThetaExams Team</strong></p>
            </div>
            <div style='text-align: center; margin-top: 30px; color: #666; font-size: 12px;'>
                <p>This is an automated confirmation email.</p>
                <p>&copy; " . date('Y') . " ThetaExams. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email with optional invoice attachment
    $attachments = [];
    if ($invoicePath && file_exists($invoicePath)) {
        $attachments[] = $invoicePath;
    }
    
    return sendEmail($email, $subject, $body, $name, $attachments);
}

/**
 * Generate PDF invoice for purchase
 * 
 * @param string $paymentId Payment transaction ID
 * @param array $userInfo User information (name, email)
 * @param array $items Array of purchased items
 * @param float $total Total amount
 * @param array $billingInfo Billing information from payment form
 * @return string|false Path to generated PDF or false on failure
 */
function generateInvoicePDF($paymentId, $userInfo, $items, $total, $billingInfo) {
    try {
        // Create invoices directory if it doesn't exist
        $invoiceDir = __DIR__ . '/../uploads/invoices/';
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0777, true);
        }
        
        $invoiceFileName = 'invoice_' . $paymentId . '_' . time() . '.pdf';
        $invoicePath = $invoiceDir . $invoiceFileName;
        
        // Build HTML content for PDF
        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($item['name']) . "<br><small style='color: #666;'>Code: " . htmlspecialchars($item['code']) . "</small></td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: center;'>" . $item['duration_days'] . " days</td>
                    <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>₹" . number_format($item['price'], 2) . "</td>
                </tr>
            ";
        }
        
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #667eea; padding-bottom: 20px; }
                .invoice-title { color: #667eea; font-size: 32px; margin: 0; }
                .company-name { color: #764ba2; font-size: 24px; margin: 10px 0; }
                .invoice-info { margin: 30px 0; }
                .info-section { margin-bottom: 20px; }
                .info-section h3 { color: #667eea; margin-bottom: 10px; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
                .invoice-table { width: 100%; border-collapse: collapse; margin: 30px 0; }
                .invoice-table th { background: #667eea; color: white; padding: 12px; text-align: left; }
                .invoice-table td { padding: 10px; border-bottom: 1px solid #ddd; }
                .total-row { background: #f0f4ff; font-weight: bold; font-size: 18px; }
                .footer { margin-top: 50px; text-align: center; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
                .status-badge { background: #10b981; color: white; padding: 5px 15px; border-radius: 15px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='invoice-header'>
                <div class='company-name'>ThetaExams</div>
                <div class='invoice-title'>INVOICE</div>
                <div style='margin-top: 10px; color: #666;'>Tax Invoice</div>
            </div>
            
            <div class='invoice-info'>
                <table style='width: 100%; margin-bottom: 30px;'>
                    <tr>
                        <td style='width: 50%; vertical-align: top;'>
                            <div class='info-section'>
                                <h3>Invoice To:</h3>
                                <strong>" . htmlspecialchars($userInfo['name']) . "</strong><br>
                                " . htmlspecialchars($userInfo['email']) . "<br>
                                " . (isset($billingInfo['billing_name']) ? htmlspecialchars($billingInfo['billing_name']) : '') . "
                            </div>
                        </td>
                        <td style='width: 50%; vertical-align: top; text-align: right;'>
                            <div class='info-section'>
                                <h3>Invoice Details:</h3>
                                <strong>Invoice #:</strong> " . htmlspecialchars($paymentId) . "<br>
                                <strong>Date:</strong> " . date('d M Y') . "<br>
                                <strong>Time:</strong> " . date('h:i A') . "<br>
                                <strong>Status:</strong> <span class='status-badge'>PAID</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <table class='invoice-table'>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style='text-align: center;'>Duration</th>
                        <th style='text-align: right;'>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    " . $itemsHtml . "
                </tbody>
                <tfoot>
                    <tr class='total-row'>
                        <td colspan='2' style='padding: 15px;'>Total Amount</td>
                        <td style='padding: 15px; text-align: right;'>₹" . number_format($total, 2) . "</td>
                    </tr>
                </tfoot>
            </table>
            
            <div style='margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 8px;'>
                <h3 style='color: #667eea; margin-top: 0;'>Payment Information</h3>
                <p><strong>Payment Method:</strong> Credit/Debit Card</p>
                <p><strong>Transaction ID:</strong> " . htmlspecialchars($paymentId) . "</p>
                <p><strong>Payment Status:</strong> <span style='color: #10b981; font-weight: bold;'>✓ Completed</span></p>
            </div>
            
            <div style='margin-top: 30px; padding: 15px; background: #e0e7ff; border-left: 4px solid #667eea; border-radius: 4px;'>
                <strong>Note:</strong> This is a computer-generated invoice and does not require a signature. 
                All purchased exam papers are now accessible from your dashboard.
            </div>
            
            <div class='footer'>
                <p><strong>ThetaExams - Your Gateway to Excellence</strong></p>
                <p style='font-size: 12px; color: #999;'>Thank you for choosing ThetaExams!</p>
                <p style='font-size: 12px; color: #999;'>&copy; " . date('Y') . " ThetaExams. All rights reserved.</p>
            </div>
        </body>
        </html>
        ";
        
        // Try to use TCPDF if available, otherwise use HTML
        $tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        
        if (file_exists($tcpdfPath)) {
            try {
                require_once($tcpdfPath);
                
                // Create PDF using TCPDF
                $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetCreator('ThetaExams');
                $pdf->SetAuthor('ThetaExams');
                $pdf->SetTitle('Invoice - ' . $paymentId);
                $pdf->SetSubject('Purchase Invoice');
                
                // Remove default header/footer
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                
                // Set margins
                $pdf->SetMargins(15, 15, 15);
                $pdf->SetAutoPageBreak(true, 15);
                
                // Add page
                $pdf->AddPage();
                
                // Write HTML content
                $pdf->writeHTML($htmlContent, true, false, true, false, '');
                
                // Save PDF
                $pdf->Output($invoicePath, 'F');
                
                return $invoicePath;
            } catch (Exception $e) {
                error_log("TCPDF generation failed: " . $e->getMessage());
            }
        }
        
        // Fallback: Save as HTML invoice (can be printed to PDF by user)
        $htmlInvoicePath = str_replace('.pdf', '.html', $invoicePath);
        file_put_contents($htmlInvoicePath, $htmlContent);
        return $htmlInvoicePath;
        
    } catch (Exception $e) {
        error_log("Invoice generation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Send evaluation acceptance notification email to student
 * 
 * @param string $email Student email
 * @param string $name Student name
 * @param string $subjectCode Subject code
 * @param string $subjectName Subject name
 * @param float $marksObtained Marks obtained (not displayed in email)
 * @param float $maxMarks Maximum marks (not displayed in email)
 * @param float $percentage Percentage scored (not displayed in email)
 * @param string $grade Grade obtained (not displayed in email)
 * @param string $remarks Evaluator remarks/feedback (not displayed in email)
 * @param int $submissionId Submission ID
 * @return array Result of email sending
 */
function sendEvaluationCompleteEmail($email, $name, $subjectCode, $subjectName, $marksObtained, $maxMarks, $percentage, $grade, $remarks, $submissionId) {
    $subject = "✅ Answer Sheet Accepted for Evaluation - " . $subjectCode . " | ThetaExams";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 650px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 14px 35px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: 600; }
            .info-card { background: white; border: 2px solid #e5e7eb; border-radius: 12px; padding: 25px; margin: 20px 0; }
            .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
            .info-row:last-child { border-bottom: none; }
            .info-label { color: #6b7280; font-weight: 500; }
            .info-value { color: #1f2937; font-weight: 600; }
            .highlight-box { background: #e0e7ff; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px; }
            .icon-badge { display: inline-block; width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 40px; line-height: 80px; text-align: center; border-radius: 50%; margin: 20px auto; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 28px;'>✅ Answer Sheet Accepted!</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>Your submission has been accepted for evaluation</p>
            </div>
            <div class='content'>
                <h2 style='color: #667eea;'>Hello " . htmlspecialchars($name) . ",</h2>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <div class='icon-badge'>✓</div>
                </div>
                
                <p style='font-size: 16px; text-align: center;'>Great news! Your answer sheet for <strong>" . htmlspecialchars($subjectName) . " (" . htmlspecialchars($subjectCode) . ")</strong> has been accepted and processed by our evaluator. 🎓</p>
                
                <div class='info-card'>
                    <h3 style='color: #667eea; margin-top: 0;'>� Submission Details</h3>
                    <div class='info-row'>
                        <span class='info-label'>Subject:</span>
                        <span class='info-value'>" . htmlspecialchars($subjectName) . "</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>Subject Code:</span>
                        <span class='info-value'>" . htmlspecialchars($subjectCode) . "</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>Status:</span>
                        <span class='info-value' style='color: #10b981;'>✓ Evaluation Complete</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>Date:</span>
                        <span class='info-value'>" . date('d M Y, h:i A') . "</span>
                    </div>
                </div>
                
                <div class='highlight-box'>
                    <strong style='color: #667eea; font-size: 16px;'>📝 What's Next?</strong>
                    <ul style='margin: 10px 0 0 0; padding-left: 20px;'>
                        <li>Login to your dashboard to view the evaluation results</li>
                        <li>Check your marks and detailed feedback from the evaluator</li>
                        <li>Review areas of improvement</li>
                        <li>Continue practicing to enhance your performance</li>
                    </ul>
                </div>
                
                <p style='text-align: center; margin-top: 30px;'>
                    <a href='http://localhost/student-app/student/dashboard.php' class='button'>� View Dashboard</a>
                </p>
                
                <div style='margin-top: 30px; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e5e7eb;'>
                    <h4 style='color: #667eea; margin-top: 0;'>💡 Need Help?</h4>
                    <p style='margin: 0;'>If you have any questions about your evaluation, please contact our support team. We're here to help you succeed!</p>
                </div>
                
                <p style='margin-top: 30px;'>Best regards,<br><strong>The ThetaExams Team</strong></p>
            </div>
            <div style='text-align: center; margin-top: 30px; color: #666; font-size: 12px;'>
                <p>This is an automated notification email.</p>
                <p>&copy; " . date('Y') . " ThetaExams. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body, $name);
}
