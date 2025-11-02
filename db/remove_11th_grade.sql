-- ============================================================================
-- Migration Script: Remove 11th Grade/Standard from System
-- Date: November 2025
-- Description: This script removes 11th grade from the system and updates
--              the database schema to only support 10th and 12th grades.
-- ============================================================================

USE student_photo_app;

-- Step 1: Backup affected data (optional - comment out if not needed)
-- CREATE TABLE IF NOT EXISTS backup_11th_question_papers AS 
-- SELECT * FROM question_papers WHERE grade_level = '11th';

-- Step 2: Delete all 11th grade question papers
DELETE FROM question_papers WHERE grade_level = '11th';

-- Step 3: Update any students with year 2 to year 1 (10th) or year 3 to year 2 (12th)
-- This maps: Year 1 = 10th, Year 2 = 12th (skipping 11th entirely)
-- You may want to review this based on your data
UPDATE users 
SET year = CASE 
    WHEN year = 2 THEN 2  -- Keep year 2 but it now maps to 12th
    WHEN year = 3 THEN 2  -- Move year 3 down to year 2 (12th)
    ELSE year 
END
WHERE role = 'student';

-- Step 4: Modify the question_papers table to remove '11th' from the ENUM
-- Note: This requires recreating the column
ALTER TABLE question_papers 
MODIFY COLUMN grade_level ENUM('10th', '12th') NOT NULL;

-- Step 5: Verify changes
SELECT 
    'Question Papers' as table_name,
    grade_level,
    COUNT(*) as count
FROM question_papers
GROUP BY grade_level

UNION ALL

SELECT 
    'Students' as table_name,
    CASE 
        WHEN year = 1 THEN '10th (Year 1)'
        WHEN year = 2 THEN '12th (Year 2)'
        ELSE CONCAT('Other (Year ', year, ')')
    END as grade_level,
    COUNT(*) as count
FROM users
WHERE role = 'student'
GROUP BY year;

-- ============================================================================
-- Migration Complete
-- ============================================================================
-- IMPORTANT NOTES:
-- 1. All 11th grade question papers have been deleted
-- 2. The grade_level enum now only contains '10th' and '12th'
-- 3. Student year mapping: Year 1 = 10th, Year 2 = 12th
-- 4. Review the verification query results above
-- ============================================================================
