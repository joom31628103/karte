<?php
require_once '../config.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

$conn   = getDB();
$action = $_REQUEST['action'] ?? '';

function jout(array $data): never { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function err(string $msg): never  { http_response_code(400); jout(['success'=>false,'error'=>$msg]); }

function ps(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result|bool {
    $stmt = $conn->prepare($sql);
    if (!$stmt) err('クエリエラー');
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result() ?: true;
    $stmt->close();
    return $result;
}

/* ===== GET ===== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {

        case 'list':
            $nendo = (int)($_GET['nendo'] ?? 0);
            if ($nendo) {
                $result = ps($conn, "
                    SELECT g.*, sn.nendo, sn.gakunen, sn.class_no, sn.bango, sn.sinkyu, sn.teacher_id,
                           t.display_name AS tanninmei
                    FROM gakuseki g
                    LEFT JOIN student_nendo sn ON g.gakno=sn.gakno AND sn.nendo=?
                    LEFT JOIN teachers t ON sn.teacher_id=t.id
                    ORDER BY sn.gakunen, sn.class_no, sn.bango, g.furigana
                ", 'i', [$nendo]);
            } else {
                $result = ps($conn, "
                    SELECT g.*, sn.nendo, sn.gakunen, sn.class_no, sn.bango, sn.sinkyu, sn.teacher_id,
                           t.display_name AS tanninmei
                    FROM gakuseki g
                    LEFT JOIN student_nendo sn ON g.gakno=sn.gakno AND sn.nendo=(
                        SELECT MAX(nendo) FROM student_nendo sn2 WHERE sn2.gakno=g.gakno
                    )
                    LEFT JOIN teachers t ON sn.teacher_id=t.id
                    ORDER BY g.furigana
                ");
            }
            $rows = [];
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            jout(['success'=>true,'rows'=>$rows]);

        case 'get':
            $gakno = trim($_GET['gakno'] ?? '');
            $r     = ps($conn, "SELECT * FROM gakuseki WHERE gakno=?", 's', [$gakno]);
            $g     = $r->fetch_assoc();
            if (!$g) err('学籍が見つかりません');
            $r2 = ps($conn, "SELECT sn.*, t.display_name AS tanninmei FROM student_nendo sn LEFT JOIN teachers t ON sn.teacher_id=t.id WHERE sn.gakno=? ORDER BY sn.nendo", 's', [$gakno]);
            $nendo_list = [];
            while ($row = $r2->fetch_assoc()) $nendo_list[] = $row;
            jout(['success'=>true,'gakuseki'=>$g,'nendo_list'=>$nendo_list]);

        case 'nendo_list':
            $r    = ps($conn, "SELECT DISTINCT nendo FROM student_nendo ORDER BY nendo DESC");
            $rows = [];
            while ($row = $r->fetch_assoc()) $rows[] = $row['nendo'];
            jout(['success'=>true,'nendos'=>$rows]);

        case 'teachers':
            $r    = ps($conn, "SELECT id, display_name FROM teachers ORDER BY id");
            $rows = [];
            while ($row = $r->fetch_assoc()) $rows[] = $row;
            jout(['success'=>true,'teachers'=>$rows]);

        default: err('不明なアクション');
    }
}

/* ===== POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) err('トークンエラー');

    switch ($action) {

        case 'save_gakuseki':
            $gakno    = trim($_POST['gakno'] ?? '');
            $name     = trim($_POST['name'] ?? '');
            if (!$gakno) err('学籍番号は必須です');
            if (!$name)  err('氏名は必須です');
            $furigana = trim($_POST['furigana'] ?? '');
            $seibetu  = trim($_POST['seibetu'] ?? '');
            $birthday = trim($_POST['birthday'] ?? '') ?: null;
            $yuubin   = trim($_POST['yuubin'] ?? '');
            $jyusyo   = trim($_POST['jyusyo'] ?? '');
            $hogosya  = trim($_POST['hogosya'] ?? '');
            $hogokana = trim($_POST['hogokana'] ?? '');
            $zokugara = trim($_POST['zokugara'] ?? '');
            $tel1     = trim($_POST['tel1'] ?? '');
            $tel2     = trim($_POST['tel2'] ?? '');
            $nyunendo = (int)($_POST['nyunendo'] ?? 0) ?: null;
            $nyugaku  = trim($_POST['nyugaku'] ?? '') ?: null;
            $sotsugyo = trim($_POST['sotsugyo'] ?? '') ?: null;
            $status   = trim($_POST['gakuseki_status'] ?? '');
            $notes    = trim($_POST['notes'] ?? '');

            $exists = ps($conn, "SELECT id FROM gakuseki WHERE gakno=?", 's', [$gakno])->fetch_assoc();
            if ($exists) {
                $stmt = $conn->prepare("UPDATE gakuseki SET name=?,furigana=?,seibetu=?,birthday=?,yuubin=?,jyusyo=?,hogosya=?,hogokana=?,zokugara=?,tel1=?,tel2=?,nyunendo=?,nyugaku=?,sotsugyo=?,gakuseki_status=?,notes=?,updated_at=NOW() WHERE gakno=?");
                $stmt->bind_param('sssssssssssssssss', $name,$furigana,$seibetu,$birthday,$yuubin,$jyusyo,$hogosya,$hogokana,$zokugara,$tel1,$tel2,$nyunendo,$nyugaku,$sotsugyo,$status,$notes,$gakno);
            } else {
                $stmt = $conn->prepare("INSERT INTO gakuseki (gakno,name,furigana,seibetu,birthday,yuubin,jyusyo,hogosya,hogokana,zokugara,tel1,tel2,nyunendo,nyugaku,sotsugyo,gakuseki_status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('sssssssssssssssss', $gakno,$name,$furigana,$seibetu,$birthday,$yuubin,$jyusyo,$hogosya,$hogokana,$zokugara,$tel1,$tel2,$nyunendo,$nyugaku,$sotsugyo,$status,$notes);
            }
            if (!$stmt->execute()) {
                if ($conn->errno === 1062) err('その学籍番号は既に登録されています');
                err('保存エラー: '.$conn->error);
            }
            $stmt->close();
            jout(['success'=>true]);

        case 'save_nendo':
            $gakno    = trim($_POST['gakno'] ?? '');
            $nendo    = (int)($_POST['nendo'] ?? 0);
            if (!$gakno || !$nendo) err('学籍番号と年度は必須です');
            $gakunen  = $_POST['gakunen'] !== '' ? (int)$_POST['gakunen'] : null;
            $class_no = trim($_POST['class_no'] ?? '');
            $bango    = $_POST['bango'] !== '' ? (int)$_POST['bango'] : null;
            $teacher_id = $_POST['teacher_id'] !== '' ? (int)$_POST['teacher_id'] : null;
            $sinkyu   = trim($_POST['sinkyu'] ?? '');
            $stmt = $conn->prepare("INSERT INTO student_nendo (gakno,nendo,gakunen,class_no,bango,teacher_id,sinkyu) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE gakunen=VALUES(gakunen),class_no=VALUES(class_no),bango=VALUES(bango),teacher_id=VALUES(teacher_id),sinkyu=VALUES(sinkyu)");
            $stmt->bind_param('siiiiss', $gakno,$nendo,$gakunen,$class_no,$bango,$teacher_id,$sinkyu);
            $stmt->execute();
            $stmt->close();
            jout(['success'=>true]);

        case 'delete_nendo':
            $gakno = trim($_POST['gakno'] ?? '');
            $nendo = (int)($_POST['nendo'] ?? 0);
            ps($conn, "DELETE FROM student_nendo WHERE gakno=? AND nendo=?", 'si', [$gakno, $nendo]);
            jout(['success'=>true]);

        case 'delete_gakuseki':
            $gakno = trim($_POST['gakno'] ?? '');
            ps($conn, "DELETE FROM student_nendo WHERE gakno=?", 's', [$gakno]);
            ps($conn, "DELETE FROM gakuseki WHERE gakno=?", 's', [$gakno]);
            jout(['success'=>true]);

        default: err('不明なアクション');
    }
}
