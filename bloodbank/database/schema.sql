-- ============================================================
-- BLOOD BANK MANAGEMENT SYSTEM - DATABASE SCHEMA
-- Engine: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS blood_bank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blood_bank;

-- ------------------------------------------------------------
-- 1. BLOOD GROUPS
-- ------------------------------------------------------------
CREATE TABLE blood_groups (
    group_id INT PRIMARY KEY AUTO_INCREMENT,
    blood_type VARCHAR(5) NOT NULL UNIQUE
);

INSERT INTO blood_groups (blood_type) VALUES
('A+'), ('A-'), ('B+'), ('B-'), ('AB+'), ('AB-'), ('O+'), ('O-');

-- ------------------------------------------------------------
-- 2. USERS (authentication for all three roles)
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','hospital','donor') NOT NULL,
    linked_id INT NULL,          -- points to donors.donor_id or hospitals.hospital_id
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
);

-- ------------------------------------------------------------
-- 3. DONORS
-- ------------------------------------------------------------
CREATE TABLE donors (
    donor_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    gender VARCHAR(20),
    group_id INT NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100),
    address VARCHAR(255),
    last_donation_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES blood_groups(group_id),
    INDEX idx_donors_group (group_id),
    INDEX idx_donors_phone (phone)
);

-- ------------------------------------------------------------
-- 4. HOSPITALS
-- ------------------------------------------------------------
CREATE TABLE hospitals (
    hospital_id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_name VARCHAR(150) NOT NULL,
    address VARCHAR(255),
    contact_no VARCHAR(15),
    email VARCHAR(100),
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 5. BLOOD STOCK
-- ------------------------------------------------------------
CREATE TABLE blood_stock (
    stock_id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    available_units INT NOT NULL DEFAULT 0,
    collection_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    FOREIGN KEY (group_id) REFERENCES blood_groups(group_id),
    INDEX idx_stock_group (group_id),
    INDEX idx_stock_expiry (expiry_date),
    CHECK (available_units >= 0)
);

-- ------------------------------------------------------------
-- 6. DONATIONS
-- ------------------------------------------------------------
CREATE TABLE donations (
    donation_id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    donation_date DATE NOT NULL,
    units INT NOT NULL DEFAULT 1,
    collection_location VARCHAR(150),
    notes VARCHAR(255),
    FOREIGN KEY (donor_id) REFERENCES donors(donor_id)
);

-- ------------------------------------------------------------
-- 7. BLOOD REQUESTS
-- ------------------------------------------------------------
CREATE TABLE blood_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    group_id INT NOT NULL,
    units_required INT NOT NULL,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    priority ENUM('Normal','Urgent','Emergency') DEFAULT 'Normal',
    status ENUM('Pending','Approved','Rejected','Fulfilled','Cancelled') DEFAULT 'Pending',
    remarks VARCHAR(255),
    FOREIGN KEY (hospital_id) REFERENCES hospitals(hospital_id),
    FOREIGN KEY (group_id) REFERENCES blood_groups(group_id),
    INDEX idx_req_hospital (hospital_id),
    INDEX idx_req_group (group_id),
    INDEX idx_req_status (status)
);

-- ------------------------------------------------------------
-- 8. NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    role_target ENUM('admin','hospital','donor') NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 9. AUDIT LOGS
-- ------------------------------------------------------------
CREATE TABLE audit_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(150) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Hospitals
INSERT INTO hospitals (hospital_name, address, contact_no, email, status) VALUES
('City General Hospital', '12 MG Road, Pune', '9822011111', 'contact@citygeneral.in', 'Active'),
('Sunrise Multispeciality', '45 Baner Road, Pune', '9822022222', 'info@sunrise.in', 'Active'),
('Apex Care Hospital', '9 FC Road, Pune', '9822033333', 'admin@apexcare.in', 'Active');

-- Donors
INSERT INTO donors (name, age, gender, group_id, phone, email, address, last_donation_date) VALUES
('Rahul Kumar', 28, 'Male', 7, '9876543210', 'rahul.k@example.com', 'Kothrud, Pune', '2026-09-13'),
('Priya Sharma', 24, 'Female', 3, '9876543211', 'priya.s@example.com', 'Wakad, Pune', '2026-08-02'),
('Aditya Rao', 32, 'Male', 1, '9876543212', 'aditya.r@example.com', 'Hadapsar, Pune', '2026-07-20'),
('Sneha Patil', 27, 'Female', 8, '9876543213', 'sneha.p@example.com', 'Aundh, Pune', '2026-09-01'),
('Vikram Singh', 35, 'Male', 5, '9876543214', 'vikram.s@example.com', 'Viman Nagar, Pune', NULL),
('Neha Joshi', 22, 'Female', 6, '9876543215', 'neha.j@example.com', 'Baner, Pune', '2026-06-15'),
('Karan Mehta', 30, 'Male', 2, '9876543216', 'karan.m@example.com', 'Kharadi, Pune', '2026-05-10'),
('Ananya Gupta', 26, 'Female', 4, '9876543217', 'ananya.g@example.com', 'Shivaji Nagar, Pune', NULL);

-- Blood stock (mix of good stock, low stock, and near-expiry)
INSERT INTO blood_stock (group_id, available_units, collection_date, expiry_date) VALUES
(7, 25, '2026-09-01', '2026-10-13'),   -- O+
(1, 8,  '2026-08-20', '2026-10-02'),   -- A+
(4, 0,  '2026-07-01', '2026-08-12'),   -- B-  (out of stock / expired)
(5, 12, '2026-09-10', '2026-10-22'),   -- AB+
(8, 6,  '2026-09-05', '2026-10-01'),   -- O-  (expiring soon)
(3, 18, '2026-09-12', '2026-10-24'),   -- B+
(2, 4,  '2026-08-15', '2026-09-30'),   -- A-  (expiring soon)
(6, 9,  '2026-09-08', '2026-10-20');   -- AB-

-- Donations
INSERT INTO donations (donor_id, donation_date, units, collection_location, notes) VALUES
(1, '2026-09-13', 1, 'Main Camp - Pune', 'Routine donation'),
(2, '2026-08-02', 1, 'Wakad Blood Drive', NULL),
(3, '2026-07-20', 1, 'Main Camp - Pune', NULL),
(4, '2026-09-01', 1, 'Aundh Community Center', 'First time donor'),
(6, '2026-06-15', 1, 'Baner Blood Drive', NULL),
(7, '2026-05-10', 1, 'Main Camp - Pune', NULL);

-- Blood requests
INSERT INTO blood_requests (hospital_id, group_id, units_required, request_date, priority, status, remarks) VALUES
(1, 7, 3, '2026-09-25 09:00:00', 'Emergency', 'Pending', 'Accident trauma case'),
(2, 1, 2, '2026-09-24 14:30:00', 'Urgent', 'Pending', 'Scheduled surgery'),
(3, 4, 1, '2026-09-20 11:00:00', 'Normal', 'Rejected', 'Insufficient stock'),
(1, 5, 4, '2026-09-18 08:00:00', 'Normal', 'Approved', 'Elective procedure'),
(2, 8, 2, '2026-09-15 16:00:00', 'Emergency', 'Fulfilled', 'Emergency delivery complication');

-- NOTE: Demo login users are NOT inserted here because passwords must be
-- hashed with PHP's password_hash() at runtime (bcrypt hashes cannot be
-- safely precomputed outside PHP). Run database/seed_users.php once after
-- importing this file to create the 3 demo accounts (see README.md).

-- Notifications
INSERT INTO notifications (role_target, message, is_read) VALUES
('admin', 'New emergency blood request received from City General Hospital.', 0),
('admin', 'O- blood stock is critically low.', 0),
('admin', '4 units of A- blood expire within 7 days.', 0);
