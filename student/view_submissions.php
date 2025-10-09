<?php
include('../config/config.php');
include('../includes/header.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student'){
    header("Location: ../auth/login.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM submissions WHERE student_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="row">
    <div class="col-xl-10">
        <div class="page-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Your Submissions</h2>
                <a class="btn btn-sm btn-primary" href="upload.php">+ New Upload</a>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>PDF</th>
                            <th>Uploaded At</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <?php 
                        // Fix PDF URL for proper access
                        $pdfUrl = $row['pdf_url'];
                        if (strpos($pdfUrl, '/uploads/') === 0) {
                            // Convert absolute path to relative path from student directory
                            $pdfUrl = '..' . $pdfUrl;
                        }
                        ?>
                        <tr>
                            <td><a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" class="link-primary">View PDF</a></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
