<?php
include 'includes/db.php';

$result_id = $_GET['id'] ?? 0;
$row = null;

function mbtiCategory($mbti) {
    $mbti = strtoupper(trim((string) $mbti));
    if (!preg_match('/^[EI][SN][TF][JP]$/', $mbti)) {
        return '';
    }

    $middle = substr($mbti, 1, 2);
    if ($middle === 'NT') {
        return 'Analyst';
    }
    if ($middle === 'NF') {
        return 'Diplomat';
    }
    if ($mbti[1] === 'S' && $mbti[3] === 'J') {
        return 'Sentinel';
    }
    if ($mbti[1] === 'S' && $mbti[3] === 'P') {
        return 'Explorer';
    }

    return 'Balanced Profile';
}

if ($result_id) {
    $stmt = $conn->prepare("
        SELECT rr.*, s.name AS student_name, s.matric_no, s.mbti_type AS declared_mbti, s.lab_group,
               ma.description, ma.tags, ma.media_category, ma.file_name,
               am.genre, am.energy_level, am.mood_type, am.song_title, am.artist_or_creator,
               am.tempo_bpm, am.rms_energy, am.spectral_centroid, am.zero_crossing_rate,
               am.tempo_category, am.personality_tendency
        FROM recommendation_result rr
        JOIN student s ON rr.student_id = s.student_id
        LEFT JOIN multimedia_asset ma ON s.student_id = ma.student_id
        LEFT JOIN audio_metadata am ON rr.audio_id = am.audio_id
        WHERE rr.result_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $result_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
}

if (!$row) {
    $row = $conn->query("
        SELECT rr.*, s.name AS student_name, s.matric_no, s.mbti_type AS declared_mbti, s.lab_group,
               ma.description, ma.tags, ma.media_category, ma.file_name,
               am.genre, am.energy_level, am.mood_type, am.song_title, am.artist_or_creator,
               am.tempo_bpm, am.rms_energy, am.spectral_centroid, am.zero_crossing_rate,
               am.tempo_category, am.personality_tendency
        FROM recommendation_result rr
        JOIN student s ON rr.student_id = s.student_id
        LEFT JOIN multimedia_asset ma ON s.student_id = ma.student_id
        LEFT JOIN audio_metadata am ON rr.audio_id = am.audio_id
        ORDER BY rr.result_id DESC
        LIMIT 1
    ")->fetch_assoc();
}
?>
<?php include 'includes/header.php'; ?>
<section class="hero">
    <div>
        <h1>Recommendation Result</h1>
        <p>Step 3 of 3: Podcast recommendation generated from TBR signals and estimated CBR audio tendencies.</p>
    </div>
    <div class="tag">RESULT</div>
</section>

<?php if (!$row): ?>
<section class="card">
    <h3>No Result Found</h3>
    <p>Please complete the analysis form first.</p>
    <a class="btn" href="analysis.php">Go to Analysis</a>
</section>
<?php else: ?>
<?php
    $declaredCategory = mbtiCategory($row['declared_mbti'] ?? '');
    $estimatedCategory = mbtiCategory($row['predicted_mbti'] ?? '');
    $declaredMbti = $row['declared_mbti'] ?: 'Not set';
    $estimatedMbti = $row['predicted_mbti'] ?: 'Not determined';
    $audioSummary = $row['genre']
        ? trim(($row['genre'] ?: '-') . ' / ' . ($row['energy_level'] ?: '-') . ' / ' . ($row['mood_type'] ?: '-'))
        : 'No audio data';
    $alignmentText = 'Persona could not be determined from available data.';
    if ($row['generated_persona'] === 'Authentic') {
        $alignmentText = 'Estimated signals align with the declared profile.';
    } elseif ($row['generated_persona'] === 'Creative Deviation') {
        $alignmentText = 'Estimated signals differ from the declared profile.';
    }
?>
<section class="result-clean-summary">
    <article class="result-profile-card">
        <span class="metric-label">Estimated MBTI</span>
        <h2><?= htmlspecialchars($estimatedMbti) ?></h2>
        <div class="result-profile-types">
            <span>Declared <strong><?= htmlspecialchars($declaredMbti) ?></strong></span>
            <?php if ($estimatedCategory): ?>
                <span>Category <strong><?= htmlspecialchars($estimatedCategory) ?></strong></span>
            <?php endif; ?>
        </div>
        <p><?= htmlspecialchars($alignmentText) ?></p>
    </article>

    <div class="result-facts-grid">
        <div class="result-fact">
            <span class="metric-label">Student</span>
            <strong><?= htmlspecialchars($row['student_name']) ?></strong>
            <small><?= htmlspecialchars($row['matric_no']) ?> | <?= htmlspecialchars($row['lab_group']) ?></small>
        </div>
        <div class="result-fact">
            <span class="metric-label">Persona</span>
            <strong><?= htmlspecialchars($row['generated_persona'] ?: '-') ?></strong>
            <small><?= htmlspecialchars($estimatedCategory ? $estimatedCategory . ' signal' : 'Estimated signal') ?></small>
        </div>
        <div class="result-fact">
            <span class="metric-label">Podcast</span>
            <strong><?= htmlspecialchars($row['podcast_title'] ?: '-') ?></strong>
            <small><?= htmlspecialchars($row['recommended_podcast'] ?: '-') ?></small>
        </div>
        <div class="result-fact">
            <span class="metric-label">Song</span>
            <strong><?= htmlspecialchars($row['recommended_song'] ?: '-') ?></strong>
            <small>Recommendation output</small>
        </div>
        <div class="result-fact">
            <span class="metric-label">Audio</span>
            <strong><?= htmlspecialchars($audioSummary) ?></strong>
            <small><?= htmlspecialchars($row['personality_tendency'] ?: 'CBR not available') ?></small>
        </div>
        <div class="result-fact">
            <span class="metric-label">Generated</span>
            <strong><?= htmlspecialchars($row['generated_date'] ?: '-') ?></strong>
            <small>Latest saved result</small>
        </div>
    </div>
</section>

<section class="table-card">
    <h2>Podcast Script</h2>
    <div class="podcast-script">
        <?= nl2br(htmlspecialchars($row['podcast_script'])) ?>
    </div>
</section>

<?php if ($row['genre']): ?>
<section class="table-card">
    <h2>Audio Content Features</h2>
    <div class="audio-feature-grid">
        <div><span class="metric-label">Tempo</span><strong><?= htmlspecialchars((string) $row['tempo_bpm']) ?> BPM</strong><small><?= htmlspecialchars($row['tempo_category'] ?? '-') ?></small></div>
        <div><span class="metric-label">RMS Energy</span><strong><?= htmlspecialchars((string) $row['rms_energy']) ?></strong><small><?= htmlspecialchars($row['energy_level']) ?></small></div>
        <div><span class="metric-label">Spectral Centroid</span><strong><?= htmlspecialchars((string) $row['spectral_centroid']) ?> Hz</strong><small><?= htmlspecialchars($row['genre']) ?></small></div>
        <div><span class="metric-label">Zero Crossing</span><strong><?= htmlspecialchars((string) $row['zero_crossing_rate']) ?></strong><small><?= htmlspecialchars($row['mood_type']) ?></small></div>
    </div>
    <p class="small-text">CBR is estimated from audio signal features and is not an accurate MBTI diagnosis.</p>
</section>
<?php endif; ?>

<section class="table-card">
    <h2>Record Details</h2>
    <div class="record-detail-list">
        <div><span>Student ID</span><strong><?= htmlspecialchars($row['student_id']) ?></strong></div>
        <div><span>Evidence File</span><strong class="truncate-text" title="<?= htmlspecialchars($row['file_name'] ?: '-') ?>"><?= htmlspecialchars($row['file_name'] ?: '-') ?></strong></div>
        <div><span>Media Category</span><strong><?= htmlspecialchars($row['media_category'] ?: '-') ?></strong></div>
        <div><span>Result ID</span><strong>#<?= htmlspecialchars((string) $row['result_id']) ?></strong></div>
    </div>
</section>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
