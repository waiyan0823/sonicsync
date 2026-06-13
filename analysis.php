<?php
include 'includes/db.php';

$message = '';
$error = '';
$selected_student_id = $_GET['student_id'] ?? '';

$students = $conn->query("SELECT student_id, name, mbti_type FROM student ORDER BY name");

$prefill_desc = '';
$prefill_genre = '';
$prefill_mood = '';
$prefill_tempo = '';
$prefill_energy = '';
$auto_loaded = false;

function predictTBR($text) {
    $text = strtolower($text);
    $introvertWords = ['calm','quiet','alone','peaceful','reflect','dream','deep','future','feel','nature','silence','soft','slow','mind','heart','thought','whisper','lesson','hope','solitude','gentle'];
    $extrovertWords = ['party','crowd','excited','social','energy','friends','active','adventure','fun','together','delight','challenge','rise','run','bright','bold','joy','motivation'];

    $i = 0; $e = 0; $found = [];
    foreach ($introvertWords as $word) {
        if (strpos($text, $word) !== false) { $i++; $found[] = $word; }
    }
    foreach ($extrovertWords as $word) {
        if (strpos($text, $word) !== false) { $e++; $found[] = $word; }
    }

    if ($i > $e)      { $mbti = 'INFP'; $persona = 'Reflective Introvert'; }
    elseif ($e > $i)  { $mbti = 'ENFP'; $persona = 'Expressive Extrovert'; }
    else              { $mbti = 'ISFP'; $persona = 'Balanced Artistic Personality'; }

    return ['mbti' => $mbti, 'persona' => $persona, 'keywords' => implode(', ', array_unique($found))];
}

function predictCBR($genre, $mood, $tempo, $energy) {
    $score = 0;
    if (in_array($genre, ['Lofi','Acoustic','Jazz','Instrumental','Classical'])) { $score++; }
    if (in_array($mood, ['Calm','Sad','Reflective'])) { $score++; }
    if ($tempo === 'Slow') { $score++; }
    if ($energy === 'Low') { $score++; }
    return $score >= 2 ? 'Reflective Introvert' : 'Expressive Extrovert';
}

function cbrToMbti($cbrPersonality) {
    return $cbrPersonality === 'Reflective Introvert' ? 'INFP' : 'ENFP';
}

function getPoemText($poem_id) {
    $poems = [
        '1' => "The quiet night surrounds my mind,\nAs distant dreams begin to shine.\nIn silence I reflect and grow,\nSearching for answers I wish to know.",
        '2' => "Together we run towards the light,\nWith endless energy and delight.\nChallenges fade as spirits rise,\nAdventure shines before our eyes.",
        '3' => "The ocean whispers soft and slow,\nGuiding thoughts that gently flow.\nEvery wave carries a lesson deep,\nA promise the heart will always keep."
    ];
    return $poems[$poem_id] ?? '';
}

function generatePodcast($finalMbti, $studentName, $personaType, $genre, $keywords, $declaredMbti) {
    $podcasts = [
        'INFP' => ['title' => 'The Quiet Dreamer', 'podcast' => 'The Introvert Hour'],
        'ENFP' => ['title' => 'The Electric Spirit', 'podcast' => 'Extrovert Unleashed'],
        'ISFP' => ['title' => 'The Gentle Artist', 'podcast' => 'Creative Minds'],
        'INTJ' => ['title' => 'The Strategic Mind', 'podcast' => 'Deep Dive'],
        'INFJ' => ['title' => 'The Visionary', 'podcast' => 'Insightful Conversations'],
    ];

    $p = $podcasts[$finalMbti] ?? ['title' => 'The Curious Explorer', 'podcast' => 'Personality Plus'];

    $songGenres = [
        'Lofi' => 'Gentle Rain', 'Acoustic' => 'Quiet Strings', 'Jazz' => 'Midnight Blues',
        'Instrumental' => 'Echoes', 'Classical' => 'Moonlight Sonata',
        'Pop' => 'Neon Lights', 'Rock' => 'Thunderstrike', 'EDM' => 'Pulse'
    ];
    $song = $songGenres[$genre] ?? 'Echoes of You';

    $script = "Welcome to " . $p['podcast'] . ". Today we feature " . $studentName
        . ", whose declared MBTI is " . $declaredMbti
        . ". The system has classified this profile as " . $personaType
        . ". Detected keywords — " . $keywords
        . ". " . $studentName . "'s musical preference of " . $genre
        . " reinforces a personality profile that is "
        . ($personaType === 'Authentic' ? 'consistent and self-aware.' : 'creatively expressive and dynamically evolving.')
        . " This episode's recommended track is \"" . $song . "\".";

    return [
        'podcast_title' => $p['title'],
        'podcast_script' => $script,
        'recommended_song' => $song,
        'recommended_podcast' => $p['podcast']
    ];
}

function runAnalysis($conn, $student_id, $image_description, $genre, $mood, $tempo, $energy) {
    $stmt = $conn->prepare("SELECT name, mbti_type FROM student WHERE student_id = ?");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    if (!$student) return null;

    $combined_tbr = trim($image_description);
    if ($combined_tbr !== '') {
        $tbr = predictTBR($combined_tbr);
        $tbr_mbti = $tbr['mbti'];
        $keywords = $tbr['keywords'];
    } else {
        $tbr_mbti = 'N/A';
        $keywords = 'N/A';
    }

    if ($genre && $mood && $tempo && $energy) {
        $cbr_personality = predictCBR($genre, $mood, $tempo, $energy);
        $cbr_mbti = cbrToMbti($cbr_personality);
    } else {
        $cbr_personality = 'N/A';
        $cbr_mbti = 'N/A';
    }

    $final_mbti = ($tbr_mbti !== 'N/A') ? $tbr_mbti : $cbr_mbti;
    if ($final_mbti === 'N/A') $final_mbti = $student['mbti_type'] ?: 'INFP';

    $persona_type = ($student['mbti_type'] && $final_mbti !== 'N/A' && strtoupper($student['mbti_type']) === strtoupper($final_mbti))
        ? 'Authentic' : 'Creative Deviation';

    $podcast = generatePodcast($final_mbti, $student['name'], $persona_type, $genre ?: 'General', $keywords, $student['mbti_type']);

    $audio_id = null;
    $stmt = $conn->prepare("SELECT am.audio_id FROM multimedia_asset ma JOIN audio_metadata am ON ma.asset_id = am.asset_id WHERE ma.student_id = ? LIMIT 1");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) $audio_id = $r['audio_id'];

    $stmt = $conn->prepare("INSERT INTO recommendation_result (student_id, audio_id, generated_persona, podcast_title, podcast_script, recommended_song, recommended_podcast) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sisssss', $student_id, $audio_id, $persona_type, $podcast['podcast_title'], $podcast['podcast_script'], $podcast['recommended_song'], $podcast['recommended_podcast']);
    $stmt->execute();

    return $conn->insert_id;
}

// Auto-analyze if student_id is passed and data exists in DB
if ($selected_student_id) {
    $stmt = $conn->prepare("SELECT description FROM multimedia_asset WHERE student_id = ? ORDER BY upload_date DESC LIMIT 1");
    $stmt->bind_param('s', $selected_student_id);
    $stmt->execute();
    $desc = $stmt->get_result()->fetch_assoc()['description'] ?? '';
    if ($desc) { $prefill_desc = $desc; }

    $stmt = $conn->prepare("SELECT am.genre, am.mood_type, am.energy_level FROM audio_metadata am JOIN multimedia_asset ma ON am.asset_id = ma.asset_id WHERE ma.student_id = ? LIMIT 1");
    $stmt->bind_param('s', $selected_student_id);
    $stmt->execute();
    $meta = $stmt->get_result()->fetch_assoc();

    if ($desc && $meta && !empty($meta['genre'])) {
        $result_id = runAnalysis($conn, $selected_student_id, $desc, $meta['genre'], $meta['mood_type'], 'Medium', $meta['energy_level']);
        if ($result_id) {
            header('Location: result.php?id=' . $result_id);
            exit;
        }
    }
}

// Manual form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = trim($_POST['student_id'] ?? '');
    $desc = trim($_POST['image_description'] ?? '');
    $poem_text = getPoemText(trim($_POST['poem_id'] ?? ''));
    $genre = trim($_POST['genre'] ?? '');
    $mood = trim($_POST['mood'] ?? '');
    $tempo = trim($_POST['tempo'] ?? '');
    $energy = trim($_POST['energy'] ?? '');
    $full_text = trim($desc . ' ' . $poem_text);

    if ($sid === '') {
        $error = 'Please select a student.';
    } else {
        $result_id = runAnalysis($conn, $sid, $full_text, $genre, $mood, $tempo, $energy);
        if ($result_id) {
            header('Location: result.php?id=' . $result_id);
            exit;
        }
        $error = 'Analysis failed. Student not found.';
    }
}
?>
<?php include 'includes/header.php'; ?>
<section class="hero">
    <div>
        <h1>TBR & CBR Analysis</h1>
        <p>Step 3 of 4: If files were uploaded, analysis runs automatically. Otherwise, fill the form below.</p>
    </div>
    <div class="tag">ANALYSIS</div>
</section>

<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($prefill_desc): ?>
<div class="alert" style="border-color:rgba(180,92,255,0.5);background:rgba(180,92,255,0.12)">
    Description auto-loaded from upload. Review below and submit to complete analysis.
</div>
<?php endif; ?>

<div class="info-box">
    <h4>How the analysis works</h4>
    <p><strong>TBR (Text-Based Retrieval):</strong> Your description and the poem text are scanned for introvert keywords (calm, quiet, reflect) vs extrovert keywords (party, excited, crowd). More introvert words → INFP; more extrovert → ENFP; balanced → ISFP.<br>
    <strong>CBR (Content-Based Retrieval):</strong> Audio metadata (genre, mood, tempo, energy) is scored. Calm/Slow/Low/Lofi = introvert; Energetic/Fast/High/Pop = extrovert.<br>
    <strong>Final Persona:</strong> If TBR result matches declared MBTI → "Authentic". If different → "Creative Deviation". A podcast script is generated based on the final MBTI type.</p>
</div>

<form method="POST">
    <section class="form-card">
        <h2>Student</h2>
        <label>Select Student</label>
        <select name="student_id" required>
            <option value="">-- Select --</option>
            <?php $students->data_seek(0); while ($s = $students->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($s['student_id']) ?>" <?= $s['student_id'] === $selected_student_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['student_id'] . ' - ' . $s['name'] . ' (' . $s['mbti_type'] . ')') ?>
                </option>
            <?php endwhile; ?>
        </select>
    </section>

    <section class="analysis-module tbr">
        <legend>TBR Analysis <span class="module-tag tbr">Text-Based Retrieval</span></legend>
        <h3>Image Description</h3>
        <p class="small-text">Describe the image below. The system scans your text for introvert/extrovert keywords to predict MBTI tendency.</p>
        <div class="analysis-layout">
            <div class="image-box">
                <img src="https://picsum.photos/seed/<?= mt_rand() ?>/400/300" alt="Random image for description" style="width:100%;border-radius:12px">
            </div>
            <div>
                <label>Your Description</label>
                <textarea name="image_description" placeholder="Example: This image feels calm and peaceful. It makes me reflect quietly about my future."><?= htmlspecialchars($prefill_desc) ?></textarea>
            </div>
        </div>
        <h3>Poem Analysis</h3>
        <p class="small-text">Select a poem. The system analyzes its text for keywords and emotion to support the MBTI prediction.</p>
        <label>Select Poem</label>
        <select name="poem_id">
            <option value="">-- Skip --</option>
            <option value="1">Poem 1 — Solitude and Hope (introvert-leaning)</option>
            <option value="2">Poem 2 — Joy and Motivation (extrovert-leaning)</option>
            <option value="3">Poem 3 — Reflection and Peace (introvert-leaning)</option>
        </select>
    </section>

    <section class="analysis-module cbr">
        <legend>CBR Analysis <span class="module-tag cbr">Content-Based Retrieval</span></legend>
        <p class="small-text">Enter audio characteristics, or upload a song first and CBR will auto-read from it.</p>
        <div class="grid-2">
            <div>
                <label>Genre</label>
                <select name="genre">
                    <option value="">Select genre</option>
                    <option value="Lofi">Lofi</option><option value="Acoustic">Acoustic</option>
                    <option value="Jazz">Jazz</option><option value="Instrumental">Instrumental</option>
                    <option value="Classical">Classical</option><option value="Pop">Pop</option>
                    <option value="Rock">Rock</option><option value="EDM">EDM</option>
                </select>
            </div>
            <div>
                <label>Mood</label>
                <select name="mood">
                    <option value="">Select mood</option>
                    <option value="Calm">Calm</option><option value="Happy">Happy</option>
                    <option value="Sad">Sad</option><option value="Energetic">Energetic</option>
                    <option value="Reflective">Reflective</option>
                </select>
            </div>
        </div>
        <div class="grid-2">
            <div>
                <label>Tempo</label>
                <select name="tempo">
                    <option value="">Select tempo</option>
                    <option value="Slow">Slow</option><option value="Medium">Medium</option><option value="Fast">Fast</option>
                </select>
            </div>
            <div>
                <label>Energy Level</label>
                <select name="energy">
                    <option value="">Select energy</option>
                    <option value="Low">Low</option><option value="Medium">Medium</option><option value="High">High</option>
                </select>
            </div>
        </div>
    </section>

    <button class="btn" type="submit">Generate Analysis</button>
</form>

<?php include 'includes/footer.php'; ?>
