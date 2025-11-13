<?php

// DEBUG: Enable error reporting for troubleshooting
ini_set('display_errors', 0); // Disable display errors for AJAX to prevent HTML output
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1); // Log errors instead

require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

// Configuration
$selectedSubjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$maxFiles = 15;
$allowedPdfMime = ['application/pdf'];
$maxPdfSize = 25 * 1024 * 1024; // 25MB per submission

// Handle AJAX POST request BEFORE any HTML output
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1'){
    $respond = function($status, $message, $extra = []) {
        $payload = array_merge(['status' => $status, 'message' => $message], $extra);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    };

    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $respond('error', 'Invalid CSRF token.');
    }

    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
    if(empty($subject_id)) {
        $respond('error', 'Please select a subject.');
    }

    // Check if student has purchased access to this subject
    $access_check = $conn->prepare("SELECT COUNT(*) as has_access FROM purchased_subjects WHERE student_id = ? AND subject_id = ? AND status = 'active' AND expiry_date > CURDATE()");
    $access_check->bind_param("ii", $_SESSION['user_id'], $subject_id);
    $access_check->execute();
    $access_result = $access_check->get_result()->fetch_assoc();
    
    if ($access_result['has_access'] == 0) {
        $respond('error', 'Access denied. You need to purchase this subject before uploading answer sheets.');
    }

    // Enforce one submission per subject per student
    $dupStmt = $conn->prepare("SELECT 1 FROM submissions WHERE student_id = ? AND subject_id = ? LIMIT 1");
    if ($dupStmt) {
        $dupStmt->bind_param("ii", $_SESSION['user_id'], $subject_id);
        $dupStmt->execute();
        $dupRes = $dupStmt->get_result();
        if ($dupRes && $dupRes->num_rows > 0) {
            $respond('error', 'You have already submitted for this subject. Only one submission allowed.');
        }
        $dupStmt->close();
    }

    if(!isset($_FILES['pdf_file'])) {
        $respond('error', 'No PDF received. Please try again.');
    }

    $pdfFile = $_FILES['pdf_file'];
    if($pdfFile['error'] !== UPLOAD_ERR_OK) {
        $respond('error', 'Upload failed. Please try again.');
    }

    if($pdfFile['size'] > $maxPdfSize) {
        $respond('error', 'PDF is too large. Please keep submissions under 25MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $pdfFile['tmp_name']);
    finfo_close($finfo);
    if(!in_array($mime, $allowedPdfMime)) {
        $respond('error', 'Only PDF files are allowed.');
        return;
    }

    // Persist PDF
    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $relativePath = 'uploads/pdfs/' . $filename;
    $storePath = dirname(__DIR__) . '/' . $relativePath;

    if(!is_dir(dirname($storePath))) {
        mkdir(dirname($storePath), 0775, true);
    }

    if(!move_uploaded_file($pdfFile['tmp_name'], $storePath)) {
        $respond('error', 'Could not store uploaded PDF.');
        return;
    }

    $originalNames = trim($_POST['original_names'] ?? '');
    
    // Start transaction for submission and assignment creation
    $conn->begin_transaction();
    
    try {
        // Insert submission
        $stmt = $conn->prepare("INSERT INTO submissions (student_id, subject_id, pdf_url, original_filename, file_size, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $fileSize = (int)$pdfFile['size'];
        $stmt->bind_param("iissi", $_SESSION['user_id'], $subject_id, $relativePath, $originalNames, $fileSize);
        
        if(!$stmt->execute()) {
            throw new Exception('Failed to insert submission');
        }
        
        $submission_id = $conn->insert_id;
        
        // Get all evaluators assigned to this subject
        $evaluatorStmt = $conn->prepare("
            SELECT DISTINCT u.id, u.name, u.email 
            FROM users u 
            INNER JOIN evaluator_subjects es ON u.id = es.evaluator_id 
            WHERE es.subject_id = ? AND u.role = 'evaluator' AND u.is_active = 1
        ");
        $evaluatorStmt->bind_param("i", $subject_id);
        $evaluatorStmt->execute();
        $evaluators = $evaluatorStmt->get_result();
        
        if($evaluators->num_rows == 0) {
            throw new Exception('No evaluators found for this subject');
        }
        
        // Create assignment records for all evaluators
        $assignStmt = $conn->prepare("INSERT INTO submission_assignments (submission_id, evaluator_id, status, assigned_at) VALUES (?, ?, 'pending', NOW())");
        $notifyStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, created_at) VALUES (?, 'assignment_offered', ?, ?, ?, NOW())");
        
        while($evaluator = $evaluators->fetch_assoc()) {
            // Create assignment record
            $assignStmt->bind_param("ii", $submission_id, $evaluator['id']);
            if(!$assignStmt->execute()) {
                throw new Exception('Failed to create assignment for evaluator ' . $evaluator['name']);
            }
            
            // Create notification
            $notifyTitle = "New Assignment Available";
            $notifyMessage = "A new submission is available for evaluation. Please review and accept/deny the assignment.";
            $notifyStmt->bind_param("issi", $evaluator['id'], $notifyTitle, $notifyMessage, $submission_id);
            $notifyStmt->execute(); // Don't fail on notification errors
        }
        
        // Commit transaction
        $conn->commit();
        
        $respond('success', 'Submission uploaded successfully and assigned to evaluators!', ['redirect' => 'view_submissions.php']);
        return;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $respond('error', 'Error: ' . $e->getMessage());
    }
}

// Get available subjects for dropdown - only purchased subjects (for HTML form display)
$subjectsQuery = "SELECT s.id, s.code, s.name, ps.expiry_date,
                  DATEDIFF(ps.expiry_date, CURDATE()) as days_remaining
                  FROM subjects s
                  JOIN purchased_subjects ps ON s.id = ps.subject_id
                  WHERE s.is_active = 1 AND ps.student_id = ? AND ps.status = 'active' AND ps.expiry_date > CURDATE()
                  ORDER BY s.code";
$subjectsStmt = $conn->prepare($subjectsQuery);
$subjectsStmt->bind_param("i", $_SESSION['user_id']);
$subjectsStmt->execute();
$subjectsResult = $subjectsStmt->get_result();

$isIndexPage = false;
include('../includes/header.php');
?>

<link href="../moderator/css/moderator-style.css" rel="stylesheet">
<style>
/* Fix button hover styles */
.btn-primary:hover,
.btn-success:hover,
.btn-outline-primary:hover,
.btn-outline-secondary:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-primary:hover {
    background-color: #0056b3 !important;
    border-color: #0056b3 !important;
    color: white !important;
}

.btn-success:hover {
    background-color: #157347 !important;
    border-color: #157347 !important;
    color: white !important;
}

.btn-outline-primary:hover {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    color: white !important;
}

.btn-outline-secondary:hover {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    color: white !important;
}
</style>

<?php require_once('includes/sidebar.php'); ?>

<div class="dashboard-layout">
    <div class="main-content">

<div class="row">
    <div class="col-lg-9 col-xl-8">
        <div class="page-card">
            <div id="upload-status" class="mb-2"></div>
            
            <!-- Camera Capture Section -->
            <div class="mb-2">
                <h5 class="mb-3">Capture and Upload</h5>
                <div class="camera-section">
                    <video id="camera-preview" class="mb-3" style="width: 100%; max-width: 400px; height: 300px; background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px; display: none;"></video>
                    <canvas id="photo-canvas" style="display: none;"></canvas>
                    <div class="camera-controls mb-1">
                        <button type="button" id="start-camera" class="btn btn-outline-primary me-2">
                            📷 Open Camera
                        </button>
                        <button type="button" id="take-photo" class="btn btn-success me-2" style="display: none;">
                            📸 Scan Answers
                        </button>
                        <button type="button" id="stop-camera" class="btn btn-outline-secondary" style="display: none;">
                            ⏹️ Stop Camera
                        </button>
                    </div>
                    <div id="captured-photos" class="d-flex flex-wrap gap-2 mb-3"></div>
                </div>
                <hr class="my-4">
            </div>

            <form method="POST" enctype="multipart/form-data" class="vstack gap-3" id="upload-form">
                <?php csrf_input(); ?>
                <input type="hidden" name="ajax" value="1">
                <div>
                    <label class="form-label">Select Subject <span class="text-danger">*</span></label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Choose a subject...</option>
                        <?php if ($subjectsResult->num_rows > 0): ?>
                            <?php while($subject = $subjectsResult->fetch_assoc()): ?>
                                <option value="<?php echo (int)$subject['id']; ?>" <?php echo ($selectedSubjectId === (int)$subject['id']) ? 'selected' : ''; ?>>
                                    <?php 
                                    $subject_text = htmlspecialchars($subject['code'] . ' - ' . $subject['name']);
                                    if ($subject['days_remaining'] <= 7) {
                                        $subject_text .= ' (Expires in ' . $subject['days_remaining'] . ' days)';
                                    }
                                    echo $subject_text;
                                    ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                    
                    <?php if ($subjectsResult->num_rows == 0): ?>
                        <div class="alert alert-warning mt-2">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>No Purchased Subjects Available!</strong><br>
                            You need to purchase subjects before you can upload answer sheets.<br>
                            <a href="browse_exams.php" class="btn btn-sm btn-primary mt-2">
                                <i class="fas fa-shopping-cart"></i> Browse & Purchase Subjects
                            </a>
                        </div>
                    <?php else: ?>
                        <small class="text-muted">Only your purchased subjects are shown here.</small>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="form-label">📁 Or Select Images / PDF from Device</label>
                    <input type="file" name="device_files" multiple accept="image/*,application/pdf" class="form-control" capture="environment">
                    <small>Allowed: JPG, PNG, or a single PDF. Max <?= $maxFiles; ?> images.</small>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="upload" class="btn btn-primary">Upload Answersheet</button>
                    <a href="view_submissions.php" class="btn btn-outline-secondary">View Submissions</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js"></script>
<script>
// Fallback CDN if first one fails
window.addEventListener('load', function() {
    if (typeof window.jspdf === 'undefined' && typeof window.jsPDF === 'undefined') {
        console.log('Primary jsPDF CDN failed, trying fallback...');
        const fallbackScript = document.createElement('script');
        fallbackScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        fallbackScript.onerror = function() {
            console.error('Both jsPDF CDNs failed to load');
        };
        document.head.appendChild(fallbackScript);
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if jsPDF is available
    const checkPDFLibrary = () => {
        return typeof window.jspdf !== 'undefined' || typeof window.jsPDF !== 'undefined';
    };
    
    // Wait for library to load if not immediately available
    if (!checkPDFLibrary()) {
        let attempts = 0;
        const maxAttempts = 20; // Wait up to 2 seconds
        const checkInterval = setInterval(() => {
            attempts++;
            if (checkPDFLibrary() || attempts >= maxAttempts) {
                clearInterval(checkInterval);
                if (!checkPDFLibrary()) {
                    console.error('jsPDF library failed to load');
                }
            }
        }, 100);
    }

    const MAX_FILES = <?php echo (int) $maxFiles; ?>;
    const video = document.getElementById('camera-preview');
    const canvas = document.getElementById('photo-canvas');
    const startBtn = document.getElementById('start-camera');
    const takePhotoBtn = document.getElementById('take-photo');
    const stopBtn = document.getElementById('stop-camera');
    const capturedPhotos = document.getElementById('captured-photos');
    const uploadForm = document.getElementById('upload-form');
    const fileInput = uploadForm.querySelector('input[name="device_files"]');
    const subjectSelect = uploadForm.querySelector('select[name="subject_id"]');
    const submitBtn = uploadForm.querySelector('button[type="submit"]');
    const uploadStatus = document.getElementById('upload-status');

    let stream = null;
    let capturedFiles = [];

    const setStatus = (type, message) => {
        if (!uploadStatus) return;
        uploadStatus.innerHTML = `<div class="alert alert-${type} mb-0">${message}</div>`;
    };

    const clearStatus = () => {
        if (uploadStatus) uploadStatus.innerHTML = '';
    };

    const getSelectedImageCount = () => Array.from(fileInput.files || []).filter(file => file.type.startsWith('image/')).length;
    const getSelectedPdfCount = () => Array.from(fileInput.files || []).filter(file => file.type === 'application/pdf').length;

    const readFileAsDataURL = (file) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(file);
    });

    const loadImageElement = (src) => new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });

    const resetFormState = () => {
        capturedFiles = [];
        capturedPhotos.innerHTML = '';
        fileInput.value = '';
    };

    // Start camera
    startBtn.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment'
                }
            });
            video.srcObject = stream;
            video.style.display = 'block';
            video.play();

            startBtn.style.display = 'none';
            takePhotoBtn.style.display = 'inline-block';
            stopBtn.style.display = 'inline-block';
            clearStatus();
        } catch (err) {
            setStatus('danger', 'Error accessing camera: ' + err.message);
        }
    });

    // Take photo
    takePhotoBtn.addEventListener('click', () => {
        if ((capturedFiles.length + getSelectedImageCount()) >= MAX_FILES) {
            setStatus('danger', `You can upload up to ${MAX_FILES} images in one submission.`);
            return;
        }

        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0);

        canvas.toBlob((blob) => {
            if (!blob) {
                setStatus('danger', 'Could not capture photo. Please try again.');
                return;
            }

            const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
            capturedFiles.push(file);

            const preview = document.createElement('div');
            preview.className = 'captured-photo position-relative';
            preview.style.cssText = 'width: 100px; height: 80px; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden;';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(blob);
            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';

            const deleteBtn = document.createElement('button');
            deleteBtn.innerHTML = '×';
            deleteBtn.className = 'btn btn-sm btn-danger position-absolute top-0 end-0';
            deleteBtn.style.cssText = 'width: 20px; height: 20px; padding: 0; line-height: 1; font-size: 12px;';
            deleteBtn.onclick = () => {
                const index = capturedFiles.indexOf(file);
                if (index > -1) capturedFiles.splice(index, 1);
                preview.remove();
            };

            preview.appendChild(img);
            preview.appendChild(deleteBtn);
            capturedPhotos.appendChild(preview);
        }, 'image/jpeg', 0.85);
    });

    // Stop camera
    stopBtn.addEventListener('click', () => {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.style.display = 'none';
        startBtn.style.display = 'inline-block';
        takePhotoBtn.style.display = 'none';
        stopBtn.style.display = 'none';
    });

    // Validate file selection limits
    fileInput.addEventListener('change', () => {
        clearStatus();
        const imageCount = getSelectedImageCount();
        const pdfCount = getSelectedPdfCount();

        if (pdfCount > 1) {
            setStatus('danger', 'Please choose only one PDF file.');
            fileInput.value = '';
            return;
        }

        if (pdfCount === 1 && (imageCount > 0 || capturedFiles.length > 0)) {
            setStatus('danger', 'Please upload either images or a PDF, not both.');
            fileInput.value = '';
            return;
        }

        if ((imageCount + capturedFiles.length) > MAX_FILES) {
            setStatus('danger', `You can upload up to ${MAX_FILES} images (including captured photos).`);
            fileInput.value = '';
        }
    });

    // Handle form submission
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearStatus();

        if (!subjectSelect.value) {
            setStatus('danger', 'Please select a subject.');
            return;
        }

        const selectedFiles = Array.from(fileInput.files || []);
        const pdfFiles = selectedFiles.filter(file => file.type === 'application/pdf');
        const imageFiles = selectedFiles.filter(file => file.type.startsWith('image/'));
        const totalImageCount = imageFiles.length + capturedFiles.length;

        if (totalImageCount === 0 && pdfFiles.length === 0) {
            setStatus('danger', 'Please capture or select answer sheet images, or upload a PDF.');
            return;
        }

        if (pdfFiles.length === 1 && totalImageCount > 0) {
            setStatus('danger', 'Please upload either images or a PDF, not both.');
            return;
        }

        if (totalImageCount > MAX_FILES) {
            setStatus('danger', `You can upload up to ${MAX_FILES} images in one submission.`);
            return;
        }

        const csrfToken = uploadForm.querySelector('input[name="csrf_token"]').value;
        let pdfBlob;
        let pdfFileName = `answers_${Date.now()}.pdf`;
        let originalNames = [];

        try {
            if (totalImageCount > 0) {
                // Check for jsPDF availability
                let jsPDF;
                if (window.jspdf && window.jspdf.jsPDF) {
                    jsPDF = window.jspdf.jsPDF;
                } else if (window.jsPDF) {
                    jsPDF = window.jsPDF;
                } else {
                    setStatus('danger', 'PDF library failed to load. Please refresh the page and try again.');
                    return;
                }

                setStatus('info', 'Generating PDF from images...');
                
                const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const margin = 10;
                const maxWidth = pageWidth - margin * 2;
                const maxHeight = pageHeight - margin * 2;
                let firstPage = true;
                const orderedFiles = [...capturedFiles, ...imageFiles];

                originalNames = orderedFiles.map(file => file.name);

                for (const file of orderedFiles) {
                    const dataUrl = await readFileAsDataURL(file);
                    const imgElement = await loadImageElement(dataUrl);

                    const widthRatio = maxWidth / imgElement.width;
                    const heightRatio = maxHeight / imgElement.height;
                    const ratio = Math.min(widthRatio, heightRatio, 1);
                    const renderWidth = imgElement.width * ratio;
                    const renderHeight = imgElement.height * ratio;
                    const x = (pageWidth - renderWidth) / 2;
                    const y = (pageHeight - renderHeight) / 2;
                    const imageType = file.type.includes('png') ? 'PNG' : 'JPEG';

                    if (!firstPage) {
                        pdf.addPage();
                    }
                    firstPage = false;

                    pdf.addImage(dataUrl, imageType, x, y, renderWidth, renderHeight);
                }

                pdfBlob = pdf.output('blob');
            } else if (pdfFiles.length === 1) {
                pdfBlob = pdfFiles[0];
                pdfFileName = pdfFiles[0].name || pdfFileName;
                originalNames = [pdfFileName];
            }
        } catch (error) {
            console.error(error);
            setStatus('danger', 'Failed to generate PDF. Please try again.');
            return;
        }

        setStatus('info', 'Uploading, please wait...');
        submitBtn.disabled = true;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('ajax', '1');
        formData.append('subject_id', subjectSelect.value);
        formData.append('original_names', originalNames.join(', '));

        const pdfFileToSend = pdfBlob instanceof File ? pdfBlob : new File([pdfBlob], pdfFileName, { type: 'application/pdf' });
        formData.append('pdf_file', pdfFileToSend);

        try {
            const response = await fetch('upload.php', {
                method: 'POST',
                body: formData
            });

            // Get response text first
            const responseText = await response.text();

            // Check if response is OK
            if (!response.ok) {
                console.error('Server error:', responseText);
                setStatus('danger', 'Server error. Please check console for details.');
                submitBtn.disabled = false;
                return;
            }

            // Try to parse JSON response
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('Failed to parse JSON:', jsonError);
                console.error('Response text:', responseText);
                setStatus('danger', 'Invalid server response. Please check console for details.');
                submitBtn.disabled = false;
                return;
            }

            if (result.status === 'success') {
                setStatus('success', result.message || 'Uploaded successfully.');
                resetFormState();
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1200);
                }
            } else {
                setStatus('danger', result.message || 'Upload failed. Please try again.');
            }
        } catch (error) {
            console.error('Upload error:', error);
            setStatus('danger', 'Unexpected error while uploading. Error: ' + error.message);
        } finally {
            submitBtn.disabled = false;
        }
    });
});
</script>

    </div>
</div>

<?php include('../includes/footer.php'); ?>
