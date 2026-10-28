-- Funeral Service Management System - MySQL schema
CREATE DATABASE IF NOT EXISTS fprs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fprs;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  phone VARCHAR(50),
  password VARCHAR(255) NOT NULL,
  role ENUM('client','director','admin') DEFAULT 'client',
  valid_id_path VARCHAR(255),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  director_id INT DEFAULT NULL,
  parlor_id INT DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  inclusions TEXT,
  price DECIMAL(10,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE funerals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  director_id INT DEFAULT NULL,
  package_id INT DEFAULT NULL,
  funeral_date DATETIME,
  status ENUM('pending','scheduled','completed','cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
);

CREATE TABLE inventory_resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  director_id INT,
  parlor_id INT,
  item_type ENUM('flower','candle','plant','casket','equipment','other'),
  name VARCHAR(200),
  details TEXT,
  available_status ENUM('available','unavailable') DEFAULT 'available',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  message_text TEXT NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE shops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT,
  name VARCHAR(200) NOT NULL,
  location VARCHAR(255),
  contact_info VARCHAR(255),
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE funeral_parlors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  director_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  location VARCHAR(255) NOT NULL,
  contact_number VARCHAR(50) NOT NULL,
  license_path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Additive migration. Existing parlor feedback is retained as legacy history.
CREATE TABLE IF NOT EXISTS service_inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  package_id INT NOT NULL,
  parlor_id INT NOT NULL,
  occurrence_no INT NOT NULL DEFAULT 1,
  package_title VARCHAR(200) NOT NULL,
  package_price DECIMAL(10,2) NOT NULL,
  inquiry_message TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'inquired',
  confirmed_at TIMESTAMP NULL DEFAULT NULL,
  confirmed_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY client_package_occurrence (client_id, package_id, occurrence_no),
  KEY parlor_created (parlor_id, created_at),
  FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
  FOREIGN KEY (parlor_id) REFERENCES funeral_parlors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS package_feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT NOT NULL,
  rating DECIMAL(4,2) NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_review_per_experience (inquiry_id),
  CONSTRAINT valid_package_rating CHECK (rating BETWEEN 1 AND 5),
  FOREIGN KEY (inquiry_id) REFERENCES service_inquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS package_feedback_answers (
  feedback_id INT NOT NULL,
  criterion_key VARCHAR(40) NOT NULL,
  rating TINYINT NOT NULL,
  PRIMARY KEY (feedback_id, criterion_key),
  CONSTRAINT valid_questionnaire_rating CHECK (rating BETWEEN 1 AND 5),
  FOREIGN KEY (feedback_id) REFERENCES package_feedback(id) ON DELETE CASCADE
) ENGINE=InnoDB;
