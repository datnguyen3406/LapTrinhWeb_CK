CREATE INDEX IF NOT EXISTS idx_users_role_active_created
    ON users(role, is_active, created_at DESC);
