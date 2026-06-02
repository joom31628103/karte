<?php
// chat_system を zip でダウンロードするスクリプト（使用後削除）
$secret = 'karte2026deploy';
if (($_GET['token'] ?? '') !== $secret) { http_response_code(403); die('403'); }

$src = $_SERVER['DOCUMENT_ROOT'] . '/chat_system';
if (!is_dir($src)) die('chat_system フォルダが見つかりません: ' . $src);

$zipFile = sys_get_temp_dir() . '/chat_system_' . date('Ymd_His') . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) die('ZIPの作成に失敗しました');

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($files as $file) {
    $filePath   = $file->getRealPath();
    $relativePath = substr($filePath, strlen($src) + 1);
    $zip->addFile($filePath, $relativePath);
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="chat_system_' . date('Ymd') . '.zip"');
header('Content-Length: ' . filesize($zipFile));
header('Cache-Control: no-cache');
readfile($zipFile);
unlink($zipFile);
exit;
