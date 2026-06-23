CREATE DATABASE IF NOT EXISTS gw08;
USE gw08;

DROP TABLE IF EXISTS recommendation_result;
DROP TABLE IF EXISTS audio_metadata;
DROP TABLE IF EXISTS multimedia_asset;
DROP TABLE IF EXISTS student;

CREATE TABLE student (
    student_id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    matric_no VARCHAR(20) DEFAULT '',
    phone_no VARCHAR(20) DEFAULT '',
    lab_group VARCHAR(50) DEFAULT '',
    mbti_type VARCHAR(10) DEFAULT '',
    life_value TEXT,
    preferred_podcast_tone VARCHAR(50) DEFAULT ''
);

CREATE TABLE multimedia_asset (
    asset_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(30) NOT NULL,
    file_size INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    description TEXT,
    tags VARCHAR(500) DEFAULT '',
    media_category VARCHAR(30) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student(student_id)
);

CREATE TABLE audio_metadata (
    audio_id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    song_title VARCHAR(255) DEFAULT '',
    artist_or_creator VARCHAR(255) DEFAULT '',
    duration INT DEFAULT 0,
    energy_level VARCHAR(20) DEFAULT '',
    mood_type VARCHAR(50) DEFAULT '',
    lyrics_keywords TEXT,
    genre VARCHAR(50) DEFAULT '',
    tempo_bpm DECIMAL(8,2) DEFAULT NULL,
    rms_energy DECIMAL(10,6) DEFAULT NULL,
    spectral_centroid DECIMAL(10,2) DEFAULT NULL,
    zero_crossing_rate DECIMAL(10,6) DEFAULT NULL,
    tempo_category VARCHAR(20) DEFAULT '',
    personality_tendency VARCHAR(100) DEFAULT '',
    audio_features_json TEXT,
    analysis_status VARCHAR(255) DEFAULT '',
    analyzed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (asset_id) REFERENCES multimedia_asset(asset_id)
);

CREATE TABLE recommendation_result (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    audio_id INT DEFAULT NULL,
    predicted_mbti VARCHAR(10) DEFAULT '',
    generated_persona VARCHAR(100) DEFAULT '',
    podcast_title VARCHAR(255) DEFAULT '',
    podcast_script TEXT,
    recommended_song VARCHAR(255) DEFAULT '',
    recommended_podcast VARCHAR(255) DEFAULT '',
    generated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student(student_id),
    FOREIGN KEY (audio_id) REFERENCES audio_metadata(audio_id)
);

INSERT INTO student (student_id, name, matric_no, phone_no, lab_group, mbti_type, life_value, preferred_podcast_tone)
VALUES
('B032410001', 'Aisyah Abdullah', 'B032410001', '012-3456789', 'GW08', 'INFP', 'Creativity, Empathy', 'Inspirational'),
('B032410002', 'Raju Kumar', 'B032410002', '012-9876543', 'GW08', 'ENFP', 'Adventure, Connection', 'Energetic'),
('B032410003', 'Siti Nurhaliza', 'B032410003', '011-2233445', 'GW08', 'INTJ', 'Knowledge, Independence', 'Educational');

INSERT INTO multimedia_asset (student_id, file_name, file_type, file_size, file_path, description, tags, media_category)
VALUES
('B032410001', 'sunset_reflection.jpg', 'jpg', 204800, 'assets/uploads/images/sunset_reflection.jpg', 'A calm sunset by the beach. The quiet waves make me feel peaceful and reflective about my future.', 'calm, peaceful, reflection', 'Image'),
('B032410002', 'concert_crowd.mp3', 'mp3', 5120000, 'assets/uploads/audio/concert_crowd.mp3', 'High energy concert with cheering crowd. The excitement is contagious!', 'excited, crowd, energy', 'Audio'),
('B032410003', 'research_paper.pdf', 'pdf', 1024000, 'assets/uploads/pdf/research_paper.pdf', 'Analysis of machine learning algorithms for personality detection.', 'deep, analysis, learning', 'PDF');

INSERT INTO audio_metadata (asset_id, song_title, artist_or_creator, duration, energy_level, mood_type, lyrics_keywords, genre)
VALUES
(2, 'Electric Dream', 'DJ Phoenix', 234, 'High', 'Energetic', 'party, crowd, rise, together', 'EDM');

INSERT INTO recommendation_result (student_id, audio_id, predicted_mbti, generated_persona, podcast_title, podcast_script, recommended_song, recommended_podcast)
VALUES
('B032410001', NULL, 'INFP', 'Authentic', 'The Quiet Dreamer', 'In this episode, we explore the mind of an INFP. Aisyah''s calm and reflective nature shines through her description of a peaceful sunset. Her declared INFP personality aligns with the system''s analysis, confirming an Authentic profile.', 'Sunset Lullaby', 'The Introvert Hour'),
('B032410002', 1, 'ENFP', 'Creative Deviation', 'The Electric Spirit', 'Raju declared ENFP but his uploaded EDM track suggests a bold, high-energy personality that leans toward creative deviation. His love for adventure and social connection is reflected in both his declared type and the system''s analysis.', 'Neon Nights', 'Extrovert Unleashed');
