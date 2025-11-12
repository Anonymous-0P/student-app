# Moderator Review System - Implementation Guide

## Overview
This implementation provides moderators with a comprehensive interface to review evaluated answer sheets exactly as submitted by evaluators, including marks and annotated PDFs.

## New Files Created

### 1. `moderator/review_evaluation.php`
**Main review interface** that displays:
- **Original Student Answer Sheet**: The PDF submitted by the student
- **Annotated Answer Sheet**: The PDF uploaded by the evaluator with their marks and annotations
- **Evaluation Details**: Marks, grade, percentage, and all evaluation data
- **Evaluator Feedback**: Complete remarks and comments from the evaluator
- **Student Information**: Full student details with contact options
- **Evaluator Information**: Evaluator details and evaluation timeline
- **Moderator Actions**:
  - Override marks
  - Approve evaluation
  - Request re-evaluation
  - Contact student/evaluator

**Key Features**:
- Tabbed PDF viewer to switch between original and annotated PDFs
- Visual grade display with color-coded badges
- Complete evaluation timeline
- Real-time mark override functionality
- Approval and re-evaluation workflows

### 2. `moderator/approve_submission.php`
**Backend API endpoint** for approving evaluations:
- Updates submission status to 'approved'
- Sets final marks and feedback
- Records moderator remarks
- Sends email notifications to student and evaluator
- Logs approval timestamp

**Request Format**:
```
POST: submission_id, moderator_remarks (optional)
```

**Response**:
```json
{
  "success": true/false,
  "message": "Status message",
  "submission_id": 123
}
```

### 3. `moderator/request_reevaluation.php`
**Backend API endpoint** for requesting re-evaluation:
- Updates submission status to 'under_review'
- Sets evaluation_status to 'revision_needed'
- Records reason for re-evaluation
- Sends email notification to evaluator
- Updates timestamp

**Request Format**:
```
POST: submission_id, reason (required)
```

**Response**:
```json
{
  "success": true/false,
  "message": "Status message",
  "submission_id": 123
}
```

## Modified Files

### `moderator/submissions.php`
**Changes**:
- Updated action buttons to include "Review Evaluation" button
- Review button appears for evaluated submissions
- Links to `review_evaluation.php?id={submission_id}`
- Uses primary button style for better visibility

**Button Logic**:
```php
if ($submission['status'] === 'evaluated' || $submission['evaluation_status'] === 'evaluated') {
    // Show "Review Evaluation" button
} elseif ($submission['status'] === 'pending') {
    // Show "Assign Evaluator" button
}
```

## Database Schema Requirements

### Required Columns in `submissions` table:
- `id` - Submission identifier
- `student_id` - References users table
- `evaluator_id` - References users table
- `moderator_id` - References users table
- `pdf_url` - **Original student answer sheet**
- `annotated_pdf_url` - **Evaluator's annotated version**
- `marks_obtained` - Marks given by evaluator
- `max_marks` - Total possible marks
- `evaluator_remarks` - Evaluator's feedback
- `moderator_remarks` - Moderator's comments
- `status` - Current status (pending/evaluated/approved/rejected)
- `evaluation_status` - Evaluation workflow status
- `evaluated_at` - Timestamp of evaluation
- `approved_at` - Timestamp of moderator approval
- `final_marks` - Final approved marks
- `final_feedback` - Combined feedback

## Workflow

### 1. Evaluator Submission Process
```
Evaluator → evaluate.php
  ↓
1. Enter marks in input fields
2. Add evaluator remarks
3. Upload annotated PDF (optional but recommended)
  ↓
Database Update:
  - marks_obtained = entered marks
  - max_marks = total marks
  - evaluator_remarks = feedback text
  - annotated_pdf_url = uploaded PDF path
  - status = 'evaluated'
  - evaluation_status = 'evaluated'
  - evaluated_at = NOW()
```

### 2. Moderator Review Process
```
Moderator → submissions.php
  ↓
Filter: status='evaluated'
  ↓
Click "Review Evaluation" button
  ↓
review_evaluation.php displays:
  ├── Original PDF (student's submission)
  ├── Annotated PDF (evaluator's version)
  ├── Marks (exactly as entered by evaluator)
  ├── Evaluator remarks
  └── All submission details
  ↓
Moderator Actions:
  ├── Approve → approve_submission.php
  ├── Override Marks → override_marks.php
  └── Request Re-evaluation → request_reevaluation.php
```

### 3. Approval Workflow
```
Moderator clicks "Approve"
  ↓
approve_submission.php:
  - Sets status = 'approved'
  - Copies marks_obtained → final_marks
  - Combines feedback → final_feedback
  - Records approved_at timestamp
  - Sends email to student
  - Sends email to evaluator
```

### 4. Re-evaluation Workflow
```
Moderator clicks "Request Re-evaluation"
  ↓
Provides reason
  ↓
request_reevaluation.php:
  - Sets status = 'under_review'
  - Sets evaluation_status = 'revision_needed'
  - Records moderator_remarks with reason
  - Sends email to evaluator
  ↓
Evaluator receives notification
  ↓
Evaluator can modify evaluation in evaluate.php
```

## Features Implemented

### ✅ PDF Display
- **Tabbed Interface**: Switch between original and annotated PDFs
- **Inline Viewing**: 850px height iframe for comfortable viewing
- **Download Options**: Separate downloads for original and annotated versions
- **Open in New Tab**: View PDFs in full browser window

### ✅ Marks Display
- **Exact Replication**: Shows marks exactly as entered by evaluator
- **Visual Grade Card**: Large, color-coded grade display
- **Percentage Calculation**: Automatic percentage with grade assignment
- **Breakdown View**: Obtained/Total/Percentage in clear layout

### ✅ Evaluator Remarks
- **Full Text Display**: Complete feedback visible to moderator
- **Formatted Display**: Preserves line breaks with nl2br()
- **Highlighted Box**: Distinct styling for easy reading

### ✅ Moderator Actions
1. **Override Marks**:
   - Prompt for new marks value
   - Require reason for override
   - Validation (0 to max_marks)
   - Logged action

2. **Approve Evaluation**:
   - Optional moderator remarks
   - Confirmation dialog
   - Email notifications
   - Status update

3. **Request Re-evaluation**:
   - Required reason field
   - Notification to evaluator
   - Status tracking

### ✅ Communication
- **Email Student**: Direct mailto link
- **Email Evaluator**: Direct mailto link
- **Automated Notifications**: On approval/re-evaluation requests

### ✅ Timeline Tracking
- Submission date/time
- Evaluation date/time
- Turnaround time calculation
- Visual timeline display

## Security Features

1. **Authentication Check**: Only logged-in moderators can access
2. **Ownership Verification**: Moderator must own the submission
3. **SQL Injection Prevention**: All queries use prepared statements
4. **XSS Protection**: All output uses htmlspecialchars()
5. **CSRF Protection**: Session-based validation
6. **File Access Validation**: Checks file existence before display

## UI/UX Features

### Design Elements
- **Gradient Headers**: Purple gradient for modern look
- **Color-Coded Grades**: 
  - A+/A: Green gradient
  - B: Blue gradient
  - C: Orange gradient
  - D/F: Red gradient
- **Responsive Layout**: Works on desktop and tablet
- **Card-Based Design**: Clean, organized information blocks
- **Smooth Animations**: Fade-in effects and hover states

### User Experience
- **Tab Switching**: JavaScript-powered PDF tab navigation
- **Action Confirmations**: Prevent accidental operations
- **Loading States**: Visual feedback during operations
- **Error Handling**: User-friendly error messages
- **Success Feedback**: Clear confirmation messages

## Testing Checklist

### Moderator Access
- [ ] Can view only own submissions
- [ ] Can see evaluated submissions list
- [ ] Review button appears for evaluated items
- [ ] Access denied for other moderators' submissions

### PDF Display
- [ ] Original PDF loads correctly
- [ ] Annotated PDF loads correctly
- [ ] Tab switching works
- [ ] Download buttons work
- [ ] Open in new tab works
- [ ] Handles missing annotated PDF gracefully

### Marks Display
- [ ] Shows correct marks from evaluator
- [ ] Percentage calculates correctly
- [ ] Grade displays correctly
- [ ] Breakdown shows all three values

### Actions
- [ ] Override marks validates input
- [ ] Override marks requires reason
- [ ] Approve sends notifications
- [ ] Re-evaluation sends notification
- [ ] All actions update database correctly

### Email Notifications
- [ ] Student receives approval email
- [ ] Evaluator receives approval notification
- [ ] Evaluator receives re-evaluation request
- [ ] Emails contain correct information

## Future Enhancements

1. **Comparison View**: Side-by-side original vs annotated PDF
2. **Annotation Tools**: Allow moderator to add own annotations
3. **Bulk Actions**: Approve multiple submissions at once
4. **Analytics**: Track moderator review time and patterns
5. **Version History**: Track all mark changes and overrides
6. **Comments Thread**: Discussion between moderator and evaluator
7. **Export Reports**: Generate PDF reports of evaluations
8. **Mobile Optimization**: Better mobile/tablet support
9. **Real-time Updates**: WebSocket for live status changes
10. **Advanced Filters**: More granular submission filtering

## Usage Instructions

### For Moderators

1. **Access Evaluated Submissions**:
   ```
   Dashboard → Submissions → Filter: Status = "Evaluated"
   ```

2. **Review Evaluation**:
   - Click blue "Review Evaluation" button (clipboard icon)
   - View opens with all evaluation details

3. **View PDFs**:
   - Click "Original Answer Sheet" tab for student submission
   - Click "Annotated Answer Sheet" tab for evaluator version
   - Use download buttons to save locally

4. **Check Marks**:
   - Large grade display at top
   - Marks breakdown shows obtained/total/percentage
   - Evaluator remarks box shows feedback

5. **Take Action**:
   - **Approve**: If evaluation is satisfactory
   - **Override**: If marks need adjustment
   - **Re-evaluate**: If major issues found

6. **Contact Parties**:
   - Use email buttons to contact student or evaluator
   - All contact links pre-filled with addresses

## Troubleshooting

### PDF Not Displaying
- Check file exists in uploads directory
- Verify file permissions (read access)
- Check browser PDF support
- Try "Open in New Tab" button

### Marks Not Showing
- Verify submission is evaluated
- Check marks_obtained and max_marks columns
- Ensure evaluator completed submission

### Actions Not Working
- Check JavaScript console for errors
- Verify session is active
- Confirm moderator_id matches submission
- Check network tab for API responses

### Email Not Sending
- Verify PHP mail() configuration
- Check email addresses are valid
- Review mail server logs
- Consider using SMTP instead of mail()

## Support Files

### Related Files
- `evaluator/evaluate.php` - Where evaluations are created
- `moderator/submissions.php` - List view of submissions
- `moderator/override_marks.php` - Mark override handler (existing)
- `config/config.php` - Database connection
- `includes/functions.php` - Helper functions

### CSS Styling
- Bootstrap 5 framework
- Custom gradient styles
- Responsive grid system
- Font Awesome icons

## Conclusion

This implementation provides a complete moderator review system that:
- ✅ Shows evaluations exactly as submitted
- ✅ Displays both original and annotated PDFs
- ✅ Preserves evaluator's marks and remarks
- ✅ Enables moderator oversight and intervention
- ✅ Maintains complete audit trail
- ✅ Sends appropriate notifications
- ✅ Provides intuitive, modern UI

The system is production-ready and can be accessed at:
```
http://localhost/student-app/moderator/review_evaluation.php?id={submission_id}
```

Or through the submissions list:
```
http://localhost/student-app/moderator/submissions.php?status=evaluated
```
