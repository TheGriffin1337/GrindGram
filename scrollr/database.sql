-- ============================================
-- SCROLLR - Database Schema
-- MySQL 8.0+
-- ============================================

CREATE DATABASE IF NOT EXISTS scrollr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scrollr;

-- ============================================
-- USERS
-- ============================================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,              -- bcrypt hash
    avatar      VARCHAR(255) DEFAULT NULL,
    bio         TEXT         DEFAULT NULL,
    role        ENUM('user','admin') DEFAULT 'user',
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- POSTS
-- ============================================
CREATE TABLE posts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL,
    type         ENUM('image','video','text','ai') NOT NULL DEFAULT 'text',
    title        VARCHAR(255) DEFAULT NULL,
    content      TEXT         DEFAULT NULL,          -- tekst postu lub opis
    media_url    VARCHAR(500) DEFAULT NULL,          -- ścieżka do pliku
    thumbnail    VARCHAR(500) DEFAULT NULL,          -- miniatura dla video
    tags         JSON         DEFAULT NULL,          -- ["nauka","tech","motywacja"]
    ai_prompt    TEXT         DEFAULT NULL,          -- prompt użyty do generowania
    views        INT          DEFAULT 0,
    likes_count  INT          DEFAULT 0,
    is_published TINYINT(1)   DEFAULT 1,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type        (type),
    INDEX idx_created     (created_at DESC),
    INDEX idx_user        (user_id),
    INDEX idx_published   (is_published)
);

-- ============================================
-- LIKES
-- ============================================
CREATE TABLE likes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT      NOT NULL,
    post_id    INT      NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id)  ON DELETE CASCADE
);

-- ============================================
-- COMMENTS
-- ============================================
CREATE TABLE comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT  NOT NULL,
    post_id    INT  NOT NULL,
    content    TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id)  ON DELETE CASCADE,
    INDEX idx_post (post_id)
);

-- ============================================
-- SESSIONS (server-side sessions)
-- ============================================
CREATE TABLE user_sessions (
    token      VARCHAR(128) PRIMARY KEY,
    user_id    INT          NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_expires (expires_at)
);

-- ============================================
-- AI GENERATION QUEUE
-- ============================================
CREATE TABLE ai_jobs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    prompt      TEXT         NOT NULL,
    type        ENUM('text','image') DEFAULT 'text',
    status      ENUM('pending','processing','done','failed') DEFAULT 'pending',
    result_post INT          DEFAULT NULL,           -- id wygenerowanego posta
    error_msg   TEXT         DEFAULT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    done_at     DATETIME     DEFAULT NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (result_post) REFERENCES posts(id) ON DELETE SET NULL
);

-- ============================================
-- SEED: admin user (password: admin123)
-- ============================================
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@scrollr.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ============================================
-- SEED: przykładowe posty
-- ============================================
INSERT INTO posts (user_id, type, title, content, tags) VALUES
(1, 'text', '🚀 Jak uczyć się programowania skutecznie?',
 'Kluczem jest konsekwencja. 30 minut dziennie przez rok da Ci więcej niż 8h raz w tygodniu. Zacznij od małych projektów które rozwiązują Twoje własne problemy.',
 '["programowanie","nauka","motywacja"]'),
(1, 'text', '💡 Zasada Pareto w nauce',
 '80% wyników pochodzi z 20% wysiłku. W programowaniu: naucz się dobrze fundamentów (zmienne, pętle, funkcje, algorytmy) zanim zaczniesz uczyć się frameworków.',
 '["nauka","produktywność"]'),
(1, 'text', '🧠 Deep Work - jak skupić się w erze rozproszenia',
 'Cal Newport pokazuje że zdolność do głębokiej koncentracji to supermocy XXI wieku. Zablokuj powiadomienia, zaplanuj sesje pracy bez przeszkód minimum 90 minut.',
 '["produktywność","ksiazki","mindset"]');
