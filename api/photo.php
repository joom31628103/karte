<?php
require_once '../config.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

function jout(array $d): never { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function err(string $m): never  { http_response_code(400); jout(['success'=>false,'error'=>$m]); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('POSTのみ');

// CSRF
$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) err('トークンエラー');

$action = $_POST['action'] ?? '';
$conn   = getDB();

/* ── アップロード ── */
if ($action === 'upload') {
    $gakno = trim($_POST['gakno'] ?? '');
    $sid   = trim($_POST['student_id'] ?? '');
    if (!$gakno && !$sid) err('IDが不正です');

    $file = $_FILES['photo'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) err('ファイルが受信できませんでした');

    // サイズ上限 4MB
    if ($file['size'] > 4 * 1024 * 1024) err('ファイルサイズは4MB以下にしてください');

    // MIME確認（exif_imagetypeで実際の中身をチェック）
    $tmpPath = $file['tmp_name'];
    $imgType = @exif_imagetype($tmpPath);
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($imgType, $allowed, true)) err('JPEG・PNG・GIF・WebP のみ対応しています');

    $ext = match($imgType) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    };

    $uploadDir = dirname(__DIR__) . '/uploads/photos/';
    $key       = $gakno ?: 's_'.$sid;
    $filename  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '_' . time() . '.' . $ext;
    $destPath  = $uploadDir . $filename;

    // 古い写真を削除
    $oldFile = null;
    if ($gakno) {
        $stmt = $conn->prepare('SELECT photo FROM gakuseki WHERE gakno=?');
        $stmt->bind_param('s', $gakno);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $oldFile = $row['photo'] ?? null;
    } else {
        $stmt = $conn->prepare('SELECT photo FROM students WHERE student_id=?');
        $stmt->bind_param('s', $sid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $oldFile = $row['photo'] ?? null;
    }
    if ($oldFile && file_exists($uploadDir . basename($oldFile))) {
        @unlink($uploadDir . basename($oldFile));
    }

    if (!move_uploaded_file($tmpPath, $destPath)) err('保存に失敗しました');

    $webPath = '/karte/uploads/photos/' . $filename;

    if ($gakno) {
        $stmt = $conn->prepare('UPDATE gakuseki SET photo=? WHERE gakno=?');
        $stmt->bind_param('ss', $webPath, $gakno);
    } else {
        $stmt = $conn->prepare('UPDATE students SET photo=? WHERE student_id=?');
        $stmt->bind_param('ss', $webPath, $sid);
    }
    $stmt->execute();
    $stmt->close();

    jout(['success'=>true, 'url'=>$webPath]);
}

/* ── 削除 ── */
if ($action === 'delete') {
    $gakno = trim($_POST['gakno'] ?? '');
    $sid   = trim($_POST['student_id'] ?? '');
    $uploadDir = dirname(__DIR__) . '/uploads/photos/';

    if ($gakno) {
        $stmt = $conn->prepare('SELECT photo FROM gakuseki WHERE gakno=?');
        $stmt->bind_param('s', $gakno);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row['photo']) @unlink($uploadDir . basename($row['photo']));
        $null = null;
        $stmt = $conn->prepare('UPDATE gakuseki SET photo=NULL WHERE gakno=?');
        $stmt->bind_param('s', $gakno);
    } else {
        $stmt = $conn->prepare('SELECT photo FROM students WHERE student_id=?');
        $stmt->bind_param('s', $sid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row['photo']) @unlink($uploadDir . basename($row['photo']));
        $stmt = $conn->prepare('UPDATE students SET photo=NULL WHERE student_id=?');
        $stmt->bind_param('s', $sid);
    }
    $stmt->execute();
    $stmt->close();
    jout(['success'=>true]);
}

err('不明なアクション');
