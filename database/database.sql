-- Optional: stores contact-form messages in MySQL (in addition to the email).
-- Import this file in phpMyAdmin, then set 'db' => ['enabled' => true, ...] in includes/config.php

CREATE DATABASE IF NOT EXISTS portfolio_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE portfolio_db;

CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80)  NOT NULL,
  email       VARCHAR(120) NOT NULL,
  phone       VARCHAR(20)  DEFAULT NULL,
  service     VARCHAR(100) DEFAULT NULL,
  message     TEXT         NOT NULL,
  ip_address  VARCHAR(45)  DEFAULT NULL,
  user_agent  VARCHAR(255) DEFAULT NULL,
  status      ENUM('new','read','replied') NOT NULL DEFAULT 'new',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
