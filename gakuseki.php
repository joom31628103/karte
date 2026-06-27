<?php
require_once 'config.php';
requireLogin();
$teacher = htmlspecialchars($_SESSION['teacher_name']);
$conn = getDB();
$teachers = $conn->query("SELECT id, display_name FROM teachers ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$firstSid = $conn->query("SELECT student_id FROM students ORDER BY class_name,seat_number,student_id LIMIT 1")->fetch_assoc()['student_id'] ?? '';
$conn->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/karte/favicon.php">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>学籍管理 — 生徒カルテ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo','Noto Sans JP',sans-serif;background:#d0d4dc;min-height:100vh;font-size:13px;color:#1a2240;}

/* トップバー */
.fm-topbar{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);color:#fff;padding:4px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:2px solid #0f1e40;min-height:44px;position:sticky;top:0;z-index:100;}
.fm-topbar-left{display:flex;align-items:center;gap:10px;}
.fm-topbar-title{font-size:1.05rem;font-weight:900;letter-spacing:.04em;color:#e8ecff;display:flex;align-items:center;gap:7px;}
.fm-topbar-title .dot{width:8px;height:8px;border-radius:50%;background:#6ee7b7;display:inline-block;}
.fm-topbar-name{color:#c4d4ff;font-size:.83rem;font-weight:600;}
.fm-topbar-right{display:flex;gap:6px;align-items:center;}
.fm-btn-top{padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#e8ecff;cursor:pointer;font-size:.78rem;font-family:inherit;text-decoration:none;transition:background .15s;white-space:nowrap;display:inline-flex;align-items:center;gap:4px;}
.fm-btn-top:hover{background:rgba(255,255,255,.25);}
.kebab-menu{position:relative;}
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

.container{max-width:1080px;margin:0 auto;padding:14px 16px 48px;}

/* メインパネル */
.fm-panel-wrap{background:#f0f2f8;border:2px solid #aab0cc;border-radius:4px;}
.fm-panel-header{background:#3b4f8a;padding:6px 12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.fm-panel-header-title{color:#dce4ff;font-size:.83rem;font-weight:700;flex:1;}
.search-input{padding:5px 10px;border:1px solid #6a7ab0;border-radius:4px;font-size:.82rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;min-width:160px;flex:1;}
.search-input:focus{border-color:#8ba4ff;}
.filter-select{padding:5px 8px;border:1px solid #6a7ab0;border-radius:4px;font-size:.8rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;cursor:pointer;}
.fm-add-btn{padding:5px 13px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:4px;color:#fff;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;}
.fm-add-btn:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}

/* テーブル */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.82rem;}
thead tr{background:#3b4f8a;}
th{padding:7px 10px;text-align:left;font-size:.71rem;font-weight:700;color:#dce4ff;border:1px solid #263570;white-space:nowrap;letter-spacing:.03em;}
tbody tr{background:#fff;border-bottom:1px solid #d0d4e0;transition:background .1s;}
tbody tr:nth-child(even){background:#f5f6fb;}
tbody tr:hover{background:#e8ecff;}
td{padding:8px 10px;color:#1a2240;vertical-align:middle;border-right:1px solid #e4e6f0;}
td:last-child{border-right:none;}
.gakno-chip{color:#3b4f8a;font-weight:700;font-size:.8rem;}
.gakunen-chip{background:#dde3f5;color:#2c3e6b;padding:2px 8px;border-radius:3px;font-size:.76rem;font-weight:700;display:inline-block;border:1px solid #aab0cc;}
.status-chip{padding:2px 8px;border-radius:3px;font-size:.75rem;font-weight:600;border:1px solid;}
.status-在学{background:#d4f0dd;color:#1a6638;border-color:#8acea0;}
.status-転出{background:#fef3c7;color:#7a5010;border-color:#d4aa60;}
.status-退学{background:#fde0e0;color:#7a2020;border-color:#c08080;}
.status-卒業{background:#dbeafe;color:#1a3a6e;border-color:#7ab0e0;}
.btn-sm{padding:3px 9px;border-radius:3px;font-size:.75rem;cursor:pointer;font-family:inherit;border:1px solid;}
.btn-edit{background:#e8ecf8;border-color:#aab0cc;color:#2c3e6b;} .btn-edit:hover{background:#dde3f5;}
.btn-del{background:#f8ecec;border-color:#c0a0a0;color:#9b3030;} .btn-del:hover{background:#fce0e0;}
.empty{text-align:center;padding:48px;color:#7a82a0;}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.modal-overlay.show{display:flex;}
.modal{background:#f0f2f8;border:2px solid #aab0cc;border-radius:6px;width:92%;max-width:640px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.4);animation:mi .15s ease;}
@keyframes mi{from{transform:scale(.95);opacity:0}to{transform:scale(1);opacity:1}}
.modal-head{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);padding:8px 14px;color:#e8ecff;font-size:.95rem;font-weight:700;border-radius:4px 4px 0 0;}
.modal-body{padding:16px 14px;}
.modal-section{font-size:.72rem;font-weight:700;color:#3b4f8a;text-transform:uppercase;letter-spacing:.06em;margin:14px 0 8px;padding-bottom:3px;border-bottom:1.5px solid #aab0cc;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.form-row{display:flex;flex-direction:column;gap:3px;}
.form-row.full{grid-column:1/-1;}
.form-row label{font-size:.72rem;font-weight:700;color:#5a6080;text-transform:uppercase;letter-spacing:.04em;}
.form-row input,.form-row select,.form-row textarea{padding:7px 10px;border:1px solid #aab0cc;border-radius:4px;font-size:.88rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;}
.form-row input:focus,.form-row select:focus,.form-row textarea:focus{border-color:#546099;}
.form-row textarea{resize:vertical;min-height:60px;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;padding:8px 14px 12px;border-top:1px solid #d0d4e0;}
.btn-cancel{padding:6px 14px;border:1px solid #aab0cc;border-radius:4px;background:#e4e7f0;cursor:pointer;font-size:.82rem;font-family:inherit;color:#3a4060;}
.btn-cancel:hover{background:#d4d8e8;}
.btn-save{padding:6px 16px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:4px;color:#fff;cursor:pointer;font-weight:700;font-size:.82rem;font-family:inherit;}
.btn-save:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}

/* 年度パネル */
.nendo-panel{background:#e8ecf8;border:1px solid #aab0cc;border-radius:4px;padding:12px;margin-top:12px;}
.nendo-panel h4{font-size:.82rem;font-weight:700;color:#2c3e6b;margin-bottom:8px;}
.nendo-row{display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #c8cce0;font-size:.82rem;}
.nendo-row:last-child{border-bottom:none;}
.nendo-year{font-weight:700;color:#3b4f8a;min-width:46px;}
.nendo-info{flex:1;color:#3a4060;}
.nendo-add-form{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;align-items:flex-end;}
.nendo-add-form input,.nendo-add-form select{padding:5px 8px;border:1px solid #aab0cc;border-radius:4px;font-size:.82rem;font-family:inherit;outline:none;min-width:70px;background:#fff;color:#1a2240;}
.nendo-add-form input:focus,.nendo-add-form select:focus{border-color:#546099;}
.btn-nendo-add{padding:5px 12px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;color:#fff;border-radius:4px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit;}
.btn-nendo-add:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}

.table-wrap{-webkit-overflow-scrolling:touch;}

@media(max-width:768px){
  .fm-topbar-name{display:none;}
  .container{padding:10px 12px 40px;}
}
@media(max-width:480px){
  body{font-size:12px;}
  .fm-topbar{padding:4px 8px;}
  .fm-topbar-title{font-size:.95rem;}
  .fm-btn-top{font-size:.7rem;padding:4px 8px;}
  .fm-panel-header{flex-wrap:wrap;padding:6px 8px;gap:5px;}
  .search-input{width:100%;}
  table{font-size:.76rem;}
  th,td{padding:6px 7px;}
  .btn-sm{font-size:.72rem;padding:2px 7px;}
  .form-grid{grid-template-columns:1fr;}
  .form-row.full{grid-column:1;}
  .nendo-add-form{gap:5px;}
  .nendo-add-form input,.nendo-add-form select{min-width:60px;}
  .modal{width:96%;}
  .modal-body{padding:12px;}
}
</style>
</head>
<body>
<div class="fm-topbar">
  <div class="fm-topbar-left">
    <div class="fm-topbar-title"><span class="dot"></span>生徒カルテ</div>
    <span class="fm-topbar-name"><?= $teacher ?> 先生</span>
  </div>
  <div class="fm-topbar-right">
    <div class="kebab-menu">
      <button class="kebab-btn" onclick="toggleKebab(event)" title="メニュー"><span></span><span></span><span></span></button>
      <div class="kebab-dropdown" id="kebabDropdown">
        <?php if($firstSid):?><a href="/karte/karte_detail.php?id=<?= urlencode($firstSid) ?>">🏫 生徒情報</a><?php endif;?>
        <?php if($firstSid):?><a href="/karte/karte_detail.php?id=<?= urlencode($firstSid) ?>&list=1">📋 一覧表示</a><?php endif;?>
        <a href="/karte/home.php">🏠 HOME</a>
        <?php if($firstSid):?><a href="/karte/karte_card.php?id=<?= urlencode($firstSid) ?>">🖨 印刷・PDF</a><?php endif;?>
        <a class="current-page">📚 学籍管理</a>
        <a href="/karte/student_manager.php">👥 生徒管理</a>
        <a href="/karte/backup.php">🗄️ バックアップ</a>
        <a href="/karte/account.php">⚙ アカウント</a>
        <a href="/karte/logout.php">🚪 ログアウト</a>
      </div>
    </div>
  </div>
</div>

<div class="container">
  <div class="fm-panel-wrap">
    <div class="fm-panel-header">
      <span class="fm-panel-header-title">学籍管理</span>
      <input type="text" class="search-input" id="searchInput" placeholder="学籍番号・氏名・ふりがなで検索…">
      <select class="filter-select" id="nendoFilter">
        <option value="">全年度</option>
      </select>
      <select class="filter-select" id="statusFilter">
        <option value="">全状態</option>
        <option>在学</option><option>卒業</option><option>転出</option><option>退学</option>
      </select>
      <button class="fm-add-btn" id="btnAdd">＋ 学籍を登録</button>
      <a href="/karte/api/gakuseki.php?action=csv_export" class="fm-add-btn" style="background:linear-gradient(180deg,#3a7d44 0%,#256030 100%);border-color:#1a4522;text-decoration:none;">↓ CSV書出</a>
      <button class="fm-add-btn" id="btnImport" style="background:linear-gradient(180deg,#7a5c1e 0%,#5a4010 100%);border-color:#3a280a;">↑ CSV読込</button>
      <button class="fm-add-btn" id="btnDeleteAll" style="background:linear-gradient(180deg,#8b1a1a 0%,#5a0a0a 100%);border-color:#3a0000;">⚠ 全件削除</button>
      <input type="file" id="csvFileInput" accept=".csv" style="display:none">
    </div>
    <div class="table-wrap">
      <table id="gakTable">
        <thead>
          <tr>
            <th>学籍番号</th>
            <th>氏名</th>
            <th>入学年度</th>
            <th>現在の学年/組/番</th>
            <th>担任</th>
            <th>状態</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="empty" id="emptyMsg" style="display:none">学籍データがありません。「学籍を登録」から追加してください。</div>
  </div>
</div>

<!-- 学籍編集モーダル -->
<div class="modal-overlay" id="modal">
  <div class="modal">
    <div class="modal-head" id="modalTitle">学籍を登録</div>
    <div class="modal-body">
    <input type="hidden" id="m-gakno-orig" value="">

    <div class="modal-section">基本情報</div>
    <div class="form-grid">
      <div class="form-row">
        <label>学籍番号 *</label>
        <input type="text" id="m-gakno" placeholder="例: 20210001">
      </div>
      <div class="form-row">
        <label>学籍状態</label>
        <select id="m-status">
          <option value="">—</option>
          <option>在学</option><option>卒業</option><option>転出</option><option>退学</option>
        </select>
      </div>
      <div class="form-row">
        <label>氏名 *</label>
        <input type="text" id="m-name" placeholder="新垣 拓海">
      </div>
      <div class="form-row">
        <label>ふりがな</label>
        <input type="text" id="m-furigana" placeholder="あらかき たくみ">
      </div>
      <div class="form-row">
        <label>性別</label>
        <select id="m-seibetu">
          <option value="">—</option>
          <option value="男">男</option>
          <option value="女">女</option>
          <option value="その他">その他</option>
        </select>
      </div>
      <div class="form-row">
        <label>生年月日</label>
        <input type="date" id="m-birthday">
      </div>
    </div>

    <div class="modal-section">住所・連絡先</div>
    <div class="form-grid">
      <div class="form-row">
        <label>郵便番号</label>
        <input type="text" id="m-yuubin" placeholder="901-2112">
      </div>
      <div class="form-row full">
        <label>住所</label>
        <input type="text" id="m-jyusyo" placeholder="浦添市沢岻1-9-27 101号">
      </div>
      <div class="form-row">
        <label>電話1（保護者）</label>
        <input type="text" id="m-tel1">
      </div>
      <div class="form-row">
        <label>電話2</label>
        <input type="text" id="m-tel2">
      </div>
    </div>

    <div class="modal-section">保護者情報</div>
    <div class="form-grid">
      <div class="form-row">
        <label>保護者名</label>
        <input type="text" id="m-hogosya">
      </div>
      <div class="form-row">
        <label>ふりがな</label>
        <input type="text" id="m-hogokana">
      </div>
      <div class="form-row">
        <label>続柄</label>
        <select id="m-zokugara">
          <option value="">—</option>
          <option>父</option><option>母</option><option>祖父</option><option>祖母</option><option>その他</option>
        </select>
      </div>
      <div class="form-row">
        <label>保護者郵便番号</label>
        <input type="text" id="m-hogosya_yuubin" placeholder="900-0000">
      </div>
      <div class="form-row full">
        <label>保護者住所</label>
        <input type="text" id="m-hogosya_jyusyo">
      </div>
      <div class="form-row">
        <label>保護者住所１</label>
        <input type="text" id="m-hogosya_addr1">
      </div>
      <div class="form-row">
        <label>保護者住所２</label>
        <input type="text" id="m-hogosya_addr2">
      </div>
    </div>

    <div class="modal-section">在籍期間</div>
    <div class="form-grid">
      <div class="form-row">
        <label>入学年度</label>
        <input type="number" id="m-nyunendo" placeholder="2021" min="2000" max="2099">
      </div>
      <div class="form-row">
        <label>入学日</label>
        <input type="date" id="m-nyugaku">
      </div>
      <div class="form-row">
        <label>卒業（予定）日</label>
        <input type="date" id="m-sotsugyo">
      </div>
    </div>

    <div class="modal-section">その他</div>
    <div class="form-grid">
      <div class="form-row full">
        <label>出身中学校</label>
        <input type="text" id="m-shusshin_chugaku" placeholder="〇〇中学校">
      </div>
    </div>

    <div class="modal-section">備考</div>
    <div class="form-row full">
      <label>備考</label>
      <textarea id="m-notes" rows="3" placeholder="家庭状況・支援状況など"></textarea>
    </div>

    <!-- 年度別情報（編集時のみ表示） -->
    <div id="nendoSection" style="display:none">
      <div class="modal-section">年度別 クラス・担任情報</div>
      <div id="nendoList" class="nendo-panel">
        <h4>登録済み年度</h4>
        <div id="nendoRows"></div>
      </div>
      <div class="nendo-panel" style="margin-top:10px;">
        <h4>年度を追加 / 更新</h4>
        <div class="nendo-add-form">
          <div><label style="font-size:.7rem;color:#5a6080">年度</label><br>
            <input type="number" id="n-nendo" placeholder="2021" min="2000" max="2099" style="width:88px"></div>
          <div><label style="font-size:.7rem;color:#5a6080">学年</label><br>
            <select id="n-gakunen" style="width:76px">
              <option value="">—</option>
              <option value="1">1年</option><option value="2">2年</option><option value="3">3年</option>
            </select></div>
          <div><label style="font-size:.7rem;color:#5a6080">組</label><br>
            <input type="text" id="n-class" placeholder="1組" style="width:66px"></div>
          <div><label style="font-size:.7rem;color:#5a6080">番号</label><br>
            <input type="number" id="n-bango" placeholder="1" min="1" max="99" style="width:66px"></div>
          <div><label style="font-size:.7rem;color:#5a6080">担任</label><br>
            <select id="n-teacher">
              <option value="">—</option>
              <?php foreach($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['display_name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label style="font-size:.7rem;color:#5a6080">進級状態</label><br>
            <select id="n-sinkyu">
              <option value="">—</option>
              <option>進級</option><option>留年</option><option>転出</option><option>退学</option><option>卒業</option>
            </select></div>
          <button class="btn-nendo-add" id="btnAddNendo">追加</button>
        </div>
      </div>
    </div>

    </div><!-- /.modal-body -->
    <div class="modal-btns">
      <button class="btn-cancel" id="btnCancel">キャンセル</button>
      <button class="btn-save" id="btnSave">保存する</button>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= generateCsrfToken() ?>';
let allRows = [];
let currentGakno = null;

async function loadList() {
  const nendo = document.getElementById('nendoFilter').value;
  const url = '/karte/api/gakuseki.php?action=list' + (nendo ? '&nendo='+nendo : '');
  const res = await fetch(url);
  const data = await res.json();
  if (!data.success) return;
  allRows = data.rows;
  filterTable();
}

async function loadNendos() {
  const res = await fetch('/karte/api/gakuseki.php?action=nendo_list');
  const data = await res.json();
  const sel = document.getElementById('nendoFilter');
  sel.innerHTML = '<option value="">全年度</option>';
  (data.nendos||[]).forEach(n => {
    const o = document.createElement('option');
    o.value = n; o.textContent = n + '年度';
    sel.appendChild(o);
  });
}

function filterTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  const st = document.getElementById('statusFilter').value;
  const rows = allRows.filter(r =>
    (!q || (r.gakno||'').toLowerCase().includes(q) || (r.name||'').toLowerCase().includes(q) || (r.furigana||'').toLowerCase().includes(q)) &&
    (!st || r.gakuseki_status === st)
  );
  renderTable(rows);
}

function renderTable(rows) {
  const tbody = document.getElementById('tbody');
  const empty = document.getElementById('emptyMsg');
  const table = document.getElementById('gakTable');
  if (!rows.length) { tbody.innerHTML=''; table.style.display='none'; empty.style.display=''; return; }
  table.style.display=''; empty.style.display='none';
  tbody.innerHTML = rows.map(r => {
    const gakunenInfo = r.gakunen ? `${r.gakunen}年${r.class_no ? r.class_no+'組' : ''}${r.bango ? r.bango+'番' : ''}` : '—';
    const status = r.gakuseki_status || '';
    return `<tr>
      <td><span class="gakno-chip">${esc(r.gakno)}</span></td>
      <td><strong>${esc(r.name)}</strong><br><span style="font-size:.76rem;color:#94a3b8">${esc(r.furigana||'')}</span></td>
      <td>${r.nyunendo ? r.nyunendo+'年度' : '—'}</td>
      <td>${r.nendo ? `<span class="gakunen-chip">${r.nendo}年度</span> ${gakunenInfo}` : '—'}</td>
      <td>${esc(r.tanninmei||'—')}</td>
      <td>${status ? `<span class="status-chip status-${esc(status)}">${esc(status)}</span>` : '—'}</td>
      <td style="white-space:nowrap">
        <button class="btn-sm btn-edit" data-gakno="${esc(r.gakno)}" onclick="openEdit(this.dataset.gakno)">編集</button>
        <button class="btn-sm btn-del" data-gakno="${esc(r.gakno)}" data-name="${esc(r.name)}" onclick="delGak(this.dataset.gakno,this.dataset.name)">削除</button>
      </td>
    </tr>`;
  }).join('');
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function esc2(s){return String(s||'').replace(/'/g,"\\'");}

function openAdd() {
  currentGakno = null;
  document.getElementById('modalTitle').textContent = '学籍を登録';
  document.getElementById('m-gakno-orig').value = '';
  document.getElementById('m-gakno').readOnly = false;
  ['gakno','name','furigana','yuubin','jyusyo','tel1','tel2','hogosya','hogokana','hogosya_yuubin','hogosya_jyusyo','hogosya_addr1','hogosya_addr2','shusshin_chugaku','notes'].forEach(f => document.getElementById('m-'+f).value='');
  ['seibetu','zokugara','status'].forEach(f => document.getElementById('m-'+f).value='');
  ['birthday','nyugaku','sotsugyo'].forEach(f => document.getElementById('m-'+f).value='');
  document.getElementById('m-nyunendo').value='';
  document.getElementById('nendoSection').style.display='none';
  document.getElementById('modal').classList.add('show');
}

async function openEdit(gakno) {
  currentGakno = gakno;
  const res = await fetch('/karte/api/gakuseki.php?action=get&gakno='+encodeURIComponent(gakno));
  const data = await res.json();
  if (!data.success) { alert(data.error); return; }
  const g = data.gakuseki;
  document.getElementById('modalTitle').textContent = '学籍を編集';
  document.getElementById('m-gakno-orig').value = g.gakno;
  document.getElementById('m-gakno').value = g.gakno;
  document.getElementById('m-gakno').readOnly = true;
  document.getElementById('m-name').value = g.name||'';
  document.getElementById('m-furigana').value = g.furigana||'';
  document.getElementById('m-seibetu').value = g.seibetu||'';
  document.getElementById('m-birthday').value = g.birthday||'';
  document.getElementById('m-yuubin').value = g.yuubin||'';
  document.getElementById('m-jyusyo').value = g.jyusyo||'';
  document.getElementById('m-tel1').value = g.tel1||'';
  document.getElementById('m-tel2').value = g.tel2||'';
  document.getElementById('m-hogosya').value = g.hogosya||'';
  document.getElementById('m-hogokana').value = g.hogokana||'';
  document.getElementById('m-zokugara').value = g.zokugara||'';
  document.getElementById('m-nyunendo').value = g.nyunendo||'';
  document.getElementById('m-nyugaku').value = g.nyugaku||'';
  document.getElementById('m-sotsugyo').value = g.sotsugyo||'';
  document.getElementById('m-status').value = g.gakuseki_status||'';
  document.getElementById('m-shusshin_chugaku').value  = g.shusshin_chugaku||'';
  document.getElementById('m-hogosya_yuubin').value    = g.hogosya_yuubin||'';
  document.getElementById('m-hogosya_jyusyo').value    = g.hogosya_jyusyo||'';
  document.getElementById('m-hogosya_addr1').value     = g.hogosya_addr1||'';
  document.getElementById('m-hogosya_addr2').value     = g.hogosya_addr2||'';
  document.getElementById('m-notes').value = g.notes||'';
  document.getElementById('nendoSection').style.display='';
  renderNendoList(data.nendo_list);
  document.getElementById('modal').classList.add('show');
}

function renderNendoList(list) {
  const container = document.getElementById('nendoRows');
  if (!list.length) { container.innerHTML='<p style="color:#94a3b8;font-size:.84rem">登録なし</p>'; return; }
  container.innerHTML = list.map(n => `
    <div class="nendo-row">
      <span class="nendo-year">${n.nendo}年度</span>
      <span class="nendo-info">${n.gakunen||'?'}年${n.class_no ? n.class_no+'組' : ''}${n.bango ? n.bango+'番' : ''} ${n.tanninmei ? '/ 担任:'+n.tanninmei : ''} ${n.sinkyu ? '['+n.sinkyu+']' : ''}</span>
      <button class="btn-sm btn-del" onclick="delNendo('${esc2(currentGakno)}',${n.nendo})">削除</button>
    </div>
  `).join('');
}

document.getElementById('btnAddNendo').onclick = async () => {
  if (!currentGakno) return;
  const fd = new FormData();
  fd.append('action','save_nendo'); fd.append('csrf_token',CSRF);
  fd.append('gakno', currentGakno);
  fd.append('nendo', document.getElementById('n-nendo').value);
  fd.append('gakunen', document.getElementById('n-gakunen').value);
  fd.append('class_no', document.getElementById('n-class').value);
  fd.append('bango', document.getElementById('n-bango').value);
  fd.append('teacher_id', document.getElementById('n-teacher').value);
  fd.append('sinkyu', document.getElementById('n-sinkyu').value);
  const res = await fetch('/karte/api/gakuseki.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) {
    const res2 = await fetch('/karte/api/gakuseki.php?action=get&gakno='+encodeURIComponent(currentGakno));
    const d2 = await res2.json();
    renderNendoList(d2.nendo_list);
    await loadNendos();
  } else alert(data.error||'エラー');
};

async function delNendo(gakno, nendo) {
  if (!confirm(`${nendo}年度のデータを削除しますか？`)) return;
  const fd = new FormData();
  fd.append('action','delete_nendo'); fd.append('csrf_token',CSRF);
  fd.append('gakno',gakno); fd.append('nendo',nendo);
  await fetch('/karte/api/gakuseki.php',{method:'POST',body:fd});
  const res = await fetch('/karte/api/gakuseki.php?action=get&gakno='+encodeURIComponent(gakno));
  const data = await res.json();
  renderNendoList(data.nendo_list);
}

document.getElementById('btnSave').onclick = async () => {
  const fd = new FormData();
  fd.append('action','save_gakuseki'); fd.append('csrf_token',CSRF);
  ['gakno','name','furigana','yuubin','jyusyo','tel1','tel2','hogosya','hogokana','hogosya_yuubin','hogosya_jyusyo','hogosya_addr1','hogosya_addr2','shusshin_chugaku','notes'].forEach(f =>
    fd.append(f, document.getElementById('m-'+f).value));
  ['seibetu','zokugara'].forEach(f => fd.append(f, document.getElementById('m-'+f).value));
  ['birthday','nyugaku','sotsugyo'].forEach(f => fd.append(f, document.getElementById('m-'+f).value));
  fd.append('nyunendo', document.getElementById('m-nyunendo').value);
  fd.append('gakuseki_status', document.getElementById('m-status').value);
  const res = await fetch('/karte/api/gakuseki.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) {
    document.getElementById('modal').classList.remove('show');
    await loadList();
    await loadNendos();
  } else alert(data.error||'エラー');
};

async function delGak(gakno, name) {
  if (!confirm(`「${name}」の学籍データを削除しますか？\n年度別データも含めてすべて削除されます。`)) return;
  const fd = new FormData();
  fd.append('action','delete_gakuseki'); fd.append('csrf_token',CSRF); fd.append('gakno',gakno);
  const res = await fetch('/karte/api/gakuseki.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) { await loadList(); await loadNendos(); }
  else alert(data.error||'エラー');
}

document.getElementById('btnAdd').onclick = openAdd;
document.getElementById('btnCancel').onclick = () => document.getElementById('modal').classList.remove('show');
document.getElementById('modal').addEventListener('click', e => { if(e.target===e.currentTarget) e.currentTarget.classList.remove('show'); });
document.getElementById('searchInput').addEventListener('input', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);
document.getElementById('nendoFilter').addEventListener('change', loadList);

/* ===== 全件削除 ===== */
document.getElementById('btnDeleteAll').onclick = async () => {
  const cnt = document.querySelectorAll('#tbody tr').length;
  if (!confirm(`学籍データを全件（${cnt}件）削除しますか？\nこの操作は取り消せません。`)) return;
  if (!confirm('本当に全件削除しますか？')) return;
  const fd = new FormData();
  fd.append('action','delete_all'); fd.append('csrf_token',CSRF);
  const res  = await fetch('/karte/api/gakuseki.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) { alert(`${data.deleted}件削除しました`); await loadList(); await loadNendos(); }
  else alert(data.error||'エラー');
};

/* ===== CSV インポート ===== */
document.getElementById('btnImport').onclick = () => document.getElementById('csvFileInput').click();
document.getElementById('csvFileInput').onchange = async function() {
  const file = this.files[0];
  if (!file) return;
  if (!confirm(`「${file.name}」を読み込んで学籍データを更新しますか？\n既存の学籍番号は上書きされます。`)) {
    this.value = ''; return;
  }
  const fd = new FormData();
  fd.append('action', 'csv_import');
  fd.append('csrf_token', CSRF);
  fd.append('csv', file);
  try {
    const res  = await fetch('/karte/api/gakuseki.php', {method:'POST', body:fd});
    const data = await res.json();
    if (!data.success) { alert('エラー: ' + (data.error || '不明')); return; }
    let msg = `読込完了\n新規追加: ${data.inserted}件\n更新: ${data.updated}件`;
    if (data.errors && data.errors.length) msg += '\n\n警告:\n' + data.errors.join('\n');
    alert(msg);
    await loadList();
  } catch(e) {
    alert('読込中にエラーが発生しました: ' + e.message);
  }
  this.value = '';
};

loadNendos().then(() => loadList());

function toggleKebab(e){e.stopPropagation();document.getElementById('kebabDropdown').classList.toggle('open');}
document.addEventListener('click',function(){const d=document.getElementById('kebabDropdown');if(d)d.classList.remove('open');});
</script>
</body>
</html>
