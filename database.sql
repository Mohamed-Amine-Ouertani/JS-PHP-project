-- Library Management System Database
-- Run this SQL to set up the database

CREATE DATABASE IF NOT EXISTS library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'suspended') DEFAULT 'active',
    member_id VARCHAR(20) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Book categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

-- Books table
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(150) NOT NULL,
    isbn VARCHAR(20),
    category_id INT,
    description TEXT,
    cover_image VARCHAR(255) DEFAULT 'default_cover.jpg',
    publisher VARCHAR(150),
    publication_year INT,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Reservations table
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    returned_at TIMESTAMP NULL,
    status ENUM('pending', 'active', 'returned', 'cancelled', 'overdue') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- Insert default admin account (password: Admin@123)
INSERT INTO users (full_name, email, password, role, member_id)
VALUES ('Amine', 'admin@library.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ADMIN-001');

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Fiction', 'Novels, short stories, and other fictional works'),
('Science', 'Scientific literature, research, and discoveries'),
('History', 'Historical accounts and biographies'),
('Technology', 'Computer science, engineering, and tech'),
('Philosophy', 'Philosophical works and critical thinking'),
('Arts & Literature', 'Poetry, drama, and literary criticism'),
('Self-Help', 'Personal development and growth'),
('Children', 'Books for young readers');

-- Insert sample books
INSERT INTO books (title, author, isbn, category_id, description, total_copies, available_copies, publisher, publication_year) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0743273565', 1, 'A story of decadence and excess, Gatsby explores the darker aspects of the American Dream.', 3, 3, 'Scribner', 1925),
('To Kill a Mockingbird', 'Harper Lee', '978-0061935466', 1, 'A gripping tale of racial injustice and childhood innocence in the American South.', 2, 2, 'HarperCollins', 1960),
('A Brief History of Time', 'Stephen Hawking', '978-0553380163', 2, 'A landmark volume in science writing by one of the great minds of our time.', 2, 2, 'Bantam Books', 1988),
('Sapiens', 'Yuval Noah Harari', '978-0062316097', 3, 'A brief history of humankind from the Stone Age to the twenty-first century.', 4, 4, 'Harper', 2011),
('Clean Code', 'Robert C. Martin', '978-0132350884', 4, 'A handbook of agile software craftsmanship.', 3, 3, 'Prentice Hall', 2008),
('1984', 'George Orwell', '978-0451524935', 1, 'A dystopian social science fiction novel and cautionary tale.', 3, 3, 'Signet Classic', 1949);
