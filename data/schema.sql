-- Lost & Found Tracker — Database Schema
-- INF2006 Team Project 1

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    reported_by INT NOT NULL,
    type ENUM('lost', 'found') NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    private_detail VARCHAR(255) NULL,
    location VARCHAR(100) NOT NULL,
    item_date DATE NOT NULL,
    status ENUM('open', 'matched', 'claimed', 'returned', 'discarded') DEFAULT 'open',
    photo_url VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reported_by) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS claims (
    claim_id INT AUTO_INCREMENT PRIMARY KEY,
    lost_item_id INT NOT NULL,
    found_item_id INT NOT NULL,
    claimed_by INT NOT NULL,
    verification_answer VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lost_item_id) REFERENCES items(item_id),
    FOREIGN KEY (found_item_id) REFERENCES items(item_id),
    FOREIGN KEY (claimed_by) REFERENCES users(user_id)
);

-- Default admin account for testing (login: admin@sit.singaporetech.edu.sg / admin123)
-- password is a bcrypt hash of "admin123" — for demo/testing only, never commit real credentials
-- CHANGE this password before any real/public deployment
INSERT INTO users (name, email, password_hash, role) VALUES
('Security Admin', 'admin@sit.singaporetech.edu.sg', '$2b$10$Aar6/EVoxtlPMIZd21OYU./hdbc1j/gxYT7.jrpCJGfnI8gjDQDyu', 'admin');
