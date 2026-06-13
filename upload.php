<?php
include 'includes/db.php';

$message = '';
$error = '';
$students = $conn->query("SELECT student_id, name, mbti_type FROM student ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $new_name = trim($_POST['new_name'] ?? '');
    $new_matric = trim($_POST['new_matric'] ?? '');
    $new_mbti = trim($_POST['new_mbti'] ?? '');

    if ($student_id === '' && $new_name === '') {
        $error = 'Select an existing student or enter a new student name.';
    } else {
        if ($student_id === '') {
            $student_id = 'B' . date('YmdHis');
            $stmt = $conn->prepare("INSERT INTO student (student_id, name, matric_no, mbti_type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $student_id, $new_name, $new_matric, $new_mbti);
            $stmt->execute();
        }

        $uploaded = false;

        // --- Image Upload (for TBR) ---
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
            $f = $_FILES['image_file'];
            $ext = strtolower(pathinfo(basename($f['name']), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                $desc = trim($_POST['image_description'] ?? '');
                $tags = trim($_POST['image_tags'] ?? '');
                $new_name = time() . '_img_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($f['name']));
                $path = 'assets/uploads/images/' . $new_name;
                if (move_uploaded_file($f['tmp_name'], $path)) {
                    $stmt = $conn->prepare("INSERT INTO multimedia_asset (student_id, file_name, file_type, file_size, file_path, description, tags, media_category) VALUES (?, ?, ?, ?, ?, ?, ?, 'Image')");
                    $stmt->bind_param('sssisss', $student_id, $new_name, $ext, $f['size'], $path, $desc, $tags);
                    $stmt->execute();
                    $uploaded = true;
                }
            }
        }

        // --- Audio Upload (for CBR) ---
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === 0) {
            $f = $_FILES['audio_file'];
            $ext = strtolower(pathinfo(basename($f['name']), PATHINFO_EXTENSION));
            if (in_array($ext, ['mp3','wav','m4a'])) {
                $tags = trim($_POST['audio_tags'] ?? '');
                $song_title = trim($_POST['song_title'] ?? '');
                $artist = trim($_POST['artist'] ?? '');
                $duration = intval($_POST['duration'] ?? 0);
                $genre = trim($_POST['genre'] ?? '');
                $energy = trim($_POST['energy_level'] ?? '');
                $mood = trim($_POST['mood_type'] ?? '');
                $lyrics = trim($_POST['lyrics_keywords'] ?? '');
                $new_name = time() . '_aud_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($f['name']));
                $path = 'assets/uploads/audio/' . $new_name;
                if (move_uploaded_file($f['tmp_name'], $path)) {
                    $stmt = $conn->prepare("INSERT INTO multimedia_asset (student_id, file_name, file_type, file_size, file_path, description, tags, media_category) VALUES (?, ?, ?, ?, ?, ?, ?, 'Audio')");
                    $desc = "Song: $song_title by $artist";
                    $stmt->bind_param('sssisss', $student_id, $new_name, $ext, $f['size'], $path, $desc, $tags);
                    $stmt->execute();
                    $asset_id = $stmt->insert_id;

                    $stmt = $conn->prepare("INSERT INTO audio_metadata (asset_id, song_title, artist_or_creator, duration, energy_level, mood_type, lyrics_keywords, genre) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('ississss', $asset_id, $song_title, $artist, $duration, $energy, $mood, $lyrics, $genre);
                    $stmt->execute();
                    $uploaded = true;
                }
            }
        }

        // --- PDF Upload (for TBR) ---
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
            $f = $_FILES['pdf_file'];
            $ext = strtolower(pathinfo(basename($f['name']), PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $desc = trim($_POST['pdf_description'] ?? '');
                $tags = trim($_POST['pdf_tags'] ?? '');
                $new_name = time() . '_pdf_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($f['name']));
                $path = 'assets/uploads/pdf/' . $new_name;
                if (move_uploaded_file($f['tmp_name'], $path)) {
                    $stmt = $conn->prepare("INSERT INTO multimedia_asset (student_id, file_name, file_type, file_size, file_path, description, tags, media_category) VALUES (?, ?, ?, ?, ?, ?, ?, 'PDF')");
                    $stmt->bind_param('sssisss', $student_id, $new_name, $ext, $f['size'], $path, $desc, $tags);
                    $stmt->execute();
                    $uploaded = true;
                }
            }
        }

        if ($uploaded) {
            $message = 'File(s) uploaded successfully. <a class="alert-link" href="analysis.php?student_id=' . urlencode($student_id) . '">Go to Analysis</a>';
        } elseif (!$error) {
            $error = 'No file was uploaded. Select a file in one of the sections below.';
        }
    }
}
?>
<?php include 'includes/header.php'; ?>
<section class="hero">
    <div>
        <h1>Upload Multimedia</h1>
        <p>Step 2 of 4: Register a student, then upload their files. Each section feeds a different part of the analysis.</p>
    </div>
    <div class="tag">UPLOAD</div>
</section>

<section class="form-card">
    <h2>Student</h2>

    <?php if ($message): ?><div class="alert"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="grid-2">
            <div>
                <label>Select Existing Student</label>
                <select name="student_id">
                    <option value="">-- Register new student --</option>
                    <?php while ($s = $students->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($s['student_id']) ?>">
                            <?= htmlspecialchars($s['student_id'] . ' - ' . $s['name'] . ' (' . $s['mbti_type'] . ')') ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="new-student-box">
            <p class="small-text"><strong>Or register a new student:</strong></p>
            <div class="grid-3">
                <div>
                    <label>Full Name</label>
                    <input type="text" name="new_name" placeholder="Enter student name">
                </div>
                <div>
                    <label>Matric No</label>
                    <input type="text" name="new_matric" placeholder="B0324xxxxx">
                </div>
                <div>
                    <label>Declared MBTI</label>
                    <select name="new_mbti">
                        <option value="">Select MBTI</option>
                        <?php foreach (['INFP','ENFP','INFJ','ENFJ','INTJ','ENTJ','ISFP','ESFP','ISTJ','ESTJ','ISFJ','ESFJ','ISTP','ESTP','INTP','ENTP'] as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <hr style="margin:24px 0;border-color:var(--border)">

        <!-- ====== IMAGE SECTION (TBR) ====== -->
        <div class="analysis-module tbr">
            <legend>Upload Image <span class="module-tag tbr">for TBR Analysis</span></legend>
            <p class="small-text">Upload an image. During Analysis, the system will show this image and the student describes what they see — TBR scans the description for personality keywords.</p>
            <div class="grid-2">
                <div>
                    <label>Image File</label>
                    <input type="file" name="image_file" accept="image/*">
                </div>
                <div>
                    <label>Tags (comma separated)</label>
                    <input type="text" name="image_tags" placeholder="e.g. nature, calm, sunset">
                </div>
            </div>
            <label>Description <span class="module-tag tbr">TBR Input</span></label>
            <textarea name="image_description" placeholder="Describe what this image shows. TBR will scan this text for introvert/extrovert keywords."></textarea>
        </div>

        <!-- ====== AUDIO SECTION (CBR) ====== -->
        <div class="analysis-module cbr">
            <legend>Upload Audio / Song <span class="module-tag cbr">for CBR Analysis</span></legend>
            <p class="small-text">Upload the student's personal song. CBR reads genre, energy, mood, and duration from this song to independently confirm or contradict their declared MBTI.</p>
            <div class="grid-2">
                <div>
                    <label>Audio File</label>
                    <input type="file" name="audio_file" accept="audio/*">
                </div>
                <div>
                    <label>Tags</label>
                    <input type="text" name="audio_tags" placeholder="e.g. lofi, chill, reflective">
                </div>
            </div>
            <div class="grid-3">
                <div>
                    <label>Song Title</label>
                    <input type="text" name="song_title" placeholder="Enter song title">
                </div>
                <div>
                    <label>Artist / Creator</label>
                    <input type="text" name="artist" placeholder="Enter artist name">
                </div>
                <div>
                    <label>Duration (seconds)</label>
                    <input type="number" name="duration" placeholder="e.g. 240">
                </div>
            </div>
            <div class="grid-3">
                <div>
                    <label>Genre</label>
                    <select name="genre">
                        <option value="">Select genre</option>
                        <option>Lofi</option><option>Acoustic</option><option>Jazz</option>
                        <option>Instrumental</option><option>Pop</option><option>Rock</option>
                        <option>EDM</option><option>Classical</option>
                    </select>
                </div>
                <div>
                    <label>Energy Level</label>
                    <select name="energy_level">
                        <option value="">Select energy</option>
                        <option>Low</option><option>Medium</option><option>High</option>
                    </select>
                </div>
                <div>
                    <label>Mood Type</label>
                    <select name="mood_type">
                        <option value="">Select mood</option>
                        <option>Calm</option><option>Happy</option><option>Sad</option>
                        <option>Energetic</option><option>Reflective</option>
                    </select>
                </div>
            </div>
            <label>Lyrics Keywords</label>
            <input type="text" name="lyrics_keywords" placeholder="e.g. dream, rise, together">
        </div>

        <!-- ====== PDF SECTION (TBR) ====== -->
        <div class="analysis-module tbr">
            <legend>Upload PDF <span class="module-tag tbr">for TBR Analysis</span></legend>
            <p class="small-text">Upload a PDF document. TBR scans the description text for personality keywords.</p>
            <div class="grid-2">
                <div>
                    <label>PDF File</label>
                    <input type="file" name="pdf_file" accept=".pdf">
                </div>
                <div>
                    <label>Tags</label>
                    <input type="text" name="pdf_tags" placeholder="e.g. research, analysis, deep">
                </div>
            </div>
            <label>Description <span class="module-tag tbr">TBR Input</span></label>
            <textarea name="pdf_description" placeholder="Describe what this PDF contains. TBR will scan for introvert/extrovert keywords."></textarea>
        </div>

        <button class="btn" type="submit">Upload File(s)</button>
    </form>
</section>
<?php include 'includes/footer.php'; ?>
