
<style>
/* Sidebar Styles */
.dashboard-layout {
    display: flex;
    min-height: calc(100vh - 80px);
}

.sidebar {
    width: 280px;
    background: white;
    border-right: 1px solid #e5e7eb;
    position: fixed;
    left: 0;
    top: 80px;
    bottom: 0;
    overflow-y: auto;
    z-index: 1000;
    transition: transform 0.3s ease;
}

.sidebar-header {
    padding: 1.5rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
}

.sidebar-brand .icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.sidebar-menu {
    padding: 1rem 0;
}

.sidebar-section {
    margin-bottom: 1.5rem;
}

.sidebar-section-title {
    padding: 0.5rem 1.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sidebar-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.25rem;
    color: #4b5563;
    text-decoration: none;
    transition: all 0.2s;
    position: relative;
}

.sidebar-item:hover {
    background: #f3f4f6;
    color: #111827;
}

.sidebar-item.active {
    background: #eff6ff;
    color: #2563eb;
    border-right: 3px solid #2563eb;
}

.sidebar-item .icon {
    width: 20px;
    margin-right: 0.75rem;
    text-align: center;
}

.sidebar-item .badge {
    margin-left: auto;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.main-content {
    margin-left: 80px;
    margin-right: -80px;
    flex: 1;
    padding: 1rem 0;
    width: calc(100% - 80px);
}

.sidebar-toggle {
    display: none;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        top: 0;
        box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: auto;
        margin-right: auto;
        width: calc(100% - 20px);
        max-width: 100vw;
        padding: 0.5rem 12px !important;
        overflow-x: hidden;
        box-sizing: border-box;
    }
    
    /* Ensure all child elements respect parent width */
    .main-content * {
        max-width: 100%;
        box-sizing: border-box;
    }
    
    /* Fix Bootstrap row overflow */
    .main-content .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    .main-content [class*="col-"] {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    /* Hide navbar hamburger menu on mobile for student pages */
    .navbar .navbar-toggler {
        display: none !important;
    }
    
    /* Hide navbar items on mobile, sidebar will be used instead */
    .navbar-collapse {
        display: none !important;
    }
}
</style>

<?php
// Get current page name for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Get cart count
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $cart_stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE student_id = ?");
    $cart_stmt->bind_param("i", $_SESSION['user_id']);
    $cart_stmt->execute();
    $cart_count = $cart_stmt->get_result()->fetch_assoc()['count'];
}
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <span>Student Portal</span>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main</div>
            <a href="dashboard.php" class="sidebar-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home icon"></i>
                <span>Dashboard</span>
            </a>
            <a href="browse_exams.php" class="sidebar-item <?= $currentPage == 'browse_exams.php' ? 'active' : '' ?>">
                <i class="fas fa-book-open icon"></i>
                <span>Browse Exams</span>
            </a>
            <a href="question_papers.php" class="sidebar-item <?= $currentPage == 'question_papers.php' ? 'active' : '' ?>">
                <i class="fas fa-file-download icon"></i>
                <span>Question Papers</span>
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Submissions</div>
            <a href="upload.php" class="sidebar-item <?= $currentPage == 'upload.php' ? 'active' : '' ?>">
                <i class="fas fa-cloud-upload-alt icon"></i>
                <span>Upload Answer</span>
            </a>
            <a href="view_submissions.php" class="sidebar-item <?= $currentPage == 'view_submissions.php' ? 'active' : '' ?>">
                <i class="fas fa-file-alt icon"></i>
                <span>My Submissions</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Shopping</div>
            <a href="cart.php" class="sidebar-item <?= $currentPage == 'cart.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart icon"></i>
                <span>Cart</span>
                <?php if ($cart_count > 0): ?>
                <span class="badge bg-primary"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
            <a href="checkout.php" class="sidebar-item <?= $currentPage == 'checkout.php' ? 'active' : '' ?>">
                <i class="fas fa-credit-card icon"></i>
                <span>Checkout</span>
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="../index.php" class="sidebar-item">
                <i class="fas fa-home icon"></i>
                <span>Home</span>
            </a>
            <a href="../auth/logout.php" class="sidebar-item" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt icon"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Toggle Button for Mobile -->
<button class="sidebar-toggle" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<script>
// Sidebar Toggle for Mobile
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Mobile menu toggle from header
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        });
    }

    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }
    
    // Close sidebar when clicking a link on mobile
    const sidebarLinks = document.querySelectorAll('.sidebar-item');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 991.98) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        });
    });
});
</script>
