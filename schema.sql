-- Database schema for StudyDesk

CREATE TABLE IF NOT EXISTS desks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    desk_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (desk_id) REFERENCES desks(id) ON DELETE CASCADE
);

-- Insert sample data (optional)
INSERT INTO desks (name, description) VALUES
('Mathematics', 'Math study materials and tutorials'),
('Physics', 'Physics concepts and problem solving'),
('Chemistry', 'Chemistry lessons and experiments');

INSERT INTO videos (desk_id, title, url) VALUES
(1, 'Introduction to Algebra', 'https://www.youtube.com/watch?v=example1'),
(1, 'Calculus Basics', 'https://www.youtube.com/watch?v=example2'),
(2, 'Newton\'s Laws', 'https://www.youtube.com/watch?v=example3'),
(3, 'Periodic Table', 'https://www.youtube.com/watch?v=example4');