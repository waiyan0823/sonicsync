<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/lecturer_source.php';

$options = getopt('', ['scope::', 'limit::']);
$scope = $options['scope'] ?? 'all';
$limit = max(0, (int) ($options['limit'] ?? 0));

if (!in_array($scope, ['all', 'GW08'], true)) {
    fwrite(STDERR, "Scope must be all or GW08.\n");
    exit(1);
}

try {
    $stats = syncLecturerData($conn, $scope, $limit);
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit($stats['errors'] ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
