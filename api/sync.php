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
                $conn->query("INSERT IGNORE INTO `$tbl` ($colList) VALUES ($vals)");
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

// ── マージ（新しいほう優先） ──────────────────────────────
if ($action === 'merge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!$data || !isset($data['local']['tables']) || !isset($data['remote']['tables'])) {
        http_response_code(400);
        die(json_encode(['success'=>false,'error'=>'不正なデータ形式']));
    }

    // テーブルごとのマージ設定
    // key: 同一レコードを識別するキー列（配列可）
    // ts:  新旧比較に使うタイムスタンプ列
    // append: true = 両方の和集合を取る（追記型テーブル）
    $MERGE_CFG = [
        'students'         => ['key'=>['student_id'],      'ts'=>'updated_at'],
        'karte_records'    => ['key'=>['id'],               'ts'=>'created_at', 'append'=>true],
        'karte_attendance' => ['key'=>['id'],               'ts'=>'created_at', 'append'=>true],
        'karte_interviews' => ['key'=>['id'],               'ts'=>'created_at', 'append'=>true],
        'gakuseki'         => ['key'=>['gakno'],            'ts'=>'updated_at'],
        'student_nendo'    => ['key'=>['gakno','nendo'],    'ts'=>'created_at', 'append'=>true],
        'teachers'         => ['key'=>['username'],         'ts'=>'created_at'],
    ];

    $merged  = [];
    $stats   = [];

    foreach ($SYNC_TABLES as $tbl) {
        $cfg     = $MERGE_CFG[$tbl] ?? ['key'=>['id'],'ts'=>'created_at'];
        $keys    = $cfg['key'];
        $tsCol   = $cfg['ts'];
        $append  = $cfg['append'] ?? false;

        $localRows  = $data['local']['tables'][$tbl]  ?? [];
        $remoteRows = $data['remote']['tables'][$tbl] ?? [];

        // 行をキーでインデックス化
        $makeIdx = function(array $rows) use ($keys): array {
            $idx = [];
            foreach ($rows as $row) {
                $k = implode('|', array_map(fn($f) => $row[$f] ?? '', $keys));
                $idx[$k] = $row;
            }
            return $idx;
        };

        $localIdx  = $makeIdx($localRows);
        $remoteIdx = $makeIdx($remoteRows);

        $result       = [];
        $localWins    = 0;
        $remoteWins   = 0;
        $localOnly    = 0;
        $remoteOnly   = 0;

        if ($append) {
            // 追記型：両方の和集合。同じキーが衝突したら新しいほう
            $allKeys = array_unique(array_merge(array_keys($localIdx), array_keys($remoteIdx)));
            foreach ($allKeys as $k) {
                $hasL = isset($localIdx[$k]);
                $hasR = isset($remoteIdx[$k]);
                if ($hasL && $hasR) {
                    $lt = $localIdx[$k][$tsCol]  ?? '0';
                    $rt = $remoteIdx[$k][$tsCol] ?? '0';
                    $result[] = ($lt >= $rt) ? $localIdx[$k] : $remoteIdx[$k];
                    if ($lt >= $rt) $localWins++; else $remoteWins++;
                } elseif ($hasL) {
                    $result[] = $localIdx[$k];
                    $localOnly++;
                } else {
                    $result[] = $remoteIdx[$k];
                    $remoteOnly++;
                }
            }
        } else {
            // 更新型：同じキーは新しいほう優先
            $allKeys = array_unique(array_merge(array_keys($localIdx), array_keys($remoteIdx)));
            foreach ($allKeys as $k) {
                $hasL = isset($localIdx[$k]);
                $hasR = isset($remoteIdx[$k]);
                if ($hasL && $hasR) {
                    $lt = $localIdx[$k][$tsCol]  ?? '0';
                    $rt = $remoteIdx[$k][$tsCol] ?? '0';
                    $result[] = ($lt >= $rt) ? $localIdx[$k] : $remoteIdx[$k];
                    if ($lt >= $rt) $localWins++; else $remoteWins++;
                } elseif ($hasL) {
                    $result[] = $localIdx[$k];
                    $localOnly++;
                } else {
                    $result[] = $remoteIdx[$k];
                    $remoteOnly++;
                }
            }
        }

        $merged[$tbl] = $result;
        $stats[$tbl]  = [
            'total'       => count($result),
            'local_wins'  => $localWins,
            'remote_wins' => $remoteWins,
            'local_only'  => $localOnly,
            'remote_only' => $remoteOnly,
        ];
    }

    echo json_encode([
        'success' => true,
        'merged'  => ['tables' => $merged],
        'stats'   => $stats,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── スキーマ取得 ──────────────────────────────────────────
if ($action === 'schema') {
    $schema = [];
    foreach ($SYNC_TABLES as $tbl) {
        $r = $conn->query("SHOW COLUMNS FROM `$tbl`");
        if (!$r) { $schema[$tbl] = []; continue; }
        $cols = [];
        while ($c = $r->fetch_assoc()) {
            $cols[$c['Field']] = [
                'type'    => $c['Type'],
                'null'    => $c['Null'],
                'default' => $c['Default'],
                'extra'   => $c['Extra'],
            ];
        }
        $schema[$tbl] = $cols;
    }
    echo json_encode(['success'=>true,'env'=>ENV_NAME,'schema'=>$schema], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── スキーマ適用（ADD COLUMN / MODIFY COLUMN のみ） ────────
if ($action === 'schema_apply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['alters'])) {
        http_response_code(400);
        die(json_encode(['success'=>false,'error'=>'不正なデータ形式']));
    }
    $results = [];
    foreach ($data['alters'] as $sql) {
        if (!preg_match('/^ALTER\s+TABLE\s+`?\w+`?\s+(ADD\s+COLUMN|MODIFY\s+COLUMN)/i', $sql)) {
            $results[] = ['sql'=>$sql,'ok'=>false,'error'=>'ADD COLUMN / MODIFY COLUMN のみ許可されています'];
            continue;
        }
        $ok = $conn->query($sql);
        $results[] = ['sql'=>$sql,'ok'=>(bool)$ok,'error'=>$ok ? null : $conn->error];
    }
    echo json_encode(['success'=>true,'results'=>$results], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Git同期 ──────────────────────────────────────────
// git_status / git_pull / github_data_status はサーバー自身（さくら）からの実行＝デプロイ用途も許可する。
// git_push / git_merge は誤って本番からpushしないよう、ローカルPC（loopback）からのみ許可。
if (in_array($action, ['git_status','git_push','git_pull','git_merge','github_data_status','git_file_diff','schema_github'])) {
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLoopback = in_array($ip, ['127.0.0.1','::1']);
    if (in_array($action, ['git_push','git_merge','git_file_diff','schema_github']) && !$isLoopback) {
        http_response_code(403);
        die(json_encode(['success'=>false,'error'=>'この操作はローカルPCからのみ実行できます']));
    }
    if (!$isLoopback && ENV_NAME !== 'sakura') {
        http_response_code(403);
        die(json_encode(['success'=>false,'error'=>'Git操作はローカルPC、またはサーバー自身からのみ実行できます']));
    }
    $root    = realpath(dirname(__DIR__));
    $safeDir = str_replace('\\', '/', $root);
    shell_exec('git config --global --add safe.directory ' . escapeshellarg($safeDir) . ' 2>&1');
    $g = 'git -C ' . escapeshellarg($root);

    // HOME: Linux(さくら)では実際の$HOMEを、Windows(ローカル)ではUSERPROFILEを使う。
    // これがないとgitが ~/.git-credentials や ~/.gitconfig を見つけられず認証に失敗する。
    $gitEnv = array_merge(getenv() ?: [], [
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_ASKPASS'         => 'echo',
        'HOME'                => getenv('HOME') ?: (getenv('USERPROFILE') ?: 'C:/Users/hi'),
    ]);
    $gitRun = function(string $cmd, int $timeout = 30) use ($gitEnv): string {
        $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = @proc_open($cmd, $desc, $pipes, null, $gitEnv);
        if (!is_resource($proc)) return "[エラー: プロセスを起動できませんでした]";
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($proc);
        return trim(($out ?: '') . ($err ? "\n" . $err : ''));
    };

    if ($action === 'git_status') {
        $isRepo = $gitRun("$g rev-parse --is-inside-work-tree", 5) === 'true';
        if (!$isRepo) {
            echo json_encode(['success'=>true,'initialized'=>false,'env'=>ENV_NAME], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $gitRun("$g fetch --quiet origin", 15); // GitHubの最新状態を取得（失敗しても無視）
        $branch = $gitRun("$g branch --show-current", 5);
        $status = $gitRun("$g status --porcelain", 5);
        $log5   = $gitRun("$g log --oneline -5", 5);
        $remote = $gitRun("$g remote get-url origin", 5);
        $hash   = $gitRun("$g rev-parse --short HEAD", 5);
        $ahead = $behind = 0;
        $originHash = $originLog = '';
        if ($branch) {
            $ab = $gitRun("$g rev-list --left-right --count HEAD...origin/$branch", 5);
            if (preg_match('/^(\d+)\s+(\d+)$/', $ab, $m)) { $ahead = (int)$m[1]; $behind = (int)$m[2]; }
            $originHash = $gitRun("$g rev-parse --short origin/$branch", 5);
            $originLog  = $gitRun("$g log origin/$branch -1 --oneline", 5);
        }
        echo json_encode([
            'success'     => true,
            'initialized' => true,
            'env'         => ENV_NAME,
            'branch'      => $branch,
            'hash'        => $hash,
            'ahead'       => $ahead,
            'behind'      => $behind,
            'originHash'  => $originHash,
            'originLog'   => $originLog,
            'changes'     => $status ? array_values(array_filter(explode("\n", $status))) : [],
            'log'         => $log5   ? array_values(array_filter(explode("\n", $log5)))   : [],
            'remote'      => $remote,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // GitHub（origin）に実際にpush済みのdata/students/*.jsonを読み、
    // ローカル/サーバーと同じ形式（生徒・学籍台帳・年度情報・面談記録・出欠・面談）の件数を集計する。
    // ＝「GitHub上のDB状態」を、作業ツリーの未コミット変更に左右されずに見せるための機能。
    if ($action === 'github_data_status') {
        $isRepo = $gitRun("$g rev-parse --is-inside-work-tree", 5) === 'true';
        if (!$isRepo) {
            echo json_encode(['success'=>true,'initialized'=>false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $gitRun("$g fetch --quiet origin", 15);
        $branch = $gitRun("$g branch --show-current", 5) ?: 'master';

        $lsOut = $gitRun("$g ls-tree -r --name-only origin/" . escapeshellarg($branch) . " -- data/students", 10);
        $files = $lsOut ? array_values(array_filter(explode("\n", $lsOut))) : [];
        $files = array_values(array_filter($files, function($f) {
            return strpos($f, '/history/') === false && substr($f, -5) === '.json';
        }));

        // ローカル/サーバーの表と行の並びを揃えるため、$SYNC_TABLESと同じ順序にする（teachersは対象外）。
        $counts = ['students'=>0,'karte_records'=>0,'karte_attendance'=>0,'karte_interviews'=>0,'gakuseki'=>0,'student_nendo'=>0];
        $lastExport = null;
        foreach ($files as $f) {
            $content = $gitRun("$g show " . escapeshellarg("origin/$branch:$f"), 5);
            $data = json_decode($content, true);
            if (!$data) continue;
            if (!empty($data['student']))   $counts['students']++;
            if (!empty($data['gakuseki']))  $counts['gakuseki']++;
            if (!empty($data['nendo']))     $counts['student_nendo']    += count($data['nendo']);
            if (!empty($data['records']))   $counts['karte_records']    += count($data['records']);
            if (!empty($data['attendance']))$counts['karte_attendance'] += count($data['attendance']);
            if (!empty($data['interviews']))$counts['karte_interviews'] += count($data['interviews']);
            $exp = $data['_meta']['exported_at'] ?? null;
            if ($exp && (!$lastExport || $exp > $lastExport)) $lastExport = $exp;
        }
        echo json_encode([
            'success'      => true,
            'initialized'  => true,
            'branch'       => $branch,
            'file_count'   => count($files),
            'counts'       => $counts,
            'last_export'  => $lastExport,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'git_push' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true);
        $msg    = trim($body['message'] ?? '') ?: ('Update ' . date('Y-m-d H:i'));
        $gc     = $g . ' -c user.email=joom31628103@gmail.com -c user.name=hide';
        $add    = $gitRun("$g add -A");
        $commit = $gitRun("$gc commit -m " . escapeshellarg($msg));
        $push   = $gitRun("$g push", 30);
        $lower  = strtolower($push);
        $ok     = strpos($lower,'タイムアウト')===false && strpos($lower,'error')===false && strpos($lower,'fatal')===false;
        echo json_encode([
            'success' => $ok,
            'steps'   => [
                ['label'=>'git add -A',  'out'=>$add],
                ['label'=>'git commit',  'out'=>$commit],
                ['label'=>'git push',    'out'=>$push],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'git_pull' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pull  = $gitRun("$g pull", 30);
        $lower = strtolower($pull);
        $ok    = strpos($lower,'タイムアウト')===false && strpos($lower,'error')===false && strpos($lower,'fatal')===false;
        echo json_encode(['success'=>$ok,'output'=>$pull], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 職場PC・家PCの双方からGitHubへpushする運用のため、
    // 「ローカルの変更をコミット → pull（フェッチ＋マージ） → push」を1操作でまとめて行う。
    // 文字レベルでの衝突（同じ行の同時編集）はgitでも自動解決できないため、その場合はpushせず停止する。
    if ($action === 'git_merge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true);
        $msg    = trim($body['message'] ?? '') ?: ('Update ' . date('Y-m-d H:i'));
        $gc     = $g . ' -c user.email=joom31628103@gmail.com -c user.name=hide';

        $add    = $gitRun("$g add -A");
        $commit = $gitRun("$gc commit -m " . escapeshellarg($msg));
        $pull   = $gitRun("$g pull --no-edit", 30);
        $conflict = (bool)preg_match('/CONFLICT|Automatic merge failed/i', $pull);

        $steps = [
            ['label'=>'git add -A',              'out'=>$add],
            ['label'=>'git commit',              'out'=>$commit],
            ['label'=>'git pull（フェッチ＋マージ）', 'out'=>$pull],
        ];

        if ($conflict) {
            echo json_encode([
                'success'  => false,
                'conflict' => true,
                'steps'    => $steps,
                'error'    => 'コンフリクトが発生しました。手動での解決が必要です（pushは行っていません）。',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $push  = $gitRun("$g push", 30);
        $lower = strtolower($push);
        $ok    = strpos($lower,'タイムアウト')===false && strpos($lower,'error')===false && strpos($lower,'fatal')===false;
        $steps[] = ['label'=>'git push', 'out'=>$push];

        echo json_encode([
            'success'  => $ok,
            'conflict' => false,
            'steps'    => $steps,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ローカルの作業ツリー（未コミット含む）とGitHub（origin/branch）で
    // 内容が異なるPHP/JS/CSS/HTMLファイルの一覧を返す。
    if ($action === 'git_file_diff') {
        $branch = $gitRun("$g branch --show-current", 5) ?: 'master';
        $gitRun("$g fetch --quiet origin", 15);
        $pathspec = "-- '*.php' '*.js' '*.css' '*.html'";
        $diffOut = $gitRun("$g diff --name-status origin/" . escapeshellarg($branch) . " $pathspec", 15);
        $files = [];
        foreach (($diffOut ? explode("\n", $diffOut) : []) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (!preg_match('/^([AMD])\s+(.+)$/', $line, $m)) continue;
            $files[] = ['path' => $m[2], 'status' => $m[1]];
        }
        echo json_encode(['success'=>true,'branch'=>$branch,'files'=>$files], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // GitHub（origin/branch）にコミット済みのschema.jsonを読み、
    // ローカルの現在のDBスキーマと比較できるようにする。
    if ($action === 'schema_github') {
        $branch = $gitRun("$g branch --show-current", 5) ?: 'master';
        $gitRun("$g fetch --quiet origin", 15);
        $content = $gitRun("$g show " . escapeshellarg("origin/$branch:schema.json"), 5);
        $data = json_decode($content, true);
        if (!$data) {
            echo json_encode(['success'=>true,'exists'=>false,'branch'=>$branch], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['success'=>true,'exists'=>true,'branch'=>$branch,'schema'=>$data['schema'] ?? $data,'exported_at'=>$data['exported_at'] ?? null], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── ローカルの現在のDBスキーマをschema.jsonとして書き出す（GitHubへコミットするため） ──
if ($action === 'schema_export_json' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1','::1'])) {
        http_response_code(403);
        die(json_encode(['success'=>false,'error'=>'この操作はローカルPCからのみ実行できます']));
    }
    $schema = [];
    foreach ($SYNC_TABLES as $tbl) {
        $r = $conn->query("SHOW COLUMNS FROM `$tbl`");
        if (!$r) { $schema[$tbl] = []; continue; }
        $cols = [];
        while ($c = $r->fetch_assoc()) {
            $cols[$c['Field']] = [
                'type'    => $c['Type'],
                'null'    => $c['Null'],
                'default' => $c['Default'],
                'extra'   => $c['Extra'],
            ];
        }
        $schema[$tbl] = $cols;
    }
    $root = realpath(dirname(__DIR__));
    $out  = ['exported_at' => date('c'), 'env' => ENV_NAME, 'schema' => $schema];
    $written = file_put_contents($root . DIRECTORY_SEPARATOR . 'schema.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => $written !== false], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── ファイル一覧（MD5付き） ──────────────────────────────────
if ($action === 'files') {
    $root = realpath(dirname(__DIR__));
    $excludeDirs  = ['photos','uploads','sync','.git','node_modules','cache','tmp'];
    $excludeFiles = ['config.local.php','config.php','deploy.php'];
    $includeExts  = ['php','js','css','html'];
    $files = [];
    try {
        $rii = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                function($cur, $key, $iter) use ($excludeDirs) {
                    if ($iter->hasChildren()) return !in_array($cur->getFilename(), $excludeDirs);
                    return true;
                }
            )
        );
        foreach ($rii as $f) {
            if (!$f->isFile()) continue;
            if (!in_array(strtolower($f->getExtension()), $includeExts)) continue;
            if (in_array($f->getFilename(), $excludeFiles)) continue;
            $abs = $f->getPathname();
            $rel = ltrim(str_replace([$root . DIRECTORY_SEPARATOR, $root . '/'], '', $abs), '/\\');
            $rel = str_replace('\\', '/', $rel);
            // 改行コード（CRLF/LF）の違いだけで差分と誤検知しないよう正規化してからハッシュ化
            $normalized = str_replace("\r\n", "\n", file_get_contents($abs));
            $files[$rel] = ['md5' => md5($normalized), 'size' => $f->getSize(), 'mtime' => $f->getMTime()];
        }
        ksort($files);
    } catch (Exception $e) { /* ignore */ }
    echo json_encode(['success' => true, 'env' => ENV_NAME, 'files' => $files], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['success'=>false,'error'=>'不明なアクション']);
