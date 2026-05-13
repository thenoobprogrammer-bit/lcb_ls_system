CREATE DATABASE IF NOT EXISTS lcb_lights_sound CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lcb_lights_sound;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    account_type ENUM('administrator', 'employee', 'rental') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    address TEXT NULL,
    contact_number VARCHAR(30) NULL,
    employee_role ENUM('Sound Technician', 'Light Technician', 'Crew') NULL,
    profile_image VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE inventory_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    allows_wire_length TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    category_id INT NULL,
    category VARCHAR(100) NOT NULL,
    wire_length_label VARCHAR(30) NULL,
    description TEXT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('Available', 'In Use', 'Maintenance', 'Damaged') NOT NULL DEFAULT 'Available',
    image_filename VARCHAR(255) NULL,
    last_maintenance_date DATE NULL,
    maintenance_notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES inventory_categories(id) ON DELETE SET NULL
);

CREATE TABLE payrolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_period_start DATE NOT NULL,
    payroll_period_end DATE NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
    allowance DECIMAL(10,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
    net_pay DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    equipment_ids TEXT NULL,
    package_items_json LONGTEXT NULL,
    equipment_summary TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    availability_status ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available',
    approval_status ENUM('Pending', 'Approved', 'Denied') NOT NULL DEFAULT 'Pending',
    submitted_by INT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_user_id INT NOT NULL,
    package_id INT NULL,
    event_name VARCHAR(150) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    address TEXT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    contact_number VARCHAR(30) NOT NULL,
    notes TEXT NULL,
    attachment_filename VARCHAR(255) NULL,
    event_status ENUM('Pending', 'Approved', 'Denied', 'Completed') NOT NULL DEFAULT 'Pending',
    is_overtime TINYINT(1) NOT NULL DEFAULT 0,
    overtime_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
    overtime_fee_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    overtime_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rental_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    downpayment DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    remaining_balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('Pending', 'Partial', 'Paid') NOT NULL DEFAULT 'Pending',
    invoice_number VARCHAR(60) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NULL,
    sender_name VARCHAR(150) NOT NULL,
    sender_email VARCHAR(150) NULL,
    subject VARCHAR(150) NOT NULL,
    message_body TEXT NOT NULL,
    admin_reply TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_name VARCHAR(150) NOT NULL,
    module_name VARCHAR(150) NOT NULL,
    details TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE event_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    employee_id INT NOT NULL,
    assigned_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_employee (event_id, employee_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO users (username, password_hash, account_type, full_name, address, contact_number, employee_role)
VALUES
('admin', '$2y$10$rfvVjlwmq5d8czxjTj4AbuZUtGFd6eCzVGOnwGTrSYm1g7mM75l1y', 'administrator', 'System Administrator', 'Main Office', '09171234567', NULL),
('soundtech', '$2y$10$rfvVjlwmq5d8czxjTj4AbuZUtGFd6eCzVGOnwGTrSYm1g7mM75l1y', 'employee', 'Marco Dela Cruz', 'Cebu City', '09170001111', 'Sound Technician'),
('rentaluser', '$2y$10$rfvVjlwmq5d8czxjTj4AbuZUtGFd6eCzVGOnwGTrSYm1g7mM75l1y', 'rental', 'Andrea Santos', 'Mandaue City', '09179990000', NULL);

INSERT INTO inventory_categories (category_name, allows_wire_length)
VALUES
('Speaker', 0),
('Mixer', 0),
('Lights', 0),
('Lights Controller', 0),
('Wires', 1);

INSERT INTO inventory_items (item_name, category_id, category, description, quantity, unit_price, status, last_maintenance_date, maintenance_notes, created_by)
VALUES
( 'Line Array Speaker', 1, 'Speaker', 'Dual 12-inch professional speaker set', 8, 8500.00, 'Available', '2026-04-10', 'Cleaned and tested', 1),
( 'Moving Head Light', 3, 'Lights', 'RGBW moving head fixture', 10, 6200.00, 'In Use', '2026-04-05', 'Used in April event', 1),
( 'Wireless Microphone', 1, 'Speaker', 'Handheld microphone with receiver', 15, 2800.00, 'Available', '2026-04-12', 'Battery replaced', 1);

INSERT INTO packages (package_name, description, equipment_ids, equipment_summary, price, availability_status, approval_status, submitted_by, approved_by)
VALUES
('Basic Party Package', 'Suitable for birthdays and small gatherings', '1,3', 'Line Array Speaker x2, Wireless Microphone x2', 12000.00, 'Available', 'Approved', 1, 1),
('Premium Concert Package', 'Full setup for large stage events', '1,2,3', 'Line Array Speaker x4, Moving Head Light x6, Wireless Microphone x4', 38000.00, 'Available', 'Approved', 1, 1);

INSERT INTO events (rental_user_id, package_id, event_name, event_type, event_date, start_time, end_time, address, full_name, contact_number, notes, event_status)
VALUES
(3, 1, 'Barangay Fiesta Night', 'Community Event', '2026-04-28', '18:00:00', '23:00:00', 'Barangay Hall Grounds', 'Andrea Santos', '09179990000', 'Need setup by 3 PM', 'Pending');

INSERT INTO payments (event_id, total_cost, downpayment, amount_paid, remaining_balance, payment_status, invoice_number)
VALUES
(1, 12000.00, 3000.00, 3000.00, 9000.00, 'Partial', 'INV-2026-0001');
