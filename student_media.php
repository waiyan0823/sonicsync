<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$studentId = trim((string) ($_GET['student_id'] ?? ''));
if ($studentId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $studentId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'A valid student is required.']);
    exit;
}

$studentStmt = $conn->prepare(
    'SELECT student_id, name, matric_no, mbti_type FROM student WHERE student_id = ?'
);
$studentStmt->bind_param('s', $studentId);
$studentStmt->execute();
$student = $studentStmt->get_result()->fetch_assoc();
$studentStmt->close();

if (!$student) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Student not found.']);
    exit;
}

$pdfStmt = $conn->prepare(
    "SELECT asset_id, file_name, file_path, upload_date
     FROM multimedia_asset
     WHERE student_id = ?
       AND media_category = 'PDF'
       AND (
           LOWER(file_name) LIKE '%.pdf'
           OR LOWER(file_path) LIKE '%.pdf'
           OR LOWER(file_type) = 'pdf'
       )
     ORDER BY upload_date DESC, asset_id DESC
     LIMIT 1"
);
$pdfStmt->bind_param('s', $studentId);
$pdfStmt->execute();
$pdf = $pdfStmt->get_result()->fetch_assoc();
$pdfStmt->close();

if ($pdf) {
    $pdf['view_url'] = 'media_proxy.php?asset_id=' . (int) $pdf['asset_id'];
    $pdf['source_url'] = $pdf['file_path'];
    unset($pdf['file_path']);
}

echo json_encode([
    'ok' => true,
    'student' => $student,
    'pdf' => $pdf ?: null,
    'analysis_url' => 'analysis.php?student_id=' . rawurlencode($studentId),
], JSON_UNESCAPED_SLASHES);
