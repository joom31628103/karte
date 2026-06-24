<?php
/**
 * karte バックアップライブラリ
 * 生徒1人分の全データをJSONファイルに書き出す
 * 失敗しても例外を投げず、呼び出し元の処理を止めない
 */

define('KARTE_BACKUP_DIR',     __DIR__ . '/../data/students/');
define('KARTE_BACKUP_HIST',    __DIR__ . '/../data/students/history/');
define('KARTE_BACKUP_VERSION', 2);
define('KARTE_BACKUP_KEEP',    20); // 世代数上限

/**
 * 生徒1人のJSONバックアップを更新する
 * @param mysqli $conn  DB接続（呼び出し元のものをそのまま使う）
 * @param string $sid   student_id
 * @return bool 成功/失敗
 */
function karteBackupStudent(mysqli $conn, string $sid): bool {
    if (!$sid) return false;
    try {
        $data = _karteCollectStudent($conn, $sid);
        if (!$data) return false;
        return _karteWriteJson($sid, $data);
    } catch (Throwable $e) {
        error_log("[karte backup] sid={$sid} " . $e->getMessage());
        return false;
    }
}

/**
 * 全生徒を一括バックアップ（管理画面・復元ユーティリティから呼ぶ）
 */
function karteBackupAll(mysqli $conn): array {
    $result = ['ok' => 0, 'fail' => 0, 'ids' => []];
    $res = $conn->query("SELECT student_id FROM students ORDER BY student_id");
    while ($row = $res->fetch_assoc()) {
        $sid = $row['student_id'];
        if (karteBackupStudent($conn, $sid)) {
            $result['ok']++;
        } else {
            $result['fail']++;
            $result['ids'][] = $sid;
        }
    }
    return $result;
}

/**
 * JSONファイルから1生徒のデータをDBに復元する
 * 既存データは上書き（REPLACE INTO）
 */
function karteRestoreStudent(mysqli $conn, string $sid): array {
    $file = KARTE_BACKUP_DIR . preg_replace('/[^a-zA-Z0-9_\-]/', '', $sid) . '.json';
    if (!file_exists($file)) return ['success' => false, 'error' => 'ファイルが存在しません'];

    $json = json_decode(file_get_contents($file), true);
    if (!$json) return ['success' => false, 'error' => 'JSONパースエラー'];

    try {
        // ── students ──
        if (!empty($json['student'])) {
            _upsertRow($conn, 'students', $json['student'], 'student_id');
        }
        // ── gakuseki ──
        if (!empty($json['gakuseki'])) {
            _upsertRow($conn, 'gakuseki', $json['gakuseki'], 'gakno');
        }
        // ── student_nendo ──
        if (!empty($json['nendo'])) {
            foreach ($json['nendo'] as $row) {
                _upsertRow($conn, 'student_nendo', $row, 'id');
            }
        }
        // ── karte_records ──
        if (!empty($json['records'])) {
            foreach ($json['records'] as $row) {
                _upsertRow($conn, 'karte_records', $row, 'id');
            }
        }
        // ── karte_attendance ──
        if (!empty($json['attendance'])) {
            foreach ($json['attendance'] as $row) {
                _upsertRow($conn, 'karte_attendance', $row, 'id');
            }
        }
        // ── karte_interviews ──
        if (!empty($json['interviews'])) {
            foreach ($json['interviews'] as $row) {
                _upsertRow($conn, 'karte_interviews', $row, 'id');
            }
        }
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ──────────────────────────────────────────────
// 内部関数
// ──────────────────────────────────────────────

function _karteCollectStudent(mysqli $conn, string $sid): ?array {
    // students
    $r = $conn->prepare("SELECT * FROM students WHERE student_id=?");
    $r->bind_param('s', $sid); $r->execute();
    $student = $r->get_result()->fetch_assoc(); $r->close();
    if (!$student) return null;

    // gakuseki（gaknoで紐付け）
    $gakno = $student['gakno'] ?? '';
    $gakuseki = null;
    $nendo = [];
    if ($gakno) {
        $r = $conn->prepare("SELECT * FROM gakuseki WHERE gakno=?");
        $r->bind_param('s', $gakno); $r->execute();
        $gakuseki = $r->get_result()->fetch_assoc(); $r->close();

        $r = $conn->prepare("SELECT * FROM student_nendo WHERE gakno=? ORDER BY nendo");
        $r->bind_param('s', $gakno); $r->execute();
        $res = $r->get_result();
        while ($row = $res->fetch_assoc()) $nendo[] = $row;
        $r->close();
    }

    // karte_records
    $records = [];
    $r = $conn->prepare("SELECT * FROM karte_records WHERE student_id=? ORDER BY record_date, id");
    $r->bind_param('s', $sid); $r->execute();
    $res = $r->get_result();
    while ($row = $res->fetch_assoc()) $records[] = $row;
    $r->close();

    // karte_attendance
    $attendance = [];
    $r = $conn->prepare("SELECT * FROM karte_attendance WHERE student_id=? ORDER BY att_date, id");
    $r->bind_param('s', $sid); $r->execute();
    $res = $r->get_result();
    while ($row = $res->fetch_assoc()) $attendance[] = $row;
    $r->close();

    // karte_interviews
    $interviews = [];
    $r = $conn->prepare("SELECT * FROM karte_interviews WHERE student_id=? ORDER BY interview_date, id");
    $r->bind_param('s', $sid); $r->execute();
    $res = $r->get_result();
    while ($row = $res->fetch_assoc()) $interviews[] = $row;
    $r->close();

    return [
        '_meta' => [
            'student_id'  => $sid,
            'exported_at' => date('Y-m-d\TH:i:s'),
            'version'     => KARTE_BACKUP_VERSION,
        ],
        'student'     => $student,
        'gakuseki'    => $gakuseki,
        'nendo'       => $nendo,
        'records'     => $records,
        'attendance'  => $attendance,
        'interviews'  => $interviews,
    ];
}

function _karteWriteJson(string $sid, array $data): bool {
    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sid);
    if (!$safeId) return false;

    if (!is_dir(KARTE_BACKUP_DIR)) mkdir(KARTE_BACKUP_DIR, 0755, true);
    if (!is_dir(KARTE_BACKUP_HIST)) mkdir(KARTE_BACKUP_HIST, 0755, true);

    $dest = KARTE_BACKUP_DIR . $safeId . '.json';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;

    // 既存ファイルを世代履歴へ退避
    if (file_exists($dest)) {
        $ts      = date('Ymd_His');
        $histDir = KARTE_BACKUP_HIST . $safeId . '/';
        if (!is_dir($histDir)) mkdir($histDir, 0755, true);
        copy($dest, $histDir . $ts . '.json');

        // 上限を超えた古い世代を削除
        $old = glob($histDir . '*.json');
        if ($old && count($old) > KARTE_BACKUP_KEEP) {
            sort($old);
            foreach (array_slice($old, 0, count($old) - KARTE_BACKUP_KEEP) as $f) {
                @unlink($f);
            }
        }
    }

    $tmp = $dest . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $dest);
}

/**
 * 生徒の世代一覧を返す（新しい順）
 * @return array [ ['ts'=>'20250624_143022', 'label'=>'2025-06-24 14:30:22', 'file'=>'/path/...json'], ... ]
 */
function karteListVersions(string $sid): array {
    $safeId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sid);
    $histDir = KARTE_BACKUP_HIST . $safeId . '/';
    $files   = glob($histDir . '*.json') ?: [];
    rsort($files);
    return array_map(function($f) {
        $ts = basename($f, '.json');
        $label = preg_replace('/^(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/', '$1-$2-$3 $4:$5:$6', $ts);
        return ['ts' => $ts, 'label' => $label, 'file' => $f, 'size' => round(filesize($f)/1024, 1)];
    }, $files);
}

/**
 * 指定世代のJSONからDBに復元する
 */
function karteRestoreVersion(mysqli $conn, string $sid, string $ts): array {
    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sid);
    $ts     = preg_replace('/[^0-9_]/', '', $ts);
    $file   = KARTE_BACKUP_HIST . $safeId . '/' . $ts . '.json';
    if (!file_exists($file)) return ['success' => false, 'error' => 'バージョンファイルが見つかりません'];
    $json = json_decode(file_get_contents($file), true);
    if (!$json) return ['success' => false, 'error' => 'JSONパースエラー'];
    try {
        if (!empty($json['student']))    _upsertRow($conn, 'students',          $json['student'], 'student_id');
        if (!empty($json['gakuseki']))   _upsertRow($conn, 'gakuseki',          $json['gakuseki'], 'gakno');
        foreach ($json['nendo']       ?? [] as $row) _upsertRow($conn, 'student_nendo',    $row, 'id');
        foreach ($json['records']     ?? [] as $row) _upsertRow($conn, 'karte_records',    $row, 'id');
        foreach ($json['attendance']  ?? [] as $row) _upsertRow($conn, 'karte_attendance', $row, 'id');
        foreach ($json['interviews']  ?? [] as $row) _upsertRow($conn, 'karte_interviews', $row, 'id');
        // 復元後にカレントバックアップも更新
        karteBackupStudent($conn, $sid);
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/** REPLACE INTO 的なUPSERT（主キーが合致すれば上書き） */
function _upsertRow(mysqli $conn, string $table, array $row, string $pk): void {
    if (empty($row)) return;
    $cols = array_keys($row);
    $vals = array_values($row);

    $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : 's', $vals));

    $sql  = "REPLACE INTO `{$table}` ({$colList}) VALUES ({$placeholders})";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException($conn->error);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
}
