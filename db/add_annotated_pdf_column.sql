-- Add annotated_pdf_url column to submissions table
-- This will store the evaluator's annotated version of the answer sheet

ALTER TABLE submissions 
ADD COLUMN annotated_pdf_url VARCHAR(255) NULL AFTER pdf_url;

-- Verify the column was added
DESCRIBE submissions;
