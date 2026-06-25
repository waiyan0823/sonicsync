<?php include 'includes/db.php'; ?>
<?php include 'includes/header.php'; ?>

<section class="dashboard-hero">
    <div class="dashboard-hero-copy">
        <span class="dashboard-eyebrow">PERSONALITY DISCOVERY</span>
        <h1>Understand the person behind the media.</h1>
        <p>Select a student, explore their creative inputs, and turn their words and music preferences into a clear personality profile.</p>
        <div class="dashboard-actions">
            <a class="btn" href="<?= appLink('analysis.php', $groupCode) ?>">Open SonicSync System <span aria-hidden="true">→</span></a>
            <a class="btn secondary" href="<?= htmlspecialchars($lecturerDashboardUrl, ENT_QUOTES, 'UTF-8') ?>">Back to Lecturer Dashboard</a>
        </div>
        <div class="dashboard-assurance">
            <span>Guided workflow</span>
            <span>Student-focused</span>
            <span>Immediate result</span>
        </div>
    </div>

    <div class="identity-visual" aria-hidden="true">
        <div class="identity-ring ring-one"></div>
        <div class="identity-ring ring-two"></div>
        <div class="identity-core">
            <span>SS</span>
            <small>PROFILE</small>
        </div>
        <div class="identity-chip chip-visual">VISUAL</div>
        <div class="identity-chip chip-poem">POEM</div>
        <div class="identity-chip chip-music">MUSIC</div>
    </div>
</section>

<section class="dashboard-section workflow-section">
    <div class="dashboard-section-heading">
        <div>
            <span class="dashboard-eyebrow">HOW SONICSYNC WORKS</span>
            <h2>One clear path from student to personality.</h2>
        </div>
        <p>Complete the stages in order. Each stage prepares the evidence needed for the final profile.</p>
    </div>

    <div class="dashboard-workflow">
        <article class="workflow-card">
            <div class="workflow-number">01</div>
            <div class="workflow-icon" aria-hidden="true">ID</div>
            <h3>Select a student</h3>
            <p>Choose an existing student from the available class list.</p>
            <a href="<?= appLink('analysis.php', $groupCode) ?>">Open student list <span aria-hidden="true">→</span></a>
        </article>

        <article class="workflow-card">
            <div class="workflow-number">02</div>
            <div class="workflow-icon" aria-hidden="true">01</div>
            <h3>Explore the prompts</h3>
            <p>View a generated image and the student's uploaded poem before responding.</p>
            <a href="<?= appLink('analysis.php', $groupCode) ?>">Open analysis <span aria-hidden="true">→</span></a>
        </article>

        <article class="workflow-card">
            <div class="workflow-number">03</div>
            <div class="workflow-icon" aria-hidden="true">Aa</div>
            <h3>Describe and reflect</h3>
            <p>The student describes the image. Poem text and music details can add more context.</p>
            <a href="<?= appLink('analysis.php', $groupCode) ?>">Enter responses <span aria-hidden="true">→</span></a>
        </article>

        <article class="workflow-card">
            <div class="workflow-number">04</div>
            <div class="workflow-icon" aria-hidden="true">MB</div>
            <h3>Generate the profile</h3>
            <p>Review the predicted MBTI, personality alignment, and personalised recommendations.</p>
            <a href="<?= appLink('result.php', $groupCode) ?>">View latest result <span aria-hidden="true">→</span></a>
        </article>
    </div>
</section>

<section class="dashboard-section input-story">
    <div class="input-story-copy">
        <span class="dashboard-eyebrow">A COMPLETE VIEW</span>
        <h2>Different creative choices reveal different sides of a person.</h2>
        <p>SonicSync brings three student inputs together so the final result has context, not just a single answer.</p>
        <a class="text-link" href="<?= appLink('analysis.php', $groupCode) ?>">Begin with a student <span aria-hidden="true">→</span></a>
    </div>

    <div class="input-stack">
        <div class="input-card input-card-visual">
            <span class="input-card-index">01</span>
            <div>
                <h3>Visual response</h3>
                <p>What the student notices, feels, and writes about a generated scene.</p>
            </div>
        </div>
        <div class="input-card input-card-poem">
            <span class="input-card-index">02</span>
            <div>
                <h3>Written expression</h3>
                <p>The student's poem offers extra language, themes, and emotional context.</p>
            </div>
        </div>
        <div class="input-card input-card-music">
            <span class="input-card-index">03</span>
            <div>
                <h3>Music preference</h3>
                <p>Genre, mood, pace, and energy support the overall personality reading.</p>
            </div>
        </div>
    </div>
</section>

<section class="dashboard-cta">
    <div>
        <span class="dashboard-eyebrow">READY TO BEGIN?</span>
        <h2>Start with one student. SonicSync guides the rest.</h2>
    </div>
    <a class="btn" href="<?= appLink('analysis.php', $groupCode) ?>">Start Personality Analysis <span aria-hidden="true">→</span></a>
</section>

<?php include 'includes/footer.php'; ?>
