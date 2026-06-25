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
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo','Noto Sans JP',sans-serif;background:#d0d4dc;min-height:100vh;font-size:13px;color:#1a2240;}

/* トップバー（FileMaker風濃紺） */
.fm-topbar{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);color:#fff;padding:4px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:2px solid #0f1e40;min-height:44px;position:sticky;top:0;z-index:100;}
.fm-topbar-left{display:flex;align-items:center;gap:10px;}
.fm-topbar-title{font-size:1.1rem;font-weight:900;letter-spacing:.04em;color:#e8ecff;display:flex;align-items:center;gap:8px;}
.fm-topbar-title .dot{width:8px;height:8px;border-radius:50%;background:#6ee7b7;display:inline-block;}
.fm-topbar-name{color:#c4d4ff;font-size:.85rem;font-weight:600;}
.fm-topbar-right{display:flex;gap:6px;align-items:center;}
/* ハンバーガーメニュー */
.kebab-menu{position:relative;}
.kebab-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);color:#e8ecff;border-radius:6px;padding:6px 10px;font-size:.9rem;cursor:pointer;line-height:1;font-family:inherit;display:flex;flex-direction:column;gap:4px;align-items:center;justify-content:center;width:38px;height:34px;}
.kebab-btn span{display:block;width:18px;height:2px;background:#e8ecff;border-radius:1px;}
.kebab-btn:hover{background:rgba(255,255,255,.25);}
.kebab-dropdown{display:none;position:absolute;top:calc(100% + 6px);right:0;background:linear-gradient(180deg,#2c3e6b,#1a2a55);border:1px solid rgba(255,255,255,.2);border-radius:8px;min-width:170px;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,.4);overflow:hidden;}
.kebab-dropdown.open{display:block;}
.kebab-dropdown a,.kebab-dropdown button{display:block;width:100%;padding:10px 16px;color:#e8ecff;text-decoration:none;font-size:.85rem;border:none;border-bottom:1px solid rgba(255,255,255,.08);background:none;text-align:left;cursor:pointer;font-family:inherit;box-sizing:border-box;}
.kebab-dropdown a:last-child,.kebab-dropdown button:last-child{border-bottom:none;}
.kebab-dropdown a:hover,.kebab-dropdown button:hover{background:rgba(255,255,255,.15);}

/* コンテンツ */
.container{max-width:1020px;margin:0 auto;padding:16px 16px 48px;}

/* 前回の続き バー */
.resume-bar{display:none;background:linear-gradient(90deg,#1a3a6b,#263570);border-bottom:2px solid #0f1e40;padding:8px 16px;align-items:center;gap:10px;flex-wrap:wrap;}
.resume-bar.show{display:flex;}
.resume-text{color:#c4d4ff;font-size:.82rem;flex:1;min-width:0;}
.resume-text strong{color:#e8ecff;}
.resume-btn{padding:5px 14px;border-radius:6px;background:#546099;border:1px solid #8ba4ff;color:#e8ecff;font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;white-space:nowrap;transition:background .15s;}
.resume-btn:hover{background:#7b90d4;}
.resume-dismiss{background:none;border:none;color:#6d8fd0;font-size:1.1rem;cursor:pointer;padding:2px 6px;line-height:1;flex-shrink:0;}
.resume-dismiss:hover{color:#e8ecff;}

/* 統計バー */
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;}
.stat-card{background:#f0f2f8;border:1px solid #aab0cc;border-radius:6px;padding:12px 10px;text-align:center;}
.stat-num{font-size:1.7rem;font-weight:800;color:#1a2a55;line-height:1;margin-bottom:3px;}
.stat-label{font-size:.7rem;color:#5a6080;font-weight:600;letter-spacing:.04em;}

/* メインパネル（FileMaker風） */
.fm-panel-wrap{background:#f0f2f8;border:2px solid #aab0cc;border-radius:4px;}
.fm-panel-header{background:#3b4f8a;padding:6px 12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.fm-panel-header-title{color:#dce4ff;font-size:.85rem;font-weight:700;flex:1;}
.search-input{padding:5px 10px;border:1px solid #6a7ab0;border-radius:4px;font-size:.82rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;min-width:180px;flex:1;}
.search-input:focus{border-color:#8ba4ff;}
.filter-select{padding:5px 8px;border:1px solid #6a7ab0;border-radius:4px;font-size:.8rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;cursor:pointer;}
.fm-add-btn{padding:5px 13px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:4px;color:#fff;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;}
.fm-add-btn:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}

/* テーブル */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.82rem;}
thead tr{background:#3b4f8a;}
th{padding:7px 10px;text-align:left;font-size:.72rem;font-weight:700;color:#dce4ff;border:1px solid #263570;white-space:nowrap;letter-spacing:.04em;}
tbody tr{background:#fff;border-bottom:1px solid #d0d4e0;cursor:pointer;transition:background .12s;}
tbody tr:nth-child(even){background:#f5f6fb;}
tbody tr:hover{background:#e8ecff;}
td{padding:8px 10px;color:#1a2240;vertical-align:middle;border-right:1px solid #e4e6f0;}
td:last-child{border-right:none;}
.td-id{color:#3b4f8a;font-weight:700;font-size:.8rem;}
.td-name{font-weight:700;font-size:.88rem;}
.td-furi{color:#7a82a0;font-size:.76rem;}
.td-class{background:#dde3f5;color:#2c3e6b;padding:2px 9px;border-radius:3px;font-size:.75rem;font-weight:700;display:inline-block;border:1px solid #aab0cc;}
.badge-count{background:#e8ecf8;color:#3b4f8a;padding:2px 8px;border-radius:3px;font-size:.75rem;font-weight:600;border:1px solid #c0c8e0;}
.badge-count.has{background:#3b4f8a;color:#dce4ff;border-color:#263570;}
.empty{text-align:center;padding:48px;color:#7a82a0;font-size:.88rem;}

/* モーダル */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.modal-overlay.show{display:flex;}
.modal{background:#f0f2f8;border:2px solid #aab0cc;border-radius:6px;width:90%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,.4);animation:mi .15s ease;}
@keyframes mi{from{transform:scale(.95);opacity:0}to{transform:scale(1);opacity:1}}
.modal-head{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);padding:8px 14px;color:#e8ecff;font-size:.95rem;font-weight:700;border-radius:4px 4px 0 0;}
.modal-body{padding:18px 16px;}
.form-row{margin-bottom:12px;}
.form-row label{display:block;font-size:.72rem;font-weight:700;color:#5a6080;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;}
.form-row input,.form-row select{width:100%;padding:7px 10px;border:1px solid #aab0cc;border-radius:4px;font-size:.88rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;}
.form-row input:focus,.form-row select:focus{border-color:#546099;box-shadow:0 0 0 2px rgba(84,96,153,.2);}
.form-2col{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;padding:10px 16px 14px;border-top:1px solid #d0d4e0;margin-top:4px;}
.btn-cancel{padding:6px 16px;border:1px solid #aab0cc;border-radius:4px;background:#e4e7f0;cursor:pointer;font-size:.82rem;font-family:inherit;color:#3a4060;}
.btn-cancel:hover{background:#d4d8e8;}
.btn-save{padding:6px 18px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:4px;color:#fff;cursor:pointer;font-weight:700;font-size:.82rem;font-family:inherit;}
.btn-save:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}

.table-wrap{-webkit-overflow-scrolling:touch;}

@media(max-width:768px){
  .fm-topbar-name{display:none;}
  .container{padding:10px 12px 40px;}
  .stats{grid-template-columns:1fr 1fr 1fr;}
}
@media(max-width:480px){
  body{font-size:12px;}
  .fm-topbar{padding:4px 8px;}
  .fm-topbar-title{font-size:.95rem;}
  .stats{gap:5px;}
  .stat-num{font-size:1.3rem;}
  .stat-label{font-size:.62rem;}
  .fm-panel-header{flex-wrap:wrap;padding:6px 8px;gap:6px;}
  .search-input{min-width:0;width:100%;order:2;}
  .filter-select{font-size:.78rem;}
  .fm-add-btn{font-size:.76rem;padding:4px 10px;}
  table{font-size:.76rem;}
  th,td{padding:6px 8px;}
  .modal{width:96%;}
  .form-2col{grid-template-columns:1fr;}
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
        <a href="/karte/gakuseki.php">📚 学籍管理</a>
        <a href="/karte/student_manager.php">👥 生徒管理</a>
        <a href="/karte/photo_import.php">📸 写真取込</a>
        <a href="/karte/survey_import.php">📋 調査票取込</a>
        <a href="/karte/structure.php">🗺 構造図</a>
        <a href="/karte/backup.php">💾 バックアップ</a>
        <a href="/karte/account.php">⚙ アカウント</a>
        <a href="/karte/logout.php">🚪 ログアウト</a>
      </div>
    </div>
  </div>
</div>

<!-- 前回の続きバー（JS で表示制御） -->
<div class="resume-bar" id="resumeBar">
  <span class="resume-text" id="resumeText">前回の続きから</span>
  <a href="#" class="resume-btn" id="resumeBtn">続きから開く</a>
  <button class="resume-dismiss" id="resumeDismiss" title="閉じる">✕</button>
</div>

<div class="container">
  <div class="stats">
    <div class="stat-card">
      <div class="stat-num"><?= $total ?></div>
      <div class="stat-label">登録生徒数</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $recCnt ?></div>
      <div class="stat-label">指導記録</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $attCnt ?></div>
      <div class="stat-label">出欠記録</div>
    </div>
  </div>

  <div class="fm-panel-wrap">
    <div class="fm-panel-header">
      <span class="fm-panel-header-title">生徒一覧</span>
      <input type="text" class="search-input" id="searchInput" placeholder="名前・ふりがな・番号で検索…">
      <select class="filter-select" id="classFilter">
        <option value="">全クラス</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="fm-add-btn" id="btnAddStudent">＋ 生徒を追加</button>
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
    <div class="modal-head">生徒を追加</div>
    <div class="modal-body">
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

function toggleKebab(e) { e.stopPropagation(); document.getElementById('kebabDropdown').classList.toggle('open'); }
document.addEventListener('click', function() { document.getElementById('kebabDropdown').classList.remove('open'); });

/* ── 前回の続きバー ── */
(function(){
  try {
    const st = JSON.parse(localStorage.getItem('karte_last_state') || 'null');
    if (!st || !st.student_id || !st.student_name) return;
    // 24時間以内のみ表示
    if (Date.now() - st.ts > 86400000) return;
    const elapsed = Math.round((Date.now() - st.ts) / 60000);
    const timeStr = elapsed < 60
      ? `${elapsed}分前`
      : elapsed < 1440
      ? `${Math.round(elapsed/60)}時間前`
      : `${Math.round(elapsed/1440)}日前`;
    const label = st.tab_label || '指導記録';
    document.getElementById('resumeText').innerHTML =
      `<strong>${st.student_name}</strong> さんの <strong>${label}</strong> を ${timeStr} に表示していました`;
    document.getElementById('resumeBtn').href =
      `/karte/karte_detail.php?id=${encodeURIComponent(st.student_id)}`;
    document.getElementById('resumeBar').classList.add('show');
    document.getElementById('resumeDismiss').onclick = () => {
      document.getElementById('resumeBar').classList.remove('show');
      try { localStorage.removeItem('karte_last_state'); } catch(e) {}
    };
  } catch(e) {}
})();
</script>
</body>
</html>
