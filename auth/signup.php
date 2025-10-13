<?php
include('../config/config.php');

if(isset($_POST['signup'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = 'student'; // Always student role
    $roll_no = isset($_POST['roll_no']) ? trim($_POST['roll_no']) : null;
    $course = isset($_POST['course']) ? trim($_POST['course']) : null;
    $year = isset($_POST['year']) ? (int)$_POST['year'] : null;
    $department = isset($_POST['department']) ? trim($_POST['department']) : null;

    if (empty($roll_no) || empty($course)) {
        $error = "Please fill required fields: Roll Number and Course.";
    } else {
        // Check if email or roll number already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR roll_no = ?");
        $check_stmt->bind_param("ss", $email, $roll_no);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error = "Email or Roll Number already exists. Please use different credentials.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, roll_no, course, year, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssis", $name, $email, $password, $role, $roll_no, $course, $year, $department);

            if($stmt->execute()){
                $success = "Student account created successfully! You can now login.";
                // Optional: Auto redirect after a delay
                header("refresh:2;url=login.php");
            } else {
                $error = "Error creating account: " . $conn->error;
            }
        }
    }
}
?>

<?php include('../includes/header.php'); ?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="page-card">
            <h2 class="mb-4">Create Student Account</h2>
            <?php if(isset($error)) echo '<div class="alert alert-danger py-2">'.htmlspecialchars($error).'</div>'; ?>
            <?php if(isset($success)) echo '<div class="alert alert-success py-2">'.htmlspecialchars($success).'</div>'; ?>
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Jane Doe" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Enter a strong password" required minlength="6">
                    <small class="text-muted">Password must be at least 6 characters long</small>
                </div>
                
                <!-- Student profile fields (always visible now) -->
                <div class="col-12">
                    <h6 class="text-primary mt-3 mb-2">Student Information</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Roll Number <span class="text-danger">*</span></label>
                            <input type="text" name="roll_no" class="form-control" placeholder="CS21-001" required value="<?= isset($_POST['roll_no']) ? htmlspecialchars($_POST['roll_no']) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course <span class="text-danger">*</span></label>
                            <input type="text" name="course" class="form-control" placeholder="B.Tech Computer Science" required value="<?= isset($_POST['course']) ? htmlspecialchars($_POST['course']) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year <span class="text-muted">(optional)</span></label>
                            <select name="year" class="form-select">
                                <option value="">Select Year</option>
                                <option value="1" <?= (isset($_POST['year']) && $_POST['year'] == '1') ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= (isset($_POST['year']) && $_POST['year'] == '2') ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= (isset($_POST['year']) && $_POST['year'] == '3') ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= (isset($_POST['year']) && $_POST['year'] == '4') ? 'selected' : '' ?>>4th Year</option>
                                <option value="5" <?= (isset($_POST['year']) && $_POST['year'] == '5') ? 'selected' : '' ?>>5th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department <span class="text-muted">(optional)</span></label>
                            <input type="text" name="department" class="form-control" placeholder="Computer Science" value="<?= isset($_POST['department']) ? htmlspecialchars($_POST['department']) : '' ?>">
                        </div>
                    </div>
                </div>
                <div class="col-12 d-grid">
                    <button type="submit" name="signup" class="btn btn-primary btn-lg">Create Student Account</button>
                </div>
                <p class="mt-2 mb-0 small text-center">Already have an account? <a href="login.php" class="text-decoration-none">Login here</a></p>
            </form>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
