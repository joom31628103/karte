<?php
require_once 'config.php';
requireLogin();
$conn = getDB();
$teacher = htmlspecialchars($_SESSION['teacher_name']);

// クラス一覧
$classRows = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name!='' ORDER BY class_name");
$classes = [];
while ($r = $classRows->fetch_assoc()) $classes[] = $r['class_name'];

// 統計
$total   = (int)$conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'];
$recCnt  = (int)$conn->query("SELECT COUNT(*) AS c FROM karte_records")->fetch_assoc()['c'];
$attCnt  = (int)$conn->query("SELECT COUNT(*) AS c FROM karte_attendance")->fetch_assoc()['c'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>生徒カルテ 一覧</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Noto Sans JP',sans-serif;background:#0f0a1e;min-height:100vh;color:#1e293b;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 15% 0%,#4c1d95 0%,transparent 55%),radial-gradient(ellipse at 85% 0%,#1e3a8a 0%,transparent 55%),radial-gradient(ellipse at 50% 110%,#312e81 0%,transparent 60%);z-index:0;pointer-events:none;}
.topbar{position:sticky;top:0;z-index:100;background:rgba(15,10,30,.78);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.08);padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;}
.topbar-left{display:flex;align-items:center;gap:12px;}
.topbar-badge{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;padding:4px 13px;border-radius:20px;font-size:.74rem;font-weight:700;letter-spacing:.05em;}
.topbar-name{color:#fff;font-size:.95rem;font-weight:600;}
.topbar-right{display:flex;align-items:center;gap:10px;}
.btn-logout{padding:7px 18px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);border-radius:8px;cursor:pointer;font-size:.8rem;font-family:inherit;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;}
.btn-logout:hover{background:rgba(255,255,255,.16);color:#fff;}
.container{position:relative;z-index:1;max-width:1000px;margin:0 auto;padding:0 20px 64px;}
.page-header{padding:34px 0 22px;text-align:center;color:#fff;}
.page-header h1{font-size:1.65rem;font-weight:800;background:linear-gradient(135deg,#fff 0%,#c4b5fd 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.page-header p{margin-top:6px;font-size:.87rem;color:rgba(255,255,255,.5);}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;}
.stat-card{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:18px 14px;text-align:center;backdrop-filter:blur(8px);}
.stat-num{font-size:1.9rem;font-weight:800;color:#fff;line-height:1;margin-bottom:4px;}
.stat-label{font-size:.71rem;color:rgba(255,255,255,.5);font-weight:500;}
.main-card{background:rgba(255,255,255,.96);border-radius:20px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.toolbar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;}
.search-input{flex:1;min-width:200px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;color:#1e293b;outline:none;transition:border-color .2s;}
.search-input:focus{border-color:#7c3aed;}
.filter-select{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.88rem;font-family:inherit;color:#1e293b;background:#fff;outline:none;cursor:pointer;}
.btn-add{padding:10px 18px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;}
.btn-add:hover{opacity:.9;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.87rem;}
thead tr{border-bottom:2px solid #e2e8f0;}
th{padding:10px 12px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s;cursor:pointer;}
tbody tr:hover{background:#f8f5ff;}
td{padding:11px 12px;color:#1e293b;}
.td-id{color:#7c3aed;font-weight:700;font-size:.82rem;}
.td-name{font-weight:700;}
.td-furi{color:#94a3b8;font-size:.78rem;}
.td-class{background:#ede9fe;color:#6d28d9;padding:3px 10px;border-radius:20px;font-size:.76rem;font-weight:700;display:inline-block;}
.badge-count{background:#f1f5f9;color:#64748b;padding:2px 9px;border-radius:12px;font-size:.76rem;font-weight:600;}
.badge-count.has{background:#ede9fe;color:#7c3aed;}
.empty{text-align:center;padding:48px;color:#94a3b8;font-size:.9rem;}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-overlay.show{display:flex;}
.modal{background:#fff;border-radius:20px;padding:32px 28px;max-width:460px;width:90%;box-shadow:0 40px 100px rgba(0,0,0,.4);animation:mi .18s ease;}
@keyframes mi{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}
.modal h3{font-size:1.1rem;color:#1e293b;margin-bottom:20px;font-weight:800;}
.form-row{margin-bottom:14px;}
.form-row label{display:block;font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
.form-row input,.form-row select{width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;color:#1e293b;outline:none;transition:border-color .2s;}
.form-row input:focus,.form-row select:focus{border-color:#7c3aed;}
.form-2col{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal-btns{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
.btn-cancel{padding:10px 20px;border:1.5px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;font-size:.88rem;font-family:inherit;}
.btn-cancel:hover{background:#f8fafc;}
.btn-save{padding:10px 22px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem;font-family:inherit;}
.btn-save:hover{opacity:.9;}
@media(max-width:640px){.stats{grid-template-columns:1fr 1fr}.toolbar{flex-direction:column}.search-input{min-width:0;width:100%}}
</style>
</head>
<body>
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-badge">📋 生徒カルテ</span>
    <span class="topbar-name"><?= $teacher ?> 先生</span>
  </div>
  <div class="topbar-right">
    <a href="/karte/gakuseki.php" class="btn-logout">📚 学籍管理</a>
    <a href="/karte/student_manager.php" class="btn-logout">👥 生徒管理</a>
    <a href="/karte/logout.php" class="btn-logout">ログアウト</a>
  </div>
</div>

<div class="container">
  <div class="page-header">
    <h1>生徒カルテ 一覧</h1>
    <p>生徒ごとの基本情報・指導記録・面談記録を管理します</p>
  </div>

  <div class="stats">
    <div class="stat-card">
      <div class="stat-num" id="s-total"><?= $total ?></div>
      <div class="stat-label">登録生徒数</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" id="s-rec"><?= $recCnt ?></div>
      <div class="stat-label">指導記録</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" id="s-att"><?= $attCnt ?></div>
      <div class="stat-label">出欠記録</div>
    </div>
  </div>

  <div class="main-card">
    <div class="toolbar">
      <input type="text" class="search-input" id="searchInput" placeholder="名前・ふりがな・番号で検索…">
      <select class="filter-select" id="classFilter">
        <option value="">全クラス</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn-add" id="btnAddStudent">＋ 生徒を追加</button>
    </div>
    <div class="table-wrap">
      <table id="studentTable">
        <thead>
          <tr>
            <th>番号</th>
            <th>氏名</th>
            <th>クラス</th>
            <th>指導記録</th>
            <th>出欠記録</th>
            <th>最終記録日</th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="empty" id="emptyMsg" style="display:none">生徒が登録されていません。「生徒を追加」から登録してください。</div>
  </div>
</div>

<!-- 生徒追加モーダル -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <h3>生徒を追加</h3>
    <div class="form-2col">
      <div class="form-row">
        <label>学籍番号 *</label>
        <input type="text" id="f-sid" placeholder="例: 1101">
      </div>
      <div class="form-row">
        <label>クラス</label>
        <input type="text" id="f-class" placeholder="例: 1年1組">
      </div>
    </div>
    <div class="form-2col">
      <div class="form-row">
        <label>氏名 *</label>
        <input type="text" id="f-name" placeholder="山田 太郎">
      </div>
      <div class="form-row">
        <label>ふりがな</label>
        <input type="text" id="f-furi" placeholder="やまだ たろう">
      </div>
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" id="btnCancelAdd">キャンセル</button>
      <button class="btn-save" id="btnSaveAdd">追加する</button>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= generateCsrfToken() ?>';
let allRows = [];

async function loadStudents() {
  const res = await fetch('/karte/api/karte.php?action=student_summary');
  const data = await res.json();
  if (!data.success) return;
  allRows = data.rows;
  renderTable(allRows);
}

function renderTable(rows) {
  const tbody = document.getElementById('tbody');
  const empty = document.getElementById('emptyMsg');
  const table = document.getElementById('studentTable');
  if (!rows.length) { tbody.innerHTML=''; table.style.display='none'; empty.style.display=''; return; }
  table.style.display=''; empty.style.display='none';
  tbody.innerHTML = rows.map(r => `
    <tr onclick="location.href='/karte/karte_detail.php?id=${encodeURIComponent(r.student_id)}'">
      <td><span class="td-id">${esc(r.student_id)}</span></td>
      <td>
        <div class="td-name">${esc(r.name)}</div>
        ${r.furigana ? `<div class="td-furi">${esc(r.furigana)}</div>` : ''}
      </td>
      <td>${r.class_name ? `<span class="td-class">${esc(r.class_name)}</span>` : '<span style="color:#cbd5e1">—</span>'}</td>
      <td><span class="badge-count ${r.rec_count>0?'has':''}">${r.rec_count}件</span></td>
      <td><span class="badge-count ${r.att_count>0?'has':''}">${r.att_count}件</span></td>
      <td style="color:#64748b;font-size:.82rem">${r.last_record || '—'}</td>
    </tr>
  `).join('');
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function filterTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  const cls = document.getElementById('classFilter').value;
  renderTable(allRows.filter(r =>
    (!q || r.student_id.toLowerCase().includes(q) || (r.name||'').toLowerCase().includes(q) || (r.furigana||'').toLowerCase().includes(q)) &&
    (!cls || r.class_name === cls)
  ));
}

document.getElementById('searchInput').addEventListener('input', filterTable);
document.getElementById('classFilter').addEventListener('change', filterTable);

// 追加モーダル
document.getElementById('btnAddStudent').onclick = () => document.getElementById('addModal').classList.add('show');
document.getElementById('btnCancelAdd').onclick  = () => document.getElementById('addModal').classList.remove('show');
document.getElementById('addModal').addEventListener('click', e => { if(e.target===e.currentTarget) e.currentTarget.classList.remove('show'); });

document.getElementById('btnSaveAdd').onclick = async () => {
  const sid   = document.getElementById('f-sid').value.trim();
  const name  = document.getElementById('f-name').value.trim();
  const cls   = document.getElementById('f-class').value.trim();
  const furi  = document.getElementById('f-furi').value.trim();
  if (!sid || !name) { alert('学籍番号と氏名は必須です。'); return; }
  const res = await fetch('/karte/api/students.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'add',student_id:sid,name,class_name:cls,furigana:furi,csrf_token:CSRF})
  });
  const data = await res.json();
  if (data.success) {
    document.getElementById('addModal').classList.remove('show');
    ['f-sid','f-name','f-class','f-furi'].forEach(id=>document.getElementById(id).value='');
    loadStudents();
  } else { alert(data.error || 'エラーが発生しました'); }
};

loadStudents();
</script>
</body>
</html>
