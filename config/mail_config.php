<?php
/**
 * PHPMailer Configuration File
 * Configure your SMTP settings here
 */

// SMTP Configuration - Hostinger
define('MAIL_HOST', ' ');          // Hostinger SMTP server
define('MAIL_PORT', 465);                           // SMTP port (587 for TLS, 465 for SSL)
define('MAIL_USERNAME', ' '); // Your Hostinger email address
define('MAIL_PASSWORD', ' ');     // Your Hostinger email password
define('MAIL_ENCRYPTION', 'ssl');                   // Encryption type: 'tls' or 'ssl'
define('MAIL_FROM_ADDRESS', ' '); // From email address (MUST match MAIL_USERNAME)
define('MAIL_FROM_NAME', '');              // From name

// Email Settings
define('MAIL_DEBUG', 0);                            // Debug level: 0 = off, 1 = client, 2 = server
define('MAIL_CHARSET', 'UTF-8');                    // Email character set

/**
 * HOSTINGER SMTP Setup Instructions
 * 
 * To use Hostinger email:
 * 1. Log in to your Hostinger control panel (hPanel)
 * 2. Go to "Emails" section
 * 3. Create an email account if you haven't already (e.g., noreply@yourdomain.com)
 * 4. Use these settings:
 *    - SMTP Server: smtp.hostinger.com
 *    - SMTP Port: 587 (TLS) or 465 (SSL)
 *    - Username: Your full email address (e.g., noreply@yourdomain.com)
 *    - Password: Your email account password
 *    - Encryption: TLS (recommended) or SSL
 * 
 * Alternative Hostinger SMTP settings (if above doesn't work):
 * - SMTP Host: smtp.titan.email (for Titan email)
 * - SMTP Port: 587 or 465
 * 
 * For other email providers:
 * - Gmail: smtp.gmail.com, port 587, TLS (requires App Password)
 * - Outlook/Hotmail: smtp.office365.com, port 587, TLS
 * - Yahoo: smtp.mail.yahoo.com, port 587, TLS
 */
