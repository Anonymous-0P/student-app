-- Add password reset columns to users table
ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL AFTER password,
ADD COLUMN reset_token_expiry DATETIME NULL DEFAULT NULL AFTER reset_token,
ADD INDEX idx_reset_token (reset_token);

-- Note: This migration adds support for password reset functionality
-- The reset_token stores a secure random token for password reset
-- The reset_token_expiry ensures tokens expire after 1 hour for security
