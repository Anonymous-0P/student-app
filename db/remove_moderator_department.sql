-- Migration to remove department dependency for moderators
-- Date: 2025-11-12
-- Description: This migration removes the department column usage for moderators
-- Note: We're not physically dropping the column to maintain backward compatibility
-- and prevent data loss for other roles that may use it

-- Step 1: Set all moderator departments to NULL (clean up)
UPDATE users 
SET department = NULL 
WHERE role = 'moderator';

-- Step 2: Add index to improve moderator queries
CREATE INDEX IF NOT EXISTS idx_users_role_active 
ON users(role, is_active);

-- Step 3: Add index for moderator assignments
CREATE INDEX IF NOT EXISTS idx_users_moderator_id 
ON users(moderator_id);

-- Verification Query
-- Run this to verify the migration
SELECT 
    COUNT(*) as total_moderators,
    SUM(CASE WHEN department IS NULL THEN 1 ELSE 0 END) as moderators_without_dept,
    SUM(CASE WHEN department IS NOT NULL THEN 1 ELSE 0 END) as moderators_with_dept
FROM users 
WHERE role = 'moderator';

-- Expected result: moderators_without_dept should equal total_moderators
