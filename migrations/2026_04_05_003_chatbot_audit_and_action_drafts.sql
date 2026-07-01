CREATE TABLE IF NOT EXISTS chatbot_sessions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_key VARCHAR(64) NOT NULL UNIQUE,
    ma_tk INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_interaction_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chatbot_sessions_matk (ma_tk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS chatbot_action_drafts (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    token VARCHAR(32) NOT NULL UNIQUE,
    action_type VARCHAR(100) NOT NULL,
    title VARCHAR(180) NOT NULL,
    summary TEXT NULL,
    permission_required VARCHAR(100) NULL,
    payload_json MEDIUMTEXT NULL,
    status_name VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_by INT NOT NULL,
    confirmed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    executed_at DATETIME NULL,
    result_message TEXT NULL,
    CONSTRAINT fk_chatbot_action_drafts_session FOREIGN KEY (session_id) REFERENCES chatbot_sessions(id) ON DELETE CASCADE,
    INDEX idx_chatbot_action_drafts_status (status_name),
    INDEX idx_chatbot_action_drafts_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS chatbot_messages (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    role_name VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    source_name VARCHAR(40) NULL,
    actions_json MEDIUMTEXT NULL,
    suggestions_json MEDIUMTEXT NULL,
    action_draft_token VARCHAR(32) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chatbot_messages_session FOREIGN KEY (session_id) REFERENCES chatbot_sessions(id) ON DELETE CASCADE,
    INDEX idx_chatbot_messages_created_at (created_at),
    INDEX idx_chatbot_messages_role (role_name),
    INDEX idx_chatbot_messages_action_token (action_draft_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
