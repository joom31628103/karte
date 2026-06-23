<?php
require_once '../config.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

$conn   = getDB();
$action = $_REQUEST['action'] ?? '';

function jout(array $data): never { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function err(string $msg): never  { http_response_code(400); jout(['success'=>false,'error'=>$msg]); }

// Prepared Statement ヘルパー
function ps(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result|bool {
    $stmt = $conn->prepare($sql);
    if (!$stmt) err('クエリエラー: '.$conn->error);
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result() ?: true;
    $stmt->close();
    return $result;
}

// 操作ログ記録（activity_log テーブルが未作成の場合は静かにスキップ）
function logActivity(mysqli $conn, string $studentId, string $actionType, string $detail): void {
    if (!$studentId) return;
    $tid   = (int)($_SESSION['teacher_id'] ?? 0);
    $tname = $_SESSION['teacher_name'] ?? '';
    $stmt  = @$conn->prepare("INSERT INTO activity_log (teacher_id,teacher_name,student_id,action_type,detail) VALUES (?,?,?,?,?)");
    if (!$stmt) return;
    $det = mb_strimwidth($detail, 0, 300, '…');
    $stmt->bind_param('issss', $tid, $tname, $studentId, $actionType, $det);
    @$stmt->execute();
    $stmt->close();
}

/* ===== GET ===== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {

        case 'student_summary':
            $result = ps($conn, "
                SELECT s.student_id, s.name, s.furigana, s.class_name, s.seat_number,
                    (SELECT COUNT(*) FROM karte_records   kr WHERE kr.student_id=s.student_id) AS rec_count,
                    (SELECT COUNT(*) FROM karte_attendance ka WHERE ka.student_id=s.student_id) AS att_count,
                    (SELECT MAX(record_date) FROM karte_records kr2 WHERE kr2.student_id=s.student_id) AS last_record
                FROM students s
                ORDER BY s.class_name, s.seat_number, s.student_id
            ");
            $rows = [];
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            jout(['success'=>true,'rows'=>$rows]);

        case 'list_records':
            $sid    = $_GET['student_id'] ?? '';
            $result = ps($conn, "SELECT * FROM karte_records WHERE student_id=? ORDER BY record_date DESC, id DESC", 's', [$sid]);
            $rows   = [];
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            jout(['success'=>true,'rows'=>$rows]);

        case 'list_attendance':
            $sid    = $_GET['student_id'] ?? '';
            $result = ps($conn, "SELECT * FROM karte_attendance WHERE student_id=? ORDER BY att_date DESC, id DESC", 's', [$sid]);
            $rows   = [];
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            jout(['success'=>true,'rows'=>$rows]);

        case 'list_interviews':
            $sid    = $_GET['student_id'] ?? '';
            $result = ps($conn, "SELECT * FROM karte_interviews WHERE student_id=? ORDER BY interview_date DESC, id DESC", 's', [$sid]);
            $rows   = [];
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            jout(['success'=>true,'rows'=>$rows]);

        case 'get_gakno':
            $sid = $_GET['student_id'] ?? '';
            $r   = ps($conn, "SELECT gakno FROM students WHERE student_id=?", 's', [$sid]);
            $row = $r->fetch_assoc();
            jout(['success'=>true,'gakno'=>$row['gakno'] ?? '']);

        case 'get_memos':
            $sid = $_GET['student_id'] ?? '';
            $r   = ps($conn, "SELECT memo_posi,memo_nega,memo_main FROM students WHERE student_id=?", 's', [$sid]);
            $row = $r->fetch_assoc();
            jout(['success'=>true,'posi'=>$row['memo_posi']??'','nega'=>$row['memo_nega']??'','main'=>$row['memo_main']??'']);

        case 'list_history':
            $sid = $_GET['student_id'] ?? '';
            if (!$sid) err('student_id が必要です');
            $result = @$conn->query(
                "SELECT * FROM activity_log WHERE student_id='".$conn->real_escape_string($sid)."' ORDER BY created_at DESC LIMIT 300"
            );
            $rows = [];
            if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
            jout(['success'=>true,'rows'=>$rows]);

        default: err('不明なアクション');
    }
}

/* ===== POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) err('トークンエラー');

    switch ($action) {

        case 'add_student':
            $sid  = trim($_POST['student_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $cls  = trim($_POST['class_name'] ?? '');
            $furi = trim($_POST['furigana'] ?? '');
            if (!$sid || !$name) err('学籍番号と氏名は必須です');
            $stmt = $conn->prepare("INSERT INTO students (student_id,name,class_name,furigana) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $sid, $name, $cls, $furi);
            if (!$stmt->execute()) {
                if ($conn->errno === 1062) err('その学籍番号は既に登録されています');
                err('登録エラー');
            }
            jout(['success'=>true,'id'=>$stmt->insert_id]);

        case 'save_basic':
            $sid  = trim($_POST['student_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $furi = trim($_POST['furigana'] ?? '');
            $cls  = trim($_POST['class_name'] ?? '');
            $seat = $_POST['seat_number'] !== '' ? (int)$_POST['seat_number'] : null;
            $gender  = trim($_POST['gender'] ?? '');
            $bday    = trim($_POST['birthday'] ?? '') ?: null;
            $phone   = trim($_POST['phone'] ?? '');
            $parent  = trim($_POST['parent_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $notes   = trim($_POST['notes'] ?? '');
            $stmt = $conn->prepare("UPDATE students SET name=?,furigana=?,class_name=?,seat_number=?,gender=?,birthday=?,phone=?,parent_name=?,address=?,notes=?,updated_at=NOW() WHERE student_id=?");
            $stmt->bind_param('sssississss', $name,$furi,$cls,$seat,$gender,$bday,$phone,$parent,$address,$notes,$sid);
            $stmt->execute();
            if ($name) logActivity($conn, $sid, '基本情報を更新', $name ? "氏名: $name クラス: $cls" : '家庭状況メモを更新');
            jout(['success'=>true]);

        case 'add_record':
            $sid     = trim($_POST['student_id'] ?? '');
            $date    = trim($_POST['record_date'] ?? date('Y-m-d'));
            $type    = trim($_POST['record_type'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $teacher = trim($_POST['teacher'] ?? '');
            $next    = trim($_POST['next_action'] ?? '');
            if (!$content) err('内容は必須です');
            $stmt = $conn->prepare("INSERT INTO karte_records (student_id,record_date,record_type,content,teacher,next_action) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssssss', $sid,$date,$type,$content,$teacher,$next);
            $stmt->execute();
            logActivity($conn, $sid, '指導記録を追加', "[$type] $date — $content");
            jout(['success'=>true,'id'=>$stmt->insert_id]);

        case 'update_record':
            $id      = (int)($_POST['id'] ?? 0);
            $date    = trim($_POST['record_date'] ?? '');
            $type    = trim($_POST['record_type'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $teacher = trim($_POST['teacher'] ?? '');
            $next    = trim($_POST['next_action'] ?? '');
            if (!$id) err('IDが不正です');
            // 変更前のstudent_idを取得
            $prevR = ps($conn, "SELECT student_id FROM karte_records WHERE id=?", 'i', [$id]);
            $prevRow = $prevR ? $prevR->fetch_assoc() : null;
            $recSid = $prevRow['student_id'] ?? '';
            $stmt = $conn->prepare("UPDATE karte_records SET record_date=?,record_type=?,content=?,teacher=?,next_action=? WHERE id=?");
            $stmt->bind_param('sssssi', $date,$type,$content,$teacher,$next,$id);
            $stmt->execute();
            logActivity($conn, $recSid, '指導記録を編集', "[$type] $date — $content");
            jout(['success'=>true]);

        case 'delete_record':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) err('IDが不正です');
            $prevR = ps($conn, "SELECT student_id,record_type,record_date FROM karte_records WHERE id=?", 'i', [$id]);
            $prevRow = $prevR ? $prevR->fetch_assoc() : null;
            ps($conn, "DELETE FROM karte_records WHERE id=?", 'i', [$id]);
            if ($prevRow) logActivity($conn, $prevRow['student_id'], '指導記録を削除', "[{$prevRow['record_type']}] {$prevRow['record_date']}");
            jout(['success'=>true]);

        case 'add_attendance':
            $sid     = trim($_POST['student_id'] ?? '');
            $date    = trim($_POST['att_date'] ?? date('Y-m-d'));
            $type    = trim($_POST['att_type'] ?? '');
            $reason  = trim($_POST['reason'] ?? '');
            $contact = trim($_POST['parent_contacted'] ?? '未');
            $notes   = trim($_POST['notes'] ?? '');
            $stmt = $conn->prepare("INSERT INTO karte_attendance (student_id,att_date,att_type,reason,parent_contacted,notes) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssssss', $sid,$date,$type,$reason,$contact,$notes);
            $stmt->execute();
            logActivity($conn, $sid, '出欠記録を追加', "[$type] $date" . ($reason ? " 理由: $reason" : ''));
            jout(['success'=>true,'id'=>$stmt->insert_id]);

        case 'delete_attendance':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) err('IDが不正です');
            $prevR = ps($conn, "SELECT student_id,att_type,att_date FROM karte_attendance WHERE id=?", 'i', [$id]);
            $prevRow = $prevR ? $prevR->fetch_assoc() : null;
            ps($conn, "DELETE FROM karte_attendance WHERE id=?", 'i', [$id]);
            if ($prevRow) logActivity($conn, $prevRow['student_id'], '出欠記録を削除', "[{$prevRow['att_type']}] {$prevRow['att_date']}");
            jout(['success'=>true]);

        case 'add_interview':
            $sid     = trim($_POST['student_id'] ?? '');
            $date    = trim($_POST['interview_date'] ?? date('Y-m-d'));
            $type    = trim($_POST['interview_type'] ?? '');
            $parti   = trim($_POST['participants'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $next    = trim($_POST['next_action'] ?? '');
            if (!$content) err('内容は必須です');
            $stmt = $conn->prepare("INSERT INTO karte_interviews (student_id,interview_date,interview_type,participants,content,next_action) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssssss', $sid,$date,$type,$parti,$content,$next);
            $stmt->execute();
            logActivity($conn, $sid, '面談記録を追加', "[$type] $date" . ($parti ? " 参加: $parti" : '') . " — $content");
            jout(['success'=>true,'id'=>$stmt->insert_id]);

        case 'delete_interview':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) err('IDが不正です');
            $prevR = ps($conn, "SELECT student_id,interview_type,interview_date FROM karte_interviews WHERE id=?", 'i', [$id]);
            $prevRow = $prevR ? $prevR->fetch_assoc() : null;
            ps($conn, "DELETE FROM karte_interviews WHERE id=?", 'i', [$id]);
            if ($prevRow) logActivity($conn, $prevRow['student_id'], '面談記録を削除', "[{$prevRow['interview_type']}] {$prevRow['interview_date']}");
            jout(['success'=>true]);

        case 'save_gakno':
            $sid   = trim($_POST['student_id'] ?? '');
            $gakno = trim($_POST['gakno'] ?? '') ?: null;

            // 紐づけ時：students.photo を gakuseki.photo へ移行（gakuseki に写真がない場合のみ）
            if ($gakno) {
                $rs = ps($conn, "SELECT photo FROM students WHERE student_id=?", 's', [$sid]);
                $rg = ps($conn, "SELECT photo FROM gakuseki WHERE gakno=?", 's', [$gakno]);
                $studentPhoto = ($rs->fetch_assoc())['photo'] ?? null;
                $gakPhoto     = ($rg->fetch_assoc())['photo'] ?? null;
                if ($studentPhoto && !$gakPhoto) {
                    ps($conn, "UPDATE gakuseki SET photo=? WHERE gakno=?", 'ss', [$studentPhoto, $gakno]);
                    ps($conn, "UPDATE students SET photo=NULL WHERE student_id=?", 's', [$sid]);
                }
            }

            $stmt  = $conn->prepare("UPDATE students SET gakno=? WHERE student_id=?");
            $stmt->bind_param('ss', $gakno, $sid);
            $stmt->execute();
            jout(['success'=>true]);

        case 'save_memos':
            $sid  = trim($_POST['student_id'] ?? '');
            $posi = trim($_POST['posi'] ?? '');
            $nega = trim($_POST['nega'] ?? '');
            $main = trim($_POST['main'] ?? '');
            $stmt = $conn->prepare("UPDATE students SET memo_posi=?,memo_nega=?,memo_main=? WHERE student_id=?");
            $stmt->bind_param('ssss', $posi,$nega,$main,$sid);
            $stmt->execute();
            logActivity($conn, $sid, 'メモ・所見を更新', ($posi ? "ポジ: $posi " : '') . ($nega ? "ネガ: $nega" : ''));
            jout(['success'=>true]);

        default: err('不明なアクション');
    }
}
