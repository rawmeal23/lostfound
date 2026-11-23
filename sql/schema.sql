-- CHANGE: Define the database tables, including separate lost/found report dates.
CREATE DATABASE IF NOT EXISTS lostfound
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lostfound;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_id VARCHAR(30) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','teacher','staff','admin') NOT NULL DEFAULT 'student',
  contact_no VARCHAR(20) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_id INT NOT NULL,
  location_id INT NOT NULL,
  type ENUM('lost','found') NOT NULL,
  title VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  location_note VARCHAR(150) NULL,
  date_lost DATE NULL,
  date_found DATE NULL,
  kept_at ENUM('with_finder','at_office') NULL,
  photo_path VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected','claimed','returned','archived') NOT NULL DEFAULT 'pending',
  returned_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT chk_item_report_date CHECK (
    (type = 'lost' AND date_lost IS NOT NULL AND date_found IS NULL)
    OR (type = 'found' AND date_found IS NOT NULL AND date_lost IS NULL)
  ),
  INDEX idx_status_type (status, type),
  INDEX idx_date_lost (date_lost),
  INDEX idx_date_found (date_found)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE claims (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  claimant_id INT NOT NULL,
  proof_details TEXT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (claimant_id) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id),
  INDEX idx_claim_item (item_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CHANGE: Preserve report, claim, outcome, and return events as an append-only history.
CREATE TABLE item_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  claim_id INT NULL,
  actor_user_id INT NULL,
  event_type ENUM(
    'report_created',
    'item_approved',
    'item_rejected',
    'claim_submitted',
    'claim_approved',
    'claim_rejected',
    'item_returned',
    'outcome_found',
    'outcome_never_found',
    'item_archived'
  ) NOT NULL,
  claim_date DATETIME NULL,
  details VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (claim_id) REFERENCES claims(id),
  FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_item_logs_item_created (item_id, created_at),
  INDEX idx_item_logs_claim (claim_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
