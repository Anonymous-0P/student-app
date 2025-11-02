# Annotated PDF Upload Feature - Implementation Guide

## Overview
Evaluators can now download student answer sheets, annotate them using professional PDF tools, and upload the annotated versions back to the system. Students will see these annotated PDFs in their dashboard.

## How It Works

### For Evaluators:

1. **Navigate to Evaluation Page**
   - Go to `evaluator/evaluate.php?id={submission_id}`
   - You'll see the student's answer sheet in the PDF viewer

2. **Download the PDF**
   - Click the "Download PDF to Annotate" button above the PDF viewer
   - Save the PDF to your computer

3. **Annotate the PDF**
   - Use any PDF annotation tool:
     - ✅ **Adobe Acrobat Reader** (Free) - Recommended
     - ✅ **Foxit Reader** (Free)
     - ✅ **PDF-XChange Editor** (Free)
     - ✅ **Xodo PDF** (Free, mobile-friendly)
     - ✅ **Preview** (Mac built-in)
   - Add comments, highlights, corrections, marks, etc.
   - Save the annotated PDF

4. **Upload Annotated PDF**
   - Scroll to the "Upload Annotated PDF" section in the evaluation form
   - Click "Choose File" and select your annotated PDF
   - File validation:
     - ✅ Must be PDF format
     - ✅ Max size: 10MB
   - Continue filling out the evaluation form

5. **Submit Evaluation**
   - Complete marks and remarks as usual
   - Click "Submit Evaluation"
   - Annotated PDF is automatically saved and linked to the submission

### For Students:

1. **View Evaluated Submissions**
   - Go to Student Dashboard (`student/dashboard.php`)
   - Look for "Evaluation Results" section

2. **Access Annotated PDF**
   - Evaluated submissions show a green button: **"✓ Evaluated"**
   - Click this button to download the annotated PDF
   - Also available in:
     - `student/view_submissions.php` - All submissions page
     - `student/view_pdf.php` - Individual PDF viewer

## Technical Implementation

### Backend (PHP)

**File:** `evaluator/evaluate.php`

```php
// Handle file upload
if (isset($_FILES['annotated_pdf']) && $_FILES['annotated_pdf']['error'] === UPLOAD_ERR_OK) {
    $uploaded_file = $_FILES['annotated_pdf'];
    
    // Validate PDF type
    $file_type = mime_content_type($uploaded_file['tmp_name']);
    if ($file_type === 'application/pdf') {
        // Create directory if needed
        $upload_dir = dirname(__DIR__) . '/uploads/annotated_pdfs';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }
        
        // Generate unique filename
        $filename = 'annotated_' . $submission_id . '_' . time() . '.pdf';
        $file_path = $upload_dir . '/' . $filename;
        $relative_path = 'uploads/annotated_pdfs/' . $filename;
        
        // Save file
        if (move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
            $annotated_pdf_path = $relative_path;
        }
    }
}

// Update database
UPDATE submissions SET 
    annotated_pdf_url = ?,
    status = 'evaluated',
    evaluated_at = NOW()
WHERE id = ?
```

### Frontend (HTML/JavaScript)

**File Input:**
```html
<input type="file" 
       class="form-control" 
       id="annotated_pdf" 
       name="annotated_pdf" 
       accept=".pdf,application/pdf"
       onchange="validatePdfFile(this)">
```

**Validation Function:**
```javascript
function validatePdfFile(input) {
    const file = input.files[0];
    const fileSize = file.size / 1024 / 1024; // MB
    const fileExtension = file.name.split('.').pop().toLowerCase();
    
    // Check extension
    if (fileExtension !== 'pdf') {
        alert('Please upload a PDF file only.');
        input.value = '';
        return false;
    }
    
    // Check size (max 10MB)
    if (fileSize > 10) {
        alert('File size must be less than 10MB');
        input.value = '';
        return false;
    }
    
    return true;
}
```

### Database Schema

**Column:** `annotated_pdf_url` in `submissions` table

```sql
ALTER TABLE submissions 
ADD COLUMN annotated_pdf_url VARCHAR(255) NULL 
AFTER pdf_url;
```

### Student Dashboard Display

**File:** `student/dashboard.php`

```php
<?php if(!empty($row['annotated_pdf_url']) && file_exists('../' . $row['annotated_pdf_url'])): ?>
    <a href="../<?= htmlspecialchars($row['annotated_pdf_url']) ?>" 
       target="_blank"
       class="btn btn-sm btn-success" 
       title="Download Annotated Answer Sheet">
        ✓ Evaluated
    </a>
<?php endif; ?>
```

## File Structure

```
student-app/
├── uploads/
│   ├── annotated_pdfs/          # Annotated PDFs storage
│   │   ├── annotated_88_1761992856.pdf
│   │   └── annotated_89_1761993001.pdf
│   └── pdfs/                    # Original submissions
├── evaluator/
│   └── evaluate.php             # Evaluation page with upload
└── student/
    ├── dashboard.php            # Shows annotated PDF button
    ├── view_submissions.php     # All submissions with buttons
    └── view_pdf.php             # Individual PDF viewer
```

## Security Features

1. **File Type Validation**
   - Server-side MIME type check
   - Extension validation (.pdf only)

2. **File Size Limit**
   - 10MB maximum
   - Prevents large file uploads

3. **Unique Filenames**
   - Format: `annotated_{submission_id}_{timestamp}.pdf`
   - Prevents overwrites and collisions

4. **Access Control**
   - Only assigned evaluators can upload
   - Students can only view their own submissions

5. **File Existence Check**
   - Verifies file exists before showing download button
   - Graceful handling of missing files

## Testing

**Test Page:** `test_annotated_upload.php`

1. Checks directory exists and is writable
2. Verifies database column exists
3. Lists existing annotated PDFs
4. Provides upload test form

## Troubleshooting

### Upload not working?
- Check directory permissions: `chmod 775 uploads/annotated_pdfs`
- Verify PHP upload settings in `php.ini`:
  ```ini
  upload_max_filesize = 10M
  post_max_size = 10M
  ```

### Button not showing for students?
- Verify `annotated_pdf_url` column exists in database
- Check file path in database matches actual file location
- Ensure file permissions allow reading

### File too large error?
- Reduce PDF file size using compression tools
- Or increase PHP upload limits (not recommended beyond 10MB)

## Future Enhancements (Optional)

1. **Direct Annotation in Browser**
   - PDF.js with annotation layer
   - Complex implementation (~40+ hours)

2. **Annotation History**
   - Track multiple versions
   - Version comparison

3. **Bulk Upload**
   - Upload multiple annotated PDFs at once
   - Batch processing

4. **Mobile Annotation**
   - Touch-optimized annotation tools
   - Responsive PDF viewer

## Summary

✅ **Simple & Reliable:** Uses familiar tools (Adobe, Foxit, etc.)  
✅ **Professional Quality:** Evaluators can use advanced annotation features  
✅ **Backend Ready:** Database and file handling fully implemented  
✅ **Student Friendly:** Easy download buttons in dashboard  
✅ **Secure:** File validation and access control in place  

This solution provides professional annotation capabilities without complex JavaScript implementations or expensive third-party libraries.
