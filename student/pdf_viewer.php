<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];

// Handle PDF serving FIRST - before any HTML output
if (isset($_GET['serve_pdf']) && isset($_GET['paper_id'])) {
    $paper_id = (int)$_GET['paper_id'];
    
    // Get paper details for serving with access control
    $paperStmt = $conn->prepare("SELECT qp.*, s.code as subject_code, s.name as subject_name,
                                (SELECT COUNT(*) FROM purchased_subjects ps WHERE ps.subject_id = qp.subject_id AND ps.student_id = ? AND ps.status = 'active' AND ps.expiry_date > CURDATE()) as has_access
                                FROM question_papers qp 
                                LEFT JOIN subjects s ON qp.subject_id = s.id 
                                WHERE qp.id = ? AND qp.is_active = 1");
    $paperStmt->bind_param("ii", $student_id, $paper_id);
    $paperStmt->execute();
    $paper = $paperStmt->get_result()->fetch_assoc();
    
    // Check access before serving PDF
    if (!$paper || $paper['has_access'] == 0) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Access denied. You need to purchase this subject to view question papers.';
        exit;
    }
    
    if ($paper && file_exists($paper['file_path'])) {
        // Set headers for secure PDF display
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $paper['original_filename'] . '"');
        header('Content-Length: ' . filesize($paper['file_path']));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, no-cache, no-store, max-age=0, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Security headers to prevent caching and downloading
        header('X-Content-Type-Options: nosniff');
        // Don't use X-Frame-Options: SAMEORIGIN for PDF serving as it might block iframe
        header('X-Robots-Tag: noindex, nofollow, nosnippet, noarchive');
        
        // Clear any output buffer before sending PDF
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output the PDF
        readfile($paper['file_path']);
        exit;
    }
    
    // If file not found, return proper error
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'PDF file not found: ' . (isset($paper['file_path']) ? $paper['file_path'] : 'No file path');
    exit;
}

// Regular page handling - check for paper_id
if (!isset($_GET['paper_id'])) {
    header('Location: question_papers.php');
    exit;
}

$paper_id = (int)$_GET['paper_id'];

// Get paper details for page display with access control
$paperStmt = $conn->prepare("SELECT qp.*, s.code as subject_code, s.name as subject_name,
                            (SELECT COUNT(*) FROM purchased_subjects ps WHERE ps.subject_id = qp.subject_id AND ps.student_id = ? AND ps.status = 'active' AND ps.expiry_date > CURDATE()) as has_access
                            FROM question_papers qp 
                            LEFT JOIN subjects s ON qp.subject_id = s.id 
                            WHERE qp.id = ? AND qp.is_active = 1");
$paperStmt->bind_param("ii", $student_id, $paper_id);
$paperStmt->execute();
$paper = $paperStmt->get_result()->fetch_assoc();

if (!$paper) {
    header('Location: question_papers.php');
    exit;
}

// Check if student has purchased access to this subject
if ($paper['has_access'] == 0) {
    // Redirect to purchase page with subject information
    header('Location: browse_exams.php?subject_id=' . $paper['subject_id'] . '&access_denied=1');
    exit;
}

// Track view (only for page view, not PDF serving)
$viewStmt = $conn->prepare("INSERT INTO question_paper_downloads (question_paper_id, student_id, ip_address) VALUES (?, ?, ?)");
$viewStmt->bind_param("iis", $paper_id, $student_id, $_SERVER['REMOTE_ADDR']);
$viewStmt->execute();

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
    <title><?= htmlspecialchars($paper['title']) ?> - PDF Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            user-select: none; /* Disable text selection */
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
            overflow: auto; /* Allow scrolling */
            position: relative;
        }
        
        .pdf-embed {
            width: 100%;
            height: 100%;
            border: none;
            pointer-events: auto;
            overflow: auto; /* Enable scrolling in PDF */
        }
        
        .pdf-fallback {
            padding: 3rem;
            text-align: center;
            background: #f8f9fa;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
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
        
        /* Hide default browser PDF controls and toolbar */
        iframe {
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        
        /* Hide PDF toolbar and controls */
        iframe[src*="pdf"]::after,
        object[data*="pdf"]::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: #f8f9fa;
            z-index: 1000;
            pointer-events: none;
        }
        
        /* Additional security - hide any PDF controls */
        .pdf-embed {
            position: relative;
        }
        
        .pdf-embed::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: transparent;
            z-index: 100;
            pointer-events: none;
        }
        
        /* Security notice styling */
        .alert-info {
            background: rgba(13, 110, 253, 0.1);
            border: 1px solid rgba(13, 110, 253, 0.2);
            color: #0c63e4;
        }

        /* Additional PDF Security - Block All Interactions */
        .pdf-embed, 
        iframe[src*="pdf"], 
        object[data*="pdf"], 
        embed[src*="pdf"] {
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            user-select: none !important;
            -webkit-touch-callout: none !important;
            -webkit-user-drag: none !important;
            -moz-user-drag: none !important;
            user-drag: none !important;
        }

        /* Block context menu on PDF elements */
        iframe, object, embed {
            -webkit-context-menu: none !important;
            context-menu: none !important;
        }

        /* Additional layer to capture all events */
        .pdf-viewer-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
            pointer-events: none;
            background: transparent;
        }

        /* Force disable all PDF controls */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
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
                        <?= htmlspecialchars($paper['title']) ?>
                    </h5>
                    <small class="opacity-75">
                        <?= htmlspecialchars($paper['subject_name'] ?? 'Unknown Subject') ?> - <?= htmlspecialchars($paper['exam_type']) ?>
                        <?php if ($paper['grade_level']): ?>
                        | Grade <?= htmlspecialchars($paper['grade_level']) ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="col-auto">
                    <a href="dashboard.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Go Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Viewer Container -->
    <!-- Exam Timer (outside pdf-viewer-container) -->
    <div id="exam-timer" style="position:sticky;top:0;z-index:1000;background:#fffbe6;padding:12px 0 8px 0;text-align:center;font-size:1.3rem;font-weight:600;color:#d35400;border-bottom:2px solid #f6e58d;">
        Time Remaining: <span id="timer-display">03:00:00</span>
    </div>
        <!-- Timer Expired Popup -->
        <div id="timer-expired-popup" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:20000;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;">
            <div style="background:#fff;padding:2rem 1.5rem;border-radius:12px;box-shadow:0 4px 32px rgba(0,0,0,0.18);max-width:90vw;text-align:center;">
                <div style="font-size:2rem;color:#d35400;margin-bottom:1rem;"><i class='fas fa-hourglass-end'></i></div>
                <div style="font-size:1.2rem;font-weight:500;">Time is up! The exam duration has ended.</div>
                <div style="margin:1.2rem 0 0.5rem 0;font-size:1rem;">Redirecting to answer upload page...</div>
            </div>
        </div>
    <div class="pdf-viewer-container">

    <script>
    // 3 hour countdown timer (3 * 60 * 60 seconds)
    let totalSeconds = 3 * 60 * 60;
    const timerDisplay = document.getElementById('timer-display');

    function updateTimer() {
        const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
        const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
        const seconds = String(totalSeconds % 60).padStart(2, '0');
        timerDisplay.textContent = `${hours}:${minutes}:${seconds}`;
    }

    function tick() {
        if (totalSeconds > 0) {
            totalSeconds--;
            updateTimer();
        } else {
            clearInterval(timerInterval);
            timerDisplay.textContent = '00:00:00';
            // Show custom popup and redirect to upload.php
            var popup = document.getElementById('timer-expired-popup');
            if (popup) {
                popup.style.display = 'flex';
            }
            setTimeout(function() {
                window.location.href = 'upload.php';
            }, 5000);
        }
    }

    updateTimer();
    const timerInterval = setInterval(tick, 1000);
    </script>
        <!-- Loading Indicator -->
        <div id="loading" class="loading-spinner">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading PDF...</span>
                </div>
                <p class="mt-2 text-muted">Loading question paper...</p>
            </div>
        </div>

        <!-- Secure PDF Embed with Maximum Restrictions -->
        <object 
            id="pdf-object"
            data="?serve_pdf=1&paper_id=<?= $paper['id'] ?>#toolbar=0&navpanes=0&scrollbar=1&view=FitH&pagemode=none&disableexternallinks=true" 
            type="application/pdf" 
            class="pdf-embed"
            style="display: none; -webkit-user-select: none; -moz-user-select: none; user-select: none;"
            onload="hideLoding()"
            oncontextmenu="showSecurityAlert('Right-click is disabled for content protection.'); return false;">
            
            <!-- Fallback: Iframe with Maximum Security -->
            <iframe 
                id="pdf-iframe"
                src="?serve_pdf=1&paper_id=<?= $paper['id'] ?>#toolbar=0&navpanes=0&scrollbar=1&view=FitH&pagemode=none&disableexternallinks=true"
                class="pdf-embed"
                style="-webkit-user-select: none; -moz-user-select: none; user-select: none;"
                scrolling="auto"
                allowfullscreen
                onload="hideLoding()"
                oncontextmenu="showSecurityAlert('Right-click is disabled for content protection.'); return false;"
                onselectstart="return false;"
                ondragstart="return false;">
                
                <!-- Final Fallback for browsers without PDF support -->
                <div class="pdf-fallback">
                    <div class="alert alert-warning m-3">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h4>PDF Viewer Not Supported</h4>
                        <p>Your browser doesn't support PDF viewing in this mode.</p>
                        <p class="text-muted">Please try one of these options:</p>
                        <div class="d-grid gap-2">
                            <a href="?serve_pdf=1&paper_id=<?= $paper['id'] ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-external-link-alt me-2"></i>Open PDF in New Tab
                            </a>
                            <button onclick="retryPdfLoad()" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh me-2"></i>Retry Loading
                            </button>
                        </div>
                    </div>
                </div>
            </iframe>
        </object>
        
        
        
        <!-- Toolbar blocking overlay -->
        <div id="toolbar-blocker" style="
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 1000;
            pointer-events: none;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            opacity: 0.95;
        ">
            <i class="fas fa-shield-alt me-2"></i>
            <span>Secure View - Toolbar Hidden</span>
        </div>
    </div>

    <!-- Answer Sheet Upload Section -->
    <!-- <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-upload text-primary me-2"></i>
                            Upload Your Answer Sheet for this Question Paper
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="upload-status" class="mb-3"></div>
                        
                        
                        <div class="mb-4">
                        <div class="text-center my-4">
                            <a href="upload.php" class="btn btn-lg btn-success">
                                <i class="fas fa-upload me-2"></i>Submit Answer Sheet
                            </a>
                        </div>
                        
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Instructions:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Take clear photos of each answer sheet page</li>
                                    <li>Ensure good lighting and avoid shadows</li>
                                    <li>All pages will be compiled into a single PDF</li>
                                    <li>Review your submission before uploading</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> -->
    
    <!-- Submit Button at the bottom of PDF viewer -->
    <div class="text-center my-8">
            <a href="upload.php" class="btn btn-lg btn-success">
                <i class="fas fa-upload me-2"></i>Submit Answer Sheet
            </a>
        </div>

        
    <!-- Include jsPDF for image to PDF conversion -->
    <script src="https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js"></script>

    <script>
        let pdfLoaded = false;
        let loadAttempts = 0;
        const maxAttempts = 3;
        
        function hideLoding() {
            if (!pdfLoaded) {
                console.log('PDF loaded successfully');
                document.getElementById('loading').style.display = 'none';
                
                // Try object first, then iframe
                const pdfObject = document.getElementById('pdf-object');
                const pdfIframe = document.getElementById('pdf-iframe');
                
                if (pdfObject) {
                    pdfObject.style.display = 'block';
                } else if (pdfIframe) {
                    pdfIframe.style.display = 'block';
                }
                
                pdfLoaded = true;
                
                // Add final protection overlay after PDF loads
                addProtectionOverlay();
                
                console.log('PDF viewer ready - scrolling enabled');
            }
        }
        
        // Add protection overlay that blocks right-click but allows scrolling
        function addProtectionOverlay() {
            const pdfContainer = document.querySelector('.pdf-viewer-container');
            if (!pdfContainer) return;
            
            // Create transparent overlay
            const overlay = document.createElement('div');
            overlay.id = 'pdf-protection-overlay';
            overlay.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 999;
                background: transparent;
                pointer-events: none;
            `;
            
            // Enable pointer events only for right-click blocking
            overlay.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                e.stopPropagation();
                showSecurityAlert('Right-click is disabled for content protection.');
                return false;
            }, true);
            
            overlay.addEventListener('mousedown', function(e) {
                if (e.button === 2) { // Right mouse button
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true);
            
            // Temporarily enable pointer events for right-click detection
            overlay.addEventListener('mouseenter', function() {
                overlay.style.pointerEvents = 'auto';
            });
            
            overlay.addEventListener('mouseleave', function() {
                overlay.style.pointerEvents = 'none';
            });
            
            pdfContainer.appendChild(overlay);
            console.log('Protection overlay added');
        }
        
        function retryPdfLoad() {
            if (loadAttempts < maxAttempts) {
                loadAttempts++;
                console.log(`Retrying PDF load, attempt ${loadAttempts}`);
                
                // Reset state
                pdfLoaded = false;
                document.getElementById('loading').style.display = 'block';
                
                // Reload the PDF object
                const pdfObject = document.getElementById('pdf-object');
                if (pdfObject) {
                    const currentSrc = pdfObject.data;
                    pdfObject.data = '';
                    setTimeout(function() {
                        pdfObject.data = currentSrc + '&retry=' + loadAttempts;
                    }, 100);
                }
            } else {
                showSecurityAlert('Maximum retry attempts reached. Please refresh the page.');
            }
        }
        
        // Check PDF loading status
        function checkPdfStatus() {
            const pdfUrl = '?serve_pdf=1&paper_id=<?= $paper['id'] ?>';
            
            fetch(pdfUrl, { method: 'HEAD' })
                .then(response => {
                    console.log('PDF serve check:', response.status, response.statusText);
                    if (!response.ok) {
                        console.error('PDF serving failed:', response.status);
                        showSecurityAlert('PDF loading failed. Please try refreshing the page.');
                    }
                })
                .catch(error => {
                    console.error('PDF check failed:', error);
                    showSecurityAlert('Network error loading PDF. Please check your connection.');
                });
        }
        
        // Comprehensive security measures
        document.addEventListener('DOMContentLoaded', function() {
            
            // Check PDF availability
            checkPdfStatus();
            
            // Completely disable right-click context menu everywhere
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                showSecurityAlert('Right-click is disabled for content protection.');
                return false;
            }, true); // Use capture phase to block all right-clicks
            
            // Additional PDF-specific right-click blocking
            document.addEventListener('mousedown', function(e) {
                if (e.button === 2) { // Right mouse button
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true);
            
            // Block context menu on PDF elements specifically
            const pdfElements = document.querySelectorAll('.pdf-embed, #pdf-object, #pdf-iframe');
            pdfElements.forEach(element => {
                element.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }, true);
            });
            
            // Disable text selection (but not within PDF viewer)
            document.addEventListener('selectstart', function(e) {
                // Don't block selection within PDF viewer
                if (e.target.closest('.pdf-embed') || e.target.closest('#pdf-object') || e.target.closest('#pdf-iframe')) {
                    return true; // Allow selection within PDF for reading
                }
                e.preventDefault();
                return false;
            });
            
            // Disable drag and drop (but not PDF scrolling)
            document.addEventListener('dragstart', function(e) {
                // Don't block PDF interactions
                if (e.target.closest('.pdf-embed') || e.target.closest('#pdf-object') || e.target.closest('#pdf-iframe')) {
                    return true; // Allow PDF interactions
                }
                e.preventDefault();
                return false;
            });
            
            // Disable keyboard shortcuts for printing, saving, etc.
            // BUT ALLOW scrolling keys (arrows, page up/down, etc.)
            document.addEventListener('keydown', function(e) {
                // Allow navigation keys for PDF scrolling
                const allowedKeys = [
                    'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight',
                    'PageUp', 'PageDown', 'Home', 'End',
                    'Space' // Allow spacebar for page down
                ];
                
                if (allowedKeys.includes(e.key)) {
                    return true; // Allow these keys for PDF navigation
                }
                
                // Disable Ctrl+S (Save), Ctrl+P (Print), Ctrl+A (Select All), 
                // Ctrl+C (Copy), F12 (DevTools), Ctrl+Shift+I (DevTools), etc.
                if (e.ctrlKey && (
                    e.key === 's' || e.key === 'S' ||
                    e.key === 'p' || e.key === 'P' ||
                    e.key === 'a' || e.key === 'A' ||
                    e.key === 'c' || e.key === 'C' ||
                    e.key === 'v' || e.key === 'V' ||
                    e.key === 'x' || e.key === 'X'
                )) {
                    e.preventDefault();
                    showSecurityAlert('This action is disabled for content protection.');
                    return false;
                }
                
                // Disable F12, Ctrl+Shift+I, Ctrl+Shift+J (Developer Tools)
                if (e.key === 'F12' || 
                    (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J'))) {
                    e.preventDefault();
                    showSecurityAlert('Developer tools are disabled.');
                    return false;
                }
                
                // Disable F5/Ctrl+R (Refresh) - might be too restrictive, comment out if needed
                // if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                //     e.preventDefault();
                //     return false;
                // }
            });
            
            // Completely disable print function and related methods
            window.print = function() {
                showSecurityAlert('Printing is disabled for this document.');
                return false;
            };
            
            // Block additional print methods
            if (window.document) {
                document.execCommand = function(command) {
                    if (command === 'print') {
                        showSecurityAlert('Printing is disabled for this document.');
                        return false;
                    }
                };
            }
            
            // Block clipboard operations
            document.addEventListener('copy', function(e) {
                e.preventDefault();
                showSecurityAlert('Copying is disabled for content protection.');
                return false;
            });
            
            document.addEventListener('cut', function(e) {
                e.preventDefault();
                showSecurityAlert('Cutting is disabled for content protection.');
                return false;
            });
            
            document.addEventListener('paste', function(e) {
                e.preventDefault();
                return false;
            });
            
            // Override clipboard API
            if (navigator.clipboard) {
                navigator.clipboard.writeText = function() {
                    showSecurityAlert('Clipboard access is disabled for content protection.');
                    return Promise.reject('Clipboard disabled');
                };
            }
            
            // Monitor for window resize/devtools
            let devtools = {
                open: false,
                orientation: null
            };
            
            // Additional PDF-specific event blocking
            function blockPdfEvents() {
                const pdfContainer = document.querySelector('.pdf-viewer-container');
                const pdfObject = document.getElementById('pdf-object');
                const pdfIframe = document.getElementById('pdf-iframe');
                
                // Add event listeners to PDF container to capture all events
                if (pdfContainer) {
                    // Capture all mouse events on PDF container
                    ['mousedown', 'mouseup', 'click', 'contextmenu', 'selectstart', 'dragstart'].forEach(eventType => {
                        pdfContainer.addEventListener(eventType, function(e) {
                            // Only block right-click and context menu
                            if (eventType === 'contextmenu' || (eventType === 'mousedown' && e.button === 2)) {
                                e.preventDefault();
                                e.stopPropagation();
                                e.stopImmediatePropagation();
                                showSecurityAlert('Right-click is disabled for content protection.');
                                return false;
                            }
                        }, true); // Use capture phase
                    });
                }
                
                // Add specific blocking to PDF elements
                [pdfObject, pdfIframe].forEach(element => {
                    if (element) {
                        element.oncontextmenu = function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            showSecurityAlert('Right-click is disabled for content protection.');
                            return false;
                        };
                        
                        element.onmousedown = function(e) {
                            if (e.button === 2) {
                                e.preventDefault();
                                e.stopPropagation();
                                return false;
                            }
                        };
                    }
                });
            }
            
            // Try to access PDF content and disable its context menu
            function disablePdfContextMenu() {
                try {
                    const pdfObject = document.getElementById('pdf-object');
                    const pdfIframe = document.getElementById('pdf-iframe');
                    
                    // For object element
                    if (pdfObject && pdfObject.contentDocument) {
                        pdfObject.contentDocument.addEventListener('contextmenu', function(e) {
                            e.preventDefault();
                            return false;
                        }, true);
                    }
                    
                    // For iframe element
                    if (pdfIframe && pdfIframe.contentDocument) {
                        pdfIframe.contentDocument.addEventListener('contextmenu', function(e) {
                            e.preventDefault();
                            return false;
                        }, true);
                    }
                } catch (error) {
                    // Cross-origin restrictions prevent access to PDF content
                    console.log('PDF content access blocked by browser security (expected)');
                }
            }
            
            // Apply PDF blocking
            blockPdfEvents();
            
            // Try to disable PDF context menu after load
            setTimeout(disablePdfContextMenu, 1000);
            setTimeout(disablePdfContextMenu, 3000);
            setTimeout(disablePdfContextMenu, 5000);
            
            console.log('Enhanced PDF security initialized');
            
            setInterval(function() {
                if (window.outerHeight - window.innerHeight > 200 || 
                    window.outerWidth - window.innerWidth > 200) {
                    if (!devtools.open) {
                        devtools.open = true;
                        showSecurityAlert('Please close developer tools to continue viewing.');
                    }
                } else {
                    devtools.open = false;
                }
            }, 500);
        });
        
        // Enhanced PDF loading timeout with fallback
        setTimeout(function() {
            if (!pdfLoaded) {
                console.log('PDF loading timeout, showing fallback options');
                document.getElementById('loading').style.display = 'none';
                
                // Show fallback message
                const container = document.querySelector('.pdf-viewer-container');
                container.innerHTML = `
                    <div class="pdf-fallback">
                        <div class="alert alert-warning m-3">
                            <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                            <h4>PDF Loading Issue</h4>
                            <p>The PDF viewer is having trouble loading the document.</p>
                            <p class="text-muted">This might be due to browser compatibility or network issues.</p>
                            <div class="d-grid gap-2 mt-3">
                                <a href="?serve_pdf=1&paper_id=<?= $paper['id'] ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt me-2"></i>Open PDF in New Tab
                                </a>
                                <button onclick="location.reload()" class="btn btn-outline-secondary">
                                    <i class="fas fa-refresh me-2"></i>Refresh Page
                                </button>
                                <a href="question_papers.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Papers
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            }
        }, 5000); // Increased timeout to 5 seconds
        
        // Security alert function
        function showSecurityAlert(message) {
            // Create a subtle toast notification instead of alert
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #dc3545;
                color: white;
                padding: 12px 20px;
                border-radius: 5px;
                z-index: 9999;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                animation: slideIn 0.3s ease;
            `;
            toast.innerHTML = `<i class="fas fa-shield-alt me-2"></i>${message}`;
            
            document.body.appendChild(toast);
            
            setTimeout(function() {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(function() {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Disable image saving (right-click on images)
        document.addEventListener('dragstart', function(e) {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
            }
        });

        // Answer Sheet Upload Functionality
        const MAX_FILES = 15;
        const video = document.getElementById('camera-preview');
        const canvas = document.getElementById('photo-canvas');
        const startBtn = document.getElementById('start-camera');
        const takePhotoBtn = document.getElementById('take-photo');
        const stopBtn = document.getElementById('stop-camera');
        const capturedPhotos = document.getElementById('captured-photos');
        const uploadForm = document.getElementById('answer-upload-form');
        const fileInput = document.getElementById('answer-files');
        const uploadStatus = document.getElementById('upload-status');
        const clearAllBtn = document.getElementById('clear-all');

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
            clearStatus();
        };

        // Start camera
        if (startBtn) {
            startBtn.addEventListener('click', async () => {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' }
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
        }

        // Take photo
        if (takePhotoBtn) {
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

                    const file = new File([blob], `answer_photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
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
        }

        // Stop camera
        if (stopBtn) {
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
        }

        // Clear all
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', () => {
                resetFormState();
                setStatus('info', 'All files cleared.');
            });
        }

        // Validate file selection
        if (fileInput) {
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
        }

        // Handle form submission
        if (uploadForm) {
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                clearStatus();

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

                let pdfBlob;
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
                        originalNames = [pdfFiles[0].name];
                    }

                    setStatus('info', 'Uploading answer sheet...');

                    const formData = new FormData();
                    formData.append('subject_id', '<?= $paper['subject_id'] ?>');
                    formData.append('paper_id', '<?= $paper['id'] ?>');
                    formData.append('original_names', originalNames.join(', '));

                    const pdfFileToSend = pdfBlob instanceof File ? pdfBlob : new File([pdfBlob], `answer_sheet_${Date.now()}.pdf`, { type: 'application/pdf' });
                    formData.append('pdf_file', pdfFileToSend);

                    const response = await fetch('../student/upload_answer.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        setStatus('success', result.message || 'Answer sheet uploaded successfully.');
                        resetFormState();
                        setTimeout(() => {
                            if (result.redirect) {
                                window.location.href = result.redirect;
                            }
                        }, 2000);
                    } else {
                        setStatus('danger', result.message || 'Upload failed. Please try again.');
                    }
                } catch (error) {
                    console.error(error);
                    setStatus('danger', 'Error processing your submission. Please try again.');
                }
            });
        }
    </script>
    </script>
</body>
</html>