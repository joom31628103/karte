<?php
require_once '../config.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'POST only']); exit; }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'error'=>'トークンエラー']); exit; }

$file = $_FILES['csvfile'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'error'=>'ファイルアップロードエラー']); exit;
}

$conn = getDB();

// 未存在カラムを自動追加
$addCols = [
    "student_phone VARCHAR(50) DEFAULT NULL",
    "parent1_name VARCHAR(100) DEFAULT NULL",
    "parent1_furi VARCHAR(100) DEFAULT NULL",
    "parent1_phone VARCHAR(50) DEFAULT NULL",
    "parent1_phone_note VARCHAR(200) DEFAULT NULL",
    "parent1_work_name VARCHAR(100) DEFAULT NULL",
    "parent1_work_phone VARCHAR(50) DEFAULT NULL",
    "parent1_work_note VARCHAR(200) DEFAULT NULL",
    "parent2_name VARCHAR(100) DEFAULT NULL",
    "parent2_furi VARCHAR(100) DEFAULT NULL",
    "parent2_phone VARCHAR(50) DEFAULT NULL",
    "parent2_phone_note VARCHAR(200) DEFAULT NULL",
    "parent2_work_name VARCHAR(100) DEFAULT NULL",
    "parent2_work_phone VARCHAR(50) DEFAULT NULL",
    "parent2_work_note VARCHAR(200) DEFAULT NULL",
];
foreach ($addCols as $colDef) {
    try { $conn->query("ALTER TABLE students ADD COLUMN $colDef"); } catch(Exception $e) {}
}

// CSVを読み込む（BOM対応）
$content = file_get_contents($file['tmp_name']);
$content = ltrim($content, "\xEF\xBB\xBF");
$lines = explode("\n", str_replace("\r\n", "\n", str_replace("\r", "\n", $content)));

// ヘッダー行を取得してカラムマッピング
$header = null;
$ok = 0; $skip = 0; $errors = [];

$stmt = $conn->prepare("UPDATE students SET
    student_phone=?,
    parent1_name=?,parent1_furi=?,parent1_phone=?,parent1_phone_note=?,
    parent1_work_name=?,parent1_work_phone=?,parent1_work_note=?,
    parent2_name=?,parent2_furi=?,parent2_phone=?,parent2_phone_note=?,
    parent2_work_name=?,parent2_work_phone=?,parent2_work_note=?,
    updated_at=NOW()
    WHERE student_id=?");
if (!$stmt) { echo json_encode(['success'=>false,'error'=>'DB準備エラー: '.$conn->error]); exit; }

foreach ($lines as $i => $line) {
    $line = trim($line);
    if ($line === '') continue;

    // CSV行をパース
    $row = str_getcsv($line);

    if ($header === null) {
        $header = $row;
        continue;
    }

    // 列インデックスマッピング
    $col = array_flip($header);
    $get = fn($k) => trim($row[$col[$k] ?? -1] ?? '');

    $sid = $get('学籍番号');
    if (!$sid) { $skip++; continue; }

    $student_phone     = $get('生徒電話番号');
    $parent1_name      = $get('保護者1氏名');
    $parent1_furi      = $get('保護者1ふりがな');
    $parent1_phone     = $get('保護者1電話番号');
    $parent1_phone_note= $get('保護者1電話備考');
    $parent1_work_name = $get('保護者1勤務先名');
    $parent1_work_phone= $get('保護者1勤務先電話');
    $parent1_work_note = $get('保護者1勤務先備考');
    $parent2_name      = $get('保護者2氏名');
    $parent2_furi      = $get('保護者2ふりがな');
    $parent2_phone     = $get('保護者2電話番号');
    $parent2_phone_note= $get('保護者2電話備考');
    $parent2_work_name = $get('保護者2勤務先名');
    $parent2_work_phone= $get('保護者2勤務先電話');
    $parent2_work_note = $get('保護者2勤務先備考');

    $stmt->bind_param('ssssssssssssssss',
        $student_phone,
        $parent1_name,$parent1_furi,$parent1_phone,$parent1_phone_note,
        $parent1_work_name,$parent1_work_phone,$parent1_work_note,
        $parent2_name,$parent2_furi,$parent2_phone,$parent2_phone_note,
        $parent2_work_name,$parent2_work_phone,$parent2_work_note,
        $sid
    );
    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        $ok++;
    } else {
        $errors[] = $sid;
    }
}
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'ok'      => $ok,
    'skip'    => $skip,
    'errors'  => $errors,
    'message' => "{$ok}名更新完了" . ($skip ? "、{$skip}件スキップ" : '') . (count($errors) ? "、".count($errors)."件エラー" : ''),
]);
