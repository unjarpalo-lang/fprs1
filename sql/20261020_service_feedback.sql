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
