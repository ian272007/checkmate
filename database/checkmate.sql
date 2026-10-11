-- checkmate.sql
-- Run this in phpMyAdmin (SQL tab) to create the database and the users table.

-- make the database (only if it doesn't exist yet)
CREATE DATABASE IF NOT EXISTS checkmate_db;

-- use that database
USE checkmate_db;

-- table that stores every account made from the sign up form
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,           -- unique number for each user
  full_name VARCHAR(100) NOT NULL,
  student_id VARCHAR(10) NOT NULL UNIQUE,      -- like 2025-62390, UNIQUE so no one signs up twice
  email VARCHAR(100) NOT NULL UNIQUE,          -- NCST email, also UNIQUE
  course VARCHAR(100) NOT NULL,
  year_level VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,              -- saved as a hash, NOT the real password
  role VARCHAR(10) NOT NULL DEFAULT 'student' -- everyone who signs up is a student

);



CREATE TABLE IF NOT EXISTS answers (
    answer_id   INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,                       -- questions.question_id
    user_id     INT NOT NULL,                       -- users.id of the person who answered
    answer      TEXT NOT NULL,
    create_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_answers_question (question_id),
    INDEX idx_answers_user (user_id)
);


CREATE TABLE IF NOT EXISTS question_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active'
);


CREATE TABLE IF NOT EXISTS question_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active'
);

CREATE TABLE IF NOT EXISTS question_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active'
);

CREATE TABLE IF NOT EXISTS `reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `reporter_id` int(11) NOT NULL,          -- the student who sent the report (users.id)
  `question_id` int(11) DEFAULT NULL,      -- the question that was reported
  `reason` varchar(100) NOT NULL,          -- for example: Inappropriate question
  `details` text DEFAULT NULL,             -- optional explanation from the student
  `status` varchar(20) NOT NULL DEFAULT 'Pending',   -- Pending or Resolved
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`report_id`),
  KEY `idx_reports_status` (`status`),
  KEY `idx_reports_reporter` (`reporter_id`),
  KEY `idx_reports_question` (`question_id`),
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
 

 