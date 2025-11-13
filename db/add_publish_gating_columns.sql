-- Migration: Add publication gating & evaluation enhancement columns
-- Run this after initial schema (`db.sql`) to enable moderator publish workflow.
-- Safe re-run: uses IF NOT EXISTS where supported (MySQL 8+). For earlier versions,
-- ignore duplicate column errors.

ALTER TABLE submissions
  ADD COLUMN IF NOT EXISTS per_question_marks TEXT NULL AFTER evaluator_remarks,
  ADD COLUMN IF NOT EXISTS annotated_pdf_url VARCHAR(255) NULL AFTER per_question_marks,
  ADD COLUMN IF NOT EXISTS percentage DECIMAL(6,2) NULL AFTER annotated_pdf_url,
  ADD COLUMN IF NOT EXISTS grade VARCHAR(4) NULL AFTER percentage,
  ADD COLUMN IF NOT EXISTS is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER grade,
  ADD COLUMN IF NOT EXISTS published_at TIMESTAMP NULL AFTER is_published,
  ADD COLUMN IF NOT EXISTS moderator_remarks TEXT NULL AFTER published_at,
  ADD COLUMN IF NOT EXISTS moderator_id INT NULL AFTER moderator_remarks;

-- Index to speed up student gating queries
ALTER TABLE submissions
  ADD INDEX IF NOT EXISTS idx_submissions_is_published (is_published),
  ADD INDEX IF NOT EXISTS idx_submissions_student_subject (student_id, subject_id);

-- Optional: enforce one active submission per student+subject (commented out by default)
-- ALTER TABLE submissions ADD UNIQUE KEY uniq_student_subject (student_id, subject_id);

-- Foreign key for moderator_id (if users table exists and not already linked)
ALTER TABLE submissions
  ADD CONSTRAINT fk_submissions_moderator
  FOREIGN KEY (moderator_id) REFERENCES users(id)
  ON DELETE SET NULL;

-- Backfill percentage & grade for already evaluated submissions (unpublished)
UPDATE submissions
SET 
  percentage = CASE WHEN max_marks > 0 AND marks_obtained IS NOT NULL THEN ROUND((marks_obtained / max_marks) * 100, 2) ELSE percentage END,
  grade = CASE 
    WHEN marks_obtained IS NULL OR max_marks <= 0 THEN grade
    WHEN (marks_obtained / max_marks) * 100 >= 90 THEN 'A+'
    WHEN (marks_obtained / max_marks) * 100 >= 85 THEN 'A'
    WHEN (marks_obtained / max_marks) * 100 >= 80 THEN 'A-'
    WHEN (marks_obtained / max_marks) * 100 >= 75 THEN 'B+'
    WHEN (marks_obtained / max_marks) * 100 >= 70 THEN 'B'
    WHEN (marks_obtained / max_marks) * 100 >= 65 THEN 'B-'
    WHEN (marks_obtained / max_marks) * 100 >= 60 THEN 'C+'
    WHEN (marks_obtained / max_marks) * 100 >= 55 THEN 'C'
    WHEN (marks_obtained / max_marks) * 100 >= 50 THEN 'C-'
    WHEN (marks_obtained / max_marks) * 100 >= 35 THEN 'D'
    ELSE 'F' END
WHERE percentage IS NULL OR grade IS NULL;

-- NOTE: Publication remains gated by is_published=0 until moderator action sets to 1.