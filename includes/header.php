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
<style>
/* Enhanced Navbar Styling */
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

/* Enhanced icon styling for better visibility */
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

.nav-link-enhanced.active i {
    color: white !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5) !important;
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

.navbar-toggler-enhanced:focus {
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.3) !important;
}

/* Scroll effect */
.navbar-scrolled {
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4) !important;
    backdrop-filter: blur(15px) !important;
}

/* Mobile responsive improvements */
@media (max-width: 991.98px) {
    .navbar-nav {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
        backdrop-filter: blur(10px);
    }
    
    .nav-link-enhanced {
        margin: 0.25rem 0 !important;
        text-align: center;
    }
}

/* Active state for current page */
.nav-link-enhanced.active {
    background: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    font-weight: 600 !important;
}

/* Floating effect on scroll */
.navbar-floating {
    position: fixed !important;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    width: 95%;
    max-width: 1200px;
    border-radius: 16px;
    z-index: 1030;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.4);
}

@media (max-width: 768px) {
    .navbar-floating {
        width: 98%;
        top: 5px;
        border-radius: 12px;
    }
}
</style>

<header class="navbar navbar-expand-lg navbar-dark navbar-enhanced shadow-sm">
    <div class="container">
    <a class="navbar-brand navbar-brand-enhanced" href="<?= $baseUrl ?>index.php">ThetaExams</a>
        <button class="navbar-toggler navbar-toggler-enhanced" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-enhanced <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>" href="<?= $baseUrl ?>index.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <?php if(isset($_SESSION['role'])): ?>
                    <?php if($_SESSION['role'] == 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'student/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>student/dashboard.php">
                                <i class="fas fa-chart-pie"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'student/browse_exams') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>student/browse_exams.php">
                                <i class="fas fa-books"></i> Browse Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'student/cart') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>student/cart.php">
                                <i class="fas fa-shopping-cart"></i> Cart
                            </a>
                        </li>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'admin/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Admin Portal
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'admin/subjects') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/subjects.php">
                                <i class="fas fa-book"></i> Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'admin/analytics') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/analytics.php">
                                <i class="fas fa-chart-line"></i> Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'admin/reports') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                    <?php elseif($_SESSION['role'] == 'moderator'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'moderator/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>moderator/dashboard.php">
                                <i class="fas fa-user-tie"></i> Moderator Panel
                            </a>
                        </li>
                    <?php elseif($_SESSION['role'] == 'evaluator'): ?>
                        <!-- <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'evaluator/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>evaluator/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> 
                            </a>
                        </li> -->
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'evaluator/assignments') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>evaluator/assignments.php">
                                <i class="fas fa-tasks"></i> Dashboard
                            </a>
                        </li>
                        <!-- <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'evaluator/pending_evaluations') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>evaluator/pending_evaluations.php">
                                <i class="fas fa-clock"></i> Evaluations
                            </a>
                        </li> -->
                        <!-- <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'evaluator/evaluation_history') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>evaluator/evaluation_history.php">
                                <i class="fas fa-history"></i> History
                            </a>
                        </li> -->
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-enhanced" href="<?= $baseUrl ?>auth/logout.php" onclick="return confirm('Are you sure you want to logout?')">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
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
            <?php if (!empty($userRole)): ?>
            <button class="hamburger-btn ms-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#roleMenuOffcanvas" aria-controls="roleMenuOffcanvas">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
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
        
        // Optional: Hide/show navbar on scroll
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scrolling down
            navbar.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
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

<main class="flex-grow-1 py-4">
    <div class="container">
