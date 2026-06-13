SONICSYNC - MULTIMEDIA MBTI DETECTION SYSTEM
===============================================

PREREQUISITES:
- PHP 8+ with mysqli extension enabled
- MySQL / MariaDB server

SETUP:

1. Database:
   a. Start your MySQL/MariaDB server.
   b. Import database.sql:
      mysql -u root < database.sql

2. Web Server:
   Option A: PHP Built-in Server (recommended for testing)
      php -S 0.0.0.0:8080
      Then open http://localhost:8080

   Option B: XAMPP / Apache
      Copy this folder to C:\xampp\htdocs\sonicsync
      Open http://localhost/sonicsync

3. Demo Flow:
   Dashboard > Upload Multimedia > Analysis > Result > Retrieval

DATABASE TABLES:
- student: Student identity and declared MBTI
- multimedia_asset: Uploaded files with descriptions and tags
- audio_metadata: Extracted audio characteristics (genre, mood, energy)
- recommendation_result: Generated persona and podcast recommendations

RETRIEVAL STRATEGIES:
- ABR: Filter by MBTI, genre, energy, mood, lab group, persona (structured attributes)
- TBR: Keyword search in descriptions, tags, lyrics, scripts (text-based)
- CBR: Filter by genre, energy, mood, file type (content-based)
