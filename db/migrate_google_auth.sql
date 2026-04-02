-- Migration: Add Google OAuth support to users table
-- Run this on existing Railway DB

ALTER TABLE users
  MODIFY COLUMN username VARCHAR(100) NULL,
  MODIFY COLUMN email VARCHAR(255) NULL,
  MODIFY COLUMN password_hash VARCHAR(255) NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) UNIQUE NULL AFTER avatar_url;

CREATE INDEX IF NOT EXISTS idx_google_id ON users (google_id);
