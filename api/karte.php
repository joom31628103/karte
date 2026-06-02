<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
$conn   = getDB();
$action = $_REQUEST['action'] ?? '';

function jout($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function err($msg)  { http_response_code(400); jout(['success'=>false,'error'=>$msg]); }
function esc($conn, $v){ return $conn->real_escape_string($v ?? ''); }

/* ===== GET ===== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {

        case 'student_summary':
            $r = $conn->query("
                SELECT s.student_id, s.name, s.furigana, s.class_name, s.seat_number,
                    (SELECT COUNT(*) FROM karte_records kr WHERE kr.student_id=s.student_id) AS rec_count,
                    (SELECT COUNT(*) FROM karte_attendance ka WHERE ka.student_id=s.student_id) AS att_count,
                    (SELECT MAX(record_date) FROM karte_records kr2 WHERE kr2.student_id=s.student_id) AS last_record
                FROM students s
                ORDER BY s.class_name, s.seat_number, s.student_id
            ");
            $rows=[];
            while($row=$r->fetch_assoc()) $rows[]=$row;
            jout(['success'=>true,'rows'=>$rows]);

        case 'list_records':
            $sid = esc($conn,$_GET['student_id']??'');
            $r   = $conn->query("SELECT * FROM karte_records WHERE student_id='$sid' ORDER BY record_date DESC, id DESC");
            $rows=[];
            while($row=$r->fetch_assoc()) $rows[]=$row;
            jout(['success'=>true,'rows'=>$rows]);

        case 'list_attendance':
            $sid = esc($conn,$_GET['student_id']??'');
            $r   = $conn->query("SELECT * FROM karte_attendance WHERE student_id='$sid' ORDER BY att_date DESC, id DESC");
            $rows=[];
            while($row=$r->fetch_assoc()) $rows[]=$row;
            jout(['success'=>true,'rows'=>$rows]);

        case 'list_interviews':
            $sid = esc($conn,$_GET['student_id']??'');
            $r   = $conn->query("SELECT * FROM karte_interviews WHERE student_id='$sid' ORDER BY interview_date DESC, id DESC");
            $rows=[];
            while($row=$r->fetch_assoc()) $rows[]=$row;
            jout(['success'=>true,'rows'=>$rows]);

        default: err('不明なアクション');
    }
}

/* ===== POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token']??'')) err('トークンエラー');

    switch ($action) {

        case 'add_student':
            $sid   = esc($conn,$_POST['student_id']??'');
            $name  = esc($conn,$_POST['name']??'');
            $cls   = esc($conn,$_POST['class_name']??'');
            $furi  = esc($conn,$_POST['furigana']??'');
            if (!$sid || !$name) err('学籍番号と氏名は必須です');
            $conn->query("INSERT INTO students (student_id,name,class_name,furigana) VALUES ('$sid','$name','$cls','$furi')");
            if ($conn->errno === 1062) err('その学籍番号は既に登録されています');
            jout(['success'=>true,'id'=>$conn->insert_id]);

        case 'save_basic':
            $sid  = esc($conn,$_POST['student_id']??'');
            $cols = ['name','furigana','class_name','gender','phone','parent_name','address','notes'];
            $parts = array_map(fn($c)=>"$c='".esc($conn,$_POST[$c]??'')."'", $cols);
            $seat = $_POST['seat_number']??'';
            $parts[] = "seat_number=".($seat!==''?(int)$seat:'NULL');
            $bday = esc($conn,$_POST['birthday']??'');
            $parts[] = "birthday=".($bday?"'$bday'":'NULL');
            $conn->query("UPDATE students SET ".implode(',',$parts).",updated_at=NOW() WHERE student_id='$sid'");
            jout(['success'=>true]);

        case 'add_record':
            $sid    = esc($conn,$_POST['student_id']??'');
            $date   = esc($conn,$_POST['record_date']??date('Y-m-d'));
            $type   = esc($conn,$_POST['record_type']??'');
            $content= esc($conn,$_POST['content']??'');
            $teacher= esc($conn,$_POST['teacher']??'');
            $next   = esc($conn,$_POST['next_action']??'');
            $conn->query("INSERT INTO karte_records (student_id,record_date,record_type,content,teacher,next_action) VALUES ('$sid','$date','$type','$content','$teacher','$next')");
            jout(['success'=>true,'id'=>$conn->insert_id]);

        case 'update_record':
            $id     = (int)($_POST['id']??0);
            $date   = esc($conn,$_POST['record_date']??'');
            $type   = esc($conn,$_POST['record_type']??'');
            $content= esc($conn,$_POST['content']??'');
            $teacher= esc($conn,$_POST['teacher']??'');
            $next   = esc($conn,$_POST['next_action']??'');
            $conn->query("UPDATE karte_records SET record_date='$date',record_type='$type',content='$content',teacher='$teacher',next_action='$next' WHERE id=$id");
            jout(['success'=>true]);

        case 'delete_record':
            $id=(int)($_POST['id']??0);
            $conn->query("DELETE FROM karte_records WHERE id=$id");
            jout(['success'=>true]);

        case 'add_attendance':
            $sid    = esc($conn,$_POST['student_id']??'');
            $date   = esc($conn,$_POST['att_date']??date('Y-m-d'));
            $type   = esc($conn,$_POST['att_type']??'');
            $reason = esc($conn,$_POST['reason']??'');
            $contact= esc($conn,$_POST['parent_contacted']??'未');
            $notes  = esc($conn,$_POST['notes']??'');
            $conn->query("INSERT INTO karte_attendance (student_id,att_date,att_type,reason,parent_contacted,notes) VALUES ('$sid','$date','$type','$reason','$contact','$notes')");
            jout(['success'=>true,'id'=>$conn->insert_id]);

        case 'delete_attendance':
            $id=(int)($_POST['id']??0);
            $conn->query("DELETE FROM karte_attendance WHERE id=$id");
            jout(['success'=>true]);

        case 'add_interview':
            $sid    = esc($conn,$_POST['student_id']??'');
            $date   = esc($conn,$_POST['interview_date']??date('Y-m-d'));
            $type   = esc($conn,$_POST['interview_type']??'');
            $parti  = esc($conn,$_POST['participants']??'');
            $content= esc($conn,$_POST['content']??'');
            $next   = esc($conn,$_POST['next_action']??'');
            $conn->query("INSERT INTO karte_interviews (student_id,interview_date,interview_type,participants,content,next_action) VALUES ('$sid','$date','$type','$parti','$content','$next')");
            jout(['success'=>true,'id'=>$conn->insert_id]);

        case 'delete_interview':
            $id=(int)($_POST['id']??0);
            $conn->query("DELETE FROM karte_interviews WHERE id=$id");
            jout(['success'=>true]);

        default: err('不明なアクション');
    }
}
