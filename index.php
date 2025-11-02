<?php 
// Start session before output
session_start(); 

// Include the header file
include('includes/header.php'); 
?>

<!-- Close the Bootstrap container for full-width design -->
</div><!-- /.container -->
</main>

<style>
    /* Override Bootstrap defaults for landing page */
    body.bg-light {
        background: linear-gradient(135deg, #8B5FBF 0%, #B8A4D9 35%, #F4C2A1 70%, #F4A6CD 100%) !important;
        color: #333;
        overflow-x: hidden;
    }

    /* Reset margins and ensure full coverage */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* Ensure full height coverage */
    html, body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
    }

    /* Hero Section */
    .hero {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 40px 20px;
        position: relative;
        overflow: hidden;
    }

    .hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3), transparent 50%),
            radial-gradient(circle at 80% 80%, rgba(74, 144, 226, 0.3), transparent 50%);
        pointer-events: none;
    }

    .hero-content {
        max-width: 1000px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
        animation: fadeInUp 0.8s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .hero h1 {
        color: #ffffff;
        font-size: 3.5em;
        margin-bottom: 24px;
        font-weight: 700;
        line-height: 1.2;
        text-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
    }

    .hero .highlight {
        background: linear-gradient(135deg, #F4C2A1 0%, #F4A6CD 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .hero p {
        color: #e2e8f0;
        font-size: 1.4em;
        line-height: 1.8;
        margin-bottom: 40px;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
    }

    .cta-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 40px;
        width: 100%;
    }

    /* Center single button on mobile */
    @media (max-width: 600px) {
        .cta-buttons {
            flex-direction: column;
            align-items: center;
            gap: 0;
        }
        .cta-buttons .btn {
            width: 90vw;
            max-width: 320px;
            margin-left: auto;
            margin-right: auto;
            display: block;
        }
    }

    .btn {
        display: inline-block;
        padding: 18px 48px;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        font-size: 1.1em;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .btn-primary {
        background: linear-gradient(135deg, #8B5FBF 0%, #B8A4D9 100%);
        color: white;
        box-shadow: 0 8px 20px rgba(139, 95, 191, 0.4);
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(139, 95, 191, 0.6);
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(10px);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-3px);
    }

    /* Features Section */
    .features {
        padding: 100px 20px;
        background: rgba(255, 255, 255, 0.98);
        position: relative;
    }

    .features-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .section-title {
        text-align: center;
        font-size: 2.5em;
        color: #1a1a2e;
        margin-bottom: 20px;
        font-weight: 700;
    }

    .section-subtitle {
        text-align: center;
        font-size: 1.2em;
        color: #4a5568;
        margin-bottom: 60px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 40px;
        margin-top: 60px;
    }

    .feature-card {
        background: white;
        padding: 40px 30px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        text-align: center;
        border: 1px solid rgba(139, 95, 191, 0.1);
    }

    .feature-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 40px rgba(139, 95, 191, 0.2);
        border-color: rgba(139, 95, 191, 0.3);
    }

    .feature-icon {
        font-size: 3.5em;
        margin-bottom: 20px;
        filter: drop-shadow(0 4px 8px rgba(139, 95, 191, 0.2));
    }

    .feature-card h3 {
        color: #1a1a2e;
        font-size: 1.5em;
        margin-bottom: 15px;
        font-weight: 600;
    }

    .feature-card p {
        color: #4a5568;
        line-height: 1.7;
        font-size: 1em;
    }

    /* How It Works Section */
    .how-it-works {
        padding: 100px 20px;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    }

    .steps {
        max-width: 1000px;
        margin: 60px auto 0;
        display: grid;
        gap: 30px;
    }

    .step {
        display: flex;
        align-items: center;
        gap: 30px;
        background: white;
        padding: 30px;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .step:hover {
        transform: translateX(10px);
        box-shadow: 0 8px 25px rgba(139, 95, 191, 0.25);
    }

    .step-number {
        min-width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #8B5FBF 0%, #B8A4D9 100%);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8em;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(139, 95, 191, 0.4);
    }

    .step-content h3 {
        color: #1a1a2e;
        font-size: 1.4em;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .step-content p {
        color: #4a5568;
        line-height: 1.7;
    }

    /* CTA Section */
    .cta-section {
        padding: 100px 20px;
        background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .cta-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 30% 50%, rgba(120, 119, 198, 0.2), transparent 50%),
            radial-gradient(circle at 70% 50%, rgba(74, 144, 226, 0.2), transparent 50%);
        pointer-events: none;
    }

    .cta-content {
        max-width: 800px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }

    .cta-section h2 {
        color: white;
        font-size: 2.8em;
        margin-bottom: 24px;
        font-weight: 700;
    }

    .cta-section p {
        color: #e2e8f0;
        font-size: 1.3em;
        margin-bottom: 40px;
        line-height: 1.7;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .hero h1 {
            font-size: 2.5em;
        }

        .hero p {
            font-size: 1.1em;
        }

        .cta-buttons {
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            padding: 16px 40px;
            width: 100%;
            max-width: 300px;
        }

        .section-title {
            font-size: 2em;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }

        .step {
            flex-direction: column;
            text-align: center;
        }

        .step:hover {
            transform: translateY(-5px);
        }

        .cta-section h2 {
            font-size: 2em;
        }
    }
</style>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1>Welcome to <span class="highlight">ThetaExams</span></h1>
        <p>Browse and purchase access to premium exam materials. Take tests, submit answer sheets digitally, and track your academic progress with ease.</p>
        
        <?php if(!isset($_SESSION['user_id'])): ?>
            <div class="cta-buttons">
                <a href="student/browse_exams.php" class="btn btn-primary">Browse Exams</a>
            </div>
        <?php else: ?>
            <div class="cta-buttons">
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'student'): ?>
                    <div style="width:100%;display:flex;justify-content:center;">
                        <a href="student/dashboard.php" class="btn btn-primary">📚 Go to Dashboard</a>
                    </div>
                <?php elseif($_SESSION['role'] == 'admin'): ?>
                    <a href="admin/dashboard.php" class="btn btn-primary">🛠️ Admin Panel</a>
                <?php elseif($_SESSION['role'] == 'moderator'): ?>
                    <a href="moderator/dashboard.php" class="btn btn-primary">👨‍💼 Moderator Panel</a>
                <?php elseif($_SESSION['role'] == 'evaluator'): ?>
                    <a href="evaluator/dashboard.php" class="btn btn-primary">👨‍🏫 Evaluator Panel</a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-primary">🔐 Complete Login</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Features Section -->
<section class="features">
    <div class="features-container">
        <h2 class="section-title">Perfect for Students</h2>
        <p class="section-subtitle">Experience the easiest way to submit assignments and track your academic progress digitally</p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📸</div>
                <h3>Easy Upload</h3>
                <p>Students can quickly capture and upload answer sheets using their mobile devices or cameras.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📄</div>
                <h3>Auto PDF Conversion</h3>
                <p>Images are automatically converted to high-quality PDFs for standardized viewing and storage.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">👁️</div>
                <h3>Track Progress</h3>
                <p>Monitor your submission status, view grades, and receive feedback from instructors in real-time.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure & Private</h3>
                <p>All submissions are encrypted and stored securely with role-based access control.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Lightning Fast</h3>
                <p>Optimized processing ensures quick uploads and conversions without any delays.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Mobile Friendly</h3>
                <p>Fully responsive design works seamlessly on smartphones, tablets, and desktops.</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="how-it-works">
    <div class="features-container">
        <h2 class="section-title">How It Works</h2>
        <p class="section-subtitle">Get started in three simple steps</p>
        
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Browse Exam Catalog</h3>
                    <p>Explore our catalog of exams and subjects. Preview details and pricing before making a selection.</p>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Purchase & Access</h3>
                    <p>Select exams, add them to your cart, and complete the purchase process to gain access to exam materials.</p>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Study & Submit</h3>
                    <p>Access your purchased materials from the dashboard and submit your work when ready for evaluation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="cta-content">
        <h2>Ready to Start Learning?</h2>
        <p>Join thousands of students who are accessing premium exam materials and enhancing their academic performance with our platform.</p>
        <?php if(!isset($_SESSION['user_id'])): ?>
            <div class="cta-buttons">
                <a href="student/browse_exams.php" class="btn btn-primary">Browse Exams</a>
            </div>
        <?php else: ?>
            <div class="cta-buttons">
                <?php if($_SESSION['role'] == 'student'): ?>
                    <a href="student/dashboard.php" class="btn btn-primary">Upload Your First Assignment</a>
                <?php else: ?>
                    <a href="admin/dashboard.php" class="btn btn-primary">Access Admin Panel</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include('includes/footer.php'); ?>
