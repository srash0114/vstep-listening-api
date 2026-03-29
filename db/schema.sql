-- VSTEP Listening Test Database Schema (v2.0) - Part-based system
-- Restructured to support Part 1, Part 2, Part 3 with passages and bulk upload

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    avatar_url VARCHAR(255),
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exams (Đề thi)
CREATE TABLE IF NOT EXISTS exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    level VARCHAR(10),  -- A1, A2, B1, B2, C1, C2
    total_duration INT NOT NULL,  -- in minutes
    total_questions INT NOT NULL DEFAULT 35,  -- Standard VSTEP: 35 questions
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_level (level),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parts (Phần thi 1, 2, 3)
CREATE TABLE IF NOT EXISTS parts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    part_number INT NOT NULL,  -- 1, 2, or 3
    title VARCHAR(255) NOT NULL,
    description TEXT,
    audio_url VARCHAR(500),  -- Main audio file for entire part
    audio_path VARCHAR(255), -- Appwrite file ID for deletion
    duration INT,  -- in seconds
    question_count INT,  -- 8 for Part 1, 12 for Part 2, 15 for Part 3
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_part (exam_id, part_number),
    INDEX idx_exam_id (exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Passages (Đoạn hội thoại/bài giảng - for Part 2 and Part 3)
CREATE TABLE IF NOT EXISTS passages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    part_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,  -- e.g., "Conversation 1 - Natasha and Colin"
    script TEXT NOT NULL,  -- Transcript/Full text
    audio_url VARCHAR(500),  -- Individual audio segment (if exists)
    passage_order INT,  -- Order within the part
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    INDEX idx_part_id (part_id),
    INDEX idx_passage_order (passage_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions (Câu hỏi)
CREATE TABLE IF NOT EXISTS questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    part_id INT NOT NULL,
    passage_id INT,  -- NULL for Part 1, NOT NULL for Part 2 and 3
    question_number INT NOT NULL,  -- 1-35 overall
    order_index INT NOT NULL,  -- Order within part/passage
    content TEXT NOT NULL,
    difficulty_level VARCHAR(10),  -- E.g., 3-, 3, 4, 5
    script TEXT,  -- Additional context or description
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    FOREIGN KEY (passage_id) REFERENCES passages(id) ON DELETE SET NULL,
    INDEX idx_part_id (part_id),
    INDEX idx_passage_id (passage_id),
    INDEX idx_question_number (question_number),
    INDEX idx_difficulty_level (difficulty_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Options (Đáp án lựa chọn)
CREATE TABLE IF NOT EXISTS options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    content VARCHAR(500) NOT NULL,
    option_label CHAR(1) NOT NULL,  -- A, B, C, D
    is_correct BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id),
    INDEX idx_is_correct (is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Exams (Lịch sử làm bài)
CREATE TABLE IF NOT EXISTS user_exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL DEFAULT NULL,
    time_spent INT,  -- in seconds
    total_questions INT,
    correct_answers INT,
    score DECIMAL(5, 2),  -- 0-100
    percentage INT,
    performance_level ENUM('excellent', 'good', 'average', 'needs_improvement') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_exam_id (exam_id),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Answers (Câu trả lời đã chọn)
CREATE TABLE IF NOT EXISTS user_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_exam_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT,  -- NULL if no answer
    is_correct BOOLEAN,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_exam_id) REFERENCES user_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES options(id) ON DELETE SET NULL,
    INDEX idx_user_exam_id (user_exam_id),
    INDEX idx_question_id (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
