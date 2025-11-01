<?php
include('../config/config.php');
include('../includes/header.php');
require_once('../includes/functions.php');
checkLogin('student');

// Fetch active subjects
$year = $_SESSION['year'] ?? null;
$department = $_SESSION['department'] ?? null;

$query = "SELECT s.* FROM subjects s WHERE s.is_active=1";
$params = [];
$types = '';
if ($year) { $query .= " AND (s.year IS NULL OR s.year=?)"; $types .= 'i'; $params[] = $year; }
if ($department) { $query .= " AND (s.department IS NULL OR s.department=?)"; $types .= 's'; $params[] = $department; }
$query .= " ORDER BY s.code";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<div class="row">
    <div class="col-lg-9 col-xl-8">
        <div class="page-card">
            <h2 class="mb-3">Select Subject</h2>
            <p class="text-muted">Choose a subject to upload your answer sheet for evaluation.</p>
            <div class="list-group">
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($row['code'].' - '.$row['name']); ?></div>
                            <div class="small text-muted">Dept: <?php echo htmlspecialchars($row['department'] ?? 'Any'); ?> • Year: <?php echo htmlspecialchars($row['year'] ?? 'Any'); ?></div>
                        </div>
                        <div>
                            <a class="btn btn-primary" href="question_paper.php?subject_id=<?php echo (int)$row['id']; ?>">Upload Answer Sheet</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
