<?php
declare(strict_types=1);

require __DIR__ . '/includes/order-management.php';

function send_delivery_file(string $absolutePath, string $downloadName): never
{
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        http_response_code(404);
        exit('Requested delivery file was not found.');
    }

    $mime = detect_mime_type($absolutePath) ?? 'application/octet-stream';
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
    header('Content-Length: ' . (string) filesize($absolutePath));
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    readfile($absolutePath);
    exit;
}

$token = clean_text($_GET['token'] ?? '', 64);
if ($token === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(404);
    exit('Invalid delivery link.');
}

$pdo = db();
if (!$pdo) {
    http_response_code(503);
    exit('Database connection unavailable.');
}

if (!order_delivery_table_exists($pdo)) {
    http_response_code(404);
    exit('Delivery attachment table not available.');
}

$stmt = $pdo->prepare('
    SELECT odf.original_name, odf.relative_path, odf.is_active
    FROM order_delivery_files odf
    WHERE odf.delivery_token = :delivery_token
      AND odf.is_active = 1
    LIMIT 1
');
$stmt->execute(['delivery_token' => $token]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('Delivery file not found or no longer active.');
}

$relativePath = str_replace(['..', '\\'], ['', '/'], (string) ($file['relative_path'] ?? ''));
$absolutePath = __DIR__ . '/' . ltrim($relativePath, '/');
$downloadName = (string) ($file['original_name'] ?: 'delivery-file');

send_delivery_file($absolutePath, $downloadName);
