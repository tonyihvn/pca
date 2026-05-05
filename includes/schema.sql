-- ============================================================
-- Academy database schema
-- Run with:  mysql -u root -p academy < schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS registrations (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course      VARCHAR(64)  NOT NULL,
    name        VARCHAR(120) NOT NULL,
    email       VARCHAR(180) NOT NULL,
    phone       VARCHAR(40)  NOT NULL,
    level       VARCHAR(40)  NOT NULL,
    description TEXT         NULL,
    ip          VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_course (course),
    INDEX idx_email  (email),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(120) NOT NULL,
    email        VARCHAR(180) NOT NULL,
    phone        VARCHAR(40)  NULL,
    inquiry_type VARCHAR(80)  NOT NULL,
    message      TEXT         NOT NULL,
    ip           VARCHAR(45)  NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
