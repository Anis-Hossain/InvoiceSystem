-- =====================================================
-- Invoice Management System - Database Setup
-- Import this file via phpMyAdmin or MySQL CLI
-- =====================================================

CREATE DATABASE IF NOT EXISTS invoice_management;
USE invoice_management;

-- -------------------------------------------------------
-- Clients Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(30),
    company VARCHAR(150),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- Invoices Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    discount DECIMAL(12,2) DEFAULT 0.00,
    total DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- Invoice Items Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) DEFAULT 0.00,
    total DECIMAL(12,2) DEFAULT 0.00,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- Payments Table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    method ENUM('cash','bank_transfer','credit_card','cheque','other') DEFAULT 'cash',
    reference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- Users Table  (role: 'admin' | 'company')
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,          -- bcrypt hash
    role ENUM('admin','company') DEFAULT 'company',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- User ↔ Client pivot  (many-to-many)
-- A company user can be linked to multiple clients.
-- Admins have no rows here (they see everything).
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_clients (
    user_id INT NOT NULL,
    client_id INT NOT NULL,
    PRIMARY KEY (user_id, client_id),
    UNIQUE KEY unique_client (client_id),  -- each client can only belong to ONE user
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- Sample Data
-- -------------------------------------------------------
INSERT INTO clients (name, email, phone, company, address, city, country) VALUES
('John Smith', 'john@example.com', '+1-555-0101', 'Smith Corp', '123 Main St', 'New York', 'USA'),
('Sara Ahmed', 'sara@techbd.com', '+880-1700-000001', 'TechBD Ltd', '45 Gulshan Ave', 'Dhaka', 'Bangladesh'),
('James Lee', 'james@leeco.io', '+44-20-1234-5678', 'Lee Co.', '10 Baker Street', 'London', 'UK');

INSERT INTO invoices (invoice_number, client_id, issue_date, due_date, status, subtotal, tax_rate, tax_amount, discount, total, notes) VALUES
('INV-2024-001', 1, '2024-11-01', '2024-11-15', 'paid', 1500.00, 10.00, 150.00, 0.00, 1650.00, 'First project milestone'),
('INV-2024-002', 2, '2024-11-10', '2024-11-25', 'sent', 2200.00, 5.00, 110.00, 100.00, 2210.00, 'Website redesign'),
('INV-2024-003', 3, '2024-10-01', '2024-10-15', 'overdue', 800.00, 0.00, 0.00, 0.00, 800.00, 'Consulting services');

INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES
(1, 'Web Development - Phase 1', 1, 1000.00, 1000.00),
(1, 'UI/UX Design', 2, 250.00, 500.00),
(2, 'Website Redesign', 1, 1500.00, 1500.00),
(2, 'SEO Optimization', 1, 700.00, 700.00),
(3, 'Consulting - 8 hours', 8, 100.00, 800.00);

INSERT INTO payments (invoice_id, amount, payment_date, method, reference, notes) VALUES
(1, 1650.00, '2024-11-14', 'bank_transfer', 'TXN-001', 'Full payment received');

-- -------------------------------------------------------
-- Users + user_clients are seeded by setup_users.php.
-- Do NOT insert users manually here — bcrypt hashes must be
-- generated by PHP. Run: http://localhost/invoice_system/setup_users.php
-- -------------------------------------------------------
