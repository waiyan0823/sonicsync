<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

function normalize_remote_url(string $url): string
{
    $parsed = parse_url($url);
    if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
        return $url;
    }

    $path = isset($parsed['path']) ? rawurlencode($parsed['path']) : '';
    $path = str_replace('%2F', '/', $path);
    $query = '';
    if (!empty($parsed['query'])) {
        $query = '?' . preg_replace_callback(
            '/(^|[&=])([^&=]+)/',
            static fn(array $matches): string => $matches[1] . rawurlencode(rawurldecode($matches[2])),
            $parsed['query']
        );
    }

    return $parsed['scheme'] . '://' . $parsed['host'] . $path . $query;
}

$assetId = filter_input(INPUT_GET, 'asset_id', FILTER_VALIDATE_INT);
if (!$assetId) {
    http_response_code(400);
    exit('Invalid media asset.');
}

$stmt = $conn->prepare(
    "SELECT file_name, file_path
     FROM multimedia_asset
     WHERE asset_id = ? AND media_category = 'PDF'
     LIMIT 1"
);
$stmt->bind_param('i', $assetId);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asset) {
    http_response_code(404);
    exit('PDF not found.');
}

$fileName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', basename((string) $asset['file_name']));
$filePath = (string) $asset['file_path'];
$url = parse_url($filePath);

if (isset($url['scheme'])) {
    $host = strtolower((string) ($url['host'] ?? ''));
    $path = (string) ($url['path'] ?? '');
    $allowedScheme = in_array(strtolower((string) $url['scheme']), ['http', 'https'], true);
    $allowedHost = $host === 'bitp3353.utem.edu.my';
    $allowedPath = str_starts_with($path, '/2026/all/uploads/');

    if (!$allowedScheme || !$allowedHost || !$allowedPath) {
        http_response_code(403);
        exit('This remote file source is not allowed.');
    }

    $remoteUrl = normalize_remote_url($filePath);
    $curl = curl_init($remoteUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36 SonicSync/1.0',
        CURLOPT_HTTPHEADER => [
            'Accept: application/pdf,*/*;q=0.8',
            'Referer: ' . strtolower((string) $url['scheme']) . '://' . $host . '/',
        ],
    ]);
    $contents = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($contents === false || $status < 200 || $status >= 300) {
        http_response_code(502);
        exit('Unable to retrieve the lecturer PDF. ' . $error);
    }

    header('Content-Type: ' . ($contentType ?: 'application/pdf'));
    header('Content-Length: ' . strlen($contents));
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('X-Content-Type-Options: nosniff');
    echo $contents;
    exit;
}

$projectRoot = realpath(__DIR__);
$relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR);
$localFile = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relativePath);
if (!$projectRoot || !$localFile || !str_starts_with($localFile, $projectRoot . DIRECTORY_SEPARATOR) || !is_file($localFile)) {
    http_response_code(404);
    exit('Local PDF not found.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($localFile));
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('X-Content-Type-Options: nosniff');
readfile($localFile);
