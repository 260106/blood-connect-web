-- ============================================
-- BloodConnect Database Setup (InfinityFree Safe Version)
-- ============================================

-- ================= USERS TABLE =================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    city VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================= REQUESTS TABLE =================

CREATE TABLE requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blood_group VARCHAR(5) NOT NULL,
    city VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    hospital_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ================= DEFAULT ADMIN =================

INSERT INTO users 
(name, email, password, blood_group, city, role)
VALUES 
(
    'Admin',
    'admin@bloodconnect.com',
    '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL536lRi',
    'O+',
    'Admin City',
    'admin'
);