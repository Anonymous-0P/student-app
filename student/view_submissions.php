<?php
include('../config/config.php');
include('../includes/header.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student'){
    header("Location: ../auth/login.php");
    exit;
}

// Get submissions with subject details
$stmt = $conn->prepare("SELECT s.*, sub.code as subject_code, sub.name as subject_name, 
                       CASE 
                           WHEN s.status = 'pending' THEN 'Pending Review'
                           WHEN s.status = 'evaluated' THEN 'Evaluated'
                           WHEN s.status = 'returned' THEN 'Returned'
                           ELSE 'Unknown'
                       END as status_display
                       FROM submissions s 
                       LEFT JOIN subjects sub ON s.subject_id = sub.id 
                       WHERE s.student_id=? 
                       ORDER BY s.created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="row">
    <div class="col-xl-11">
        <div class="page-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Your Submissions</h2>
                <a class="btn btn-sm btn-primary" href="upload.php">+ New Upload</a>
            </div>
            
            <?php if($result->num_rows == 0): ?>
                <div class="text-center py-5">
                    <div class="text-muted mb-3">📄</div>
                    <h5 class="text-muted">No submissions yet</h5>
                    <p class="text-muted">Upload your first answer sheet to get started</p>
                    <a class="btn btn-primary" href="upload.php">Upload Answers</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Marks</th>
                                <th>Submitted</th>
                                <th>Files</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php 
                            // Fix PDF URL for proper access
                            $pdfUrl = $row['pdf_url'];
                            if (strpos($pdfUrl, '/uploads/') === 0) {
                                $pdfUrl = '..' . $pdfUrl;
                            }
                            
                            // Status badge styling
                            $statusClass = 'bg-warning';
                            if($row['status'] == 'evaluated') $statusClass = 'bg-success';
                            if($row['status'] == 'returned') $statusClass = 'bg-info';
                            
                            // File size formatting
                            $fileSize = '';
                            if($row['file_size']) {
                                $fileSize = number_format($row['file_size'] / 1024, 1) . ' KB';
                            }
                            ?>
                            <tr>
                                <td>
                                    <?php if($row['subject_code']): ?>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['subject_code']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['subject_name']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">No subject</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?> text-white">
                                        <?= htmlspecialchars($row['status_display']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['marks'] !== null): ?>
                                        <div class="fw-bold text-success">
                                            <?= number_format((float)$row['marks'], 1) ?>
                                            <?php if($row['total_marks']): ?>
                                                / <?= number_format((float)$row['total_marks'], 1) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                                    <div class="small text-muted"><?= date('g:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td>
                                    <?php if($row['original_filename']): ?>
                                        <div class="small"><?= htmlspecialchars($row['original_filename']) ?></div>
                                    <?php endif; ?>
                                    <?php if($fileSize): ?>
                                        <div class="small text-muted"><?= $fileSize ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" 
                                           class="btn btn-sm btn-outline-primary" title="View PDF">
                                            📄 View
                                        </a>
                                        <?php if($row['evaluation_notes']): ?>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="showNotes('<?= htmlspecialchars(addslashes($row['evaluation_notes'])) ?>')"
                                                    title="View Notes">
                                                💬
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for evaluation notes -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Evaluation Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="notesContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showNotes(notes) {
    document.getElementById('notesContent').innerHTML = notes.replace(/\n/g, '<br>');
    new bootstrap.Modal(document.getElementById('notesModal')).show();
}
</script>

<?php include('../includes/footer.php'); ?>
