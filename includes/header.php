<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function activeLink($page, $currentPage) {
    return $page === $currentPage ? 'active' : '';
}

$groupCode = isset($_GET['group']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['group']) : 'GW08';
$lecturerDashboardUrl = 'https://bitp3353.utem.edu.my/2026/all/dashboard.php?group=' . rawurlencode($groupCode);

function appLink(string $file, string $groupCode): string {
    return htmlspecialchars($file . '?group=' . rawurlencode($groupCode), ENT_QUOTES, 'UTF-8');
}

$pages = [
    ['file' => 'index.php',     'label' => 'Dashboard',          'step' => '01'],
    ['file' => 'analysis.php',  'label' => 'Personality Analysis', 'step' => '02'],
    ['file' => 'result.php',    'label' => 'Result',             'step' => '03'],
    ['file' => 'retrieval.php', 'label' => 'Retrieval',           'step' => ''],
    ['file' => 'group_members.php', 'label' => 'Group Members',   'step' => ''],
];

$stepMap = ['analysis.php' => '02', 'result.php' => '03', 'retrieval.php' => ''];
$currentStep = $stepMap[$currentPage] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SonicSync | Multimedia MBTI Detection</title>
    <?php if ($currentPage === 'analysis.php'): ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css?v=20260624-polish-text">
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
                    <a class="<?= activeLink($p['file'], $currentPage) ?>" href="<?= appLink($p['file'], $groupCode) ?>">
                        <span class="step-badge"><?= $p['step'] ?></span><?= $p['label'] ?>
                    </a>
                <?php else: ?>
                    <a class="<?= activeLink($p['file'], $currentPage) ?>" href="<?= appLink($p['file'], $groupCode) ?>">
                        <?= $p['label'] ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a class="lecturer-dashboard-link" href="<?= htmlspecialchars($lecturerDashboardUrl, ENT_QUOTES, 'UTF-8') ?>">Back to Lecturer Dashboard</a>
        </nav>

        <div class="side-note">
            <span><?= htmlspecialchars($groupCode) ?></span>
            <p>Multimedia Personality Profiling</p>
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
