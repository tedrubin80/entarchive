CREATE TABLE collection (
  id INT AUTO_INCREMENT PRIMARY KEY,
  media_type ENUM('movie','book','comic','music') NOT NULL,
  title VARCHAR(255),
  year VARCHAR(10),
  creator VARCHAR(255),
  identifier VARCHAR(50),
  source_id VARCHAR(50),
  poster_url TEXT,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL
);
