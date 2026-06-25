<?php
include 'includes/db.php';

$group = isset($_GET['group'])
    ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['group'])
    : 'GW08';

$members = [];
$stmt = $conn->prepare("SELECT name AS full_name, matric_no FROM student WHERE lab_group = ? ORDER BY name");
$stmt->bind_param('s', $group);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}
$stmt->close();
?>
<?php include 'includes/header.php'; ?>

<section class="hero">
    <div>
        <h1>Group Members</h1>
        <p>Student list for <?= htmlspecialchars($group) ?>, merged into SonicSync.</p>
    </div>
    <div class="tag"><?= htmlspecialchars($group) ?></div>
</section>

<section class="table-card">
    <div class="group-header-row">
        <div>
            <h2>Senarai Ahli Kumpulan</h2>
            <p class="small-text">Names and matric numbers are loaded from the SonicSync student table.</p>
        </div>
        <form method="GET" class="group-filter-form">
            <label for="group">Group</label>
            <input id="group" name="group" value="<?= htmlspecialchars($group) ?>" placeholder="GW08">
            <button class="btn" type="submit">View</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <tr>
                <th style="width:90px;">Bil</th>
                <th>Nama Penuh</th>
                <th style="width:260px;">No. Matrik</th>
            </tr>
            <?php if (!$members): ?>
                <tr>
                    <td colspan="3" class="muted">Tiada data ahli kumpulan ditemui untuk kod group "<?= htmlspecialchars($group) ?>".</td>
                </tr>
            <?php else: ?>
                <?php foreach ($members as $index => $member): ?>
                    <tr>
                        <td><strong><?= $index + 1 ?></strong></td>
                        <td><?= htmlspecialchars(strtoupper($member['full_name'])) ?></td>
                        <td><span class="result-badge"><?= htmlspecialchars($member['matric_no'] ?: '-') ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>

    <div class="group-actions">
        <a class="btn" href="<?= appLink('analysis.php', $groupCode) ?>">Start Personality Analysis</a>
        <a class="btn secondary" href="<?= htmlspecialchars($lecturerDashboardUrl, ENT_QUOTES, 'UTF-8') ?>">Back to Dashboard</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
