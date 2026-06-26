<?php
require_once '../config.php';
require_once '../lib/backup.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

$conn   = getDB();
$action = $_REQUEST['action'] ?? '';

// バックアップ付きjout: 成功レスポンスを返す前に対象生徒のJSONを更新
function jout_backup(mysqli $conn, array $data, string $sid = ''): never {
    if ($sid) karteBackupStudent($conn, $sid);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

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

// 操作ログ記録（テーブルが未作成の場合は自動作成してから記録）
function logActivity(mysqli $conn, string $studentId, string $actionType, string $detail): void {
    if (!$studentId) return;
    // テーブルが存在しなければ作成（MySQL 5.x 対応: CREATE TABLE IF NOT EXISTS は使用可能）
    $conn->query("CREATE TABLE IF NOT EXISTS activity_log (
        id           INT PRIMARY KEY AUTO_INCREMENT,
        teacher_id   INT NOT NULL DEFAULT 0,
        teacher_name VARCHAR(100) DEFAULT '',
        student_id   VARCHAR(10) NOT NULL,
        action_type  VARCHAR(50) NOT NULL,
        detail       TEXT,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $tid   = (int)($_SESSION['teacher_id'] ?? 0);
    $tname = $_SESSION['teacher_name'] ?? '';
    $stmt  = $conn->prepare("INSERT INTO activity_log (teacher_id,teacher_name,student_id,action_type,detail) VALUES (?,?,?,?,?)");
    if (!$stmt) return;
    $det = mb_strimwidth($detail, 0, 300, '…');
    $stmt->bind_param('issss', $tid, $tname, $studentId, $actionType, $det);
    $stmt->execute();
    $stmt->close();
}

/* ===== GET ===== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {

        case 'keepalive':
            jout(['success'=>true]);

        case 'header_list':
            $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
            if (empty($ids)) {
                // 全件
                $result = $conn->query("
                    SELECT s.student_id, s.name, s.furigana, s.photo,
                           s.phone, s.parent_name, s.birthday, s.gender, s.address,
                           g.tel1, g.hogosya, g.birthday AS g_birthday, g.seibetu, g.jyusyo, g.yuubin,
                           g.shusshin_chugaku, g.photo AS g_photo,
                           sn.nendo, sn.gakunen, sn.class_no, sn.bango
                    FROM students s
                    LEFT JOIN gakuseki g ON s.gakno = g.gakno
                    LEFT JOIN (
                        SELECT sn2.gakno, sn2.nendo, sn2.gakunen, sn2.class_no, sn2.bango
                        FROM student_nendo sn2
                        INNER JOIN (SELECT gakno, MAX(nendo) AS mn FROM student_nendo GROUP BY gakno) m
                        ON sn2.gakno = m.gakno AND sn2.nendo = m.mn
                    ) sn ON s.gakno = sn.gakno
                    ORDER BY sn.gakunen, sn.class_no, sn.bango, s.student_id
                ");
            } else {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('s', count($ids));
                $result = ps($conn, "
                    SELECT s.student_id, s.name, s.furigana, s.photo,
                           s.phone, s.parent_name, s.birthday, s.gender, s.address,
                           g.tel1, g.hogosya, g.birthday AS g_birthday, g.seibetu, g.jyusyo, g.yuubin,
                           g.shusshin_chugaku, g.photo AS g_photo,
                           sn.nendo, sn.gakunen, sn.class_no, sn.bango
                    FROM students s
                    LEFT JOIN gakuseki g ON s.gakno = g.gakno
                    LEFT JOIN (
                        SELECT sn2.gakno, sn2.nendo, sn2.gakunen, sn2.class_no, sn2.bango
                        FROM student_nendo sn2
                        INNER JOIN (SELECT gakno, MAX(nendo) AS mn FROM student_nendo GROUP BY gakno) m
                        ON sn2.gakno = m.gakno AND sn2.nendo = m.mn
                    ) sn ON s.gakno = sn.gakno
                    WHERE s.student_id IN ($ph)
                    ORDER BY sn.gakunen, sn.class_no, sn.bango, s.student_id
                ", $types, $ids);
            }
            $rows = [];
            while ($r2 = $result->fetch_assoc()) {
                $photo = $r2['g_photo'] ?: $r2['photo'];
                $rows[] = [
                    'student_id' => $r2['student_id'],
                    'name'       => $r2['name'],
                    'furigana'   => $r2['furigana'],
                    'nendo'      => $r2['nendo'] ?? '',
                    'gakunen'    => $r2['gakunen'] ?? '',
                    'class_no'   => $r2['class_no'] ?? $r2['class_name'] ?? '',
                    'bango'      => $r2['bango'] ?? $r2['seat_number'] ?? '',
                    'shusshin'   => $r2['shusshin_chugaku'] ?? '',
                    'hogosya'    => $r2['hogosya'] ?: $r2['parent_name'],
                    'tel'        => $r2['tel1'] ?: $r2['phone'],
                    'birthday'   => $r2['g_birthday'] ?: $r2['birthday'],
                    'seibetu'    => $r2['seibetu'] ?: $r2['gender'],
                    'address'    => $r2['yuubin'] ? '〒'.$r2['yuubin'].' '.$r2['jyusyo'] : ($r2['jyusyo'] ?: $r2['address']),
                    'photo'      => $photo ? '/karte/uploads/photos/'.rawurlencode($photo) : '',
                ];
            }
            jout(['success'=>true,'rows'=>$rows]);

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
            // memo_* カラムが未作成の場合も空で返す
            $cols = $conn->query("SHOW COLUMNS FROM students LIKE 'memo_posi'")->num_rows > 0
                ? "memo_posi,memo_nega,memo_main" : "'' AS memo_posi,'' AS memo_nega,'' AS memo_main";
            $r   = ps($conn, "SELECT {$cols} FROM students WHERE student_id=?", 's', [$sid]);
            $row = $r->fetch_assoc();
            jout(['success'=>true,'posi'=>$row['memo_posi']??'','nega'=>$row['memo_nega']??'','main'=>$row['memo_main']??'']);

        case 'get_student':
            $sid = $_GET['student_id'] ?? '';
            if (!$sid) err('student_id が必要です');
            $s = $conn->query("SELECT * FROM students WHERE student_id='".$conn->real_escape_string($sid)."'")->fetch_assoc();
            if (!$s) err('生徒が見つかりません');
            $gakno = $s['gakno'] ?? '';
            $gak = null; $nendo_list = [];
            if ($gakno) {
                $gak = $conn->query("SELECT * FROM gakuseki WHERE gakno='".$conn->real_escape_string($gakno)."'")->fetch_assoc();
                $nr  = $conn->query("SELECT sn.*, t.display_name AS tanninmei FROM student_nendo sn LEFT JOIN teachers t ON sn.teacher_id=t.id WHERE sn.gakno='".$conn->real_escape_string($gakno)."' ORDER BY sn.nendo");
                while ($row=$nr->fetch_assoc()) $nendo_list[]=$row;
            }
            $ln = end($nendo_list) ?: null;
            jout(['success'=>true,'data'=>[
                'student_id' => $sid,
                'gakno'      => $gakno,
                'dispName'   => $gak['name']      ?? $s['name']        ?? '',
                'dispFuri'   => $gak['furigana']  ?? $s['furigana']    ?? '',
                'dispBday'   => $gak['birthday']  ?? $s['birthday']    ?? '',
                'dispTel'    => $gak['tel1']      ?? $s['phone']       ?? '',
                'dispJyusyo' => $gak ? trim(($gak['yuubin'] ? ' 〒'.$gak['yuubin'].' ' : '').$gak['jyusyo']) : ($s['address'] ?? ''),
                'dispHogosya'=> $gak['hogosya']   ?? $s['parent_name'] ?? '',
                'dispSeibetu'=> $gak['seibetu']   ?? $s['gender']      ?? '',
                'dispGakunen'=> $ln['gakunen']    ?? '',
                'dispClass'  => $ln['class_no']   ?? $s['class_name']  ?? '',
                'dispBango'  => $ln['bango']      ?? $s['seat_number'] ?? '',
                'dispNendo'  => $ln['nendo']      ?? '',
                'dispTannin' => $ln['tanninmei']  ?? '',
                'dispStatus'  => $gak['gakuseki_status'] ?? '',
                'dispShusshin'=> $gak['shusshin_chugaku'] ?? $s['school_from'] ?? '',
                'dispPhoto'  => $gak['photo']     ?? $s['photo']       ?? '',
                'memo_posi'  => $s['memo_posi']   ?? '',
                'memo_nega'  => $s['memo_nega']   ?? '',
                'memo_main'  => $s['memo_main']   ?? '',
                'notes'      => $s['notes']       ?? '',
                'class_name' => $s['class_name']  ?? '',
                'seat_number'=> $s['seat_number'] ?? '',
                'school_from'=> $gak['shusshin_chugaku'] ?? $s['school_from'] ?? '',
                'student_phone'=> $s['student_phone'] ?? '',
                'name'       => $s['name']        ?? '',
                'furigana'   => $s['furigana']    ?? '',
                'birthday'   => $s['birthday']    ?? '',
                'gender'     => $s['gender']      ?? '',
                'phone'      => $s['phone']       ?? '',
                'parent_name'=> $s['parent_name'] ?? '',
                'address'    => $s['address']     ?? '',
                'b_notes'    => $s['notes']       ?? '',
                // 保護者1
                'parent1_name'      => $s['parent1_name']       ?? '',
                'parent1_furi'      => $s['parent1_furi']       ?? '',
                'parent1_phone'     => $s['parent1_phone']      ?? '',
                'parent1_phone_note'=> $s['parent1_phone_note'] ?? '',
                'parent1_work_name' => $s['parent1_work_name']  ?? '',
                'parent1_work_phone'=> $s['parent1_work_phone'] ?? '',
                'parent1_work_note' => $s['parent1_work_note']  ?? '',
                // 保護者2
                'parent2_name'      => $s['parent2_name']       ?? '',
                'parent2_furi'      => $s['parent2_furi']       ?? '',
                'parent2_phone'     => $s['parent2_phone']      ?? '',
                'parent2_phone_note'=> $s['parent2_phone_note'] ?? '',
                'parent2_work_name' => $s['parent2_work_name']  ?? '',
                'parent2_work_phone'=> $s['parent2_work_phone'] ?? '',
                'parent2_work_note' => $s['parent2_work_note']  ?? '',
                'primary_parent'    => $s['primary_parent']     ?? '1',
                'gak_name'   => $gak['name']      ?? '',
                'gak_furigana'=> $gak['furigana'] ?? '',
                'gak_birthday'=> $gak['birthday'] ?? '',
                'gak_seibetu'=> $gak['seibetu']   ?? '',
                // 学籍台帳フィールド
                'gak_hogosya' => $gak['hogosya']  ?? '',
                'gak_hogokana'=> $gak['hogokana'] ?? '',
                'gak_zokugara'=> $gak['zokugara'] ?? '',
                'gak_tel1'   => $gak['tel1']      ?? '',
                'gak_tel2'   => $gak['tel2']      ?? '',
                'gak_yuubin' => $gak['yuubin']    ?? '',
                'gak_jyusyo' => $gak['jyusyo']    ?? '',
                'nendo_list' => $nendo_list,
            ]]);

        case 'list_history':
            $sid = $_GET['student_id'] ?? '';
            if (!$sid) err('student_id が必要です');
            // activity_log テーブルが未作成の場合は空配列を返す
            $tableExists = $conn->query("SHOW TABLES LIKE 'activity_log'")->num_rows > 0;
            $rows = [];
            if ($tableExists) {
                $result = $conn->query(
                    "SELECT * FROM activity_log WHERE student_id='".$conn->real_escape_string($sid)."' ORDER BY created_at DESC LIMIT 300"
                );
                if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
            }
            jout(['success'=>true,'rows'=>$rows]);

        case 'search_students':
            $name    = trim($_GET['name']     ?? '');
            $furi    = trim($_GET['furi']     ?? '');
            $gakunen = trim($_GET['gakunen']  ?? '');
            $classno = trim($_GET['class_no'] ?? '');
            $bango   = trim($_GET['bango']    ?? '');
            $shusshin= trim($_GET['shusshin'] ?? '');
            $seibetu = trim($_GET['seibetu']  ?? '');
            $nendo   = trim($_GET['nendo']    ?? '');
            $birthday= trim($_GET['birthday'] ?? '');
            $address = trim($_GET['address']  ?? '');
            $hogosya = trim($_GET['hogosya']  ?? '');
            $tel     = trim($_GET['tel']      ?? '');

            $sql = "SELECT DISTINCT s.student_id
                    FROM students s
                    LEFT JOIN gakuseki g ON s.gakno = g.gakno
                    LEFT JOIN (
                        SELECT sn.gakno, sn.nendo, sn.gakunen, sn.class_no, sn.bango
                        FROM student_nendo sn
                        INNER JOIN (
                            SELECT gakno, MAX(nendo) AS maxnendo FROM student_nendo GROUP BY gakno
                        ) m ON sn.gakno = m.gakno AND sn.nendo = m.maxnendo
                    ) sn ON s.gakno = sn.gakno
                    WHERE 1=1";
            $types = ''; $params = [];

            if ($name)    { $sql .= " AND (s.name LIKE ? OR g.name LIKE ?)";                            $params[]= "%$name%";     $params[]= "%$name%";     $types .= 'ss'; }
            if ($furi)    { $sql .= " AND (s.furigana LIKE ? OR g.furigana LIKE ?)";                    $params[]= "%$furi%";     $params[]= "%$furi%";     $types .= 'ss'; }
            if ($gakunen) { $sql .= " AND sn.gakunen = ?";                                              $params[]= $gakunen;                               $types .= 's';  }
            if ($classno) { $sql .= " AND (sn.class_no = ? OR s.class_name = ?)";                       $params[]= $classno;      $params[]= $classno;      $types .= 'ss'; }
            if ($bango)   { $sql .= " AND (sn.bango = ? OR s.seat_number = ?)";                         $params[]= $bango;        $params[]= $bango;        $types .= 'ss'; }
            if ($shusshin){ $sql .= " AND g.shusshin_chugaku LIKE ?";                                   $params[]= "%$shusshin%";                           $types .= 's';  }
            if ($seibetu) { $sql .= " AND g.seibetu = ?";                                               $params[]= $seibetu;                               $types .= 's';  }
            if ($nendo)   { $sql .= " AND sn.nendo = ?";                                                $params[]= $nendo;                                 $types .= 's';  }
            if ($birthday){ $sql .= " AND (g.birthday LIKE ? OR s.birthday LIKE ?)";                    $params[]= "%$birthday%"; $params[]= "%$birthday%"; $types .= 'ss'; }
            if ($address) { $sql .= " AND g.jyusyo LIKE ?";                                             $params[]= "%$address%";                           $types .= 's';  }
            if ($hogosya) { $sql .= " AND (g.hogosya LIKE ? OR s.parent_name LIKE ?)";                  $params[]= "%$hogosya%";  $params[]= "%$hogosya%";  $types .= 'ss'; }
            if ($tel)     { $sql .= " AND (g.tel1 LIKE ? OR g.tel2 LIKE ? OR s.phone LIKE ?)";         $params[]= "%$tel%";      $params[]= "%$tel%";      $params[]= "%$tel%"; $types .= 'sss'; }

            $sql .= " ORDER BY s.class_name, s.seat_number, s.student_id";
            $res = ps($conn, $sql, $types, $params);
            $ids = [];
            while ($r = $res->fetch_assoc()) $ids[] = $r['student_id'];
            jout(['success'=>true, 'ids'=>$ids, 'total'=>count($ids)]);

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
            jout_backup($conn, ['success'=>true,'id'=>$stmt->insert_id], $sid);

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
            // 新フィールド
            $school_from       = trim($_POST['school_from'] ?? '');
            $student_phone     = trim($_POST['student_phone'] ?? '');
            $parent1_name      = trim($_POST['parent1_name'] ?? '');
            $parent1_furi      = trim($_POST['parent1_furi'] ?? '');
            $parent1_phone     = trim($_POST['parent1_phone'] ?? '');
            $parent1_phone_note= trim($_POST['parent1_phone_note'] ?? '');
            $parent2_name      = trim($_POST['parent2_name'] ?? '');
            $parent2_furi      = trim($_POST['parent2_furi'] ?? '');
            $parent2_phone     = trim($_POST['parent2_phone'] ?? '');
            $parent2_phone_note= trim($_POST['parent2_phone_note'] ?? '');
            $p1_work_name      = trim($_POST['parent1_work_name'] ?? '');
            $p1_work_phone     = trim($_POST['parent1_work_phone'] ?? '');
            $p1_work_note      = trim($_POST['parent1_work_note'] ?? '');
            $p2_work_name      = trim($_POST['parent2_work_name'] ?? '');
            $p2_work_phone     = trim($_POST['parent2_work_phone'] ?? '');
            $p2_work_note      = trim($_POST['parent2_work_note'] ?? '');
            $primary_parent    = in_array($_POST['primary_parent']??'1',['1','2']) ? $_POST['primary_parent'] : '1';
            // 未存在列を自動追加
            $addCols = [
                "school_from VARCHAR(100) DEFAULT NULL",
                "student_phone VARCHAR(50) DEFAULT NULL",
                "parent1_name VARCHAR(100) DEFAULT NULL",
                "parent1_furi VARCHAR(100) DEFAULT NULL",
                "parent1_phone VARCHAR(50) DEFAULT NULL",
                "parent1_phone_note VARCHAR(200) DEFAULT NULL",
                "parent2_name VARCHAR(100) DEFAULT NULL",
                "parent2_furi VARCHAR(100) DEFAULT NULL",
                "parent2_phone VARCHAR(50) DEFAULT NULL",
                "parent2_phone_note VARCHAR(200) DEFAULT NULL",
                "parent1_work_name VARCHAR(100) DEFAULT NULL",
                "parent1_work_phone VARCHAR(50) DEFAULT NULL",
                "parent1_work_note VARCHAR(200) DEFAULT NULL",
                "parent2_work_name VARCHAR(100) DEFAULT NULL",
                "parent2_work_phone VARCHAR(50) DEFAULT NULL",
                "parent2_work_note VARCHAR(200) DEFAULT NULL",
                "primary_parent CHAR(1) DEFAULT '1'",
            ];
            foreach ($addCols as $colDef) {
                try { $conn->query("ALTER TABLE students ADD COLUMN $colDef"); } catch(Exception $e) {}
            }
            $stmt = $conn->prepare("UPDATE students SET name=?,furigana=?,class_name=?,seat_number=?,gender=?,birthday=?,phone=?,parent_name=?,address=?,notes=?,school_from=?,student_phone=?,parent1_name=?,parent1_furi=?,parent1_phone=?,parent1_phone_note=?,parent2_name=?,parent2_furi=?,parent2_phone=?,parent2_phone_note=?,parent1_work_name=?,parent1_work_phone=?,parent1_work_note=?,parent2_work_name=?,parent2_work_phone=?,parent2_work_note=?,primary_parent=?,updated_at=NOW() WHERE student_id=?");
            $stmt->bind_param('sssississsssssssssssssssssss',
                $name,$furi,$cls,$seat,$gender,$bday,$phone,$parent,$address,$notes,
                $school_from,$student_phone,
                $parent1_name,$parent1_furi,$parent1_phone,$parent1_phone_note,
                $parent2_name,$parent2_furi,$parent2_phone,$parent2_phone_note,
                $p1_work_name,$p1_work_phone,$p1_work_note,
                $p2_work_name,$p2_work_phone,$p2_work_note,
                $primary_parent,$sid);
            $stmt->execute();
            if ($name) logActivity($conn, $sid, '基本情報を更新', "氏名: $name クラス: $cls");
            jout_backup($conn, ['success'=>true], $sid);

        case 'sync_to_gakuseki':
            $sid          = trim($_POST['student_id'] ?? '');
            $name         = trim($_POST['name'] ?? '');
            $furigana     = trim($_POST['furigana'] ?? '');
            $seibetu      = trim($_POST['gender'] ?? '');
            $birthday     = trim($_POST['birthday'] ?? '') ?: null;
            $jyusyo       = trim($_POST['address'] ?? '');
            $hogosya      = trim($_POST['parent_name'] ?? '');
            $hogokana     = trim($_POST['parent_furi'] ?? '');
            $tel1         = trim($_POST['tel1'] ?? '');
            $tel2         = trim($_POST['tel2'] ?? '');
            $shusshin     = trim($_POST['shusshin_chugaku'] ?? '');
            if (!$sid) err('student_id は必須です');
            $r = ps($conn, "SELECT gakno FROM students WHERE student_id=?", 's', [$sid]);
            $row = $r->fetch_assoc();
            if (!$row || !$row['gakno']) err('学籍台帳とリンクされていません');
            $gakno = $row['gakno'];
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN shusshin_chugaku VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
            $stmt = $conn->prepare("UPDATE gakuseki SET name=?,furigana=?,seibetu=?,birthday=?,jyusyo=?,hogosya=?,hogokana=?,tel1=?,tel2=?,shusshin_chugaku=?,updated_at=NOW() WHERE gakno=?");
            $stmt->bind_param('sssssssssss', $name,$furigana,$seibetu,$birthday,$jyusyo,$hogosya,$hogokana,$tel1,$tel2,$shusshin,$gakno);
            $stmt->execute();
            logActivity($conn, $sid, '学籍台帳を上書き', "氏名: $name");
            jout_backup($conn, ['success'=>true], $sid);

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
            jout_backup($conn, ['success'=>true,'id'=>$stmt->insert_id], $sid);

        case 'update_record':
            $id      = (int)($_POST['id'] ?? 0);
            $date    = trim($_POST['record_date'] ?? '');
            $type    = trim($_POST['record_type'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $teacher = trim($_POST['teacher'] ?? '');
            $next    = trim($_POST['next_action'] ?? '');
            if (!$id) err('IDが不正です');
            $prevR = ps($conn, "SELECT student_id FROM karte_records WHERE id=?", 'i', [$id]);
            $prevRow = $prevR ? $prevR->fetch_assoc() : null;
            $recSid = $prevRow['student_id'] ?? '';
            $stmt = $conn->prepare("UPDATE karte_records SET record_date=?,record_type=?,content=?,teacher=?,next_action=? WHERE id=?");
            $stmt->bind_param('sssssi', $date,$type,$content,$teacher,$next,$id);
            $stmt->execute();
            logActivity($conn, $recSid, '指導記録を編集', "[$type] $date — $content");
            jout_backup($conn, ['success'=>true], $recSid);

        case 'delete_record':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) err('IDが不正です');
            $prevR = ps($conn, "SELECT student_id,record_type,record_date FROM karte_records WHERE id=?", 'i', [$id]);
            $prevRow = $prevR ? $prevR->fetch_assoc() : null;
            ps($conn, "DELETE FROM karte_records WHERE id=?", 'i', [$id]);
            if ($prevRow) logActivity($conn, $prevRow['student_id'], '指導記録を削除', "[{$prevRow['record_type']}] {$prevRow['record_date']}");
            jout_backup($conn, ['success'=>true], $prevRow['student_id'] ?? '');

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
            jout_backup($conn, ['success'=>true,'id'=>$stmt->insert_id], $sid);

        case 'delete_attendance':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) err('IDが不正です');
            $prevR = ps($conn, "SELECT student_id,att_type,att_date FROM karte_attendance WHERE id=?", 'i', [$id]);
            $prevRow = $prevR ? $prevR->fetch_assoc() : null;
            ps($conn, "DELETE FROM karte_attendance WHERE id=?", 'i', [$id]);
            if ($prevRow) logActivity($conn, $prevRow['student_id'], '出欠記録を削除', "[{$prevRow['att_type']}] {$prevRow['att_date']}");
            jout_backup($conn, ['success'=>true], $prevRow['student_id'] ?? '');

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
            jout_backup($conn, ['success'=>true,'id'=>$stmt->insert_id], $sid);

        case 'delete_interview':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) err('IDが不正です');
            $prevR = ps($conn, "SELECT student_id,interview_type,interview_date FROM karte_interviews WHERE id=?", 'i', [$id]);
            $prevRow = $prevR ? $prevR->fetch_assoc() : null;
            ps($conn, "DELETE FROM karte_interviews WHERE id=?", 'i', [$id]);
            if ($prevRow) logActivity($conn, $prevRow['student_id'], '面談記録を削除', "[{$prevRow['interview_type']}] {$prevRow['interview_date']}");
            jout_backup($conn, ['success'=>true], $prevRow['student_id'] ?? '');

        case 'save_gakno':
            $sid   = trim($_POST['student_id'] ?? '');
            $gakno = trim($_POST['gakno'] ?? '') ?: null;
            if ($gakno) {
                // 完全一致で見つからない場合、後ろ4桁での部分一致を試みる
                $exact = ps($conn, "SELECT gakno FROM gakuseki WHERE gakno=?", 's', [$gakno])->fetch_assoc();
                if (!$exact && strlen($gakno) > 4) {
                    $suffix = substr($gakno, -4);
                    $found  = ps($conn, "SELECT gakno FROM gakuseki WHERE RIGHT(gakno,4)=?", 's', [$suffix])->fetch_assoc();
                    if ($found) $gakno = $found['gakno'];
                }
                try {
                    $rs = ps($conn, "SELECT photo FROM students WHERE student_id=?", 's', [$sid]);
                    $rg = ps($conn, "SELECT photo FROM gakuseki WHERE gakno=?", 's', [$gakno]);
                    $studentPhoto = ($rs->fetch_assoc())['photo'] ?? null;
                    $gakPhoto     = ($rg->fetch_assoc())['photo'] ?? null;
                    if ($studentPhoto && !$gakPhoto) {
                        ps($conn, "UPDATE gakuseki SET photo=? WHERE gakno=?", 'ss', [$studentPhoto, $gakno]);
                        ps($conn, "UPDATE students SET photo=NULL WHERE student_id=?", 's', [$sid]);
                    }
                } catch (Exception $e) { /* photoカラムが存在しない場合は無視 */ }
            }
            $stmt  = $conn->prepare("UPDATE students SET gakno=? WHERE student_id=?");
            $stmt->bind_param('ss', $gakno, $sid);
            $stmt->execute();
            jout_backup($conn, ['success'=>true], $sid);

        case 'save_memos':
            $sid  = trim($_POST['student_id'] ?? '');
            $posi = trim($_POST['posi'] ?? '');
            $nega = trim($_POST['nega'] ?? '');
            $main = trim($_POST['main'] ?? '');
            $stmt = $conn->prepare("UPDATE students SET memo_posi=?,memo_nega=?,memo_main=? WHERE student_id=?");
            $stmt->bind_param('ssss', $posi,$nega,$main,$sid);
            $stmt->execute();
            logActivity($conn, $sid, 'メモ・所見を更新', ($posi ? "ポジ: $posi " : '') . ($nega ? "ネガ: $nega" : ''));
            jout_backup($conn, ['success'=>true], $sid);

        default: err('不明なアクション');
    }
}
