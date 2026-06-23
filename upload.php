<?php
header('Location: analysis.php', true, 302);
exit;

include 'includes/db.php';

$message = '';
$error = '';
$students = $conn->query("SELECT student_id, name FROM student ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');

    if ($student_id === '') {
        $error = 'Select a student before uploading files.';
    } else {
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
        <p>Step 2 of 4: Select an existing student, then upload files for their personality analysis.</p>
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
                <select name="student_id" id="student-select" required>
                    <option value="">-- Select student --</option>
                    <?php while ($s = $students->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($s['student_id']) ?>">
                            <?= htmlspecialchars($s['student_id'] . ' - ' . $s['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="analysis-module tbr" id="student-poem-panel" style="display:none; margin-top:20px;">
            <legend>Student Poem <span class="module-tag tbr">Lecturer PDF</span></legend>
            <p class="small-text" id="student-poem-status">Select a student to load their poem.</p>
            <div id="student-poem-content" style="display:none;">
                <iframe id="student-poem-frame" title="Student poem PDF" style="width:100%;height:520px;border:1px solid var(--border);border-radius:12px;background:#fff;"></iframe>
                <label for="student-poem-text" style="margin-top:16px;">Extracted Poem Text</label>
                <textarea id="student-poem-text" rows="9" readonly placeholder="The poem text will appear here after the PDF is read."></textarea>
                <a class="btn" id="analyze-student-poem" href="analysis.php" style="display:inline-block;margin-top:12px;">Analyze This Poem</a>
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
            <p class="small-text">Upload the student's personal song and record its genre, energy, mood, and duration. CBR scores these stored attributes to support the personality prediction.</p>
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

<script src="assets/vendor/pdfjs/pdf.min.js"></script>
<script>
const studentSelect = document.getElementById('student-select');
const poemPanel = document.getElementById('student-poem-panel');
const poemContent = document.getElementById('student-poem-content');
const poemStatus = document.getElementById('student-poem-status');
const poemFrame = document.getElementById('student-poem-frame');
const poemText = document.getElementById('student-poem-text');
const analyzeLink = document.getElementById('analyze-student-poem');

function normalizeExtractedText(text) {
    return text
        .replace(/(?:\b[A-Za-z]\b[ \t])+\b[A-Za-z]\b/g, match => match.replace(/[ \t]/g, ''))
        .replace(/[ \t]{2,}/g, ' ')
        .replace(/ *\n */g, '\n')
        .trim();
}

async function extractPdfText(url) {
    if (!window.pdfjsLib) throw new Error('PDF reader could not be loaded.');

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/vendor/pdfjs/pdf.worker.min.js';
    const pdf = await pdfjsLib.getDocument(url).promise;
    const pages = [];

    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        const page = await pdf.getPage(pageNumber);
        const content = await page.getTextContent();
        pages.push(content.items.map(item => item.str).join(' '));
    }

    return normalizeExtractedText(pages.join('\n\n'));
}

async function loadStudentPoem() {
    const studentId = studentSelect.value;
    poemPanel.style.display = studentId ? 'block' : 'none';
    poemContent.style.display = 'none';
    poemText.value = '';
    poemFrame.removeAttribute('src');

    if (!studentId) return;
    poemStatus.textContent = 'Loading the student document...';

    try {
        const response = await fetch(`student_media.php?student_id=${encodeURIComponent(studentId)}`);
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'Unable to load student media.');

        if (!data.pdf) {
            poemStatus.textContent = `${data.student.name} does not have a poem PDF in the lecturer data.`;
            return;
        }

        poemFrame.src = data.pdf.view_url;
        poemContent.style.display = 'block';
        analyzeLink.href = data.analysis_url;
        poemStatus.textContent = `Reading ${data.pdf.file_name} for ${data.student.name}...`;

        const text = await extractPdfText(data.pdf.view_url);
        poemText.value = text;
        poemStatus.textContent = text
            ? 'Poem loaded. Continue to Analysis to analyze this text with TBR.'
            : 'PDF loaded, but no text was detected. It may be a scanned document.';
    } catch (error) {
        poemStatus.textContent = error.message;
    }
}

studentSelect.addEventListener('change', loadStudentPoem);
</script>
<?php include 'includes/footer.php'; ?>
