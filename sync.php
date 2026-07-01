<?php
require_once 'config.php';
requireLogin();
sendSecurityHeaders();
$conn = getDB();
$firstSid = $conn->query("SELECT student_id FROM students ORDER BY class_name,seat_number,student_id LIMIT 1")->fetch_assoc()['student_id'] ?? '';
$conn->close();
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="/karte/favicon.php">
  <link rel="icon" type="image/png" sizes="32x32" href="/karte/icon-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/karte/icon-180.png">
  <link rel="manifest" href="/karte/manifest.json">
  <meta name="theme-color" content="#1a2a55">
<title>DB同期 — カルテ</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Hiragino Kaku Gothic ProN',Meiryo,sans-serif;background:#f0f2f8;color:#1a2240;min-height:100vh;}
.topbar{background:linear-gradient(135deg,#3b4f8a,#263570);color:#fff;padding:14px 24px;display:flex;align-items:center;gap:16px;}
.topbar a{color:#ccd3f0;text-decoration:none;font-size:.88rem;}
.topbar a:hover{color:#fff;}
.topbar-title{font-size:1.1rem;font-weight:700;}
.kebab-menu{position:relative;margin-left:auto;}
.kebab-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);color:#e8ecff;border-radius:6px;padding:6px 10px;cursor:pointer;display:flex;flex-direction:column;gap:4px;align-items:center;justify-content:center;width:38px;height:34px;}
.kebab-btn span{display:block;width:18px;height:2px;background:#e8ecff;border-radius:1px;}
.kebab-btn:hover{background:rgba(255,255,255,.25);}
.kebab-dropdown{display:none;position:absolute;top:calc(100% + 6px);right:0;background:linear-gradient(180deg,#2c3e6b,#1a2a55);border:1px solid rgba(255,255,255,.2);border-radius:8px;min-width:170px;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,.4);overflow:hidden;}
.kebab-dropdown.open{display:block;}
.kebab-dropdown a{display:block;width:100%;padding:10px 16px;color:#e8ecff;text-decoration:none;font-size:.85rem;border-bottom:1px solid rgba(255,255,255,.08);}
.kebab-dropdown a:last-child{border-bottom:none;}
.kebab-dropdown a:hover{background:rgba(255,255,255,.15);}
.kebab-dropdown .current-page{color:#6a7a99;cursor:default;pointer-events:none;}
.kebab-dropdown .current-page:hover{background:none;}
.container{max-width:860px;margin:32px auto;padding:0 16px;}
h2{font-size:1.15rem;font-weight:700;color:#3b4f8a;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #c8d0ea;}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(60,80,140,.10);padding:24px;margin-bottom:24px;}
.status-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:8px;}
.status-box{background:#f5f7ff;border-radius:8px;padding:16px;border:1px solid #c8d0ea;}
.status-box h3{font-size:.88rem;font-weight:700;color:#3b4f8a;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.env-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:700;}
.env-local {background:#d1fae5;color:#065f46;}
.env-remote{background:#dbeafe;color:#1e3a8a;}
.status-table{width:100%;font-size:.8rem;border-collapse:collapse;}
.status-table td{padding:3px 6px;border-bottom:1px solid #e8eaf0;}
.status-table td:last-child{text-align:right;font-weight:700;color:#3b4f8a;}
.status-footer{font-size:.75rem;color:#888;margin-top:8px;}
.sync-btns{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin:20px 0 8px;}
.sync-btn{padding:18px;border:none;border-radius:8px;cursor:pointer;font-size:.95rem;font-weight:700;font-family:inherit;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:6px;}
.sync-btn .icon{font-size:2rem;}
.sync-btn .sub{font-size:.75rem;font-weight:400;opacity:.85;}
.btn-download{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;}
.btn-download:hover{background:linear-gradient(135deg,#60a5fa,#3b82f6);}
.btn-merge   {background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;}
.btn-merge:hover {background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.btn-upload  {background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;}
.btn-upload:hover{background:linear-gradient(135deg,#fbbf24,#f59e0b);}
.merge-stats{background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:14px 18px;margin-top:12px;display:none;}
.merge-stats h4{font-size:.88rem;font-weight:700;color:#5b21b6;margin-bottom:10px;}
.merge-stats table{width:100%;font-size:.8rem;border-collapse:collapse;}
.merge-stats td{padding:3px 8px;border-bottom:1px solid #ede9fe;}
.merge-stats td:not(:first-child){text-align:right;}
.merge-stats .col-h{font-weight:700;color:#555;font-size:.75rem;}
.sync-btn:disabled{opacity:.5;cursor:not-allowed;}
.warn-box{background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:12px 16px;font-size:.82rem;color:#9a3412;margin-bottom:16px;}
.log-box{background:#1e2340;color:#a0f0b0;border-radius:8px;padding:16px;font-family:monospace;font-size:.82rem;min-height:120px;max-height:260px;overflow-y:auto;white-space:pre-wrap;}
.log-box .err{color:#ff8080;}
.log-box .ok {color:#7fffaa;}
.log-box .info{color:#80d0ff;}
.progress{height:6px;background:#e0e4f0;border-radius:3px;overflow:hidden;margin:12px 0;display:none;}
.progress-bar{height:100%;background:linear-gradient(90deg,#3b82f6,#60a5fa);width:0;transition:width .4s;}
.last-sync{font-size:.78rem;color:#666;text-align:center;margin-top:8px;}
/* cron info */
.cron-code{background:#1e2340;color:#a0f0b0;border-radius:6px;padding:12px;font-family:monospace;font-size:.82rem;margin:10px 0;}
.step{display:flex;gap:12px;margin-bottom:12px;align-items:flex-start;}
.step-num{background:#3b4f8a;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;margin-top:2px;}
.step-body{font-size:.85rem;line-height:1.6;}
</style>
</head>
<body>
<div class="topbar">
  <span class="topbar-title">🔄 データベース同期</span>
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
      <a href="/karte/backup.php">🗄️ バックアップ</a>
      <a class="current-page">🔄 DB同期</a>
      <a href="/karte/account.php">⚙ アカウント</a>
      <a href="/karte/logout.php">🚪 ログアウト</a>
    </div>
  </div>
</div>

<div class="container">

  <!-- ステータス -->
  <div class="card">
    <h2>現在の状態</h2>
    <div class="status-grid">
      <div class="status-box">
        <h3>🏠 ローカル（家のPC）<span class="env-badge env-local">LOCAL</span></h3>
        <table class="status-table" id="localStatus"><tr><td colspan="2">読み込み中…</td></tr></table>
        <div class="status-footer" id="localTime"></div>
      </div>
      <div class="status-box">
        <h3>🌐 サーバー（本番）<span class="env-badge env-remote">REMOTE</span></h3>
        <table class="status-table" id="remoteStatus"><tr><td colspan="2">読み込み中…</td></tr></table>
        <div class="status-footer" id="remoteTime"></div>
      </div>
    </div>
    <button onclick="loadStatus()" style="font-size:.8rem;padding:5px 14px;border:1px solid #c8d0ea;background:#f5f7ff;border-radius:5px;cursor:pointer;">🔃 更新</button>
  </div>

  <!-- 同期ボタン -->
  <div class="card">
    <h2>同期操作</h2>
    <div class="warn-box">
      ⚠️ 同期すると<strong>相手側のデータが完全に上書き</strong>されます。<br>
      どちらか一方で作業してから同期してください。
    </div>
    <div class="sync-btns">
      <button class="sync-btn btn-download" id="btnDownload" onclick="confirmSync('download')">
        <span class="icon">⬇️</span>
        <span>サーバー → ローカル</span>
        <span class="sub">本番データで家のPCを上書き</span>
      </button>
      <button class="sync-btn btn-merge" id="btnMerge" onclick="confirmSync('merge')">
        <span class="icon">🔀</span>
        <span>マージ同期</span>
        <span class="sub">更新日時を比較して新しいほうを残す</span>
      </button>
      <button class="sync-btn btn-upload" id="btnUpload" onclick="confirmSync('upload')">
        <span class="icon">⬆️</span>
        <span>ローカル → サーバー</span>
        <span class="sub">家のデータで本番を上書き</span>
      </button>
    </div>
    <div class="progress" id="progress"><div class="progress-bar" id="progressBar"></div></div>
    <div class="merge-stats" id="mergeStats">
      <h4>🔀 マージ結果</h4>
      <table id="mergeStatsTable"></table>
    </div>
    <div class="last-sync" id="lastSyncInfo"></div>
  </div>

  <!-- ログ -->
  <div class="card">
    <h2>実行ログ</h2>
    <div class="log-box" id="logBox">同期ログがここに表示されます。</div>
  </div>

  <!-- スキーマ同期 -->
  <div class="card" id="schemaSyncCard">
    <h2>🗂️ スキーマ同期</h2>
    <div id="syncRemoteWarnSchema" style="display:none;background:#fff7ed;border:1px solid #fcd34d;border-radius:6px;padding:10px 14px;font-size:.82rem;color:#92400e;margin-bottom:14px;">
      ⚠️ サーバー上で実行しています。<strong>スキーマ同期はローカルPC（localhost）から実行してください。</strong>
    </div>
    <p style="font-size:.83rem;color:#555;margin-bottom:14px;">ローカルとサーバーのテーブル構造を比較し、不足カラムの追加・型の修正を行います。</p>
    <button id="schemaSyncBtn" onclick="doSchemaSync()" style="background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;border:none;border-radius:7px;padding:10px 22px;cursor:pointer;font-size:.9rem;font-weight:700;">🗂️ スキーマ確認・同期</button>
    <div id="schemaRes" style="display:none;margin-top:16px;background:#f8fffe;border:1px solid #99f6e4;border-radius:8px;padding:14px;">
      <div id="schemaDetail"></div>
    </div>
  </div>

  <!-- ファイル比較 -->
  <div class="card">
    <h2>📁 ファイル比較</h2>
    <div id="fileCompareWarn" style="display:none;background:#fff7ed;border:1px solid #fcd34d;border-radius:6px;padding:10px 14px;font-size:.82rem;color:#92400e;margin-bottom:14px;">
      ⚠️ サーバー上では比較できません。<strong>ローカルPC（localhost）から実行してください。</strong>
    </div>
    <p style="font-size:.83rem;color:#555;margin-bottom:14px;">ローカルとサーバーのファイル（PHP/JS/CSS/HTML）を比較し、差分をSCPコマンドで反映できます。<br>対象外：photos / uploads / sync フォルダ</p>
    <button id="fileCompareBtn" onclick="doFileCompare()" style="background:linear-gradient(135deg,#0369a1,#0284c7);color:#fff;border:none;border-radius:7px;padding:10px 22px;cursor:pointer;font-size:.9rem;font-weight:700;">📁 ファイル比較を実行</button>
    <div id="fileCompareRes" style="display:none;margin-top:16px;"></div>
  </div>

  <!-- GitHub同期 -->
  <div class="card">
    <h2>🐙 GitHub同期</h2>
    <div id="gitWarn" style="display:none;background:#fff7ed;border:1px solid #fcd34d;border-radius:6px;padding:10px 14px;font-size:.82rem;color:#92400e;margin-bottom:14px;">
      ⚠️ GitHub同期はローカルPC（localhost）からのみ実行できます。
    </div>
    <div id="gitStatusBox" style="background:#f5f7ff;border:1px solid #c8d0ea;border-radius:8px;padding:12px 16px;margin-bottom:14px;font-size:.83rem;">読み込み中…</div>
    <div style="display:flex;gap:10px;margin-bottom:12px;align-items:center;flex-wrap:wrap;">
      <input type="text" id="gitMsgInput" placeholder="コミットメッセージ（省略時は自動生成）"
        style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #c8d0ea;border-radius:6px;font-size:.85rem;font-family:inherit;">
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
      <button id="gitPullBtn" onclick="doGitPull()"
        style="background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;border:none;border-radius:7px;padding:10px 20px;cursor:pointer;font-size:.9rem;font-weight:700;">⬇️ Pull（GitHubから取得）</button>
      <button id="gitPushBtn" onclick="doGitPush()"
        style="background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;border:none;border-radius:7px;padding:10px 20px;cursor:pointer;font-size:.9rem;font-weight:700;">⬆️ Push（GitHubへプッシュ）</button>
      <button onclick="loadGitStatus()"
        style="font-size:.8rem;padding:8px 14px;border:1px solid #c8d0ea;background:#f5f7ff;border-radius:5px;cursor:pointer;">🔃 状態更新</button>
    </div>
    <div class="log-box" id="gitLogBox" style="display:none;min-height:80px;"></div>
    <div style="margin-top:14px;background:#f5f7ff;border:1px solid #c8d0ea;border-radius:7px;padding:12px 16px;font-size:.8rem;color:#555;">
      <strong style="color:#3b4f8a;">💡 認証エラー時の対処：PATをURLに埋め込む（一度だけ実行）</strong>
      <ol style="margin:8px 0 4px 16px;padding:0;line-height:1.9;">
        <li>GitHubで <strong>Settings → Developer settings → Personal access tokens → Tokens (classic)</strong> からPAT（Contents書き込み権限）を発行</li>
        <li>PowerShellで以下を実行（YOUR_PATを置き換え）：</li>
      </ol>
      <div style="position:relative;margin-top:4px;">
        <pre id="kartePushCmd" style="background:#1e2340;color:#a0f0b0;border-radius:5px;padding:8px 12px;font-size:.78rem;margin:0;white-space:pre-wrap">git -C C:\xampp\htdocs\karte remote set-url origin https://YOUR_PAT@github.com/joom31628103/karte.git
git -C C:\xampp\htdocs\karte push</pre>
        <button onclick="navigator.clipboard.writeText(document.getElementById('kartePushCmd').textContent).then(()=>alert('コピーしました！'))" style="position:absolute;top:4px;right:4px;background:#2d3748;color:#a0f0b0;border:1px solid #4a5568;border-radius:4px;padding:2px 10px;font-size:.7rem;cursor:pointer">コピー</button>
      </div>
      <div style="margin-top:6px;color:#888;font-size:.75rem;">PATをURLに埋め込むと次回以降WebUIのPushボタンも認証なしで使えます。</div>
    </div>
  </div>

  <!-- 方法③：自動同期の説明 -->
  <div class="card">
    <h2>③ 自動同期の設定（Windowsタスクスケジューラ）</h2>
    <p style="font-size:.85rem;color:#555;margin-bottom:16px;">
      PC起動時やログオン時に自動でサーバー→ローカルの同期を実行します。
    </p>
    <div class="step">
      <div class="step-num">1</div>
      <div class="step-body">
        <strong>PowerShellスクリプトを配置</strong><br>
        <code>C:\xampp\htdocs\karte\sync\karte_sync.ps1</code> が自動同期スクリプトです。
      </div>
    </div>
    <div class="step">
      <div class="step-num">2</div>
      <div class="step-body">
        <strong>タスクスケジューラで登録</strong><br>
        スタートメニュー →「タスクスケジューラ」→「基本タスクの作成」<br>
        トリガー：<strong>ログオン時</strong> / 操作：<strong>プログラムの開始</strong><br>
        プログラム：<code>powershell.exe</code><br>
        引数：<code>-ExecutionPolicy Bypass -File "C:\xampp\htdocs\karte\sync\karte_sync.ps1" -mode auto</code>
      </div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-body">
        <strong>動作確認</strong><br>
        PCを再起動してログオン後、このページのローカルのデータが更新されていれば成功です。
      </div>
    </div>
  </div>

</div>

<script>
const LOCAL_API  = '/karte/api/sync.php';
const REMOTE_API = '<?= REMOTE_URL ?>/karte/api/sync.php';
const TOKEN      = '<?= SYNC_TOKEN ?>';

const tableLabels = {
  students:'生徒', karte_records:'面談記録', karte_attendance:'出欠',
  karte_interviews:'面談', gakuseki:'学籍台帳', student_nendo:'年度情報', teachers:'教師'
};

function log(msg, type='') {
  const box = document.getElementById('logBox');
  const ts  = new Date().toLocaleTimeString('ja-JP');
  box.innerHTML += `\n<span class="${type}">[${ts}] ${msg}</span>`;
  box.scrollTop = box.scrollHeight;
}

function setProgress(pct) {
  const p = document.getElementById('progress');
  p.style.display = pct>0&&pct<100 ? 'block' : pct>=100 ? 'none' : 'none';
  document.getElementById('progressBar').style.width = pct+'%';
}

async function loadStatus() {
  const render = (elId, timeId, data) => {
    if (!data?.success) {
      document.getElementById(elId).innerHTML = '<tr><td class="err">接続失敗</td></tr>';
      return;
    }
    const rows = Object.entries(data.counts)
      .map(([k,v]) => `<tr><td>${tableLabels[k]||k}</td><td>${v.toLocaleString()} 件</td></tr>`)
      .join('');
    document.getElementById(elId).innerHTML = rows;
    const t = data.last_updated ? new Date(data.last_updated).toLocaleString('ja-JP') : '—';
    document.getElementById(timeId).textContent = `最終更新：${t}`;
  };

  try {
    const [loc, rem] = await Promise.all([
      fetch(`${LOCAL_API}?action=status&token=${TOKEN}`).then(r=>r.json()).catch(()=>null),
      fetch(`${REMOTE_API}?action=status&token=${TOKEN}`).then(r=>r.json()).catch(()=>null),
    ]);
    render('localStatus',  'localTime',  loc);
    render('remoteStatus', 'remoteTime', rem);
  } catch(e) {
    log('ステータス取得エラー: '+e.message, 'err');
  }
}

async function confirmSync(dir) {
  const msgs = {
    download: '⬇️ サーバー → ローカルに同期します。\nローカルのデータが上書きされます。よろしいですか？',
    upload:   '⬆️ ローカル → サーバーに同期します。\nサーバーのデータが上書きされます。よろしいですか？',
    merge:    '🔀 マージ同期を実行します。\n更新日時を比較して新しいほうのデータを両方に反映します。\nよろしいですか？',
  };
  if (!confirm(msgs[dir])) return;
  document.getElementById('mergeStats').style.display = 'none';
  if (dir === 'merge') await doMerge();
  else await doSync(dir);
}

async function doSync(dir) {
  const btn = document.getElementById(dir==='download'?'btnDownload':'btnUpload');
  btn.disabled = true;
  setProgress(10);
  document.getElementById('logBox').textContent = '';

  const srcApi = dir==='download' ? REMOTE_API : LOCAL_API;
  const dstApi = dir==='download' ? LOCAL_API  : REMOTE_API;
  const srcLabel = dir==='download' ? 'サーバー' : 'ローカル';
  const dstLabel = dir==='download' ? 'ローカル' : 'サーバー';

  try {
    log(`${srcLabel}からデータをエクスポート中…`, 'info');
    setProgress(25);
    const expRes = await fetch(`${srcApi}?action=export&token=${TOKEN}`);
    if (!expRes.ok) throw new Error(`エクスポート失敗 (HTTP ${expRes.status})`);
    const expData = await expRes.json();
    if (!expData.tables) throw new Error('エクスポートデータが不正です');

    const totalRows = Object.values(expData.tables).reduce((s,r)=>s+r.length,0);
    log(`エクスポート完了：${totalRows.toLocaleString()} 件取得（${expData.env || '?'} / ${expData.exported_at}）`, 'ok');
    setProgress(55);

    log(`${dstLabel}へインポート中…`, 'info');
    const impRes = await fetch(`${dstApi}?action=import&token=${TOKEN}`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(expData),
    });
    if (!impRes.ok) throw new Error(`インポート失敗 (HTTP ${impRes.status})`);
    const impData = await impRes.json();
    if (!impData.success) throw new Error(impData.error || 'インポートエラー');

    setProgress(100);
    log('インポート完了 内訳:', 'ok');
    Object.entries(impData.imported||{}).forEach(([k,v])=>{
      log(`  ${tableLabels[k]||k}：${v} 件`, 'ok');
    });
    log(`✅ 同期完了！（${srcLabel} → ${dstLabel}）`, 'ok');

    const now = new Date().toLocaleString('ja-JP');
    localStorage.setItem('karte_last_sync', now);
    document.getElementById('lastSyncInfo').textContent = `最終同期：${now}`;
    await loadStatus();

  } catch(e) {
    log('❌ エラー：'+e.message, 'err');
    setProgress(0);
  } finally {
    btn.disabled = false;
    setTimeout(()=>setProgress(0), 1500);
  }
}

async function doMerge() {
  const btn = document.getElementById('btnMerge');
  btn.disabled = true;
  setProgress(10);
  document.getElementById('logBox').textContent = '';

  try {
    // ① 両方からエクスポート
    log('ローカルからエクスポート中…', 'info');
    const [locExp, remExp] = await Promise.all([
      fetch(`${LOCAL_API}?action=export&token=${TOKEN}`).then(r=>{ if(!r.ok) throw new Error('ローカルexport失敗'); return r.json(); }),
      fetch(`${REMOTE_API}?action=export&token=${TOKEN}`).then(r=>{ if(!r.ok) throw new Error('サーバーexport失敗'); return r.json(); }),
    ]);
    log(`エクスポート完了（ローカル: ${Object.values(locExp.tables).reduce((s,r)=>s+r.length,0)}件 / サーバー: ${Object.values(remExp.tables).reduce((s,r)=>s+r.length,0)}件）`, 'ok');
    setProgress(35);

    // ② マージ（ローカルAPIで計算）
    log('マージ計算中…', 'info');
    const mergeRes = await fetch(`${LOCAL_API}?action=merge&token=${TOKEN}`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ local: locExp, remote: remExp }),
    });
    if (!mergeRes.ok) throw new Error('マージ計算失敗');
    const mergeData = await mergeRes.json();
    if (!mergeData.success) throw new Error(mergeData.error || 'マージエラー');

    const totalMerged = Object.values(mergeData.merged.tables).reduce((s,r)=>s+r.length,0);
    log(`マージ完了：合計 ${totalMerged} 件`, 'ok');
    setProgress(55);

    // ③ 両方にインポート
    log('ローカルにインポート中…', 'info');
    const [locImp, remImp] = await Promise.all([
      fetch(`${LOCAL_API}?action=import&token=${TOKEN}`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(mergeData.merged),
      }).then(r=>r.json()),
      fetch(`${REMOTE_API}?action=import&token=${TOKEN}`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(mergeData.merged),
      }).then(r=>r.json()),
    ]);
    if (!locImp.success) throw new Error('ローカルimport失敗: ' + (locImp.error||''));
    if (!remImp.success) throw new Error('サーバーimport失敗: ' + (remImp.error||''));

    setProgress(100);
    log('✅ マージ同期完了！', 'ok');

    // マージ統計を表示
    const stats = mergeData.stats || {};
    const labels = {students:'生徒', karte_records:'面談記録', karte_attendance:'出欠',
                    karte_interviews:'面談', gakuseki:'学籍台帳', student_nendo:'年度情報', teachers:'教師'};
    let html = `<tr class="col-h"><td>テーブル</td><td>合計</td><td>ローカル優先</td><td>サーバー優先</td><td>ローカルのみ</td><td>サーバーのみ</td></tr>`;
    for (const [tbl, s] of Object.entries(stats)) {
      const changed = s.local_wins + s.remote_wins + s.local_only + s.remote_only;
      if (changed === 0) continue;
      html += `<tr><td>${labels[tbl]||tbl}</td><td>${s.total}</td><td>${s.local_wins}</td><td>${s.remote_wins}</td><td>${s.local_only}</td><td>${s.remote_only}</td></tr>`;
    }
    const noChange = Object.values(stats).every(s=>s.local_wins+s.remote_wins+s.local_only+s.remote_only===0);
    if (noChange) html += `<tr><td colspan="6" style="color:#666;text-align:center;padding:8px">差分なし（両方とも同じデータ）</td></tr>`;
    document.getElementById('mergeStatsTable').innerHTML = html;
    document.getElementById('mergeStats').style.display = 'block';

    const now = new Date().toLocaleString('ja-JP');
    localStorage.setItem('karte_last_sync', now);
    document.getElementById('lastSyncInfo').textContent = `最終同期：${now}`;
    await loadStatus();

  } catch(e) {
    log('❌ エラー：'+e.message, 'err');
    setProgress(0);
  } finally {
    btn.disabled = false;
    setTimeout(()=>setProgress(0), 1500);
  }
}

function toggleKebab(e){e.stopPropagation();document.getElementById('kebabDropdown').classList.toggle('open');}
document.addEventListener('click',function(){const d=document.getElementById('kebabDropdown');if(d)d.classList.remove('open');});

// サーバー上での同期ブロック
(function(){
  const isRemote=location.hostname!=='localhost'&&location.hostname!=='127.0.0.1';
  if(isRemote){
    document.getElementById('syncRemoteWarnSchema').style.display='block';
    const btn=document.getElementById('schemaSyncBtn');
    if(btn){btn.disabled=true;btn.style.opacity='0.4';btn.style.cursor='not-allowed';}
    ['btnDownload','btnMerge','btnUpload'].forEach(id=>{
      const b=document.getElementById(id);
      if(b){b.disabled=true;b.style.opacity='0.4';b.style.cursor='not-allowed';}
    });
    // データ同期の warn-box に追記
    const wb=document.querySelector('.warn-box');
    if(wb) wb.innerHTML+='<br><strong style="color:#b91c1c;">⚠️ サーバー上では同期できません。ローカルPC（localhost）から実行してください。</strong>';
  }
})();

// ── スキーマ同期 ────────────────────────────────────────────
function normalizeType(t){
  return t.replace(/\b(int|bigint|smallint|mediumint)\(\d+\)/gi,'$1')
          .replace(/\btinyint\((?!1\b)\d+\)/gi,'tinyint')
          .toLowerCase().trim();
}
let _showVerDiff=true;
function toggleVerDiff(){
  _showVerDiff=!_showVerDiff;
  const btn=document.getElementById('verDiffToggle');
  if(btn) btn.textContent=_showVerDiff?'🔽 バージョン差の違いを表示しない':'🔼 バージョン差の違いを表示する';
  window._redrawSchema&&window._redrawSchema();
}

async function doSchemaSync(){
  if(location.hostname!=='localhost'&&location.hostname!=='127.0.0.1'){
    alert('スキーマ同期はローカルPC（localhost）から実行してください。');return;
  }
  document.getElementById('schemaRes').style.display='none';
  log('スキーマ取得中…','info');
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
          alters.push(buildSql(tbl,'ADD COLUMN',col,info)); diffRows.push({tbl,col,type:info.type});
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
          svrMissing.push({tbl,col,type:info.type}); svrAlters.push(buildSql(tbl,'ADD COLUMN',col,info));
        } else if(rCols[col].type!==info.type&&!typeDiffLocal.find(d=>d.tbl===tbl&&d.col===col)){
          const verOnly=(normalizeType(info.type)===normalizeType(rCols[col].type));
          typeDiffRemote.push({tbl,col,localType:info.type,remoteType:rCols[col].type,verOnly});
          typeDiffRemoteAlters.push({sql:buildSql(tbl,'MODIFY COLUMN',col,info),verOnly});
        }
      }
    }

    async function fetchJson(url,opts){
      const r=await fetch(url,opts);const txt=await r.text();
      try{return JSON.parse(txt);}catch(e){throw new Error('APIエラー:\n'+txt.replace(/<[^>]+>/g,'').trim().substring(0,200));}
    }
    const wBox=(msg)=>`<div style="background:#fff7ed;border:1px solid #fdba74;border-radius:5px;padding:8px 12px;font-size:.8rem;color:#92400e;margin-bottom:8px;">⚠️ <strong>警告：</strong>${msg}</div>`;
    const dBox=(msg)=>`<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:5px;padding:8px 12px;font-size:.8rem;color:#991b1b;margin-bottom:8px;">🚨 <strong>危険：</strong>${msg}</div>`;
    const th=(cols)=>`<table style="width:100%;border-collapse:collapse;font-size:.78rem;margin-bottom:8px;"><tr style="font-weight:700;color:#555;">${cols.map(c=>`<td style="padding:3px 6px">${c}</td>`).join('')}</tr>`;
    const verBadge='<span style="font-size:.68rem;background:#e0e7ff;color:#3730a3;padding:1px 5px;border-radius:3px;">バージョン差</span>';
    window._pendingLocalAlters=null; window._pendingRemoteAlters=null;

    const detail=document.getElementById('schemaDetail');
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
        h+=`<div style="margin-bottom:10px;text-align:right;">`;
        h+=`<button id="verDiffToggle" onclick="toggleVerDiff()" style="background:#e2e8f0;border:none;border-radius:5px;padding:5px 12px;cursor:pointer;font-size:.75rem;color:#475569;">${showVer?'🔽 バージョン差の違いを表示しない':'🔼 バージョン差の違いを表示する'}</button>`;
        if(!showVer) h+=`<span style="font-size:.73rem;color:#94a3b8;margin-left:8px;">(バージョン差 ${verCnt}件 を非表示中)</span>`;
        h+=`</div>`;
      }
      if(total===0){
        const msg=verCnt>0&&!showVer?'実質的な差分はありません。':'ローカルとサーバーのテーブル構造は同じです。';
        detail.innerHTML=h+`<p style="color:#059669;font-weight:700;">✅ ${msg}</p>`;
        document.getElementById('schemaRes').style.display='block';
        log('✅ スキーマ確認完了 — 差分なし','ok'); return;
      }
      if(alters.length>0){
        h+=`<p style="margin:0 0 5px;color:#0f766e;font-weight:700;">① ローカルに不足しているカラム（${alters.length}件）</p>`;
        h+=th(['テーブル','カラム名','型']);
        diffRows.forEach(r=>{h+=`<tr><td style="padding:3px 6px">${r.tbl}</td><td style="padding:3px 6px;color:#0f766e">${r.col}</td><td style="padding:3px 6px">${r.type}</td></tr>`;});
        h+=`</table><button onclick="applySchemaLocal()" style="background:#0f766e;color:#fff;border:none;border-radius:6px;padding:6px 16px;cursor:pointer;font-size:.82rem;margin-bottom:14px;">🗂️ ローカルに追加する</button>`;
      }
      if(svrMissing.length>0){
        h+=`<p style="margin:4px 0 5px;color:#b45309;font-weight:700;">② サーバーに不足しているカラム（${svrMissing.length}件）</p>`;
        h+=th(['テーブル','カラム名','型']);
        svrMissing.forEach(r=>{h+=`<tr><td style="padding:3px 6px">${r.tbl}</td><td style="padding:3px 6px;color:#b45309">${r.col}</td><td style="padding:3px 6px">${r.type}</td></tr>`;});
        h+=`</table>`+wBox('サーバーのテーブル構造を変更します。実行前にバックアップを推奨します。');
        h+=`<button onclick="applySchemaRemote()" style="background:#b45309;color:#fff;border:none;border-radius:6px;padding:6px 16px;cursor:pointer;font-size:.82rem;margin-bottom:14px;">⚠️ サーバーに追加する</button>`;
      }
      if(visLT.length>0){
        h+=`<p style="margin:4px 0 5px;color:#7c3aed;font-weight:700;">③ 型が異なるカラム（ローカルをサーバーに合わせる：${visLT.length}件）</p>`;
        h+=th(['テーブル','カラム名','ローカル現在','→ サーバー基準','']);
        visLT.forEach(r=>{h+=`<tr><td style="padding:3px 6px">${r.tbl}</td><td style="padding:3px 6px">${r.col}</td><td style="padding:3px 6px;color:#dc2626">${r.localType}</td><td style="padding:3px 6px;color:#7c3aed">${r.remoteType}</td><td style="padding:3px 6px">${r.verOnly?verBadge:''}</td></tr>`;});
        h+=`</table>`+dBox('型を変更するとデータが変換・切り捨てられる場合があります。必ずバックアップを取ってから実行してください。');
        h+=`<button onclick="applyTypeLocal(window._pendingLocalAlters)" style="background:#7c3aed;color:#fff;border:none;border-radius:6px;padding:6px 16px;cursor:pointer;font-size:.82rem;margin-bottom:14px;">🚨 ローカルの型を変更する</button>`;
      }
      if(visRT.length>0){
        h+=`<p style="margin:4px 0 5px;color:#dc2626;font-weight:700;">④ 型が異なるカラム（サーバーをローカルに合わせる：${visRT.length}件）</p>`;
        h+=th(['テーブル','カラム名','サーバー現在','→ ローカル基準','']);
        visRT.forEach(r=>{h+=`<tr><td style="padding:3px 6px">${r.tbl}</td><td style="padding:3px 6px">${r.col}</td><td style="padding:3px 6px;color:#dc2626">${r.remoteType}</td><td style="padding:3px 6px;color:#7c3aed">${r.localType}</td><td style="padding:3px 6px">${r.verOnly?verBadge:''}</td></tr>`;});
        h+=`</table>`+dBox('型を変更するとデータが変換・切り捨てられる場合があります。必ずバックアップを取ってから実行してください。');
        h+=`<button onclick="applyTypeRemote(window._pendingRemoteAlters)" style="background:#dc2626;color:#fff;border:none;border-radius:6px;padding:6px 16px;cursor:pointer;font-size:.82rem;">🚨 サーバーの型を変更する</button>`;
      }
      detail.innerHTML=h;
      document.getElementById('schemaRes').style.display='block';
      log('✅ スキーマ確認完了','ok');
    };
    window._redrawSchema();

    window.applySchemaLocal=async function(){
      if(!confirm(`ローカルDB に ${alters.length} 件のカラムを追加します。よろしいですか？`))return;
      log('ローカルにカラム追加中…','info');
      try{const res=await fetchJson(`${LOCAL_API}?action=schema_apply&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alters})});
        const ok=res.results.filter(r=>r.ok).length,ng=res.results.filter(r=>!r.ok);
        log(`✅ ローカル：${ok}件追加完了`+(ng.length?` / ❌ ${ng.length}件失敗`:''),'ok');
        ng.forEach(r=>log(`  ❌ ${r.sql}: ${r.error}`,'err'));
      }catch(e){log('❌ '+e.message,'err');}
    };
    window.applySchemaRemote=async function(){
      if(!confirm(`サーバーDB に ${svrMissing.length} 件のカラムを追加します。\nバックアップ推奨。よろしいですか？`))return;
      log('サーバーにカラム追加中…','info');
      try{const res=await fetchJson(`${REMOTE_API}?action=schema_apply&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alters:svrAlters})});
        const ok=res.results.filter(r=>r.ok).length,ng=res.results.filter(r=>!r.ok);
        log(`✅ サーバー：${ok}件追加完了`+(ng.length?` / ❌ ${ng.length}件失敗`:''),'ok');
        ng.forEach(r=>log(`  ❌ ${r.sql}: ${r.error}`,'err'));
      }catch(e){log('❌ '+e.message,'err');}
    };
    window.applyTypeLocal=async function(sqls){
      if(!confirm(`【危険】ローカルDB の ${sqls.length} 件の型を変更します。\nデータが変換・切り捨てされる可能性があります。\nバックアップを強く推奨します。\n本当に実行しますか？`))return;
      log('ローカルの型変更中… SQL: '+sqls.join(' / '),'info');
      try{const res=await fetchJson(`${LOCAL_API}?action=schema_apply&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alters:sqls})});
        const ok=res.results.filter(r=>r.ok).length,ng=res.results.filter(r=>!r.ok);
        log(`✅ ローカル型変更：${ok}件完了`+(ng.length?` / ❌ ${ng.length}件失敗`:''),'ok');
        ng.forEach(r=>log(`  ❌ ${r.sql}: ${r.error}`,'err'));
      }catch(e){log('❌ '+e.message,'err');}
    };
    window.applyTypeRemote=async function(sqls){
      if(!confirm(`【危険】サーバーDB の ${sqls.length} 件の型を変更します。\nデータが変換・切り捨てされる可能性があります。\nバックアップを強く推奨します。\n本当に実行しますか？`))return;
      log('サーバーの型変更中… SQL: '+sqls.join(' / '),'info');
      try{const res=await fetchJson(`${REMOTE_API}?action=schema_apply&token=${TOKEN}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({alters:sqls})});
        const ok=res.results.filter(r=>r.ok).length,ng=res.results.filter(r=>!r.ok);
        log(`✅ サーバー型変更：${ok}件完了`+(ng.length?` / ❌ ${ng.length}件失敗`:''),'ok');
        ng.forEach(r=>log(`  ❌ ${r.sql}: ${r.error}`,'err'));
      }catch(e){log('❌ '+e.message,'err');}
    };
  }catch(e){log('❌ '+e.message,'err');}
}

// ── GitHub同期 ──────────────────────────────────────────────
(function(){
  const isRemote = location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';
  if (isRemote) {
    const w = document.getElementById('gitWarn');
    if (w) w.style.display = 'block';
    ['gitPullBtn','gitPushBtn'].forEach(id => {
      const b = document.getElementById(id);
      if (b) { b.disabled = true; b.style.opacity = '0.4'; b.style.cursor = 'not-allowed'; }
    });
  }
})();

window.loadGitStatus = async function() {
  const box = document.getElementById('gitStatusBox');
  try {
    const d = await fetch(`${LOCAL_API}?action=git_status&token=${TOKEN}`).then(r => r.json());
    if (!d.success) { box.innerHTML = '<span style="color:#c00">取得失敗</span>'; return; }
    if (!d.initialized) {
      box.innerHTML = `<span style="color:#b45309">⚠️ Gitリポジトリが未初期化です。</span>
        <div style="font-size:.78rem;color:#555;margin-top:6px;">PowerShellで以下を実行してください：<br>
        <code style="background:#1e2340;color:#a0f0b0;padding:4px 8px;border-radius:4px;display:inline-block;margin-top:4px;">cd C:\\xampp\\htdocs\\karte &amp;&amp; git init &amp;&amp; git remote add origin &lt;GitHubリポジトリURL&gt;</code></div>`;
      return;
    }
    const chCount = d.changes.length;
    const chColor = chCount > 0 ? '#dc2626' : '#059669';
    let h = `<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px;">
      <span>🌿 <strong>${d.branch || '(no branch)'}</strong></span>
      <span style="color:${chColor}">📝 未コミット: <strong>${chCount}件</strong></span>
      ${d.remote ? `<span style="font-size:.75rem;color:#3b4f8a;word-break:break-all">🔗 ${d.remote}</span>` : ''}
    </div>`;
    if (chCount > 0) {
      h += `<div style="font-size:.73rem;background:#fef2f2;border-radius:5px;padding:6px 10px;margin-bottom:6px;max-height:100px;overflow-y:auto;">`;
      d.changes.slice(0,15).forEach(c => { h += `<div style="font-family:monospace">${c}</div>`; });
      if (chCount > 15) h += `<div style="color:#888">…他${chCount-15}件</div>`;
      h += `</div>`;
    }
    if (d.log.length > 0) {
      h += `<div style="font-size:.73rem;color:#555;">最近のコミット：`;
      d.log.forEach(l => { h += `<div style="font-family:monospace;color:#3b4f8a">${l}</div>`; });
      h += `</div>`;
    }
    box.innerHTML = h;
  } catch(e) {
    box.innerHTML = '<span style="color:#c00">エラー: ' + e.message + '</span>';
  }
};

function gitLog(msg, type='') {
  const box = document.getElementById('gitLogBox');
  box.style.display = 'block';
  const ts = new Date().toLocaleTimeString('ja-JP');
  box.innerHTML += `\n<span class="${type}">[${ts}] ${msg}</span>`;
  box.scrollTop = box.scrollHeight;
}

window.doGitPull = async function() {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  if (!confirm('⬇️ GitHubから最新のコードを取得します（git pull）。\nローカルの未コミット変更が競合する場合があります。続行しますか？')) return;
  const btn = document.getElementById('gitPullBtn');
  btn.disabled = true;
  document.getElementById('gitLogBox').textContent = '';
  gitLog('git pull 実行中…', 'info');
  try {
    const d = await fetch(`${LOCAL_API}?action=git_pull&token=${TOKEN}`, {
      method: 'POST', headers: {'Content-Type':'application/json'}
    }).then(r => r.json());
    gitLog(d.output || '(出力なし)', d.success ? 'ok' : 'err');
    gitLog(d.success ? '✅ Pull完了' : '❌ Pull失敗', d.success ? 'ok' : 'err');
    if (d.success) await loadGitStatus();
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

window.doGitPush = async function() {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  const msg = document.getElementById('gitMsgInput').value.trim() || ('Update ' + new Date().toLocaleString('ja-JP'));
  if (!confirm(`⬆️ GitHubにプッシュします（git add -A → commit → push）。\nコミットメッセージ：「${msg}」\n続行しますか？`)) return;
  const btn = document.getElementById('gitPushBtn');
  btn.disabled = true;
  document.getElementById('gitLogBox').textContent = '';
  gitLog('プッシュ開始…', 'info');
  try {
    const d = await fetch(`${LOCAL_API}?action=git_push&token=${TOKEN}`, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message: msg})
    }).then(r => r.json());
    (d.steps || []).forEach(s => {
      gitLog(`▶ ${s.label}`, 'info');
      if (s.out) {
        const isErr = /error|fatal/i.test(s.out);
        gitLog(s.out, isErr ? 'err' : 'ok');
      }
    });
    gitLog(d.success ? '✅ Push完了' : '❌ Push失敗（ログを確認してください）', d.success ? 'ok' : 'err');
    if (d.success) { document.getElementById('gitMsgInput').value = ''; await loadGitStatus(); }
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

if (location.hostname === 'localhost' || location.hostname === '127.0.0.1') loadGitStatus();

// ── ファイル比較 ────────────────────────────────────────────
(function(){
  const isRemote=location.hostname!=='localhost'&&location.hostname!=='127.0.0.1';
  if(isRemote){
    const w=document.getElementById('fileCompareWarn');
    if(w) w.style.display='block';
    const b=document.getElementById('fileCompareBtn');
    if(b){b.disabled=true;b.style.opacity='0.4';b.style.cursor='not-allowed';}
  }
})();

window.doFileCompare=async function(){
  if(location.hostname!=='localhost'&&location.hostname!=='127.0.0.1'){
    alert('ファイル比較はローカルPC（localhost）から実行してください。');return;
  }
  const btn=document.getElementById('fileCompareBtn');
  const resDiv=document.getElementById('fileCompareRes');
  btn.disabled=true; resDiv.style.display='none';
  log('ファイルリスト取得中…','info');
  try{
    const[lF,rF]=await Promise.all([
      fetch(`${LOCAL_API}?action=files&token=${TOKEN}`).then(r=>r.json()),
      fetch(`${REMOTE_API}?action=files&token=${TOKEN}`).then(r=>r.json()),
    ]);
    if(!lF.success) throw new Error(`ローカルAPI失敗: ${lF.error||JSON.stringify(lF)}`);
    if(!rF.success) throw new Error(`サーバーAPI失敗: ${rF.error||JSON.stringify(rF)}`);
    const lFiles=lF.files,rFiles=rF.files;
    const allPaths=[...new Set([...Object.keys(lFiles),...Object.keys(rFiles)])].sort();
    const same=[],diffC=[],locOnly=[],svrOnly=[];
    for(const p of allPaths){
      const hasL=p in lFiles,hasR=p in rFiles;
      if(hasL&&hasR){ (lFiles[p].md5===rFiles[p].md5?same:diffC).push(p); }
      else if(hasL){ locOnly.push(p); }
      else{ svrOnly.push(p); }
    }
    log(`✅ 比較完了：一致 ${same.length}件 / 差分 ${diffC.length}件 / ローカルのみ ${locOnly.length}件 / サーバーのみ ${svrOnly.length}件`,'ok');

    const fmt=b=>b<1024?b+'B':(b/1024).toFixed(1)+'KB';
    const fmtD=ts=>ts?new Date(ts*1000).toLocaleString('ja-JP',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}):'—';

    let h=`<div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
      <span style="background:#d1fae5;color:#065f46;padding:3px 12px;border-radius:5px;font-size:.8rem;font-weight:700;">✅ 一致 ${same.length}</span>
      <span style="background:#fef9c3;color:#713f12;padding:3px 12px;border-radius:5px;font-size:.8rem;font-weight:700;">🟡 内容差分 ${diffC.length}</span>
      <span style="background:#fce7f3;color:#9d174d;padding:3px 12px;border-radius:5px;font-size:.8rem;font-weight:700;">🔴 ローカルのみ ${locOnly.length}</span>
      <span style="background:#dbeafe;color:#1e3a8a;padding:3px 12px;border-radius:5px;font-size:.8rem;font-weight:700;">🔵 サーバーのみ ${svrOnly.length}</span>
    </div>`;

    const totalDiff=diffC.length+locOnly.length+svrOnly.length;
    if(totalDiff===0){
      h+='<p style="color:#059669;font-weight:700;">✅ すべてのファイルが一致しています。</p>';
    } else {
      h+=`<table style="width:100%;border-collapse:collapse;font-size:.75rem;margin-bottom:14px;">
        <tr style="font-weight:700;color:#555;background:#f5f7ff;">
          <td style="padding:5px 8px;border-bottom:2px solid #e0e4f0;">ファイル</td>
          <td style="padding:5px 8px;border-bottom:2px solid #e0e4f0;">状態</td>
          <td style="padding:5px 8px;border-bottom:2px solid #e0e4f0;">ローカル</td>
          <td style="padding:5px 8px;border-bottom:2px solid #e0e4f0;">サーバー</td>
        </tr>`;
      const addRow=(paths,label,color,bg)=>paths.forEach(p=>{
        const l=lFiles[p],r=rFiles[p];
        h+=`<tr style="background:${bg}">
          <td style="padding:4px 8px;border-bottom:1px solid #e8eaf0;font-family:monospace;font-size:.72rem">${p}</td>
          <td style="padding:4px 8px;border-bottom:1px solid #e8eaf0;color:${color};font-weight:700;white-space:nowrap">${label}</td>
          <td style="padding:4px 8px;border-bottom:1px solid #e8eaf0;color:#666;font-size:.72rem">${l?fmt(l.size)+'<br>'+fmtD(l.mtime):'—'}</td>
          <td style="padding:4px 8px;border-bottom:1px solid #e8eaf0;color:#666;font-size:.72rem">${r?fmt(r.size)+'<br>'+fmtD(r.mtime):'—'}</td>
        </tr>`;
      });
      addRow(diffC,'内容差分','#92400e','#fffbeb');
      addRow(locOnly,'ローカルのみ','#9d174d','#fff0f9');
      addRow(svrOnly,'サーバーのみ','#1e3a8a','#eff6ff');
      h+='</table>';

      const LR='C:\\xampp\\htdocs\\karte\\', SR='opened@opened.sakura.ne.jp:~/www/karte/';
      const toUp=[...diffC,...locOnly];
      if(toUp.length){
        window._scpUpKarte=toUp.map(p=>`scp -o StrictHostKeyChecking=no "${LR}${p.replace(/\//g,'\\')}" ${SR}${p}`).join('\n');
        h+=`<p style="font-size:.82rem;font-weight:700;color:#92400e;margin:12px 0 5px">⬆️ ローカル→サーバーにアップロード（${toUp.length}件）</p>
        <div style="position:relative">
          <pre style="background:#1e2340;color:#a0f0b0;border-radius:6px;padding:10px 12px 10px 12px;font-size:.7rem;overflow-x:auto;margin:0 0 4px;white-space:pre-wrap;word-break:break-all">${window._scpUpKarte}</pre>
          <button onclick="copyScpKarte('up')" style="position:absolute;top:6px;right:6px;background:#2d3748;color:#a0f0b0;border:1px solid #4a5568;border-radius:4px;padding:2px 10px;font-size:.7rem;cursor:pointer">コピー</button>
        </div>`;
      }
      if(svrOnly.length){
        window._scpDnKarte=svrOnly.map(p=>`scp -o StrictHostKeyChecking=no ${SR}${p} "${LR}${p.replace(/\//g,'\\')}"`).join('\n');
        h+=`<p style="font-size:.82rem;font-weight:700;color:#1e3a8a;margin:12px 0 5px">⬇️ サーバー→ローカルにダウンロード（${svrOnly.length}件）</p>
        <div style="position:relative">
          <pre style="background:#1e2340;color:#80d0ff;border-radius:6px;padding:10px 12px 10px 12px;font-size:.7rem;overflow-x:auto;margin:0 0 4px;white-space:pre-wrap;word-break:break-all">${window._scpDnKarte}</pre>
          <button onclick="copyScpKarte('dn')" style="position:absolute;top:6px;right:6px;background:#2d3748;color:#80d0ff;border:1px solid #4a5568;border-radius:4px;padding:2px 10px;font-size:.7rem;cursor:pointer">コピー</button>
        </div>`;
      }
    }
    resDiv.innerHTML=h; resDiv.style.display='block';
  }catch(e){log('❌ '+e.message,'err');}
  finally{btn.disabled=false;}
};

window.copyScpKarte=function(type){
  const txt=type==='up'?window._scpUpKarte:window._scpDnKarte;
  if(!txt) return;
  if(navigator.clipboard){
    navigator.clipboard.writeText(txt).then(()=>alert('コピーしました！')).catch(()=>prompt('以下をコピーしてください：',txt));
  } else {
    prompt('以下をコピーしてください：',txt);
  }
};

// 初期化
const ls = localStorage.getItem('karte_last_sync');
if (ls) document.getElementById('lastSyncInfo').textContent = `最終同期：${ls}`;
loadStatus();
</script>
</body>
</html>
