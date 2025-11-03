# Purchase Confirmation Email & Invoice System

## Overview
When a student purchases an exam, they automatically receive:
1. **Purchase confirmation email** with order details
2. **Invoice attachment** (PDF or HTML format)
3. **Access to view/download invoice** from the dashboard

## Features Implemented

### 1. Automated Email Notification
- **Trigger**: Automatically sent after successful payment
- **Content**: 
  - Order confirmation with payment ID
  - List of purchased subjects
  - Total amount paid
  - Invoice attached
  - Quick link to dashboard
- **Sender**: copilot@thetadynamics.in (via Hostinger SMTP)

### 2. Invoice Generation
- **Format**: PDF (if TCPDF available) or HTML (fallback)
- **Location**: `uploads/invoices/`
- **Filename**: `invoice_[PAYMENT_ID]_[TIMESTAMP].pdf/.html`
- **Content**:
  - Company branding (ThetaExams)
  - Invoice number (Payment ID)
  - Customer details (name, email)
  - Itemized list of purchases
  - Payment information
  - Total amount
  - Date and time of purchase
  - Payment status badge

### 3. Invoice Viewer
- **URL**: `student/view_invoice.php?payment_id=[PAYMENT_ID]`
- **Features**:
  - Print button for physical copy
  - Secure access (only owner can view)
  - On-demand invoice regeneration
  - Mobile-responsive design

## Files Modified/Created

### Modified Files
1. **student/process_payment.php**
   - Added email sending after successful payment
   - Integrated invoice generation
   - Stores email status in session

2. **student/payment_success.php**
   - Added email confirmation notice
   - Added "View Invoice" button
   - Updated information section

3. **includes/mail_helper.php**
   - Added `sendPurchaseConfirmationEmail()` function
   - Added `generateInvoicePDF()` function
   - Professional HTML email template
   - Invoice PDF/HTML generation logic

### New Files
1. **student/view_invoice.php**
   - Invoice viewer page
   - Print functionality
   - Secure access validation

## Technical Details

### Email Template Features
- **Design**: Professional gradient design with ThetaExams branding
- **Responsive**: Mobile-friendly HTML email
- **Elements**:
  - Success header with checkmark icon
  - Invoice details box
  - Itemized purchase table
  - Payment status badge
  - Call-to-action button
  - Help/support information

### Invoice Features
- **Generation Methods**:
  1. **Primary**: TCPDF library (if installed)
  2. **Fallback**: HTML file (can be printed to PDF)
- **Security**: Only accessible by purchase owner
- **Storage**: Persistent storage in `uploads/invoices/`
- **On-Demand**: Auto-generates if missing

### Database Integration
- Uses existing `payment_transactions` table
- Uses existing `purchased_subjects` table
- No schema changes required

## Email Configuration
Uses existing Hostinger SMTP settings from `config/mail_config.php`:
- **Host**: smtp.hostinger.com
- **Port**: 465 (SSL)
- **From**: copilot@thetadynamics.in
- **Username**: copilot@thetadynamics.in

## User Flow

### Purchase Process
```
1. Student adds exams to cart
2. Proceeds to checkout
3. Fills billing information
4. Submits payment
   ↓
5. Payment processed successfully
   ↓
6. Database updated:
   - Payment transaction recorded
   - Purchased subjects added
   - Cart cleared
   ↓
7. Invoice generated and saved
   ↓
8. Email sent with invoice attachment
   ↓
9. Redirected to success page
   ↓
10. Can view/download invoice
    Can access purchased exams
```

### Email Content
```
Subject: Purchase Confirmation - ThetaExams Order #[PAYMENT_ID]

Content:
- Welcome message
- Order summary (ID, date, status)
- Purchased items table
- Total amount
- Invoice attachment
- Dashboard link
- Support information
```

### Invoice Details
```
Header:
- Company Name: ThetaExams
- Document Type: Tax Invoice
- Invoice Number: [PAYMENT_ID]

Body:
- Customer Information
- Invoice Details (date, time, status)
- Itemized Purchase List
- Payment Information
- Total Amount

Footer:
- Terms and notes
- Company information
```

## Testing Checklist

### Email Testing
- [ ] Email sends successfully
- [ ] Email arrives in inbox (not spam)
- [ ] Email displays correctly in desktop email clients
- [ ] Email displays correctly in mobile email clients
- [ ] Invoice attachment opens correctly
- [ ] All links work correctly

### Invoice Testing
- [ ] Invoice generates without errors
- [ ] Invoice displays correctly
- [ ] Invoice shows correct information
- [ ] Invoice is printable
- [ ] Only owner can access invoice
- [ ] Invoice regenerates if missing

### Integration Testing
- [ ] Complete purchase flow works
- [ ] Email sent after successful payment
- [ ] Invoice attached to email
- [ ] Success page shows email confirmation
- [ ] View Invoice button works
- [ ] Multiple purchases create separate invoices

## Production Deployment

### Before Going Live
1. **Update URLs**: Change all `localhost/student-app` URLs to production domain
   - `includes/mail_helper.php`: Email template links
   - `student/view_invoice.php`: Dashboard links

2. **Test Email Delivery**: Verify emails don't go to spam
   - Check SPF records
   - Check DKIM configuration
   - Test with multiple email providers

3. **Create Invoice Directory**: Ensure proper permissions
   ```bash
   mkdir uploads/invoices
   chmod 755 uploads/invoices
   ```

4. **Optional: Install TCPDF** for PDF generation
   ```bash
   composer require tecnickcom/tcpdf
   ```

5. **Monitor**: Check email sending logs and invoice generation

### Email Deliverability Tips
- Use consistent sender name and email
- Include unsubscribe option for marketing emails
- Keep HTML clean and valid
- Test with spam checkers
- Monitor bounce rates

## Troubleshooting

### Email Not Sending
1. Check SMTP credentials in `config/mail_config.php`
2. Verify Hostinger SMTP is accessible
3. Check PHP error logs
4. Enable SMTP debug mode (set MAIL_DEBUG = 2)

### Invoice Not Generating
1. Check `uploads/invoices/` directory exists
2. Verify write permissions on directory
3. Check PHP error logs
4. Try HTML fallback if TCPDF fails

### Invoice Not Displaying
1. Verify payment_id is correct
2. Check student_id matches payment owner
3. Ensure invoice file exists in uploads/invoices/
4. Check browser console for errors

## Future Enhancements

### Potential Improvements
1. **Email Features**
   - Add email preferences (allow users to opt-out)
   - Send reminder emails before access expires
   - Send evaluation completion notifications

2. **Invoice Features**
   - Add company GST/Tax information
   - Include QR code for verification
   - Support multiple currencies
   - Add payment method details

3. **System Features**
   - Invoice history page
   - Bulk invoice download
   - Email resend option
   - SMS notifications

4. **Analytics**
   - Track email open rates
   - Monitor invoice downloads
   - Analyze purchase patterns

## Security Considerations

### Current Security Measures
- Invoice access restricted to purchase owner
- Payment ID verification
- Student ID verification
- Secure file storage

### Recommendations
- Implement invoice expiry for old invoices
- Add digital signature for authenticity
- Log all invoice access attempts
- Implement rate limiting for invoice generation

## Support

### Common User Questions

**Q: I didn't receive the email**
A: Check spam folder, verify email address, contact support for resend

**Q: Invoice won't open**
A: Use "View Invoice" button on success page, try different browser

**Q: Need duplicate invoice**
A: Access from payment history or contact support with payment ID

**Q: Wrong information on invoice**
A: Contact support immediately with payment ID

## Summary
The purchase confirmation email and invoice system provides students with:
- Professional purchase confirmation
- Detailed invoice for records
- Easy access to purchase history
- Improved user experience
- Better support for queries

All emails are sent automatically via Hostinger SMTP with invoices attached in PDF or HTML format.
