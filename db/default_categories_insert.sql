
-- Insert default categories (no duplicates due to composite UNIQUE constraint)
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

-- Comic genres
('Superhero', 'comic', 'Superhero comics'),
('Manga', 'comic', 'Japanese manga'),
('Graphic Novel', 'comic', 'Narrative graphic works'),
('Fantasy', 'comic', 'Fantasy comics'),
('Horror', 'comic', 'Horror comics'),

-- Music genres
('Rock', 'music', 'Rock music'),
('Pop', 'music', 'Popular music'),
('Jazz', 'music', 'Jazz music'),
('Classical', 'music', 'Classical compositions'),
('Hip-Hop', 'music', 'Hip-Hop and Rap'),
('Electronic', 'music', 'Electronic music'),
('Country', 'music', 'Country music'),
('Soundtrack', 'music', 'Film and game soundtracks'),
('Metal', 'music', 'Heavy metal music');
