<?php
require_once('../config/config.php');
require_once('../includes/functions.php');
include('../includes/header.php');

checkLogin('student');

require('../vendor/fpdf/fpdf.php');

// Configuration
$allowedMime = ['image/jpeg','image/png','image/gif'];
$maxFileSize = 4 * 1024 * 1024; // 4MB per image
$maxFiles = 15;

// Get available subjects for dropdown
$subjectsQuery = "SELECT id, code, name FROM subjects WHERE is_active=1 ORDER BY code";
$subjectsResult = $conn->query($subjectsQuery);

if(isset($_POST['upload'])){
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo '<p>Invalid CSRF token.</p>';
    } else {
        $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
        
        if(empty($subject_id)) {
            echo '<div class="alert alert-danger">Please select a subject.</div>';
        } else if(!isset($_FILES['images'])) {
            echo '<p>No files uploaded.</p>';
        } else {
            $files = $_FILES['images'];
            $count = count($files['tmp_name']);
            if ($count > $maxFiles) {
                echo '<p>Too many files. Max ' . $maxFiles . ' allowed.</p>';
            } else {
                $pdf = new FPDF();
                $allOk = true;
                $totalFileSize = 0;
                $originalFilenames = [];
                
                for($i=0; $i<$count; $i++) {
                    if($files['error'][$i] !== UPLOAD_ERR_OK) { $allOk = false; break; }
                    if($files['size'][$i] > $maxFileSize) { $allOk = false; echo '<p>File too large: '.sanitize($files['name'][$i]).'</p>'; break; }
                    $totalFileSize += $files['size'][$i];
                    $originalFilenames[] = $files['name'][$i];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $files['tmp_name'][$i]);
                    finfo_close($finfo);
                    if(!in_array($mime, $allowedMime)) { $allOk = false; echo '<p>Invalid file type: '.sanitize($files['name'][$i]).'</p>'; break; }
                }
                if($allOk) {
                    for($i=0; $i<$count; $i++) {
                        $tmpName = $files['tmp_name'][$i];
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
                        echo "<p>PDF uploaded successfully! <a href='view_submissions.php'>View Submissions</a></p>";
                    } else {
                        echo '<p>Database error while saving submission.</p>';
                    }
                }
            }
        }
    }
}
?>

<div class="row">
    <div class="col-lg-9 col-xl-8">
        <div class="page-card">
            <h2 class="mb-3">Upload Answers</h2>
            
            <!-- Camera Capture Section -->
            <div class="mb-4">
                <h5 class="mb-3">📸 Take Photos with Camera</h5>
                <div class="camera-section">
                    <video id="camera-preview" class="mb-3" style="width: 100%; max-width: 400px; height: 300px; background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px; display: none;"></video>
                    <canvas id="photo-canvas" style="display: none;"></canvas>
                    <div class="camera-controls mb-3">
                        <button type="button" id="start-camera" class="btn btn-outline-primary me-2">
                            📷 Start Camera
                        </button>
                        <button type="button" id="take-photo" class="btn btn-success me-2" style="display: none;">
                            📸 Capture Photo
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
                <div>
                    <label class="form-label">� Select Subject <span class="text-danger">*</span></label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Choose a subject...</option>
                        <?php while($subject = $subjectsResult->fetch_assoc()): ?>
                            <option value="<?php echo (int)$subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['code'] . ' - ' . $subject['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">�📁 Or Select Images from Device</label>
                    <input type="file" name="images[]" multiple accept="image/*" class="form-control" capture="environment">
                    <small>Allowed: JPG, PNG, GIF. Max 4MB each. Max <?= $maxFiles; ?> images.</small>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="upload" class="btn btn-primary">Upload & Convert to PDF</button>
                    <a href="view_submissions.php" class="btn btn-outline-secondary">View Submissions</a>
                </div>
            </form>
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
            const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
            capturedFiles.push(file);
            
            // Create preview
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
