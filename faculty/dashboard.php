<?php
include('../config/config.php');
include('../includes/header.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty'){
    header("Location: ../auth/login.php");
    exit;
}

$result = $conn->query("SELECT submissions.id, submissions.pdf_url, submissions.created_at, users.name 
                        FROM submissions 
                        JOIN users ON submissions.student_id = users.id 
                        ORDER BY submissions.created_at DESC");
?>

<div class="row">
    <div class="col-xl-11">
        <div class="page-card">
            <h2 class="mb-3">Faculty Dashboard</h2>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student Name</th>
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
                            // Convert absolute path to relative path from faculty directory
                            $pdfUrl = '..' . $pdfUrl;
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Open</a></td>
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
