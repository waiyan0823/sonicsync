<?php
include 'includes/db.php';

$result_id = $_GET['id'] ?? 0;
$row = null;

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
<section class="grid-4">
    <div class="card">
        <h3>Estimated MBTI Signal</h3>
        <span class="result-badge"><?= htmlspecialchars($row['predicted_mbti'] ?: 'Not determined') ?></span>
        <p class="small-text" style="margin-top:12px">Prototype signal from TBR, with CBR fallback when needed</p>
    </div>
    <div class="card">
        <h3>Generated Persona</h3>
        <span class="result-badge"><?= htmlspecialchars($row['generated_persona']) ?></span>
        <p class="small-text" style="margin-top:12px">
            <?php if ($row['generated_persona'] === 'Authentic'): ?>
                The estimated signals <strong>align with</strong> the declared <?= htmlspecialchars($row['declared_mbti']) ?> profile.
            <?php elseif ($row['generated_persona'] === 'Creative Deviation'): ?>
                The estimated signals <strong>differ from</strong> the declared <?= htmlspecialchars($row['declared_mbti']) ?> profile.
            <?php else: ?>
                Persona could not be determined from available data.
            <?php endif; ?>
        </p>
    </div>
    <div class="card">
        <h3>Declared MBTI</h3>
        <span class="result-badge"><?= htmlspecialchars($row['declared_mbti'] ?: 'Not set') ?></span>
        <p class="small-text" style="margin-top:12px">Self-reported by student</p>
    </div>
    <div class="card">
        <h3>Podcast</h3>
        <span class="result-badge"><?= htmlspecialchars($row['podcast_title']) ?></span>
        <p class="small-text" style="margin-top:12px"><?= htmlspecialchars($row['recommended_podcast']) ?></p>
    </div>
</section>

<section class="table-card">
    <h2>Podcast Script</h2>
    <div class="podcast-script">
        <?= nl2br(htmlspecialchars($row['podcast_script'])) ?>
    </div>
</section>

<section class="grid-3">
    <div class="card">
        <h3>Recommended Song</h3>
        <span class="result-badge"><?= htmlspecialchars($row['recommended_song']) ?></span>
    </div>
    <div class="card">
        <h3>Student</h3>
        <p><?= htmlspecialchars($row['student_name']) ?></p>
        <p class="small-text"><?= htmlspecialchars($row['matric_no']) ?> | <?= htmlspecialchars($row['lab_group']) ?></p>
    </div>
    <div class="card">
        <h3>Uploaded File</h3>
        <p><?= htmlspecialchars($row['file_name']) ?></p>
        <p class="small-text"><?= htmlspecialchars($row['media_category']) ?></p>
    </div>
</section>

<?php if ($row['genre']): ?>
<section class="table-card">
    <h2>Audio Content Features (CBR Input)</h2>
    <div class="grid-3">
        <div><strong>Estimated Genre:</strong> <?= htmlspecialchars($row['genre']) ?></div>
        <div><strong>Estimated Energy:</strong> <?= htmlspecialchars($row['energy_level']) ?></div>
        <div><strong>Estimated Mood:</strong> <?= htmlspecialchars($row['mood_type']) ?></div>
    </div>
    <table style="margin-top:16px;">
        <tr><th>Tempo</th><td><?= htmlspecialchars((string) $row['tempo_bpm']) ?> BPM (<?= htmlspecialchars($row['tempo_category'] ?? '') ?>)</td></tr>
        <tr><th>RMS Energy</th><td><?= htmlspecialchars((string) $row['rms_energy']) ?></td></tr>
        <tr><th>Spectral Centroid</th><td><?= htmlspecialchars((string) $row['spectral_centroid']) ?> Hz</td></tr>
        <tr><th>Zero Crossing Rate</th><td><?= htmlspecialchars((string) $row['zero_crossing_rate']) ?></td></tr>
        <tr><th>CBR Personality Tendency</th><td><?= htmlspecialchars($row['personality_tendency'] ?? '') ?></td></tr>
    </table>
    <p class="small-text">CBR is estimated from audio signal features and is not an accurate MBTI diagnosis.</p>
</section>
<?php endif; ?>

<section class="table-card">
    <h2>Result Details</h2>
    <table>
        <tr><th>Student ID</th><td><?= htmlspecialchars($row['student_id']) ?></td></tr>
        <tr><th>Student Name</th><td><?= htmlspecialchars($row['student_name']) ?></td></tr>
        <tr><th>Declared MBTI</th><td><?= htmlspecialchars($row['declared_mbti'] ?: 'Not set') ?></td></tr>
        <tr><th>Estimated MBTI Signal</th><td><?= htmlspecialchars($row['predicted_mbti'] ?: 'Not determined') ?></td></tr>
        <tr><th>Generated Persona</th><td><?= htmlspecialchars($row['generated_persona']) ?></td></tr>
        <tr><th>Podcast Title</th><td><?= htmlspecialchars($row['podcast_title']) ?></td></tr>
        <tr><th>Recommended Song</th><td><?= htmlspecialchars($row['recommended_song']) ?></td></tr>
        <tr><th>Recommended Podcast</th><td><?= htmlspecialchars($row['recommended_podcast']) ?></td></tr>
        <tr><th>Generated At</th><td><?= htmlspecialchars($row['generated_date']) ?></td></tr>
    </table>
</section>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
