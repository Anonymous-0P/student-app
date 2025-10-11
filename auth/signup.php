<?php
include('../config/config.php');

if(isset($_POST['signup'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    $roll_no = isset($_POST['roll_no']) ? trim($_POST['roll_no']) : null;
    $course = isset($_POST['course']) ? trim($_POST['course']) : null;
    $year = isset($_POST['year']) ? (int)$_POST['year'] : null;
    $department = isset($_POST['department']) ? trim($_POST['department']) : null;

    if ($role === 'student' && (empty($roll_no) || empty($course))) {
        $error = "Please fill required student fields: Roll Number and Course.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, roll_no, course, year, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssis", $name, $email, $password, $role, $roll_no, $course, $year, $department);

        if($stmt->execute()){
            header("Location: login.php");
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}
?>

<?php include('../includes/header.php'); ?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="page-card">
            <h2 class="mb-4">Create an Account</h2>
            <?php if(isset($error)) echo '<div class="alert alert-danger py-2">'.htmlspecialchars($error).'</div>'; ?>
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Jane Doe" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="">Select Role</option>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>
                <!-- Student profile fields -->
                <div class="col-12 student-fields d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Roll Number <span class="text-danger">*</span></label>
                            <input type="text" name="roll_no" class="form-control" placeholder="CS21-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course <span class="text-danger">*</span></label>
                            <input type="text" name="course" class="form-control" placeholder="B.Tech">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year <span class="text-muted">(optional)</span></label>
                            <input type="number" min="1" max="5" name="year" class="form-control" placeholder="2">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department <span class="text-muted">(optional)</span></label>
                            <input type="text" name="department" class="form-control" placeholder="Computer Science">
                        </div>
                    </div>
                </div>
                <div class="col-12 d-grid">
                    <button type="submit" name="signup" class="btn btn-primary btn-lg">Sign Up</button>
                </div>
                <p class="mt-2 mb-0 small text-center">Already have an account? <a href="login.php">Login</a></p>
            </form>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const roleSelect = document.querySelector('select[name="role"]');
    const studentFields = document.querySelector('.student-fields');
    const toggleFields = () => {
        if (roleSelect.value === 'student') {
            studentFields.classList.remove('d-none');
            // Make roll_no and course required when student
            studentFields.querySelector('input[name="roll_no"]').required = true;
            studentFields.querySelector('input[name="course"]').required = true;
            // Year and department are optional
            studentFields.querySelector('input[name="year"]').required = false;
            studentFields.querySelector('input[name="department"]').required = false;
        } else {
            studentFields.classList.add('d-none');
            studentFields.querySelectorAll('input').forEach(i => i.required = false);
        }
    };
    roleSelect.addEventListener('change', toggleFields);
    toggleFields();
});
</script>
