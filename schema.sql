-- Database migration for StudyDesk - User System Update

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add user_id column to existing desks table
ALTER TABLE desks ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE desks ADD COLUMN is_public BOOLEAN DEFAULT FALSE AFTER description;

-- Add foreign key constraint
ALTER TABLE desks ADD CONSTRAINT fk_desks_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Create desk_shares table for sharing functionality
CREATE TABLE IF NOT EXISTS desk_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    desk_id INT NOT NULL,
    shared_with_user_id INT NOT NULL,
    can_edit BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (desk_id) REFERENCES desks(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share (desk_id, shared_with_user_id)
);

-- Create videos table for storing video links
CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    desk_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (desk_id) REFERENCES desks(id) ON DELETE CASCADE
);

-- Insert a default admin user (you should change the password)
INSERT INTO users (username, email, password_hash) VALUES
('admin', 'admin@studydesk.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: password

-- Update existing desks to be owned by the admin user and make them public
UPDATE desks SET user_id = 1, is_public = TRUE WHERE user_id = 1;

-- Insert sample data (optional additional desks)
INSERT INTO desks (user_id, name, description, is_public) VALUES
(1, 'Computer Science', 'Programming and algorithms', TRUE),
(1, 'History', 'World history and civilizations', TRUE);