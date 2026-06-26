<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST only']); exit; }

$file = $_FILES['csvfile'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) { echo json_encode(['error'=>'upload error']); exit; }

$content = file_get_contents($file['tmp_name']);
$rawHex  = bin2hex(substr($content, 0, 12)); // 最初12バイトを16進で

// BOM除去
$hasBom = (substr($content, 0, 3) === "\xEF\xBB\xBF");
if ($hasBom) $content = substr($content, 3);

// エンコーディング検出
$enc = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP', 'JIS', 'ASCII'], true);
if ($enc && $enc !== 'UTF-8' && $enc !== 'ASCII') {
    $content = mb_convert_encoding($content, 'UTF-8', $enc);
}

$fp = fopen('php://memory', 'r+');
fwrite($fp, $content);
rewind($fp);

$header = fgetcsv($fp, 0, ',', '"');
$row1   = fgetcsv($fp, 0, ',', '"');
fclose($fp);

echo json_encode([
    'rawHex'    => $rawHex,
    'hasBom'    => $hasBom,
    'detected'  => $enc,
    'header'    => $header,
    'row1'      => $row1,
    'header_hex'=> $header ? bin2hex($header[0]) : null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
