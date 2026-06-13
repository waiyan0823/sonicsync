<?php
include 'includes/db.php';

$mode = $_GET['mode'] ?? 'all';
$conditions = [];
$params = [];
$types = '';

// ABR fields
$mbti_filter = trim($_GET['mbti_filter'] ?? '');
$genre_filter = trim($_GET['genre_filter'] ?? '');
$energy_filter = trim($_GET['energy_filter'] ?? '');
$mood_filter = trim($_GET['mood_filter'] ?? '');
$lab_group = trim($_GET['lab_group'] ?? '');
$persona_filter = trim($_GET['persona_filter'] ?? '');
$file_type_filter = trim($_GET['file_type_filter'] ?? '');

// TBR fields
$keyword = trim($_GET['keyword'] ?? '');

// CBR fields
$cbr_genre = trim($_GET['cbr_genre'] ?? '');
$cbr_energy = trim($_GET['cbr_energy'] ?? '');
$cbr_mood = trim($_GET['cbr_mood'] ?? '');
$cbr_file_type = trim($_GET['cbr_file_type'] ?? '');

$base_sql = "
    SELECT s.student_id, s.name AS student_name, s.matric_no, s.mbti_type AS declared_mbti, s.lab_group,
           ma.asset_id, ma.file_name, ma.file_type, ma.file_size, ma.description, ma.tags, ma.media_category, ma.upload_date,
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
        $conditions[] = "(ma.description LIKE ? OR ma.tags LIKE ? OR am.lyrics_keywords LIKE ? OR rr.podcast_script LIKE ? OR rr.generated_persona LIKE ?)";
        $kw = "%$keyword%";
        foreach (range(1, 5) as $i) { $params[] = $kw; $types .= 's'; }
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
        <p>Search across all 4 database tables using ABR (structured attributes), TBR (text keywords), or CBR (audio content characteristics).</p>
    </div>
    <div class="tag">SEARCH</div>
</section>

<div class="info-box">
    <h4>Retrieval Methods Overview</h4>
    <p>
        <strong>ABR (Attribute-Based Retrieval):</strong> Exact-match filtering on structured columns (MBTI type, genre, energy, mood, persona).<br>
        <strong>TBR (Text-Based Retrieval):</strong> LIKE pattern matching across description text, tags, lyrics keywords, podcast scripts, and persona labels.<br>
        <strong>CBR (Content-Based Retrieval):</strong> Filtering by audio content features (genre, energy, mood, file type) to classify personality tendency.<br>
        All methods run JOINs across <strong>student → multimedia_asset → audio_metadata → recommendation_result</strong>.
    </p>
</div>

<section class="grid-3" style="margin-bottom:10px">
    <div class="retrieval-method-card abr">
        <h4>ABR <span class="module-tag abr">Attribute-Based</span></h4>
        <p>Filters: student.mbti_type, am.genre, am.energy_level, am.mood_type, s.lab_group, rr.generated_persona, ma.file_type</p>
    </div>
    <div class="retrieval-method-card tbr">
        <h4>TBR <span class="module-tag tbr">Text-Based</span></h4>
        <p>Scans: ma.description, ma.tags, am.lyrics_keywords, rr.podcast_script, rr.generated_persona</p>
    </div>
    <div class="retrieval-method-card cbr">
        <h4>CBR <span class="module-tag cbr">Content-Based</span></h4>
        <p>Filters: am.genre, am.energy_level, am.mood_type, ma.file_type</p>
    </div>
</section>

<div class="retrieval-tabs">
    <a class="tab <?= $mode === 'all' ? 'active' : '' ?>" href="?mode=all">All Methods</a>
    <a class="tab <?= $mode === 'abr' ? 'active' : '' ?>" href="?mode=abr">ABR</a>
    <a class="tab <?= $mode === 'tbr' ? 'active' : '' ?>" href="?mode=tbr">TBR</a>
    <a class="tab <?= $mode === 'cbr' ? 'active' : '' ?>" href="?mode=cbr">CBR</a>
</div>

<section class="form-card">
    <h2>
        <?= strtoupper($mode === 'all' ? 'All Retrieval Methods' : $mode) ?> Search
        <?php if ($total): ?><span class="result-count"><?= $total ?> record(s)</span><?php endif; ?>
    </h2>
    <form method="GET">
        <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">

        <?php if ($mode === 'abr' || $mode === 'all'): ?>
        <fieldset class="retrieval-fieldset">
            <legend>ABR <span class="module-tag abr">Attribute-Based Retrieval</span></legend>
            <p class="small-text">Filter records by exact match on structured database columns. Each field maps directly to a table column.</p>
            <div class="grid-4">
                <div>
                    <label>MBTI Type <span class="small-text">(student.mbti_type)</span></label>
                    <select name="mbti_filter">
                        <option value="">All</option>
                        <?php foreach (['INFP','ENFP','INFJ','ENFJ','INTJ','ENTJ','ISFP','ESFP','ISTJ','ESTJ','ISFJ','ESFJ','ISTP','ESTP','INTP','ENTP'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($mbti_filter ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Genre <span class="small-text">(audio_metadata.genre)</span></label>
                    <select name="genre_filter">
                        <option value="">All</option>
                        <?php foreach (['Lofi','Acoustic','Jazz','Instrumental','Classical','Pop','Rock','EDM'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($genre_filter ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Energy <span class="small-text">(am.energy_level)</span></label>
                    <select name="energy_filter">
                        <option value="">All</option>
                        <option value="Low" <?= ($energy_filter ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($energy_filter ?? '') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($energy_filter ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div>
                    <label>Mood <span class="small-text">(am.mood_type)</span></label>
                    <select name="mood_filter">
                        <option value="">All</option>
                        <option value="Calm" <?= ($mood_filter ?? '') === 'Calm' ? 'selected' : '' ?>>Calm</option>
                        <option value="Happy" <?= ($mood_filter ?? '') === 'Happy' ? 'selected' : '' ?>>Happy</option>
                        <option value="Sad" <?= ($mood_filter ?? '') === 'Sad' ? 'selected' : '' ?>>Sad</option>
                        <option value="Energetic" <?= ($mood_filter ?? '') === 'Energetic' ? 'selected' : '' ?>>Energetic</option>
                        <option value="Reflective" <?= ($mood_filter ?? '') === 'Reflective' ? 'selected' : '' ?>>Reflective</option>
                    </select>
                </div>
            </div>
            <div class="grid-4">
                <div>
                    <label>Lab Group <span class="small-text">(student.lab_group)</span></label>
                    <input type="text" name="lab_group" value="<?= htmlspecialchars($lab_group ?? '') ?>" placeholder="e.g. GW08">
                </div>
                <div>
                    <label>Generated Persona <span class="small-text">(rr.generated_persona)</span></label>
                    <select name="persona_filter">
                        <option value="">All</option>
                        <option value="Authentic" <?= ($persona_filter ?? '') === 'Authentic' ? 'selected' : '' ?>>Authentic</option>
                        <option value="Creative Deviation" <?= ($persona_filter ?? '') === 'Creative Deviation' ? 'selected' : '' ?>>Creative Deviation</option>
                    </select>
                </div>
                <div>
                    <label>File Type <span class="small-text">(ma.file_type)</span></label>
                    <select name="file_type_filter">
                        <option value="">All</option>
                        <option value="jpg" <?= ($file_type_filter ?? '') === 'jpg' ? 'selected' : '' ?>>JPG</option>
                        <option value="mp3" <?= ($file_type_filter ?? '') === 'mp3' ? 'selected' : '' ?>>MP3</option>
                        <option value="pdf" <?= ($file_type_filter ?? '') === 'pdf' ? 'selected' : '' ?>>PDF</option>
                    </select>
                </div>
            </div>
        </fieldset>
        <?php endif; ?>

        <?php if ($mode === 'tbr' || $mode === 'all'): ?>
        <fieldset class="retrieval-fieldset">
            <legend>TBR <span class="module-tag tbr">Text-Based Retrieval</span></legend>
            <p class="small-text">Search for keywords across text fields. Uses SQL LIKE %keyword% on descriptions, tags, lyrics, podcast scripts, and persona labels.</p>
            <div>
                <label>Keyword Search <span class="small-text">(ma.description, ma.tags, am.lyrics_keywords, rr.podcast_script, rr.generated_persona)</span></label>
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword ?? '') ?>" placeholder="e.g. calm, dream, party">
            </div>
        </fieldset>
        <?php endif; ?>

        <?php if ($mode === 'cbr' || $mode === 'all'): ?>
        <fieldset class="retrieval-fieldset">
            <legend>CBR <span class="module-tag cbr">Content-Based Retrieval</span></legend>
            <p class="small-text">Filter audio content by intrinsic characteristics. Maps audio features to personality tendencies (e.g., Lofi + Calm + Slow → introvert).</p>
            <div class="grid-4">
                <div>
                    <label>Genre <span class="small-text">(am.genre)</span></label>
                    <select name="cbr_genre">
                        <option value="">All</option>
                        <?php foreach (['Lofi','Acoustic','Jazz','Instrumental','Classical','Pop','Rock','EDM'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($cbr_genre ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Energy Level <span class="small-text">(am.energy_level)</span></label>
                    <select name="cbr_energy">
                        <option value="">All</option>
                        <option value="Low" <?= ($cbr_energy ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($cbr_energy ?? '') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($cbr_energy ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div>
                    <label>Mood <span class="small-text">(am.mood_type)</span></label>
                    <select name="cbr_mood">
                        <option value="">All</option>
                        <option value="Calm" <?= ($cbr_mood ?? '') === 'Calm' ? 'selected' : '' ?>>Calm</option>
                        <option value="Happy" <?= ($cbr_mood ?? '') === 'Happy' ? 'selected' : '' ?>>Happy</option>
                        <option value="Sad" <?= ($cbr_mood ?? '') === 'Sad' ? 'selected' : '' ?>>Sad</option>
                        <option value="Energetic" <?= ($cbr_mood ?? '') === 'Energetic' ? 'selected' : '' ?>>Energetic</option>
                        <option value="Reflective" <?= ($cbr_mood ?? '') === 'Reflective' ? 'selected' : '' ?>>Reflective</option>
                    </select>
                </div>
                <div>
                    <label>File Type <span class="small-text">(ma.file_type)</span></label>
                    <select name="cbr_file_type">
                        <option value="">All</option>
                        <option value="mp3" <?= ($cbr_file_type ?? '') === 'mp3' ? 'selected' : '' ?>>MP3</option>
                        <option value="wav" <?= ($cbr_file_type ?? '') === 'wav' ? 'selected' : '' ?>>WAV</option>
                        <option value="m4a" <?= ($cbr_file_type ?? '') === 'm4a' ? 'selected' : '' ?>>M4A</option>
                    </select>
                </div>
            </div>
        </fieldset>
        <?php endif; ?>

        <button class="btn" type="submit">Execute Retrieval</button>
        <a class="btn secondary" href="retrieval.php">Reset All</a>
    </form>
</section>

<section class="table-card">
    <h2>Retrieved Records</h2>
    <p class="small-text">
        <?php if ($mode === 'all'): ?>Showing all records across 4 tables with JOINs on student_id and asset_id. Use tabs above to filter by retrieval method.<?php endif; ?>
        <?php if ($mode === 'abr'): ?>ABR query: exact-match WHERE conditions on <strong>student.mbti_type</strong>, <strong>audio_metadata.genre</strong>, <strong>energy_level</strong>, <strong>mood_type</strong>, <strong>student.lab_group</strong>, <strong>recommendation_result.generated_persona</strong>, <strong>multimedia_asset.file_type</strong>.<?php endif; ?>
        <?php if ($mode === 'tbr'): ?>TBR query: LIKE %keyword% matching on <strong>multimedia_asset.description</strong>, <strong>tags</strong>, <strong>audio_metadata.lyrics_keywords</strong>, <strong>recommendation_result.podcast_script</strong>, and <strong>generated_persona</strong>.<?php endif; ?>
        <?php if ($mode === 'cbr'): ?>CBR query: exact-match WHERE on <strong>audio_metadata.genre</strong>, <strong>energy_level</strong>, <strong>mood_type</strong>, and <strong>multimedia_asset.file_type</strong>. Matches audio features to personality.<?php endif; ?>
    </p>
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
                <tr><td colspan="7">No records match the criteria. Try a different search or reset the form.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
