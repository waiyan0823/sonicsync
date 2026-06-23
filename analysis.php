<?php
include 'includes/db.php';

$message = '';
$error = '';
$selected_student_id = $_GET['student_id'] ?? $_POST['student_id'] ?? '';

$students = $conn->query("SELECT student_id, name FROM student ORDER BY name");

$prefill_genre = '';
$prefill_mood = '';
$prefill_tempo = '';
$prefill_energy = '';
$auto_loaded = false;
$pdf_asset = null;
$audio_asset = null;
$audio_analysis = null;
$cbr_error = '';
$submitted_poem_text = trim($_POST['poem_text'] ?? '');
$image_seed = filter_var($_POST['image_seed'] ?? null, FILTER_VALIDATE_INT);
if ($image_seed === false || $image_seed === null) {
    $image_seed = random_int(1, 999999999);
}

function dbColumnExists($conn, $table, $column) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && (bool) $result->fetch_assoc();
}

function ensureAudioFeatureColumns($conn) {
    $columns = [
        'tempo_bpm' => "ALTER TABLE audio_metadata ADD COLUMN tempo_bpm DECIMAL(8,2) DEFAULT NULL",
        'rms_energy' => "ALTER TABLE audio_metadata ADD COLUMN rms_energy DECIMAL(10,6) DEFAULT NULL",
        'spectral_centroid' => "ALTER TABLE audio_metadata ADD COLUMN spectral_centroid DECIMAL(10,2) DEFAULT NULL",
        'zero_crossing_rate' => "ALTER TABLE audio_metadata ADD COLUMN zero_crossing_rate DECIMAL(10,6) DEFAULT NULL",
        'tempo_category' => "ALTER TABLE audio_metadata ADD COLUMN tempo_category VARCHAR(20) DEFAULT ''",
        'personality_tendency' => "ALTER TABLE audio_metadata ADD COLUMN personality_tendency VARCHAR(100) DEFAULT ''",
        'audio_features_json' => "ALTER TABLE audio_metadata ADD COLUMN audio_features_json TEXT",
        'analysis_status' => "ALTER TABLE audio_metadata ADD COLUMN analysis_status VARCHAR(255) DEFAULT ''",
        'analyzed_at' => "ALTER TABLE audio_metadata ADD COLUMN analyzed_at DATETIME DEFAULT NULL",
    ];

    foreach ($columns as $column => $sql) {
        if (!dbColumnExists($conn, 'audio_metadata', $column)) {
            $conn->query($sql);
        }
    }
}

function audioDisplayUrl($filePath) {
    if (preg_match('/^https?:\/\//i', $filePath)) {
        return $filePath;
    }
    return str_replace('\\', '/', $filePath);
}

function audioAnalyzerSource($filePath) {
    if (preg_match('/^https?:\/\//i', $filePath)) {
        return $filePath;
    }

    $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);
    $localFile = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relativePath);
    return $localFile ?: $filePath;
}

function normalizeLecturerAudioPath($audioPath) {
    $audioPath = trim((string) $audioPath);
    if (preg_match('/^https?:\/\//i', $audioPath)) {
        return $audioPath;
    }
    if (str_starts_with($audioPath, '/2026/all/uploads/')) {
        return 'https://bitp3353.utem.edu.my' . $audioPath;
    }
    return $audioPath;
}

function findStudentAudioAsset($conn, $student_id) {
    $stmt = $conn->prepare("
        SELECT ma.asset_id, ma.student_id, ma.file_name, ma.file_type, ma.file_size, ma.file_path,
               am.audio_id, am.duration, am.energy_level, am.mood_type, am.genre,
               am.tempo_bpm, am.rms_energy, am.spectral_centroid, am.zero_crossing_rate,
               am.tempo_category, am.personality_tendency, am.audio_features_json,
               am.analysis_status, am.analyzed_at
        FROM multimedia_asset ma
        LEFT JOIN audio_metadata am ON ma.asset_id = am.asset_id
        WHERE ma.student_id = ?
          AND (ma.media_category = 'Audio' OR LOWER(ma.file_type) IN ('mp3','wav','m4a','ogg','flac'))
        ORDER BY ma.upload_date DESC, ma.asset_id DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($asset) {
        if (!$asset['audio_id']) {
            $insert = $conn->prepare("INSERT INTO audio_metadata (asset_id) VALUES (?)");
            $insert->bind_param('i', $asset['asset_id']);
            $insert->execute();
            $asset['audio_id'] = $insert->insert_id;
            $insert->close();
        }
        return $asset;
    }

    return importAudioFromVstu($conn, $student_id);
}

function importAudioFromVstu($conn, $student_id) {
    try {
        $columnsResult = $conn->query("SHOW COLUMNS FROM `mmdb2026`.`vstu`");
    } catch (mysqli_sql_exception $e) {
        return null;
    }
    if (!$columnsResult) {
        return null;
    }

    $columns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $studentColumns = ['student_id', 'matric_no', 'matric', 'id'];
    $audioColumns = ['audio_file', 'audio_path', 'audio_url', 'music_file', 'music_path', 'song_file', 'song_url', 'file_path'];
    $studentColumn = null;
    $audioColumn = null;

    foreach ($studentColumns as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $studentColumn = $candidate;
            break;
        }
    }
    foreach ($audioColumns as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $audioColumn = $candidate;
            break;
        }
    }
    if (!$audioColumn) {
        foreach ($columns as $column) {
            if (preg_match('/audio|music|song|mp3|wav|m4a/i', $column)) {
                $audioColumn = $column;
                break;
            }
        }
    }
    if (!$studentColumn || !$audioColumn) {
        return null;
    }

    $sql = "SELECT `$audioColumn` AS audio_path FROM `mmdb2026`.`vstu` WHERE `$studentColumn` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $audioPath = normalizeLecturerAudioPath($row['audio_path'] ?? '');
    if ($audioPath === '') {
        return null;
    }

    $pathForName = parse_url($audioPath, PHP_URL_PATH) ?: $audioPath;
    $fileName = basename($pathForName) ?: ($student_id . '_audio');
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'mp3';
    $fileSize = 0;

    $insertAsset = $conn->prepare("INSERT INTO multimedia_asset (student_id, file_name, file_type, file_size, file_path, description, tags, media_category) VALUES (?, ?, ?, ?, ?, 'Imported from mmdb2026.vstu for CBR analysis', 'vstu,audio,cbr', 'Audio')");
    $insertAsset->bind_param('sssis', $student_id, $fileName, $fileType, $fileSize, $audioPath);
    $insertAsset->execute();
    $assetId = $insertAsset->insert_id;
    $insertAsset->close();

    $insertAudio = $conn->prepare("INSERT INTO audio_metadata (asset_id, song_title, artist_or_creator) VALUES (?, ?, 'Unknown')");
    $insertAudio->bind_param('is', $assetId, $fileName);
    $insertAudio->execute();
    $audioId = $insertAudio->insert_id;
    $insertAudio->close();

    return [
        'asset_id' => $assetId,
        'student_id' => $student_id,
        'file_name' => $fileName,
        'file_type' => $fileType,
        'file_size' => $fileSize,
        'file_path' => $audioPath,
        'audio_id' => $audioId,
        'duration' => null,
        'energy_level' => '',
        'mood_type' => '',
        'genre' => '',
        'tempo_bpm' => null,
        'rms_energy' => null,
        'spectral_centroid' => null,
        'zero_crossing_rate' => null,
        'tempo_category' => '',
        'personality_tendency' => '',
        'audio_features_json' => null,
        'analysis_status' => '',
        'analyzed_at' => null,
    ];
}

function runPythonAudioAnalyzer($source) {
    $script = __DIR__ . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'analyze_audio.py';
    if (!is_file($script)) {
        return ['ok' => false, 'error' => 'Audio analyzer script is missing.'];
    }

    $configured = trim((string) getenv('SONICSYNC_PYTHON'));
    $commands = $configured ? [$configured] : ['py', 'python', 'python3'];
    foreach ($commands as $python) {
        $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($source) . ' 2>&1';
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);
        $raw = trim(implode("\n", $output));
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        if ($exitCode === 0 && $raw !== '') {
            return ['ok' => false, 'error' => 'Audio analyzer returned invalid JSON: ' . $raw];
        }
    }

    return ['ok' => false, 'error' => 'Python was not found. Set SONICSYNC_PYTHON or install Python with librosa and numpy.'];
}

function saveAudioAnalysis($conn, $audio_id, $analysis) {
    if (!$audio_id || empty($analysis['ok'])) {
        return;
    }

    $duration = (int) round((float) ($analysis['duration_seconds'] ?? 0));
    $energy = (string) ($analysis['energy_level'] ?? '');
    $mood = (string) ($analysis['estimated_mood'] ?? '');
    $genre = (string) ($analysis['estimated_genre'] ?? '');
    $tempoCategory = (string) ($analysis['tempo_category'] ?? '');
    $tendency = (string) ($analysis['personality_tendency'] ?? '');
    $tempoBpm = (float) ($analysis['tempo_bpm'] ?? 0);
    $rms = (float) ($analysis['rms_energy'] ?? 0);
    $centroid = (float) ($analysis['spectral_centroid'] ?? 0);
    $zcr = (float) ($analysis['zero_crossing_rate'] ?? 0);
    $json = json_encode($analysis, JSON_UNESCAPED_SLASHES);
    $status = 'Analyzed from audio content';

    $stmt = $conn->prepare("
        UPDATE audio_metadata
        SET duration = ?, energy_level = ?, mood_type = ?, genre = ?, tempo_category = ?,
            personality_tendency = ?, tempo_bpm = ?, rms_energy = ?, spectral_centroid = ?,
            zero_crossing_rate = ?, audio_features_json = ?, analysis_status = ?, analyzed_at = NOW()
        WHERE audio_id = ?
    ");
    $stmt->bind_param('isssssddddssi', $duration, $energy, $mood, $genre, $tempoCategory, $tendency, $tempoBpm, $rms, $centroid, $zcr, $json, $status, $audio_id);
    $stmt->execute();
    $stmt->close();
}

function audioAnalysisFromDb($asset) {
    if (!$asset || !$asset['analyzed_at']) {
        return null;
    }

    return [
        'ok' => true,
        'tempo_bpm' => (float) $asset['tempo_bpm'],
        'rms_energy' => (float) $asset['rms_energy'],
        'spectral_centroid' => (float) $asset['spectral_centroid'],
        'zero_crossing_rate' => (float) $asset['zero_crossing_rate'],
        'duration_seconds' => (float) $asset['duration'],
        'estimated_genre' => $asset['genre'],
        'estimated_mood' => $asset['mood_type'],
        'tempo_category' => $asset['tempo_category'],
        'energy_level' => $asset['energy_level'],
        'personality_tendency' => $asset['personality_tendency'],
    ];
}

function mbtiFromAxes($axes) {
    return $axes['IE'] . $axes['SN'] . $axes['TF'] . $axes['JP'];
}

function personaFromMbti($mbti) {
    $personas = [
        'INFP' => 'Reflective Idealist',
        'INFJ' => 'Insightful Guide',
        'INTJ' => 'Strategic Thinker',
        'INTP' => 'Curious Analyst',
        'ISFP' => 'Gentle Creator',
        'ISFJ' => 'Supportive Guardian',
        'ISTJ' => 'Steady Organizer',
        'ISTP' => 'Calm Problem Solver',
        'ENFP' => 'Expressive Explorer',
        'ENFJ' => 'Warm Motivator',
        'ENTP' => 'Inventive Debater',
        'ENTJ' => 'Decisive Leader',
        'ESFP' => 'Energetic Performer',
        'ESFJ' => 'Social Caregiver',
        'ESTP' => 'Bold Problem Solver',
        'ESTJ' => 'Practical Leader',
    ];

    return $personas[$mbti] ?? 'Balanced Personality Signal';
}

function detectAxisScores($text, $groups) {
    $scores = [];
    $found = [];

    foreach ($groups as $axis => $words) {
        $scores[$axis] = 0;
        foreach ($words as $word) {
            if (strpos($text, $word) !== false) {
                $scores[$axis]++;
                $found[] = $word;
            }
        }
    }

    return [$scores, array_values(array_unique($found))];
}

function pickAxisLetter($leftLetter, $leftScore, $rightLetter, $rightScore, $defaultLetter) {
    if ($leftScore > $rightScore) {
        return $leftLetter;
    }
    if ($rightScore > $leftScore) {
        return $rightLetter;
    }
    return $defaultLetter;
}

function predictTBR($text) {
    $text = strtolower($text);
    $groups = [
        'I' => ['calm','quiet','alone','peaceful','reflect','deep','silence','soft','slow','mind','thought','whisper','solitude','gentle'],
        'E' => ['party','crowd','excited','social','energy','friends','active','adventure','fun','together','challenge','run','bright','bold'],
        'S' => ['detail','real','touch','sound','color','observe','present','practical','clear','shape','scene','texture'],
        'N' => ['dream','future','imagine','symbol','meaning','possibility','wonder','vision','abstract','story','idea','hope'],
        'T' => ['logic','reason','analyze','decide','fact','truth','measure','order','plan','system','evidence','structure'],
        'F' => ['feel','heart','emotion','kind','care','love','empathy','warm','gentle','sad','happy','soul'],
        'J' => ['plan','organize','certain','steady','clear','decide','prepared','order','focus','control','finish','structured'],
        'P' => ['explore','free','open','spontaneous','curious','adapt','flow','discover','change','play','improvise','wander'],
    ];

    [$scores, $found] = detectAxisScores($text, $groups);
    $axes = [
        'IE' => pickAxisLetter('I', $scores['I'], 'E', $scores['E'], 'I'),
        'SN' => pickAxisLetter('S', $scores['S'], 'N', $scores['N'], 'N'),
        'TF' => pickAxisLetter('T', $scores['T'], 'F', $scores['F'], 'F'),
        'JP' => pickAxisLetter('J', $scores['J'], 'P', $scores['P'], 'P'),
    ];
    $mbti = mbtiFromAxes($axes);

    return [
        'mbti' => $mbti,
        'persona' => personaFromMbti($mbti),
        'keywords' => implode(', ', $found),
        'axes' => $axes,
    ];
}

function predictCBR($genre, $mood, $tempo, $energy) {
    $scores = [
        'I' => 0, 'E' => 0,
        'S' => 0, 'N' => 0,
        'T' => 0, 'F' => 0,
        'J' => 0, 'P' => 0,
    ];

    if (in_array($genre, ['Lofi','Acoustic','Jazz','Instrumental','Classical'], true)) {
        $scores['I'] += 2;
        $scores['N'] += 1;
        $scores['F'] += 1;
    } else {
        $scores['E'] += 2;
        $scores['S'] += 1;
        $scores['P'] += 1;
    }

    if (in_array($mood, ['Calm','Sad','Reflective','Balanced'], true)) {
        $scores['I'] += 1;
        $scores['F'] += 2;
        $scores['J'] += 1;
    } else {
        $scores['E'] += 1;
        $scores['T'] += 1;
        $scores['P'] += 2;
    }

    if ($tempo === 'Slow') {
        $scores['I'] += 1;
        $scores['J'] += 2;
        $scores['N'] += 1;
    } elseif ($tempo === 'Fast') {
        $scores['E'] += 1;
        $scores['S'] += 2;
        $scores['P'] += 1;
    }

    if ($energy === 'Low') {
        $scores['I'] += 2;
        $scores['F'] += 1;
    } elseif ($energy === 'High') {
        $scores['E'] += 2;
        $scores['T'] += 1;
    }

    $axes = [
        'IE' => pickAxisLetter('I', $scores['I'], 'E', $scores['E'], 'E'),
        'SN' => pickAxisLetter('S', $scores['S'], 'N', $scores['N'], 'S'),
        'TF' => pickAxisLetter('T', $scores['T'], 'F', $scores['F'], 'F'),
        'JP' => pickAxisLetter('J', $scores['J'], 'P', $scores['P'], 'P'),
    ];
    $mbti = mbtiFromAxes($axes);
    $tendency = $axes['IE'] === 'I' ? 'Reflective Introvert' : 'Expressive Extrovert';

    return [
        'mbti' => $mbti,
        'persona' => personaFromMbti($mbti),
        'tendency' => $tendency,
        'axes' => $axes,
    ];
}

function combineMbtiSignals($tbr, $cbr, $declaredMbti) {
    $scores = [
        'I' => 0, 'E' => 0,
        'S' => 0, 'N' => 0,
        'T' => 0, 'F' => 0,
        'J' => 0, 'P' => 0,
    ];

    if (!empty($tbr['axes'])) {
        $scores[$tbr['axes']['IE']] += 3;
        $scores[$tbr['axes']['SN']] += 2;
        $scores[$tbr['axes']['TF']] += 2;
        $scores[$tbr['axes']['JP']] += 2;
    }

    if (!empty($cbr['axes'])) {
        $scores[$cbr['axes']['IE']] += 2;
        $scores[$cbr['axes']['SN']] += 1;
        $scores[$cbr['axes']['TF']] += 1;
        $scores[$cbr['axes']['JP']] += 1;
    }

    $declaredMbti = strtoupper(trim((string) $declaredMbti));
    if (preg_match('/^[EI][SN][TF][JP]$/', $declaredMbti)) {
        $scores[$declaredMbti[0]] += 1;
        $scores[$declaredMbti[1]] += 1;
        $scores[$declaredMbti[2]] += 1;
        $scores[$declaredMbti[3]] += 1;
    }

    $axes = [
        'IE' => pickAxisLetter('I', $scores['I'], 'E', $scores['E'], 'I'),
        'SN' => pickAxisLetter('S', $scores['S'], 'N', $scores['N'], 'N'),
        'TF' => pickAxisLetter('T', $scores['T'], 'F', $scores['F'], 'F'),
        'JP' => pickAxisLetter('J', $scores['J'], 'P', $scores['P'], 'P'),
    ];

    return mbtiFromAxes($axes);
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
    $mbtiLabel = trim((string) $declaredMbti);
    if ($mbtiLabel === '') {
        $mbtiLabel = $finalMbti ?: 'Not provided';
    }

    $script = "Welcome to " . $p['podcast'] . ". Today we feature " . $studentName
        . ", whose declared MBTI is " . $mbtiLabel
        . ". The system has estimated this profile as " . $personaType
        . ". Detected keywords — " . $keywords
        . ". " . $studentName . "'s musical preference of " . $genre
        . " supports an audio-based personality tendency that is "
        . ($personaType === 'Authentic' ? 'consistent and self-aware.' : 'creatively expressive and dynamically evolving.')
        . " This is an estimate from audio features, not a definitive MBTI diagnosis."
        . " This episode's recommended track is \"" . $song . "\".";

    return [
        'podcast_title' => $p['title'],
        'podcast_script' => $script,
        'recommended_song' => $song,
        'recommended_podcast' => $p['podcast']
    ];
}

function runAnalysis($conn, $student_id, $image_description, $audioAnalysis) {
    $stmt = $conn->prepare("SELECT name, mbti_type FROM student WHERE student_id = ?");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    if (!$student) return null;

    $combined_tbr = trim($image_description);
    $tbr = null;
    if ($combined_tbr !== '') {
        $tbr = predictTBR($combined_tbr);
        $tbr_mbti = $tbr['mbti'];
        $keywords = $tbr['keywords'] ?: 'N/A';
    } else {
        $tbr_mbti = 'N/A';
        $keywords = 'N/A';
    }

    $genre = $audioAnalysis['estimated_genre'] ?? '';
    $mood = $audioAnalysis['estimated_mood'] ?? '';
    $tempo = $audioAnalysis['tempo_category'] ?? '';
    $energy = $audioAnalysis['energy_level'] ?? '';

    $cbr = null;
    if (!empty($audioAnalysis['ok']) && $genre && $mood && $tempo && $energy) {
        $cbr = predictCBR($genre, $mood, $tempo, $energy);
        $cbr_personality = $cbr['tendency'];
        $cbr_mbti = $cbr['mbti'];
    } else {
        $cbr_personality = 'N/A';
        $cbr_mbti = 'N/A';
    }

    if ($tbr || $cbr) {
        $final_mbti = combineMbtiSignals($tbr ?? [], $cbr ?? [], $student['mbti_type'] ?? '');
    } else {
        $final_mbti = $student['mbti_type'] ?: 'INFP';
    }

    $persona_type = ($student['mbti_type'] && $final_mbti !== 'N/A' && strtoupper($student['mbti_type']) === strtoupper($final_mbti))
        ? 'Authentic' : 'Creative Deviation';

    $podcast = generatePodcast($final_mbti, $student['name'], $persona_type, $genre ?: 'General', $keywords, $student['mbti_type']);

    $audio_id = null;
    $stmt = $conn->prepare("SELECT am.audio_id FROM multimedia_asset ma JOIN audio_metadata am ON ma.asset_id = am.asset_id WHERE ma.student_id = ? LIMIT 1");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) $audio_id = $r['audio_id'];

    if ($audio_id && $genre && $mood && $energy) {
        $stmt = $conn->prepare("UPDATE audio_metadata SET genre = ?, mood_type = ?, energy_level = ?, lyrics_keywords = ? WHERE audio_id = ?");
        $stmt->bind_param('ssssi', $genre, $mood, $energy, $keywords, $audio_id);
        $stmt->execute();
    }

    $stmt = $conn->prepare("INSERT INTO recommendation_result (student_id, audio_id, predicted_mbti, generated_persona, podcast_title, podcast_script, recommended_song, recommended_podcast) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sissssss', $student_id, $audio_id, $final_mbti, $persona_type, $podcast['podcast_title'], $podcast['podcast_script'], $podcast['recommended_song'], $podcast['recommended_podcast']);
    $stmt->execute();

    return $conn->insert_id;
}

ensureAudioFeatureColumns($conn);

// Load the selected student's lecturer media for review before analysis.
if ($selected_student_id) {
    $stmt = $conn->prepare("
        SELECT asset_id, file_name, file_path
        FROM multimedia_asset
        WHERE student_id = ?
          AND media_category = 'PDF'
          AND (
              LOWER(file_name) LIKE '%.pdf'
              OR LOWER(file_path) LIKE '%.pdf'
              OR LOWER(file_type) = 'pdf'
          )
        ORDER BY upload_date DESC, asset_id DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $selected_student_id);
    $stmt->execute();
    $pdf_asset = $stmt->get_result()->fetch_assoc();

    $audio_asset = findStudentAudioAsset($conn, $selected_student_id);
    if ($audio_asset) {
        $audio_analysis = audioAnalysisFromDb($audio_asset);
        if (!$audio_analysis) {
            $audio_analysis = runPythonAudioAnalyzer(audioAnalyzerSource($audio_asset['file_path']));
            if (!empty($audio_analysis['ok'])) {
                saveAudioAnalysis($conn, (int) $audio_asset['audio_id'], $audio_analysis);
                $audio_asset = findStudentAudioAsset($conn, $selected_student_id);
            } else {
                $cbr_error = $audio_analysis['error'] ?? 'Audio analysis failed.';
            }
        }
    }
}

// Manual form submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $sid = trim($_POST['student_id'] ?? '');
    $desc = trim($_POST['image_description'] ?? '');
    $poem_text = trim($_POST['poem_text'] ?? '');
    $full_text = trim($desc . ' ' . $poem_text);

    if ($sid === '') {
        $error = 'Please select a student.';
    } elseif ($desc === '') {
        $error = 'Describe the random image before generating the TBR personality result.';
    } else {
        if ($pdf_asset && $poem_text !== '') {
            $stmt = $conn->prepare("UPDATE multimedia_asset SET description = ? WHERE asset_id = ? AND student_id = ?");
            $stmt->bind_param('sis', $poem_text, $pdf_asset['asset_id'], $sid);
            $stmt->execute();
        }
        $result_id = runAnalysis($conn, $sid, $full_text, $audio_analysis ?: []);
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
        <p>Step 2 of 3: Describe the random image for TBR, then optionally add poem and audio evidence.</p>
    </div>
    <div class="tag">ANALYSIS</div>
</section>

<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="info-box">
    <h4>How the analysis works</h4>
    The system analyzes the student's image description, poem, and audio file to estimate personality tendencies. The result is generated based on content analysis and should be treated as an estimation rather than an actual MBTI diagnosis.
</div>

<form method="POST">
    <section class="form-card">
        <h2>Student</h2>
        <label>Select Student</label>
        <select name="student_id" id="analysis-student-select" required>
            <option value="">-- Select --</option>
            <?php $students->data_seek(0); while ($s = $students->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($s['student_id']) ?>" <?= $s['student_id'] === $selected_student_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['student_id'] . ' - ' . $s['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </section>

    <section class="analysis-module tbr">
        <legend>TBR Analysis <span class="module-tag tbr">Text-Based Retrieval</span></legend>
        <h3>Random Image Description <span class="module-tag tbr">Required</span></h3>
        <p class="small-text">Look at the generated image and describe what you see, feel, or imagine. TBR analyzes only the words the student writes, not the image pixels.</p>
        <div class="analysis-layout">
            <div class="image-box">
                <img id="tbr-random-image" src="random_tbr_image.php?seed=<?= (int) $image_seed ?>" alt="Random visual prompt for student description">
                <button class="btn secondary" id="new-random-image" type="button" style="width:100%;margin:0;border-radius:0;">Generate Another Image</button>
            </div>
            <div>
                <label for="image-description">Student's Manual Description</label>
                <textarea id="image-description" name="image_description" required minlength="10" placeholder="Example: This scene feels calm and peaceful. It makes me reflect quietly about the future."><?= htmlspecialchars($_POST['image_description'] ?? '') ?></textarea>
                <input id="image-seed" type="hidden" name="image_seed" value="<?= (int) $image_seed ?>">
                <p class="small-text">Write at least one complete sentence. The description is the main TBR input used to estimate a personality signal.</p>
            </div>
        </div>
        <h3>Student Poem <span class="module-tag tbr">Optional Support</span></h3>
        <?php if (!$selected_student_id): ?>
            <p class="small-text">Select a student above. Their lecturer-uploaded poem will appear here automatically.</p>
        <?php elseif (!$pdf_asset): ?>
            <div class="alert error">No poem PDF was found for this student. Choose another student or upload a PDF first.</div>
            <label for="poem-text">Poem Text</label>
            <textarea id="poem-text" name="poem_text" rows="10" placeholder="Paste the poem text here if the PDF is unavailable."><?= htmlspecialchars($submitted_poem_text) ?></textarea>
        <?php else: ?>
            <p class="small-text" id="poem-status">Loading and reading <?= htmlspecialchars($pdf_asset['file_name']) ?>...</p>
            <iframe
                src="media_proxy.php?asset_id=<?= (int) $pdf_asset['asset_id'] ?>"
                title="<?= htmlspecialchars($pdf_asset['file_name']) ?>"
                style="width:100%;height:560px;border:1px solid var(--border);border-radius:12px;background:#fff;"
            ></iframe>
            <label for="poem-text" style="margin-top:16px;">Extracted Poem Text <span class="module-tag tbr">TBR Input</span></label>
            <textarea id="poem-text" name="poem_text" rows="12" placeholder="The PDF text will appear here. You can correct it before analysis."><?= htmlspecialchars($submitted_poem_text) ?></textarea>
            <p class="small-text">The extracted text is editable so scanned or unusually formatted PDFs can be corrected before analysis.</p>
        <?php endif; ?>
    </section>

    <section class="analysis-module cbr">
        <legend>CBR Analysis <span class="module-tag cbr">Content-Based Retrieval</span></legend>
        <p class="small-text">CBR is performed from the student's actual audio content. No manual genre, mood, tempo, or energy values are entered here.</p>

        <?php if (!$selected_student_id): ?>
            <p class="small-text">Select a student above to load their audio file for CBR analysis.</p>
        <?php elseif (!$audio_asset): ?>
            <div class="alert error">No audio file was found for this student in the multimedia table or mmdb2026.vstu.</div>
        <?php else: ?>
            <div class="table-card" style="margin-bottom:16px;">
                <h2>Retrieved Audio File</h2>
                <p><strong><?= htmlspecialchars($audio_asset['file_name']) ?></strong></p>
                <audio controls preload="metadata" style="width:100%;margin-top:10px;">
                    <source src="<?= htmlspecialchars(audioDisplayUrl($audio_asset['file_path'])) ?>" type="audio/<?= htmlspecialchars($audio_asset['file_type'] ?: 'mpeg') ?>">
                    Your browser does not support the audio player.
                </audio>
                <p class="small-text" style="margin-top:10px;">Source: <?= htmlspecialchars($audio_asset['file_path']) ?></p>
            </div>

            <?php if ($cbr_error): ?>
                <div class="alert error"><?= htmlspecialchars($cbr_error) ?></div>
            <?php elseif (!empty($audio_analysis['ok'])): ?>
                <div class="grid-4">
                    <div class="card">
                        <h3>Estimated Genre</h3>
                        <span class="result-badge"><?= htmlspecialchars($audio_analysis['estimated_genre']) ?></span>
                    </div>
                    <div class="card">
                        <h3>Estimated Mood</h3>
                        <span class="result-badge"><?= htmlspecialchars($audio_analysis['estimated_mood']) ?></span>
                    </div>
                    <div class="card">
                        <h3>Tempo Category</h3>
                        <span class="result-badge"><?= htmlspecialchars($audio_analysis['tempo_category']) ?></span>
                    </div>
                    <div class="card">
                        <h3>Energy Level</h3>
                        <span class="result-badge"><?= htmlspecialchars($audio_analysis['energy_level']) ?></span>
                    </div>
                </div>

                <section class="table-card">
                    <h2>Extracted Audio Features</h2>
                    <table>
                        <tr><th>Tempo</th><td><?= htmlspecialchars((string) $audio_analysis['tempo_bpm']) ?> BPM</td></tr>
                        <tr><th>RMS Energy</th><td><?= htmlspecialchars((string) $audio_analysis['rms_energy']) ?></td></tr>
                        <tr><th>Spectral Centroid</th><td><?= htmlspecialchars((string) $audio_analysis['spectral_centroid']) ?> Hz</td></tr>
                        <tr><th>Zero Crossing Rate</th><td><?= htmlspecialchars((string) $audio_analysis['zero_crossing_rate']) ?></td></tr>
                        <tr><th>Duration</th><td><?= htmlspecialchars((string) $audio_analysis['duration_seconds']) ?> seconds</td></tr>
                        <tr><th>CBR Personality Tendency</th><td><?= htmlspecialchars($audio_analysis['personality_tendency']) ?></td></tr>
                    </table>
                    <p class="small-text">This tendency is estimated from audio signal features only. It does not claim to accurately determine MBTI.</p>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <button class="btn" id="generate-analysis" type="submit">Generate Analysis</button>
</form>

<script>
document.getElementById('analysis-student-select').addEventListener('change', function () {
    window.location.href = this.value
        ? `analysis.php?student_id=${encodeURIComponent(this.value)}`
        : 'analysis.php';
});

document.getElementById('new-random-image').addEventListener('click', function () {
    const seed = Math.floor(Math.random() * 999999999) + 1;
    document.getElementById('image-seed').value = seed;
    document.getElementById('tbr-random-image').src = `random_tbr_image.php?seed=${seed}`;
    document.getElementById('image-description').value = '';
    document.getElementById('image-description').focus();
});
</script>

<?php if ($pdf_asset): ?>
<script src="assets/vendor/pdfjs/pdf.min.js"></script>
<script>
const poemText = document.getElementById('poem-text');
const poemStatus = document.getElementById('poem-status');
const analysisButton = document.getElementById('generate-analysis');
const pdfUrl = 'media_proxy.php?asset_id=<?= (int) $pdf_asset['asset_id'] ?>';

function normalizeExtractedText(text) {
    return text
        .replace(/(?:\b[A-Za-z]\b[ \t])+\b[A-Za-z]\b/g, match => match.replace(/[ \t]/g, ''))
        .replace(/[ \t]{2,}/g, ' ')
        .replace(/ *\n */g, '\n')
        .trim();
}

async function loadPoemText() {
    if (poemText.value.trim()) {
        poemStatus.textContent = 'Poem text is ready for TBR analysis.';
        return;
    }

    analysisButton.disabled = true;

    try {
        if (!window.pdfjsLib) throw new Error('PDF reader could not be loaded.');
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/vendor/pdfjs/pdf.worker.min.js';
        const pdf = await pdfjsLib.getDocument(pdfUrl).promise;
        const pages = [];

        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
            const page = await pdf.getPage(pageNumber);
            const content = await page.getTextContent();
            pages.push(content.items.map(item => item.str).join(' '));
        }

        poemText.value = normalizeExtractedText(pages.join('\n\n'));
        poemStatus.textContent = poemText.value
            ? 'Poem loaded from the lecturer system and ready for TBR analysis.'
            : 'The PDF contains no readable text. Type the poem into the box below before analysis.';
    } catch (error) {
        poemStatus.textContent = `${error.message} Type the poem into the box below before analysis.`;
    } finally {
        analysisButton.disabled = false;
    }
}

loadPoemText();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
