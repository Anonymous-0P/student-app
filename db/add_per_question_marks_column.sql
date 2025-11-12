-- Add per_question_marks column to submissions table
-- This will store JSON data with question-wise marks breakdown

ALTER TABLE `submissions` 
ADD COLUMN `per_question_marks` TEXT NULL AFTER `evaluator_remarks`;

-- Add index for better query performance (optional)
CREATE INDEX idx_evaluation_status ON submissions(evaluation_status);
