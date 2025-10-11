<?php
include('../config/config.php');
include('../includes/header.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty'){
    header("Location: ../auth/login.php");
    exit;
}

// Get submission statistics
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'evaluated' THEN 1 ELSE 0 END) as evaluated,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned
    FROM submissions")->fetch_assoc();

// Get recent submissions with subject info
$result = $conn->query("SELECT s.id, s.pdf_url, s.created_at, s.status, u.name, u.roll_no,
                       sub.code as subject_code, sub.name as subject_name
                       FROM submissions s
                       JOIN users u ON s.student_id = u.id 
                       LEFT JOIN subjects sub ON s.subject_id = sub.id
                       ORDER BY s.created_at DESC 
                       LIMIT 10");
?>

<div class="row">
    <div class="col-12">
        <div class="page-card">
            <h2 class="mb-4">Faculty Dashboard</h2>
            
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <div class="fw-bold text-primary fs-3"><?= $stats['total'] ?></div>
                            <div class="text-muted">Total Submissions</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <div class="fw-bold text-warning fs-3"><?= $stats['pending'] ?></div>
                            <div class="text-muted">Pending Review</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <div class="fw-bold text-success fs-3"><?= $stats['evaluated'] ?></div>
                            <div class="text-muted">Evaluated</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <div class="fw-bold text-info fs-3"><?= $stats['returned'] ?></div>
                            <div class="text-muted">Returned</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="d-flex gap-2 mb-4">
                <a href="evaluate_submissions.php" class="btn btn-primary">
                    📝 Evaluate Submissions
                </a>
                <a href="evaluate_submissions.php?status=pending" class="btn btn-warning">
                    ⏳ View Pending (<?= $stats['pending'] ?>)
                </a>
                <a href="view_submission.php" class="btn btn-outline-secondary">
                    📄 View All Submissions
                </a>
            </div>

            <!-- Recent Submissions -->
            <h5 class="mb-3">Recent Submissions</h5>
            <?php if($result->num_rows == 0): ?>
                <div class="text-center py-4">
                    <div class="text-muted mb-3">📄</div>
                    <h6 class="text-muted">No submissions yet</h6>
                    <p class="text-muted">Student submissions will appear here</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php 
                            $pdfUrl = $row['pdf_url'];
                            if (strpos($pdfUrl, '/uploads/') === 0) {
                                $pdfUrl = '..' . $pdfUrl;
                            }
                            
                            $statusClass = 'bg-warning';
                            if($row['status'] == 'evaluated') $statusClass = 'bg-success';
                            if($row['status'] == 'returned') $statusClass = 'bg-info';
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                                    <?php if($row['roll_no']): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($row['roll_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['subject_code']): ?>
                                        <div><?= htmlspecialchars($row['subject_code']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['subject_name']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">No subject</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?> text-white">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                                    <div class="small text-muted"><?= date('g:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" 
                                           class="btn btn-sm btn-outline-primary" title="View PDF">
                                            📄
                                        </a>
                                        <a href="evaluate_submissions.php#submission_<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-outline-success" title="Evaluate">
                                            ✏️
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-3">
                    <a href="evaluate_submissions.php" class="btn btn-outline-primary">View All Submissions</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
