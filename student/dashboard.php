<?php
include('../config/config.php');
include('../includes/header.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student'){
    header("Location: ../auth/login.php");
    exit;
}
?>
<div class="row">
    <div class="col-lg-8 col-xl-7">
        <div class="page-card">
            <h2 class="mb-3">Student Dashboard</h2>
            <p class="text-muted mb-4">Upload your answer sheets as images which will be converted to a PDF.</p>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <a class="btn btn-primary" href="upload.php">Upload Answers</a>
                <a class="btn btn-outline-primary" href="view_submissions.php">View Your Submissions</a>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
