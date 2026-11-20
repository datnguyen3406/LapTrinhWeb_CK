CREATE TRIGGER IF NOT EXISTS trg_users_updated_at
AFTER UPDATE ON users
FOR EACH ROW
WHEN NEW.updated_at = OLD.updated_at
BEGIN
    UPDATE users
       SET updated_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now')
     WHERE id = NEW.id;
END;
