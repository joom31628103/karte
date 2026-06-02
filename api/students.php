<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$conn   = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function jout($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function err($m) { http_response_code(400); jout(['success'=>false,'error'=>$m]); }
function e($conn,$v){ return $conn->real_escape_string($v ?? ''); }

/* ===== GET ===== */
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $r = $conn->query("SELECT student_id,name,furigana,class_name,seat_number FROM students ORDER BY class_name,seat_number,student_id");
        $students = [];
        while ($row=$r->fetch_assoc()) $students[] = $row;

        $cr = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name!='' ORDER BY class_name");
        $classes = [];
        while ($row=$cr->fetch_assoc()) $classes[] = $row['class_name'];

        jout(['success'=>true,'students'=>$students,'classes'=>$classes]);
    }

    if ($action === 'export') {
        $r = $conn->query("SELECT student_id,name,class_name,furigana,seat_number FROM students ORDER BY class_name,seat_number,student_id");
        $rows = [];
        while ($row=$r->fetch_assoc()) $rows[] = $row;
        $conn->close();

        $filename = '生徒一覧_'.date('Ymd_His').'.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: no-cache');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        $out = fopen('php://output','w');
        fputcsv($out, ['生徒ID','名前','クラス','ふりがな','出席番号']);
        foreach ($rows as $s) {
            fputcsv($out, [$s['student_id'],$s['name'],$s['class_name'],$s['furigana'],$s['seat_number']]);
        }
        fclose($out);
        exit;
    }

    err('不明なアクション');
}

/* ===== POST ===== */
if ($method === 'POST') {
    // JSON body or form POST
    $body = file_get_contents('php://input');
    $json = json_decode($body, true);
    $action = $json['action'] ?? ($_POST['action'] ?? '');

    // CSRF: フォームPOSTのみ検証（JSON APIはセッション認証のみ）
    if (!$json && !verifyCsrfToken($_POST['csrf_token'] ?? '')) err('トークンエラー');

    switch ($action) {

        case 'add':
            $sid  = e($conn, $json['student_id'] ?? $_POST['student_id'] ?? '');
            $name = e($conn, $json['name']       ?? $_POST['name']       ?? '');
            $cls  = e($conn, $json['class_name'] ?? $_POST['class_name'] ?? '');
            $furi = e($conn, $json['furigana']   ?? $_POST['furigana']   ?? '');
            if (!$sid) err('学籍番号は必須です');
            $conn->query("INSERT INTO students (student_id,name,class_name,furigana) VALUES ('$sid','$name','$cls','$furi')");
            if ($conn->errno === 1062) err('その学籍番号は既に登録されています');
            jout(['success'=>true,'id'=>$conn->insert_id]);

        case 'delete':
            $sid = e($conn, $json['student_id'] ?? $_POST['student_id'] ?? '');
            // カルテデータも削除
            $conn->query("DELETE FROM karte_records    WHERE student_id='$sid'");
            $conn->query("DELETE FROM karte_attendance WHERE student_id='$sid'");
            $conn->query("DELETE FROM karte_interviews WHERE student_id='$sid'");
            $conn->query("DELETE FROM students WHERE student_id='$sid'");
            jout(['success'=>true]);

        case 'bulk_delete':
            $ids = $json['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) err('IDが指定されていません');
            $placeholders = implode(',', array_map(fn($id)=>"'".e($conn,$id)."'", $ids));
            $conn->query("DELETE FROM karte_records    WHERE student_id IN ($placeholders)");
            $conn->query("DELETE FROM karte_attendance WHERE student_id IN ($placeholders)");
            $conn->query("DELETE FROM karte_interviews WHERE student_id IN ($placeholders)");
            $conn->query("DELETE FROM students WHERE student_id IN ($placeholders)");
            jout(['success'=>true,'deleted'=>$conn->affected_rows]);

        case 'change_class':
            $sid = e($conn, $json['student_id'] ?? '');
            $cls = e($conn, $json['class_name'] ?? '');
            $conn->query("UPDATE students SET class_name='$cls' WHERE student_id='$sid'");
            jout(['success'=>true]);

        case 'rename_class':
            $old = e($conn, $json['old_name'] ?? '');
            $new = e($conn, $json['new_name'] ?? '');
            if (!$old || !$new) err('クラス名が不正です');
            $conn->query("UPDATE students SET class_name='$new' WHERE class_name='$old'");
            jout(['success'=>true]);

        case 'delete_class':
            $cls = e($conn, $json['class_name'] ?? '');
            // クラスに生徒がいれば空クラスにする（削除はしない）
            $cnt = (int)$conn->query("SELECT COUNT(*) AS c FROM students WHERE class_name='$cls'")->fetch_assoc()['c'];
            if ($cnt > 0) {
                $conn->query("UPDATE students SET class_name='' WHERE class_name='$cls'");
            }
            jout(['success'=>true,'cleared'=>$cnt]);

        case 'import':
            // CSV インポート（multipart/form-data）
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) err('トークンエラー');
            if (empty($_FILES['csv'])) err('CSVファイルが送信されていません');
            $file = $_FILES['csv'];
            if ($file['error'] !== UPLOAD_ERR_OK) err('アップロードエラー: '.$file['error']);

            $content = file_get_contents($file['tmp_name']);
            $content = ltrim($content, "\xEF\xBB\xBF"); // UTF-8 BOM 除去
            $lines   = preg_split('/\r\n|\r|\n/', trim($content));
            if (count($lines) < 2) err('データが空です（ヘッダー行のみ）');
            array_shift($lines); // ヘッダー行スキップ

            $created=0; $updated=0; $errors=[];
            foreach ($lines as $lineNum => $line) {
                if (trim($line)==='') continue;
                $tmp = str_getcsv($line);
                if (count($tmp) < 3) {
                    $errors[] = '行'.($lineNum+2).': 列数不足（生徒ID,名前,クラス の3列以上必要）';
                    continue;
                }
                $sid  = trim($tmp[0]);
                $name = trim($tmp[1]);
                $cls  = trim($tmp[2]);
                $furi = isset($tmp[3]) ? trim($tmp[3]) : '';
                $seat = isset($tmp[4]) && $tmp[4]!=='' ? (int)$tmp[4] : null;

                if ($sid === '') {
                    $errors[] = '行'.($lineNum+2).': 生徒IDが空です';
                    continue;
                }

                $sidE  = e($conn,$sid);
                $nameE = e($conn,$name);
                $clsE  = e($conn,$cls);
                $furiE = e($conn,$furi);
                $seatV = $seat !== null ? (int)$seat : 'NULL';

                $exists = $conn->query("SELECT 1 FROM students WHERE student_id='$sidE'")->num_rows;
                if ($exists) {
                    $conn->query("UPDATE students SET name='$nameE',class_name='$clsE',furigana='$furiE',seat_number=$seatV WHERE student_id='$sidE'");
                    $updated++;
                } else {
                    $conn->query("INSERT INTO students (student_id,name,class_name,furigana,seat_number) VALUES ('$sidE','$nameE','$clsE','$furiE',$seatV)");
                    $created++;
                }
            }
            jout(['success'=>true,'created'=>$created,'updated'=>$updated,'errors'=>$errors]);

        default: err('不明なアクション');
    }
}
