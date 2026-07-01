<?php
/**
 * karte DB同期API
 * action=export  → 全データをJSONで返す
 * action=import  → POSTされたJSONでDBを上書き
 * action=status  → 各テーブルの件数を返す
 */
require_once '../config.php';

// CORS（localhost ↔ サーバー間の同期を許可）
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://localhost', 'http://127.0.0.1', 'http://localhost:80'];
if (in_array($origin, $allowed) || preg_match('#^https?://localhost(:\d+)?$#', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// トークン認証（ログイン不要・専用トークンで保護）
$provided = $_REQUEST['token'] ?? '';
if (!defined('SYNC_TOKEN') || !hash_equals(SYNC_TOKEN, $provided)) {
    http_response_code(403);
    die(json_encode(['success'=>false,'error'=>'認証エラー：トークンが違います']));
}

header('Content-Type: application/json; charset=utf-8');
$conn   = getDB();
$action = $_REQUEST['action'] ?? '';

// 同期対象テーブル（ログ・セキュリティ系は除外）
$SYNC_TABLES = [
    'students',
    'karte_records',
    'karte_attendance',
    'karte_interviews',
    'gakuseki',
    'student_nendo',
    'teachers',
];

// ── エクスポート ──────────────────────────────────────────
if ($action === 'export') {
    $data = [
        'version'     => 2,
        'exported_at' => date('c'),
        'env'         => ENV_NAME,
        'tables'      => [],
    ];
    foreach ($SYNC_TABLES as $tbl) {
        $res = $conn->query("SELECT * FROM `$tbl`");
        if (!$res) { $data['tables'][$tbl] = []; continue; }
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $data['tables'][$tbl] = $rows;
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── インポート ────────────────────────────────────────────
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!$data || !isset($data['tables'])) {
        http_response_code(400);
        die(json_encode(['success'=>false,'error'=>'不正なデータ形式']));
    }

    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $conn->begin_transaction();
    $imported = [];
    try {
        foreach ($SYNC_TABLES as $tbl) {
            if (!array_key_exists($tbl, $data['tables'])) continue;
            $rows = $data['tables'][$tbl];
            $conn->query("DELETE FROM `$tbl`");
            $imported[$tbl] = 0;
            if (empty($rows)) continue;

            // カラムが存在しない場合は自動追加（スキーマ差異を吸収）
            $existCols = [];
            $colRes = $conn->query("SHOW COLUMNS FROM `$tbl`");
            while ($c = $colRes->fetch_assoc()) $existCols[] = $c['Field'];

            foreach ($rows as $row) {
                // 存在しないカラムをスキップ
                $filteredRow = array_filter($row, fn($k) => in_array($k, $existCols), ARRAY_FILTER_USE_KEY);
                if (empty($filteredRow)) continue;
                $cols = array_keys($filteredRow);
                $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
                $vals    = implode(',', array_map(fn($v) =>
                    $v === null ? 'NULL' : "'" . $conn->real_escape_string((string)$v) . "'",
                    array_values($filteredRow)
                ));
                $conn->query("INSERT INTO `$tbl` ($colList) VALUES ($vals)");
                $imported[$tbl]++;
            }
        }
        $conn->commit();
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        echo json_encode([
            'success'     => true,
            'message'     => 'インポート完了',
            'imported'    => $imported,
            'source_env'  => $data['env'] ?? '不明',
            'exported_at' => $data['exported_at'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// ── ステータス確認 ────────────────────────────────────────
if ($action === 'status') {
    $counts = [];
    foreach ($SYNC_TABLES as $tbl) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM `$tbl`");
        $counts[$tbl] = $r ? (int)$r->fetch_assoc()['c'] : 0;
    }
    $lastStudent = $conn->query("SELECT MAX(updated_at) AS t FROM students")->fetch_assoc()['t'] ?? null;
    echo json_encode([
        'success'          => true,
        'env'              => ENV_NAME,
        'counts'           => $counts,
        'last_updated'     => $lastStudent,
        'server_time'      => date('c'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['success'=>false,'error'=>'不明なアクション']);
