-- Run once in phpMyAdmin or: mysql -u root < database/schema.sql
-- XAMPP default: user root, no password.

CREATE DATABASE IF NOT EXISTS cris_activities
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cris_activities;

CREATE TABLE IF NOT EXISTS folders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  deleted_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_folders_deleted (deleted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  folder_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(64) NOT NULL,
  mime_type VARCHAR(128) NOT NULL DEFAULT 'application/octet-stream',
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_files_folder
    FOREIGN KEY (folder_id) REFERENCES folders (id)
    ON DELETE RESTRICT,
  INDEX idx_files_folder (folder_id),
  INDEX idx_files_deleted (deleted_at)
) ENGINE=InnoDB;
