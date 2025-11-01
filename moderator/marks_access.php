<?php
require_once('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

$moderator_id = $_SESSION['user_id'];

// Get evaluator parameter if passed
$selected_evaluator_id = isset($_GET['evaluator_id']) ? (int)$_GET['evaluator_id'] : null;
$selected_evaluator_name = isset($_GET['evaluator_name']) ? $_GET['evaluator_name'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Access - Moderator Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
            border: none;
        }
        
        .nav-tabs {
            border: none;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 0.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px;
            color: #6c757d;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .form-select, .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .loading-spinner {
            color: #667eea;
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            color: #667eea;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header Card -->
        <div class="card">
            <div class="card-header text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Marks Access & Management
                    <?php if($selected_evaluator_name): ?>
                        <small class="opacity-75"> - <?= htmlspecialchars($selected_evaluator_name) ?></small>
                    <?php endif; ?>
                </h4>
                <a href="dashboard.php" class="btn btn-light btn-sm" title="Back to Dashboard">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="card">
            <div class="card-body p-4">
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-4" id="marksTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="view-marks-tab" data-bs-toggle="tab" data-bs-target="#view-marks" type="button" role="tab">
                            <i class="fas fa-eye me-2"></i>View Marks
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="cross-check-tab" data-bs-toggle="tab" data-bs-target="#cross-check" type="button" role="tab">
                            <i class="fas fa-search me-2"></i>Cross-check
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="override-marks-tab" data-bs-toggle="tab" data-bs-target="#override-marks" type="button" role="tab">
                            <i class="fas fa-edit me-2"></i>Override Marks
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="marksTabContent">
                    <!-- View Marks Tab -->
                    <div class="tab-pane fade show active" id="view-marks" role="tabpanel">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="evaluatorSelect" class="form-label fw-bold">Select Evaluator</label>
                                <select class="form-select" id="evaluatorSelect" onchange="loadEvaluatorMarks()">
                                    <option value="">Choose an evaluator...</option>
                                    <?php
                                    // Get evaluators under this moderator
                                    $eval_query = "SELECT id, name, email FROM users WHERE moderator_id = ? AND role = 'evaluator' AND is_active = 1 ORDER BY name";
                                    $eval_stmt = $conn->prepare($eval_query);
                                    $eval_stmt->bind_param("i", $moderator_id);
                                    $eval_stmt->execute();
                                    $evaluators = $eval_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    
                                    foreach($evaluators as $evaluator): ?>
                                        <option value="<?= $evaluator['id'] ?>" <?= $evaluator['id'] == $selected_evaluator_id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($evaluator['name']) ?> (<?= htmlspecialchars($evaluator['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="subjectFilter" class="form-label fw-bold">Filter by Subject</label>
                                <select class="form-select" id="subjectFilter" onchange="loadEvaluatorMarks()">
                                    <option value="">All subjects</option>
                                    <?php
                                    // Get subjects for this moderator
                                    $subj_query = "SELECT DISTINCT s.id, s.name, s.code FROM subjects s 
                                                  JOIN moderator_subjects ms ON s.id = ms.subject_id 
                                                  WHERE ms.moderator_id = ? AND ms.is_active = 1 ORDER BY s.name";
                                    $subj_stmt = $conn->prepare($subj_query);
                                    $subj_stmt->bind_param("i", $moderator_id);
                                    $subj_stmt->execute();
                                    $subjects = $subj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    
                                    foreach($subjects as $subject): ?>
                                        <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div id="evaluatorMarksResults">
                            <div class="empty-state">
                                <i class="fas fa-user-check fa-3x"></i>
                                <h5>Select an evaluator to view marks</h5>
                                <p class="text-muted">Choose an evaluator from the dropdown above</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cross-check Tab -->
                    <div class="tab-pane fade" id="cross-check" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Analyze submissions for marking consistency and identify potential discrepancies.
                        </div>
                        <button class="btn btn-primary btn-lg mb-4" onclick="loadConsistencyCheck()">
                            <i class="fas fa-sync me-2"></i>Check for Inconsistencies
                        </button>
                        <div id="consistencyResults">
                            <div class="empty-state">
                                <i class="fas fa-balance-scale fa-3x"></i>
                                <h5>Consistency Analysis</h5>
                                <p class="text-muted">Click the button above to analyze marking patterns</p>
                            </div>
                        </div>
                    </div>

                    <!-- Override Marks Tab -->
                    <div class="tab-pane fade" id="override-marks" role="tabpanel">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Mark overrides create audit trails and notify all parties.
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label for="submissionSearch" class="form-label fw-bold">Search Submission</label>
                                <input type="text" class="form-control" id="submissionSearch" placeholder="Enter submission ID or student name">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button class="btn btn-primary w-100" onclick="searchSubmission()">
                                    <i class="fas fa-search me-2"></i>Search
                                </button>
                            </div>
                        </div>
                        <div id="overrideResults">
                            <div class="empty-state">
                                <i class="fas fa-edit fa-3x"></i>
                                <h5>Override Marks</h5>
                                <p class="text-muted">Search for a submission to modify its marks</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-load evaluator marks if pre-selected
        <?php if($selected_evaluator_id): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                loadEvaluatorMarks();
            }, 500);
        });
        <?php endif; ?>

        // Load evaluator marks
        function loadEvaluatorMarks() {
            const evaluatorId = document.getElementById('evaluatorSelect').value;
            const subjectId = document.getElementById('subjectFilter').value;
            
            if (!evaluatorId) {
                document.getElementById('evaluatorMarksResults').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-check fa-3x"></i>
                        <h5>Select an evaluator to view marks</h5>
                        <p class="text-muted">Choose an evaluator from the dropdown above</p>
                    </div>`;
                return;
            }
            
            document.getElementById('evaluatorMarksResults').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x loading-spinner"></i>
                    <p class="mt-3 text-muted">Loading marks data...</p>
                </div>`;
            
            fetch('get_evaluator_marks.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `evaluator_id=${evaluatorId}&subject_id=${subjectId}`
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('evaluatorMarksResults').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('evaluatorMarksResults').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Error loading marks data: ${error.message}
                    </div>`;
            });
        }

        // Load consistency check
        function loadConsistencyCheck() {
            document.getElementById('consistencyResults').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x loading-spinner"></i>
                    <p class="mt-3 text-muted">Analyzing marking consistency...</p>
                </div>`;
            
            fetch('check_marking_consistency.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'moderator_id=<?= $moderator_id ?>'
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('consistencyResults').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('consistencyResults').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Error checking consistency: ${error.message}
                    </div>`;
            });
        }

        // Search submission for override
        function searchSubmission() {
            const searchTerm = document.getElementById('submissionSearch').value.trim();
            
            if (!searchTerm) {
                alert('Please enter a submission ID or student name to search');
                return;
            }
            
            document.getElementById('overrideResults').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x loading-spinner"></i>
                    <p class="mt-3 text-muted">Searching submissions...</p>
                </div>`;
            
            fetch('search_submission_for_override.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `search_term=${encodeURIComponent(searchTerm)}&moderator_id=<?= $moderator_id ?>`
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('overrideResults').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('overrideResults').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Error searching submissions: ${error.message}
                    </div>`;
            });
        }

        // Override marks function
        function overrideMarks(submissionId, currentMarks, maxMarks) {
            const newMarks = prompt(`Override marks for this submission.\nCurrent marks: ${currentMarks}/${maxMarks}\n\nEnter new marks:`, currentMarks);
            
            if (newMarks === null) return; // User cancelled
            
            const newMarksFloat = parseFloat(newMarks);
            if (isNaN(newMarksFloat) || newMarksFloat < 0 || newMarksFloat > maxMarks) {
                alert('Please enter valid marks (0 to ' + maxMarks + ')');
                return;
            }
            
            const reason = prompt('Please provide a reason for this mark override:');
            if (!reason || reason.trim() === '') {
                alert('Reason is required for mark overrides');
                return;
            }
            
            // Show loading
            const overrideBtn = event.target;
            const originalText = overrideBtn.innerHTML;
            overrideBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            overrideBtn.disabled = true;
            
            fetch('override_marks.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `submission_id=${submissionId}&new_marks=${newMarksFloat}&reason=${encodeURIComponent(reason)}&moderator_id=<?= $moderator_id ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Marks updated successfully!');
                    searchSubmission(); // Refresh results
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating marks: ' + error.message);
            })
            .finally(() => {
                overrideBtn.innerHTML = originalText;
                overrideBtn.disabled = false;
            });
        }
    </script>
</body>
</html>