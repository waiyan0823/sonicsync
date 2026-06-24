CREATE TABLE IF NOT EXISTS image_prompt_bank (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    category VARCHAR(80) NOT NULL,
    emotion_tag VARCHAR(80) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE
);

INSERT INTO image_prompt_bank (image_path, category, emotion_tag, active) VALUES
('assets/img/tbr/bench-alone.jpg', 'Solitude', 'Reflection', TRUE),
('assets/img/tbr/crowded-station.jpg', 'Social', 'Anxiety', TRUE),
('assets/img/tbr/forest-path.jpg', 'Nature', 'Peace', TRUE),
('assets/img/tbr/rainy-window.jpg', 'Urban', 'Melancholy', TRUE),
('assets/img/tbr/empty-playground.jpg', 'Abstract', 'Nostalgia', TRUE);
