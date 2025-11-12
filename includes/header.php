<?php
if(!isset($_SESSION)) session_start();

// Determine base URL based on current directory level
$currentPath = $_SERVER['REQUEST_URI'];
$pathParts = explode('/', trim($currentPath, '/'));
$baseUrl = '';

// If we're in a subdirectory (auth, student, faculty, admin, moderator, evaluator), go up one level
if (isset($pathParts[1]) && in_array($pathParts[1], ['auth', 'student', 'faculty', 'admin', 'moderator', 'evaluator'])) {
    $baseUrl = '../';
} elseif (strpos($currentPath, '/student-app/') !== false) {
    // If we're directly in the app root
    $baseUrl = './';
} else {
    $baseUrl = '/student-app/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThetaExams</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/style.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<?php $isIndexPage = (basename($_SERVER['PHP_SELF']) == 'index.php'); ?>
<style>
/* Conditional Navbar Styling */
<?php if ($isIndexPage): ?>
/* Original Gradient Navbar for Index Page */
.navbar-enhanced {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #8B5FBF 100%) !important;
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.navbar-enhanced::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, transparent 100%);
    pointer-events: none;
}

.navbar-enhanced::after {
    content: '';
    position: absolute;
    top: 0;
    right: -50%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transform: skewX(-25deg);
    animation: shimmer 3s infinite;
    pointer-events: none;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) skewX(-25deg); }
    100% { transform: translateX(200%) skewX(-25deg); }
}

.navbar-brand-enhanced {
    font-weight: 700 !important;
    font-size: 1.4rem !important;
    color: white !important;
    text-decoration: none !important;
    position: relative;
    transition: all 0.3s ease;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.navbar-brand-enhanced::before {
    content: '🎓';
    margin-right: 0.5rem;
    font-size: 1.2rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.navbar-brand-enhanced:hover {
    color: rgba(255, 255, 255, 0.9) !important;
    transform: translateY(-2px);
    text-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
}

.nav-link-enhanced {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500 !important;
    padding: 0.75rem 1rem !important;
    border-radius: 8px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    position: relative !important;
    margin: 0 0.25rem !important;
    text-decoration: none !important;
    backdrop-filter: blur(5px);
}

.nav-link-enhanced::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    opacity: 0;
    transition: all 0.3s ease;
    z-index: -1;
}

.nav-link-enhanced:hover {
    color: white !important;
    transform: translateY(-2px);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    text-decoration: none !important;
}

.nav-link-enhanced:hover::before {
    opacity: 1;
    transform: scale(1.05);
}

.nav-link-enhanced i {
    margin-right: 0.5rem !important;
    font-size: 1rem !important;
    color: rgba(255, 255, 255, 0.95) !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.3s ease !important;
}

.nav-link-enhanced:hover i {
    color: white !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4) !important;
    transform: scale(1.1) !important;
}

.navbar-toggler-enhanced {
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    border-radius: 8px !important;
    padding: 0.5rem !important;
    transition: all 0.3s ease !important;
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(5px) !important;
}

.navbar-toggler-enhanced:hover {
    border-color: rgba(255, 255, 255, 0.6) !important;
    background: rgba(255, 255, 255, 0.2) !important;
    transform: scale(1.05);
}

/* Show all nav items on index page */
.navbar-nav .nav-item {
    display: block !important;
}

.hamburger-btn {
    display: flex !important;
}

<?php else: ?>
/* Minimal Clean Navbar for Dashboard Pages */
.navbar-enhanced {
    background: white !important;
    border-bottom: 1px solid #e5e7eb !important;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
    padding: 1rem 0 !important;
}

.navbar-enhanced::before,
.navbar-enhanced::after {
    display: none !important;
}

.navbar-brand-enhanced {
    font-weight: 600 !important;
    color: #111827 !important;
    font-size: 1.25rem !important;
    text-decoration: none !important;
    transition: color 0.2s ease;
}

.navbar-brand-enhanced::before {
    content: '';
    margin-right: 0;
}

.navbar-brand-enhanced:hover {
    color: #111827 !important;
    transform: none;
    text-shadow: none;
}

.nav-link-enhanced {
    color: #6b7280 !important;
    font-weight: 500 !important;
    padding: 0.75rem 1rem !important;
    border-radius: 8px !important;
    transition: color 0.2s ease !important;
    position: relative !important;
    margin: 0 0.25rem !important;
    text-decoration: none !important;
}

.nav-link-enhanced::before {
    display: none !important;
}

.nav-link-enhanced:hover {
    color: #007bff !important;
    transform: none;
    text-shadow: none;
    text-decoration: none !important;
}

.nav-link-enhanced:hover::before {
    opacity: 0;
    transform: scale(1.05);
}

.nav-link-enhanced i {
    margin-right: 0.5rem !important;
    font-size: 1rem !important;
    color: inherit !important;
    transition: color 0.2s ease !important;
}

.nav-link-enhanced:hover i {
    color: inherit !important;
}

.navbar-toggler-enhanced {
    border: 1px solid #d1d5db !important;
    border-radius: 6px !important;
    padding: 0.5rem !important;
}

/* Hide all navbar items except specific ones based on role */
<?php
$currentPath = $_SERVER['REQUEST_URI'];
$isStudentPage = strpos($currentPath, '/student/') !== false;
$isAdminPage = strpos($currentPath, '/admin/') !== false;
$isModeratorPage = strpos($currentPath, '/moderator/') !== false;
$isEvaluatorPage = strpos($currentPath, '/evaluator/') !== false;
$isAuthPage = strpos($currentPath, '/auth/') !== false;
?>

<?php if ($isAuthPage): ?>
/* Show all navbar items on auth pages (login/signup) */
.navbar-nav .nav-item {
    display: block !important;
}
<?php elseif ($isStudentPage): ?>
/* Show dashboard, home, and logout for student pages */
.navbar-nav .nav-item {
    display: none !important;
}
.navbar-nav .nav-item:has(a[href*="student/dashboard.php"]),
.navbar-nav .nav-item:has(a[href*="index.php"]),
.navbar-nav .nav-item:has(a[href*="logout.php"]) {
    display: block !important;
}
<?php elseif ($isEvaluatorPage): ?>
/* Show dashboard and logout for evaluator pages */
.navbar-nav .nav-item {
    display: none !important;
}
.navbar-nav .nav-item:has(a[href*="dashboard.php"]),
.navbar-nav .nav-item:has(a[href*="assignments.php"]),
.navbar-nav .nav-item:has(a[href*="logout.php"]) {
    display: block !important;
}
<?php else: ?>
/* Show only dashboard link for other roles */
.navbar-nav .nav-item {
    display: none !important;
}
.navbar-nav .nav-item:has(a[href*="dashboard.php"]),
.navbar-nav .nav-item:has(a[href*="logout.php"]) {
    display: block !important;
}
<?php endif; ?>

/* Show hamburger menu button on mobile only */
.hamburger-btn {
    display: none !important;
}

@media (max-width: 991.98px) {
    .hamburger-btn {
        display: flex !important;
    }
}
<?php endif; ?>

/* Mobile responsive improvements */
@media (max-width: 991.98px) {
    .navbar-nav {
        background: transparent;
        padding: 0;
    }
    
    .nav-link-enhanced {
        margin: 0.25rem 0 !important;
        text-align: left;
    }
    
    /* Fixed header on mobile - stays at top when scrolling */
    header.navbar.navbar-enhanced {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 1050 !important;
        margin: 0 !important;
    }
    
    /* Compensate for fixed header */
    body {
        padding-top: 56px;
    }
    
    .dashboard-layout,
    .browse-exams-content {
        padding-top: 0;
    }
}
</style>

<header class="navbar navbar-expand-lg <?= $isIndexPage ? 'navbar-dark' : 'navbar-light' ?> navbar-enhanced shadow-sm">
    <div class="container">
    <?php 
    $isStudentPage = strpos($_SERVER['REQUEST_URI'], '/student/') !== false;
    $isCheckoutPage = strpos($_SERVER['REQUEST_URI'], 'checkout.php') !== false;
    $isPaymentSuccessPage = strpos($_SERVER['REQUEST_URI'], 'payment_success.php') !== false;
    ?>
    
    <!-- Mobile Menu Toggle for Student Pages -->
    <?php if ($isStudentPage && isset($_SESSION['role']) && $_SESSION['role'] == 'student' && !$isCheckoutPage && !$isPaymentSuccessPage): ?>
        <button class="btn btn-link d-lg-none" type="button" id="mobileMenuToggle" style="padding: 0.5rem; margin-right: 0.5rem; color: inherit; text-decoration: none;">
            <i class="fas fa-bars" style="font-size: 1.5rem;"></i>
        </button>
    <?php endif; ?>
    
    <a class="navbar-brand navbar-brand-enhanced" href="<?= $baseUrl ?>index.php">
        <?php if (!$isIndexPage): ?>
            <i class="fas fa-graduation-cap me-2"></i>
        <?php endif; ?>
        ThetaExams
    </a>
        <?php if (!$isPaymentSuccessPage): ?>
        <button class="navbar-toggler navbar-toggler-enhanced" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <?php endif; ?>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if(isset($_SESSION['role'])): ?>
                    <?php if($_SESSION['role'] == 'student'): ?>
                        <!-- Student navigation handled by sidebar -->
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'admin/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Admin Portal
                            </a>
                        </li>
                    <?php elseif($_SESSION['role'] == 'moderator'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'moderator/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>moderator/dashboard.php">
                                <i class="fas fa-user-tie"></i> Moderator Portal
                            </a>
                        </li>
                    <?php elseif($_SESSION['role'] == 'evaluator'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'evaluator/assignments') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>evaluator/assignments.php">
                                <i class="fas fa-tasks"></i> Evaluator Portal
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if($_SESSION['role'] != 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-enhanced" href="<?= $baseUrl ?>auth/logout.php" onclick="return confirm('Are you sure you want to logout?')">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-enhanced <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>" href="<?= $baseUrl ?>index.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'login') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>auth/login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'signup') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>auth/signup.php">
                            <i class="fas fa-user-plus"></i> Signup
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <?php 
            $currentUserRole = $_SESSION['role'] ?? '';
            $isPaymentSuccessPage = strpos($_SERVER['REQUEST_URI'], 'payment_success.php') !== false;
            if (!empty($currentUserRole) && $currentUserRole !== 'student' && !$isPaymentSuccessPage): 
            ?>
            <!-- <button class="hamburger-btn ms-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#roleMenuOffcanvas" aria-controls="roleMenuOffcanvas">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button> -->
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
// Enhanced navbar interactions
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.querySelector('.navbar-enhanced');
    let lastScrollTop = 0;
    
    // Scroll effects
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 50) {
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-scrolled');
        }
        
        // Optional: Hide/show navbar on scroll (disabled on mobile)
        if (window.innerWidth > 991.98) {
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                navbar.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                navbar.style.transform = 'translateY(0)';
            }
        } else {
            // Keep navbar visible on mobile
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = scrollTop;
    });
    
    // Add ripple effect to nav links
    const navLinks = document.querySelectorAll('.nav-link-enhanced');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
                z-index: 1;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php
// Get current user role for hamburger menu
$userRole = $_SESSION['role'] ?? '';
$userName = $_SESSION['name'] ?? '';
?>

<!-- Hamburger Menu -->
<?php if (!empty($userRole)): ?>

<!-- Offcanvas Menu -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="roleMenuOffcanvas" aria-labelledby="roleMenuOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="roleMenuOffcanvasLabel">
            <?php if ($userRole === 'student'): ?>
                <i class="fas fa-user-graduate text-primary"></i> Student Menu
            <?php elseif ($userRole === 'evaluator'): ?>
                <i class="fas fa-user-check text-primary"></i> Evaluator Menu
            <?php elseif ($userRole === 'moderator'): ?>
                <i class="fas fa-user-tie text-primary"></i> Moderator Menu
            <?php elseif ($userRole === 'admin'): ?>
                <i class="fas fa-user-shield text-primary"></i> Admin Menu
            <?php endif; ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <!-- Student Menu -->
        <?php if ($userRole === 'student'): ?>
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-tachometer-alt text-primary"></i> Dashboard
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>student/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>index.php">
                            <i class="fas fa-globe"></i> Home
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-shopping-cart text-success"></i> Exam Store
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>student/browse_exams.php">
                            <i class="fas fa-books"></i> Browse Exams
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>student/cart.php">
                            <i class="fas fa-shopping-cart"></i> My Cart
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-file-alt text-info"></i> Question Papers
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>student/question_papers.php">
                            <i class="fas fa-file-download"></i> Download Papers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>student/subjects.php">
                            <i class="fas fa-book"></i> Browse Subjects
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-upload text-info"></i> Submissions
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>student/upload.php">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Submission
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>student/view_submissions.php">
                            <i class="fas fa-history"></i> View Submissions
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Evaluator Menu -->
        <?php if ($userRole === 'evaluator'): ?>
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-tachometer-alt text-primary"></i> Dashboard
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>evaluator/dashboard_enhanced.php">
                            <i class="fas fa-home"></i> Dashboard Home
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-tasks text-warning"></i> Assignments
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>evaluator/assignments.php">
                            <i class="fas fa-clipboard-list"></i> View Assignments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>evaluator/pending_evaluations.php">
                            <i class="fas fa-clock"></i> Pending Evaluations
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-history text-success"></i> History
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>evaluator/evaluation_history.php">
                            <i class="fas fa-list-alt"></i> Evaluation History
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Moderator Menu -->
        <?php if ($userRole === 'moderator'): ?>
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-tachometer-alt text-primary"></i> Dashboard
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>moderator/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard Home
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-users text-warning"></i> Evaluator Management
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>moderator/assign_evaluator.php">
                            <i class="fas fa-user-plus"></i> Assign Evaluators
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-file-alt text-info"></i> Submissions
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>moderator/submissions.php">
                            <i class="fas fa-list"></i> All Submissions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>moderator/submissions.php?status=pending">
                            <i class="fas fa-clock"></i> Pending Submissions
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-chart-bar text-success"></i> Analytics
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>moderator/marks_overview.php">
                            <i class="fas fa-percentage"></i> Marks Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>moderator/reports.php">
                            <i class="fas fa-file-chart-column"></i> Reports
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Admin Menu -->
        <?php if ($userRole === 'admin'): ?>
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-tachometer-alt text-primary"></i> Dashboard
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/analytics.php">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-users text-warning"></i> User Management
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/manage_students.php">
                            <i class="fas fa-user-graduate"></i> Manage Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/manage_moderators.php">
                            <i class="fas fa-user-tie"></i> Manage Moderators
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/manage_evaluators.php">
                            <i class="fas fa-user-check"></i> Manage Evaluators
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-file-alt text-info"></i> Content Management
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/answer_sheets.php">
                            <i class="fas fa-file-document"></i> Answer Sheets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/subjects.php">
                            <i class="fas fa-book"></i> Subjects
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/manage_question_papers.php">
                            <i class="fas fa-file-text"></i> Question Papers
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <h6 class="nav-section-title">
                    <i class="fas fa-chart-bar text-success"></i> Reports
                </h6>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/reports.php">
                            <i class="fas fa-file-chart-column"></i> System Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>admin/export.php">
                            <i class="fas fa-download"></i> Export Data
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Common Account Section for All Roles -->
        <div class="nav-section">
            <h6 class="nav-section-title">
                <i class="fas fa-user text-secondary"></i> Account
            </h6>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-danger" href="<?= $baseUrl ?>auth/logout.php" onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<style>
/* Hamburger Menu Styles */
.hamburger-menu {
    /* Removed fixed positioning so it displays inline in navbar */
}

.hamburger-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
    cursor: pointer;
}

.hamburger-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.hamburger-line {
    width: 20px;
    height: 2px;
    background: white;
    margin: 2px 0;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.hamburger-btn:hover .hamburger-line {
    width: 24px;
}

/* Offcanvas Menu Styles */
.offcanvas {
    background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
    border-right: 1px solid #dee2e6;
    width: 300px !important;
}

.offcanvas-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.offcanvas-title {
    font-weight: 600;
}

.btn-close {
    filter: invert(1);
}

.nav-section {
    margin-bottom: 1.5rem;
}

.nav-section-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.nav-link {
    color: #495057 !important;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin: 0.25rem 0;
    transition: all 0.3s ease;
    text-decoration: none;
}

.nav-link:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
    transform: translateX(5px);
}

.nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
    font-weight: 600;
}

.nav-link i {
    width: 20px;
    margin-right: 10px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .offcanvas {
        width: 280px !important;
    }
}
</style>
<?php endif; ?>

<main class="flex-grow-1 py-2">
    <div class="container">
