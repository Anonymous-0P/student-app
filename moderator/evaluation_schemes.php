<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

checkLogin('moderator');

$pageTitle = "Evaluation Schemes";

// Get moderator's assigned subjects
$moderator_id = $_SESSION['user_id'];
$assigned_subjects_query = $conn->prepare("
    SELECT subject_id FROM moderator_subjects WHERE moderator_id = ?
");
$assigned_subjects_query->bind_param("i", $moderator_id);
$assigned_subjects_query->execute();
$assigned_result = $assigned_subjects_query->get_result();

$subject_ids = [];
while ($row = $assigned_result->fetch_assoc()) {
    $subject_ids[] = $row['subject_id'];
}

// Get evaluation schemes for assigned subjects
if (!empty($subject_ids)) {
    $placeholders = str_repeat('?,', count($subject_ids) - 1) . '?';
    $schemes_query = $conn->prepare("
        SELECT 
            es.*,
            s.name as subject_name,
            s.code as subject_code
        FROM evaluation_schemes es
        JOIN subjects s ON es.subject_id = s.id
        WHERE es.is_active = 1 AND es.subject_id IN ($placeholders)
        ORDER BY s.name, es.created_at DESC
    ");
    $schemes_query->bind_param(str_repeat('i', count($subject_ids)), ...$subject_ids);
    $schemes_query->execute();
    $schemes = $schemes_query->get_result();
} else {
    $schemes = null;
}

require_once('../includes/header.php');
?>

<style>
.scheme-card {
    transition: all 0.3s ease;
    border-left: 4px solid #667eea;
}

.scheme-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.file-icon {
    font-size: 2.5rem;
    color: #667eea;
}
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="mb-4">
        <h2><i class="fas fa-file-alt me-2"></i>Evaluation Schemes</h2>
        <p class="text-muted mb-0">View evaluation schemes for your assigned subjects</p>
    </div>

    <!-- Schemes Grid -->
    <div class="row g-3">
        <?php if ($schemes && $schemes->num_rows > 0): ?>
            <?php while ($scheme = $schemes->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card scheme-card h-100">
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="file-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                            </div>
                            
                            <h5 class="card-title mb-2 text-center"><?= htmlspecialchars($scheme['title']) ?></h5>
                            
                            <div class="text-center mb-3">
                                <span class="badge bg-primary"><?= htmlspecialchars($scheme['subject_code']) ?></span>
                                <div class="text-muted small mt-1"><?= htmlspecialchars($scheme['subject_name']) ?></div>
                            </div>
                            
                            <?php if ($scheme['description']): ?>
                                <p class="card-text text-muted small mb-3">
                                    <?= htmlspecialchars($scheme['description']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="small text-muted mb-3">
                                <div><i class="fas fa-calendar me-2"></i>Uploaded: <?= date('M d, Y', strtotime($scheme['created_at'])) ?></div>
                                <div><i class="fas fa-file me-2"></i>Size: <?= number_format($scheme['file_size'] / 1024, 2) ?> KB</div>
                                <div><i class="fas fa-download me-2"></i><?= htmlspecialchars($scheme['original_filename']) ?></div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?= htmlspecialchars($scheme['file_path']) ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-eye me-2"></i>View Scheme
                                </a>
                                <a href="<?= htmlspecialchars($scheme['file_path']) ?>" download class="btn btn-outline-success">
                                    <i class="fas fa-download me-2"></i>Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?= empty($subject_ids) ? 'No subjects assigned to you yet.' : 'No evaluation schemes available for your assigned subjects.' ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>
