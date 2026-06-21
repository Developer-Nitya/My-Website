<?php
declare(strict_types=1);

require __DIR__ . '/includes/product-catalog.php';

function send_download_file(string $absolutePath, string $downloadName): never
{
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        http_response_code(404);
        exit('Requested file was not found.');
    }

    $mime = detect_mime_type($absolutePath) ?? 'application/octet-stream';
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
    header('Content-Length: ' . (string) filesize($absolutePath));
    header('X-Content-Type-Options: nosniff');
    readfile($absolutePath);
    exit;
}

$fileId = (int) ($_GET['file_id'] ?? 0);
$fallbackSample = clean_text($_GET['sample'] ?? '', 50);
$samplePath = clean_text($_GET['sample_path'] ?? '', 255);

if ($fallbackSample !== '' || $samplePath !== '') {
    $sampleAbsolute = __DIR__ . '/samples/sample-ppt.pptx';
    send_download_file($sampleAbsolute, 'free-sample-ppt.pptx');
}

if ($fileId < 1) {
    http_response_code(403);
    exit('Direct access denied.');
}

$pdo = db();
if (!$pdo) {
    http_response_code(503);
    exit('Database connection unavailable.');
}

$activeGuard = content_table_has_column($pdo, 'content_files', 'is_active') ? ' AND cf.is_active = 1' : '';
$deletedGuard = content_table_has_column($pdo, 'content_items', 'is_deleted') ? ' AND ci.is_deleted = 0' : '';

$stmt = $pdo->prepare('
    SELECT cf.id, cf.original_name, cf.relative_path, ci.access_type, cf.file_role, cf.is_downloadable
    FROM content_files cf
    INNER JOIN content_items ci ON ci.id = cf.content_item_id
    WHERE cf.id = :id' . $activeGuard . $deletedGuard . '
    LIMIT 1
');
$stmt->execute(['id' => $fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

if (strtolower((string) $file['access_type']) !== 'free' || (string) $file['file_role'] !== 'sample' || (int) $file['is_downloadable'] !== 1) {
    http_response_code(403);
    exit('Premium downloads are blocked.');
}

$relativePath = str_replace(['..', '\\'], ['', '/'], (string) $file['relative_path']);
$absolutePath = __DIR__ . '/' . ltrim($relativePath, '/');
$downloadName = (string) ($file['original_name'] ?: 'sample-download.pptx');

send_download_file($absolutePath, $downloadName);
