<?php
require_once('../config/config.php');
require_once('../includes/functions.php');
include('../includes/header.php');

checkLogin('faculty');

if(!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    echo '<p>Invalid submission id.</p>';
    include('../includes/footer.php');
    exit;
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT s.id, s.pdf_url, s.created_at, u.name, u.email FROM submissions s JOIN users u ON s.student_id = u.id WHERE s.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if($res->num_rows === 0) {
    echo '<p>Submission not found.</p>';
    include('../includes/footer.php');
    exit;
}
$submission = $res->fetch_assoc();

// Ensure the file exists on disk (prevent broken links)
$pdfPath = $submission['pdf_url'];
// Fix PDF URL for proper access
if (strpos($pdfPath, '/uploads/') === 0) {
    // Convert absolute path to relative path from faculty directory
    $pdfPath = '..' . $pdfPath;
}

if(!preg_match('/\.pdf$/i', $pdfPath)) {
    echo '<p>Stored file is not a PDF.</p>';
    include('../includes/footer.php');
    exit;
}

?>
<div class="row">
    <div class="col-xl-11">
        <div class="page-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <h2 class="mb-0">Submission #<?= $submission['id']; ?></h2>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&laquo; Back</a>
            </div>
            <dl class="row mb-4">
                <dt class="col-sm-2">Student</dt><dd class="col-sm-10"><?= sanitize($submission['name']); ?> (<?= sanitize($submission['email']); ?>)</dd>
                <dt class="col-sm-2">Uploaded</dt><dd class="col-sm-10"><?= sanitize($submission['created_at']); ?></dd>
            </dl>
            <p><a href="<?= sanitize($pdfPath); ?>" target="_blank" class="btn btn-primary btn-sm">Open in New Tab</a></p>
            <div class="pdf-viewer mb-3">
                <object data="<?= sanitize($pdfPath); ?>" type="application/pdf">
                        <p>Your browser does not support embedded PDFs. <a href="<?= sanitize($pdfPath); ?>" target="_blank">Download PDF</a>.</p>
                </object>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>