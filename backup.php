<?php
require_once 'config.php';
require_once 'lib/backup.php';
requireLogin();

$conn    = getDB();
$msg     = '';
$msgType = 'ok';
$viewSid = trim($_GET['sid'] ?? '');   // 世代一覧表示する生徒

/* ── POST アクション ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'トークンエラー'; $msgType = 'err';
    } else {
        $act = $_POST['action'] ?? '';

        if ($act === 'export_all') {
            $r = karteBackupAll($conn);
            $msg = "バックアップ完了: {$r['ok']}名成功";
            if ($r['fail']) $msg .= "、{$r['fail']}名失敗 (" . implode(',',$r['ids']) . ')';
            $msgType = $r['fail'] ? 'warn' : 'ok';

        } elseif ($act === 'download_zip') {
            // 全バックアップJSONをZIPにまとめてダウンロード
            karteBackupAll($conn); // 最新化
            $files = glob(KARTE_BACKUP_DIR . '*.json') ?: [];
            if (!class_exists('ZipArchive')) {
                // ZipArchive非対応の場合: JSON全件を1ファイルにまとめてダウンロード
                $conn->close();
                $all = [];
                foreach ($files as $f) {
                    $sid = basename($f, '.json');
                    $all[$sid] = json_decode(file_get_contents($f), true);
                }
                $json = json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                header('Content-Type: application/json; charset=UTF-8');
                header('Content-Disposition: attachment; filename="karte_backup_'.date('Ymd_His').'.json"');
                header('Content-Length: ' . strlen($json));
                echo $json;
                exit;
            }
            $zipFile = tempnam(sys_get_temp_dir(), 'karte_bak_') . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
                $msg = 'ZIP作成に失敗しました'; $msgType = 'err';
            } else {
                foreach ($files as $f) $zip->addFile($f, basename($f));
                $zip->close();
                $conn->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="karte_backup_'.date('Ymd_His').'.zip"');
                header('Content-Length: ' . filesize($zipFile));
                readfile($zipFile);
                @unlink($zipFile);
                exit;
            }

        } elseif ($act === 'restore_all') {
            $files = glob(KARTE_BACKUP_DIR . '*.json') ?: [];
            $ok = $fail = 0;
            foreach ($files as $f) {
                $sid = basename($f, '.json');
                karteRestoreStudent($conn, $sid)['success'] ? $ok++ : $fail++;
            }
            $msg = "復元完了: {$ok}名成功" . ($fail ? "、{$fail}名失敗" : '');
            $msgType = $fail ? 'warn' : 'ok';

        } elseif ($act === 'restore_one') {
            $sid = trim($_POST['sid'] ?? '');
            $r   = karteRestoreStudent($conn, $sid);
            $msg = $r['success'] ? "{$sid} を最新バックアップから復元しました" : "復元失敗: ".($r['error']??'');
            $msgType = $r['success'] ? 'ok' : 'err';

        } elseif ($act === 'restore_version') {
            $sid = trim($_POST['sid'] ?? '');
            $ts  = trim($_POST['ts']  ?? '');
            $r   = karteRestoreVersion($conn, $sid, $ts);
            $viewSid = $sid;
            $msg = $r['success'] ? "バージョン {$ts} から復元しました" : "復元失敗: ".($r['error']??'');
            $msgType = $r['success'] ? 'ok' : 'err';

        } elseif ($act === 'delete_version') {
            $sid    = trim($_POST['sid'] ?? '');
            $ts     = preg_replace('/[^0-9_]/', '', $_POST['ts'] ?? '');
            $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sid);
            $file   = KARTE_BACKUP_HIST . $safeId . '/' . $ts . '.json';
            if ($file && file_exists($file)) { @unlink($file); $msg = "バージョン {$ts} を削除しました"; }
            else { $msg = 'ファイルが見つかりません'; $msgType = 'err'; }
            $viewSid = $sid;

        } elseif ($act === 'restore_from_zip') {
            $up = $_FILES['zipfile'] ?? null;
            if (!$up || $up['error'] !== UPLOAD_ERR_OK) {
                $msg = 'ファイルのアップロードに失敗しました'; $msgType = 'err';
            } else {
                $tmpPath = $up['tmp_name'];
                $origName = strtolower($up['name'] ?? '');
                $ok = $fail = 0;
                $failIds = [];

                // マジックバイトでZIP判定（拡張子・ZipArchive可否より信頼性が高い）
                $magic = file_get_contents($tmpPath, false, null, 0, 4);
                $isZip = ($magic === "PK\x03\x04" || $magic === "PK\x05\x06");

                if ($isZip && !class_exists('ZipArchive')) {
                    $msg = 'このサーバーはZipArchiveに対応していません。JSONバンドル形式（.json）でダウンロードしたファイルをお使いください。'; $msgType = 'err';
                } elseif ($isZip && class_exists('ZipArchive')) {
                    // ZIP形式: 各 *.json を KARTE_BACKUP_DIR に書き込んで復元
                    $zip = new ZipArchive();
                    if ($zip->open($tmpPath) !== true) {
                        $msg = 'ZIPファイルを開けませんでした'; $msgType = 'err';
                    } else {
                        $firstErr = '';
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $name = $zip->getNameIndex($i);
                            if (!str_ends_with($name, '.json')) continue;
                            $sid = preg_replace('/[^a-zA-Z0-9_\-]/', '', basename($name, '.json'));
                            if (!$sid) continue;
                            $content = $zip->getFromIndex($i);
                            if ($content === false) { $fail++; $failIds[] = $sid; continue; }
                            if (!json_decode($content)) { $fail++; $failIds[] = $sid; continue; }
                            $written = file_put_contents(KARTE_BACKUP_DIR . $sid . '.json', $content);
                            if ($written === false) {
                                $fail++; $failIds[] = $sid;
                                if (!$firstErr) $firstErr = "書込失敗: " . KARTE_BACKUP_DIR . $sid . '.json';
                                continue;
                            }
                            $r = karteRestoreStudent($conn, $sid);
                            if ($r['success']) {
                                $ok++;
                            } else {
                                $fail++; $failIds[] = $sid;
                                if (!$firstErr) $firstErr = $r['error'] ?? '不明なエラー';
                            }
                        }
                        $zip->close();
                        if ($ok === 0 && $fail === 0) {
                            $msg = 'ZIPにJSONファイルが見つかりませんでした'; $msgType = 'warn';
                        } else {
                            $msg = "ZIP復元完了: {$ok}名成功";
                            if ($fail) $msg .= "、{$fail}名失敗";
                            if ($firstErr) $msg .= " — エラー例: {$firstErr}";
                            $msgType = $fail ? 'warn' : 'ok';
                        }
                    }
                } elseif (!$isZip) {
                    // JSON bundle形式: { "sid": {...}, ... }
                    $content = file_get_contents($tmpPath);
                    $all = json_decode($content, true);
                    if (!is_array($all)) {
                        $msg = 'ファイル形式が正しくありません（ZIP または JSONバンドルが必要です）'; $msgType = 'err';
                    } else {
                        foreach ($all as $sid => $data) {
                            $sid = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$sid);
                            if (!$sid || !is_array($data)) { $fail++; continue; }
                            file_put_contents(KARTE_BACKUP_DIR . $sid . '.json', json_encode($data, JSON_UNESCAPED_UNICODE));
                            $r = karteRestoreStudent($conn, $sid);
                            $r['success'] ? $ok++ : ($fail++ && ($failIds[] = $sid));
                        }
                        $msg = "JSONバンドル復元完了: {$ok}名成功";
                        if ($fail) $msg .= "、{$fail}名失敗";
                        $msgType = $fail ? 'warn' : 'ok';
                    }
                }
            }
        }
    }
}

/* ── 一覧データ ── */
$files = glob(KARTE_BACKUP_DIR . '*.json') ?: [];
$backups = [];
foreach ($files as $f) {
    $sid  = basename($f, '.json');
    $json = @json_decode(file_get_contents($f), true);
    $vcount = count(glob(KARTE_BACKUP_HIST . $sid . '/*.json') ?: []);
    $backups[] = [
        'sid'     => $sid,
        'name'    => $json['student']['name'] ?? '—',
        'class'   => $json['student']['class_name'] ?? '—',
        'records' => count($json['records'] ?? []),
        'att'     => count($json['attendance'] ?? []),
        'inter'   => count($json['interviews'] ?? []),
        'updated' => $json['_meta']['exported_at'] ?? '—',
        'size'    => round(filesize($f)/1024, 1),
        'versions'=> $vcount,
    ];
}
usort($backups, fn($a,$b) => strcmp($a['sid'], $b['sid']));

/* ── 世代一覧（サイドパネル用） ── */
$versions = $viewSid ? karteListVersions($viewSid) : [];
$viewName = '';
foreach ($backups as $b) { if ($b['sid'] === $viewSid) { $viewName = $b['name']; break; } }

$firstSid = $conn->query("SELECT student_id FROM students ORDER BY class_name,seat_number,student_id LIMIT 1")->fetch_assoc()['student_id'] ?? '';
$conn->close();
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/karte/favicon.php">
  <link rel="icon" type="image/png" sizes="32x32" href="/karte/icon-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/karte/icon-180.png">
  <link rel="manifest" href="/karte/manifest.json">
  <meta name="theme-color" content="#1a2a55">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>バックアップ — カルテ</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo',sans-serif;background:#cdd1dc;min-height:100vh;font-size:13px;}
.topbar{background:linear-gradient(180deg,#2c3e6b,#1a2a55);color:#fff;padding:8px 16px;display:flex;align-items:center;gap:12px;border-bottom:2px solid #0f1e40;flex-wrap:wrap;}
.topbar h1{font-size:1.05rem;font-weight:900;color:#e8ecff;white-space:nowrap;}
.topbar a,.topbar button.tbtn{color:#c4d4ff;font-size:.8rem;text-decoration:none;padding:5px 11px;border:1px solid rgba(255,255,255,.25);border-radius:5px;background:rgba(255,255,255,.08);cursor:pointer;font-family:inherit;font-weight:700;}
.topbar a:hover,.topbar button.tbtn:hover{background:rgba(255,255,255,.2);}
.kebab-menu{position:relative;margin-left:auto;}
.kebab-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);color:#e8ecff;border-radius:6px;padding:6px 10px;cursor:pointer;line-height:1;font-family:inherit;display:flex;flex-direction:column;gap:4px;align-items:center;justify-content:center;width:38px;height:34px;}
.kebab-btn span{display:block;width:18px;height:2px;background:#e8ecff;border-radius:1px;}
.kebab-btn:hover{background:rgba(255,255,255,.25);}
.kebab-dropdown{display:none;position:absolute;top:calc(100% + 6px);right:0;background:linear-gradient(180deg,#2c3e6b,#1a2a55);border:1px solid rgba(255,255,255,.2);border-radius:8px;min-width:170px;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,.4);overflow:hidden;}
.kebab-dropdown.open{display:block;}
.kebab-dropdown a,.kebab-dropdown button{display:block;width:100%;padding:10px 16px;color:#e8ecff;text-decoration:none;font-size:.85rem;border:none;border-bottom:1px solid rgba(255,255,255,.08);background:none;text-align:left;cursor:pointer;font-family:inherit;box-sizing:border-box;}
.kebab-dropdown a:last-child,.kebab-dropdown button:last-child{border-bottom:none;}
.kebab-dropdown a:hover,.kebab-dropdown button:hover{background:rgba(255,255,255,.15);}
.kebab-dropdown .current-page{color:#6a7a99;cursor:default;pointer-events:none;}
.kebab-dropdown .current-page:hover{background:none;}
.tbtn-excel{background:rgba(0,128,0,.35)!important;border-color:rgba(0,200,0,.4)!important;}
.layout{display:flex;gap:0;min-height:calc(100vh - 45px);}
.main{flex:1;padding:16px;min-width:0;}
.side{width:360px;background:#fff;border-left:2px solid #aab0cc;padding:14px;overflow-y:auto;flex-shrink:0;display:<?= $viewSid ? 'block' : 'none' ?>;}
.card{background:#fff;border-radius:8px;padding:16px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.14);}
.card h2{font-size:.9rem;font-weight:800;color:#1a2240;margin-bottom:10px;padding-bottom:7px;border-bottom:2px solid #dde0ee;}
.btn{padding:7px 15px;border-radius:6px;border:none;cursor:pointer;font-size:.8rem;font-weight:700;font-family:inherit;transition:background .15s;}
.btn-primary{background:#2c5282;color:#fff;} .btn-primary:hover{background:#1a3a6e;}
.btn-danger{background:#c53030;color:#fff;} .btn-danger:hover{background:#9b2c2c;}
.btn-green{background:#276749;color:#fff;} .btn-green:hover{background:#1a4a32;}
.btn-sm{padding:3px 9px;font-size:.72rem;}
.msg{padding:9px 13px;border-radius:6px;margin-bottom:12px;font-weight:700;font-size:.83rem;}
.msg.ok{background:#c6f6d5;color:#276749;border:1px solid #9ae6b4;}
.msg.warn{background:#fefcbf;color:#744210;border:1px solid #f6e05e;}
.msg.err{background:#fed7d7;color:#9b2c2c;border:1px solid #fc8181;}
.stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
.stat{background:#f0f4ff;border:1px solid #c3d0f0;border-radius:8px;padding:9px 16px;text-align:center;}
.stat .num{font-size:1.5rem;font-weight:900;color:#2c5282;}
.stat .lbl{font-size:.7rem;color:#5a6080;margin-top:2px;}
table{width:100%;border-collapse:collapse;font-size:.78rem;}
th{background:#dde0ee;color:#3a4060;font-weight:700;padding:5px 7px;text-align:left;border:1px solid #aab0cc;}
td{padding:4px 7px;border:1px solid #d0d4dc;vertical-align:middle;}
tr:nth-child(even) td{background:#f7f8fc;}
tr:hover td{background:#eef1fb;}
.path{font-size:.69rem;color:#6677aa;margin-top:7px;word-break:break-all;}
/* 世代サイドパネル */
.side h3{font-size:.88rem;font-weight:800;color:#1a2240;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;}
.side h3 a{font-size:.75rem;color:#6677aa;text-decoration:none;font-weight:400;}
.ver-item{border:1px solid #d0d4dc;border-radius:6px;padding:8px 10px;margin-bottom:7px;background:#f8f9fc;}
.ver-item .ts{font-size:.76rem;font-weight:700;color:#2c4080;}
.ver-item .meta{font-size:.7rem;color:#8899cc;margin-top:2px;}
.ver-item .acts{margin-top:6px;display:flex;gap:6px;}
.badge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:.68rem;font-weight:700;background:#dde8ff;color:#3a5080;}
/* インポート */
.import-box{border:2px dashed #aab0cc;border-radius:8px;padding:16px;text-align:center;background:#f8f9fc;transition:border-color .2s;}
.import-box.drag{border-color:#2c5282;background:#eef2ff;}
.import-box input[type=file]{display:none;}
.import-label{display:inline-block;padding:8px 18px;background:#276749;color:#fff;border-radius:6px;cursor:pointer;font-weight:700;font-size:.83rem;}
.import-label:hover{background:#1a4a32;}
#importResult{margin-top:12px;font-size:.82rem;}
.res-ok{color:#276749;font-weight:700;}
.res-err{color:#c53030;font-weight:700;}

/* ── 機能カードグリッド ── */
.func-group{border:1px solid #d0d4dc;border-radius:8px;overflow:hidden;}
.func-group-label{padding:7px 14px;font-size:.78rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
.func-label-dl{background:#e8f4ec;color:#1a4a32;border-bottom:1px solid #b2d8be;}
.func-label-restore{background:#fff0f0;color:#7a1a1a;border-bottom:1px solid #f0b8b8;}
.func-grid{display:grid;gap:1px;background:#d0d4dc;}
.func-grid-3{grid-template-columns:repeat(3,1fr);}
.func-grid-2{grid-template-columns:repeat(2,1fr);}
.func-item{background:#fff;padding:14px;display:flex;flex-direction:column;gap:8px;}
.func-item-danger{background:#fffafa;}
.func-icon{font-size:1.6rem;line-height:1;}
.func-title{font-size:.88rem;font-weight:800;color:#1a2240;}
.func-desc{font-size:.75rem;color:#4a5070;line-height:1.7;flex:1;}
.func-desc code{background:#eef;padding:1px 4px;border-radius:3px;font-size:.85em;}
.tag{display:inline-block;padding:1px 7px;border-radius:10px;font-size:.7rem;font-weight:700;margin-top:2px;}
.tag-green{background:#c6f6d5;color:#1a4a32;}
.tag-danger{background:#fed7d7;color:#7a1a1a;}
/* ボタン — はっきりした立体感 */
.func-btn{display:block;width:100%;padding:9px 14px;border-radius:7px;border:none;cursor:pointer;font-size:.82rem;font-weight:800;font-family:inherit;text-align:center;box-shadow:0 3px 0 rgba(0,0,0,.25);transition:box-shadow .1s,transform .1s;box-sizing:border-box;}
.func-btn:active{box-shadow:0 1px 0 rgba(0,0,0,.2);transform:translateY(2px);}
.func-btn-green{background:linear-gradient(180deg,#38a169,#276749);color:#fff;}
.func-btn-green:hover{background:linear-gradient(180deg,#48bb78,#2f7a56);}
.func-btn-blue{background:linear-gradient(180deg,#4a7cc9,#2c5282);color:#fff;}
.func-btn-blue:hover{background:linear-gradient(180deg,#5a8cd9,#3a62a0);}
.func-btn-purple{background:linear-gradient(180deg,#9f7aea,#6b46c1);color:#fff;}
.func-btn-purple:hover{background:linear-gradient(180deg,#b794f4,#7c54d4);}
.func-btn-red{background:linear-gradient(180deg,#e05252,#c53030);color:#fff;}
.func-btn-red:hover{background:linear-gradient(180deg,#f06060,#d44040);}
.func-btn-brown{background:linear-gradient(180deg,#c47a2a,#744210);color:#fff;}
.func-btn-brown:hover{background:linear-gradient(180deg,#d48a3a,#855020);}
@media(max-width:700px){
  .func-grid-3{grid-template-columns:1fr;}
  .func-grid-2{grid-template-columns:1fr;}
}

/* ── 5機能グリッド ── */
.box4-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px;}
@media(max-width:1300px){.box4-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:800px){.box4-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:500px){.box4-grid{grid-template-columns:1fr;}}
.box4{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.14);overflow:hidden;display:flex;flex-direction:column;}
.box4-header{padding:10px 14px;font-size:.88rem;font-weight:900;letter-spacing:.03em;color:#fff;}
.box4-hdr-green{background:linear-gradient(135deg,#276749,#38a169);}
.box4-hdr-blue{background:linear-gradient(135deg,#2c5282,#4a7cc9);}
.box4-hdr-excel{background:linear-gradient(135deg,#1a5c2a,#2e9148);}
.box4-hdr-brown{background:linear-gradient(135deg,#744210,#c47a2a);}
.box4-hdr-indigo{background:linear-gradient(135deg,#3730a3,#6d28d9);}
/* 同期ログ */
.sync-log{background:#1e2340;color:#a0f0b0;border-radius:6px;padding:10px 12px;font-family:monospace;font-size:.75rem;min-height:60px;max-height:130px;overflow-y:auto;white-space:pre-wrap;margin-top:6px;}
.sync-log .err{color:#ff8080;}.sync-log .ok{color:#7fffaa;}.sync-log .info{color:#80d0ff;}
.sync-prog{height:4px;background:#e0e4f0;border-radius:2px;overflow:hidden;margin:4px 0;display:none;}
.sync-prog-bar{height:100%;background:linear-gradient(90deg,#6d28d9,#8b5cf6);width:0;transition:width .4s;}
.box4-section{padding:12px 14px;display:flex;flex-direction:column;gap:8px;flex:1;}
.box4-section+.box4-section{border-top:2px solid #e2e8f0;}
.box4-out{background:#f6fff9;}
.box4-in{background:#fff8f8;}
.box4-section-label{font-size:.7rem;font-weight:800;color:#8899cc;letter-spacing:.08em;text-transform:uppercase;margin-bottom:2px;}
.box4-desc{font-size:.74rem;color:#4a5070;line-height:1.7;flex:1;}
.box4-desc code{background:#eef;padding:1px 4px;border-radius:3px;font-size:.85em;}
.box4-footer{padding:6px 14px;font-size:.68rem;color:#8899cc;background:#f0f2f8;border-top:1px solid #e0e4ef;word-break:break-all;}
</style>
</head>
<body>
<div class="topbar">
  <h1>🗄️ バックアップ</h1>
  <div class="kebab-menu">
    <button class="kebab-btn" onclick="toggleKebab(event)" title="メニュー"><span></span><span></span><span></span></button>
    <div class="kebab-dropdown" id="kebabDropdown">
      <?php if($firstSid):?><a href="/karte/karte_detail.php?id=<?= urlencode($firstSid) ?>">🏫 生徒情報</a><?php endif;?>
      <?php if($firstSid):?><a href="/karte/karte_detail.php?id=<?= urlencode($firstSid) ?>&list=1">📋 一覧表示</a><?php endif;?>
      <a href="/karte/home.php">🏠 HOME</a>
      <?php if($firstSid):?><a href="/karte/karte_card.php?id=<?= urlencode($firstSid) ?>">🖨 印刷・PDF</a><?php endif;?>
      <a href="/karte/gakuseki.php">📚 学籍管理</a>
      <a href="/karte/student_manager.php">👥 生徒管理</a>
      <a href="/karte/photo_import.php">📸 写真取込</a>
      <a href="/karte/survey_import.php">📋 調査票取込</a>
      <a href="/karte/structure.php">🗺 構造図</a>
      <a class="current-page">🗄️ バックアップ</a>
      <a href="/karte/sync.php">🔄 DB同期</a>
      <a href="/karte/account.php">⚙ アカウント</a>
      <a href="/karte/logout.php">🚪 ログアウト</a>
    </div>
  </div>
</div>

<div class="layout">
<div class="main">

<?php if ($msg): ?>
<div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- 統計 -->
<div class="stats">
  <div class="stat"><div class="num"><?= count($backups) ?></div><div class="lbl">バックアップ済</div></div>
  <div class="stat"><div class="num"><?= array_sum(array_column($backups,'records')) ?></div><div class="lbl">指導記録</div></div>
  <div class="stat"><div class="num"><?= array_sum(array_column($backups,'att')) ?></div><div class="lbl">出欠記録</div></div>
  <div class="stat"><div class="num"><?= array_sum(array_column($backups,'inter')) ?></div><div class="lbl">面談記録</div></div>
  <div class="stat"><div class="num"><?= array_sum(array_column($backups,'versions')) ?></div><div class="lbl">世代総数</div></div>
  <div class="stat"><div class="num"><?= round(array_sum(array_column($backups,'size')),1) ?>KB</div><div class="lbl">合計サイズ</div></div>
</div>

<!-- 4機能グリッド -->
<div class="box4-grid">

  <!-- ① ZIPバックアップ -->
  <div class="box4">
    <div class="box4-header box4-hdr-green">📦 ZIPバックアップ</div>
    <div class="box4-section box4-out">
      <div class="box4-section-label">⬇ 出力</div>
      <div class="box4-desc">
        全生徒の <code>.json</code> を <code>.zip</code> にまとめてPCに保存。<br>
        <span class="tag tag-green">学籍台帳・年度別クラス含む</span>
      </div>
      <form method="post" onsubmit="return confirm('最新状態にバックアップしてZIPでダウンロードします。よろしいですか？')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="download_zip">
        <button type="submit" class="func-btn func-btn-green">⬇ ZIPでダウンロード</button>
      </form>
    </div>
    <div class="box4-section box4-in">
      <div class="box4-section-label">⬆ 入力</div>
      <div class="box4-desc">
        ダウンロードした <code>.zip</code> をアップロードしてDBを復元。<br>
        <span class="tag tag-danger">⚠ 既存データは上書き</span>
      </div>
      <div class="import-box" id="zipRestoreBox">
        <form method="post" enctype="multipart/form-data" id="zipRestoreForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="restore_from_zip">
          <label class="func-btn func-btn-purple" for="zipFile" style="display:block;text-align:center;cursor:pointer;">📂 .zip / .json を選択</label>
          <input type="file" id="zipFile" name="zipfile" accept=".zip,.json" onchange="startZipRestore(this)" style="display:none;">
        </form>
        <div style="margin-top:4px;font-size:.7rem;color:#8899cc;text-align:center;">ドラッグ＆ドロップも可</div>
        <div id="zipRestoreResult"></div>
      </div>
    </div>
    <div class="box4-footer">📁 <?= htmlspecialchars(KARTE_BACKUP_DIR) ?> | 世代保持: <?= KARTE_BACKUP_KEEP ?>件</div>
  </div>

  <!-- ② JSON世代管理 -->
  <div class="box4">
    <div class="box4-header box4-hdr-blue">🗂️ JSON世代管理</div>
    <div class="box4-section box4-out">
      <div class="box4-section-label">⬇ 出力（サーバーに保存）</div>
      <div class="box4-desc">
        全生徒を <code>.json</code> としてサーバーに保存。<br>
        世代履歴が自動作成され、過去の状態に戻せます。
      </div>
      <form method="post" onsubmit="return confirm('全生徒のバックアップを更新しますか？')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="export_all">
        <button type="submit" class="func-btn func-btn-blue">💾 全員バックアップ実行</button>
      </form>
    </div>
    <div class="box4-section box4-in">
      <div class="box4-section-label">⬆ 入力（サーバーから復元）</div>
      <div class="box4-desc">
        サーバー内の最新 <code>.json</code> でDBを上書き復元。<br>
        <span class="tag tag-danger">⚠ 既存データは上書き</span><br>
        <span style="font-size:.72rem;color:#5a6080;">個別復元は下の一覧表から</span>
      </div>
      <form method="post" onsubmit="return confirm('⚠ 全JSONファイルでDBを上書きします。よろしいですか？')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="restore_all">
        <button type="submit" class="func-btn func-btn-red">♻ サーバーから全員復元</button>
      </form>
    </div>
  </div>

  <!-- ③ Excel連携 -->
  <div class="box4">
    <div class="box4-header box4-hdr-excel">📊 Excel連携機能</div>
    <div class="box4-section box4-out">
      <div class="box4-section-label">⬇ 出力</div>
      <div class="box4-desc">
        指導記録・出欠・面談を <code>.xls</code> 形式でPC保存。<br>
        閲覧・印刷・他のカルテへの取り込みに使用。
      </div>
      <a href="/karte/api/export_excel.php" class="func-btn func-btn-green" style="text-decoration:none;display:block;text-align:center;">📊 Excelダウンロード</a>
    </div>
    <div class="box4-section box4-in">
      <div class="box4-section-label">⬆ 入力</div>
      <div class="box4-desc">
        <code>.xls</code> をアップロードして別のカルテのデータを取り込み。<br>
        <span class="tag tag-green">✔ 重複データは自動スキップ</span>
      </div>
      <div class="import-box" id="importBox">
        <label class="func-btn func-btn-green" for="importFile" style="display:block;text-align:center;cursor:pointer;">📂 .xls を選択</label>
        <input type="file" id="importFile" accept=".xls,.xlsx" onchange="startImport(this)" style="display:none;">
        <div style="margin-top:4px;font-size:.7rem;color:#8899cc;text-align:center;">ドラッグ＆ドロップも可</div>
        <div id="importResult"></div>
      </div>
    </div>
  </div>

  <!-- ④ CSV保護者情報 -->
  <div class="box4">
    <div class="box4-header box4-hdr-brown">📋 CSV保護者情報入出力</div>
    <div class="box4-section box4-out">
      <div class="box4-section-label">⬇ 出力</div>
      <div class="box4-desc">
        全生徒の保護者電話番号・勤務先情報を出力。<br>
        <code>.csv</code>（Excel対応・BOM付きUTF-8）
      </div>
      <a href="/karte/api/export_contacts_csv.php" class="func-btn func-btn-brown" style="text-decoration:none;display:block;text-align:center;">⬇ CSVダウンロード</a>
    </div>
    <div class="box4-section box4-in">
      <div class="box4-section-label">⬆ 入力</div>
      <div class="box4-desc">
        CSVをアップロードして保護者・職場情報を一括更新。<br>
        <strong>学籍番号</strong>をキーに照合・上書き。
      </div>
      <div class="import-box" style="">
        <label class="func-btn func-btn-brown" for="csvFile" style="display:block;text-align:center;cursor:pointer;">📂 CSVを選択</label>
        <input type="file" id="csvFile" accept=".csv" onchange="startCsvImport(this)" style="display:none;">
        <div style="margin-top:4px;font-size:.7rem;color:#8899cc;text-align:center;">ドラッグ＆ドロップも可</div>
        <div id="csvImportResult"></div>
      </div>
    </div>
  </div>

  <!-- ⑤ サーバー同期（ローカルのみ表示） -->
  <?php if (ENV_NAME === 'local'): ?>
  <div class="box4">
    <div class="box4-header box4-hdr-indigo">🔄 サーバーDB同期</div>
    <div class="box4-section box4-out">
      <div class="box4-section-label">⬇ ダウンロード</div>
      <div class="box4-desc">
        サーバーのデータで<br>ローカルを上書き。
      </div>
      <button class="func-btn func-btn-blue" id="syncBtnDown" onclick="doBackupSync('download')">⬇ サーバー→ローカル</button>
    </div>
    <div class="box4-section" style="background:#f5f3ff;">
      <div class="box4-section-label" style="color:#5b21b6;">🔀 マージ</div>
      <div class="box4-desc">
        更新日時を比較して<br>新しいほうを両方に反映。
      </div>
      <button class="func-btn func-btn-purple" id="syncBtnMerge" onclick="doBackupSync('merge')">🔀 マージ同期</button>
    </div>
    <div class="box4-section box4-in">
      <div class="box4-section-label">⬆ アップロード</div>
      <div class="box4-desc">
        ローカルのデータで<br>サーバーを上書き。
      </div>
      <button class="func-btn func-btn-brown" id="syncBtnUp" onclick="doBackupSync('upload')">⬆ ローカル→サーバー</button>
    </div>
    <div class="box4-section" style="background:#fafafa;padding-top:8px;padding-bottom:8px;">
      <div class="sync-prog" id="syncProg"><div class="sync-prog-bar" id="syncProgBar"></div></div>
      <div class="sync-log" id="syncLog">準備完了</div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /box4-grid -->

<!-- 生徒別一覧 -->
<div class="card">
  <h2>生徒別バックアップ一覧（<?= count($backups) ?>名）</h2>
  <?php if ($backups): ?>
  <table>
    <thead>
      <tr>
        <th>学籍番号</th><th>氏名</th><th>クラス</th>
        <th>指導</th><th>出欠</th><th>面談</th>
        <th>最終保存</th><th>世代</th><th>サイズ</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($backups as $b): ?>
      <tr <?= $b['sid']===$viewSid ? 'style="background:#ddeeff"' : '' ?>>
        <td><?= htmlspecialchars($b['sid']) ?></td>
        <td><?= htmlspecialchars($b['name']) ?></td>
        <td><?= htmlspecialchars($b['class']) ?></td>
        <td style="text-align:center"><?= $b['records'] ?></td>
        <td style="text-align:center"><?= $b['att'] ?></td>
        <td style="text-align:center"><?= $b['inter'] ?></td>
        <td style="font-size:.72rem"><?= htmlspecialchars(substr($b['updated'],0,16)) ?></td>
        <td style="text-align:center">
          <a href="?sid=<?= urlencode($b['sid']) ?>" class="badge"><?= $b['versions'] ?>件</a>
        </td>
        <td style="text-align:right"><?= $b['size'] ?>KB</td>
        <td style="white-space:nowrap">
          <form method="post" style="display:inline" onsubmit="return confirm('<?= htmlspecialchars($b['name'],ENT_QUOTES) ?> を最新バックアップから復元しますか？')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="restore_one">
            <input type="hidden" name="sid" value="<?= htmlspecialchars($b['sid']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">復元</button>
          </form>
          <a href="/karte/karte_detail.php?id=<?= urlencode($b['sid']) ?>"
             class="btn btn-sm" style="background:#4a5a96;color:#fff;text-decoration:none;margin-left:4px;">カルテ</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p style="color:#9aa0c0;padding:10px">バックアップがありません。まず「全員バックアップ今すぐ実行」を押してください。</p>
  <?php endif; ?>
</div>

</div><!-- /.main -->

<!-- 世代サイドパネル -->
<div class="side" id="sidePanel">
  <h3>
    <?= htmlspecialchars($viewName) ?> の世代履歴
    <a href="/karte/backup.php">✕ 閉じる</a>
  </h3>

  <?php if (empty($versions)): ?>
    <p style="color:#9aa0c0;font-size:.8rem">世代ファイルがまだありません。<br>データを編集すると自動的に世代が作成されます。</p>
  <?php else: ?>
    <p style="font-size:.72rem;color:#6677aa;margin-bottom:10px">最新 <?= count($versions) ?> 件（最大 <?= KARTE_BACKUP_KEEP ?> 件保持）</p>
    <?php foreach ($versions as $v): ?>
    <div class="ver-item">
      <div class="ts"><?= htmlspecialchars($v['label']) ?></div>
      <div class="meta"><?= $v['size'] ?>KB</div>
      <div class="acts">
        <form method="post" style="display:inline" onsubmit="return confirm('このバージョン(<?= htmlspecialchars($v['ts']) ?>)に戻しますか？現在のDBが上書きされます。')">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="restore_version">
          <input type="hidden" name="sid" value="<?= htmlspecialchars($viewSid) ?>">
          <input type="hidden" name="ts"  value="<?= htmlspecialchars($v['ts']) ?>">
          <button type="submit" class="btn btn-green btn-sm">↩ この時点に戻す</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('このバージョンを削除しますか？')">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="delete_version">
          <input type="hidden" name="sid" value="<?= htmlspecialchars($viewSid) ?>">
          <input type="hidden" name="ts"  value="<?= htmlspecialchars($v['ts']) ?>">
          <button type="submit" class="btn btn-danger btn-sm">削除</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

</div><!-- /.layout -->
<script>
const csrf = <?= json_encode($csrf) ?>;

function startImport(input) {
  const file = input.files[0];
  if (!file) return;
  doImport(file);
}

function doImport(file) {
  const res = document.getElementById('importResult');
  res.innerHTML = '<span style="color:#666">⏳ 取り込み中...</span>';
  const fd = new FormData();
  fd.append('csrf_token', csrf);
  fd.append('excel', file);
  fetch('/karte/api/import_excel.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (!d.success) {
        res.innerHTML = '<span class="res-err">❌ ' + d.error + '</span>';
        return;
      }
      const r = d.result;
      let html = '<div class="res-ok">✅ 取り込み完了</div>'
        + '<div style="margin-top:6px;font-size:.78rem;color:#444;line-height:1.8">'
        + '生徒: <b>' + r.students + '名</b>　'
        + '指導記録: <b>' + r.records + '件</b>　'
        + '出欠: <b>' + r.attendance + '件</b>　'
        + '面談: <b>' + r.interviews + '件</b>　'
        + '重複スキップ: <b>' + r.skipped + '件</b>'
        + '</div>';
      if (r.errors && r.errors.length) {
        html += '<div class="res-err" style="margin-top:4px;font-size:.72rem">'
          + r.errors.slice(0,5).join('<br>') + '</div>';
      }
      res.innerHTML = html;
      // 3秒後にページリロードして統計を更新
      setTimeout(() => location.reload(), 3000);
    })
    .catch(() => { res.innerHTML = '<span class="res-err">❌ 通信エラー</span>'; });
}

// ドラッグ＆ドロップ (Excel)
const box = document.getElementById('importBox');
box.addEventListener('dragover', e => { e.preventDefault(); box.classList.add('drag'); });
box.addEventListener('dragleave', () => box.classList.remove('drag'));
box.addEventListener('drop', e => {
  e.preventDefault(); box.classList.remove('drag');
  const file = e.dataTransfer.files[0];
  if (file) doImport(file);
});

// ZIP復元
function startZipRestore(input) {
  const file = input.files[0];
  if (!file) return;
  doZipRestore(file);
}

function doZipRestore(file) {
  const res = document.getElementById('zipRestoreResult');
  if (!confirm('⚠ アップロードしたZIPでDBを上書き復元します。よろしいですか？')) return;
  res.innerHTML = '<span style="color:#666">⏳ 復元中...</span>';
  const fd = new FormData();
  fd.append('csrf_token', csrf);
  fd.append('action', 'restore_from_zip');
  fd.append('zipfile', file);
  fetch(location.pathname, { method: 'POST', body: fd })
    .then(r => r.text())
    .then(html => {
      // レスポンスのmsgを抽出してリロード
      const m = html.match(/class="msg ([^"]+)">([^<]+)/);
      if (m) {
        const cls = m[1], txt = m[2];
        res.innerHTML = `<div class="msg ${cls}" style="margin-top:8px">${txt}</div>`;
      } else {
        res.innerHTML = '<span class="res-ok">✅ 完了</span>';
      }
      setTimeout(() => location.reload(), 2500);
    })
    .catch(() => { res.innerHTML = '<span class="res-err">❌ 通信エラー</span>'; });
}

// ドラッグ＆ドロップ (ZIP)
const zipBox = document.getElementById('zipRestoreBox');
zipBox.addEventListener('dragover', e => { e.preventDefault(); zipBox.classList.add('drag'); });
zipBox.addEventListener('dragleave', () => zipBox.classList.remove('drag'));
zipBox.addEventListener('drop', e => {
  e.preventDefault(); zipBox.classList.remove('drag');
  const file = e.dataTransfer.files[0];
  if (file) doZipRestore(file);
});

// ── 保護者連絡先CSVインポート ──
function startCsvImport(input) {
  const file = input.files[0];
  if (file) doCsvImport(file);
}
function doCsvImport(file) {
  const result = document.getElementById('csvImportResult');
  result.innerHTML = '<div style="color:#5a6080;font-size:.82rem;margin-top:8px;">⏳ インポート中…</div>';
  const fd = new FormData();
  fd.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
  fd.append('csvfile', file);
  fetch('/karte/api/import_contacts_csv.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        result.innerHTML = `<div style="color:#276749;font-weight:700;font-size:.82rem;margin-top:8px;">✅ ${d.message}</div>`;
      } else {
        result.innerHTML = `<div style="color:#c53030;font-weight:700;font-size:.82rem;margin-top:8px;">❌ ${d.error}</div>`;
      }
    })
    .catch(() => {
      result.innerHTML = '<div style="color:#c53030;font-size:.82rem;margin-top:8px;">❌ 通信エラー</div>';
    });
}
// ドラッグ＆ドロップ
const csvBox = document.querySelector('#csvFile')?.closest('.import-box');
if (csvBox) {
  csvBox.addEventListener('dragover', e => { e.preventDefault(); csvBox.classList.add('drag'); });
  csvBox.addEventListener('dragleave', () => csvBox.classList.remove('drag'));
  csvBox.addEventListener('drop', e => {
    e.preventDefault(); csvBox.classList.remove('drag');
    const file = e.dataTransfer.files[0];
    if (file) doCsvImport(file);
  });
}

// ── サーバー同期（backup.phpインライン版） ──────────────────
(function(){
const LOCAL_API  = '/karte/api/sync.php';
const REMOTE_API = '<?= REMOTE_URL ?>/karte/api/sync.php';
const TOKEN      = '<?= SYNC_TOKEN ?>';

function slog(msg, cls='') {
  const box = document.getElementById('syncLog');
  const ts  = new Date().toLocaleTimeString('ja-JP');
  box.innerHTML += (box.textContent==='準備完了' ? '' : '\n') +
    `<span class="${cls}">[${ts}] ${msg}</span>`;
  if (box.textContent==='準備完了') box.textContent = '';
  box.innerHTML = box.innerHTML.replace('準備完了','');
  box.scrollTop = box.scrollHeight;
}
function prog(pct) {
  const p = document.getElementById('syncProg');
  p.style.display = (pct > 0 && pct < 100) ? 'block' : 'none';
  document.getElementById('syncProgBar').style.width = pct + '%';
}
function setBtns(dis) {
  ['syncBtnDown','syncBtnMerge','syncBtnUp'].forEach(id => {
    document.getElementById(id).disabled = dis;
  });
}

// サーバー上での警告・ブロック
(function(){
  const isRemote=location.hostname!=='localhost'&&location.hostname!=='127.0.0.1';
  if(isRemote){
    document.getElementById('syncRemoteWarning').style.display='block';
    ['syncBtnDown','syncBtnMerge','syncBtnUp'].forEach(id=>{
      const b=document.getElementById(id);
      if(b){b.disabled=true;b.style.opacity='0.4';b.style.cursor='not-allowed';}
    });
  }
})();

window.doBackupSync = async function(dir) {
  if(location.hostname!=='localhost'&&location.hostname!=='127.0.0.1'){
    alert('データ同期はローカルPC（localhost）から実行してください。');return;
  }
  const msgs = {
    download: '⬇ サーバー→ローカルに同期します。\nローカルのデータが上書きされます。よろしいですか？',
    upload:   '⬆ ローカル→サーバーに同期します。\nサーバーのデータが上書きされます。よろしいですか？',
    merge:    '🔀 マージ同期を実行します。\n更新日時を比較して新しいほうを両方に反映します。よろしいですか？',
  };
  if (!confirm(msgs[dir])) return;

  document.getElementById('syncLog').textContent = '';
  setBtns(true); prog(10);

  try {
    if (dir === 'download' || dir === 'upload') {
      const srcApi = dir==='download' ? REMOTE_API : LOCAL_API;
      const dstApi = dir==='download' ? LOCAL_API  : REMOTE_API;
      const srcLbl = dir==='download' ? 'サーバー' : 'ローカル';
      const dstLbl = dir==='download' ? 'ローカル' : 'サーバー';
      slog(`${srcLbl}からエクスポート中…`, 'info');
      const expRes = await fetch(`${srcApi}?action=export&token=${TOKEN}`);
      if (!expRes.ok) throw new Error(`エクスポート失敗 (${expRes.status})`);
      const expData = await expRes.json();
      const total = Object.values(expData.tables||{}).reduce((s,r)=>s+r.length,0);
      slog(`取得: ${total}件`, 'ok'); prog(50);
      slog(`${dstLbl}へインポート中…`, 'info');
      const impRes = await fetch(`${dstApi}?action=import&token=${TOKEN}`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(expData),
      });
      if (!impRes.ok) throw new Error(`インポート失敗 (${impRes.status})`);
      const impData = await impRes.json();
      if (!impData.success) throw new Error(impData.error||'インポートエラー');
      prog(100);
      slog(`✅ 完了（${srcLbl}→${dstLbl}）`, 'ok');

    } else { // merge
      slog('両方からエクスポート中…', 'info');
      const [locExp, remExp] = await Promise.all([
        fetch(`${LOCAL_API}?action=export&token=${TOKEN}`).then(r=>{ if(!r.ok) throw new Error('ローカルexport失敗'); return r.json(); }),
        fetch(`${REMOTE_API}?action=export&token=${TOKEN}`).then(r=>{ if(!r.ok) throw new Error('サーバーexport失敗'); return r.json(); }),
      ]);
      prog(35); slog('マージ計算中…', 'info');
      const mergeRes = await fetch(`${LOCAL_API}?action=merge&token=${TOKEN}`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({local: locExp, remote: remExp}),
      });
      if (!mergeRes.ok) throw new Error('マージ失敗');
      const mergeData = await mergeRes.json();
      if (!mergeData.success) throw new Error(mergeData.error||'マージエラー');
      prog(60); slog('両方にインポート中…', 'info');
      const [lImp, rImp] = await Promise.all([
        fetch(`${LOCAL_API}?action=import&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(mergeData.merged)}).then(r=>r.json()),
        fetch(`${REMOTE_API}?action=import&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(mergeData.merged)}).then(r=>r.json()),
      ]);
      if (!lImp.success) throw new Error('ローカルimport: '+(lImp.error||''));
      if (!rImp.success) throw new Error('サーバーimport: '+(rImp.error||''));
      prog(100);
      const stats = mergeData.stats||{};
      const added = Object.values(stats).reduce((s,v)=>s+(v.local_only||0)+(v.remote_only||0),0);
      const upd   = Object.values(stats).reduce((s,v)=>s+(v.local_wins||0)+(v.remote_wins||0),0);
      slog(`✅ マージ完了（追加:${added}件 更新:${upd}件）`, 'ok');
    }
  } catch(e) {
    slog('❌ '+e.message, 'err'); prog(0);
  } finally {
    setBtns(false);
    setTimeout(()=>prog(0), 2000);
  }
};

function normalizeType(t){
  return t.replace(/\b(int|bigint|smallint|mediumint)\(\d+\)/gi,'$1')
          .replace(/\btinyint\((?!1\b)\d+\)/gi,'tinyint')
          .toLowerCase().trim();
}
let _showVerDiff=true;
window.toggleVerDiff=function(){
  _showVerDiff=!_showVerDiff;
  const btn=document.getElementById('verDiffToggle');
  if(btn)btn.textContent=_showVerDiff?'🔽 バージョン差の違いを表示しない':'🔼 バージョン差の違いを表示する';
  window._redrawSchema&&window._redrawSchema();
};

window.doSchemaSync=async function(){
  if(location.hostname!=='localhost'&&location.hostname!=='127.0.0.1'){
    alert('スキーマ同期はローカルPC（localhost）から実行してください。');return;
  }
  const schemaRes=document.getElementById('schemaRes');
  const detail=document.getElementById('schemaDetail');
  schemaRes.style.display='none';
  slog('スキーマ取得中…','info');
  try{
    const[lS,rS]=await Promise.all([
      fetch(`${LOCAL_API}?action=schema&token=${TOKEN}`).then(r=>r.json()),
      fetch(`${REMOTE_API}?action=schema&token=${TOKEN}`).then(r=>r.json()),
    ]);
    if(!lS.success||!rS.success) throw new Error('スキーマ取得失敗');

    const sqlDef=(v)=>{
      if(v===null) return '';
      if(/^(CURRENT_TIMESTAMP|NOW\(\)|NULL|CURRENT_DATE|CURRENT_TIME)$/i.test(v.trim())) return ` DEFAULT ${v}`;
      return ` DEFAULT '${v}'`;
    };
    const buildSql=(tbl,verb,col,info)=>{
      const nullable=info.null==='YES';
      const def=info.default!==null?sqlDef(info.default):(nullable?' DEFAULT NULL':'');
      const nullStr=nullable?' NULL':' NOT NULL';
      const extra=(info.extra||'').replace(/DEFAULT_GENERATED\s*/gi,'').trim();
      return `ALTER TABLE \`${tbl}\` ${verb} \`${col}\` ${info.type}${nullStr}${def}${extra?` ${extra}`:''}`;
    };

    const alters=[],diffRows=[],typeDiffLocal=[],typeDiffLocalAlters=[];
    for(const[tbl,rCols] of Object.entries(rS.schema)){
      const lCols=lS.schema[tbl]||{};
      for(const[col,info] of Object.entries(rCols)){
        if(!(col in lCols)){
          alters.push(buildSql(tbl,'ADD COLUMN',col,info));
          diffRows.push({tbl,col,type:info.type});
        } else if(lCols[col].type!==info.type){
          const verOnly=(normalizeType(lCols[col].type)===normalizeType(info.type));
          typeDiffLocal.push({tbl,col,localType:lCols[col].type,remoteType:info.type,verOnly});
          typeDiffLocalAlters.push({sql:buildSql(tbl,'MODIFY COLUMN',col,info),verOnly});
        }
      }
    }
    const svrMissing=[],svrAlters=[],typeDiffRemote=[],typeDiffRemoteAlters=[];
    for(const[tbl,lCols] of Object.entries(lS.schema)){
      const rCols=rS.schema[tbl]||{};
      for(const[col,info] of Object.entries(lCols)){
        if(!(col in rCols)){
          svrMissing.push({tbl,col,type:info.type});
          svrAlters.push(buildSql(tbl,'ADD COLUMN',col,info));
        } else if(rCols[col].type!==info.type&&!typeDiffLocal.find(d=>d.tbl===tbl&&d.col===col)){
          const verOnly=(normalizeType(info.type)===normalizeType(rCols[col].type));
          typeDiffRemote.push({tbl,col,localType:info.type,remoteType:rCols[col].type,verOnly});
          typeDiffRemoteAlters.push({sql:buildSql(tbl,'MODIFY COLUMN',col,info),verOnly});
        }
      }
    }

    const wBox=(msg)=>`<div style="background:#fff7ed;border:1px solid #fdba74;border-radius:5px;padding:7px 10px;font-size:.72rem;color:#92400e;margin-bottom:7px;">⚠️ <strong>警告：</strong>${msg}</div>`;
    const dBox=(msg)=>`<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:5px;padding:7px 10px;font-size:.72rem;color:#991b1b;margin-bottom:7px;">🚨 <strong>危険：</strong>${msg}</div>`;
    const th=(cols)=>`<table style="width:100%;border-collapse:collapse;font-size:.71rem;margin-bottom:7px;"><tr style="font-weight:700;color:#555;">${cols.map(c=>`<td>${c}</td>`).join('')}</tr>`;
    const verBadge='<span style="font-size:.65rem;background:#e0e7ff;color:#3730a3;padding:1px 4px;border-radius:3px;">バージョン差</span>';

    async function fetchJson(url,opts){
      const r=await fetch(url,opts);const txt=await r.text();
      try{return JSON.parse(txt);}catch(e){throw new Error('APIエラー:\n'+txt.replace(/<[^>]+>/g,'').trim().substring(0,200));}
    }
    window._pendingLocalAlters=null; window._pendingRemoteAlters=null;

    window._redrawSchema=function(){
      const showVer=_showVerDiff;
      const visLT=typeDiffLocal.filter(r=>showVer||!r.verOnly);
      const visRT=typeDiffRemote.filter(r=>showVer||!r.verOnly);
      const visLA=typeDiffLocalAlters.filter(r=>showVer||!r.verOnly).map(r=>r.sql);
      const visRA=typeDiffRemoteAlters.filter(r=>showVer||!r.verOnly).map(r=>r.sql);
      window._pendingLocalAlters=visLA; window._pendingRemoteAlters=visRA;
      const verCnt=typeDiffLocal.filter(r=>r.verOnly).length+typeDiffRemote.filter(r=>r.verOnly).length;
      const total=alters.length+svrMissing.length+visLT.length+visRT.length;
      let h='';
      if(verCnt>0){
        h+=`<div style="margin-bottom:8px;text-align:right;">`;
        h+=`<button id="verDiffToggle" onclick="toggleVerDiff()" style="background:#e2e8f0;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;font-size:.7rem;color:#475569;">${showVer?'🔽 バージョン差の違いを表示しない':'🔼 バージョン差の違いを表示する'}</button>`;
        if(!showVer) h+=`<span style="font-size:.68rem;color:#94a3b8;margin-left:6px;">(バージョン差 ${verCnt}件 を非表示中)</span>`;
        h+=`</div>`;
      }
      if(total===0){
        const msg=verCnt>0&&!showVer?'実質的な差分はありません。':'ローカルとサーバーのテーブル構造は同じです。';
        detail.innerHTML=h+`<span style="color:#059669;">${msg}</span>`;
        schemaRes.style.display='block';slog('✅ スキーマ確認完了','ok');return;
      }
      if(alters.length>0){
        h+=`<p style="margin:0 0 4px;color:#0f766e;font-weight:700;">① ローカルに不足しているカラム（${alters.length}件）</p>`;
        h+=th(['テーブル','カラム名','型']);
        diffRows.forEach(r=>{h+=`<tr><td>${r.tbl}</td><td style="color:#0f766e">${r.col}</td><td>${r.type}</td></tr>`;});
        h+=`</table><button onclick="applySchemaLocal()" style="background:#0f766e;color:#fff;border:none;border-radius:5px;padding:5px 14px;cursor:pointer;font-size:.76rem;margin-bottom:12px;">🗂️ ローカルに追加する</button>`;
      }
      if(svrMissing.length>0){
        h+=`<p style="margin:4px 0 4px;color:#b45309;font-weight:700;">② サーバーに不足しているカラム（${svrMissing.length}件）</p>`;
        h+=th(['テーブル','カラム名','型']);
        svrMissing.forEach(r=>{h+=`<tr><td>${r.tbl}</td><td style="color:#b45309">${r.col}</td><td>${r.type}</td></tr>`;});
        h+=`</table>`+wBox('サーバーのテーブル構造を変更します。実行前にバックアップを推奨します。');
        h+=`<button onclick="applySchemaRemote()" style="background:#b45309;color:#fff;border:none;border-radius:5px;padding:5px 14px;cursor:pointer;font-size:.76rem;margin-bottom:12px;">⚠️ サーバーに追加する</button>`;
      }
      if(visLT.length>0){
        h+=`<p style="margin:4px 0 4px;color:#7c3aed;font-weight:700;">③ 型が異なるカラム（ローカルをサーバーに合わせる：${visLT.length}件）</p>`;
        h+=th(['テーブル','カラム名','ローカル現在','→ サーバー基準','']);
        visLT.forEach(r=>{h+=`<tr><td>${r.tbl}</td><td>${r.col}</td><td style="color:#dc2626">${r.localType}</td><td style="color:#7c3aed">${r.remoteType}</td><td>${r.verOnly?verBadge:''}</td></tr>`;});
        h+=`</table>`+dBox('型を変更するとデータが変換・切り捨てられる場合があります。必ずバックアップを取ってから実行してください。');
        h+=`<button onclick="applyTypeLocal(window._pendingLocalAlters)" style="background:#7c3aed;color:#fff;border:none;border-radius:5px;padding:5px 14px;cursor:pointer;font-size:.76rem;margin-bottom:12px;">🚨 ローカルの型を変更する</button>`;
      }
      if(visRT.length>0){
        h+=`<p style="margin:4px 0 4px;color:#dc2626;font-weight:700;">④ 型が異なるカラム（サーバーをローカルに合わせる：${visRT.length}件）</p>`;
        h+=th(['テーブル','カラム名','サーバー現在','→ ローカル基準','']);
        visRT.forEach(r=>{h+=`<tr><td>${r.tbl}</td><td>${r.col}</td><td style="color:#dc2626">${r.remoteType}</td><td style="color:#7c3aed">${r.localType}</td><td>${r.verOnly?verBadge:''}</td></tr>`;});
        h+=`</table>`+dBox('型を変更するとデータが変換・切り捨てられる場合があります。必ずバックアップを取ってから実行してください。');
        h+=`<button onclick="applyTypeRemote(window._pendingRemoteAlters)" style="background:#dc2626;color:#fff;border:none;border-radius:5px;padding:5px 14px;cursor:pointer;font-size:.76rem;margin-bottom:4px;">🚨 サーバーの型を変更する</button>`;
      }
      detail.innerHTML=h; schemaRes.style.display='block';
      slog('✅ スキーマ確認完了','ok');
    };
    window._redrawSchema();

    window.applySchemaLocal=async function(){
      if(!confirm(`ローカルDB に ${alters.length} 件のカラムを追加します。よろしいですか？`))return;
      slog('ローカルにカラム追加中…','info');
      try{const res=await fetchJson(`${LOCAL_API}?action=schema_apply&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alters})});
        const ok=res.results.filter(r=>r.ok).length,ng=res.results.filter(r=>!r.ok);
        slog(`✅ ローカル：${ok}件追加完了`+(ng.length?` / ❌ ${ng.length}件失敗`:''),'ok');
        ng.forEach(r=>slog(`  ❌ ${r.sql}: ${r.error}`,'err'));
      }catch(e){slog('❌ '+e.message,'err');}
    };
    window.applySchemaRemote=async function(){
      if(!confirm(`サーバーDB に ${svrMissing.length} 件のカラムを追加します。\nバックアップ推奨。よろしいですか？`))return;
      slog('サーバーにカラム追加中…','info');
      try{const res=await fetchJson(`${REMOTE_API}?action=schema_apply&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alters:svrAlters})});
        const ok=res.results.filter(r=>r.ok).length,ng=res.results.filter(r=>!r.ok);
        slog(`✅ サーバー：${ok}件追加完了`+(ng.length?` / ❌ ${ng.length}件失敗`:''),'ok');
        ng.forEach(r=>slog(`  ❌ ${r.sql}: ${r.error}`,'err'));
      }catch(e){slog('❌ '+e.message,'err');}
    };
    window.applyTypeLocal=async function(sqls){
      if(!confirm(`【危険】ローカルDB の ${sqls.length} 件の型を変更します。\nデータが変換・切り捨てされる可能性があります。\nバックアップを強く推奨します。\n本当に実行しますか？`))return;
      slog('ローカルの型変更中…','info');slog('実行SQL: '+sqls.join(' / '),'info');
      try{const res=await fetchJson(`${LOCAL_API}?action=schema_apply&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alters:sqls})});
        const ok=res.results.filter(r=>r.ok).length,ng=res.results.filter(r=>!r.ok);
        slog(`✅ ローカル型変更：${ok}件完了`+(ng.length?` / ❌ ${ng.length}件失敗`:''),'ok');
        ng.forEach(r=>slog(`  ❌ ${r.sql}: ${r.error}`,'err'));
      }catch(e){slog('❌ '+e.message,'err');}
    };
    window.applyTypeRemote=async function(sqls){
      if(!confirm(`【危険】サーバーDB の ${sqls.length} 件の型を変更します。\nデータが変換・切り捨てされる可能性があります。\nバックアップを強く推奨します。\n本当に実行しますか？`))return;
      slog('サーバーの型変更中…','info');slog('実行SQL: '+sqls.join(' / '),'info');
      try{const res=await fetchJson(`${REMOTE_API}?action=schema_apply&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alters:sqls})});
        const ok=res.results.filter(r=>r.ok).length,ng=res.results.filter(r=>!r.ok);
        slog(`✅ サーバー型変更：${ok}件完了`+(ng.length?` / ❌ ${ng.length}件失敗`:''),'ok');
        ng.forEach(r=>slog(`  ❌ ${r.sql}: ${r.error}`,'err'));
      }catch(e){slog('❌ '+e.message,'err');}
    };
  }catch(e){slog('❌ '+e.message,'err');}
};
})();

function toggleKebab(e){e.stopPropagation();document.getElementById('kebabDropdown').classList.toggle('open');}
document.addEventListener('click',function(){const d=document.getElementById('kebabDropdown');if(d)d.classList.remove('open');});
</script>
</body>
</html>
