-- Add grade_level column to subjects table
-- This allows filtering by proper grade names (10th, 12th) instead of year numbers

-- Step 1: Add the grade_level column
ALTER TABLE subjects 
ADD COLUMN grade_level ENUM('10th', '12th') NULL AFTER department;

-- Step 2: Update grade_level based on existing year values
UPDATE subjects SET grade_level = '10th' WHERE year = 1;
UPDATE subjects SET grade_level = '12th' WHERE year = 2;

-- Step 3: Update grade_level based on subject code if year is NULL
UPDATE subjects 
SET grade_level = '10th' 
WHERE grade_level IS NULL AND (code LIKE '%10TH%' OR code LIKE '%_10_%');

UPDATE subjects 
SET grade_level = '12th' 
WHERE grade_level IS NULL AND (code LIKE '%12TH%' OR code LIKE '%_12_%');

-- Step 4: Set default to 10th for any remaining NULL values
UPDATE subjects 
SET grade_level = '10th' 
WHERE grade_level IS NULL;

-- Step 5: Verify the update
SELECT 
    code,
    name,
    department,
    year,
    grade_level
FROM subjects
ORDER BY grade_level, code;
