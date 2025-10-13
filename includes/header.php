<?php
if(!isset($_SESSION)) session_start();

// Determine base URL based on current directory level
$currentPath = $_SERVER['REQUEST_URI'];
$pathParts = explode('/', trim($currentPath, '/'));
$baseUrl = '';

// If we're in a subdirectory (auth, student, faculty), go up one level
if (isset($pathParts[1]) && in_array($pathParts[1], ['auth', 'student', 'faculty'])) {
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
    <title>Student Photo App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
        <a class="navbar-brand navbar-brand-enhanced" href="<?= $baseUrl ?>index.php">Student Photo App</a>
        <button class="navbar-toggler navbar-toggler-enhanced" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-enhanced <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>" href="<?= $baseUrl ?>index.php">
                        🏠 Home
                    </a>
                </li>
                <?php if(isset($_SESSION['role'])): ?>
                    <?php if($_SESSION['role'] == 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'student/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>student/dashboard.php">
                                📊 Dashboard
                            </a>
                        </li>
                    <?php elseif($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'admin/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/dashboard.php">
                                🛠️ Admin Portal
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'admin/manage_users') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>admin/manage_users.php">
                                👥 Users
                            </a>
                        </li>
                    <?php elseif($_SESSION['role'] == 'moderator'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'moderator/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>moderator/dashboard.php">
                                👨‍💼 Moderator Panel
                            </a>
                        </li>
                    <?php elseif($_SESSION['role'] == 'evaluator'): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'evaluator/dashboard') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>evaluator/dashboard.php">
                                👨‍🏫 Evaluator Panel
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-enhanced" href="<?= $baseUrl ?>auth/logout.php" onclick="return confirm('Are you sure you want to logout?')">
                            🚪 Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'login') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>auth/login.php">
                            🔑 Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-enhanced <?= (strpos($_SERVER['REQUEST_URI'], 'signup') !== false) ? 'active' : '' ?>" href="<?= $baseUrl ?>auth/signup.php">
                            📝 Signup
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
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
<main class="flex-grow-1 py-4">
    <div class="container">
