# New Evaluation and Moderation Workflow System
## Implementation Guide

### Overview
This document describes the complete workflow system implementation for managing student submissions through evaluation and moderation stages.

---

## Workflow States

### 1. **Submitted** (Initial State)
- **Trigger**: Student submits answer sheet
- **System Action**:
  - Auto-assigns to subject evaluator (load-balanced)
  - Auto-assigns moderator (evaluator's moderator)
  - Sends notification to evaluator
- **Access**:
  - Student: Can view submission, cannot see marks
  - Evaluator: Can view and start evaluation
  - Moderator: Can view (read-only)

### 2. **Under Evaluation**
- **Trigger**: Evaluator opens submission for evaluation
- **System Action**:
  - Marks submission as being worked on
  - Tracks evaluator assignment
- **Access**:
  - Student: Can view submission status
  - Evaluator: Full edit access
  - Moderator: Can monitor progress

### 3. **Evaluated (Pending Moderation)**
- **Trigger**: Evaluator submits marks and annotations
- **System Action**:
  - **LOCKS evaluation** (evaluator cannot edit anymore)
  - Hides marks/PDF from student
  - Notifies moderator for review
  - Notifies student (evaluation complete, awaiting moderation)
- **Access**:
  - Student: Cannot see marks yet
  - Evaluator: **READ-ONLY** (locked)
  - Moderator: Can review and take action

### 4. **Under Moderation**
- **Trigger**: Moderator starts reviewing
- **System Action**:
  - Confirms evaluation lock
  - Tracks moderation start time
- **Access**:
  - Evaluator: Cannot edit
  - Moderator: Can approve, adjust marks, or request revision

### 5. **Moderation Completed**
- **Trigger**: Moderator approves evaluation
- **System Action**:
  - **LOCKS moderation** (moderator decision is final)
  - Sets final marks
  - Prepares for publication
  - Notifies student (results ready, not yet visible)
- **Access**:
  - Marks still hidden from student
  - Evaluation and moderation both locked

### 6. **Result Published** (Final State)
- **Trigger**: Admin/Moderator publishes results
- **System Action**:
  - Makes marks visible to student
  - Makes annotated PDF visible (if configured)
  - Sends final notification to student
  - All locks permanent
- **Access**:
  - Student: **CAN NOW SEE MARKS AND ANNOTATED PDF**
  - Evaluator: Read-only
  - Moderator: Read-only (decision final)

### Alternative States

#### **Revision Required**
- **Trigger**: Moderator requests changes
- **System Action**:
  - **UNLOCKS evaluation**
  - Sends revision notes to evaluator
  - Notifies evaluator with moderator's comments
- **Access**:
  - Evaluator: Can edit again
  - Returns to "Under Evaluation" state

#### **Rejected**
- **Trigger**: Moderator/Admin rejects submission
- **System Action**:
  - Final state, no further changes
  - Notifies student with rejection reason
- **Access**:
  - All parties: Read-only

---

## Access Control Matrix

| Role      | Submitted | Under Eval | Evaluated (Pending) | Under Mod | Mod Completed | Published | Rejected |
|-----------|-----------|------------|---------------------|-----------|---------------|-----------|----------|
| Student   | View      | View       | View (no marks)     | View      | View (no marks)| **View all** | View     |
| Evaluator | Edit      | Edit       | **Locked**          | Locked    | Locked        | Read      | Read     |
| Moderator | View      | View       | Review & Act        | Edit      | **Locked**    | Read      | Read     |
| Admin     | All       | All        | All                 | All       | All           | All       | All      |

---

## Database Changes

### New Tables

#### 1. `workflow_logs`
Audit trail of all status transitions
```sql
- submission_id
- user_id
- user_role
- from_status
- to_status
- action
- notes
- ip_address
- created_at
```

#### 2. `workflow_notifications`
User notifications for workflow events
```sql
- submission_id
- user_id
- notification_type
- title
- message
- is_read
- is_emailed
- created_at
```

#### 3. `workflow_settings`
Configurable workflow behavior
```sql
- setting_key
- setting_value
- description
- updated_by
```

#### 4. `moderation_history`
Track moderator actions
```sql
- submission_id
- moderator_id
- action (approved/rejected/marks_adjusted/revision_requested)
- original_marks
- adjusted_marks
- adjustment_reason
- moderation_notes
```

#### 5. `evaluation_locks`
Lock management
```sql
- submission_id
- locked_by (evaluator/moderator/admin)
- locked_at
- locked_by_user_id
- reason
```

### Modified Tables

#### `submissions` table - New Columns:
```sql
- status: ENUM with new values
- annotated_pdf_visible_to_student: BOOLEAN
- results_visible_to_student: BOOLEAN
- evaluation_locked: BOOLEAN
- moderation_locked: BOOLEAN
- moderated_at: TIMESTAMP
- moderated_by: INT
- moderation_notes: TEXT
- result_published_at: TIMESTAMP
- result_published_by: INT
```

### Database Views

#### `v_pending_evaluations`
Shows all submissions waiting for evaluator action

#### `v_pending_moderations`
Shows all submissions waiting for moderator review

#### `v_published_results`
Shows all published results with final marks

---

## Stored Procedures & Functions

### 1. `log_workflow_transition()`
Automatically logs every status change

### 2. `create_workflow_notification()`
Creates notifications for users

### 3. `lock_evaluation()`
Locks submission for editing

### 4. `is_evaluation_locked()`
Checks if submission is locked

---

## Triggers

### 1. `trg_lock_on_moderation`
Auto-locks evaluation when moderator starts review

### 2. `trg_lock_on_publish`
Auto-locks everything when results are published

### 3. `trg_set_visibility_on_publish`
Auto-sets visibility flags based on configuration

---

## Helper Functions (PHP)

Located in: `includes/workflow_functions.php`

### Transition Functions
```php
transition_submission_status($conn, $submission_id, $new_status, $user_id, $user_role, $notes)
validate_status_transition($from_status, $to_status, $user_role)
```

### Lock Functions
```php
is_evaluation_locked($conn, $submission_id)
lock_evaluation($conn, $submission_id, $user_id, $role, $reason)
unlock_evaluation($conn, $submission_id)
```

### Notification Functions
```php
create_workflow_notification($conn, $submission_id, $user_id, $type, $title, $message)
get_user_notifications($conn, $user_id, $limit, $unread_only)
mark_notification_read($conn, $notification_id, $user_id)
send_workflow_email($conn, $submission_id, $user_id, $title, $message)
```

### Visibility Functions
```php
are_results_visible_to_student($conn, $submission_id)
is_annotated_pdf_visible_to_student($conn, $submission_id)
set_result_visibility($conn, $submission_id, $visible)
set_annotated_pdf_visibility($conn, $submission_id, $visible)
```

### Auto-Assignment
```php
auto_assign_evaluator($conn, $submission_id, $subject_id)
```

### Moderation History
```php
record_moderation_action($conn, $submission_id, $moderator_id, $action, ...)
get_moderation_history($conn, $submission_id)
```

---

## Configuration Settings

Default workflow settings (configurable via admin panel):

| Setting | Default | Description |
|---------|---------|-------------|
| `auto_assign_evaluator` | 1 | Auto-assign submissions to evaluators |
| `evaluator_can_edit_after_submit` | 0 | Allow evaluator edits after submission |
| `moderator_can_override_marks` | 1 | Allow moderator to adjust marks |
| `auto_notify_email` | 1 | Send email notifications |
| `auto_publish_after_moderation` | 0 | Auto-publish results after moderation |
| `show_annotated_pdf_to_student` | 1 | Show annotated PDF after publication |
| `allow_student_resubmission` | 0 | Allow resubmission after rejection |
| `moderation_required` | 1 | Require moderator approval |
| `max_revision_rounds` | 2 | Maximum revision attempts |
| `evaluation_deadline_days` | 7 | Days to complete evaluation |

---

## Notification Types

### 1. **submission_received**
- **To**: Evaluator
- **When**: Student submits or revision returned
- **Message**: "New submission assigned for evaluation"

### 2. **evaluation_completed**
- **To**: Moderator + Student
- **When**: Evaluator submits marks
- **Message**: 
  - Moderator: "Evaluation completed, pending your review"
  - Student: "Your submission has been evaluated, awaiting moderation"

### 3. **moderation_completed**
- **To**: Student
- **When**: Moderator approves
- **Message**: "Moderation completed, results will be published soon"

### 4. **result_published**
- **To**: Student
- **When**: Results made visible
- **Message**: "Your results are now available!"

### 5. **revision_required**
- **To**: Evaluator
- **When**: Moderator requests revision
- **Message**: "Moderator has requested revision"

### 6. **submission_rejected**
- **To**: Student
- **When**: Submission rejected
- **Message**: "Your submission has been rejected"

---

## Installation Steps

### 1. Run Database Migration
```
http://localhost/student-app/admin/run_workflow_migration.php
```
(Requires admin login)

### 2. Verify Installation
The migration script will:
- Create all new tables
- Add new columns to submissions
- Create views and stored procedures
- Set up triggers
- Insert default settings
- Verify everything is working

### 3. Include Workflow Functions
Add to your PHP files:
```php
require_once('includes/workflow_functions.php');
```

---

## Usage Examples

### Student Submits Answer Sheet
```php
// Create submission
$submission_id = create_submission($conn, $student_id, $subject_id, $pdf_path);

// Auto-assign evaluator and set status
$assignment = auto_assign_evaluator($conn, $submission_id, $subject_id);

// Transition to Submitted status
transition_submission_status(
    $conn, 
    $submission_id, 
    STATUS_SUBMITTED, 
    $student_id, 
    'student',
    'Initial submission'
);
```

### Evaluator Starts Evaluation
```php
// Check if locked
if (!is_evaluation_locked($conn, $submission_id)) {
    // Transition to Under Evaluation
    transition_submission_status(
        $conn,
        $submission_id,
        STATUS_UNDER_EVALUATION,
        $evaluator_id,
        'evaluator',
        'Started evaluation'
    );
}
```

### Evaluator Submits Marks
```php
// Update marks
$query = "UPDATE submissions 
          SET marks_obtained = ?, evaluator_remarks = ?, 
              annotated_pdf_url = ?, evaluated_at = NOW()
          WHERE id = ?";
// Execute query...

// Transition to Evaluated (Pending Moderation)
// This automatically locks the evaluation
transition_submission_status(
    $conn,
    $submission_id,
    STATUS_EVALUATED_PENDING_MODERATION,
    $evaluator_id,
    'evaluator',
    'Evaluation completed'
);
```

### Moderator Reviews and Approves
```php
// Start moderation
transition_submission_status(
    $conn,
    $submission_id,
    STATUS_UNDER_MODERATION,
    $moderator_id,
    'moderator',
    'Started moderation'
);

// If marks need adjustment
if ($adjust_marks) {
    record_moderation_action(
        $conn,
        $submission_id,
        $moderator_id,
        'marks_adjusted',
        $original_marks,
        $new_marks,
        $reason,
        $notes
    );
    
    // Update marks
    $query = "UPDATE submissions 
              SET marks_obtained = ?, moderation_notes = ?, 
                  moderated_by = ?, moderated_at = NOW()
              WHERE id = ?";
    // Execute...
}

// Approve and complete moderation
transition_submission_status(
    $conn,
    $submission_id,
    STATUS_MODERATION_COMPLETED,
    $moderator_id,
    'moderator',
    'Moderation approved'
);
```

### Publish Results to Student
```php
// Publish results
transition_submission_status(
    $conn,
    $submission_id,
    STATUS_RESULT_PUBLISHED,
    $admin_id,
    'admin',
    'Results published'
);

// Results and annotated PDF now visible to student
// Triggers automatically set:
// - results_visible_to_student = TRUE
// - annotated_pdf_visible_to_student = TRUE (if configured)
// - evaluation_locked = TRUE
// - moderation_locked = TRUE
```

### Request Revision
```php
// Moderator requests revision
record_moderation_action(
    $conn,
    $submission_id,
    $moderator_id,
    'revision_requested',
    null,
    null,
    $revision_reason,
    $revision_notes
);

// Transition to Revision Required (unlocks evaluation)
transition_submission_status(
    $conn,
    $submission_id,
    STATUS_REVISION_REQUIRED,
    $moderator_id,
    'moderator',
    $revision_reason
);

// Evaluator can now edit again
```

---

## Security Features

### 1. **Status Transition Validation**
- Validates role permissions
- Validates state flow
- Prevents unauthorized transitions

### 2. **Automatic Locking**
- Evaluations lock when sent to moderator
- Cannot be unlocked except for revision
- Moderator decisions are final

### 3. **Audit Trail**
- Every status change logged
- User, role, IP address tracked
- Timestamps for all actions

### 4. **Visibility Control**
- Results hidden until published
- Annotated PDFs controlled separately
- Role-based access enforced

### 5. **Data Integrity**
- Transactions for all critical operations
- Foreign key constraints
- Triggers ensure consistency

---

## Next Steps for Full Implementation

The workflow system is now ready. Next, you need to:

1. **Update Student Submission Pages**
   - Modify submission creation to use new workflow
   - Hide marks until `results_visible_to_student = TRUE`
   - Show workflow status to student

2. **Update Evaluator Pages**
   - Check `is_evaluation_locked()` before allowing edits
   - Use `transition_submission_status()` when submitting
   - Show lock status in UI

3. **Update Moderator Pages**
   - Build moderation review interface
   - Implement approve/reject/revise actions
   - Show moderation history

4. **Add Admin Publishing Interface**
   - Bulk publish results
   - Individual publish controls
   - Configure visibility settings

5. **Add Notification Dashboard**
   - Display `workflow_notifications` to users
   - Mark as read functionality
   - Email digest option

6. **Create Workflow Reports**
   - Use database views for reporting
   - Track evaluation times
   - Monitor moderator performance

---

## File Summary

### Created Files:
1. `db/workflow_system_migration.sql` - Complete database migration
2. `includes/workflow_functions.php` - All workflow helper functions
3. `admin/run_workflow_migration.php` - Migration runner script
4. `WORKFLOW_IMPLEMENTATION.md` - This documentation

### Files to Update (Next Phase):
1. Student submission pages
2. Evaluator evaluation pages
3. Moderator review pages
4. Admin result publication pages
5. Dashboard notification displays

---

## Troubleshooting

### Migration Errors
- Check MySQL version (5.7+ required for some features)
- Verify user has CREATE/ALTER permissions
- Check for conflicting column/table names

### Lock Not Working
- Verify triggers are created
- Check `evaluation_locks` table
- Ensure `evaluation_locked` column exists

### Notifications Not Sending
- Check `auto_notify_email` setting
- Verify PHP mail() configuration
- Check `workflow_notifications` table

### Status Transitions Failing
- Check `validate_status_transition()` logic
- Verify user role is correct
- Check workflow_logs for details

---

## Support & Maintenance

### Monitoring
```sql
-- Check recent workflow activity
SELECT * FROM workflow_logs ORDER BY created_at DESC LIMIT 50;

-- Check pending moderations
SELECT * FROM v_pending_moderations;

-- Check unread notifications
SELECT user_id, COUNT(*) as unread
FROM workflow_notifications
WHERE is_read = FALSE
GROUP BY user_id;
```

### Performance
- All tables have proper indexes
- Views optimize common queries
- Stored procedures reduce round-trips

### Backups
- Always backup before migration
- Rollback script included in migration file
- Test on staging environment first

---

*Implementation Date: November 12, 2025*
*Version: 1.0*
