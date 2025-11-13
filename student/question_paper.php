<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');
checkLogin('student');

require('../vendor/fpdf/fpdf.php');

// Configuration for file upload
$allowedMime = ['image/jpeg','image/png','image/gif'];
$maxFileSize = 4 * 1024 * 1024; // 4MB per image
$maxFiles = 15;

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
if ($subject_id <= 0) { echo '<div class="alert alert-danger">Invalid subject.</div>'; include('../includes/footer.php'); exit; }

// Handle file upload
if(isset($_POST['upload'])){
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $upload_error = 'Invalid CSRF token.';
    } else {
        // Enforce one submission per subject per student
        $dupStmt = $conn->prepare("SELECT 1 FROM submissions WHERE student_id = ? AND subject_id = ? LIMIT 1");
        if ($dupStmt) {
            $dupStmt->bind_param("ii", $_SESSION['user_id'], $subject_id);
            $dupStmt->execute();
            $dupRes = $dupStmt->get_result();
            if ($dupRes && $dupRes->num_rows > 0) {
                $upload_error = 'You have already submitted for this subject. Only one submission allowed.';
            }
            $dupStmt->close();
        }

        if(!isset($upload_error) && !isset($_FILES['images'])) {
            $upload_error = 'No files uploaded.';
        } elseif(!isset($upload_error)) {
            $files = $_FILES['images'];
            $count = count($files['tmp_name']);
            if ($count > $maxFiles) {
                $upload_error = 'Too many files. Max ' . $maxFiles . ' allowed.';
            } else {
                $pdf = new FPDF();
                $allOk = true;
                $totalFileSize = 0;
                $originalFilenames = [];
                
                for($i=0; $i<$count; $i++) {
                    if($files['error'][$i] !== UPLOAD_ERR_OK) { $allOk = false; break; }
                    if($files['size'][$i] > $maxFileSize) { $allOk = false; $upload_error = 'File too large: '.sanitize($files['name'][$i]); break; }
                    $totalFileSize += $files['size'][$i];
                    $originalFilenames[] = $files['name'][$i];
                    
                    $tmpName = $files['tmp_name'][$i];
                    $mimeType = $files['type'][$i];
                    if(!in_array($mimeType, $allowedMime)) { $allOk = false; $upload_error = 'Invalid file type.'; break; }
                    
                    $pdf->AddPage();
                    
                    // Get image info and determine type
                    $imageInfo = getimagesize($tmpName);
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                    $mimeType = $imageInfo['mime'];
                    
                    // Determine FPDF image type from MIME type
                    $fpdfType = '';
                    switch($mimeType) {
                        case 'image/jpeg':
                            $fpdfType = 'JPG';
                            break;
                        case 'image/png':
                            $fpdfType = 'PNG';
                            break;
                        case 'image/gif':
                            $fpdfType = 'GIF';
                            break;
                        default:
                            $fpdfType = 'JPG'; // fallback
                    }
                    
                    // Fit image to page preserving aspect ratio
                    $pageWidth = 190; // A4 width minus margins
                    $pageHeight = 277; // A4 height minus margins
                    $ratio = min($pageWidth/$width, $pageHeight/$height);
                    $newW = $width * $ratio;
                    $newH = $height * $ratio;
                    $x = (210 - $newW)/2; // center on A4 (210mm width)
                    $y = (297 - $newH)/2; // center on A4 height
                    
                    // Pass the image type explicitly to FPDF
                    $pdf->Image($tmpName, $x, $y, $newW, $newH, $fpdfType);
                }
                
                if($allOk) {
                    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    $pdfFileRelative = '../uploads/pdfs/' . $filename; // Relative path for web access
                    $pdfFile = dirname(__DIR__) . '/uploads/pdfs/' . $filename; // Absolute path for file system
                    $pdf->Output('F', $pdfFile);

                    // Prepare comprehensive submission data
                    $originalFilenamesStr = implode(', ', $originalFilenames);
                    
                    $stmt = $conn->prepare("INSERT INTO submissions (student_id, subject_id, pdf_url, original_filename, file_size, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $storePath = $pdfFileRelative; // store relative web path
                    $stmt->bind_param("iissi", $_SESSION['user_id'], $subject_id, $storePath, $originalFilenamesStr, $totalFileSize);
                    
                    if($stmt->execute()) {
                        $upload_success = "Answer sheet uploaded successfully! <a href='view_submissions.php'>View Submissions</a>";
                    } else {
                        $upload_error = 'Database error while saving submission.';
                    }
                }
            }
        }
    }
}

// Load subject information
$stmt = $conn->prepare("SELECT * FROM subjects WHERE id=? AND is_active=1 LIMIT 1");
$stmt->bind_param('i', $subject_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
if (!$subject) { 
    echo '<div class="alert alert-warning">Subject not found or inactive.</div>'; 
    include('../includes/footer.php'); 
    exit; 
}
?>

<style>
.question-paper {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.question-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.question-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.upload-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 2rem;
    color: white;
    margin-top: 2rem;
}

.upload-card {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}

.camera-section {
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
</style>

<div class="row">
    <div class="col-lg-10 col-xl-9">
        <!-- Answer Sheet Submission Section -->
        <div class="question-paper">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="mb-1 text-primary"><?php echo htmlspecialchars($subject['code'].' - '.$subject['name']); ?></h2>
                    <div class="text-muted">
                        <i class="fas fa-upload"></i> Answer Sheet Submission Portal
                    </div>
                </div>
                <div>
                    <a href="subjects.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Change Subject
                    </a>
                </div>
            </div>

            <!-- Instructions -->
            <div class="alert alert-info mb-4">
                <h6><i class="fas fa-info-circle"></i> Submission Instructions:</h6>
                <ul class="mb-0">
                    <li>Take clear photos of your handwritten answer sheets</li>
                    <li>Ensure all pages are visible and well-lit</li>
                    <li>Upload all pages of your answers below</li>
                    <li>The system will automatically convert your images to PDF</li>
                </ul>
            </div>

            <!-- Info Card -->
            <div class="alert alert-primary mb-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-file-upload fa-2x text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="mb-1">Ready to Submit Your Answer Sheet?</h5>
                        <p class="mb-0">Use the upload section below to submit your handwritten answers. You can take photos using your device camera or select existing images from your gallery.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Answer Sheet Section -->
        <div class="upload-section">
            <h3 class="mb-4"><i class="fas fa-upload"></i> Submit Your Answer Sheet</h3>
            
            <?php if(isset($upload_success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $upload_success ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($upload_error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($upload_error) ?>
                </div>
            <?php endif; ?>

            <div class="upload-card">
                <!-- Camera Capture Section -->
                <div class="camera-section">
                    <h5 class="mb-3"><i class="fas fa-camera"></i> Take Photos with Camera</h5>
                    <div class="camera-controls">
                        <video id="camera-preview" class="mb-3" style="width: 100%; max-width: 400px; height: 300px; background: rgba(255,255,255,0.1); border: 2px dashed rgba(255,255,255,0.3); border-radius: 8px; display: none;"></video>
                        <canvas id="photo-canvas" style="display: none;"></canvas>
                        <div class="camera-buttons mb-3">
                            <button type="button" id="start-camera" class="btn btn-light me-2">
                                <i class="fas fa-camera"></i> Start Camera
                            </button>
                            <button type="button" id="take-photo" class="btn btn-success me-2" style="display: none;">
                                <i class="fas fa-camera-retro"></i> Capture Photo
                            </button>
                            <button type="button" id="stop-camera" class="btn btn-outline-light" style="display: none;">
                                <i class="fas fa-stop"></i> Stop Camera
                            </button>
                        </div>
                        <div id="captured-photos" class="d-flex flex-wrap gap-2 mb-3"></div>
                    </div>
                </div>

                <!-- File Upload Form -->
                <form method="POST" enctype="multipart/form-data" class="vstack gap-3" id="upload-form">
                    <?php csrf_input(); ?>
                    <div>
                        <label class="form-label text-white">
                            <i class="fas fa-folder-open"></i> Or Select Images from Device
                        </label>
                        <input type="file" name="images[]" multiple accept="image/*" class="form-control" capture="environment">
                        <small class="text-light">Allowed: JPG, PNG, GIF. Max 4MB each. Max <?= $maxFiles; ?> images.</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="upload" class="btn btn-light btn-lg">
                            <i class="fas fa-upload"></i> Upload & Convert to PDF
                        </button>
                        <a href="view_submissions.php" class="btn btn-outline-light">
                            <i class="fas fa-eye"></i> View Submissions
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('camera-preview');
    const canvas = document.getElementById('photo-canvas');
    const startBtn = document.getElementById('start-camera');
    const takePhotoBtn = document.getElementById('take-photo');
    const stopBtn = document.getElementById('stop-camera');
    const capturedPhotos = document.getElementById('captured-photos');
    const uploadForm = document.getElementById('upload-form');
    const fileInput = uploadForm.querySelector('input[type="file"]');
    
    let stream = null;
    let capturedFiles = [];

    // Start camera
    startBtn.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'environment' // Use back camera on mobile
                } 
            });
            video.srcObject = stream;
            video.style.display = 'block';
            video.play();
            
            startBtn.style.display = 'none';
            takePhotoBtn.style.display = 'inline-block';
            stopBtn.style.display = 'inline-block';
        } catch (err) {
            alert('Error accessing camera: ' + err.message);
        }
    });

    // Take photo
    takePhotoBtn.addEventListener('click', () => {
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0);
        
        // Convert canvas to blob
        canvas.toBlob((blob) => {
            const file = new File([blob], `answer_sheet_${Date.now()}.jpg`, { type: 'image/jpeg' });
            capturedFiles.push(file);
            
            // Create preview
            const preview = document.createElement('div');
            preview.className = 'captured-photo position-relative';
            preview.style.cssText = 'width: 100px; height: 80px; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; overflow: hidden;';
            
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
        }, 'image/jpeg', 0.8);
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

    // Handle form submission
    uploadForm.addEventListener('submit', (e) => {
        if (capturedFiles.length > 0) {
            // Create new FormData and add captured photos
            const dt = new DataTransfer();
            
            // Add captured photos
            capturedFiles.forEach(file => {
                dt.items.add(file);
            });
            
            // Add selected files
            Array.from(fileInput.files).forEach(file => {
                dt.items.add(file);
            });
            
            // Update file input
            fileInput.files = dt.files;
        }
    });
});
</script>

<?php include('../includes/footer.php'); ?>
