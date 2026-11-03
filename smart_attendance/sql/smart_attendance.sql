-- =====================================================
-- SMART ATTENDANCE - AI FACE RECOGNITION SYSTEM
-- Import this file in phpMyAdmin (Import tab)
-- Default logins:  admin / admin123   |   lecturer / lecturer123
-- =====================================================

CREATE DATABASE IF NOT EXISTS smart_attendance
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_attendance;

-- -----------------------------------------------------
-- TABLE 1: users  (people who can log in to the system)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50)  NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,                 -- bcrypt hash (never plain text)
  role     ENUM('admin','lecturer') NOT NULL DEFAULT 'lecturer'
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- TABLE 2: students (face_descriptor = 128 numbers as JSON text)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  reg_no          VARCHAR(30)  NOT NULL UNIQUE,   -- registration number
  name            VARCHAR(100) NOT NULL,
  course          VARCHAR(100) NULL,
  face_descriptor TEXT NULL,                      -- JSON array of 128 floats (face embedding)
  photo_path      VARCHAR(255) NULL,              -- e.g. uploads/ICT001_1700000000.jpg
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- TABLE 3: attendance (one row per student per day)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  student_id       INT NOT NULL,
  `date`           DATE NOT NULL,
  time_in          TIME NOT NULL,
  status           ENUM('Present','Late') NOT NULL DEFAULT 'Present',
  confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0, -- match % = (1 - distance) * 100
  -- DATABASE LEVEL duplicate block: same student cannot be saved twice on the same day
  UNIQUE KEY uq_student_day (student_id, `date`),
  CONSTRAINT fk_att_student FOREIGN KEY (student_id)
    REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Default users (passwords are bcrypt hashes made with password_hash())
-- -----------------------------------------------------
INSERT IGNORE INTO users (username, password, role) VALUES
('admin',    '$2y$10$PmREp4mXVtoLd54tAGKsbeFnbXj9drMNv4Bp6AdBchdzkVyVJyDze', 'admin'),
('lecturer', '$2y$10$Y00NBZcCuwNsT4uykQnTzOFDH2TWWic0gLI0yek574dNdHs4Gi40u', 'lecturer');
