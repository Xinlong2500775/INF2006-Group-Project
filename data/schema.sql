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
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lost_item_id) REFERENCES items(item_id),
    FOREIGN KEY (found_item_id) REFERENCES items(item_id),
    FOREIGN KEY (claimed_by) REFERENCES users(user_id)
);

-- A default admin account for testing (CHANGE this password before any real deployment)
-- password below is a bcrypt hash of "admin123" — for demo/testing only, never commit real credentials
