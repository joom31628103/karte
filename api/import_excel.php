<?php
require_once '../config.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

function jout(array $d): never { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function err(string $m): never  { http_response_code(400); jout(['success'=>false,'error'=>$m]); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('POSTのみ');
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) err('トークンエラー');

$file = $_FILES['excel'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) err('ファイルが受信できませんでした');
if ($file['size'] > 20 * 1024 * 1024) err('ファイルサイズは20MB以下にしてください');

// SpreadsheetML XMLとして読み込む
$xml = @simplexml_load_file($file['tmp_name']);
if (!$xml) {
    // UTF-8 BOM除去して再試行
    $raw = file_get_contents($file['tmp_name']);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $xml = @simplexml_load_string($raw);
}
if (!$xml) err('Excelファイルの解析に失敗しました。このシステムでダウンロードした.xlsファイルのみ対応しています。');

// 名前空間登録
$xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');

$conn = getDB();
$result = ['students'=>0,'records'=>0,'attendance'=>0,'interviews'=>0,'skipped'=>0,'errors'=>[]];

/**
 * シート名でWorksheetを探してセルデータの2次元配列を返す
 */
function getSheetRows(SimpleXMLElement $xml, string $name): array {
    $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
    foreach ($xml->Worksheet as $ws) {
        $attrs = $ws->attributes('urn:schemas-microsoft-com:office:spreadsheet');
        if ((string)$attrs['Name'] === $name) {
            $rows = [];
            foreach ($ws->Table->Row as $row) {
                $cells = [];
                foreach ($row->Cell as $cell) {
                    $cells[] = (string)($cell->Data ?? '');
                }
                $rows[] = $cells;
            }
            return $rows;
        }
    }
    return [];
}

// ─── 生徒一覧 ───────────────────────────────────────
$rows = getSheetRows($xml, '生徒一覧');
array_shift($rows); // ヘッダー行除外
foreach ($rows as $r) {
    if (empty($r[0])) continue; // 学籍番号が空ならスキップ
    $sid    = trim($r[0]);
    $name   = trim($r[1] ?? '');
    $furi   = trim($r[2] ?? '');
    $cls    = trim($r[3] ?? '');
    $seat   = $r[4] !== '' ? (int)$r[4] : null;
    $gender = trim($r[5] ?? '');
    $bday   = trim($r[6] ?? '') ?: null;
    $phone  = trim($r[7] ?? '');
    $parent = trim($r[8] ?? '');
    $addr   = trim($r[9] ?? '');
    $notes  = trim($r[10] ?? '');
    if (!$name) continue;
    $stmt = $conn->prepare(
        "INSERT INTO students (student_id,name,furigana,class_name,seat_number,gender,birthday,phone,parent_name,address,notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE name=VALUES(name),furigana=VALUES(furigana),class_name=VALUES(class_name),
         seat_number=VALUES(seat_number),gender=VALUES(gender),birthday=VALUES(birthday),
         phone=VALUES(phone),parent_name=VALUES(parent_name),address=VALUES(address),notes=VALUES(notes)"
    );
    if (!$stmt) { $result['errors'][] = '生徒INSERT準備失敗'; continue; }
    $stmt->bind_param('ssssissssss',$sid,$name,$furi,$cls,$seat,$gender,$bday,$phone,$parent,$addr,$notes);
    $stmt->execute() ? $result['students']++ : $result['errors'][] = "生徒 {$sid}: ".$stmt->error;
    $stmt->close();
}

// ─── 指導記録 ───────────────────────────────────────
$rows = getSheetRows($xml, '指導記録');
array_shift($rows);
foreach ($rows as $r) {
    if (empty($r[0])) continue;
    $sid     = trim($r[0]);
    $date    = trim($r[3] ?? '') ?: date('Y-m-d');
    $type    = trim($r[4] ?? '');
    $content = trim($r[5] ?? '');
    $teacher = trim($r[6] ?? '');
    $next    = trim($r[7] ?? '');
    if (!$content) continue;
    // 同じ生徒・日付・内容が既存なら追加しない
    $chk = $conn->prepare("SELECT id FROM karte_records WHERE student_id=? AND record_date=? AND content=? LIMIT 1");
    $chk->bind_param('sss', $sid, $date, $content);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) { $result['skipped']++; $chk->close(); continue; }
    $chk->close();
    $stmt = $conn->prepare("INSERT INTO karte_records (student_id,record_date,record_type,content,teacher,next_action) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('ssssss',$sid,$date,$type,$content,$teacher,$next);
    $stmt->execute() ? $result['records']++ : $result['errors'][] = "指導記録 {$sid}: ".$stmt->error;
    $stmt->close();
}

// ─── 出欠記録 ───────────────────────────────────────
$rows = getSheetRows($xml, '出欠記録');
array_shift($rows);
foreach ($rows as $r) {
    if (empty($r[0])) continue;
    $sid     = trim($r[0]);
    $date    = trim($r[3] ?? '') ?: date('Y-m-d');
    $type    = trim($r[4] ?? '');
    $reason  = trim($r[5] ?? '');
    $contact = trim($r[6] ?? '');
    $notes   = trim($r[7] ?? '');
    // 重複チェック（生徒・日付・種別）
    $chk = $conn->prepare("SELECT id FROM karte_attendance WHERE student_id=? AND att_date=? AND att_type=? LIMIT 1");
    $chk->bind_param('sss',$sid,$date,$type);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) { $result['skipped']++; $chk->close(); continue; }
    $chk->close();
    $stmt = $conn->prepare("INSERT INTO karte_attendance (student_id,att_date,att_type,reason,parent_contacted,notes) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('ssssss',$sid,$date,$type,$reason,$contact,$notes);
    $stmt->execute() ? $result['attendance']++ : $result['errors'][] = "出欠 {$sid}: ".$stmt->error;
    $stmt->close();
}

// ─── 面談記録 ───────────────────────────────────────
$rows = getSheetRows($xml, '面談記録');
array_shift($rows);
foreach ($rows as $r) {
    if (empty($r[0])) continue;
    $sid     = trim($r[0]);
    $date    = trim($r[3] ?? '') ?: date('Y-m-d');
    $type    = trim($r[4] ?? '');
    $parti   = trim($r[5] ?? '');
    $content = trim($r[6] ?? '');
    $next    = trim($r[7] ?? '');
    if (!$content) continue;
    $chk = $conn->prepare("SELECT id FROM karte_interviews WHERE student_id=? AND interview_date=? AND content=? LIMIT 1");
    $chk->bind_param('sss',$sid,$date,$content);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) { $result['skipped']++; $chk->close(); continue; }
    $chk->close();
    $stmt = $conn->prepare("INSERT INTO karte_interviews (student_id,interview_date,interview_type,participants,content,next_action) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('ssssss',$sid,$date,$type,$parti,$content,$next);
    $stmt->execute() ? $result['interviews']++ : $result['errors'][] = "面談 {$sid}: ".$stmt->error;
    $stmt->close();
}

$conn->close();
jout(['success'=>true, 'result'=>$result]);
