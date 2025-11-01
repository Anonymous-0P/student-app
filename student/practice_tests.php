<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('student');

$student_id = $_SESSION['user_id'];
$subject_id = (int)($_GET['subject_id'] ?? 0);

// Verify student has purchased this subject
$check_stmt = $conn->prepare("
    SELECT s.code, s.name
    FROM purchased_subjects ps
    JOIN subjects s ON ps.subject_id = s.id
    WHERE ps.student_id = ? AND ps.subject_id = ? AND ps.status = 'active'
");
$check_stmt->bind_param("ii", $student_id, $subject_id);
$check_stmt->execute();
$subject = $check_stmt->get_result()->fetch_assoc();

if (!$subject) {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = "Practice Tests - " . $subject['name'];
require_once('../includes/header.php');
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="text-center py-5">
                <i class="fas fa-tasks fa-4x text-primary mb-4"></i>
                <h2>Practice Tests</h2>
                <h4 class="text-muted mb-4"><?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['name']) ?></h4>
                <p class="lead text-muted mb-4">
                    This feature is currently under development. Practice tests and mock exams will be available here soon.
                </p>
                <div class="d-grid gap-2 d-md-block">
                    <a href="subject_details.php?id=<?= $subject_id ?>" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Subject
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>