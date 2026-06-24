<?php
require_once '../config.php';
require_once '../lib/excel.php';
requireLogin();
sendSecurityHeaders();

$conn = getDB();

/* ── 生徒一覧シート ── */
$students = [];
$res = $conn->query("
    SELECT student_id, name, furigana, class_name, seat_number,
           gender, birthday, phone, parent_name, address, notes
    FROM students
    ORDER BY class_name, seat_number, student_id
");
while ($r = $res->fetch_assoc()) $students[] = $r;

/* ── 指導記録シート ── */
$records = [];
$res = $conn->query("
    SELECT r.*, s.name, s.class_name
    FROM karte_records r
    JOIN students s ON s.student_id = r.student_id
    ORDER BY r.record_date DESC, r.id DESC
");
while ($r = $res->fetch_assoc()) $records[] = $r;

/* ── 出欠記録シート ── */
$attendance = [];
$res = $conn->query("
    SELECT a.*, s.name, s.class_name
    FROM karte_attendance a
    JOIN students s ON s.student_id = a.student_id
    ORDER BY a.att_date DESC, a.id DESC
");
while ($r = $res->fetch_assoc()) $attendance[] = $r;

/* ── 面談記録シート ── */
$interviews = [];
$res = $conn->query("
    SELECT i.*, s.name, s.class_name
    FROM karte_interviews i
    JOIN students s ON s.student_id = i.student_id
    ORDER BY i.interview_date DESC, i.id DESC
");
while ($r = $res->fetch_assoc()) $interviews[] = $r;

$conn->close();

/* ── Excelビルド ── */
$wb = new KarteXlsx();

// Sheet1: 生徒一覧
$rows = [['学籍番号','氏名','フリガナ','クラス','出席番号','性別','生年月日','電話番号','保護者名','住所','備考']];
foreach ($students as $s) {
    $rows[] = [
        $s['student_id'], $s['name'], $s['furigana'], $s['class_name'],
        $s['seat_number'], $s['gender'], $s['birthday'], $s['phone'],
        $s['parent_name'], $s['address'], $s['notes'],
    ];
}
$wb->addSheet('生徒一覧', $rows);

// Sheet2: 指導記録
$rows = [['学籍番号','氏名','クラス','日付','種別','内容','担当者','次のアクション']];
foreach ($records as $r) {
    $rows[] = [
        $r['student_id'], $r['name'], $r['class_name'],
        $r['record_date'], $r['record_type'], $r['content'],
        $r['teacher'], $r['next_action'],
    ];
}
$wb->addSheet('指導記録', $rows);

// Sheet3: 出欠記録
$rows = [['学籍番号','氏名','クラス','日付','種別','理由','保護者連絡','備考']];
foreach ($attendance as $a) {
    $rows[] = [
        $a['student_id'], $a['name'], $a['class_name'],
        $a['att_date'], $a['att_type'], $a['reason'],
        $a['parent_contacted'], $a['notes'],
    ];
}
$wb->addSheet('出欠記録', $rows);

// Sheet4: 面談記録
$rows = [['学籍番号','氏名','クラス','日付','種別','参加者','内容','次のアクション']];
foreach ($interviews as $i) {
    $rows[] = [
        $i['student_id'], $i['name'], $i['class_name'],
        $i['interview_date'], $i['interview_type'], $i['participants'],
        $i['content'], $i['next_action'],
    ];
}
$wb->addSheet('面談記録', $rows);

$filename = 'カルテ_' . date('Ymd_His') . '.xlsx';
$wb->download($filename);
