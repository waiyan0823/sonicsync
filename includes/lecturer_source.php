<?php

const LECTURER_PORTAL_BASE = 'https://bitp3353.utem.edu.my/2026/all/';

function lecturerFetch(string $relativeUrl): string
{
    $url = preg_match('~^https?://~i', $relativeUrl)
        ? $relativeUrl
        : LECTURER_PORTAL_BASE . ltrim($relativeUrl, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'SonicSync-GW08/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $html = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($html === false || $status !== 200) {
        throw new RuntimeException("Lecturer portal request failed ($status): $error");
    }
    return $html;
}

function lecturerXPath(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

function lecturerNodeValue(DOMXPath $xpath, string $query): string
{
    $nodes = $xpath->query($query);
    if (!$nodes || $nodes->length === 0) {
        return '';
    }
    $node = $nodes->item(0);
    return $node instanceof DOMAttr ? trim($node->value) : trim($node->textContent);
}

function lecturerAbsoluteUrl(string $path): string
{
    $path = trim($path);
    if ($path === '' || preg_match('~^https?://~i', $path)) {
        return $path;
    }
    return LECTURER_PORTAL_BASE . ltrim($path, '/');
}

function lecturerProfileIds(int $limit = 0): array
{
    $xpath = lecturerXPath(lecturerFetch('gallery.php'));
    $ids = [];
    foreach ($xpath->query("//a[contains(@href, 'profile.php?id=')]") as $link) {
        parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $id = isset($query['id']) ? (int) $query['id'] : 0;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
    rsort($ids, SORT_NUMERIC);
    return $limit > 0 ? array_slice($ids, 0, $limit) : $ids;
}

function lecturerProfile(int $profileId): array
{
    $relativeUrl = 'profile.php?id=' . $profileId;
    $xpath = lecturerXPath(lecturerFetch($relativeUrl));
    $media = [];
    $mediaQueries = [
        'Image' => "//img[contains(concat(' ', normalize-space(@class), ' '), ' profile-img ')]/@src",
        'Audio' => '//audio/@src',
        'PDF' => "//embed[contains(translate(@type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'pdf')]/@src",
        'Video' => '//video/@src',
    ];
    foreach ($mediaQueries as $category => $query) {
        $path = lecturerNodeValue($xpath, $query);
        if ($path !== '') {
            $media[] = ['category' => $category, 'url' => lecturerAbsoluteUrl($path)];
        }
    }

    return [
        'source_id' => $profileId,
        'source_url' => LECTURER_PORTAL_BASE . $relativeUrl,
        'name' => lecturerNodeValue($xpath, "//input[@name='fullname']/@value"),
        'matric_no' => strtoupper(lecturerNodeValue($xpath, "//label[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'matric')]/following-sibling::input[1]/@value")),
        'phone_no' => lecturerNodeValue($xpath, "//input[@name='phone']/@value"),
        'lab_group' => strtoupper(lecturerNodeValue($xpath, "//label[normalize-space(.)='Group']/following-sibling::input[1]/@value")),
        'life_value' => lecturerNodeValue($xpath, "//textarea[@name='life_motto']"),
        'media' => $media,
    ];
}

function lecturerFileName(string $url): string
{
    return urldecode(basename((string) parse_url($url, PHP_URL_PATH)));
}

function lecturerViewAvailable(mysqli $conn): bool
{
    try {
        $result = $conn->query('SELECT 1 FROM vstu LIMIT 1');
        $available = $result !== false;
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        return $available;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

function lecturerViewMediaUrl(string $path): string
{
    $path = trim($path);
    if ($path === '' || preg_match('~^https?://~i', $path)) {
        return $path;
    }

    $path = ltrim(str_replace('\\', '/', $path), '/');
    if (!str_starts_with(strtolower($path), 'uploads/')) {
        $path = 'uploads/' . $path;
    }
    return lecturerAbsoluteUrl($path);
}

function lecturerProfilesFromView(mysqli $conn, string $scope = 'all', int $limit = 0): array
{
    $sql = 'SELECT id, matric_no, full_name, phone_no, group_no, life_motto,
                   photoStu, docStu, audioStu, videoStu
            FROM vstu';
    if ($scope !== 'all') {
        $sql .= ' WHERE group_no = ?';
    }
    $sql .= ' ORDER BY id DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }

    $stmt = $conn->prepare($sql);
    if ($scope !== 'all') {
        $stmt->bind_param('s', $scope);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $profiles = [];

    $mediaColumns = [
        'photoStu' => 'Image',
        'audioStu' => 'Audio',
        'docStu' => 'PDF',
        'videoStu' => 'Video',
    ];

    while ($row = $result->fetch_assoc()) {
        $media = [];
        foreach ($mediaColumns as $column => $category) {
            $url = lecturerViewMediaUrl((string) ($row[$column] ?? ''));
            if ($url !== '') {
                $media[] = ['category' => $category, 'url' => $url];
            }
        }

        $profileId = (int) $row['id'];
        $profiles[] = [
            'source_id' => $profileId,
            'source_url' => LECTURER_PORTAL_BASE . 'profile.php?id=' . $profileId,
            'name' => trim((string) $row['full_name']),
            'matric_no' => strtoupper(trim((string) $row['matric_no'])),
            'phone_no' => trim((string) $row['phone_no']),
            'lab_group' => strtoupper(trim((string) $row['group_no'])),
            'life_value' => trim((string) $row['life_motto']),
            'media' => $media,
        ];
    }

    $stmt->close();
    return $profiles;
}

function syncLecturerProfile(mysqli $conn, array $profile): array
{
    $studentId = $profile['matric_no'] ?: 'LECTURER-' . $profile['source_id'];
    $name = $profile['name'] ?: 'Lecturer Profile ' . $profile['source_id'];
    $matric = $profile['matric_no'];
    $phone = $profile['phone_no'];
    $group = $profile['lab_group'];
    $lifeValue = $profile['life_value'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'INSERT INTO student (student_id, name, matric_no, phone_no, lab_group, life_value)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name=VALUES(name), matric_no=VALUES(matric_no),
             phone_no=VALUES(phone_no), lab_group=VALUES(lab_group), life_value=VALUES(life_value)'
        );
        $stmt->bind_param('ssssss', $studentId, $name, $matric, $phone, $group, $lifeValue);
        $stmt->execute();
        $stmt->close();

        $newAssets = 0;
        $updatedAssets = 0;
        foreach ($profile['media'] as $media) {
            $url = $media['url'];
            $category = $media['category'];
            $fileName = lecturerFileName($url);
            $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $description = "Lecturer portal $category submission by $name.";
            if ($lifeValue !== '') {
                $description .= " Life motto: $lifeValue";
            }
            $tags = implode(', ', array_filter([
                'lecturer-portal', 'profile:' . $profile['source_id'], $group, $matric, strtolower($category),
            ]));

            $stmt = $conn->prepare('SELECT asset_id FROM multimedia_asset WHERE student_id=? AND file_path=? LIMIT 1');
            $stmt->bind_param('ss', $studentId, $url);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $assetId = (int) $existing['asset_id'];
                $stmt = $conn->prepare('UPDATE multimedia_asset SET file_name=?, file_type=?, description=?, tags=?, media_category=? WHERE asset_id=?');
                $stmt->bind_param('sssssi', $fileName, $fileType, $description, $tags, $category, $assetId);
                $stmt->execute();
                $stmt->close();
                $updatedAssets++;
            } else {
                $fileSize = 0;
                $stmt = $conn->prepare('INSERT INTO multimedia_asset (student_id,file_name,file_type,file_size,file_path,description,tags,media_category) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->bind_param('sssissss', $studentId, $fileName, $fileType, $fileSize, $url, $description, $tags, $category);
                $stmt->execute();
                $assetId = $stmt->insert_id;
                $stmt->close();
                $newAssets++;
            }

            if ($category === 'Audio') {
                $stmt = $conn->prepare('SELECT audio_id FROM audio_metadata WHERE asset_id=? LIMIT 1');
                $stmt->bind_param('i', $assetId);
                $stmt->execute();
                $audio = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$audio) {
                    $stmt = $conn->prepare('INSERT INTO audio_metadata (asset_id,song_title,artist_or_creator) VALUES (?,?,?)');
                    $stmt->bind_param('iss', $assetId, $fileName, $name);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $conn->commit();
        return ['new_assets' => $newAssets, 'updated_assets' => $updatedAssets];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function syncLecturerPortal(mysqli $conn, string $scope = 'all', int $limit = 0): array
{
    $stats = ['source' => 'Public profile pages', 'discovered' => 0, 'imported' => 0, 'skipped' => 0, 'new_assets' => 0, 'updated_assets' => 0, 'errors' => []];
    $profileIds = lecturerProfileIds($limit);
    $stats['discovered'] = count($profileIds);
    foreach ($profileIds as $profileId) {
        try {
            $profile = lecturerProfile($profileId);
            if ($scope !== 'all' && strtoupper($profile['lab_group']) !== strtoupper($scope)) {
                $stats['skipped']++;
                continue;
            }
            $result = syncLecturerProfile($conn, $profile);
            $stats['imported']++;
            $stats['new_assets'] += $result['new_assets'];
            $stats['updated_assets'] += $result['updated_assets'];
        } catch (Throwable $e) {
            $stats['errors'][] = "Profile $profileId: " . $e->getMessage();
        }
    }
    return $stats;
}

function syncLecturerData(mysqli $conn, string $scope = 'all', int $limit = 0): array
{
    if (!lecturerViewAvailable($conn)) {
        return syncLecturerPortal($conn, $scope, $limit);
    }

    $profiles = lecturerProfilesFromView($conn, $scope, $limit);
    $stats = [
        'source' => 'gw08.vstu',
        'discovered' => count($profiles),
        'imported' => 0,
        'skipped' => 0,
        'new_assets' => 0,
        'updated_assets' => 0,
        'errors' => [],
    ];

    foreach ($profiles as $profile) {
        try {
            $result = syncLecturerProfile($conn, $profile);
            $stats['imported']++;
            $stats['new_assets'] += $result['new_assets'];
            $stats['updated_assets'] += $result['updated_assets'];
        } catch (Throwable $e) {
            $stats['errors'][] = 'Profile ' . $profile['source_id'] . ': ' . $e->getMessage();
        }
    }

    return $stats;
}
