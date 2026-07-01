<?php
require_once 'config.php';
requireLogin();
sendSecurityHeaders();
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
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
      <a href="/karte/home.php">🏠 HOME</a>
      <a href="/karte/karte_detail.php">🏫 生徒情報</a>
      <a href="/karte/gakuseki.php">📚 学籍管理</a>
      <a href="/karte/student_manager.php">👥 生徒管理</a>
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

// 初期化
const ls = localStorage.getItem('karte_last_sync');
if (ls) document.getElementById('lastSyncInfo').textContent = `最終同期：${ls}`;
loadStatus();
</script>
</body>
</html>
