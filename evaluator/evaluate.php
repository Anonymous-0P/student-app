<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('evaluator');

$submission_id = (int)($_GET['id'] ?? 0);
$evaluator_id = $_SESSION['user_id'];

if (!$submission_id) {
    header('Location: pending_evaluations.php');
    exit;
}

// Get submission details with proper joins
$query = "SELECT 
    s.*, 
    sub.code as subject_code, 
    sub.name as subject_name,
    u.name as student_name, 
    u.email as student_email,
    sa.id as assignment_id,
    sa.status as assignment_status,
    sa.assigned_at,
    sa.notes as assignment_notes
    FROM submissions s
    INNER JOIN subjects sub ON s.subject_id = sub.id
    INNER JOIN users u ON s.student_id = u.id
    LEFT JOIN submission_assignments sa ON s.id = sa.submission_id AND sa.evaluator_id = ?
    WHERE s.id = ? AND (sa.evaluator_id = ? OR s.evaluator_id = ?)";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("iiii", $evaluator_id, $submission_id, $evaluator_id, $evaluator_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result ? $result->fetch_assoc() : null;

if (!$submission) {
    $_SESSION['error_message'] = "Submission not found or you don't have permission to evaluate it.";
    header("Location: pending_evaluations.php");
    exit();
}

// Mark assignment as in progress if it's currently assigned
if ($submission['assignment_status'] === 'accepted' || $submission['assignment_status'] === 'pending') {
    $update_status_query = "UPDATE submission_assignments SET status = 'accepted' WHERE id = ?";
    $stmt = $conn->prepare($update_status_query);
    if($stmt) {
        $stmt->bind_param("i", $submission['assignment_id']);
        $stmt->execute();
    }
}

// Handle form submission for evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    // Check if this submission has already been evaluated by this evaluator
    $check_evaluation_query = "SELECT id FROM submissions 
                              WHERE id = ? AND (status = 'evaluated' OR evaluation_status = 'evaluated')";
    $check_stmt = $conn->prepare($check_evaluation_query);
    if ($check_stmt) {
        $check_stmt->bind_param("i", $submission_id);
        $check_stmt->execute();
        $existing_evaluation = $check_stmt->get_result()->fetch_assoc();
        
        if ($existing_evaluation) {
            $error_message = "This submission has already been evaluated and cannot be modified.";
        } else {
            // Proceed with evaluation
            $marks_obtained = floatval($_POST['marks_obtained']);
            $max_marks = floatval($_POST['max_marks']);
            $evaluator_remarks = trim($_POST['evaluator_remarks']);
            
            // Process per-question marks - SERVER SIDE
            // Read question_marks array directly from POST data
            $question_marks = [];
            
            error_log('EVALUATOR SUBMIT: Raw POST data=' . print_r($_POST, true));
            
            // Method 1: Check for question_marks array (most reliable)
            if (isset($_POST['question_marks']) && is_array($_POST['question_marks'])) {
                foreach ($_POST['question_marks'] as $q_num => $q_mark) {
                    // Include zeros too so moderator sees full breakdown
                    $mark_value = is_numeric($q_mark) ? floatval($q_mark) : 0.0;
                    $question_marks[$q_num] = $mark_value;
                }
                error_log('EVALUATOR SUBMIT: question_marks from array=' . print_r($question_marks, true));
            }
            
            // Method 2: Fallback to JSON if array is empty
            if (empty($question_marks) && isset($_POST['question_marks_json']) && !empty($_POST['question_marks_json'])) {
                $decoded = json_decode($_POST['question_marks_json'], true);
                if (is_array($decoded)) {
                    $question_marks = $decoded;
                    error_log('EVALUATOR SUBMIT: question_marks from JSON=' . print_r($question_marks, true));
                }
            }
            
            // Validate that we have question marks
            if (empty($question_marks)) {
                error_log('EVALUATOR SUBMIT: ERROR - No question marks received!');
                $_SESSION['error_message'] = 'Question-wise marks are mandatory. Please fill in marks for each question.';
                header("Location: evaluate.php?id=" . $submission_id);
                exit();
            }
            
            $per_question_marks_json = json_encode($question_marks);
            error_log('EVALUATOR SUBMIT: Final per_question_marks_json=' . $per_question_marks_json);
            
            // Handle annotated PDF file upload
            $annotated_pdf_path = null;
            $pdf_replaced = false;
            if (isset($_FILES['annotated_pdf']) && $_FILES['annotated_pdf']['error'] === UPLOAD_ERR_OK) {
                $uploaded_file = $_FILES['annotated_pdf'];
                
                // Validate file type
                $file_type = mime_content_type($uploaded_file['tmp_name']);
                $allowed_types = ['application/pdf'];
                
                if (in_array($file_type, $allowed_types)) {
                    // Get the original PDF path and create backup
                    $original_pdf_url = $submission['pdf_url'];
                    
                    if ($original_pdf_url) {
                        // Create backup directory if it doesn't exist
                        $backup_dir = dirname(__DIR__) . '/uploads/pdfs/backups';
                        if (!is_dir($backup_dir)) {
                            mkdir($backup_dir, 0775, true);
                        }
                        
                        // Get original file path
                        $original_file_path = dirname(__DIR__) . '/' . $original_pdf_url;
                        
                        // Create backup of original PDF
                        if (file_exists($original_file_path)) {
                            $backup_filename = 'backup_' . $submission_id . '_' . time() . '_' . basename($original_pdf_url);
                            $backup_path = $backup_dir . '/' . $backup_filename;
                            copy($original_file_path, $backup_path);
                        }
                        
                        // Replace the original PDF with the annotated one
                        if (move_uploaded_file($uploaded_file['tmp_name'], $original_file_path)) {
                            $annotated_pdf_path = $original_pdf_url; // Use the same path
                            $pdf_replaced = true;
                        }
                    } else {
                        // If no original PDF URL exists, create new one in pdfs folder
                        $upload_dir = dirname(__DIR__) . '/uploads/pdfs';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0775, true);
                        }
                        
                        // Generate filename
                        $filename = 'submission_' . $submission_id . '_' . time() . '.pdf';
                        $file_path = $upload_dir . '/' . $filename;
                        $relative_path = 'uploads/pdfs/' . $filename;
                        
                        // Move uploaded file
                        if (move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
                            $annotated_pdf_path = $relative_path;
                            $pdf_replaced = true;
                        }
                    }
                }
            }
    
            // Calculate percentage and grade
            $percentage = $max_marks > 0 ? round(($marks_obtained / $max_marks) * 100, 2) : 0;
            $grade = 'F';
            if ($percentage >= 90) $grade = 'A+';
            elseif ($percentage >= 85) $grade = 'A';
            elseif ($percentage >= 80) $grade = 'A-';
            elseif ($percentage >= 75) $grade = 'B+';
            elseif ($percentage >= 70) $grade = 'B';
            elseif ($percentage >= 65) $grade = 'B-';
            elseif ($percentage >= 60) $grade = 'C+';
            elseif ($percentage >= 55) $grade = 'C';
            elseif ($percentage >= 50) $grade = 'C-';
            elseif ($percentage >= 35) $grade = 'D';
            else $grade = 'F';
    
            // Validate marks
            if ($marks_obtained < 0 || $marks_obtained > $max_marks) {
                $error_message = "Marks obtained cannot be negative or exceed maximum marks.";
            } else {
                // Start transaction
                $conn->begin_transaction();
        
        try {
            // Update submission with evaluation
            if ($pdf_replaced && $annotated_pdf_path) {
                // When PDF is replaced, update pdf_url and set annotated_pdf_url
                $update_submission_query = "UPDATE submissions SET 
                    marks_obtained = ?, 
                    max_marks = ?, 
                    evaluator_remarks = ?, 
                    pdf_url = ?,
                    annotated_pdf_url = ?,
                    status = 'evaluated',
                    evaluation_status = 'evaluated',
                    evaluated_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ?";
                
                $stmt = $conn->prepare($update_submission_query);
                if (!$stmt) {
                    throw new Exception("Prepare failed for submission update: " . $conn->error);
                }
                $stmt->bind_param("ddsssi", $marks_obtained, $max_marks, $evaluator_remarks, $annotated_pdf_path, $annotated_pdf_path, $submission_id);
            } else {
                $update_submission_query = "UPDATE submissions SET 
                    marks_obtained = ?, 
                    max_marks = ?, 
                    evaluator_remarks = ?, 
                    status = 'evaluated',
                    evaluation_status = 'evaluated',
                    evaluated_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ?";
                
                $stmt = $conn->prepare($update_submission_query);
                if (!$stmt) {
                    throw new Exception("Prepare failed for submission update: " . $conn->error);
                }
                $stmt->bind_param("ddsi", $marks_obtained, $max_marks, $evaluator_remarks, $submission_id);
            }
            $stmt->execute();
            
            // Try to update per_question_marks and extra fields; if schema lacks columns, gracefully fallback
            $savedPerQuestion = false;
            try {
                $additional_update_query = "UPDATE submissions SET 
                    per_question_marks = ?,
                    percentage = ?,
                    grade = ?,
                    is_published = 0
                    WHERE id = ?";
                $stmt2 = $conn->prepare($additional_update_query);
                if ($stmt2) {
                    $stmt2->bind_param("sdsi", $per_question_marks_json, $percentage, $grade, $submission_id);
                    $savedPerQuestion = $stmt2->execute();
                    if ($savedPerQuestion) {
                        error_log("EVALUATOR SUBMIT: per_question_marks saved with extras for submission $submission_id");
                        error_log("EVALUATOR SUBMIT: Data saved: $per_question_marks_json");
                    } else {
                        error_log("EVALUATOR SUBMIT: Failed to save per_question_marks with extras: " . $stmt2->error);
                    }
                    $stmt2->close();
                } else {
                    error_log("EVALUATOR SUBMIT: Prepare failed for extras update (likely missing columns): " . $conn->error);
                }
            } catch (Exception $e) {
                error_log("EVALUATOR SUBMIT: Exception during extras update: " . $e->getMessage());
            }

            // Fallback: ensure per_question_marks is saved even if percentage/grade/is_published don't exist
            if (!$savedPerQuestion) {
                try {
                    $stmt2b = $conn->prepare("UPDATE submissions SET per_question_marks = ? WHERE id = ?");
                    if ($stmt2b) {
                        $stmt2b->bind_param("si", $per_question_marks_json, $submission_id);
                        if ($stmt2b->execute()) {
                            error_log("EVALUATOR SUBMIT: per_question_marks saved via fallback for submission $submission_id");
                        } else {
                            error_log("EVALUATOR SUBMIT: Fallback update failed: " . $stmt2b->error);
                        }
                        $stmt2b->close();
                    } else {
                        error_log("EVALUATOR SUBMIT: Fallback prepare failed: " . $conn->error);
                    }
                } catch (Exception $e) {
                    error_log("EVALUATOR SUBMIT: Exception during fallback update: " . $e->getMessage());
                }
            }
            
            // Update submission assignment status (keep as accepted since completed is tracked in submissions table)
            $update_assignment_query = "UPDATE submission_assignments SET 
                status = 'accepted'
                WHERE submission_id = ? AND evaluator_id = ?";
            
            $stmt3 = $conn->prepare($update_assignment_query);
            if (!$stmt3) {
                throw new Exception("Prepare failed for assignment update: " . $conn->error);
            }
            $stmt3->bind_param("ii", $submission_id, $evaluator_id);
            $stmt3->execute();
            
            // Add to marks history (if table exists)
            try {
                $history_query = "INSERT INTO marks_history 
                    (submission_id, evaluator_id, marks_given, max_marks, remarks, action_type, created_by_role) 
                    VALUES (?, ?, ?, ?, ?, 'evaluation_completed', 'evaluator')";
                
                $stmt4 = $conn->prepare($history_query);
                if ($stmt4) {
                    $stmt4->bind_param("iidds", $submission_id, $evaluator_id, $marks_obtained, $max_marks, $evaluator_remarks);
                    $stmt4->execute();
                }
            } catch (Exception $e) {
                // Marks history table might not exist, that's okay
                error_log("Could not insert marks history: " . $e->getMessage());
                
                // Try with basic structure
                try {
                    $basic_history_query = "INSERT INTO marks_history 
                        (submission_id, evaluator_id, marks_given, max_marks) 
                        VALUES (?, ?, ?, ?)";
                    
                    $stmt5 = $conn->prepare($basic_history_query);
                    if ($stmt5) {
                        $stmt5->bind_param("iidd", $submission_id, $evaluator_id, $marks_obtained, $max_marks);
                        $stmt5->execute();
                    }
                } catch (Exception $e2) {
                    error_log("Could not insert basic marks history: " . $e2->getMessage());
                }
            }
            
            // Notify student about evaluation completion (DEFERRED VISIBILITY: student sees marks only after publish)
            try {
                $percentage = ($max_marks > 0) ? round(($marks_obtained / $max_marks) * 100, 1) : 0;
                $grade = '';
                if ($percentage >= 90) $grade = 'A+';
                elseif ($percentage >= 80) $grade = 'A';
                elseif ($percentage >= 70) $grade = 'B';
                elseif ($percentage >= 60) $grade = 'C';
                elseif ($percentage >= 50) $grade = 'D';
                else $grade = 'F';
                
                $studentNotifyStmt = $conn->prepare("
                    INSERT INTO notifications (user_id, type, title, message, related_id, metadata, created_at) 
                    VALUES (?, 'evaluation_complete', ?, ?, ?, ?, NOW())
                ");
                
                if ($studentNotifyStmt) {
                    $notifyTitle = "📊 Evaluation Complete - {$submission['subject_code']}";
                    $notifyMessage = "Your submission has been evaluated! Score: {$marks_obtained}/{$max_marks} ({$percentage}% - Grade {$grade}). Click to view detailed feedback.";
                    $metadata = json_encode([
                        'subject_code' => $submission['subject_code'],
                        'marks_obtained' => $marks_obtained,
                        'max_marks' => $max_marks,
                        'percentage' => $percentage,
                        'grade' => $grade,
                        'evaluator_name' => $_SESSION['name']
                    ]);
                    $studentNotifyStmt->bind_param("issis", $submission['student_id'], $notifyTitle, $notifyMessage, $submission_id, $metadata);
                    $studentNotifyStmt->execute();
                }
            } catch (Exception $e) {
                // Notifications table might not exist, that's okay
                error_log("Could not create student notification: " . $e->getMessage());
            }
            
            $conn->commit();
            
            // Send evaluation completion email to student (no marks shown)
            require_once('../includes/mail_helper.php');
            $emailResult = sendEvaluationCompletedEmail(
                $submission['student_email'],
                $submission['student_name'],
                $submission['subject_code'],
                $submission['subject_name']
            );
            
            // Store email status (optional - for debugging)
            if (!$emailResult['success']) {
                error_log("Failed to send evaluation email: " . $emailResult['message']);
            }
            
            $_SESSION['success_message'] = "Evaluation submitted successfully! The student has been notified via email.";
            header("Location: assignments.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error submitting evaluation: " . $e->getMessage();
        }
            } // End of marks validation else block
        } // End of existing evaluation else block
    } else {
        $error_message = "Unable to verify evaluation status.";
    }
}

$pageTitle = "Evaluate Submission";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluate Submission - Evaluator Portal</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --bg-light: #f9fafb;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #ffffff;
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .evaluation-page {
            background: #ffffff;
            min-height: 100vh;
            padding-top: 10px;
        }
        
        .evaluation-container {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .submission-info {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid var(--primary-color);
        }
        
        .pdf-viewer {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #ffffff;
            min-height: 1200px;
        }
        
        .evaluation-form {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-height: calc(100vh - 100px);
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .marks-input {
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }
        
        .marks-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .marks-display {
            background: #dbeafe;
            color: #1e40af;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 1rem;
            border: 1px solid #bfdbfe;
        }
        
        .btn-submit-evaluation {
            background: var(--success-color);
            border: none;
            color: white;
            font-weight: 500;
            padding: 0.625rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.15s;
        }
        
        .btn-submit-evaluation:hover {
            background: #059669;
            color: white;
        }
        
        .deadline-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .deadline-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .marks-breakdown {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        
        /* Question input styling */
        .question-input-group {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
            white-space: nowrap;
        }
        
        .question-input-group .form-control {
            width: 50px;
            min-width: 50px;
            text-align: center;
            font-weight: 500;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.25rem 0.25rem;
            font-size: 0.8125rem;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        
        .question-input-group .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .question-input-group .form-control.highlight-required {
            border-color: #dc3545;
            background-color: #fff5f5;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .question-label {
            min-width: 22px;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.8125rem;
            flex-shrink: 0;
        }
        
        .marks-divider {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.8125rem;
            flex-shrink: 0;
        }
        
        .marks-breakdown .col-12 {
            display: block;
        }
        
        /* Reduce spacing in marks breakdown */
        .marks-breakdown .row {
            margin-bottom: 0.5rem;
        }
        
        .marks-breakdown h6 {
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .marks-breakdown .mb-2 {
            margin-bottom: 0.5rem !important;
        }
        
        .marks-breakdown .mb-3 {
            margin-bottom: 0.75rem !important;
        }
        
        /* Feedback suggestion buttons */
        .feedback-suggestion {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            transition: all 0.15s;
            cursor: pointer;
            width: 100%;
            text-align: center;
        }
        
        .feedback-suggestion:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .feedback-suggestion i {
            display: none;
        }

        .feedback-buttons-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 4px;
            line-height: 1;
        }

        .badge.bg-success { background: #dcfce7; color: #166534; }
        .badge.bg-warning { background: #fef3c7; color: #92400e; }
        .badge.bg-info { background: #dbeafe; color: #1e40af; }
        .badge.bg-danger { background: #fee2e2; color: #991b1b; }
        .badge.bg-secondary { background: #f3f4f6; color: #374151; }
        .badge.bg-primary { background: #dbeafe; color: #1e40af; }

        h5, h6 {
            color: var(--text-dark);
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .evaluation-container {
                padding: 1rem;
            }
            
            .evaluation-form {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="evaluation-page rounded">
        <div class="container-fluid">
            <!-- Page Header -->
            


            <!-- Main Content -->
            <div class="row">
                <!-- Left Panel - Submission View -->
                <div class="col-md-10">
                    <div class="evaluation-container px-2 py-2">
                        <!-- PDF Viewer -->
                        <div class="pdf-viewer">
                            <?php if ($submission['pdf_url'] && file_exists('../uploads/pdfs/' . basename($submission['pdf_url']))): ?>
                                <iframe src="../uploads/pdfs/<?php echo htmlspecialchars(basename($submission['pdf_url'])); ?>" 
                                        width="100%" height="1200px" style="border: none; border-radius: 8px;">
                                    <p>Your browser doesn't support PDF viewing. 
                                       <a href="../uploads/pdfs/<?php echo htmlspecialchars(basename($submission['pdf_url'])); ?>" target="_blank">
                                           Click here to view the PDF
                                       </a>
                                    </p>
                                </iframe>
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                    <div class="text-center">
                                        <i class="fas fa-file-times fa-4x mb-3"></i>
                                        <h4>Submission File Not Available</h4>
                                        <p>The submitted file could not be found or is corrupted.</p>
                                        <small class="text-muted">Looking for: <?php echo htmlspecialchars($submission['pdf_url'] ?? 'No file specified'); ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Panel - Evaluation Form -->
                <div class="col-md-2">
                    <div class="evaluation-container px-1 py-2">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <div class="evaluation-form">
                            <h4 class="mb-4 text-center">
                               Evaluation Form
                            </h4>

                            <form method="POST" id="evaluationForm" enctype="multipart/form-data">
                                <!-- Marks Section -->
                                <div class="marks-breakdown">
                                    <h6 class="mb-3">
                                        <i class="fas fa-calculator me-2"></i>Marks Allocation
                                        <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">Required *</span>
                                    </h6>
                                    

                                    <?php
                                    // Division-based question template
                                    $division = isset($submission['division']) ? trim(strtolower($submission['division'])) : '';
                                    $subject_code = isset($submission['subject_code']) ? trim(strtolower($submission['subject_code'])) : '';
                                    $per_question = isset($submission['per_question_marks']) ? json_decode($submission['per_question_marks'], true) : [];
                                    // Show 10th structure if division or subject code matches 10th
                                    if (
                                        $division === '10th' || $division === '10th class' || $division === '10' || strpos($subject_code, '10th') !== false
                                    ) {
                                        // Special structure for THK_10TH
                                        if (strtolower($submission['subject_code']) === 'thk_10th') {
                                            $parts = [
                                                ['label' => 'Part 1', 'count' => 6, 'marks' => 1],
                                                ['label' => 'Part 2', 'count' => 4, 'marks' => 1],
                                                ['label' => 'Part 3', 'count' => 7, 'marks' => 1],
                                                ['label' => 'Part 4', 'count' => 10, 'marks' => 2],
                                                ['label' => 'Part 5', 'count' => 2, 'marks' => 3],
                                                ['label' => 'Part 6', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 7', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 8', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 9', 'count' => 6, 'marks' => 3],
                                                ['label' => 'Part 10', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 12', 'count' => 2, 'marks' => 4],
                                                ['label' => 'Part 13', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 14', 'count' => 1, 'marks' => 5],
                                                ['label' => 'Part 15', 'count' => 1, 'marks' => 5],
                                            ];
                                            $q_number = 1;
                                    ?>
                                    <div class="mb-3">
                                        <?php foreach ($parts as $part): ?>
                                            <div class="mb-2">
                                                <strong><?php echo $part['label']; ?>:</strong>
                                            </div>
                                            <div class="row g-2 align-items-center mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-12">
                                                        <div class="question-input-group">
                                                            <span class="question-label">Q<?php echo $q_number; ?></span>
                                                            <input type="number"
                                                                   class="form-control per-question-mark"
                                                                   name="question_marks[<?php echo $q_number; ?>]"
                                                                   min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                                   value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
                                                            <span class="marks-divider">/ <?php echo $part['marks']; ?></span>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                            // For total marks calculation in JS and max_marks field
                                            $q_count = $q_number - 1;
                                            $q_max = 100; // Always 100 for THK_10TH
                                        } else if (strtolower($submission['subject_code']) === 'eng_10th') {
                                            $parts = [
                                                ['label' => 'Part 1', 'count' => 4, 'marks' => 1],
                                                ['label' => 'Part 2', 'count' => 12, 'marks' => 1],
                                                ['label' => 'Part 3', 'count' => 1, 'marks' => 2],
                                                ['label' => 'Part 4', 'count' => 7, 'marks' => 2],
                                                ['label' => 'Part 5', 'count' => 2, 'marks' => 3],
                                                ['label' => 'Part 6', 'count' => 4, 'marks' => 3],
                                                ['label' => 'Part 7', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 8', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 9', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 10', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 11', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 12', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 13', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 14', 'count' => 1, 'marks' => 5],
                                            ];
                                            $q_number = 1;
                                    ?>
                                    <div class="mb-3">
                                        <?php foreach ($parts as $part): ?>
                                            <div class="mb-2">
                                                <strong><?php echo $part['label']; ?>:</strong>
                                            </div>
                                            <div class="row g-2 align-items-center mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-12">
                                                        <div class="question-input-group">
                                                            <span class="question-label">Q<?php echo $q_number; ?></span>
                                                            <input type="number"
                                                                   class="form-control per-question-mark"
                                                                   name="question_marks[<?php echo $q_number; ?>]"
                                                                   min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                                   value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
                                                            <span class="marks-divider">/ <?php echo $part['marks']; ?></span>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                            $q_count = $q_number - 1;
                                            $q_max = 80; // As specified for ENG_10TH
                                        } else if (strtolower($submission['subject_code']) === 'kan_10th') {
                                            $parts = [
                                                ['label' => 'Part 1', 'count' => 4, 'marks' => 1],
                                                ['label' => 'Part 2', 'count' => 12, 'marks' => 1],
                                                ['label' => 'Part 3', 'count' => 8, 'marks' => 2],
                                                ['label' => 'Part 4', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 5', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 6', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 7', 'count' => 1, 'marks' => 3],
                                                ['label' => 'Part 8', 'count' => 5, 'marks' => 3],
                                                ['label' => 'Part 9', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 10', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 11', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 12', 'count' => 1, 'marks' => 4],
                                                ['label' => 'Part 13', 'count' => 1, 'marks' => 5],
                                            ];
                                            $q_number = 1;
                                    ?>
                                    <div class="mb-3">
                                        <?php foreach ($parts as $part): ?>
                                            <div class="mb-2">
                                                <strong><?php echo $part['label']; ?>:</strong>
                                            </div>
                                            <div class="row g-2 align-items-center mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-12">
                                                        <div class="question-input-group">
                                                            <span class="question-label">Q<?php echo $q_number; ?></span>
                                                            <input type="number"
                                                                   class="form-control per-question-mark"
                                                                   name="question_marks[<?php echo $q_number; ?>]"
                                                                   min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                                   value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
                                                            <span class="marks-divider">/ <?php echo $part['marks']; ?></span>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                            $q_count = $q_number - 1;
                                            $q_max = 80; // Always 80 for KAN_10TH
                                        } else if (strtolower($submission['subject_code']) === 'kma_10th') {
                                            $parts = [
                                                ['label' => 'Part 1', 'count' => 8, 'marks' => 1],
                                                ['label' => 'Part 2', 'count' => 8, 'marks' => 1],
                                                ['label' => 'Part 3', 'count' => 8, 'marks' => 2],
                                                ['label' => 'Part 4', 'count' => 9, 'marks' => 3],
                                                ['label' => 'Part 5', 'count' => 4, 'marks' => 4],
                                                ['label' => 'Part 6', 'count' => 1, 'marks' => 5],
                                            ];
                                            $q_number = 1;
                                    ?>
                                    <div class="mb-3">
                                        <?php foreach ($parts as $part): ?>
                                            <div class="mb-2">
                                                <strong><?php echo $part['label']; ?>:</strong>
                                            </div>
                                            <div class="row g-2 align-items-center mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-12">
                                                        <div class="question-input-group">
                                                            <span class="question-label">Q<?php echo $q_number; ?></span>
                                                            <input type="number"
                                                                   class="form-control per-question-mark"
                                                                   name="question_marks[<?php echo $q_number; ?>]"
                                                                   min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                                   value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
                                                            <span class="marks-divider">/ <?php echo $part['marks']; ?></span>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                            $q_count = $q_number - 1;
                                            $q_max = 80; // As specified for KMA_10TH
                                        } else if (strtolower($submission['subject_code']) === 'ema_10th') {
                                            $parts = [
                                                ['label' => 'Part 1', 'count' => 8, 'marks' => 1],
                                                ['label' => 'Part 2', 'count' => 8, 'marks' => 1],
                                                ['label' => 'Part 3', 'count' => 8, 'marks' => 2],
                                                ['label' => 'Part 4', 'count' => 9, 'marks' => 3],
                                                ['label' => 'Part 5', 'count' => 4, 'marks' => 4],
                                                ['label' => 'Part 6', 'count' => 1, 'marks' => 5],
                                            ];
                                            $q_number = 1;
                                    ?>
                                    <div class="mb-3">
                                        <?php foreach ($parts as $part): ?>
                                            <div class="mb-2">
                                                <strong><?php echo $part['label']; ?>:</strong>
                                            </div>
                                            <div class="row g-2 align-items-center mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-12">
                                                        <div class="question-input-group">
                                                            <span class="question-label">Q<?php echo $q_number; ?></span>
                                                            <input type="number"
                                                                   class="form-control per-question-mark"
                                                                   name="question_marks[<?php echo $q_number; ?>]"
                                                                   min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                                   value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
                                                            <span class="marks-divider">/ <?php echo $part['marks']; ?></span>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                            $q_count = $q_number - 1;
                                            $q_max = 80; // As specified for EMA_10TH
                                        } else if (strtolower($submission['subject_code']) === 'eso_10th') {
                                            $parts = [
                                                ['label' => 'Part 1', 'count' => 8, 'marks' => 1],
                                                ['label' => 'Part 2', 'count' => 8, 'marks' => 1],
                                                ['label' => 'Part 3', 'count' => 8, 'marks' => 2],
                                                ['label' => 'Part 4', 'count' => 9, 'marks' => 3],
                                                ['label' => 'Part 5', 'count' => 4, 'marks' => 4],
                                                ['label' => 'Part 6', 'count' => 1, 'marks' => 5],
                                            ];
                                            $q_number = 1;
                                    ?>
                                    <div class="mb-3">
                                        <?php foreach ($parts as $part): ?>
                                            <div class="mb-2">
                                                <strong><?php echo $part['label']; ?>:</strong>
                                            </div>
                                            <div class="row g-2 align-items-center mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-12">
                                                        <div class="question-input-group">
                                                            <span class="question-label">Q<?php echo $q_number; ?></span>
                                                            <input type="number"
                                                                   class="form-control per-question-mark"
                                                                   name="question_marks[<?php echo $q_number; ?>]"
                                                                   min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                                   value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
                                                            <span class="marks-divider">/ <?php echo $part['marks']; ?></span>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                            $q_count = $q_number - 1;
                                            $q_max = 80; // As specified for ESO_10TH
                                        } else if (strtolower($submission['subject_code']) === 'kso_10th') {
                                            $parts = [
                                                ['label' => 'Part 1', 'count' => 8, 'marks' => 1],
                                                ['label' => 'Part 2', 'count' => 8, 'marks' => 1],
                                                ['label' => 'Part 3', 'count' => 8, 'marks' => 2],
                                                ['label' => 'Part 4', 'count' => 9, 'marks' => 3],
                                                ['label' => 'Part 5', 'count' => 4, 'marks' => 4],
                                                ['label' => 'Part 6', 'count' => 1, 'marks' => 5],
                                            ];
                                            $q_number = 1;
                                    ?>
                                    <div class="mb-3">
                                        <?php foreach ($parts as $part): ?>
                                            <div class="mb-2">
                                                <strong><?php echo $part['label']; ?>:</strong>
                                            </div>
                                            <div class="row g-2 align-items-center mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-12">
                                                        <div class="question-input-group">
                                                            <span class="question-label">Q<?php echo $q_number; ?></span>
                                                            <input type="number"
                                                                   class="form-control per-question-mark"
                                                                   name="question_marks[<?php echo $q_number; ?>]"
                                                                   min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                                   value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
                                                            <span class="marks-divider">/ <?php echo $part['marks']; ?></span>
                                                        </div>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php
                                            $q_count = $q_number - 1;
                                            $q_max = 80; // As specified for KSO_10TH
                                        } else {
                                            // ...existing code...
                                        }
                                    } else {
                                        // Default/simple template for other divisions
                                        $question_templates = [
                                            '12th' => ['count' => 4, 'max' => 25],
                                        ];
                                        $template = $question_templates[$division] ?? ['count' => 5, 'max' => 20];
                                        $q_count = $template['count'];
                                        $q_max = $template['max'];
                                    ?>
                                    <div class="mb-3">
                                        <div class="row g-2 align-items-center">
                                            <?php for ($i = 1; $i <= $q_count; $i++): ?>
                                            <div class="col-12">
                                                <div class="question-input-group">
                                                    <span class="question-label">Q<?php echo $i; ?></span>
                                                    <input type="number"
                                                           class="form-control per-question-mark"
                                                           name="question_marks[<?php echo $i; ?>]"
                                                           min="0" max="<?php echo $q_max; ?>" step="0.25"
                                                           value="<?php echo isset($per_question[$i]) ? htmlspecialchars($per_question[$i]) : ''; ?>">
                                                    <span class="marks-divider">/ <?php echo $q_max; ?></span>
                                                </div>
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php } ?>
                                    <div class="mb-3">
                                        <div class="mb-3">
                                            <label for="marks_obtained" class="form-label">
                                                <strong>Marks Obtained</strong>
                                            </label>
                                            <input type="number" 
                                                   class="form-control marks-input" 
                                                   id="marks_obtained" 
                                                   name="marks_obtained" 
                                                   min="0" 
                                                   max="<?php echo isset($q_max) ? $q_max : 100; ?>" 
                                                   step="0.25" 
                                                   value="<?php echo $submission['marks_obtained'] ?? ''; ?>"
                                                   required readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label for="max_marks" class="form-label">
                                                <strong>Maximum Marks</strong>
                                            </label>
                                            <input type="number" 
                                                   class="form-control marks-input" 
                                                   id="max_marks" 
                                                   name="max_marks" 
                                                   min="1" 
                                                   max="<?php echo isset($q_max) ? $q_max : 100; ?>" 
                                                   step="0.25" 
                                                   value="<?php echo isset($q_max) ? $q_max : 100; ?>"
                                                   required readonly>
                                        </div>
                                    </div>
                                    
                                </div>

                                <!-- Evaluation Comments -->
                                <div class="mb-4">
                                    <label for="evaluator_remarks" class="form-label">
                                        <strong><i class="fas fa-comment-alt me-2"></i>General Feedback</strong>
                                    </label>
                                    <textarea class="form-control" 
                                              id="evaluator_remarks" 
                                              name="evaluator_remarks" 
                                              rows="6" 
                                              placeholder="Provide detailed feedback on the submission..."
                                              required><?php echo htmlspecialchars($submission['evaluator_remarks'] ?? ''); ?></textarea>
                                    
                                    <!-- Feedback Suggestions -->
                                    <div class="mt-2">
                                        <small class="text-muted d-block text-center mb-2"><i class="fas fa-lightbulb me-1"></i>Quick Feedback Suggestions:</small>
                                        <div class="feedback-buttons-container">
                                            <button type="button" class="btn btn-outline-secondary btn-sm feedback-suggestion" data-feedback="Excellent work! Your answers demonstrate a clear understanding of the concepts.">
                                                Excellent
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm feedback-suggestion" data-feedback="Good effort! Keep up the consistent work.">
                                                Good
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm feedback-suggestion" data-feedback="Well done! Your presentation is neat and organized.">
                                                Well Done
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm feedback-suggestion" data-feedback="Please review the concepts and provide more detailed answers.">
                                                Needs Improvement
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm feedback-suggestion" data-feedback="Pay attention to handwriting and presentation. Make sure all answers are legible.">
                                                Handwriting
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm feedback-suggestion" data-feedback="Focus on time management. Some questions appear incomplete or rushed.">
                                                Time Management
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Annotated PDF Upload -->
                                <div class="mb-4">
                                    <label for="annotated_pdf" class="form-label">
                                        <strong><i class="fas fa-file-pdf me-2"></i>Upload Annotated PDF</strong>
                                    </label>
                                    
                                    <div class="input-group">
                                        <input type="file" 
                                               class="form-control" 
                                               id="annotated_pdf" 
                                               name="annotated_pdf" 
                                               accept=".pdf,application/pdf"
                                               onchange="validatePdfFile(this)">
                                        <label class="input-group-text" for="annotated_pdf">
                                            <i class="fas fa-upload"></i>
                                        </label>
                                    </div>
                                    <div id="pdfFileName" class="text-muted small mt-1"></div>
                                    <?php if (!empty($submission['annotated_pdf_url']) && file_exists('../' . $submission['annotated_pdf_url'])): ?>
                                        <div class="alert alert-success small mt-2 mb-0">
                                            <i class="fas fa-check-circle me-1"></i>
                                            Previously uploaded: 
                                            <a href="../<?= htmlspecialchars($submission['annotated_pdf_url']) ?>" target="_blank" class="alert-link">
                                                View annotated PDF
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Evaluation Guidelines -->
                                <!-- <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Evaluation Guidelines</h6>
                                    <ul class="mb-0 small">
                                        <li>Review the entire submission thoroughly</li>
                                        <li>Provide constructive and detailed feedback</li>
                                        <li>Ensure marks are justified by the quality of work</li>
                                        <li>Double-check calculations and comments</li>
                                    </ul>
                                </div> -->

                                <!-- Submit Button -->
                                <div class="d-grid gap-2">
                                    <button type="submit" name="submit_evaluation" class="btn btn-submit-evaluation">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Evaluation
                                    </button>
                                    <a href="assignments.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('../includes/footer.php'); ?>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        function updateTotalMarks() {
            // Sum per-question marks
            let total = 0;
            document.querySelectorAll('.per-question-mark').forEach(function(input) {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('marks_obtained').value = total.toFixed(2);
            // max_marks is fixed, but update in case template changes
            // document.getElementById('max_marks').value = ... already set
            calculatePercentage();
        }

        function calculatePercentage() {
            const marksObtained = parseFloat(document.getElementById('marks_obtained').value) || 0;
            const maxMarks = parseFloat(document.getElementById('max_marks').value) || 100;
            const percentage = maxMarks > 0 ? (marksObtained / maxMarks) * 100 : 0;
            document.getElementById('percentage').textContent = percentage.toFixed(1) + '%';
            // Calculate grade
            let grade = 'F';
            if (percentage >= 90) grade = 'A+';
            else if (percentage >= 85) grade = 'A';
            else if (percentage >= 80) grade = 'A';
            else if (percentage >= 75) grade = 'B+';
            else if (percentage >= 70) grade = 'B+';
            else if (percentage >= 65) grade = 'B+';
            else if (percentage >= 60) grade = 'B';
            else if (percentage >= 55) grade = 'C+';
            else if (percentage >= 35) grade = 'C';
            else grade = 'F';
            document.getElementById('grade').textContent = grade;
            // Update display colors based on grade
            const display = document.getElementById('percentage-display');
            display.className = 'marks-display';
            if (percentage >= 80) {
                display.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
            } else if (percentage >= 60) {
                display.style.background = 'linear-gradient(45deg, #ffc107, #fd7e14)';
            } else if (percentage >= 50) {
                display.style.background = 'linear-gradient(45deg, #17a2b8, #6f42c1)';
            } else {
                display.style.background = 'linear-gradient(45deg, #dc3545, #e83e8c)';
            }
        }

        // Auto-calculate total and percentage on per-question input
        document.querySelectorAll('.per-question-mark').forEach(function(input) {
            input.addEventListener('input', updateTotalMarks);
        });
        // Initial calculation
        document.addEventListener('DOMContentLoaded', function() {
            updateTotalMarks();
        });
        
        // Form validation - SIMPLIFIED VERSION
        // Just validate question marks are filled, then let form submit normally
        document.getElementById('evaluationForm').addEventListener('submit', function(e) {
            const marksObtained = parseFloat(document.getElementById('marks_obtained').value);
            const maxMarks = parseFloat(document.getElementById('max_marks').value);
            const remarks = document.getElementById('evaluator_remarks').value.trim();
            
            // Check if question marks are filled
            const questionMarks = document.querySelectorAll('.per-question-mark');
            let hasQuestionMarks = false;
            let totalQuestionMarks = 0;
            
            questionMarks.forEach(function(input) {
                const value = parseFloat(input.value) || 0;
                if (value > 0) {
                    hasQuestionMarks = true;
                }
                totalQuestionMarks += value;
            });
            
            // Validate question marks are mandatory
            if (!hasQuestionMarks || totalQuestionMarks === 0) {
                e.preventDefault();
                
                // Add visual highlight
                questionMarks.forEach(function(input) {
                    input.classList.add('highlight-required');
                });
                
                setTimeout(function() {
                    questionMarks.forEach(function(input) {
                        input.classList.remove('highlight-required');
                    });
                }, 1500);
                
                alert('⚠️ Question-wise marks are mandatory!\n\nPlease fill in the marks for each question before submitting.');
                if (questionMarks.length > 0) {
                    questionMarks[0].focus();
                    questionMarks[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            // Validate other fields
            if (isNaN(marksObtained) || marksObtained < 0) {
                e.preventDefault();
                alert('Please enter valid marks obtained (must be 0 or greater).');
                return false;
            }
            
            if (isNaN(maxMarks) || maxMarks <= 0) {
                e.preventDefault();
                alert('Please enter valid maximum marks (must be greater than 0).');
                return false;
            }
            
            if (marksObtained > maxMarks) {
                e.preventDefault();
                alert('Marks obtained cannot be greater than maximum marks.');
                return false;
            }
            
            if (remarks.length < 10) {
                e.preventDefault();
                alert('Please provide detailed evaluation comments (at least 10 characters).');
                document.getElementById('evaluator_remarks').focus();
                return false;
            }
            
            // Final confirmation
            const percentage = ((marksObtained / maxMarks) * 100).toFixed(1);
            let grade = 'F';
            if (percentage >= 90) grade = 'A+';
            else if (percentage >= 85) grade = 'A';
            else if (percentage >= 80) grade = 'A-';
            else if (percentage >= 75) grade = 'B+';
            else if (percentage >= 70) grade = 'B';
            else if (percentage >= 65) grade = 'B-';
            else if (percentage >= 60) grade = 'C+';
            else if (percentage >= 55) grade = 'C';
            else if (percentage >= 50) grade = 'C-';
            else if (percentage >= 35) grade = 'D';
            
            if (!confirm(`Are you sure you want to submit this evaluation?\n\nMarks: ${marksObtained}/${maxMarks} (${percentage}%)\nGrade: ${grade}\n\nThis action cannot be undone.`)) {
                e.preventDefault();
                return false;
            }
            
            // Let form submit normally - all question_marks[] inputs will be included in POST
            console.log('Form validation passed, submitting normally...');
            return true;
        });
        
        // Auto-save functionality (saves to localStorage for recovery)
        function autoSave() {
            const formData = {
                marks_obtained: document.getElementById('marks_obtained').value,
                max_marks: document.getElementById('max_marks').value,
                evaluator_remarks: document.getElementById('evaluator_remarks').value,
                submission_id: <?php echo $submission_id; ?>,
                timestamp: new Date().getTime()
            };
            
            localStorage.setItem('evaluation_draft_' + <?php echo $submission_id; ?>, JSON.stringify(formData));
        }
        
        // Load saved draft if available
        function loadDraft() {
            const savedDraft = localStorage.getItem('evaluation_draft_' + <?php echo $submission_id; ?>);
            if (savedDraft) {
                try {
                    const formData = JSON.parse(savedDraft);
                    const now = new Date().getTime();
                    const oneDay = 24 * 60 * 60 * 1000; // 24 hours
                    
                    // Only restore if saved within last 24 hours
                    if (now - formData.timestamp < oneDay) {
                        if (confirm('A draft of this evaluation was found. Would you like to restore it?')) {
                            if (formData.marks_obtained && !document.getElementById('marks_obtained').value) {
                                document.getElementById('marks_obtained').value = formData.marks_obtained;
                            }
                            if (formData.max_marks && !document.getElementById('max_marks').value) {
                                document.getElementById('max_marks').value = formData.max_marks;
                            }
                            if (formData.evaluator_remarks && !document.getElementById('evaluator_remarks').value) {
                                document.getElementById('evaluator_remarks').value = formData.evaluator_remarks;
                            }
                            calculatePercentage();
                        }
                    } else {
                        // Remove old draft
                        localStorage.removeItem('evaluation_draft_' + <?php echo $submission_id; ?>);
                    }
                } catch (e) {
                    console.log('Error loading draft:', e);
                }
            }
        }
        
        // Auto-save on input (debounced)
        let autoSaveTimer;
        function scheduleAutoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSave, 2000); // Save 2 seconds after last input
        }
        
        // Attach auto-save to form inputs
        document.getElementById('marks_obtained').addEventListener('input', scheduleAutoSave);
        document.getElementById('max_marks').addEventListener('input', scheduleAutoSave);
        document.getElementById('evaluator_remarks').addEventListener('input', scheduleAutoSave);
        
        // Load draft on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDraft();
        });
        
        // Clear draft on successful submission
        document.getElementById('evaluationForm').addEventListener('submit', function() {
            setTimeout(function() {
                localStorage.removeItem('evaluation_draft_' + <?php echo $submission_id; ?>);
            }, 100);
        });
        
        // Validate PDF file upload
        function validatePdfFile(input) {
            const fileNameDisplay = document.getElementById('pdfFileName');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSize = file.size / 1024 / 1024; // Size in MB
                const fileName = file.name;
                const fileExtension = fileName.split('.').pop().toLowerCase();
                
                // Check file extension
                if (fileExtension !== 'pdf') {
                    alert('Please upload a PDF file only.');
                    input.value = '';
                    fileNameDisplay.textContent = '';
                    return false;
                }
                
                // Check file size (max 10MB)
                if (fileSize > 10) {
                    alert('File size must be less than 10MB. Your file is ' + fileSize.toFixed(2) + 'MB');
                    input.value = '';
                    fileNameDisplay.textContent = '';
                    return false;
                }
                
                // Display file info
                fileNameDisplay.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>' +
                                           '<strong>' + fileName + '</strong> (' + fileSize.toFixed(2) + ' MB)';
                return true;
            }
        }
        
        // Feedback suggestion buttons
        document.querySelectorAll('.feedback-suggestion').forEach(function(button) {
            button.addEventListener('click', function() {
                const feedback = this.getAttribute('data-feedback');
                const textarea = document.getElementById('evaluator_remarks');
                const currentText = textarea.value.trim();
                
                // If textarea is empty, set the feedback directly
                if (currentText === '') {
                    textarea.value = feedback;
                } else {
                    // If there's existing text, append with a space
                    textarea.value = currentText + ' ' + feedback;
                }
                
                // Focus on textarea
                textarea.focus();
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter or Cmd+Enter to submit
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                document.getElementById('evaluationForm').dispatchEvent(new Event('submit'));
            }
            
            // Ctrl+S or Cmd+S to save draft
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                autoSave();
                
                // Show brief feedback
                const feedback = document.createElement('div');
                feedback.className = 'alert alert-success position-fixed';
                feedback.style.top = '20px';
                feedback.style.right = '20px';
                feedback.style.zIndex = '9999';
                feedback.innerHTML = '<i class="fas fa-save me-2"></i>Draft saved!';
                document.body.appendChild(feedback);
                
                setTimeout(function() {
                    feedback.remove();
                }, 2000);
            }
        });
    </script>
</body>
</html>
