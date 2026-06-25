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

        case 'get_one':
            $gakno = $_GET['gakno'] ?? '';
            if (!$gakno) err('学籍番号が空です');
            $r = ps($conn, "
                SELECT g.*, sn.gakunen, sn.class_no, sn.bango
                FROM gakuseki g
                LEFT JOIN student_nendo sn ON g.gakno=sn.gakno
                    AND sn.nendo=(SELECT MAX(nendo) FROM student_nendo sn2 WHERE sn2.gakno=g.gakno)
                WHERE g.gakno=?
            ", 's', [$gakno]);
            $row = $r ? $r->fetch_assoc() : null;
            if ($row) jout(['success'=>true,'data'=>$row]);
            else jout(['success'=>false,'data'=>null]);

        case 'csv_export':
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN shusshin_chugaku VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
            $result = $conn->query("
                SELECT
                    CONCAT(
                        COALESCE(sn.gakunen,'0'),
                        COALESCE(sn.class_no,'0'),
                        LPAD(COALESCE(sn.bango,0),2,'0')
                    ) AS ID_number,
                    sn.gakunen,
                    sn.class_no,
                    sn.bango,
                    g.name,
                    g.furigana,
                    g.hogosya,
                    g.hogokana,
                    g.seibetu,
                    g.birthday,
                    g.shusshin_chugaku,
                    g.yuubin,
                    g.jyusyo,
                    '' AS hogosya_yuubin,
                    '' AS hogosya_jyusyo,
                    g.tel1,
                    g.tel2,
                    '' AS hogosya_addr1,
                    '' AS hogosya_addr2,
                    g.nyunendo,
                    g.nyugaku,
                    g.sotsugyo,
                    g.gakuseki_status,
                    g.zokugara,
                    g.notes,
                    g.gakno
                FROM gakuseki g
                LEFT JOIN student_nendo sn ON g.gakno = sn.gakno
                    AND sn.nendo = (SELECT MAX(nendo) FROM student_nendo sn2 WHERE sn2.gakno = g.gakno)
                ORDER BY g.gakno
            ");
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="gakuseki_'.date('Ymd').'.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID_number','gakunen','class_no','bango','name','furigana','hogosya','hogokana','seibetu','birthday','shusshin_chugaku','yuubin','jyusyo','hogosya_yuubin','hogosya_jyusyo','tel1','tel2','hogosya_addr1','hogosya_addr2','nyunendo','nyugaku','sotsugyo','gakuseki_status','zokugara','notes','gakno']);
            while ($row = $result->fetch_assoc()) fputcsv($out, array_values($row));
            fclose($out);
            exit;

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
            $status        = trim($_POST['gakuseki_status']  ?? '');
            $shusshin      = trim($_POST['shusshin_chugaku'] ?? '');
            $hog_yuubin    = trim($_POST['hogosya_yuubin']   ?? '');
            $hog_jyusyo    = trim($_POST['hogosya_jyusyo']   ?? '');
            $hog_addr1     = trim($_POST['hogosya_addr1']    ?? '');
            $hog_addr2     = trim($_POST['hogosya_addr2']    ?? '');
            $notes         = trim($_POST['notes'] ?? '');

            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN shusshin_chugaku VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN hogosya_yuubin VARCHAR(10) DEFAULT ''"); }  catch(Exception $e) {}
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN hogosya_jyusyo TEXT"); }                   catch(Exception $e) {}
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN hogosya_addr1 VARCHAR(200) DEFAULT ''"); }  catch(Exception $e) {}
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN hogosya_addr2 VARCHAR(200) DEFAULT ''"); }  catch(Exception $e) {}

            $exists = ps($conn, "SELECT id FROM gakuseki WHERE gakno=?", 's', [$gakno])->fetch_assoc();
            if ($exists) {
                // UPDATE params(22): name furigana seibetu birthday yuubin jyusyo hogosya hogokana zokugara tel1 tel2[11s] nyunendo[i] nyugaku sotsugyo status shusshin hog_yuubin hog_jyusyo hog_addr1 hog_addr2 notes gakno[10s]
                $stmt = $conn->prepare("UPDATE gakuseki SET name=?,furigana=?,seibetu=?,birthday=?,yuubin=?,jyusyo=?,hogosya=?,hogokana=?,zokugara=?,tel1=?,tel2=?,nyunendo=?,nyugaku=?,sotsugyo=?,gakuseki_status=?,shusshin_chugaku=?,hogosya_yuubin=?,hogosya_jyusyo=?,hogosya_addr1=?,hogosya_addr2=?,notes=?,updated_at=NOW() WHERE gakno=?");
                $stmt->bind_param('sssssssssssissssssssss', $name,$furigana,$seibetu,$birthday,$yuubin,$jyusyo,$hogosya,$hogokana,$zokugara,$tel1,$tel2,$nyunendo,$nyugaku,$sotsugyo,$status,$shusshin,$hog_yuubin,$hog_jyusyo,$hog_addr1,$hog_addr2,$notes,$gakno);
            } else {
                // INSERT params(22): gakno name furigana seibetu birthday yuubin jyusyo hogosya hogokana zokugara tel1 tel2[12s] nyunendo[i] nyugaku sotsugyo status shusshin hog_yuubin hog_jyusyo hog_addr1 hog_addr2 notes[9s]
                $stmt = $conn->prepare("INSERT INTO gakuseki (gakno,name,furigana,seibetu,birthday,yuubin,jyusyo,hogosya,hogokana,zokugara,tel1,tel2,nyunendo,nyugaku,sotsugyo,gakuseki_status,shusshin_chugaku,hogosya_yuubin,hogosya_jyusyo,hogosya_addr1,hogosya_addr2,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssssssssssssisssssssss', $gakno,$name,$furigana,$seibetu,$birthday,$yuubin,$jyusyo,$hogosya,$hogokana,$zokugara,$tel1,$tel2,$nyunendo,$nyugaku,$sotsugyo,$status,$shusshin,$hog_yuubin,$hog_jyusyo,$hog_addr1,$hog_addr2,$notes);
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
            $gakunen  = $_POST['gakunen'] !== '' ? (int)preg_replace('/[^0-9]/', '', $_POST['gakunen']) : null;
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
            if (!$gakno) err('学籍番号が空です');
            ps($conn, "DELETE FROM student_nendo WHERE gakno=?", 's', [$gakno]);
            ps($conn, "DELETE FROM gakuseki WHERE gakno=?", 's', [$gakno]);
            jout(['success'=>true]);

        case 'delete_all':
            $conn->query("DELETE FROM student_nendo");
            $r = $conn->query("SELECT COUNT(*) AS cnt FROM gakuseki");
            $cnt = $r->fetch_assoc()['cnt'];
            $conn->query("DELETE FROM gakuseki");
            jout(['success'=>true,'deleted'=>$cnt]);

        case 'csv_import':
            if (empty($_FILES['csv']['tmp_name'])) err('ファイルが見つかりません');
            $file = $_FILES['csv']['tmp_name'];
            // BOM除去
            $raw = file_get_contents($file);
            if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
            // \r\n → \n を先に、次に残った単独 \r → \n の順で処理
            $raw   = str_replace("\r\n", "\n", $raw);
            $raw   = str_replace("\r",   "\n", $raw);
            $lines = explode("\n", $raw);

            // ヘッダー行
            $headers = str_getcsv(array_shift($lines));
            $headers = array_map('trim', $headers);


            $allowed = ['gakno','ID_number','name','furigana','seibetu','birthday','yuubin','jyusyo',
                        'hogosya','hogokana','zokugara','tel1','tel2','nyunendo','nyugaku',
                        'sotsugyo','gakuseki_status','shusshin_chugaku',
                        'hogosya_yuubin','hogosya_jyusyo','hogosya_addr1','hogosya_addr2',
                        'notes','gakunen','class_no','bango'];

            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN shusshin_chugaku VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN hogosya_yuubin VARCHAR(10) DEFAULT ''"); }  catch(Exception $e) {}
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN hogosya_jyusyo TEXT"); }                   catch(Exception $e) {}
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN hogosya_addr1 VARCHAR(200) DEFAULT ''"); }  catch(Exception $e) {}
            try { $conn->query("ALTER TABLE gakuseki ADD COLUMN hogosya_addr2 VARCHAR(200) DEFAULT ''"); }  catch(Exception $e) {}

            $inserted = 0; $updated = 0; $nendo_saved = 0; $errors = [];
            foreach ($lines as $i => $line) {
                $line = trim($line);
                if ($line === '') continue;
                $cols = str_getcsv($line);
                $row  = [];
                foreach ($headers as $j => $h) {
                    if (in_array($h, $allowed)) $row[$h] = trim($cols[$j] ?? '');
                }
                // gaknoが空の場合はID_numberで代用
                if (empty($row['gakno']) && !empty($row['ID_number'])) {
                    $row['gakno'] = $row['ID_number'];
                }
                if (empty($row['gakno']) || empty($row['name'])) {
                    $errors[] = ($i+2).'行目: 学籍番号または氏名が空';
                    continue;
                }
                $gakno      = $row['gakno'];
                $name       = $row['name'];
                $furigana   = $row['furigana']        ?? '';
                $seibetu    = $row['seibetu']         ?? '';
                $birthday   = ($row['birthday']  ?? '') ?: null;
                $yuubin     = $row['yuubin']          ?? '';
                $jyusyo     = $row['jyusyo']          ?? '';
                $hogosya    = $row['hogosya']         ?? '';
                $hogokana   = $row['hogokana']        ?? '';
                $zokugara   = $row['zokugara']        ?? '';
                $tel1       = $row['tel1']            ?? '';
                $tel2       = $row['tel2']            ?? '';
                $nyunendo   = ($row['nyunendo'] ?? '') !== '' ? (int)$row['nyunendo'] : null;
                $nyugaku    = ($row['nyugaku']   ?? '') ?: null;
                $sotsugyo   = ($row['sotsugyo']  ?? '') ?: null;
                $status     = $row['gakuseki_status']  ?? '';
                $shusshin   = $row['shusshin_chugaku'] ?? '';
                $hog_yuubin = $row['hogosya_yuubin']   ?? '';
                $hog_jyusyo = $row['hogosya_jyusyo']   ?? '';
                $hog_addr1  = $row['hogosya_addr1']    ?? '';
                $hog_addr2  = $row['hogosya_addr2']    ?? '';
                $notes      = $row['notes']            ?? '';
                $gakunen    = ($row['gakunen']  ?? '') !== '' ? (int)preg_replace('/[^0-9]/', '', $row['gakunen'])  : null;
                $class_no   = $row['class_no']         ?? '';
                $bango      = ($row['bango']    ?? '') !== '' ? (int)$row['bango']    : null;

                $exists = ps($conn, "SELECT id FROM gakuseki WHERE gakno=?", 's', [$gakno])->fetch_assoc();
                if ($exists) {
                    $stmt = $conn->prepare("UPDATE gakuseki SET name=?,furigana=?,seibetu=?,birthday=?,yuubin=?,jyusyo=?,hogosya=?,hogokana=?,zokugara=?,tel1=?,tel2=?,nyunendo=?,nyugaku=?,sotsugyo=?,gakuseki_status=?,shusshin_chugaku=?,hogosya_yuubin=?,hogosya_jyusyo=?,hogosya_addr1=?,hogosya_addr2=?,notes=?,updated_at=NOW() WHERE gakno=?");
                    $stmt->bind_param('sssssssssssissssssssss', $name,$furigana,$seibetu,$birthday,$yuubin,$jyusyo,$hogosya,$hogokana,$zokugara,$tel1,$tel2,$nyunendo,$nyugaku,$sotsugyo,$status,$shusshin,$hog_yuubin,$hog_jyusyo,$hog_addr1,$hog_addr2,$notes,$gakno);
                    $stmt->execute(); $stmt->close();
                    $updated++;
                } else {
                    $stmt = $conn->prepare("INSERT INTO gakuseki (gakno,name,furigana,seibetu,birthday,yuubin,jyusyo,hogosya,hogokana,zokugara,tel1,tel2,nyunendo,nyugaku,sotsugyo,gakuseki_status,shusshin_chugaku,hogosya_yuubin,hogosya_jyusyo,hogosya_addr1,hogosya_addr2,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->bind_param('ssssssssssssisssssssss', $gakno,$name,$furigana,$seibetu,$birthday,$yuubin,$jyusyo,$hogosya,$hogokana,$zokugara,$tel1,$tel2,$nyunendo,$nyugaku,$sotsugyo,$status,$shusshin,$hog_yuubin,$hog_jyusyo,$hog_addr1,$hog_addr2,$notes);
                    $stmt->execute(); $stmt->close();
                    $inserted++;
                }

                // gakunen/class_no/bango がある場合は student_nendo へ保存
                // nyunendoが空の場合は現在の年度をフォールバック
                if ($gakunen !== null) {
                    $base_nendo  = $nyunendo ?? (int)date('Y');
                    $nendo_year  = $base_nendo + $gakunen - 1;
                    $st2 = $conn->prepare("INSERT INTO student_nendo (gakno,nendo,gakunen,class_no,bango) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE gakunen=VALUES(gakunen),class_no=VALUES(class_no),bango=VALUES(bango)");
                    $st2->bind_param('siisi', $gakno,$nendo_year,$gakunen,$class_no,$bango);
                    $st2->execute(); $st2->close();
                    $nendo_saved++;
                }
            }
            jout(['success'=>true,'inserted'=>$inserted,'updated'=>$updated,'nendo_saved'=>$nendo_saved,'errors'=>$errors]);

        default: err('不明なアクション');
    }
}
