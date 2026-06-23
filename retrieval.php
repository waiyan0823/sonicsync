<?php
include 'includes/db.php';

$mode = $_GET['mode'] ?? 'all';
$conditions = [];
$params = [];
$types = '';

$mbti_filter = trim($_GET['mbti_filter'] ?? '');
$genre_filter = trim($_GET['genre_filter'] ?? '');
$energy_filter = trim($_GET['energy_filter'] ?? '');
$mood_filter = trim($_GET['mood_filter'] ?? '');
$lab_group = trim($_GET['lab_group'] ?? '');
$persona_filter = trim($_GET['persona_filter'] ?? '');
$file_type_filter = trim($_GET['file_type_filter'] ?? '');

$keyword = trim($_GET['keyword'] ?? '');

$cbr_genre = trim($_GET['cbr_genre'] ?? '');
$cbr_energy = trim($_GET['cbr_energy'] ?? '');
$cbr_mood = trim($_GET['cbr_mood'] ?? '');
$cbr_file_type = trim($_GET['cbr_file_type'] ?? '');

$base_sql = "
    SELECT s.student_id, s.name AS student_name, s.matric_no, s.mbti_type AS declared_mbti, s.lab_group,
           ma.asset_id, ma.file_name, ma.file_type, ma.file_size, ma.file_path, ma.description, ma.tags, ma.media_category, ma.upload_date,
           am.audio_id, am.song_title, am.artist_or_creator, am.duration, am.energy_level, am.mood_type, am.lyrics_keywords, am.genre,
           rr.result_id, rr.generated_persona, rr.podcast_title, rr.podcast_script, rr.recommended_song, rr.recommended_podcast, rr.generated_date
    FROM student s
    LEFT JOIN multimedia_asset ma ON s.student_id = ma.student_id
    LEFT JOIN audio_metadata am ON ma.asset_id = am.asset_id
    LEFT JOIN recommendation_result rr ON s.student_id = rr.student_id
";

$count_sql = "
    SELECT COUNT(*) AS total
    FROM student s
    LEFT JOIN multimedia_asset ma ON s.student_id = ma.student_id
    LEFT JOIN audio_metadata am ON ma.asset_id = am.asset_id
    LEFT JOIN recommendation_result rr ON s.student_id = rr.student_id
";

if ($mode === 'abr' || $mode === 'all') {
    if ($mbti_filter !== '') {
        $conditions[] = "s.mbti_type = ?";
        $params[] = $mbti_filter;
        $types .= 's';
    }
    if ($genre_filter !== '') {
        $conditions[] = "am.genre = ?";
        $params[] = $genre_filter;
        $types .= 's';
    }
    if ($energy_filter !== '') {
        $conditions[] = "am.energy_level = ?";
        $params[] = $energy_filter;
        $types .= 's';
    }
    if ($mood_filter !== '') {
        $conditions[] = "am.mood_type = ?";
        $params[] = $mood_filter;
        $types .= 's';
    }
    if ($lab_group !== '') {
        $conditions[] = "s.lab_group = ?";
        $params[] = $lab_group;
        $types .= 's';
    }
    if ($persona_filter !== '') {
        $conditions[] = "rr.generated_persona = ?";
        $params[] = $persona_filter;
        $types .= 's';
    }
    if ($file_type_filter !== '') {
        $conditions[] = "ma.file_type = ?";
        $params[] = $file_type_filter;
        $types .= 's';
    }
}

if ($mode === 'tbr' || $mode === 'all') {
    if ($keyword !== '') {
        $conditions[] = "(s.name LIKE ? OR s.matric_no LIKE ? OR s.life_value LIKE ? OR ma.description LIKE ? OR ma.tags LIKE ? OR am.lyrics_keywords LIKE ? OR rr.podcast_script LIKE ? OR rr.generated_persona LIKE ?)";
        $kw = "%$keyword%";
        foreach (range(1, 8) as $i) {
            $params[] = $kw;
            $types .= 's';
        }
    }
}

if ($mode === 'cbr' || $mode === 'all') {
    if ($cbr_genre !== '') {
        $conditions[] = "am.genre = ?";
        $params[] = $cbr_genre;
        $types .= 's';
    }
    if ($cbr_energy !== '') {
        $conditions[] = "am.energy_level = ?";
        $params[] = $cbr_energy;
        $types .= 's';
    }
    if ($cbr_mood !== '') {
        $conditions[] = "am.mood_type = ?";
        $params[] = $cbr_mood;
        $types .= 's';
    }
    if ($cbr_file_type !== '') {
        $conditions[] = "ma.file_type = ?";
        $params[] = $cbr_file_type;
        $types .= 's';
    }
}

$where_clause = '';
if ($conditions) {
    $where_clause = ' WHERE ' . implode(' AND ', $conditions);
}

$stmt = $conn->prepare($count_sql . $where_clause);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare($base_sql . $where_clause . ' ORDER BY s.student_id, ma.upload_date DESC');
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result();
?>
<?php include 'includes/header.php'; ?>
<section class="hero">
    <div>
        <h1>Retrieval</h1>
        <p>Find student records, media, and results with a simple search.</p>
    </div>
    <div class="tag">SEARCH</div>
</section>

<div class="retrieval-tabs">
    <a class="tab <?= $mode === 'all' ? 'active' : '' ?>" href="?mode=all">All</a>
    <a class="tab <?= $mode === 'abr' ? 'active' : '' ?>" href="?mode=abr">ABR</a>
    <a class="tab <?= $mode === 'tbr' ? 'active' : '' ?>" href="?mode=tbr">TBR</a>
    <a class="tab <?= $mode === 'cbr' ? 'active' : '' ?>" href="?mode=cbr">CBR</a>
</div>

<section class="form-card">
    <h2>
        <?= strtoupper($mode === 'all' ? 'All' : $mode) ?> Search
        <?php if ($total): ?><span class="result-count"><?= $total ?> record(s)</span><?php endif; ?>
    </h2>
    <form method="GET">
        <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">

        <?php if ($mode === 'abr' || $mode === 'all'): ?>
        <fieldset class="retrieval-fieldset">
            <legend>ABR <span class="module-tag abr">Filters</span></legend>
            <p class="small-text">Use these filters to narrow the list.</p>
            <div class="grid-4">
                <div>
                    <label>MBTI Type</label>
                    <select name="mbti_filter">
                        <option value="">All</option>
                        <?php foreach (['INFP','ENFP','INFJ','ENFJ','INTJ','ENTJ','ISFP','ESFP','ISTJ','ESTJ','ISFJ','ESFJ','ISTP','ESTP','INTP','ENTP'] as $t): ?>
                            <option value="<?= $t ?>" <?= $mbti_filter === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Genre</label>
                    <select name="genre_filter">
                        <option value="">All</option>
                        <?php foreach (['Lofi','Acoustic','Jazz','Instrumental','Classical','Pop','Rock','EDM'] as $g): ?>
                            <option value="<?= $g ?>" <?= $genre_filter === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Energy</label>
                    <select name="energy_filter">
                        <option value="">All</option>
                        <option value="Low" <?= $energy_filter === 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= $energy_filter === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= $energy_filter === 'High' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div>
                    <label>Mood</label>
                    <select name="mood_filter">
                        <option value="">All</option>
                        <option value="Calm" <?= $mood_filter === 'Calm' ? 'selected' : '' ?>>Calm</option>
                        <option value="Happy" <?= $mood_filter === 'Happy' ? 'selected' : '' ?>>Happy</option>
                        <option value="Sad" <?= $mood_filter === 'Sad' ? 'selected' : '' ?>>Sad</option>
                        <option value="Energetic" <?= $mood_filter === 'Energetic' ? 'selected' : '' ?>>Energetic</option>
                        <option value="Reflective" <?= $mood_filter === 'Reflective' ? 'selected' : '' ?>>Reflective</option>
                    </select>
                </div>
            </div>
            <div class="grid-4">
                <div>
                    <label>Lab Group</label>
                    <input type="text" name="lab_group" value="<?= htmlspecialchars($lab_group) ?>" placeholder="e.g. GW08">
                </div>
                <div>
                    <label>Generated Persona</label>
                    <select name="persona_filter">
                        <option value="">All</option>
                        <option value="Authentic" <?= $persona_filter === 'Authentic' ? 'selected' : '' ?>>Authentic</option>
                        <option value="Creative Deviation" <?= $persona_filter === 'Creative Deviation' ? 'selected' : '' ?>>Creative Deviation</option>
                    </select>
                </div>
                <div>
                    <label>File Type</label>
                    <select name="file_type_filter">
                        <option value="">All</option>
                        <option value="jpg" <?= $file_type_filter === 'jpg' ? 'selected' : '' ?>>JPG</option>
                        <option value="mp3" <?= $file_type_filter === 'mp3' ? 'selected' : '' ?>>MP3</option>
                        <option value="pdf" <?= $file_type_filter === 'pdf' ? 'selected' : '' ?>>PDF</option>
                    </select>
                </div>
            </div>
        </fieldset>
        <?php endif; ?>

        <?php if ($mode === 'tbr' || $mode === 'all'): ?>
        <fieldset class="retrieval-fieldset">
            <legend>TBR <span class="module-tag tbr">Keywords</span></legend>
            <p class="small-text">Search by words or phrases.</p>
            <div>
                <label>Keyword Search</label>
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="e.g. calm, dream, party">
            </div>
        </fieldset>
        <?php endif; ?>

        <?php if ($mode === 'cbr' || $mode === 'all'): ?>
        <fieldset class="retrieval-fieldset">
            <legend>CBR <span class="module-tag cbr">Audio</span></legend>
            <p class="small-text">Filter by audio details.</p>
            <div class="grid-4">
                <div>
                    <label>Genre</label>
                    <select name="cbr_genre">
                        <option value="">All</option>
                        <?php foreach (['Lofi','Acoustic','Jazz','Instrumental','Classical','Pop','Rock','EDM'] as $g): ?>
                            <option value="<?= $g ?>" <?= $cbr_genre === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Energy Level</label>
                    <select name="cbr_energy">
                        <option value="">All</option>
                        <option value="Low" <?= $cbr_energy === 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= $cbr_energy === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= $cbr_energy === 'High' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div>
                    <label>Mood</label>
                    <select name="cbr_mood">
                        <option value="">All</option>
                        <option value="Calm" <?= $cbr_mood === 'Calm' ? 'selected' : '' ?>>Calm</option>
                        <option value="Happy" <?= $cbr_mood === 'Happy' ? 'selected' : '' ?>>Happy</option>
                        <option value="Sad" <?= $cbr_mood === 'Sad' ? 'selected' : '' ?>>Sad</option>
                        <option value="Energetic" <?= $cbr_mood === 'Energetic' ? 'selected' : '' ?>>Energetic</option>
                        <option value="Reflective" <?= $cbr_mood === 'Reflective' ? 'selected' : '' ?>>Reflective</option>
                    </select>
                </div>
                <div>
                    <label>File Type</label>
                    <select name="cbr_file_type">
                        <option value="">All</option>
                        <option value="mp3" <?= $cbr_file_type === 'mp3' ? 'selected' : '' ?>>MP3</option>
                        <option value="wav" <?= $cbr_file_type === 'wav' ? 'selected' : '' ?>>WAV</option>
                        <option value="m4a" <?= $cbr_file_type === 'm4a' ? 'selected' : '' ?>>M4A</option>
                    </select>
                </div>
            </div>
        </fieldset>
        <?php endif; ?>

        <button class="btn" type="submit">Search</button>
        <a class="btn secondary" href="retrieval.php">Reset</a>
    </form>
</section>

<section class="table-card">
    <h2>Retrieved Records</h2>
    <div class="table-wrap">
        <table>
            <tr>
                <th>Student</th>
                <th>Declared MBTI</th>
                <th>File</th>
                <th>Genre / Energy / Mood</th>
                <th>Persona</th>
                <th>Podcast</th>
                <th>Date</th>
            </tr>
            <?php if ($results && $results->num_rows > 0): ?>
                <?php while ($row = $results->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($row['student_name']) ?></strong><br>
                        <span class="small-text"><?= htmlspecialchars($row['student_id']) ?></span>
                    </td>
                    <td><span class="result-badge"><?= htmlspecialchars($row['declared_mbti'] ?: '-') ?></span></td>
                    <td>
                        <?= htmlspecialchars($row['file_name'] ?: '-') ?><br>
                        <span class="small-text"><?= htmlspecialchars($row['file_type'] ?? '') ?></span>
                        <?php if (!empty($row['file_path'])): ?>
                            <br><a class="small-text" href="<?= htmlspecialchars($row['file_path']) ?>" target="_blank" rel="noopener">Open source</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['genre']): ?>
                            <?= htmlspecialchars($row['genre']) ?> /
                            <?= htmlspecialchars($row['energy_level'] ?? '-') ?> /
                            <?= htmlspecialchars($row['mood_type'] ?? '-') ?>
                        <?php else: ?>
                            <span class="muted">No audio data</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['generated_persona']): ?>
                            <span class="result-badge"><?= htmlspecialchars($row['generated_persona']) ?></span>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['podcast_title']): ?>
                            <strong><?= htmlspecialchars($row['podcast_title']) ?></strong><br>
                            <span class="small-text"><?= htmlspecialchars($row['recommended_song'] ?: '') ?></span>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="small-text"><?= htmlspecialchars($row['generated_date'] ?? $row['upload_date'] ?? '') ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">No records match the current search.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
