<?php
require_once '../config.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

function jout(array $d): never { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function err(string $m): never  { http_response_code(400); jout(['success'=>false,'error'=>$m]); }

$uploadDir = dirname(__DIR__) . '/uploads/survey/';

function surveyKey(string $gakno, string $sid): string {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $gakno ?: 's_'.$sid);
}

function findSurveyFile(string $dir, string $key): ?string {
    foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
        if (file_exists($dir . $key . '.' . $ext)) return $key . '.' . $ext;
    }
    return null;
}

/* ── GET: 現在の画像URL取得 ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gakno = trim($_GET['gakno'] ?? '');
    $sid   = trim($_GET['student_id'] ?? '');
    if (!$gakno && !$sid) err('IDが不正です');
    // 学籍番号リンク前（生徒ID命名）でアップロードされた画像も見つけられるよう、
    // 学籍番号キー→生徒IDキーの順にフォールバックして検索する
    $candidates = [];
    if ($gakno) $candidates[] = surveyKey($gakno, '');
    if ($sid)   $candidates[] = surveyKey('', $sid);
    $file = null;
    foreach (array_unique($candidates) as $k) {
        $file = findSurveyFile($uploadDir, $k);
        if ($file) break;
    }
    jout(['success'=>true, 'url'=> $file ? '/karte/uploads/survey/'.$file : null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('POSTのみ');

$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) err('トークンエラー');

$action = $_POST['action'] ?? '';
$gakno  = trim($_POST['gakno'] ?? '');
$sid    = trim($_POST['student_id'] ?? '');
if (!$gakno && !$sid) err('IDが不正です');
$key = surveyKey($gakno, $sid);

/* ── アップロード ── */
if ($action === 'upload') {
    $file = $_FILES['survey'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) err('ファイルが受信できませんでした');
    if ($file['size'] > 10 * 1024 * 1024) err('ファイルサイズは10MB以下にしてください');

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

    // 古いファイルを削除
    $old = findSurveyFile($uploadDir, $key);
    if ($old) @unlink($uploadDir . $old);

    $filename = $key . '.' . $ext;
    if (!move_uploaded_file($tmpPath, $uploadDir . $filename)) err('保存に失敗しました');

    jout(['success'=>true, 'url'=>'/karte/uploads/survey/'.$filename]);
}

/* ── 削除 ── */
if ($action === 'delete') {
    $old = findSurveyFile($uploadDir, $key);
    if ($old) @unlink($uploadDir . $old);
    jout(['success'=>true]);
}

err('不明なアクション');
