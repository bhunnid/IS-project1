-- =====================================================================
-- cat2_setup.sql — Server Administration CAT 2
-- Author: Brandon Kiplangat (CNS 3A)
-- Creates the CAT2 database, its tables, and a least-privilege app user.
-- Run with:  sudo mysql < db/cat2_setup.sql
-- =====================================================================

-- 1. Create the database required by the CAT
CREATE DATABASE IF NOT EXISTS CAT2
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE CAT2;

-- 2. users table — filled by the signup page (register.php)
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    fullname   VARCHAR(100) NOT NULL,
    email      VARCHAR(120) NOT NULL UNIQUE,
    username   VARCHAR(60)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,          -- stores a bcrypt hash, never plaintext
    course     VARCHAR(80)  DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. feedback table — the SECOND form required in Part B Q7
CREATE TABLE IF NOT EXISTS feedback (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(120) NOT NULL,
    subject    VARCHAR(150) NOT NULL,
    rating     TINYINT      DEFAULT NULL,       -- 1..5 star rating
    message    TEXT         NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Dedicated application account (least privilege: CAT2 only)
CREATE USER IF NOT EXISTS 'cat2_user'@'localhost' IDENTIFIED BY 'Cat2@StrathCNS';
GRANT ALL PRIVILEGES ON CAT2.* TO 'cat2_user'@'localhost';
FLUSH PRIVILEGES;
