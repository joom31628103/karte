<?php
require_once '../config.php';
requireLogin();
sendSecurityHeaders();

$conn = getDB();

// カラムが存在しない場合に備えてSHOW COLUMNSで確認
$existing = [];
$res = $conn->query("SHOW COLUMNS FROM students");
while ($r = $res->fetch_assoc()) $existing[] = $r['Field'];

$contactCols = [
    'student_phone','parent1_name','parent1_furi','parent1_phone','parent1_phone_note',
    'parent1_work_name','parent1_work_phone','parent1_work_note',
    'parent2_name','parent2_furi','parent2_phone','parent2_phone_note',
    'parent2_work_name','parent2_work_phone','parent2_work_note',
];
$selectCols = ['student_id','name','class_name'];
foreach ($contactCols as $c) {
    $selectCols[] = in_array($c, $existing) ? $c : "'' AS $c";
}

$res = $conn->query("SELECT " . implode(',', $selectCols) . " FROM students ORDER BY class_name, student_id");
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$conn->close();

$filename = '保護者連絡先_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

$fp = fopen('php://output', 'w');
// BOM（Excelで文字化けしないよう）
fwrite($fp, "\xEF\xBB\xBF");

fputcsv($fp, [
    '学籍番号','氏名','クラス',
    '生徒電話番号',
    '保護者1氏名','保護者1ふりがな','保護者1電話番号','保護者1電話備考',
    '保護者1勤務先名','保護者1勤務先電話','保護者1勤務先備考',
    '保護者2氏名','保護者2ふりがな','保護者2電話番号','保護者2電話備考',
    '保護者2勤務先名','保護者2勤務先電話','保護者2勤務先備考',
]);
foreach ($rows as $r) {
    fputcsv($fp, [
        $r['student_id'], $r['name'], $r['class_name'],
        $r['student_phone'] ?? '',
        $r['parent1_name'] ?? '', $r['parent1_furi'] ?? '',
        $r['parent1_phone'] ?? '', $r['parent1_phone_note'] ?? '',
        $r['parent1_work_name'] ?? '', $r['parent1_work_phone'] ?? '', $r['parent1_work_note'] ?? '',
        $r['parent2_name'] ?? '', $r['parent2_furi'] ?? '',
        $r['parent2_phone'] ?? '', $r['parent2_phone_note'] ?? '',
        $r['parent2_work_name'] ?? '', $r['parent2_work_phone'] ?? '', $r['parent2_work_note'] ?? '',
    ]);
}
fclose($fp);
