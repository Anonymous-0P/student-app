<?php
/**
 * Database Update Script
 * Run this script to update your database with new schema changes
 * for the Student Portal features
 */

// Include database configuration
require_once 'config/config.php';

echo "<h2>Database Update Script</h2>\n";
echo "<pre>\n";

// Track success/failure
$success = true;
$errors = [];

try {
    // Start transaction
    $conn->autocommit(false);
    
    echo "Starting database update...\n\n";
    
    // 1. Add new columns to users table
    echo "1. Updating users table structure...\n";
    
    $alterQueries = [
        "ALTER TABLE users ADD COLUMN roll_no VARCHAR(50) UNIQUE NULL AFTER email",
        "ALTER TABLE users ADD COLUMN course VARCHAR(100) NULL AFTER role",
        "ALTER TABLE users ADD COLUMN year INT NULL AFTER course",
        "ALTER TABLE users ADD COLUMN department VARCHAR(100) NULL AFTER year",
        "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER department"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $result = $conn->query($query);
            if ($result) {
                echo "   ✓ " . substr($query, 0, 50) . "...\n";
            }
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "   ⚠ Column already exists: " . substr($query, 0, 50) . "...\n";
            } else {
                throw $e;
            }
        }
    }
    
    // 1.5. Update submissions table structure
    echo "\n1.5. Updating submissions table structure...\n";
    
    $submissionAlters = [
        "ALTER TABLE submissions ADD COLUMN subject_id INT NULL AFTER student_id",
        "ALTER TABLE submissions ADD COLUMN original_filename VARCHAR(255) NULL AFTER pdf_url",
        "ALTER TABLE submissions ADD COLUMN file_size INT NULL AFTER original_filename",
        "ALTER TABLE submissions MODIFY COLUMN status ENUM('pending','approved','rejected','under_review') DEFAULT 'pending'",
        "ALTER TABLE submissions ADD COLUMN admin_remarks TEXT NULL AFTER status",
        "ALTER TABLE submissions ADD COLUMN marks DECIMAL(5,2) NULL AFTER admin_remarks",
        "ALTER TABLE submissions ADD COLUMN total_marks DECIMAL(5,2) NULL AFTER marks",
        "ALTER TABLE submissions ADD COLUMN evaluation_notes TEXT NULL AFTER total_marks",
        "ALTER TABLE submissions ADD COLUMN evaluated_at TIMESTAMP NULL AFTER evaluation_notes",
        "ALTER TABLE submissions ADD COLUMN evaluated_by INT NULL AFTER evaluated_at"
    ];
    
    foreach ($submissionAlters as $query) {
        try {
            $result = $conn->query($query);
            if ($result) {
                echo "   ✓ " . substr($query, 0, 60) . "...\n";
            }
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "   ⚠ Column already exists: " . substr($query, 0, 60) . "...\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Add foreign key constraints
    echo "\n1.6. Adding foreign key constraints...\n";
    try {
        $conn->query("ALTER TABLE submissions ADD CONSTRAINT fk_submission_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL");
        echo "   ✓ Added subject foreign key constraint\n";
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "   ⚠ Foreign key constraint already exists\n";
        } else {
            echo "   ⚠ Could not add foreign key: " . $e->getMessage() . "\n";
        }
    }
    
    try {
        $conn->query("ALTER TABLE submissions ADD CONSTRAINT fk_submission_evaluator FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "   ✓ Added evaluator foreign key constraint\n";
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "   ⚠ Foreign key constraint already exists\n";
        } else {
            echo "   ⚠ Could not add foreign key: " . $e->getMessage() . "\n";
        }
    }
    
    // 1.7. Update user roles for admin system
    echo "\n1.7. Updating user roles for admin system...\n";
    try {
        $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('student','evaluator','moderator','admin') NOT NULL");
        echo "   ✓ Updated user roles to include evaluator, moderator, admin\n";
    } catch (mysqli_sql_exception $e) {
        echo "   ⚠ Could not update user roles: " . $e->getMessage() . "\n";
    }
    
    // Add moderator assignment fields
    $adminAlters = [
        "ALTER TABLE users ADD COLUMN moderator_id INT NULL AFTER department",
        "ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER moderator_id"
    ];
    
    foreach ($adminAlters as $query) {
        try {
            $result = $conn->query($query);
            if ($result) {
                echo "   ✓ " . substr($query, 0, 60) . "...\n";
            }
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "   ⚠ Column already exists: " . substr($query, 0, 60) . "...\n";
            } else {
                echo "   ⚠ Could not add column: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Add moderator foreign key
    try {
        $conn->query("ALTER TABLE users ADD CONSTRAINT fk_user_moderator FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "   ✓ Added moderator foreign key constraint\n";
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "   ⚠ Moderator foreign key constraint already exists\n";
        } else {
            echo "   ⚠ Could not add moderator foreign key: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Create subjects table
    echo "\n2. Creating subjects table...\n";
    $subjectsTable = "
    CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(150) NOT NULL,
        department VARCHAR(100) NULL,
        year INT NULL,
        semester INT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($subjectsTable)) {
        echo "   ✓ Subjects table created successfully\n";
    } else {
        throw new Exception("Error creating subjects table: " . $conn->error);
    }
    
    // 3. Create question_templates table
    echo "\n3. Creating question_templates table...\n";
    $templatesTable = "
    CREATE TABLE IF NOT EXISTS question_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        easy_count INT DEFAULT 0,
        medium_count INT DEFAULT 0,
        hard_count INT DEFAULT 0,
        total_marks INT DEFAULT 0,
        duration_minutes INT DEFAULT 60,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($templatesTable)) {
        echo "   ✓ Question templates table created successfully\n";
    } else {
        throw new Exception("Error creating question_templates table: " . $conn->error);
    }
    
    // 4. Create questions table
    echo "\n4. Creating questions table...\n";
    $questionsTable = "
    CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        question_text TEXT NOT NULL,
        difficulty ENUM('easy','medium','hard') NOT NULL,
        marks INT DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($questionsTable)) {
        echo "   ✓ Questions table created successfully\n";
    } else {
        throw new Exception("Error creating questions table: " . $conn->error);
    }
    
    // 4.5. Create subject assignments table for moderator-subject mapping
    echo "\n4.5. Creating subject_assignments table...\n";
    $assignmentsTable = "
    CREATE TABLE IF NOT EXISTS subject_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        moderator_id INT NOT NULL,
        subject_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        UNIQUE KEY unique_assignment (moderator_id, subject_id),
        FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($assignmentsTable)) {
        echo "   ✓ Subject assignments table created successfully\n";
    } else {
        throw new Exception("Error creating subject_assignments table: " . $conn->error);
    }
    
    // 4.6. Create evaluation assignments table for tracking evaluator workload
    echo "\n4.6. Creating evaluation_assignments table...\n";
    $evalAssignmentsTable = "
    CREATE TABLE IF NOT EXISTS evaluation_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        evaluator_id INT NOT NULL,
        moderator_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('assigned','in_progress','completed') DEFAULT 'assigned',
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
        FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($evalAssignmentsTable)) {
        echo "   ✓ Evaluation assignments table created successfully\n";
    } else {
        throw new Exception("Error creating evaluation_assignments table: " . $conn->error);
    }
    
    // 5. Seed sample subjects
    echo "\n5. Inserting sample subjects...\n";
    $sampleSubjects = [
        ['CS201', 'Data Structures', 'Computer Science', 2, 3],
        ['CS202', 'Database Systems', 'Computer Science', 2, 3],
        ['CS301', 'Operating Systems', 'Computer Science', 3, 5],
        ['MA201', 'Linear Algebra', 'Mathematics', 2, 3],
        ['PH101', 'Physics Fundamentals', 'Physics', 1, 1]
    ];
    
    $stmt = $conn->prepare("INSERT INTO subjects (code, name, department, year, semester, is_active) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE name=VALUES(name), department=VALUES(department), year=VALUES(year), semester=VALUES(semester)");
    
    foreach ($sampleSubjects as $subject) {
        $stmt->bind_param("sssii", $subject[0], $subject[1], $subject[2], $subject[3], $subject[4]);
        if ($stmt->execute()) {
            echo "   ✓ Added subject: {$subject[0]} - {$subject[1]}\n";
        }
    }
    
    // 5.5. Create admin and management users
    echo "\n5.5. Creating admin and management users...\n";
    
    // Check if admin exists
    $adminCheck = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($adminCheck->num_rows == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
        
        $adminUsers = [
            ['System Administrator', 'admin@school.edu', $adminPassword, 'admin'],
            ['Dr. Sarah Johnson', 'moderator1@school.edu', password_hash('mod123', PASSWORD_BCRYPT), 'moderator'],
            ['Prof. Michael Chen', 'moderator2@school.edu', password_hash('mod123', PASSWORD_BCRYPT), 'moderator'],
            ['Ms. Emily Davis', 'evaluator1@school.edu', password_hash('eval123', PASSWORD_BCRYPT), 'evaluator'],
            ['Mr. Robert Wilson', 'evaluator2@school.edu', password_hash('eval123', PASSWORD_BCRYPT), 'evaluator']
        ];
        
        foreach ($adminUsers as $user) {
            $stmt->bind_param("ssss", $user[0], $user[1], $user[2], $user[3]);
            if ($stmt->execute()) {
                echo "   ✓ Created {$user[3]}: {$user[0]}\n";
            }
        }
    } else {
        echo "   ⚠ Admin users already exist\n";
    }
    
    // 6. Seed question templates
    echo "\n6. Creating question templates...\n";
    $templates = [
        ['CS201', 2, 2, 1, 50, 60],  // Data Structures: 2 easy, 2 medium, 1 hard
        ['CS202', 3, 2, 1, 60, 90],  // Database Systems: 3 easy, 2 medium, 1 hard
        ['CS301', 2, 3, 2, 70, 120], // Operating Systems: 2 easy, 3 medium, 2 hard
        ['MA201', 3, 3, 1, 70, 90],  // Linear Algebra: 3 easy, 3 medium, 1 hard
        ['PH101', 4, 2, 1, 50, 60]   // Physics: 4 easy, 2 medium, 1 hard
    ];
    
    foreach ($templates as $template) {
        $subjectQuery = $conn->prepare("SELECT id FROM subjects WHERE code = ?");
        $subjectQuery->bind_param("s", $template[0]);
        $subjectQuery->execute();
        $subjectResult = $subjectQuery->get_result();
        
        if ($subjectRow = $subjectResult->fetch_assoc()) {
            $templateStmt = $conn->prepare("INSERT INTO question_templates (subject_id, easy_count, medium_count, hard_count, total_marks, duration_minutes, is_active) VALUES (?, ?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE easy_count=VALUES(easy_count), medium_count=VALUES(medium_count), hard_count=VALUES(hard_count), total_marks=VALUES(total_marks), duration_minutes=VALUES(duration_minutes)");
            $templateStmt->bind_param("iiiiii", $subjectRow['id'], $template[1], $template[2], $template[3], $template[4], $template[5]);
            
            if ($templateStmt->execute()) {
                echo "   ✓ Template created for {$template[0]}: {$template[1]}E + {$template[2]}M + {$template[3]}H = {$template[4]} marks\n";
            }
        }
    }
    
    // 7. Seed sample questions
    echo "\n7. Adding sample questions...\n";
    
    // Data Structures questions
    $dsQuestions = [
        ['CS201', 'Explain the concept of a stack with a real-world example.', 'easy', 5],
        ['CS201', 'What is the difference between array and linked list?', 'easy', 5],
        ['CS201', 'Define a queue and explain its FIFO principle.', 'easy', 5],
        ['CS201', 'Describe how a binary search works and its time complexity.', 'medium', 10],
        ['CS201', 'Explain the working of Quick Sort and analyze its complexities.', 'medium', 10],
        ['CS201', 'Compare different tree traversal methods with examples.', 'medium', 10],
        ['CS201', 'Design a data structure to implement an LRU cache with operations.', 'hard', 20],
        ['CS201', 'Implement a balanced binary search tree and explain rotations.', 'hard', 20]
    ];
    
    // Database Systems questions
    $dbQuestions = [
        ['CS202', 'What is a database and how is it different from a file system?', 'easy', 5],
        ['CS202', 'Explain the concept of primary key and foreign key.', 'easy', 5],
        ['CS202', 'Define normalization and its importance in database design.', 'easy', 5],
        ['CS202', 'Explain different types of SQL joins with examples.', 'medium', 10],
        ['CS202', 'Describe ACID properties of database transactions.', 'medium', 10],
        ['CS202', 'Compare different database indexing techniques.', 'medium', 10],
        ['CS202', 'Design a database schema for an e-commerce system with optimization.', 'hard', 20]
    ];
    
    $allQuestions = array_merge($dsQuestions, $dbQuestions);
    
    foreach ($allQuestions as $question) {
        $subjectQuery = $conn->prepare("SELECT id FROM subjects WHERE code = ?");
        $subjectQuery->bind_param("s", $question[0]);
        $subjectQuery->execute();
        $subjectResult = $subjectQuery->get_result();
        
        if ($subjectRow = $subjectResult->fetch_assoc()) {
            $questionStmt = $conn->prepare("INSERT INTO questions (subject_id, question_text, difficulty, marks, is_active) VALUES (?, ?, ?, ?, 1)");
            $questionStmt->bind_param("issi", $subjectRow['id'], $question[1], $question[2], $question[3]);
            
            if ($questionStmt->execute()) {
                echo "   ✓ Added {$question[2]} question for {$question[0]}: " . substr($question[1], 0, 50) . "...\n";
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    echo "\n✅ Database update completed successfully!\n";
    echo "\nSummary:\n";
    echo "- Users table updated with profile fields\n";
    echo "- Created subjects, question_templates, and questions tables\n";
    echo "- Added " . count($sampleSubjects) . " sample subjects\n";
    echo "- Created " . count($templates) . " question templates\n";
    echo "- Added " . count($allQuestions) . " sample questions\n";
    
    echo "\n🎉 Your student portal is ready to use!\n";
    echo "You can now:\n";
    echo "1. Register students with roll numbers and profiles\n";
    echo "2. Login with email or roll number\n";
    echo "3. Select subjects and view randomized question papers\n";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $success = false;
    echo "\n❌ Error during database update: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
    
} finally {
    // Reset autocommit
    $conn->autocommit(true);
}

echo "</pre>\n";

if ($success) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>✅ Success!</strong> Database has been updated successfully. You can now use all the new student portal features.";
    echo "</div>";
    
    echo "<div style='background: #cce7ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "• Navigate to <a href='auth/signup.php'>Signup</a> to create student accounts<br>";
    echo "• Try logging in with email or roll number<br>";
    echo "• Visit <a href='student/subjects.php'>Subject Selection</a> to see the new features<br>";
    echo "• Test the randomized question paper generation";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>❌ Error!</strong> Database update failed. Please check the error messages above and try again.";
    echo "</div>";
}

echo "<p><a href='index.php'>← Back to Home</a></p>";
?>