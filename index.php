<?php include 'includes/db.php'; ?>
<?php
$studentCount = $conn->query("SELECT COUNT(*) AS c FROM student")->fetch_assoc()['c'];
$assetCount = $conn->query("SELECT COUNT(*) AS c FROM multimedia_asset")->fetch_assoc()['c'];
$resultCount = $conn->query("SELECT COUNT(*) AS c FROM recommendation_result")->fetch_assoc()['c'];
$audioCount = $conn->query("SELECT COUNT(*) AS c FROM audio_metadata")->fetch_assoc()['c'];
?>
<?php include 'includes/header.php'; ?>
<section class="hero">
    <div>
        <h1>Multimedia MBTI Portal</h1>
        <p>SonicSync — a multimedia-based MBTI profiling system using TBR (Text-Based Retrieval), CBR (Content-Based Retrieval), and ABR (Attribute-Based Retrieval) methods.</p>
    </div>
    <div class="tag">BITP 3353</div>
</section>

<section class="grid-4 stats">
    <div class="card stat-card">
        <div class="stat-number"><?= $studentCount ?></div>
        <h3>Students</h3>
        <p>Registered with declared MBTI</p>
    </div>
    <div class="card stat-card">
        <div class="stat-number"><?= $assetCount ?></div>
        <h3>Uploads</h3>
        <p>Multimedia assets submitted</p>
    </div>
    <div class="card stat-card">
        <div class="stat-number"><?= $audioCount ?></div>
        <h3>Audio Metadata</h3>
        <p>Extracted for CBR analysis</p>
    </div>
    <div class="card stat-card">
        <div class="stat-number"><?= $resultCount ?></div>
        <h3>Results</h3>
        <p>Recommendations generated</p>
    </div>
</section>

<section class="pipeline-section">
    <h2>How SonicSync Works</h2>
    <p class="small-text">A 4-step pipeline: register/upload → analyze personality → view recommendation → search across methods</p>
    <div class="flow-pipeline" style="margin-top:16px">
        <div class="flow-step active">
            <div class="step-num">01</div>
            <h4>Upload</h4>
            <p>Student registers and uploads an image, audio, or PDF file with metadata</p>
        </div>
        <div class="flow-step">
            <div class="step-num" style="font-size:24px;opacity:0.4">→</div>
        </div>
        <div class="flow-step">
            <div class="step-num">02</div>
            <h4>Analyze</h4>
            <p>TBR scans text keywords; CBR scores audio characteristics to predict MBTI</p>
        </div>
        <div class="flow-step">
            <div class="step-num" style="font-size:24px;opacity:0.4">→</div>
        </div>
        <div class="flow-step">
            <div class="step-num">03</div>
            <h4>Result</h4>
            <p>Persona is generated (Authentic / Creative Deviation) with a podcast recommendation</p>
        </div>
        <div class="flow-step">
            <div class="step-num" style="font-size:24px;opacity:0.4">→</div>
        </div>
        <div class="flow-step">
            <div class="step-num">04</div>
            <h4>Retrieve</h4>
            <p>Search all data using ABR, TBR, or CBR retrieval strategies</p>
        </div>
    </div>
</section>

<section class="table-card">
    <h2>Retrieval Methods</h2>
    <div class="retrieval-method-card abr">
        <h4>ABR <span class="module-tag abr">Attribute-Based</span></h4>
        <p>Filter records by structured attributes: MBTI type, genre, energy level, mood, lab group, file type, and generated persona. Queries across all 4 tables using exact-match conditions.</p>
    </div>
    <div class="retrieval-method-card tbr">
        <h4>TBR <span class="module-tag tbr">Text-Based</span></h4>
        <p>Search for keywords across image/PDF descriptions, tags, lyrics keywords, podcast scripts, and persona labels. Uses LIKE pattern matching on text fields.</p>
    </div>
    <div class="retrieval-method-card cbr">
        <h4>CBR <span class="module-tag cbr">Content-Based</span></h4>
        <p>Filter audio content by genre, energy level, mood, and file type. Matches audio characteristics to personality tendencies (e.g., calm/Lofi → introvert).</p>
    </div>
</section>

<section>
    <h2>Database Schema</h2>
    <p class="small-text">4 normalized tables storing the complete multimedia profiling pipeline</p>
    <div class="schema-map">
        <div class="schema-table">
            <h5>student</h5>
            <p>student_id, name, mbti_type, lab_group</p>
            <div class="arrow">↓</div>
            <p>Primary identity &amp; declared MBTI</p>
        </div>
        <div class="schema-table">
            <h5>multimedia_asset</h5>
            <p>asset_id, student_id, file, description, tags</p>
            <div class="arrow">↓</div>
            <p>Uploaded files with text metadata</p>
        </div>
        <div class="schema-table">
            <h5>audio_metadata</h5>
            <p>audio_id, asset_id, genre, mood, energy</p>
            <div class="arrow">↓</div>
            <p>Audio characteristics for CBR</p>
        </div>
        <div class="schema-table">
            <h5>recommendation_result</h5>
            <p>result_id, student_id, persona, podcast</p>
            <div class="arrow">↓</div>
            <p>Generated MBTI &amp; recommendations</p>
        </div>
    </div>
</section>
<?php include 'includes/footer.php'; ?>
