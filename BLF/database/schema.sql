CREATE DATABASE IF NOT EXISTS blf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blf;

CREATE TABLE users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100)  NOT NULL,
  email          VARCHAR(150)  NOT NULL UNIQUE,
  password_hash  VARCHAR(255)  NOT NULL,
  role           ENUM('staff','admin') NOT NULL DEFAULT 'staff',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(100) NOT NULL,
  slug  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  category_id  INT NOT NULL,
  name         VARCHAR(150) NOT NULL,
  slug         VARCHAR(150) NOT NULL UNIQUE,
  description  TEXT,
  wood_type    VARCHAR(50),
  dimensions   VARCHAR(100),
  price        DECIMAL(10,2) NULL,
  image        VARCHAR(255),
  in_stock     TINYINT(1) NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_category (category_id),
  INDEX idx_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE inquiries (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  reference_no   VARCHAR(20)  NOT NULL UNIQUE,
  product_id     INT NULL,
  customer_name  VARCHAR(100) NOT NULL,
  phone          VARCHAR(30)  NOT NULL,
  email          VARCHAR(150),
  message        TEXT,
  status         VARCHAR(20)  NOT NULL DEFAULT 'new',
  quoted_amount  DECIMAL(10,2) NULL,
  notes          TEXT,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_reference (reference_no)
) ENGINE=InnoDB;
