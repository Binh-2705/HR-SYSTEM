-- 2026_04_04_002_session_audit.sql
-- Purpose:
-- Add session audit tracking for current/other session management in Settings.

CREATE TABLE IF NOT EXISTS session_audit (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    MaTK INT NOT NULL,
    session_marker CHAR(64) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_user_marker (MaTK, session_marker),
    INDEX idx_user_activity (MaTK, last_activity),
    INDEX idx_revoked (revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
