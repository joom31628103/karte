<?php
require_once '../config.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');
set_time_limit(180); // リトライ含め最大3分

function jout(array $d): never { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function jerr(string $m): never { http_response_code(400); jout(['success'=>false,'error'=>$m]); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('POSTのみ');
$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) jerr('トークンエラー');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$tmpDir = dirname(__DIR__) . '/uploads/tmp_crops/';

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   save_temp: アップロード画像をセッション一時保存
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'save_temp') {
    $file = $_FILES['sheet'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) jerr('ファイルが受信できませんでした');
    if ($file['size'] > 20 * 1024 * 1024) jerr('ファイルサイズは20MB以下にしてください');

    $imgType = @exif_imagetype($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($imgType, $allowed, true)) jerr('JPEG・PNG・GIF・WebP のみ対応しています');

    $ext = match($imgType) {
        IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF  => 'gif', IMAGETYPE_WEBP => 'webp',
    };
    $tempId   = session_id() . '_sheet_' . time();
    $savePath = $tmpDir . $tempId . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $savePath)) jerr('一時保存に失敗しました');

    // 古い一時ファイル（2時間以上）を掃除
    foreach (glob($tmpDir . session_id() . '_sheet_*') as $f) {
        if ($f !== $savePath && filemtime($f) < time() - 7200) @unlink($f);
    }

    jout(['success' => true, 'temp_id' => $tempId, 'ext' => $ext]);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   get_temp_preview: 一時画像をブラウザへ返す
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'get_temp_preview') {
    $tempId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['temp_id'] ?? '');
    if (!$tempId || !str_starts_with($tempId, session_id())) jerr('不正なtemp_id');

    $found = null;
    foreach (['jpg','png','gif','webp'] as $ext) {
        $p = $tmpDir . $tempId . '.' . $ext;
        if (file_exists($p)) { $found = $p; break; }
    }
    if (!$found) jerr('一時ファイルが見つかりません');

    $imgType = exif_imagetype($found);
    $mime = match($imgType) {
        IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_GIF  => 'image/gif',  IMAGETYPE_WEBP => 'image/webp',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=3600');
    readfile($found);
    exit;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   clear_temp: 一時ファイル削除
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'clear_temp') {
    $tempId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['temp_id'] ?? '');
    if ($tempId && str_starts_with($tempId, session_id())) {
        foreach (glob($tmpDir . $tempId . '.*') as $f) @unlink($f);
    }
    jout(['success' => true]);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   analyze: B4画像→Geminiで顔検出→切り出し
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'analyze') {
    $apiKey = trim($_POST['api_key_override'] ?? '') ?: GEMINI_API_KEY;
    if (!$apiKey) jerr('config.phpにGEMINI_API_KEYを設定するか、画面でAPIキーを入力してください');

    // 新規アップロード or 一時保存済みファイルを使用
    $file = $_FILES['sheet'] ?? null;
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        if ($file['size'] > 20 * 1024 * 1024) jerr('ファイルサイズは20MB以下にしてください');
        $tmpPath = $file['tmp_name'];
    } elseif (!empty($_POST['temp_id'])) {
        $tempId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['temp_id']);
        if (!str_starts_with($tempId, session_id())) jerr('不正なtemp_id');
        $tmpPath = null;
        foreach (['jpg','png','gif','webp'] as $ext) {
            $p = $tmpDir . $tempId . '.' . $ext;
            if (file_exists($p)) { $tmpPath = $p; break; }
        }
        if (!$tmpPath) jerr('一時ファイルが見つかりません。画像を再度アップロードしてください');
    } else {
        jerr('ファイルが受信できませんでした');
    }
    $imgType = @exif_imagetype($tmpPath);
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($imgType, $allowed, true)) jerr('JPEG・PNG・GIF・WebP のみ対応しています');

    $mime = match($imgType) {
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_GIF  => 'image/gif',
        IMAGETYPE_WEBP => 'image/webp',
    };

    // 画像サイズ取得
    [$imgW, $imgH] = getimagesize($tmpPath);

    // base64エンコード
    $b64 = base64_encode(file_get_contents($tmpPath));

    // Gemini API呼び出し
    $prompt = <<<PROMPT
この画像は学校の顔写真一覧表です。生徒の顔写真が名前付きでグリッド状に並んでいます。
各生徒の顔写真の位置と氏名を抽出してください。

以下のJSON形式のみで返答してください（説明や```は不要）:
[
  {"name": "氏名（漢字）", "x": 左端px, "y": 上端px, "w": 幅px, "h": 高さpx},
  ...
]

ルール:
- 座標はピクセル単位（画像全体のサイズに基づく）
- 顔写真のみの範囲（名前テキストは含まない）
- 氏名は画像内に表示されているテキストをそのまま読み取る
- 全生徒分を返す
PROMPT;

    $body = json_encode([
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mime, 'data' => $b64]],
            ]
        ]],
        'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 8192],
    ], JSON_UNESCAPED_UNICODE);

    $maxRetry = 3;
    $resp = null;
    $curlErr = '';
    $gRes = null;
    for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key='.$apiKey);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            if ($attempt < $maxRetry) { sleep(3); continue; }
            jerr('Gemini API接続エラー: '.$curlErr);
        }

        $gRes = json_decode($resp, true);
        if (!$gRes) {
            if ($attempt < $maxRetry) { sleep(3); continue; }
            jerr('Gemini APIの応答が不正です');
        }

        // 高負荷(503/429)のときはリトライ
        if (isset($gRes['error'])) {
            $code = $gRes['error']['code'] ?? 0;
            $msg  = $gRes['error']['message'] ?? '不明';
            if (in_array($code, [429, 503]) && $attempt < $maxRetry) {
                sleep($attempt * 5); // 5秒 → 10秒と間隔を広げる
                continue;
            }
            jerr('Gemini APIエラー: '.$msg);
        }

        break; // 成功
    }

    $text = $gRes['candidates'][0]['content']['parts'][0]['text'] ?? '';
    // JSON部分を抽出
    if (!preg_match('/\[[\s\S]*\]/u', $text, $m)) jerr('Geminiの応答にJSONが含まれていません: '.$text);
    $regions = json_decode($m[0], true);
    if (!is_array($regions) || !$regions) jerr('Geminiの応答が空です');

    // 画像を読み込み
    $srcImg = match($imgType) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($tmpPath),
        IMAGETYPE_PNG  => imagecreatefrompng($tmpPath),
        IMAGETYPE_GIF  => imagecreatefromgif($tmpPath),
        IMAGETYPE_WEBP => imagecreatefromwebp($tmpPath),
    };
    if (!$srcImg) jerr('画像の読み込みに失敗しました');

    // DBで生徒一覧を取得（名前マッチング用）
    $conn = getDB();
    $studentsRes = $conn->query("SELECT student_id, name, furigana FROM students ORDER BY name");
    $students = [];
    while ($row = $studentsRes->fetch_assoc()) $students[] = $row;

    // 学籍台帳からも名前を取得
    $gakRes = $conn->query("SELECT g.gakno, g.name, g.furigana, s.student_id FROM gakuseki g JOIN students s ON s.gakno=g.gakno");
    while ($row = $gakRes->fetch_assoc()) {
        // gakusekiの名前で学生IDを上書き
        foreach ($students as &$st) {
            if ($st['student_id'] === $row['student_id']) {
                $st['name'] = $row['name'];
                $st['furigana'] = $row['furigana'];
            }
        }
        unset($st);
    }
    $conn->close();

    $session = session_id();
    $results = [];

    foreach ($regions as $i => $r) {
        $name = trim($r['name'] ?? '');
        $x = max(0, (int)($r['x'] ?? 0));
        $y = max(0, (int)($r['y'] ?? 0));
        $w = max(1, (int)($r['w'] ?? 0));
        $h = max(1, (int)($r['h'] ?? 0));
        // 画像サイズ内に収める
        $w = min($w, $imgW - $x);
        $h = min($h, $imgH - $y);
        if ($w <= 0 || $h <= 0) continue;

        // 切り出し
        $crop = imagecreatetruecolor($w, $h);
        imagecopy($crop, $srcImg, 0, 0, $x, $y, $w, $h);

        $fname = $session . '_crop_' . $i . '.jpg';
        imagejpeg($crop, $tmpDir . $fname, 90);
        imagedestroy($crop);

        // 名前マッチング（完全一致→部分一致）
        $matchedId = null;
        $matchedName = null;
        $matchScore = 0;
        $nameCmp = str_replace([' ','　'], '', $name);
        foreach ($students as $st) {
            $stName = str_replace([' ','　'], '', $st['name']);
            similar_text($nameCmp, $stName, $pct);
            if ($pct > $matchScore) {
                $matchScore = $pct;
                $matchedId  = $st['student_id'];
                $matchedName = $st['name'];
            }
        }

        $results[] = [
            'detected_name' => $name,
            'crop_url'      => '/karte/uploads/tmp_crops/' . $fname,
            'crop_file'     => $fname,
            'student_id'    => $matchScore >= 70 ? $matchedId : null,
            'student_name'  => $matchScore >= 70 ? $matchedName : null,
            'match_score'   => round($matchScore),
        ];
    }

    imagedestroy($srcImg);
    jout(['success'=>true, 'results'=>$results, 'students'=>$students]);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   save: 確認済みの切り出し画像を生徒写真として保存
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
if ($action === 'save') {
    $assignments = json_decode($_POST['assignments'] ?? '[]', true);
    if (!is_array($assignments)) jerr('割り当てデータが不正です');

    $photoDir  = dirname(__DIR__) . '/uploads/photos/';
    $conn = getDB();
    $saved = 0;

    foreach ($assignments as $a) {
        $sid      = trim($a['student_id'] ?? '');
        $cropFile = basename($a['crop_file'] ?? '');
        if (!$sid || !$cropFile) continue;

        $srcPath = $tmpDir . $cropFile;
        if (!file_exists($srcPath)) continue;

        // 既存写真を削除
        $stmt = $conn->prepare('SELECT photo, gakno FROM students WHERE student_id=?');
        $stmt->bind_param('s', $sid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $gakno = $row['gakno'] ?? '';
        if ($row['photo']) @unlink($photoDir . basename($row['photo']));
        if ($gakno) {
            $stmt = $conn->prepare('SELECT photo FROM gakuseki WHERE gakno=?');
            $stmt->bind_param('s', $gakno);
            $stmt->execute();
            $gRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($gRow['photo'] ?? null) @unlink($photoDir . basename($gRow['photo']));
        }

        $newFile = 'import_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sid) . '_' . time() . '.jpg';
        $destPath = $photoDir . $newFile;
        if (!copy($srcPath, $destPath)) continue;

        $webPath = '/karte/uploads/photos/' . $newFile;
        if ($gakno) {
            $stmt = $conn->prepare('UPDATE gakuseki SET photo=? WHERE gakno=?');
            $stmt->bind_param('ss', $webPath, $gakno);
            $stmt->execute(); $stmt->close();
        }
        $stmt = $conn->prepare('UPDATE students SET photo=? WHERE student_id=?');
        $stmt->bind_param('ss', $webPath, $sid);
        $stmt->execute(); $stmt->close();
        $saved++;
    }
    $conn->close();

    // tmp_cropsの古いファイルを掃除（1時間以上前）
    foreach (glob($tmpDir . '*.jpg') as $f) {
        if (filemtime($f) < time() - 3600) @unlink($f);
    }

    jout(['success'=>true, 'saved'=>$saved]);
}

jerr('不明なアクション');
