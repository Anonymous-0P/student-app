-- Create subject bundles table for combo pricing
CREATE TABLE IF NOT EXISTS subject_bundles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bundle_name VARCHAR(255) NOT NULL,
    description TEXT,
    bundle_price DECIMAL(10, 2) NOT NULL,
    discount_percentage DECIMAL(5, 2) DEFAULT 0,
    duration_days INT DEFAULT 365,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create bundle items table (subjects in each bundle)
CREATE TABLE IF NOT EXISTS bundle_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bundle_id INT NOT NULL,
    subject_id INT NOT NULL,
    FOREIGN KEY (bundle_id) REFERENCES subject_bundles(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bundle_subject (bundle_id, subject_id)
);

-- Create student bundle purchases table
CREATE TABLE IF NOT EXISTS student_bundle_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    bundle_id INT NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date TIMESTAMP NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bundle_id) REFERENCES subject_bundles(id) ON DELETE CASCADE
);
