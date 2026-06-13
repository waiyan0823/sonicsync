<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function activeLink($page, $currentPage) {
    return $page === $currentPage ? 'active' : '';
}

$pages = [
    ['file' => 'index.php',     'label' => 'Dashboard',          'step' => '01'],
    ['file' => 'upload.php',    'label' => 'Upload Multimedia',  'step' => '02'],
    ['file' => 'analysis.php',  'label' => 'Analysis (TBR+CBR)', 'step' => '03'],
    ['file' => 'result.php',    'label' => 'Result',             'step' => '04'],
    ['file' => 'retrieval.php', 'label' => 'Retrieval',           'step' => ''],
];

$stepMap = ['index.php' => '01', 'upload.php' => '02', 'analysis.php' => '03', 'result.php' => '04', 'retrieval.php' => ''];
$currentStep = $stepMap[$currentPage] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SonicSync | Multimedia MBTI Detection</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="page-shell">
    <aside class="sidebar">
        <div class="brand-box">
            <div class="brand-icon">SS</div>
            <div>
                <h2>SonicSync</h2>
                <p>MBTI Detection</p>
            </div>
        </div>

        <nav class="menu">
            <?php foreach ($pages as $p): ?>
                <?php if ($p['step']): ?>
                    <a class="<?= activeLink($p['file'], $currentPage) ?>" href="<?= $p['file'] ?>">
                        <span class="step-badge"><?= $p['step'] ?></span><?= $p['label'] ?>
                    </a>
                <?php else: ?>
                    <a class="<?= activeLink($p['file'], $currentPage) ?>" href="<?= $p['file'] ?>">
                        <?= $p['label'] ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="side-note">
            <span>GW08</span>
            <p>TBR + CBR + ABR Multimedia Profiling</p>
        </div>
    </aside>

    <main class="content">

    <?php if ($currentStep): ?>
    <div class="flow-pipeline">
        <?php foreach ($pages as $p): ?>
            <?php if ($p['step']): ?>
                <div class="flow-step <?= $p['file'] === $currentPage ? 'active' : '' ?>">
                    <div class="step-num"><?= $p['step'] ?></div>
                    <h4><?= $p['label'] ?></h4>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
