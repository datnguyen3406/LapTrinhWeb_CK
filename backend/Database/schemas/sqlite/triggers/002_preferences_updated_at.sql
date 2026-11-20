CREATE TRIGGER IF NOT EXISTS trg_preferences_updated_at
AFTER UPDATE ON user_preferences
FOR EACH ROW
WHEN NEW.updated_at = OLD.updated_at
BEGIN
    UPDATE user_preferences
       SET updated_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now')
     WHERE user_id = NEW.user_id;
END;
