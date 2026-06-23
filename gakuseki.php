<?php
require_once 'config.php';
requireLogin();
$teacher = htmlspecialchars($_SESSION['teacher_name']);
$conn = getDB();
$teachers = $conn->query("SELECT id, display_name FROM teachers ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>学籍管理 — 生徒カルテ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Noto Sans JP',sans-serif;background:#0f0a1e;min-height:100vh;color:#1e293b;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 15% 0%,#4c1d95 0%,transparent 55%),radial-gradient(ellipse at 85% 0%,#1e3a8a 0%,transparent 55%),radial-gradient(ellipse at 50% 110%,#312e81 0%,transparent 60%);z-index:0;pointer-events:none;}
.topbar{position:sticky;top:0;z-index:100;background:rgba(15,10,30,.78);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.08);padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;}
.topbar-left{display:flex;align-items:center;gap:12px;}
.topbar-badge{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;padding:4px 13px;border-radius:20px;font-size:.74rem;font-weight:700;}
.topbar-name{color:#fff;font-size:.95rem;font-weight:600;}
.btn-nav{padding:7px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);border-radius:8px;cursor:pointer;font-size:.8rem;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn-nav:hover{background:rgba(255,255,255,.16);color:#fff;}
.container{position:relative;z-index:1;max-width:1080px;margin:0 auto;padding:0 20px 64px;}
.page-header{padding:28px 0 18px;text-align:center;color:#fff;}
.page-header h1{font-size:1.55rem;font-weight:800;background:linear-gradient(135deg,#fff 0%,#c4b5fd 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.page-header p{margin-top:5px;font-size:.83rem;color:rgba(255,255,255,.45);}

.toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
.search-input{flex:1;min-width:180px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;color:#1e293b;outline:none;}
.search-input:focus{border-color:#7c3aed;}
.filter-select{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.88rem;font-family:inherit;color:#1e293b;background:#fff;outline:none;}
.btn-primary{padding:10px 18px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:5px;}
.btn-primary:hover{opacity:.9;}

.main-card{background:rgba(255,255,255,.96);border-radius:20px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.86rem;}
thead tr{border-bottom:2px solid #e2e8f0;}
th{padding:9px 12px;text-align:left;font-size:.71rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s;}
tbody tr:hover{background:#f8f5ff;}
td{padding:10px 12px;color:#1e293b;vertical-align:middle;}
.gakno-chip{color:#7c3aed;font-weight:700;font-size:.82rem;}
.gakunen-chip{background:#ede9fe;color:#6d28d9;padding:2px 9px;border-radius:20px;font-size:.76rem;font-weight:700;display:inline-block;}
.status-chip{padding:2px 9px;border-radius:12px;font-size:.76rem;font-weight:600;}
.status-在学{background:#dcfce7;color:#15803d;}
.status-転出{background:#fef3c7;color:#92400e;}
.status-退学{background:#fee2e2;color:#dc2626;}
.status-卒業{background:#dbeafe;color:#1d4ed8;}
.btn-sm{padding:4px 10px;border-radius:8px;font-size:.75rem;cursor:pointer;font-family:inherit;}
.btn-edit{background:#fff;border:1px solid #bfdbfe;color:#2563eb;} .btn-edit:hover{background:#eff6ff;}
.btn-del{background:#fff;border:1px solid #fca5a5;color:#dc2626;} .btn-del:hover{background:#fef2f2;}
.empty{text-align:center;padding:48px;color:#94a3b8;}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-overlay.show{display:flex;}
.modal{background:#fff;border-radius:20px;padding:28px 24px;max-width:640px;width:92%;max-height:90vh;overflow-y:auto;box-shadow:0 40px 100px rgba(0,0,0,.4);animation:mi .18s ease;}
@keyframes mi{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}
.modal h3{font-size:1.1rem;color:#1e293b;margin-bottom:18px;font-weight:800;}
.modal-section{font-size:.78rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.06em;margin:16px 0 10px;padding-bottom:4px;border-bottom:1.5px solid #ede9fe;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-row{display:flex;flex-direction:column;gap:4px;}
.form-row.full{grid-column:1/-1;}
.form-row label{font-size:.74rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;}
.form-row input,.form-row select,.form-row textarea{padding:9px 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.9rem;font-family:inherit;color:#1e293b;outline:none;}
.form-row input:focus,.form-row select:focus,.form-row textarea:focus{border-color:#7c3aed;}
.form-row textarea{resize:vertical;min-height:64px;}
.modal-btns{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;}
.btn-cancel{padding:9px 18px;border:1.5px solid #e2e8f0;border-radius:9px;background:#fff;cursor:pointer;font-size:.88rem;font-family:inherit;}
.btn-save{padding:9px 20px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:9px;cursor:pointer;font-weight:700;font-size:.88rem;font-family:inherit;}

/* 年度サイドパネル */
.nendo-panel{background:#f8f5ff;border-radius:14px;padding:16px;margin-top:16px;}
.nendo-panel h4{font-size:.88rem;font-weight:700;color:#4c1d95;margin-bottom:10px;}
.nendo-row{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #ede9fe;font-size:.84rem;}
.nendo-row:last-child{border-bottom:none;}
.nendo-year{font-weight:700;color:#7c3aed;min-width:50px;}
.nendo-info{flex:1;color:#475569;}
.nendo-add-form{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:flex-end;}
.nendo-add-form input,.nendo-add-form select{padding:7px 9px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.84rem;font-family:inherit;outline:none;min-width:80px;}
.nendo-add-form input:focus,.nendo-add-form select:focus{border-color:#7c3aed;}
.btn-nendo-add{padding:7px 14px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer;}

@media(max-width:580px){.form-grid{grid-template-columns:1fr}.form-row.full{grid-column:1}}
</style>
</head>
<body>
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-badge">📋 生徒カルテ</span>
    <span class="topbar-name"><?= $teacher ?> 先生</span>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/karte/home.php" class="btn-nav">← カルテ一覧</a>
    <a href="/karte/logout.php" class="btn-nav">ログアウト</a>
  </div>
</div>

<div class="container">
  <div class="page-header">
    <h1>学籍管理</h1>
    <p>生徒の学籍台帳・年度別クラス情報を管理します</p>
  </div>

  <div class="main-card">
    <div class="toolbar">
      <input type="text" class="search-input" id="searchInput" placeholder="学籍番号・氏名・ふりがなで検索…">
      <select class="filter-select" id="nendoFilter">
        <option value="">全年度</option>
      </select>
      <select class="filter-select" id="statusFilter">
        <option value="">全状態</option>
        <option>在学</option><option>卒業</option><option>転出</option><option>退学</option>
      </select>
      <button class="btn-primary" id="btnAdd">＋ 学籍を登録</button>
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
    <h3 id="modalTitle">学籍を登録</h3>
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
          <div><label style="font-size:.74rem;color:#64748b">年度</label><br>
            <input type="number" id="n-nendo" placeholder="2021" min="2000" max="2099" style="width:90px"></div>
          <div><label style="font-size:.74rem;color:#64748b">学年</label><br>
            <select id="n-gakunen" style="width:80px">
              <option value="">—</option>
              <option value="1">1年</option><option value="2">2年</option><option value="3">3年</option>
            </select></div>
          <div><label style="font-size:.74rem;color:#64748b">組</label><br>
            <input type="text" id="n-class" placeholder="1組" style="width:70px"></div>
          <div><label style="font-size:.74rem;color:#64748b">番号</label><br>
            <input type="number" id="n-bango" placeholder="1" min="1" max="99" style="width:70px"></div>
          <div><label style="font-size:.74rem;color:#64748b">担任</label><br>
            <select id="n-teacher">
              <option value="">—</option>
              <?php foreach($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['display_name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div><label style="font-size:.74rem;color:#64748b">進級状態</label><br>
            <select id="n-sinkyu">
              <option value="">—</option>
              <option>進級</option><option>留年</option><option>転出</option><option>退学</option><option>卒業</option>
            </select></div>
          <button class="btn-nendo-add" id="btnAddNendo">追加</button>
        </div>
      </div>
    </div>

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
    const gakunenInfo = r.gakunen ? `${r.gakunen}年${r.class_no||''}${r.bango ? ' '+r.bango+'番' : ''}` : '—';
    const status = r.gakuseki_status || '';
    return `<tr>
      <td><span class="gakno-chip">${esc(r.gakno)}</span></td>
      <td><strong>${esc(r.name)}</strong><br><span style="font-size:.76rem;color:#94a3b8">${esc(r.furigana||'')}</span></td>
      <td>${r.nyunendo ? r.nyunendo+'年度' : '—'}</td>
      <td>${r.nendo ? `<span class="gakunen-chip">${r.nendo}年度</span> ${gakunenInfo}` : '—'}</td>
      <td>${esc(r.tanninmei||'—')}</td>
      <td>${status ? `<span class="status-chip status-${esc(status)}">${esc(status)}</span>` : '—'}</td>
      <td style="white-space:nowrap">
        <button class="btn-sm btn-edit" onclick="openEdit('${esc2(r.gakno)}')">編集</button>
        <button class="btn-sm btn-del" onclick="delGak('${esc2(r.gakno)}',${JSON.stringify(r.name)})">削除</button>
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
  ['gakno','name','furigana','yuubin','jyusyo','tel1','tel2','hogosya','hogokana','notes'].forEach(f => document.getElementById('m-'+f).value='');
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
      <span class="nendo-info">${n.gakunen||'?'}年 ${n.class_no||''} ${n.bango ? n.bango+'番' : ''} ${n.tanninmei ? '/ 担任:'+n.tanninmei : ''} ${n.sinkyu ? '['+n.sinkyu+']' : ''}</span>
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
  ['gakno','name','furigana','yuubin','jyusyo','tel1','tel2','hogosya','hogokana','notes'].forEach(f =>
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

loadNendos().then(() => loadList());
</script>
</body>
</html>
