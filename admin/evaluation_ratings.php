<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Check if evaluator_ratings table exists
$table_check = $conn->query("SHOW TABLES LIKE 'evaluator_ratings'");
$table_exists = $table_check && $table_check->num_rows > 0;

// Initialize variables
$ratings = [];
$stats = [
    'total_ratings' => 0,
    'average_rating' => 0,
    'excellent_count' => 0,
    'good_count' => 0,
    'average_count' => 0,
    'poor_count' => 0,
    'feedback_count' => 0
];

if ($table_exists) {
    // Get all ratings with evaluator details
    $ratings_query = "
        SELECT 
            er.*,
            u.name as evaluator_name,
            u.email as evaluator_email,
            s.name as student_name,
            er.comments as feedback
        FROM evaluator_ratings er
        LEFT JOIN users u ON er.evaluator_id = u.id
        LEFT JOIN users s ON er.student_id = s.id
        ORDER BY er.created_at DESC
    ";
    
    $ratings_result = $conn->query($ratings_query);
    if ($ratings_result) {
        $ratings = $ratings_result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_ratings,
            AVG(overall_rating) as average_rating,
            SUM(CASE WHEN overall_rating >= 4 THEN 1 ELSE 0 END) as excellent_count,
            SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as good_count,
            SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as average_count,
            SUM(CASE WHEN overall_rating <= 1 THEN 1 ELSE 0 END) as poor_count,
            SUM(CASE WHEN comments IS NOT NULL AND comments != '' THEN 1 ELSE 0 END) as feedback_count
        FROM evaluator_ratings
    ";
    
    $stats_result = $conn->query($stats_query);
    if ($stats_result) {
        $stats = $stats_result->fetch_assoc();
        $stats['average_rating'] = round($stats['average_rating'], 2);
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Ratings - Student Evaluation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-light: #0056b3;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success: #28a745;
            --warning: #ffc107;
            --error: #dc3545;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        body {
            background-color: var(--gray-50);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--gray-800);
            line-height: 1.6;
        }

        .navbar {
            background: white !important;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--gray-900) !important;
            font-size: 1.25rem;
        }

        .nav-link {
            color: var(--gray-600) !important;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .content-wrapper {
            padding: 2.5rem 0;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            color: var(--gray-900);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .page-subtitle {
            color: var(--gray-500);
            font-size: 1.125rem;
            margin-bottom: 3rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            text-align: center;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
            transition: all 0.3s ease;
            border: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,123,255,0.4);
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-card-title {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .ratings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
            gap: 1.5rem;
        }

        .rating-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            margin-bottom: 1rem;
        }

        .rating-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .rating-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .evaluator-info {
            flex: 1;
        }

        .evaluator-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .student-name {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .overall-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .rating-excellent {
            background-color: #d4edda;
            color: var(--success);
        }

        .rating-good {
            background-color: #cce7ff;
            color: var(--primary-color);
        }

        .rating-average {
            background-color: #fff3cd;
            color: #856404;
        }

        .rating-poor {
            background-color: #f8d7da;
            color: var(--error);
        }

        .rating-details {
            border-top: 1px solid var(--gray-100);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .rating-categories {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .rating-category {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-name {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .rating-stars {
            display: flex;
            gap: 0.125rem;
        }

        .star {
            width: 1rem;
            height: 1rem;
            color: #fbbf24;
        }

        .star-empty {
            color: var(--gray-300);
        }

        .feedback-section {
            margin-top: 1rem;
            padding: 1rem;
            background-color: var(--gray-50);
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        .feedback-label {
            color: var(--gray-600);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .feedback-text {
            color: var(--gray-700);
            font-size: 0.875rem;
            line-height: 1.5;
            font-style: italic;
        }

        .rating-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-400);
            font-size: 0.75rem;
            margin-top: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }

        .empty-state-icon {
            width: 4rem;
            height: 4rem;
            color: var(--gray-300);
            margin: 0 auto 1rem;
        }

        .empty-state-title {
            color: var(--gray-900);
            font-weight: 600;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .empty-state-text {
            color: var(--gray-500);
            font-size: 1rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-top: 2rem;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }

        .back-button:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1.5rem 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .ratings-grid {
                grid-template-columns: 1fr;
            }
            
            .rating-categories {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <a class="nav-link" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container content-wrapper">
        <div class="text-center mb-5">
            <h1 class="page-title">Evaluation Ratings</h1>
            <p class="page-subtitle">Performance insights and feedback from students</p>
        </div>

        <?php if ($table_exists): ?>
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-value"><?php echo $stats['total_ratings']; ?></div>
                    <div class="stat-card-title">Total Reviews</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value"><?php echo $stats['average_rating']; ?><span style="font-size: 1rem; opacity: 0.8;">/5</span></div>
                    <div class="stat-card-title">Average Rating</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value"><?php echo $stats['excellent_count']; ?></div>
                    <div class="stat-card-title">Excellent Ratings</div>
                </div>
            </div>

            <!-- Ratings List -->
            <?php if (!empty($ratings)): ?>
                <div class="ratings-grid">
                    <?php foreach ($ratings as $rating): ?>
                        <div class="rating-card">
                            <div class="rating-card-header">
                                <div class="evaluator-info">
                                    <div class="evaluator-name">
                                        <?php echo htmlspecialchars($rating['evaluator_name'] ?? 'Unknown Evaluator'); ?>
                                    </div>
                                    <div class="student-name">
                                        Student: <?php echo htmlspecialchars($rating['student_name'] ?? 'Unknown Student'); ?>
                                    </div>
                                </div>
                                <div class="overall-rating <?php 
                                    $overall_rating = $rating['overall_rating'];
                                    if ($overall_rating >= 4) echo 'rating-excellent';
                                    elseif ($overall_rating >= 3) echo 'rating-good';
                                    elseif ($overall_rating >= 2) echo 'rating-average';
                                    else echo 'rating-poor';
                                ?>">
                                    <?php echo $overall_rating; ?>/5
                                </div>
                            </div>

                            <div class="rating-details">
                                <!-- Rating Categories -->
                                <div class="rating-categories">
                                    <div class="rating-category">
                                        <span class="category-name">Overall Rating</span>
                                        <div class="rating-stars">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                $class = $i <= $rating['overall_rating'] ? 'star' : 'star star-empty';
                                                echo '<i class="fas fa-star ' . $class . '"></i>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="rating-category">
                                        <span class="category-name">Evaluation Quality</span>
                                        <span class="badge <?php 
                                            $quality = $rating['evaluation_quality'];
                                            if ($quality == 'excellent') echo 'rating-excellent';
                                            elseif ($quality == 'good') echo 'rating-good';
                                            elseif ($quality == 'average') echo 'rating-average';
                                            else echo 'rating-poor';
                                        ?>"><?php echo ucfirst($quality); ?></span>
                                    </div>
                                    <div class="rating-category">
                                        <span class="category-name">Feedback Helpfulness</span>
                                        <span class="badge <?php 
                                            $helpfulness = $rating['feedback_helpfulness'];
                                            if ($helpfulness == 'very_helpful') echo 'rating-excellent';
                                            elseif ($helpfulness == 'helpful') echo 'rating-good';
                                            elseif ($helpfulness == 'somewhat_helpful') echo 'rating-average';
                                            else echo 'rating-poor';
                                        ?>"><?php echo str_replace('_', ' ', ucfirst($helpfulness)); ?></span>
                                    </div>
                                </div>

                                <!-- Feedback -->
                                <?php if (!empty($rating['feedback'])): ?>
                                    <div class="feedback-section">
                                        <div class="feedback-label">Student Feedback</div>
                                        <div class="feedback-text"><?php echo htmlspecialchars($rating['feedback']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <!-- Date -->
                                <div class="rating-date">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M d, Y', strtotime($rating['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-star empty-state-icon"></i>
                    <h3 class="empty-state-title">No Ratings Yet</h3>
                    <p class="empty-state-text">Evaluation ratings will appear here once students start rating evaluators.</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-database empty-state-icon"></i>
                <h3 class="empty-state-title">Rating System Not Available</h3>
                <p class="empty-state-text">The evaluator ratings table has not been created yet. Please contact your system administrator to set up the rating system.</p>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
       
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>