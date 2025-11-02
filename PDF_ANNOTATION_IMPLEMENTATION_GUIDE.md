# PDF Annotation Implementation Guide

## Current Status
✅ Database column `annotated_pdf_url` ready (run migration first)
✅ Backend PHP code updated to receive and save annotated PDFs
✅ Backend expects base64 encoded PDF data in POST parameter `annotated_pdf_data`

## What's Needed

The current `evaluator/evaluate.php` uses a simple iframe to display PDFs, which **does NOT support annotation**. To enable the annotation feature shown in your screenshot, you need to:

### Option 1: Use PSPDFKit (Recommended - Commercial)
- Professional PDF annotation library
- Drag & drop annotations, drawing tools, text markup
- Easy integration
- **Requires license** (~$6000/year for web SDK)

### Option 2: Use PDF.js with Annotation.js (Free)
- Open-source solution
- Requires more development work
- Good for basic annotations

### Option 3: Use Xodo/Apryse WebViewer (Commercial)
- Professional features
- Good documentation
- Requires license

## Implementation Steps (Using PDF.js + Custom Canvas Drawing)

Since you showed a screenshot with red handwritten markup, here's a practical free solution:

### Step 1: Run the Database Migration
First, execute this in your browser:
```
http://localhost/student-app/add_annotated_pdf_column_script.php
```

This will add the `annotated_pdf_url` column to the submissions table.

### Step 2: Install PDF.js Library
Download PDF.js from: https://mozilla.github.io/pdf.js/getting_started/

Place files in: `student-app/assets/js/pdfjs/`

### Step 3: Replace iframe with Canvas-based PDF Viewer
Replace the current iframe in `evaluator/evaluate.php` (around line 429) with a canvas-based viewer that supports drawing.

### Step 4: Add Drawing Tools
Implement canvas drawing functionality:
- Pen tool for freehand drawing
- Color picker (red, blue, green, black)
- Eraser tool
- Clear all button
- Save annotations button

### Step 5: Capture Annotated PDF
When evaluator clicks "Submit Evaluation":
1. Merge the original PDF with canvas annotations
2. Convert to base64
3. Send via POST parameter `annotated_pdf_data`
4. Backend saves it automatically (already implemented)

### Step 6: Display in Student Dashboard
Update `student/dashboard.php` or submission view to show:
```php
<?php if (!empty($submission['annotated_pdf_url'])): ?>
    <a href="<?php echo $submission['annotated_pdf_url']; ?>" 
       class="btn btn-primary" target="_blank">
        <i class="fas fa-file-download"></i> Download Annotated Answer Sheet
    </a>
<?php endif; ?>
```

## Quick Implementation Option

If you want a **quick working solution without complex PDF libraries**, you can:

1. Use a **screenshot-based approach**:
   - Display PDF in iframe as is
   - Add an overlay canvas for drawing annotations
   - When submitting, capture the visible PDF area + annotations as image
   - Convert to PDF using jsPDF
   - Send to backend

2. This won't give you true PDF annotations but will work for marking purposes.

## Sample Code Structure

I've prepared the backend to handle the annotated PDF. You need to add the frontend JavaScript that:

```javascript
// Pseudo-code structure
document.getElementById('evaluationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // 1. Capture/generate annotated PDF
    const annotatedPdfBlob = await generateAnnotatedPDF();
    
    // 2. Convert to base64
    const reader = new FileReader();
    reader.readAsDataURL(annotatedPdfBlob);
    reader.onloadend = function() {
        const base64data = reader.result;
        
        // 3. Add to form data
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'annotated_pdf_data';
        hiddenInput.value = base64data;
        document.getElementById('evaluationForm').appendChild(hiddenInput);
        
        // 4. Submit form
        e.target.submit();
    };
});
```

## Next Steps

1. **Run the migration**: http://localhost/student-app/add_annotated_pdf_column_script.php
2. **Choose implementation approach**: Commercial library vs free custom solution
3. **Implement frontend PDF annotation**: Replace iframe with annotatable viewer
4. **Test the flow**: Annotate → Submit → Check database → View in student dashboard
5. **Update student interface**: Add download link for annotated PDFs

## Backend Changes Already Done ✅

The backend (`evaluator/evaluate.php`) is ready and will:
- Receive `$_POST['annotated_pdf_data']` (base64 encoded PDF)
- Decode and save to `uploads/annotated_pdfs/`
- Store path in `submissions.annotated_pdf_url`
- Handle cases where annotation is optional

## What You Need to Decide

**Do you want:**
1. A full-featured PDF annotation library (commercial, easier) OR
2. A custom free solution (more development work) OR
3. A simple screenshot-based marking system (quickest)

Let me know your preference and I can provide detailed implementation code for that specific approach.
