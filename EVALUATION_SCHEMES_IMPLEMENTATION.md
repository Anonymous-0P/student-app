# Evaluation Schemes Implementation Summary

## Changes Made

### 1. Database Migration
**File**: `db/add_evaluation_schemes_table.sql`
- Created `evaluation_schemes` table to store uploaded evaluation schemes/marking schemes
- Fields include: subject_id, title, description, file_path, uploaded_by, is_active
- Supports PDF, DOC, DOCX, JPG, PNG files (max 10MB)

**To apply**: Run this SQL in phpMyAdmin:
```sql
-- Copy and paste the content from db/add_evaluation_schemes_table.sql
```

### 2. Admin Module
**File**: `admin/manage_evaluation_schemes.php` (NEW)
- Upload evaluation schemes for any subject
- View all uploaded schemes in a grid layout
- Toggle active/inactive status
- Delete schemes
- Download and view schemes

**Replaces**: `admin/answer_sheets.php` (can be deleted)

### 3. Moderator Module  
**File**: `moderator/evaluation_schemes.php` (NEW)
- View evaluation schemes for assigned subjects only
- Download and view schemes
- Read-only access (no upload/delete)

### 4. Evaluator Module
**File**: `evaluator/evaluation_schemes.php` (NEW)
- View evaluation schemes for subjects they are assigned to evaluate
- Download and view schemes
- Read-only access (no upload/delete)

### 5. Navigation Updates
**File**: `includes/header.php` (UPDATED)
- **Admin menu**: Replaced "Answer Sheets" with "Evaluation Schemes"
- **Moderator menu**: Added "Evaluation Schemes" under Submissions section
- **Evaluator menu**: Added "Evaluation Schemes" under Assignments section

## Installation Steps

1. **Run Database Migration**:
   ```sql
   -- In phpMyAdmin, select your database and run:
   CREATE TABLE IF NOT EXISTS evaluation_schemes (
       id INT AUTO_INCREMENT PRIMARY KEY,
       subject_id INT NOT NULL,
       title VARCHAR(255) NOT NULL,
       description TEXT NULL,
       file_path VARCHAR(255) NOT NULL,
       original_filename VARCHAR(255) NOT NULL,
       file_size INT NOT NULL,
       uploaded_by INT NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       is_active TINYINT(1) DEFAULT 1,
       
       INDEX idx_schemes_subject (subject_id),
       INDEX idx_schemes_active (is_active),
       INDEX idx_schemes_uploaded_by (uploaded_by),
       
       FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
       FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

2. **Create Upload Directory**:
   - Directory will be auto-created: `uploads/evaluation_schemes/`
   - Ensure write permissions (755)

3. **Delete Old File** (Optional):
   - Remove `admin/answer_sheets.php` if not needed

## Features

### Admin Features
- ✅ Upload evaluation schemes for any subject
- ✅ Add title and description
- ✅ Support multiple file types (PDF, DOC, DOCX, JPG, PNG)
- ✅ Toggle active/inactive status
- ✅ Delete schemes
- ✅ View file details (size, upload date, uploader)

### Moderator Features
- ✅ View schemes for assigned subjects only
- ✅ Download schemes for reference during review
- ✅ View scheme details

### Evaluator Features
- ✅ View schemes for assigned subjects only
- ✅ Download schemes for reference during evaluation
- ✅ View scheme details

## Access Control

- **Admin**: Full access to all schemes (upload, view, edit, delete)
- **Moderator**: Read-only access to schemes for assigned subjects
- **Evaluator**: Read-only access to schemes for subjects they evaluate

## File Upload Restrictions

- **Allowed types**: PDF, DOC, DOCX, JPG, PNG
- **Max size**: 10MB
- **Storage**: `uploads/evaluation_schemes/`
- **Naming**: `scheme_{subject_id}_{timestamp}.{extension}`

## Usage Workflow

1. **Admin uploads evaluation scheme**:
   - Go to Admin Portal → Evaluation Schemes
   - Click "Upload Scheme"
   - Select subject, add title/description, upload file
   - Scheme is automatically active

2. **Moderator views scheme**:
   - Go to Moderator Dashboard → Evaluation Schemes
   - See schemes for assigned subjects only
   - Download for review purposes

3. **Evaluator views scheme**:
   - Go to Evaluator Dashboard → Evaluation Schemes
   - See schemes for subjects they evaluate
   - Download for marking reference

## Benefits

- ✅ Centralized management of evaluation schemes
- ✅ Role-based access control
- ✅ Easy distribution to moderators and evaluators
- ✅ Version control through upload dates
- ✅ No more manual email distribution
- ✅ Active/inactive status for scheme management
