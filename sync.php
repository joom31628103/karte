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
.status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:8px;}
.env-github{background:#e0e7ff;color:#3730a3;}
.map-btn{border:none;border-radius:6px;padding:8px 12px;font-size:.76rem;font-weight:700;color:#fff;cursor:pointer;white-space:nowrap;font-family:inherit;}
.map-btn-download{background:linear-gradient(135deg,#3b82f6,#1d4ed8);}
.map-btn-download:hover{background:linear-gradient(135deg,#60a5fa,#3b82f6);}
.map-btn-merge{background:linear-gradient(135deg,#7c3aed,#5b21b6);}
.map-btn-merge:hover{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.map-btn-upload{background:linear-gradient(135deg,#f59e0b,#d97706);}
.map-btn-upload:hover{background:linear-gradient(135deg,#fbbf24,#f59e0b);}
.map-btn:disabled{opacity:.5;cursor:not-allowed;}
.triangle-wrap{position:relative;width:100%;max-width:720px;height:520px;margin:8px auto 4px;}
.triangle-svg{position:absolute;top:0;left:0;width:100%;height:100%;}
.tri-line{stroke:#c8d0ea;stroke-width:1.5;stroke-dasharray:4 3;vector-effect:non-scaling-stroke;}
.tri-node{position:absolute;transform:translate(-50%,-50%);z-index:2;}
.tri-node-badge{width:74px;height:74px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;text-align:center;line-height:1.35;box-shadow:0 2px 8px rgba(60,80,140,.15);border:2px solid #fff;}
.node-server{background:#dbeafe;color:#1e3a8a;}
.node-github{background:#e0e7ff;color:#3730a3;}
.node-local{background:#d1fae5;color:#065f46;}
.tri-panel{position:absolute;transform:translate(-50%,-50%);z-index:1;background:#fff;border:1px solid #e0e4f0;border-radius:10px;padding:10px 12px;box-shadow:0 2px 6px rgba(60,80,140,.08);width:200px;display:flex;flex-direction:column;gap:6px;}
.tri-panel .map-btn{width:100%;text-align:center;white-space:normal;}
.tri-panel-title{font-size:.74rem;font-weight:700;color:#3b4f8a;text-align:center;margin-bottom:2px;}
.tri-panel-sub{display:block;font-size:.65rem;color:#999;font-weight:400;}
.tri-panel-note{font-size:.68rem;color:#888;text-align:center;background:#f5f7ff;border-radius:5px;padding:4px 6px;}
.tri-panel-warn{font-size:.65rem;color:#b91c1c;text-align:center;background:#fef2f2;border-radius:5px;padding:5px 6px;line-height:1.4;}
.tri-panel-group-label{font-size:.66rem;color:#94a3b8;font-weight:700;text-align:center;margin-top:2px;}
.tri-panel-divider{border-top:1px dashed #e0e4f0;margin:2px 0;}
.tri-tabs{display:flex;gap:4px;margin-bottom:8px;flex-wrap:wrap;justify-content:center;}
.tri-tab{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;padding:4px 8px;font-size:.68rem;font-weight:700;color:#64748b;cursor:pointer;font-family:inherit;white-space:nowrap;}
.tri-tab.active{background:#3b4f8a;color:#fff;border-color:#3b4f8a;}
.tri-tab-content{display:flex;flex-direction:column;gap:6px;min-height:90px;}
.map-btn-check{background:linear-gradient(135deg,#64748b,#475569);}
.map-btn-check:hover{background:linear-gradient(135deg,#475569,#64748b);}
.map-btn-na{background:#f1f5f9;color:#94a3b8;border-radius:6px;padding:8px 12px;font-size:.76rem;font-weight:700;text-align:center;}
.flash-highlight{animation:flashHighlight 1.4s ease-in-out 3;}
@keyframes flashHighlight{0%,100%{box-shadow:0 2px 8px rgba(60,80,140,.10);}50%{box-shadow:0 0 0 5px rgba(59,130,246,.5);}}
@media (max-width:640px){
  .triangle-wrap{height:640px;max-width:100%;}
  .tri-panel{width:150px;padding:8px;}
  .tri-node-badge{width:58px;height:58px;font-size:.62rem;}
}
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
  <div class="card" id="statusCard">
    <h2>現在の状態</h2>
    <div class="status-grid">
      <div class="status-box">
        <h3>🏠 ローカル<span class="env-badge env-local">LOCAL</span></h3>
        <table class="status-table" id="localStatus"><tr><td colspan="2">読み込み中…</td></tr></table>
        <div class="status-footer" id="localTime"></div>
      </div>
      <div class="status-box">
        <h3>🌐 サーバー<span class="env-badge env-remote">REMOTE</span></h3>
        <table class="status-table" id="remoteStatus"><tr><td colspan="2">読み込み中…</td></tr></table>
        <div class="status-footer" id="remoteTime"></div>
      </div>
      <div class="status-box">
        <h3>🐙 GitHub<span class="env-badge env-github">REPO</span></h3>
        <table class="status-table" id="githubStatus"><tr><td colspan="2">読み込み中…</td></tr></table>
        <div class="status-footer" id="githubTime"></div>
        <div id="githubTopStatus" style="font-size:.72rem;color:#555;margin-top:6px;"></div>
      </div>
    </div>
    <button onclick="loadStatus()" style="font-size:.8rem;padding:5px 14px;border:1px solid #c8d0ea;background:#f5f7ff;border-radius:5px;cursor:pointer;">🔃 更新</button>
  </div>

  <!-- 3者の関係 -->
  <div class="card">
    <h2>🗺️ localhost・さくらサーバー・GitHubの関係</h2>
    <p style="font-size:.83rem;color:#555;margin-bottom:16px;">このシステムは3つの経路でデータ・コードをやり取りしています。それぞれ役割が異なります。下の「同期操作マップ」の見方の参考にしてください。</p>
    <div style="display:grid;grid-template-columns:1fr auto 1fr auto 1fr;gap:8px;align-items:center;margin-bottom:20px;">
      <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:14px 8px;text-align:center;font-size:.83rem;font-weight:700;color:#065f46;">🏠 家のPC<br><span style="font-weight:400;font-size:.7rem;">localhost</span></div>
      <div style="text-align:center;font-size:1.3rem;color:#999;">⇄</div>
      <div style="background:#e0e7ff;border:1px solid #a5b4fc;border-radius:8px;padding:14px 8px;text-align:center;font-size:.83rem;font-weight:700;color:#3730a3;">🐙 GitHub<br><span style="font-weight:400;font-size:.7rem;">joom31628103/karte</span></div>
      <div style="text-align:center;font-size:1.3rem;color:#999;">⇄</div>
      <div style="background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;padding:14px 8px;text-align:center;font-size:.83rem;font-weight:700;color:#1e3a8a;">🌐 さくらサーバー<br><span style="font-weight:400;font-size:.7rem;">本番</span></div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:.78rem;">
      <tr style="background:#f5f7ff;font-weight:700;color:#555;">
        <td style="padding:6px 10px;border-bottom:2px solid #e0e4f0;">やり取り</td>
        <td style="padding:6px 10px;border-bottom:2px solid #e0e4f0;">経路</td>
        <td style="padding:6px 10px;border-bottom:2px solid #e0e4f0;">方法</td>
        <td style="padding:6px 10px;border-bottom:2px solid #e0e4f0;">同期操作マップのどこ？</td>
      </tr>
      <tr>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">DBデータ</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">家PC ⇄ さくら（直接）</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">HTTP API（export/import）</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">ローカル⇄サーバー「DB」タブ</td>
      </tr>
      <tr>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">コードファイルの状態確認</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">家PC ⇄ さくら（直接）</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">MD5比較 + SCPコマンド生成</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">ローカル⇄サーバー「ファイル」タブ</td>
      </tr>
      <tr>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">コードのバージョン管理</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">家PC ⇄ GitHub ⇄ 職場PC</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">git push / git pull</td>
        <td style="padding:6px 10px;border-bottom:1px solid #eee;">ローカル⇄GitHub「ファイル」タブ</td>
      </tr>
      <tr>
        <td style="padding:6px 10px;">コードの本番デプロイ</td>
        <td style="padding:6px 10px;">GitHub → さくら</td>
        <td style="padding:6px 10px;">サーバー上でgit pull</td>
        <td style="padding:6px 10px;">サーバー⇄GitHub「ファイル」タブ</td>
      </tr>
    </table>
    <div style="margin-top:14px;font-size:.76rem;color:#888;">
      💡 職場PCも家PCと同じGitHubリポジトリをcloneすれば、<code>http://localhost/karte/sync.php</code> で同様にPull/Pushできます。
    </div>
  </div>

  <!-- 同期操作マップ -->
  <div class="card">
    <h2>🔄 同期操作マップ</h2>
    <p style="font-size:.83rem;color:#555;margin-bottom:16px;">
      ローカル ⇄ サーバー ⇄ GitHub の3つの経路について、⬇ ダウン（相手から取得）・🔀 マージ（新しいほうを残す）・⬆ アップ（相手に送る）を一覧にしています。
    </p>
    <div class="warn-box">
      ⚠️ 同期すると<strong>相手側のデータが完全に上書き</strong>されます。<br>
      どちらか一方で作業してから同期してください。
    </div>
    <div class="triangle-wrap">
      <svg class="triangle-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
        <line x1="13" y1="11" x2="87" y2="11" class="tri-line"/>
        <line x1="13" y1="11" x2="50" y2="88" class="tri-line"/>
        <line x1="87" y1="11" x2="50" y2="88" class="tri-line"/>
      </svg>

      <div class="tri-node" style="left:13%;top:11%;">
        <div class="tri-node-badge node-server">🌐<br>サーバー</div>
      </div>
      <div class="tri-node" style="left:87%;top:11%;">
        <div class="tri-node-badge node-github">🐙<br>GitHub</div>
      </div>
      <div class="tri-node" style="left:50%;top:88%;">
        <div class="tri-node-badge node-local">🏠<br>ローカル</div>
      </div>

      <!-- サーバー ⇄ GitHub（上辺） -->
      <div class="tri-panel" style="left:50%;top:33%;">
        <div class="tri-panel-title">サーバー ⇄ GitHub<span class="tri-panel-sub">（コード・DB・スキーマ）</span></div>
        <div class="tri-tabs">
          <button class="tri-tab" onclick="triTab(this,'sg-db')">💽 DB</button>
          <button class="tri-tab active" onclick="triTab(this,'sg-file')">📁 ファイル</button>
          <button class="tri-tab" onclick="triTab(this,'sg-schema')">🗂️ スキーマ</button>
        </div>
        <div class="tri-tab-content" data-tabcontent="sg-db" style="display:none;">
          <button id="mapSgDbDiffBtn" class="map-btn map-btn-check" onclick="doDbGithubDiff('server')">🔍 DBを比較（結果は下に表示）</button>
          <div class="map-btn-na">⬇ 該当なし</div>
          <div class="map-btn-na">🔀 該当なし</div>
          <div class="map-btn-na">⬆ 無効化（安全のため）</div>
          <div class="tri-panel-note">GitHubのバックアップJSONは「ファイル」タブのデプロイで届きますが、生きたDBへの反映は自動化していません。</div>
        </div>
        <div class="tri-tab-content" data-tabcontent="sg-file">
          <button id="mapSgFileDiffBtn" class="map-btn map-btn-check" onclick="doGitFileDiff('server')">🔍 ファイルを比較（結果は下に表示）</button>
          <button id="mapGitDeploy" class="map-btn map-btn-download" onclick="doGitDeploy()">⬇ GitHubから取得（デプロイ）</button>
          <div class="map-btn-na">🔀 該当なし</div>
          <div class="map-btn-na">⬆ 無効化（安全のため。ローカルからPushしてください）</div>
        </div>
        <div class="tri-tab-content" data-tabcontent="sg-schema" style="display:none;">
          <button id="mapSgSchemaDiffBtn" class="map-btn map-btn-check" onclick="doSchemaGithubDiff('server')">🔍 スキーマを比較（結果は下に表示）</button>
          <div class="map-btn-na">⬇ 該当なし</div>
          <div class="map-btn-na">🔀 該当なし</div>
          <div class="map-btn-na">⬆ 無効化（安全のため）</div>
          <div class="tri-panel-note">GitHubのschema.jsonは「ファイル」タブのデプロイで届きますが、生きたDBへの反映は自動化していません。</div>
        </div>
      </div>

      <!-- ローカル ⇄ サーバー（左辺） -->
      <div class="tri-panel" style="left:23%;top:52%;">
        <div class="tri-panel-title">ローカル ⇄ サーバー<span class="tri-panel-sub">（DB・ファイル・スキーマ）</span></div>
        <div class="tri-tabs">
          <button class="tri-tab active" onclick="triTab(this,'ls-db')">💽 DB</button>
          <button class="tri-tab" onclick="triTab(this,'ls-file')">📁 ファイル</button>
          <button class="tri-tab" onclick="triTab(this,'ls-schema')">🗂️ スキーマ</button>
        </div>
        <div class="tri-tab-content" data-tabcontent="ls-db">
          <button class="map-btn map-btn-check" onclick="scrollToStatus()">🔍 DBを比較（上の「現在の状態」参照）</button>
          <button id="mapBtnDownload" class="map-btn map-btn-download" onclick="confirmSync('download')">⬇ サーバー→ローカル</button>
          <button id="mapBtnMerge" class="map-btn map-btn-merge" onclick="confirmSync('merge')">🔀 新しいほうを両方に反映</button>
          <button id="mapBtnUpload" class="map-btn map-btn-upload" onclick="confirmSync('upload')">⬆ ローカル→サーバー</button>
        </div>
        <div class="tri-tab-content" data-tabcontent="ls-file" style="display:none;">
          <button id="mapFileCompareBtn" class="map-btn map-btn-check" onclick="doFileCompare()">🔍 ファイルを比較（結果は下に表示）</button>
          <div class="map-btn-na">⬇⬆ 比較結果にSCPコマンドを表示</div>
          <div class="map-btn-na">🔀 該当なし</div>
          <div id="fileCompareWarn" class="tri-panel-warn" style="display:none;">⚠️ ローカルからのみ実行できます</div>
        </div>
        <div class="tri-tab-content" data-tabcontent="ls-schema" style="display:none;">
          <button id="mapSchemaBtn" class="map-btn map-btn-check" onclick="doSchemaSync()">🔍 スキーマを比較（結果は下に表示）</button>
          <div class="map-btn-na">⬇⬆ 比較結果に反映ボタンを表示</div>
          <div class="map-btn-na">🔀 該当なし</div>
          <div id="syncRemoteWarnSchema" class="tri-panel-warn" style="display:none;">⚠️ ローカルからのみ実行できます</div>
        </div>
      </div>

      <!-- ローカル ⇄ GitHub（右辺） -->
      <div class="tri-panel" style="left:77%;top:52%;">
        <div class="tri-panel-title">ローカル ⇄ GitHub<span class="tri-panel-sub">（コード・DB・スキーマ）</span></div>
        <div class="tri-tabs">
          <button class="tri-tab" onclick="triTab(this,'lg-db')">💽 DB</button>
          <button class="tri-tab active" onclick="triTab(this,'lg-file')">📁 ファイル</button>
          <button class="tri-tab" onclick="triTab(this,'lg-schema')">🗂️ スキーマ</button>
        </div>
        <div class="tri-tab-content" data-tabcontent="lg-db" style="display:none;">
          <button id="mapDbGithubDiffBtn" class="map-btn map-btn-check" onclick="doDbGithubDiff('local')">🔍 DBを比較（結果は下に表示）</button>
          <div class="map-btn-na">⬇ 該当なし</div>
          <div class="map-btn-na">🔀 該当なし</div>
          <button id="mapDbExportBtn" class="map-btn map-btn-upload" onclick="doDbExportAndMerge()">⬆ GitHubに反映（エクスポート→マージ）</button>
        </div>
        <div class="tri-tab-content" data-tabcontent="lg-file">
          <button id="mapGitFileDiffBtn" class="map-btn map-btn-check" onclick="doGitFileDiff('local')">🔍 ファイルを比較（結果は下に表示）</button>
          <button id="mapGitPull" class="map-btn map-btn-download" onclick="doGitPull()">⬇ GitHubから取得（Pull）</button>
          <button id="mapGitMerge" class="map-btn map-btn-merge" onclick="doGitMerge()">🔀 マージ（commit→Pull→Push）</button>
          <button id="mapGitPush" class="map-btn map-btn-upload" onclick="doGitPush()">⬆ GitHubへ送信（Push）</button>
          <div class="tri-panel-note">この⬇🔀⬆はコード全体（PHP等）・DBバックアップ・スキーマ情報もまとめて送受信します。</div>
        </div>
        <div class="tri-tab-content" data-tabcontent="lg-schema" style="display:none;">
          <button id="mapSchemaGithubDiffBtn" class="map-btn map-btn-check" onclick="doSchemaGithubDiff('local')">🔍 スキーマを比較（結果は下に表示）</button>
          <div class="map-btn-na">⬇ 該当なし</div>
          <div class="map-btn-na">🔀 該当なし</div>
          <button id="mapSchemaExportBtn" class="map-btn map-btn-upload" onclick="doSchemaExportAndMerge()">⬆ GitHubに反映（エクスポート→マージ）</button>
        </div>
      </div>
    </div>
    <div id="gitWarn" style="display:none;background:#fff7ed;border:1px solid #fcd34d;border-radius:6px;padding:10px 14px;font-size:.82rem;color:#92400e;margin:16px 0 4px;">
      ⚠️ Pull・Push・マージ・デプロイはローカルPC（localhost）からのみ実行できます。
    </div>

    <!-- ① 状態確認（ローカル/サーバーのGit状態）※何か実行するまでは非表示 -->
    <div id="gitStatusSection" style="display:none;">
      <div class="status-grid" style="margin-top:16px;margin-bottom:10px;">
        <div class="status-box">
          <h3>🏠 ローカル<span class="env-badge env-local">LOCAL</span></h3>
          <div id="gitStatusLocal" style="font-size:.8rem;">読み込み中…</div>
        </div>
        <div class="status-box">
          <h3>🌐 サーバー<span class="env-badge env-remote">REMOTE</span></h3>
          <div id="gitStatusRemote" style="font-size:.8rem;">読み込み中…</div>
        </div>
      </div>
      <div id="gitCompareBox" style="margin-bottom:6px;"></div>
      <div style="text-align:right;margin-bottom:14px;">
        <button onclick="loadGitStatus()" style="font-size:.8rem;padding:6px 14px;border:1px solid #c8d0ea;background:#f5f7ff;border-radius:5px;cursor:pointer;">🔃 Git状態更新</button>
      </div>
    </div>

    <!-- ② 進捗・比較結果（各タブのボタンを押すとここに表示） -->
    <div class="progress" id="progress"><div class="progress-bar" id="progressBar"></div></div>
    <div class="merge-stats" id="mergeStats">
      <h4>🔀 マージ結果</h4>
      <table id="mergeStatsTable"></table>
    </div>
    <div id="schemaRes" style="display:none;margin-top:16px;background:#f8fffe;border:1px solid #99f6e4;border-radius:8px;padding:14px;">
      <h4 style="font-size:.85rem;font-weight:700;color:#0f766e;margin-bottom:10px;">🗂️ スキーマ確認・同期の結果</h4>
      <div id="schemaDetail"></div>
    </div>
    <div id="fileCompareRes" style="display:none;margin-top:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:14px;"></div>
    <div id="dbGithubRes" style="display:none;margin-top:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px;">
      <div id="dbGithubDetail"></div>
    </div>
    <div id="gitFileDiffRes" style="display:none;margin-top:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:14px;"></div>
    <div id="schemaGithubRes" style="display:none;margin-top:16px;background:#f8fffe;border:1px solid #99f6e4;border-radius:8px;padding:14px;">
      <div id="schemaGithubDetail"></div>
    </div>

    <!-- ③ コミットメッセージ（常時表示：Push/マージ前に入力できるように）・実行ログ（実行するまでは非表示） -->
    <div style="display:flex;gap:10px;margin:16px 0 12px;align-items:center;flex-wrap:wrap;">
      <input type="text" id="gitMsgInput" placeholder="コミットメッセージ（Push・マージ時。省略時は自動生成）"
        style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #c8d0ea;border-radius:6px;font-size:.85rem;font-family:inherit;">
    </div>
    <div id="logSection" style="display:none;">
      <h3 style="font-size:.85rem;color:#3b4f8a;margin-bottom:6px;">📋 実行ログ</h3>
      <div class="log-box" id="logBox">同期ログがここに表示されます。</div>
    </div>

    <!-- ④ 認証設定（初回のみ） -->
    <details style="margin-top:14px;background:#f5f7ff;border:1px solid #c8d0ea;border-radius:7px;padding:10px 16px;font-size:.8rem;color:#555;">
      <summary style="cursor:pointer;color:#3b4f8a;font-weight:700;">💡 認証エラー時の対処：PATをURLに埋め込む（初回のみ・クリックして展開）</summary>
      <ol style="margin:8px 0 4px 16px;padding:0;line-height:1.9;">
        <li>GitHubで <strong>Settings → Developer settings → Personal access tokens → Tokens (classic)</strong> からPAT（Contents書き込み権限）を発行</li>
        <li>PowerShellで以下を実行（YOUR_PATを置き換え）：</li>
      </ol>
      <div style="position:relative;margin-top:4px;">
        <pre id="kartePushCmd" style="background:#1e2340;color:#a0f0b0;border-radius:5px;padding:8px 12px;font-size:.78rem;margin:0;white-space:pre-wrap">git -C C:\xampp\htdocs\karte remote set-url origin https://YOUR_PAT@github.com/joom31628103/karte.git
git -C C:\xampp\htdocs\karte push</pre>
        <button onclick="navigator.clipboard.writeText(document.getElementById('kartePushCmd').textContent).then(()=>alert('コピーしました！'))" style="position:absolute;top:4px;right:4px;background:#2d3748;color:#a0f0b0;border:1px solid #4a5568;border-radius:4px;padding:2px 10px;font-size:.7rem;cursor:pointer">コピー</button>
      </div>
      <div style="margin-top:6px;color:#888;font-size:.75rem;">PATをURLに埋め込むと次回以降WebUIのPushボタンも認証なしで使えます。サーバー側は認証情報がキャッシュ済みのため、🚀デプロイは追加設定なしで使えます。</div>
    </details>

    <div class="last-sync" id="lastSyncInfo"></div>
    <p style="font-size:.72rem;color:#888;margin-top:10px;">
      💡 職場PC・ローカル（家PC）の両方からpushする運用のため、「🔀 マージ」は自分の変更をコミット→GitHubの最新をPull（自動マージ）→Pushまで一括実行します。ただし同じ行を双方で編集した場合の衝突（コンフリクト）は自動解決できず、その場合はPushせず停止して通知します。<br>
      📁 ファイル比較は対象外：photos / uploads / sync フォルダ。差分はSCPコマンドで反映できます。
    </p>
  </div>

  <!-- 方法③：自動同期の説明 -->
  <div class="card">
    <h2>⏱️ 自動同期の設定（Windowsタスクスケジューラ）</h2>
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

  const renderGithubData = (data) => {
    const elId = 'githubStatus', timeId = 'githubTime';
    if (!data?.success || !data.initialized) {
      document.getElementById(elId).innerHTML = '<tr><td class="err">取得失敗</td></tr>';
      return;
    }
    const rows = Object.entries(data.counts)
      .map(([k,v]) => `<tr><td>${tableLabels[k]||k}</td><td>${v.toLocaleString()} 件</td></tr>`)
      .join('');
    document.getElementById(elId).innerHTML = rows;
    const t = data.last_export ? new Date(data.last_export).toLocaleString('ja-JP') : '—';
    document.getElementById(timeId).textContent = `最終push時点のデータ：${t}`;
  };

  try {
    const [loc, rem, ghData] = await Promise.all([
      fetch(`${LOCAL_API}?action=status&token=${TOKEN}`).then(r=>r.json()).catch(()=>null),
      fetch(`${REMOTE_API}?action=status&token=${TOKEN}`).then(r=>r.json()).catch(()=>null),
      fetch(`${LOCAL_API}?action=github_data_status&token=${TOKEN}`).then(r=>r.json()).catch(()=>null),
    ]);
    render('localStatus',  'localTime',  loc);
    render('remoteStatus', 'remoteTime', rem);
    renderGithubData(ghData);
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
  showActivity();
  const btn = document.getElementById(dir==='download'?'mapBtnDownload':'mapBtnUpload');
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
  showActivity();
  const btn = document.getElementById('mapBtnMerge');
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

// 同期操作マップ：パネル内タブ切り替え
function showActivity(){
  const a = document.getElementById('gitStatusSection');
  const b = document.getElementById('logSection');
  if (a) a.style.display = 'block';
  if (b) b.style.display = 'block';
}

function revealResult(el){
  if (!el) return;
  el.scrollIntoView({behavior:'smooth', block:'center'});
  el.classList.add('flash-highlight');
  setTimeout(()=>el.classList.remove('flash-highlight'), 4300);
}

function scrollToResult(id){
  revealResult(document.getElementById(id));
}

function scrollToStatus(){
  revealResult(document.getElementById('statusCard'));
}

function triTab(btn, tabId){
  const panel = btn.closest('.tri-panel');
  panel.querySelectorAll('.tri-tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  panel.querySelectorAll('.tri-tab-content').forEach(c=>{
    c.style.display = (c.dataset.tabcontent === tabId) ? 'flex' : 'none';
  });
}

// サーバー上での同期ブロック
(function(){
  const isRemote=location.hostname!=='localhost'&&location.hostname!=='127.0.0.1';
  if(isRemote){
    document.getElementById('syncRemoteWarnSchema').style.display='block';
    ['mapBtnDownload','mapBtnMerge','mapBtnUpload','mapSchemaBtn'].forEach(id=>{
      const b=document.getElementById(id);
      if(b){b.disabled=true;b.style.opacity='0.4';b.style.cursor='not-allowed';}
    });
    // データ同期の warn-box に追記（同期操作マップ・同期操作の両方）
    document.querySelectorAll('.warn-box').forEach(wb=>{
      wb.innerHTML+='<br><strong style="color:#b91c1c;">⚠️ サーバー上では同期できません。ローカルPC（localhost）から実行してください。</strong>';
    });
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
  showActivity();
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
        scrollToResult('schemaRes');
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
      scrollToResult('schemaRes');
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
    ['mapGitPull','mapGitPush','mapGitDeploy','mapGitMerge','mapGitFileDiffBtn','mapSchemaGithubDiffBtn','mapSchemaExportBtn','mapDbGithubDiffBtn','mapDbExportBtn','mapSgDbDiffBtn','mapSgFileDiffBtn','mapSgSchemaDiffBtn'].forEach(id => {
      const b = document.getElementById(id);
      if (b) { b.disabled = true; b.style.opacity = '0.4'; b.style.cursor = 'not-allowed'; }
    });
  }
})();

function renderGitStatus(elId, d) {
  const box = document.getElementById(elId);
  if (!d?.success) { box.innerHTML = '<span style="color:#c00">取得失敗</span>'; return null; }
  if (!d.initialized) {
    box.innerHTML = `<span style="color:#b45309">⚠️ 未初期化</span>
      <div style="font-size:.75rem;color:#555;margin-top:6px;">PowerShellで以下を実行してください：<br>
      <code style="background:#1e2340;color:#a0f0b0;padding:4px 8px;border-radius:4px;display:inline-block;margin-top:4px;">cd C:\\xampp\\htdocs\\karte &amp;&amp; git init &amp;&amp; git remote add origin &lt;GitHubリポジトリURL&gt;</code></div>`;
    return null;
  }
  const chCount = d.changes.length;
  const chColor = chCount > 0 ? '#dc2626' : '#059669';
  const abParts = [];
  if (d.ahead  > 0) abParts.push(`<span style="color:#7c3aed">↑${d.ahead}件 未push</span>`);
  if (d.behind > 0) abParts.push(`<span style="color:#dc2626">↓${d.behind}件 未pull</span>`);
  if (abParts.length === 0) abParts.push('<span style="color:#059669">GitHubと同期済み</span>');
  let h = `<div>🌿 <strong>${d.branch || '(no branch)'}</strong> <code style="font-size:.72rem;background:#eef0fa;padding:1px 5px;border-radius:3px;">${d.hash || '?'}</code></div>
    <div style="margin-top:4px;color:${chColor}">📝 未コミット: <strong>${chCount}件</strong></div>
    <div style="margin-top:4px;">${abParts.join(' ')}</div>`;
  if (d.log && d.log.length > 0) {
    h += `<div style="font-size:.7rem;color:#555;margin-top:6px;">最近のコミット：`;
    d.log.slice(0,3).forEach(l => { h += `<div style="font-family:monospace;color:#3b4f8a">${l}</div>`; });
    h += `</div>`;
  }
  box.innerHTML = h;
  return d;
}

function renderGithubTop(locD, remD) {
  const box = document.getElementById('githubTopStatus');
  const d = locD?.success && locD.originLog ? locD : remD;
  if (!box) return;
  if (!d?.success || !d.initialized || !d.originLog) {
    box.innerHTML = '<span style="color:#888">情報なし</span>';
    return;
  }
  const diffPart = (label, src) => {
    if (!src?.success) return `${label}: <span style="color:#888">取得失敗</span>`;
    const ahead = src.ahead||0, behind = src.behind||0;
    if (ahead === 0 && behind === 0) return `${label}: <span style="color:#059669">✅ 同期済み</span>`;
    const parts = [];
    if (ahead  > 0) parts.push(`<span style="color:#7c3aed">↑${ahead}件未push</span>`);
    if (behind > 0) parts.push(`<span style="color:#dc2626">↓${behind}件未pull</span>`);
    return `${label}: ${parts.join(' ')}`;
  };
  box.innerHTML = `
    <div>📌 最新: <code style="font-size:.72rem;background:#eef0fa;padding:1px 5px;border-radius:3px;">${d.originHash}</code></div>
    <div style="font-size:.72rem;color:#555;margin:3px 0 6px;word-break:break-all;">${d.originLog.replace(/^\S+\s/,'')}</div>
    <div style="font-size:.76rem;">${diffPart('ローカル', locD)}</div>
    <div style="font-size:.76rem;margin-top:2px;">${diffPart('サーバー', remD)}</div>`;
}

window.loadGitStatus = async function() {
  const cmpBox = document.getElementById('gitCompareBox');
  cmpBox.innerHTML = '';
  try {
    const [locD, remD] = await Promise.all([
      fetch(`${LOCAL_API}?action=git_status&token=${TOKEN}`).then(r => r.json()).catch(()=>null),
      fetch(`${REMOTE_API}?action=git_status&token=${TOKEN}`).then(r => r.json()).catch(()=>null),
    ]);
    const l = renderGitStatus('gitStatusLocal', locD);
    const r = renderGitStatus('gitStatusRemote', remD);
    renderGithubTop(locD, remD);
    if (l && r) {
      if (l.hash === r.hash) {
        cmpBox.innerHTML = `<div style="background:#d1fae5;color:#065f46;padding:8px 12px;border-radius:6px;font-size:.8rem;font-weight:700;">✅ ローカルとサーバーのコードは同一です（${l.hash}）。</div>`;
      } else {
        cmpBox.innerHTML = `<div style="background:#fef2f2;color:#991b1b;padding:8px 12px;border-radius:6px;font-size:.8rem;font-weight:700;">⚠️ ローカル（${l.hash}）とサーバー（${r.hash}）でコードが異なります。GitHub経由でPull/Push・デプロイして揃えてください。</div>`;
      }
    }
  } catch(e) {
    cmpBox.innerHTML = '<span style="color:#c00">比較エラー: ' + e.message + '</span>';
  }
};

function gitLog(msg, type='') {
  log(msg, type); // 実行ログを1本化（DB同期・Git操作すべて同じログに集約）
}

window.doGitPull = async function() {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  if (!confirm('⬇️ GitHubから最新のコードを取得します（git pull）。\nローカルの未コミット変更が競合する場合があります。続行しますか？')) return;
  showActivity();
  const btn = document.getElementById('mapGitPull');
  btn.disabled = true;
  document.getElementById('logBox').textContent = '';
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

window.doGitDeploy = async function() {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  if (!confirm('🚀 サーバー上でgit pullを実行し、GitHubの最新コードを本番に反映します（デプロイ）。\n続行しますか？')) return;
  showActivity();
  const btn = document.getElementById('mapGitDeploy');
  btn.disabled = true;
  document.getElementById('logBox').textContent = '';
  gitLog('サーバーでgit pull実行中…', 'info');
  try {
    const d = await fetch(`${REMOTE_API}?action=git_pull&token=${TOKEN}`, {
      method: 'POST', headers: {'Content-Type':'application/json'}
    }).then(r => r.json());
    gitLog(d.output || '(出力なし)', d.success ? 'ok' : 'err');
    gitLog(d.success ? '✅ サーバーへのデプロイ完了' : '❌ デプロイ失敗', d.success ? 'ok' : 'err');
    if (d.success) await loadGitStatus();
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

window.doGitPush = async function() {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  const msg = document.getElementById('gitMsgInput').value.trim() || ('Update ' + new Date().toLocaleString('ja-JP'));
  if (!confirm(`⬆️ GitHubにプッシュします（git add -A → commit → push）。\nコミットメッセージ：「${msg}」\n続行しますか？`)) return;
  showActivity();
  const btn = document.getElementById('mapGitPush');
  btn.disabled = true;
  document.getElementById('logBox').textContent = '';
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

window.doGitMerge = async function() {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  const msg = document.getElementById('gitMsgInput').value.trim() || ('Update ' + new Date().toLocaleString('ja-JP'));
  if (!confirm(`🔀 マージ同期を実行します。\n① 自分の変更をコミット\n② GitHubの最新をPull（自動マージ）\n③ Push\nコミットメッセージ：「${msg}」\n※同じ行を双方で編集した場合の衝突は自動解決できません。続行しますか？`)) return;
  showActivity();
  const btn = document.getElementById('mapGitMerge');
  btn.disabled = true;
  document.getElementById('logBox').textContent = '';
  gitLog('マージ同期開始…', 'info');
  try {
    const d = await fetch(`${LOCAL_API}?action=git_merge&token=${TOKEN}`, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message: msg})
    }).then(r => r.json());
    (d.steps || []).forEach(s => {
      gitLog(`▶ ${s.label}`, 'info');
      if (s.out) {
        const isErr = /error|fatal|conflict/i.test(s.out);
        gitLog(s.out, isErr ? 'err' : 'ok');
      }
    });
    if (d.conflict) {
      gitLog('⚠️ コンフリクトが発生しました。Pushは行っていません。', 'err');
      gitLog('VSCode等で該当ファイルの <<<<<<< 〜 >>>>>>> を編集して解決後、Pushしてください。', 'err');
    } else {
      gitLog(d.success ? '✅ マージ同期完了（commit→Pull→Push）' : '❌ マージ同期失敗（ログを確認してください）', d.success ? 'ok' : 'err');
      if (d.success) document.getElementById('gitMsgInput').value = '';
    }
    await loadGitStatus();
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

// ── ローカル ⇄ GitHub：ファイル比較 ──────────────────────────
window.doGitFileDiff = async function(side) {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  showActivity();
  const api = side === 'server' ? REMOTE_API : LOCAL_API;
  const label = side === 'server' ? 'サーバー' : 'ローカル';
  const btn = document.getElementById(side === 'server' ? 'mapSgFileDiffBtn' : 'mapGitFileDiffBtn');
  const resDiv = document.getElementById('gitFileDiffRes');
  btn.disabled = true; resDiv.style.display = 'none';
  gitLog(`${label}とGitHubのファイル差分を確認中…`, 'info');
  try {
    const d = await fetch(`${api}?action=git_file_diff&token=${TOKEN}`, {method:'POST'}).then(r => r.json());
    if (!d.success) throw new Error(d.error || '取得失敗');
    const labels = {M:['内容差分','#92400e','#fffbeb'], A:[`${label}のみ（未push）`,'#9d174d','#fff0f9'], D:['GitHubのみ（削除済み）','#1e3a8a','#eff6ff']};
    let h = `<h4 style="font-size:.85rem;font-weight:700;color:#0369a1;margin-bottom:10px;">📁 ${label} ⇄ GitHub ファイル差分</h4>`;
    if (d.files.length === 0) {
      h += `<p style="color:#059669;font-weight:700;font-size:.85rem;">✅ ${label}とGitHubのPHP/JS/CSS/HTMLファイルはすべて一致しています。</p>`;
    } else {
      h += `<table style="width:100%;border-collapse:collapse;font-size:.75rem;">
        <tr style="font-weight:700;color:#555;background:#f5f7ff;">
          <td style="padding:5px 8px;border-bottom:2px solid #e0e4f0;">ファイル</td>
          <td style="padding:5px 8px;border-bottom:2px solid #e0e4f0;">状態</td>
        </tr>`;
      d.files.forEach(f => {
        const [lb, color, bg] = labels[f.status] || [f.status, '#555', '#fff'];
        h += `<tr style="background:${bg}">
          <td style="padding:4px 8px;border-bottom:1px solid #eee;font-family:monospace;font-size:.72rem;">${f.path}</td>
          <td style="padding:4px 8px;border-bottom:1px solid #eee;color:${color};font-weight:700;white-space:nowrap;">${lb}</td>
        </tr>`;
      });
      h += '</table>';
      h += side === 'server'
        ? '<p style="font-size:.72rem;color:#888;margin-top:8px;">💡 反映するには「⬇ GitHubから取得（デプロイ）」を使ってください。</p>'
        : '<p style="font-size:.72rem;color:#888;margin-top:8px;">💡 反映するには⬇Pull・🔀マージ・⬆Pushボタンを使ってください。</p>';
    }
    resDiv.innerHTML = h;
    resDiv.style.display = 'block';
    revealResult(resDiv);
    gitLog(`✅ ファイル差分確認完了：${d.files.length}件`, 'ok');
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

// ── ローカル/サーバー ⇄ GitHub：DBスキーマ比較 ──────────────────────────
window.doSchemaGithubDiff = async function(side) {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  showActivity();
  const api = side === 'server' ? REMOTE_API : LOCAL_API;
  const label = side === 'server' ? 'サーバー' : 'ローカル';
  const btn = document.getElementById(side === 'server' ? 'mapSgSchemaDiffBtn' : 'mapSchemaGithubDiffBtn');
  const resDiv = document.getElementById('schemaGithubRes');
  const detail = document.getElementById('schemaGithubDetail');
  btn.disabled = true; resDiv.style.display = 'none';
  gitLog(`${label}とGitHubのschema.jsonを比較中…`, 'info');
  try {
    const [lS, gS] = await Promise.all([
      fetch(`${api}?action=schema&token=${TOKEN}`).then(r=>r.json()),
      fetch(`${api}?action=schema_github&token=${TOKEN}`).then(r=>r.json()),
    ]);
    if (!lS.success) throw new Error(`${label}のスキーマ取得に失敗しました`);
    if (!gS.success) throw new Error('GitHubのschema.json取得に失敗しました');
    if (!gS.exists) {
      detail.innerHTML = `<p style="color:#b45309;font-weight:700;font-size:.85rem;">⚠️ GitHubにはまだ schema.json がありません。ローカル⇄GitHub「スキーマ」タブの「⬆ GitHubに反映」を実行してください。</p>`;
      resDiv.style.display = 'block';
      revealResult(resDiv);
      gitLog('⚠️ GitHub側にschema.jsonがありません', 'err');
      return;
    }
    const gSchema = gS.schema;
    const missingOnGithub = [], missingOnLocal = [], typeDiff = [];
    for (const [tbl, lCols] of Object.entries(lS.schema)) {
      const gCols = gSchema[tbl] || {};
      for (const [col, info] of Object.entries(lCols)) {
        if (!(col in gCols)) missingOnGithub.push({tbl, col, type: info.type});
        else if (normalizeType(gCols[col].type) !== normalizeType(info.type)) {
          typeDiff.push({tbl, col, localType: info.type, githubType: gCols[col].type});
        }
      }
    }
    for (const [tbl, gCols] of Object.entries(gSchema)) {
      const lCols = lS.schema[tbl] || {};
      for (const col of Object.keys(gCols)) {
        if (!(col in lCols)) missingOnLocal.push({tbl, col, type: gCols[col].type});
      }
    }
    const th = (cols) => `<table style="width:100%;border-collapse:collapse;font-size:.76rem;margin-bottom:10px;"><tr style="font-weight:700;color:#555;">${cols.map(c=>`<td style="padding:3px 6px">${c}</td>`).join('')}</tr>`;
    let h = `<h4 style="font-size:.85rem;font-weight:700;color:#0f766e;margin-bottom:10px;">🗂️ ${label} ⇄ GitHub スキーマ差分</h4>`;
    if (missingOnGithub.length===0 && missingOnLocal.length===0 && typeDiff.length===0) {
      h += `<p style="color:#059669;font-weight:700;font-size:.85rem;">✅ ${label}とGitHub（schema.json、${gS.exported_at ? new Date(gS.exported_at).toLocaleString('ja-JP') : '?'}時点）のスキーマは一致しています。</p>`;
    } else {
      if (missingOnGithub.length) {
        h += `<p style="margin:0 0 5px;color:#0f766e;font-weight:700;">GitHub側に未反映のカラム（${missingOnGithub.length}件）</p>` + th(['テーブル','カラム','型']);
        missingOnGithub.forEach(r=>{h+=`<tr><td style="padding:3px 6px">${r.tbl}</td><td style="padding:3px 6px;color:#0f766e">${r.col}</td><td style="padding:3px 6px">${r.type}</td></tr>`;});
        h += '</table>';
      }
      if (missingOnLocal.length) {
        h += `<p style="margin:4px 0 5px;color:#b45309;font-weight:700;">${label}側に無いカラム（GitHub側のみ・${missingOnLocal.length}件）</p>` + th(['テーブル','カラム','型']);
        missingOnLocal.forEach(r=>{h+=`<tr><td style="padding:3px 6px">${r.tbl}</td><td style="padding:3px 6px;color:#b45309">${r.col}</td><td style="padding:3px 6px">${r.type}</td></tr>`;});
        h += '</table>';
      }
      if (typeDiff.length) {
        h += `<p style="margin:4px 0 5px;color:#7c3aed;font-weight:700;">型が異なるカラム（${typeDiff.length}件）</p>` + th(['テーブル','カラム',label,'GitHub']);
        typeDiff.forEach(r=>{h+=`<tr><td style="padding:3px 6px">${r.tbl}</td><td style="padding:3px 6px">${r.col}</td><td style="padding:3px 6px;color:#dc2626">${r.localType}</td><td style="padding:3px 6px;color:#7c3aed">${r.githubType}</td></tr>`;});
        h += '</table>';
      }
      h += side === 'server'
        ? '<p style="font-size:.72rem;color:#888;margin-top:6px;">💡 サーバーへの反映はローカル⇄GitHub側での対応が必要です（安全のためサーバーからは行いません）。</p>'
        : '<p style="font-size:.72rem;color:#888;margin-top:6px;">💡 GitHubに現在の構造を反映するには「⬆ GitHubに反映」を使ってください。</p>';
    }
    detail.innerHTML = h;
    resDiv.style.display = 'block';
    revealResult(resDiv);
    gitLog('✅ スキーマ差分確認完了', 'ok');
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

window.doSchemaExportAndMerge = async function() {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  if (!confirm('🗂️ ローカルの現在のDBスキーマをschema.jsonとして書き出し、GitHubにマージ同期（commit→Pull→Push）します。\n続行しますか？')) return;
  showActivity();
  const btn = document.getElementById('mapSchemaExportBtn');
  btn.disabled = true;
  document.getElementById('logBox').textContent = '';
  gitLog('ローカルのスキーマをschema.jsonへ書き出し中…', 'info');
  try {
    const exp = await fetch(`${LOCAL_API}?action=schema_export_json&token=${TOKEN}`, {method:'POST'}).then(r=>r.json());
    if (!exp.success) throw new Error('schema.jsonの書き出しに失敗しました');
    gitLog('✅ schema.json 書き出し完了', 'ok');

    const msg = 'schema.json更新 ' + new Date().toLocaleString('ja-JP');
    gitLog('GitHubへマージ同期中…', 'info');
    const d = await fetch(`${LOCAL_API}?action=git_merge&token=${TOKEN}`, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message: msg})
    }).then(r => r.json());
    (d.steps || []).forEach(s => {
      gitLog(`▶ ${s.label}`, 'info');
      if (s.out) { const isErr = /error|fatal|conflict/i.test(s.out); gitLog(s.out, isErr ? 'err' : 'ok'); }
    });
    if (d.conflict) {
      gitLog('⚠️ コンフリクトが発生しました。Pushは行っていません。手動で解決してください。', 'err');
    } else {
      gitLog(d.success ? '✅ スキーマのGitHub反映が完了しました' : '❌ 反映に失敗しました（ログを確認してください）', d.success ? 'ok' : 'err');
    }
    await loadGitStatus();
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

// ── ローカル ⇄ GitHub：DBデータ比較（data/students/*.json ベース） ──────────
window.doDbGithubDiff = async function(side) {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  showActivity();
  const api = side === 'server' ? REMOTE_API : LOCAL_API;
  const label = side === 'server' ? 'サーバー' : 'ローカル';
  const btn = document.getElementById(side === 'server' ? 'mapSgDbDiffBtn' : 'mapDbGithubDiffBtn');
  const resDiv = document.getElementById('dbGithubRes');
  const detail = document.getElementById('dbGithubDetail');
  btn.disabled = true; resDiv.style.display = 'none';
  gitLog(`${label}DBとGitHub上のバックアップを比較中…`, 'info');
  try {
    const [lStat, ghStat] = await Promise.all([
      fetch(`${api}?action=status&token=${TOKEN}`).then(r=>r.json()),
      fetch(`${api}?action=github_data_status&token=${TOKEN}`).then(r=>r.json()),
    ]);
    if (!lStat.success) throw new Error(`${label}DBの件数取得に失敗しました`);
    if (!ghStat.success || !ghStat.initialized) {
      detail.innerHTML = `<h4 style="font-size:.85rem;font-weight:700;color:#b45309;margin-bottom:10px;">💾 ${label} ⇄ GitHub DBデータ差分</h4><p style="color:#b45309;font-weight:700;font-size:.85rem;">⚠️ GitHubにまだバックアップ（data/students/*.json）がありません。ローカル⇄GitHub「DB」タブの「⬆ GitHubに反映」を実行してください。</p>`;
      resDiv.style.display = 'block';
      revealResult(resDiv);
      gitLog('⚠️ GitHub側にバックアップがありません', 'err');
      return;
    }
    const keys = new Set([...Object.keys(lStat.counts), ...Object.keys(ghStat.counts)]);
    let h = `<h4 style="font-size:.85rem;font-weight:700;color:#b45309;margin-bottom:10px;">💾 ${label} ⇄ GitHub DBデータ差分</h4>`;
    h += `<table style="width:100%;border-collapse:collapse;font-size:.78rem;margin-bottom:8px;">
      <tr style="font-weight:700;color:#555;"><td style="padding:3px 6px">テーブル</td><td style="padding:3px 6px;text-align:right">${label}</td><td style="padding:3px 6px;text-align:right">GitHub</td><td style="padding:3px 6px"></td></tr>`;
    let anyDiff = false;
    keys.forEach(k => {
      const lv = lStat.counts[k] ?? null;
      const inGh = k in ghStat.counts;
      const gv = inGh ? ghStat.counts[k] : null;
      if (!inGh) {
        h += `<tr><td style="padding:3px 6px">${tableLabels[k]||k}</td><td style="padding:3px 6px;text-align:right">${lv===null?'—':lv.toLocaleString()}</td><td style="padding:3px 6px;text-align:right">—</td><td style="padding:3px 6px;color:#94a3b8">対象外</td></tr>`;
        return;
      }
      const diff = (lv !== gv);
      if (diff) anyDiff = true;
      h += `<tr><td style="padding:3px 6px">${tableLabels[k]||k}</td><td style="padding:3px 6px;text-align:right">${lv===null?'—':lv.toLocaleString()}</td><td style="padding:3px 6px;text-align:right">${gv.toLocaleString()}</td><td style="padding:3px 6px;color:${diff?'#dc2626':'#059669'}">${diff?'⚠️ 差分':'✅'}</td></tr>`;
    });
    h += '</table>';
    const t = ghStat.last_export ? new Date(ghStat.last_export).toLocaleString('ja-JP') : '?';
    const hint = side === 'server'
      ? '（サーバーからの反映はできません。ローカル⇄GitHub「DB」タブから行ってください）'
      : '　💡 「⬆ GitHubに反映」で最新化できます';
    h += `<p style="font-size:.72rem;color:#888;">GitHub上のバックアップ最終エクスポート：${t}${anyDiff ? hint : ''}</p>`;
    detail.innerHTML = h;
    resDiv.style.display = 'block';
    revealResult(resDiv);
    gitLog(anyDiff ? '⚠️ 件数に差分があります' : '✅ 件数は一致しています', anyDiff ? 'err' : 'ok');
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

window.doDbExportAndMerge = async function() {
  if (location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') { alert('ローカルから実行してください'); return; }
  if (!confirm('💾 ローカルDBの全生徒データを data/students/*.json へ書き出し、GitHubにマージ同期（commit→Pull→Push）します。\n続行しますか？')) return;
  showActivity();
  const btn = document.getElementById('mapDbExportBtn');
  btn.disabled = true;
  document.getElementById('logBox').textContent = '';
  gitLog('生徒データをJSONへ書き出し中…', 'info');
  try {
    const exp = await fetch(`${LOCAL_API}?action=backup_export_all&token=${TOKEN}`, {method:'POST'}).then(r=>r.json());
    if (!exp.success) throw new Error('データの書き出しに失敗しました');
    gitLog(`✅ 書き出し完了：${exp.ok}件成功${exp.fail?` / ${exp.fail}件失敗`:''}`, 'ok');

    const msg = '生徒データバックアップ更新 ' + new Date().toLocaleString('ja-JP');
    gitLog('GitHubへマージ同期中…', 'info');
    const d = await fetch(`${LOCAL_API}?action=git_merge&token=${TOKEN}`, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({message: msg})
    }).then(r => r.json());
    (d.steps || []).forEach(s => {
      gitLog(`▶ ${s.label}`, 'info');
      if (s.out) { const isErr = /error|fatal|conflict/i.test(s.out); gitLog(s.out, isErr ? 'err' : 'ok'); }
    });
    if (d.conflict) {
      gitLog('⚠️ コンフリクトが発生しました。Pushは行っていません。手動で解決してください。', 'err');
    } else {
      gitLog(d.success ? '✅ DBデータのGitHub反映が完了しました' : '❌ 反映に失敗しました（ログを確認してください）', d.success ? 'ok' : 'err');
    }
    await loadGitStatus();
  } catch(e) { gitLog('❌ ' + e.message, 'err'); }
  finally { btn.disabled = false; }
};

loadGitStatus(); // 「現在の状態」カードのGitHub欄も含め、どちらの画面からでも状態表示は行う

// ── ファイル比較 ────────────────────────────────────────────
(function(){
  const isRemote=location.hostname!=='localhost'&&location.hostname!=='127.0.0.1';
  if(isRemote){
    const w=document.getElementById('fileCompareWarn');
    if(w) w.style.display='block';
    const b=document.getElementById('mapFileCompareBtn');
    if(b){b.disabled=true;b.style.opacity='0.4';b.style.cursor='not-allowed';}
  }
})();

window.doFileCompare=async function(){
  if(location.hostname!=='localhost'&&location.hostname!=='127.0.0.1'){
    alert('ファイル比較はローカルPC（localhost）から実行してください。');return;
  }
  showActivity();
  const btn=document.getElementById('mapFileCompareBtn');
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
    revealResult(resDiv);
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
