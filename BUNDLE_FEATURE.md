# Subject Bundle/Combo Pricing Feature

## Overview
The Subject Bundle feature allows administrators to create combo packages of multiple subjects at discounted prices. Students can purchase bundles instead of individual subjects to save money.

## Features
- ✅ Create bundles with 2 or more subjects
- ✅ Set custom bundle price with automatic discount calculation
- ✅ Configure bundle duration (access period in days)
- ✅ Track bundle purchases by students
- ✅ Activate/deactivate bundles without deletion
- ✅ View bundle statistics (purchases, savings)
- ✅ Prevent deletion of bundles with active purchases

## Database Structure

### Tables Created
1. **subject_bundles** - Stores bundle definitions
   - `id` - Primary key
   - `bundle_name` - Name of the bundle (e.g., "Science Combo")
   - `description` - Optional description
   - `bundle_price` - Total price for the bundle
   - `discount_percentage` - Discount offered
   - `duration_days` - Access duration (default: 365 days)
   - `is_active` - Active status (1=active, 0=inactive)
   - `created_at`, `updated_at` - Timestamps

2. **bundle_subjects** - Junction table linking bundles to subjects
   - `id` - Primary key
   - `bundle_id` - Foreign key to subject_bundles
   - `subject_id` - Foreign key to subjects
   - Unique constraint on (bundle_id, subject_id)

3. **student_bundle_purchases** - Tracks student purchases
   - `id` - Primary key
   - `student_id` - Foreign key to users
   - `bundle_id` - Foreign key to subject_bundles
   - `purchase_date` - When purchased
   - `expiry_date` - When access expires
   - `amount_paid` - Amount paid
   - `payment_status` - pending/completed/failed

## Installation

### Option 1: Using Migration Page (Recommended)
1. Navigate to `admin/run_bundle_migration.php`
2. Click "Run Migration Now"
3. Wait for success confirmation
4. Click "Go to Bundle Management"

### Option 2: Manual Migration
1. Open phpMyAdmin
2. Select your database
3. Go to SQL tab
4. Open `db/add_subject_bundles.sql`
5. Copy all SQL content
6. Paste and execute
7. Refresh the bundle management page

## Usage Guide

### Creating a Bundle
1. Go to **Admin > Subjects > Manage Bundles**
2. Click **Create Bundle**
3. Fill in:
   - Bundle Name (e.g., "12th Grade Science Package")
   - Description (optional)
   - Bundle Price (₹)
   - Discount Percentage
   - Duration (days)
   - Select at least 2 subjects
4. Click **Create Bundle**

### Example Bundle
**Name:** Complete Physics & Chemistry Package  
**Subjects:** Physics (₹150) + Chemistry (₹150)  
**Individual Total:** ₹300  
**Bundle Price:** ₹250  
**Discount:** 20% (Save ₹50)  
**Duration:** 365 days

### Editing a Bundle
1. Click edit icon (✏️) on any bundle
2. Modify details as needed
3. Click **Update Bundle**

### Deactivating/Activating
- Click pause icon (⏸️) to deactivate
- Click play icon (▶️) to activate
- Deactivated bundles won't be visible to students

### Deleting a Bundle
- Click delete icon (🗑️)
- **Note:** Bundles with purchases cannot be deleted
- Deactivate instead if students have purchased

## Access Points

### Admin Access
- **Subjects Page:** `admin/subjects.php` → "Manage Bundles" button
- **Bundle Management:** `admin/subject_bundles.php`
- **Migration:** `admin/run_bundle_migration.php`

### Student Access (Future Implementation)
- Bundle catalog in student dashboard
- Purchase flow integration
- View purchased bundles with expiry dates

## Business Logic

### Discount Calculation
```php
Individual Total = Sum of all subject prices in bundle
Savings = Individual Total - Bundle Price
Discount % = (Savings / Individual Total) × 100
```

### Example Calculation
- Physics: ₹150
- Chemistry: ₹150
- Math: ₹200
- **Individual Total:** ₹500
- **Bundle Price:** ₹399
- **Savings:** ₹101
- **Discount:** 20.2%

### Access Duration
- Configured per bundle (default: 365 days)
- Calculated from purchase date
- Access expires on `purchase_date + duration_days`

## Security Features
- ✅ CSRF token validation on all forms
- ✅ Input sanitization and validation
- ✅ SQL injection prevention (prepared statements)
- ✅ Admin-only access restriction
- ✅ Foreign key constraints for data integrity

## Validation Rules
1. Minimum 2 subjects per bundle
2. Bundle price must be > 0
3. Bundle name is required
4. Cannot delete bundles with purchases
5. Discount percentage: 0-100%

## Future Enhancements
- [ ] Student bundle purchase interface
- [ ] Payment gateway integration
- [ ] Bundle recommendations based on enrollment
- [ ] Seasonal/promotional bundles with time limits
- [ ] Bundle analytics dashboard
- [ ] Email notifications for bundle purchases
- [ ] Bundle renewal options
- [ ] Gift bundle functionality

## Files Added/Modified

### New Files
- `admin/subject_bundles.php` - Bundle management interface
- `admin/run_bundle_migration.php` - Migration runner
- `db/add_subject_bundles.sql` - Database schema
- `BUNDLE_FEATURE.md` - This documentation

### Modified Files
- `admin/subjects.php` - Added "Manage Bundles" button

## Support
For issues or questions about the bundle feature:
1. Check that migration was run successfully
2. Verify all 3 tables exist in database
3. Check browser console for JavaScript errors
4. Review PHP error logs

## Version
- **Version:** 1.0
- **Created:** 2024
- **Compatible with:** Student Portal v1.0+
