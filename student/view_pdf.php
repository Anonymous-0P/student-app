<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

// Get submission ID from URL
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

if (!$submission_id) {
    header('Location: view_submissions.php');
    exit;
}

// Verify the submission belongs to the current student
$stmt = $conn->prepare("
    SELECT s.*, sub.name as subject_name, sub.code as subject_code 
    FROM submissions s 
    LEFT JOIN subjects sub ON s.subject_id = sub.id 
    WHERE s.id = ? AND s.student_id = ?
");
$stmt->bind_param("ii", $submission_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: view_submissions.php');
    exit;
}

$submission = $result->fetch_assoc();

// Debug: Log the PDF URL from database (remove in production)
error_log("PDF URL from database: " . $submission['pdf_url']);

// Handle PDF serving
if (isset($_GET['serve_pdf']) && $_GET['serve_pdf'] == '1') {
    // Construct the correct PDF path
    $pdfUrl = $submission['pdf_url'];
    
    // Clean up the path - remove leading slash and normalize separators
    $pdfUrl = ltrim(str_replace('\\', '/', $pdfUrl), '/');
    
    // Try multiple possible paths to find the PDF
    $possiblePaths = [
        // Direct path construction
        dirname(__DIR__) . '/' . $pdfUrl,
        // With normalized slashes
        str_replace('/', DIRECTORY_SEPARATOR, dirname(__DIR__) . '/' . $pdfUrl),
        // Just the filename in uploads/pdfs
        dirname(__DIR__) . '/uploads/pdfs/' . basename($pdfUrl),
        // Alternative with Windows-style paths
        dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pdfUrl),
    ];
    
    $pdfPath = null;
    foreach ($possiblePaths as $testPath) {
        // Normalize path for Windows
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $testPath);
        if (file_exists($normalizedPath)) {
            $pdfPath = $normalizedPath;
            break;
        }
    }
    
    if (!$pdfPath || !file_exists($pdfPath)) {
        http_response_code(404);
        $debugInfo = "PDF file not found. Tried paths:\n";
        foreach ($possiblePaths as $i => $path) {
            $debugInfo .= ($i + 1) . ". " . $path . " - " . (file_exists($path) ? "EXISTS" : "NOT FOUND") . "\n";
        }
        $debugInfo .= "\nPDF URL from DB: " . $submission['pdf_url'];
        die('<pre>' . htmlspecialchars($debugInfo) . '</pre>');
    }
    
    // Set security headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="submission_' . $submission_id . '.pdf"');
    header('Content-Length: ' . filesize($pdfPath));
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    
    // Output the PDF
    readfile($pdfPath);
    exit;
}

// Set security headers for the HTML page
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission - <?= htmlspecialchars($submission['subject_code'] ?? 'Unknown') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }
        
        .pdf-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .pdf-viewer-container {
            height: calc(100vh - 100px);
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin: 1rem;
            overflow: auto;
            position: relative;
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .pdf-header {
                padding: 0.5rem 0.75rem !important;
            }
            
            .pdf-header h5 {
                font-size: 0.9rem !important;
                margin-bottom: 0.25rem !important;
            }
            
            .pdf-header .small,
            .pdf-header small {
                font-size: 0.7rem !important;
            }
            
            .pdf-header .btn {
                padding: 0.375rem 0.75rem !important;
                font-size: 0.8rem !important;
            }
            
            .pdf-viewer-container {
                height: calc(100vh - 80px) !important;
                margin: 0.5rem !important;
            }
        }
        
        .pdf-embed {
            width: 100%;
            height: 100%;
            border: none;
            pointer-events: auto;
            overflow: auto;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            user-select: none !important;
            -webkit-touch-callout: none !important;
            -webkit-user-drag: none !important;
            -moz-user-drag: none !important;
            user-drag: none !important;
        }
        
        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            background: rgba(255,255,255,0.9);
            padding: 2rem;
            border-radius: 8px;
        }
        
        /* Disable print styles */
        @media print {
            body { display: none !important; }
        }
        
        /* Security notice styling */
        .alert-info {
            background: rgba(13, 110, 253, 0.1);
            border: 1px solid rgba(13, 110, 253, 0.2);
            color: #0c63e4;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="pdf-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">
                        <i class="fas fa-file-pdf me-2"></i>
                        Your Submission
                    </h5>
                    <small class="opacity-75">
                        <?= htmlspecialchars($submission['subject_name'] ?? 'Unknown Subject') ?>
                        
                        <?php if ($submission['marks'] !== null): ?>
                        | Marks: <?= number_format((float)$submission['marks'], 1) ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-auto">
                    <?php if(!empty($submission['annotated_pdf_url']) && file_exists('../' . $submission['annotated_pdf_url'])): ?>
                        <a href="../<?= htmlspecialchars($submission['annotated_pdf_url']) ?>" 
            
                           class="btn btn-success btn-sm me-2">
                            <i class="fas fa-check-circle me-1"></i> View Annotated Version
                        </a>
                    <?php endif; ?>
                    <a href="view_submissions.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Go Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Viewer Container -->
    <div class="pdf-viewer-container">
        <!-- Loading Indicator -->
        <div id="loading" class="loading-spinner">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading PDF...</span>
                </div>
                <p class="mt-2 text-muted">Loading your submission...</p>
            </div>
        </div>

        <!-- Secure PDF Embed -->
        <object 
            id="pdf-object"
            data="?serve_pdf=1&submission_id=<?= $submission['id'] ?>#toolbar=0&navpanes=0&scrollbar=1&view=FitH&pagemode=none&disableexternallinks=true" 
            type="application/pdf" 
            class="pdf-embed"
            style="display: none;"
            onload="hideLoding()"
            oncontextmenu="return false;">
            
            <!-- Fallback: Iframe -->
            <iframe 
                id="pdf-iframe"
                src="?serve_pdf=1&submission_id=<?= $submission['id'] ?>#toolbar=0&navpanes=0&scrollbar=1&view=FitH&pagemode=none&disableexternallinks=true"
                class="pdf-embed"
                scrolling="auto"
                allowfullscreen
                onload="hideLoding()"
                oncontextmenu="return false;"
                onselectstart="return false;"
                ondragstart="return false;">
                
                <!-- Final Fallback -->
                <div class="p-4 text-center">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h4>PDF Viewer Not Supported</h4>
                        <p>Your browser doesn't support PDF viewing in this mode.</p>
                        <a href="?serve_pdf=1&submission_id=<?= $submission['id'] ?>" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt me-2"></i>Open PDF in New Tab
                        </a>
                    </div>
                </div>
            </iframe>
        </object>
    </div>

    <script>
        let pdfLoaded = false;
        
        function hideLoding() {
            if (!pdfLoaded) {
                console.log('PDF loaded successfully');
                document.getElementById('loading').style.display = 'none';
                
                const pdfObject = document.getElementById('pdf-object');
                const pdfIframe = document.getElementById('pdf-iframe');
                
                if (pdfObject) {
                    pdfObject.style.display = 'block';
                } else if (pdfIframe) {
                    pdfIframe.style.display = 'block';
                }
                
                pdfLoaded = true;
            }
        }
        
        // Security measures
        document.addEventListener('DOMContentLoaded', function() {
            // Disable right-click
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                return false;
            }, true);
            
            // Disable text selection
            document.addEventListener('selectstart', function(e) {
                e.preventDefault();
                return false;
            });
            
            // Disable drag and drop
            document.addEventListener('dragstart', function(e) {
                e.preventDefault();
                return false;
            });
            
            // Disable keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Allow navigation keys
                const allowedKeys = [
                    'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight',
                    'PageUp', 'PageDown', 'Home', 'End', 'Space'
                ];
                
                if (allowedKeys.includes(e.key)) {
                    return true;
                }
                
                // Disable Ctrl+S, Ctrl+P, F12, etc.
                if (e.ctrlKey && (
                    e.key === 's' || e.key === 'S' ||
                    e.key === 'p' || e.key === 'P' ||
                    e.key === 'a' || e.key === 'A' ||
                    e.key === 'c' || e.key === 'C'
                )) {
                    e.preventDefault();
                    return false;
                }
                
                if (e.key === 'F12' || 
                    (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J'))) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Disable print function
            window.print = function() {
                return false;
            };
        });
    </script>
</body>
</html>