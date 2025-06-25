-- Enhanced Media Collection Database Schema
-- Supports detailed categorization for Books, Movies, Comics, and Music

-- Main collection table (core information)
CREATE TABLE collection (
  id INT AUTO_INCREMENT PRIMARY KEY,
  media_type ENUM('movie','book','comic','music') NOT NULL,
  title VARCHAR(500) NOT NULL,
  year VARCHAR(10),
  creator VARCHAR(255),
  identifier VARCHAR(100), -- ISBN, UPC, etc.
  source_id VARCHAR(100),  -- External API ID
  poster_url TEXT,
  description TEXT,
  purchase_date DATE,
  purchase_price DECIMAL(10,2),
  current_value DECIMAL(10,2),
  condition_rating ENUM('mint','near_mint','very_fine','fine','very_good','good','fair','poor'),
  personal_rating TINYINT CHECK (personal_rating >= 1 AND personal_rating <= 10),
  notes TEXT,
  location VARCHAR(255), -- Where physically stored
  loaned_to VARCHAR(255), -- If loaned out
  loan_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_media_type (media_type),
  INDEX idx_title (title),
  INDEX idx_year (year),
  INDEX idx_creator (creator),
  INDEX idx_condition (condition_rating),
  FULLTEXT idx_search (title, creator, description)
);

-- BOOK-specific attributes
CREATE TABLE book_details (
  collection_id INT PRIMARY KEY,
  isbn VARCHAR(20),
  isbn13 VARCHAR(20),
  format ENUM('hardcover','paperback','mass_market','trade_paperback','ebook','audiobook','comic_trade','graphic_novel') NOT NULL,
  genre VARCHAR(255),
  sub_genre VARCHAR(255),
  series_name VARCHAR(255),
  series_number INT,
  publisher VARCHAR(255),
  publication_date DATE,
  page_count INT,
  language VARCHAR(50) DEFAULT 'English',
  edition VARCHAR(100),
  author VARCHAR(255),
  illustrator VARCHAR(255),
  translator VARCHAR(255),
  reading_status ENUM('unread','reading','completed','dnf','reference') DEFAULT 'unread',
  reading_start_date DATE,
  reading_end_date DATE,
  
  FOREIGN KEY (collection_id) REFERENCES collection(id) ON DELETE CASCADE,
  INDEX idx_format (format),
  INDEX idx_genre (genre),
  INDEX idx_series (series_name),
  INDEX idx_author (author),
  INDEX idx_isbn (isbn),
  INDEX idx_reading_status (reading_status)
);

-- MOVIE/TV specific attributes  
CREATE TABLE movie_details (
  collection_id INT PRIMARY KEY,
  format ENUM('vhs','betamax','laserdisc','dvd','blu_ray','4k_uhd','digital','streaming','film_reel') NOT NULL,
  region ENUM('region_free','region_1','region_2','region_3','region_4','region_5','region_6','region_a','region_b','region_c') DEFAULT 'region_1',
  resolution ENUM('480i','480p','720p','1080i','1080p','4k','8k') DEFAULT '1080p',
  aspect_ratio VARCHAR(20),
  media_type_detail ENUM('movie','tv_series','tv_movie','documentary','short','music_video','concert') DEFAULT 'movie',
  director VARCHAR(255),
  studio VARCHAR(255),
  distributor VARCHAR(255),
  runtime_minutes INT,
  mpaa_rating ENUM('G','PG','PG-13','R','NC-17','NR','UR') DEFAULT 'NR',
  certification_country VARCHAR(10) DEFAULT 'US',
  original_language VARCHAR(50) DEFAULT 'English',
  subtitle_languages TEXT, -- JSON array of languages
  audio_languages TEXT,    -- JSON array of languages
  special_features TEXT,   -- Description of extras
  box_set_name VARCHAR(255),
  disc_count TINYINT DEFAULT 1,
  case_type ENUM('standard','steelbook','digipak','slip_cover','box_set','jewel_case','other'),
  watched_status ENUM('unwatched','watching','completed','abandoned') DEFAULT 'unwatched',
  last_watched_date DATE,
  watch_count INT DEFAULT 0,
  
  FOREIGN KEY (collection_id) REFERENCES collection(id) ON DELETE CASCADE,
  INDEX idx_format (format),
  INDEX idx_director (director),
  INDEX idx_studio (studio),
  INDEX idx_rating (mpaa_rating),
  INDEX idx_resolution (resolution),
  INDEX idx_media_type (media_type_detail)
);

-- COMIC specific attributes
CREATE TABLE comic_details (
  collection_id INT PRIMARY KEY,
  publisher VARCHAR(255),
  imprint VARCHAR(255), -- Marvel Knights, DC Black Label, etc.
  series_name VARCHAR(255),
  volume_number INT,
  issue_number VARCHAR(20), -- Can be fractional like 12.1
  variant_type ENUM('regular','variant','sketch','virgin','foil','glow','retailer_exclusive','convention_exclusive','error'),
  variant_description VARCHAR(500),
  cover_artist VARCHAR(255),
  writer VARCHAR(255),
  penciler VARCHAR(255),
  inker VARCHAR(255),
  colorist VARCHAR(255),
  letterer VARCHAR(255),
  format ENUM('single_issue','trade_paperback','hardcover','omnibus','absolute','deluxe','treasury','digest','graphic_novel','annual','special','one_shot') NOT NULL,
  page_count INT,
  cover_price DECIMAL(6,2),
  publication_date DATE,
  story_arc VARCHAR(255),
  key_issue_notes TEXT, -- First appearance, death, etc.
  graded BOOLEAN DEFAULT FALSE,
  grade_company ENUM('cgc','pgx','cbcs','sgc'),
  grade_score DECIMAL(3,1), -- 9.8, 10.0, etc.
  grade_notes TEXT,
  bag_and_board BOOLEAN DEFAULT TRUE,
  signed BOOLEAN DEFAULT FALSE,
  signature_info TEXT,
  read_status ENUM('unread','reading','completed','reference') DEFAULT 'unread',
  
  FOREIGN KEY (collection_id) REFERENCES collection(id) ON DELETE CASCADE,
  INDEX idx_publisher (publisher),
  INDEX idx_series (series_name),
  INDEX idx_issue (issue_number),
  INDEX idx_variant (variant_type),
  INDEX idx_writer (writer),
  INDEX idx_graded (graded),
  INDEX idx_grade_score (grade_score)
);

-- MUSIC specific attributes
CREATE TABLE music_details (
  collection_id INT PRIMARY KEY,
  format ENUM('cd','vinyl_lp','vinyl_45','vinyl_78','cassette','8_track','digital','minidisc','reel_to_reel','dcc','dat') NOT NULL,
  album_type ENUM('studio','live','compilation','soundtrack','ep','single','box_set','bootleg','demo','remix') DEFAULT 'studio',
  record_label VARCHAR(255),
  catalog_number VARCHAR(100),
  artist VARCHAR(255),
  band_name VARCHAR(255),
  featured_artists TEXT, -- JSON array
  producer VARCHAR(255),
  genre VARCHAR(255),
  sub_genre VARCHAR(255),
  release_date DATE,
  recording_date DATE,
  track_count INT,
  total_runtime_seconds INT,
  vinyl_speed ENUM('33','45','78') NULL, -- Only for vinyl
  vinyl_size ENUM('7','10','12') NULL,   -- Only for vinyl inches
  cd_type ENUM('standard','enhanced','hdcd','sacd','dvd_audio','blu_ray_audio') NULL,
  digital_format ENUM('mp3','flac','wav','aac','ogg','alac') NULL,
  bitrate VARCHAR(20) NULL, -- For digital formats
  remaster BOOLEAN DEFAULT FALSE,
  remaster_year INT NULL,
  limited_edition BOOLEAN DEFAULT FALSE,
  pressing_info VARCHAR(255), -- Color vinyl, numbered edition, etc.
  matrix_number VARCHAR(100), -- For vinyl collectors
  listening_status ENUM('unheard','listening','completed') DEFAULT 'unheard',
  
  FOREIGN KEY (collection_id) REFERENCES collection(id) ON DELETE CASCADE,
  INDEX idx_format (format),
  INDEX idx_artist (artist),
  INDEX idx_label (record_label),
  INDEX idx_genre (genre),
  INDEX idx_album_type (album_type)
);

-- Wishlist table for items you want to acquire
CREATE TABLE wishlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  media_type ENUM('movie','book','comic','music') NOT NULL,
  title VARCHAR(500) NOT NULL,
  creator VARCHAR(255),
  priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
  max_price DECIMAL(10,2),
  notes TEXT,
  date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_media_type (media_type),
  INDEX idx_priority (priority)
);

-- Categories/Tags system for flexible classification
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) UNIQUE NOT NULL,
  parent_id INT NULL,
  media_type ENUM('movie','book','comic','music','all') DEFAULT 'all',
  description TEXT,
  
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,
  INDEX idx_parent (parent_id),
  INDEX idx_media_type (media_type)
);

-- Many-to-many relationship for item categories
CREATE TABLE collection_categories (
  collection_id INT,
  category_id INT,
  PRIMARY KEY (collection_id, category_id),
  FOREIGN KEY (collection_id) REFERENCES collection(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Loan tracking
CREATE TABLE loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_id INT NOT NULL,
  borrower_name VARCHAR(255) NOT NULL,
  borrower_contact VARCHAR(255),
  loan_date DATE NOT NULL,
  expected_return_date DATE,
  actual_return_date DATE,
  notes TEXT,
  
  FOREIGN KEY (collection_id) REFERENCES collection(id) ON DELETE CASCADE,
  INDEX idx_collection (collection_id),
  INDEX idx_borrower (borrower_name),
  INDEX idx_loan_date (loan_date)
);

-- User management (enhanced)
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(255),
  role ENUM('admin','viewer','editor') DEFAULT 'admin',
  last_login TIMESTAMP NULL,
  login_attempts INT DEFAULT 0,
  locked_until TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_username (username),
  INDEX idx_email (email)
);

-- Insert some default categories
INSERT INTO categories (name, media_type, description) VALUES
-- Book genres
('Fiction', 'book', 'Fictional literature'),
('Non-Fiction', 'book', 'Factual books'),
('Science Fiction', 'book', 'Science fiction genre'),
('Fantasy', 'book', 'Fantasy genre'),
('Mystery', 'book', 'Mystery and detective fiction'),
('Romance', 'book', 'Romance novels'),
('Horror', 'book', 'Horror fiction'),
('Biography', 'book', 'Biographical works'),
('History', 'book', 'Historical books'),
('How-To', 'book', 'Instructional and guide books'),
('Reference', 'book', 'Reference materials'),
('Textbook', 'book', 'Educational textbooks'),

-- Movie genres
('Action', 'movie', 'Action films'),
('Comedy', 'movie', 'Comedy films'),
('Drama', 'movie', 'Drama films'),
('Horror', 'movie', 'Horror films'),
('Sci-Fi', 'movie', 'Science fiction films'),
('Thriller', 'movie', 'Thriller films'),
('Documentary', 'movie', 'Documentary films'),
('Animation', 'movie', 'Animated films'),
('Foreign', 'movie', 'Foreign language films'),
('Classic', 'movie', 'Classic films'),
('Criterion', 'movie', 'Criterion Collection'),

-- Comic publishers
('Marvel', 'comic', 'Marvel Comics'),
('DC', 'comic', 'DC Comics'),
('Image', 'comic', 'Image Comics'),
('Dark Horse', 'comic', 'Dark Horse Comics'),
('IDW', 'comic', 'IDW Publishing'),
('Vertigo', 'comic', 'Vertigo Comics'),
('Independent', 'comic', 'Independent publishers'),

-- Music genres
('Rock', 'music', 'Rock music'),
('Pop', 'music', 'Pop music'),
('Jazz', 'music', 'Jazz music'),
('Classical', 'music', 'Classical music'),
('Hip-Hop', 'music', 'Hip-Hop music'),
('Electronic', 'music', 'Electronic music'),
('Country', 'music', 'Country music'),
('Blues', 'music', 'Blues music'),
('Folk', 'music', 'Folk music'),
('Metal', 'music', 'Metal music'),
('Punk', 'music', 'Punk music'),
('Soundtrack', 'music', 'Movie and TV soundtracks');

-- Views for easier querying
CREATE VIEW collection_full AS
SELECT 
  c.*,
  CASE 
    WHEN c.media_type = 'book' THEN b.format
    WHEN c.media_type = 'movie' THEN m.format  
    WHEN c.media_type = 'comic' THEN cm.format
    WHEN c.media_type = 'music' THEN mu.format
  END as format,
  CASE 
    WHEN c.media_type = 'book' THEN b.genre
    WHEN c.media_type = 'movie' THEN CONCAT(m.media_type_detail, ' - ', m.mpaa_rating)
    WHEN c.media_type = 'comic' THEN cm.publisher
    WHEN c.media_type = 'music' THEN mu.genre
  END as additional_info
FROM collection c
LEFT JOIN book_details b ON c.id = b.collection_id
LEFT JOIN movie_details m ON c.id = m.collection_id  
LEFT JOIN comic_details cm ON c.id = cm.collection_id
LEFT JOIN music_details mu ON c.id = mu.collection_id;