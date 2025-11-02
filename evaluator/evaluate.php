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
            
            // Process per-question marks if provided
            $question_marks = isset($_POST['question_marks']) ? $_POST['question_marks'] : [];
            $per_question_marks_json = json_encode($question_marks);
            
            // Handle annotated PDF file upload
            $annotated_pdf_path = null;
            if (isset($_FILES['annotated_pdf']) && $_FILES['annotated_pdf']['error'] === UPLOAD_ERR_OK) {
                $uploaded_file = $_FILES['annotated_pdf'];
                
                // Validate file type
                $file_type = mime_content_type($uploaded_file['tmp_name']);
                $allowed_types = ['application/pdf'];
                
                if (in_array($file_type, $allowed_types)) {
                    // Create annotated PDFs directory if it doesn't exist
                    $upload_dir = dirname(__DIR__) . '/uploads/annotated_pdfs';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0775, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'annotated_' . $submission_id . '_' . time() . '.pdf';
                    $file_path = $upload_dir . '/' . $filename;
                    $relative_path = 'uploads/annotated_pdfs/' . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
                        $annotated_pdf_path = $relative_path;
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
            // Update submission with evaluation (include annotated PDF if provided)
            if ($annotated_pdf_path) {
                $update_submission_query = "UPDATE submissions SET 
                    marks_obtained = ?, 
                    max_marks = ?, 
                    evaluator_remarks = ?, 
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
                $stmt->bind_param("ddssi", $marks_obtained, $max_marks, $evaluator_remarks, $annotated_pdf_path, $submission_id);
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
            
            // Try to update additional columns if they exist
            try {
                $additional_update_query = "UPDATE submissions SET 
                    per_question_marks = ?,
                    percentage = ?,
                    grade = ?
                    WHERE id = ?";
                
                $stmt2 = $conn->prepare($additional_update_query);
                if ($stmt2) {
                    $stmt2->bind_param("sdsi", $per_question_marks_json, $percentage, $grade, $submission_id);
                    $stmt2->execute();
                }
            } catch (Exception $e) {
                // These columns might not exist, that's okay
                error_log("Could not update additional submission fields: " . $e->getMessage());
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
            
            // Notify student about evaluation completion
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
            
            $_SESSION['success_message'] = "Evaluation submitted successfully! The submission has been sent back to the moderator.";
            header("Location: pending_evaluations.php");
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
        .evaluation-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 10px;
        }
        
        .evaluation-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }
        
        .submission-info {
            background: rgba(108, 92, 231, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid #6c5ce7;
        }
        
        .pdf-viewer {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
            min-height: 1000px;
        }
        
        .evaluation-form {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .marks-input {
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            padding: 15px;
            border: 2px solid #6c5ce7;
            border-radius: 10px;
        }
        
        .marks-display {
            background: linear-gradient(45deg, #6c5ce7, #a29bfe);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .btn-submit-evaluation {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            font-weight: 600;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-submit-evaluation:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .deadline-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .deadline-danger {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid #dc3545;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .marks-breakdown {
            background: rgba(40, 167, 69, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="evaluation-page rounded">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="text-center text-white">
                        <h1 class="display-6 mb-3">
                            <i class="fas fa-clipboard-check me-3"></i>
                            Evaluate Submission
                        </h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb justify-content-center">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php" class="text-white-50">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="pending_evaluations.php" class="text-white-50">Pending Evaluations</a>
                                </li>
                                <li class="breadcrumb-item active text-white" aria-current="page">Evaluate</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>


            <!-- Main Content -->
            <div class="row">
                <!-- Left Panel - Submission View -->
                <div class="col-md-8">
                    <div class="evaluation-container px-2 py-2">
                        <!-- Download Button for Annotation -->
                       
                        
                        <!-- Submission Information -->
                        

                        <!-- PDF Viewer -->
                        <div class="pdf-viewer">
                            <?php if ($submission['pdf_url'] && file_exists('../uploads/pdfs/' . basename($submission['pdf_url']))): ?>
                                <iframe src="../uploads/pdfs/<?php echo htmlspecialchars(basename($submission['pdf_url'])); ?>" 
                                        width="100%" height="1000px" style="border: none; border-radius: 8px;">
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
                <div class="col-md-4">
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
                                            <div class="row g-2 align-items-end mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-6">
                                                        <label class="form-label">Q<?php echo $q_number; ?> (out of <?php echo $part['marks']; ?>)</label>
                                                        <input type="number"
                                                               class="form-control per-question-mark"
                                                               name="question_marks[<?php echo $q_number; ?>]"
                                                               min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                               value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
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
                                            <div class="row g-2 align-items-end mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-6">
                                                        <label class="form-label">Q<?php echo $q_number; ?> (out of <?php echo $part['marks']; ?>)</label>
                                                        <input type="number"
                                                               class="form-control per-question-mark"
                                                               name="question_marks[<?php echo $q_number; ?>]"
                                                               min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                               value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
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
                                            <div class="row g-2 align-items-end mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-6">
                                                        <label class="form-label">Q<?php echo $q_number; ?> (out of <?php echo $part['marks']; ?>)</label>
                                                        <input type="number"
                                                               class="form-control per-question-mark"
                                                               name="question_marks[<?php echo $q_number; ?>]"
                                                               min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                               value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
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
                                            <div class="row g-2 align-items-end mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-6">
                                                        <label class="form-label">Q<?php echo $q_number; ?> (out of <?php echo $part['marks']; ?>)</label>
                                                        <input type="number"
                                                               class="form-control per-question-mark"
                                                               name="question_marks[<?php echo $q_number; ?>]"
                                                               min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                               value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
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
                                            <div class="row g-2 align-items-end mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-6">
                                                        <label class="form-label">Q<?php echo $q_number; ?> (out of <?php echo $part['marks']; ?>)</label>
                                                        <input type="number"
                                                               class="form-control per-question-mark"
                                                               name="question_marks[<?php echo $q_number; ?>]"
                                                               min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                               value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
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
                                            <div class="row g-2 align-items-end mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-6">
                                                        <label class="form-label">Q<?php echo $q_number; ?> (out of <?php echo $part['marks']; ?>)</label>
                                                        <input type="number"
                                                               class="form-control per-question-mark"
                                                               name="question_marks[<?php echo $q_number; ?>]"
                                                               min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                               value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
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
                                            <div class="row g-2 align-items-end mb-3">
                                                <?php for ($j = 1; $j <= $part['count']; $j++, $q_number++): ?>
                                                    <div class="col-6">
                                                        <label class="form-label">Q<?php echo $q_number; ?> (out of <?php echo $part['marks']; ?>)</label>
                                                        <input type="number"
                                                               class="form-control per-question-mark"
                                                               name="question_marks[<?php echo $q_number; ?>]"
                                                               min="0" max="<?php echo $part['marks']; ?>" step="0.25"
                                                               value="<?php echo isset($per_question[$q_number]) ? htmlspecialchars($per_question[$q_number]) : ''; ?>">
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
                                        <div class="row g-2 align-items-end">
                                            <?php for ($i = 1; $i <= $q_count; $i++): ?>
                                            <div class="col-6">
                                                <label class="form-label">Q<?php echo $i; ?> (out of <?php echo $q_max; ?>)</label>
                                                <input type="number"
                                                       class="form-control per-question-mark"
                                                       name="question_marks[<?php echo $i; ?>]"
                                                       min="0" max="<?php echo $q_max; ?>" step="0.25"
                                                       value="<?php echo isset($per_question[$i]) ? htmlspecialchars($per_question[$i]) : ''; ?>">
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php } ?>
                                    <div class="row mb-3">
                                        <div class="col-6">
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
                                        <div class="col-6">
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
                                </div>

                                <!-- Annotated PDF Upload -->
                                <div class="mb-4">
                                    <label for="annotated_pdf" class="form-label">
                                        <strong><i class="fas fa-file-pdf me-2"></i>Upload Annotated PDF (Optional)</strong>
                                    </label>
                                    <div class="alert alert-info small mb-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <strong>How to upload:</strong>
                                        <ol class="mb-0 mt-1 small">
                                            <li>Download the student's PDF above</li>
                                            <li>Annotate it using Adobe Acrobat, Foxit, or any PDF editor</li>
                                            <li>Upload the annotated version here</li>
                                            <li>Student will see your annotations after submission</li>
                                        </ol>
                                    </div>
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
        
        // Form validation
        document.getElementById('evaluationForm').addEventListener('submit', function(e) {
            const marksObtained = parseFloat(document.getElementById('marks_obtained').value);
            const maxMarks = parseFloat(document.getElementById('max_marks').value);
            const remarks = document.getElementById('evaluator_remarks').value.trim();
            
            if (isNaN(marksObtained) || marksObtained < 0) {
                e.preventDefault();
                alert('Please enter valid marks obtained (must be 0 or greater).');
                return;
            }
            
            if (isNaN(maxMarks) || maxMarks <= 0) {
                e.preventDefault();
                alert('Please enter valid maximum marks (must be greater than 0).');
                return;
            }
            
            if (marksObtained > maxMarks) {
                e.preventDefault();
                alert('Marks obtained cannot be greater than maximum marks.');
                return;
            }
            
            if (remarks.length < 10) {
                e.preventDefault();
                alert('Please provide detailed evaluation comments (at least 10 characters).');
                document.getElementById('evaluator_remarks').focus();
                return;
            }
            
            // Final confirmation
            const percentage = ((marksObtained / maxMarks) * 100).toFixed(1);
            const grade = document.getElementById('grade').textContent;
            
            if (!confirm(`Are you sure you want to submit this evaluation?\n\nMarks: ${marksObtained}/${maxMarks} (${percentage}%)\nGrade: ${grade}\n\nThis action cannot be undone.`)) {
                e.preventDefault();
                return;
            }
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
