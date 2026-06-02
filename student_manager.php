<?php
require_once 'config.php';
requireLogin();
$teacher = htmlspecialchars($_SESSION['teacher_name']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>生徒管理 — 生徒カルテ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Noto Sans JP',sans-serif;background:#0f0a1e;min-height:100vh;color:#1e293b;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 15% 0%,#4c1d95 0%,transparent 55%),radial-gradient(ellipse at 85% 0%,#1e3a8a 0%,transparent 55%),radial-gradient(ellipse at 50% 110%,#312e81 0%,transparent 60%);z-index:0;pointer-events:none;}
.topbar{position:sticky;top:0;z-index:100;background:rgba(15,10,30,.78);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.08);padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;}
.topbar-left{display:flex;align-items:center;gap:12px;}
.topbar-badge{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;padding:4px 13px;border-radius:20px;font-size:.74rem;font-weight:700;}
.topbar-name{color:#fff;font-size:.95rem;font-weight:600;}
.topbar-right{display:flex;align-items:center;gap:8px;}
.btn-nav{padding:7px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);border-radius:8px;cursor:pointer;font-size:.8rem;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn-nav:hover{background:rgba(255,255,255,.16);color:#fff;}

.container{position:relative;z-index:1;max-width:1000px;margin:0 auto;padding:0 20px 64px;}
.page-header{padding:28px 0 20px;color:#fff;text-align:center;}
.page-header h1{font-size:1.5rem;font-weight:800;background:linear-gradient(135deg,#fff 0%,#c4b5fd 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.page-header p{margin-top:6px;font-size:.85rem;color:rgba(255,255,255,.5);}

/* クラスパネル */
.panel{background:rgba(255,255,255,.96);border-radius:20px;padding:22px 24px;box-shadow:0 20px 60px rgba(0,0,0,.3);margin-bottom:16px;}
.panel-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;}
.panel-title{font-size:.97rem;font-weight:800;color:#1e293b;}
.btn-group{display:flex;gap:7px;flex-wrap:wrap;}
.btn-outline{padding:7px 14px;border-radius:9px;font-size:.82rem;font-weight:700;border:1.5px solid;cursor:pointer;background:#fff;transition:background .15s;font-family:inherit;}
.btn-add   {border-color:#16a34a;color:#16a34a;}  .btn-add:hover   {background:#f0fdf4;}
.btn-export{border-color:#2563eb;color:#2563eb;}  .btn-export:hover{background:#eff6ff;}
.btn-import{border-color:#7c3aed;color:#7c3aed;}  .btn-import:hover{background:#f5f3ff;}
.btn-bulk-del{border-color:#dc2626;color:#dc2626;} .btn-bulk-del:hover:not(:disabled){background:#fef2f2;}
.btn-bulk-del:disabled{border-color:#cbd5e1;color:#cbd5e1;cursor:not-allowed;}

/* クラスタグ */
.class-tags{display:flex;flex-wrap:wrap;gap:10px;}
.class-tag{display:flex;align-items:center;gap:8px;border:2px solid #e2e8f0;border-radius:12px;padding:8px 14px;transition:border-color .2s;background:#fff;}
.class-tag-name{font-weight:700;font-size:.9rem;color:#1e293b;}
.class-tag-count{font-size:.76rem;color:#94a3b8;}
.class-tag-actions{display:flex;gap:3px;margin-left:4px;}
.btn-icon{width:24px;height:24px;border-radius:6px;border:1.5px solid #e2e8f0;background:#fff;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;}
.btn-icon:hover{background:#f1f5f9;} .btn-icon.del:hover{background:#fee2e2;border-color:#fca5a5;}

/* フィルタタブ */
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
.filter-tab{padding:5px 14px;border-radius:20px;font-size:.82rem;font-weight:600;border:1.5px solid #e2e8f0;cursor:pointer;background:#fff;color:#64748b;transition:all .15s;font-family:inherit;}
.filter-tab.active{background:#7c3aed;color:#fff;border-color:#7c3aed;}

/* テーブル */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{background:#f8fafc;padding:9px 14px;text-align:left;font-size:.78rem;color:#64748b;font-weight:700;border-bottom:1.5px solid #e2e8f0;white-space:nowrap;}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:.87rem;color:#1e293b;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#faf8ff;}
tr.row-selected td{background:#fdf4ff!important;}
.th-chk,.td-chk{width:38px;text-align:center;padding-left:8px!important;padding-right:4px!important;}
input[type=checkbox].row-chk{width:16px;height:16px;cursor:pointer;accent-color:#7c3aed;vertical-align:middle;}
.sid-text{font-weight:700;color:#7c3aed;}
.class-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.76rem;font-weight:700;}
.class-select{padding:4px 8px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.82rem;background:#fff;cursor:pointer;font-family:inherit;}
.btn-act{padding:4px 11px;border-radius:7px;font-size:.77rem;font-weight:600;cursor:pointer;border:1.5px solid;background:#fff;transition:background .15s;font-family:inherit;margin-right:3px;}
.btn-del-s{border-color:#fca5a5;color:#dc2626;} .btn-del-s:hover{background:#fef2f2;}
.btn-karte{border-color:#c4b5fd;color:#7c3aed;} .btn-karte:hover{background:#f5f3ff;}
.empty-row td{text-align:center;color:#94a3b8;padding:32px;}

/* モーダル */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-overlay.show{display:flex;}
.modal{background:#fff;border-radius:20px;padding:32px 28px;max-width:480px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 40px 100px rgba(0,0,0,.4);animation:mi .18s ease;}
@keyframes mi{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}
.modal h3{font-size:1.1rem;color:#1e293b;margin-bottom:18px;font-weight:800;}
.f-label{font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px;display:block;}
.f-input,.f-select{width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;color:#1e293b;outline:none;transition:border-color .2s;margin-bottom:14px;}
.f-input:focus,.f-select:focus{border-color:#7c3aed;}
.f-hint{font-size:.78rem;color:#94a3b8;margin:-10px 0 14px;line-height:1.5;}
.f-2col{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal-btns{display:flex;gap:10px;justify-content:flex-end;margin-top:6px;}
.btn-cancel{padding:10px 20px;border:1.5px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;font-size:.88rem;font-family:inherit;}
.btn-cancel:hover{background:#f8fafc;}
.btn-ok{padding:10px 22px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem;font-family:inherit;}
.btn-ok:hover{opacity:.9;} .btn-ok.danger{background:#dc2626;}

/* CSV ドロップゾーン */
.drop-zone{border:2px dashed #c4b5fd;border-radius:12px;padding:28px 20px;text-align:center;cursor:pointer;transition:background .15s;margin-bottom:14px;color:#7c3aed;font-size:.9rem;}
.drop-zone.over{background:#f5f3ff;}
.drop-zone input[type=file]{display:none;}
.import-hint{font-size:.8rem;color:#94a3b8;margin-bottom:14px;line-height:1.7;background:#f8fafc;padding:10px 14px;border-radius:8px;}
.import-result{font-size:.85rem;margin-bottom:12px;}
.import-result .ok{color:#16a34a;font-weight:600;}
.import-result .err{color:#dc2626;}

/* トースト */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(60px);background:#1e293b;color:#fff;padding:10px 24px;border-radius:10px;font-size:.9rem;opacity:0;transition:all .3s;z-index:999;pointer-events:none;}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1;}

@media(max-width:640px){
.filter-tabs{gap:4px;} .filter-tab{font-size:.76rem;padding:4px 10px;}
.btn-group{gap:5px;} .btn-outline{font-size:.78rem;padding:6px 10px;}
td,th{padding:8px 10px;font-size:.82rem;}
.f-2col{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-badge">📋 生徒カルテ</span>
    <span class="topbar-name"><?= $teacher ?> 先生</span>
  </div>
  <div class="topbar-right">
    <a href="/karte/home.php" class="btn-nav">← 一覧へ</a>
    <a href="/karte/logout.php" class="btn-nav">ログアウト</a>
  </div>
</div>

<div class="container">
  <div class="page-header">
    <h1>👥 生徒管理</h1>
    <p>生徒の登録・編集・CSV インポート / エクスポート</p>
  </div>

  <!-- クラス管理 -->
  <div class="panel">
    <div class="panel-head">
      <span class="panel-title">🏫 クラス一覧</span>
      <div class="btn-group">
        <button class="btn-outline btn-add"    onclick="openAddClassModal()">＋ クラス追加</button>
        <button class="btn-outline btn-export" onclick="exportCSV()">↓ CSV出力</button>
        <button class="btn-outline btn-import" onclick="openImportModal()">↑ CSV読込</button>
      </div>
    </div>
    <div class="class-tags" id="classTags">
      <span style="color:#94a3b8;font-size:.88rem;">読み込み中…</span>
    </div>
  </div>

  <!-- 生徒一覧 -->
  <div class="panel">
    <div class="panel-head">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span class="panel-title">生徒一覧</span>
        <button class="btn-outline btn-add" onclick="openAddStudentModal()" style="font-size:.8rem;padding:6px 12px;">＋ 生徒追加</button>
        <button class="btn-outline btn-bulk-del" id="btnBulkDel" onclick="openBulkDeleteModal()" disabled style="font-size:.8rem;padding:6px 12px;">
          🗑 一括削除（<span id="bulkCount">0</span>件）
        </button>
      </div>
      <div class="filter-tabs" id="filterTabs">
        <button class="filter-tab active" onclick="filterClass('all',this)">すべて</button>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="th-chk"><input type="checkbox" id="checkAll" class="row-chk" onchange="selectAll(this.checked)"></th>
            <th>学籍番号</th><th>氏名</th><th>ふりがな</th><th>クラス</th><th>出席番号</th><th>操作</th>
          </tr>
        </thead>
        <tbody id="tbody">
          <tr class="empty-row"><td colspan="7">読み込み中…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- 生徒追加モーダル -->
<div class="modal-overlay" id="addStudentModal">
  <div class="modal">
    <h3>👤 生徒を追加</h3>
    <div class="f-2col">
      <div>
        <label class="f-label">学籍番号 *</label>
        <input class="f-input" id="ns-sid"  placeholder="例: 1101">
      </div>
      <div>
        <label class="f-label">クラス</label>
        <select class="f-select" id="ns-cls" style="margin-bottom:0"></select>
      </div>
    </div>
    <label class="f-label">氏名</label>
    <input class="f-input" id="ns-name" placeholder="山田 太郎">
    <label class="f-label">ふりがな</label>
    <input class="f-input" id="ns-furi" placeholder="やまだ たろう">
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('addStudentModal')">キャンセル</button>
      <button class="btn-ok" onclick="addStudentConfirm()">追加</button>
    </div>
  </div>
</div>

<!-- クラス追加モーダル -->
<div class="modal-overlay" id="addClassModal">
  <div class="modal">
    <h3>＋ クラスを追加</h3>
    <label class="f-label">新しいクラス名</label>
    <input class="f-input" id="addClassName" placeholder="例: 1年1組">
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('addClassModal')">キャンセル</button>
      <button class="btn-ok" onclick="addClassConfirm()">追加</button>
    </div>
  </div>
</div>

<!-- クラス名変更モーダル -->
<div class="modal-overlay" id="renameClassModal">
  <div class="modal">
    <h3>✏️ クラス名を変更</h3>
    <label class="f-label">現在のクラス名</label>
    <input class="f-input" id="renameOld" readonly style="background:#f8fafc;color:#94a3b8;">
    <label class="f-label">新しいクラス名</label>
    <input class="f-input" id="renameNew" placeholder="新しいクラス名">
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('renameClassModal')">キャンセル</button>
      <button class="btn-ok" onclick="renameClassConfirm()">変更</button>
    </div>
  </div>
</div>

<!-- クラス削除確認モーダル -->
<div class="modal-overlay" id="deleteClassModal">
  <div class="modal">
    <h3>🗑️ クラスを削除</h3>
    <p id="deleteClassMsg" style="color:#64748b;font-size:.9rem;margin-bottom:24px;"></p>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('deleteClassModal')">キャンセル</button>
      <button class="btn-ok danger" onclick="deleteClassConfirm()">削除</button>
    </div>
  </div>
</div>

<!-- 一括削除モーダル -->
<div class="modal-overlay" id="bulkDeleteModal">
  <div class="modal">
    <h3>🗑️ 一括削除</h3>
    <p id="bulkDeleteMsg" style="color:#64748b;font-size:.9rem;margin-bottom:24px;"></p>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('bulkDeleteModal')">キャンセル</button>
      <button class="btn-ok danger" onclick="bulkDeleteConfirm()">削除する</button>
    </div>
  </div>
</div>

<!-- CSV インポートモーダル -->
<div class="modal-overlay" id="importModal">
  <div class="modal" style="max-width:500px;">
    <h3>📂 CSV で生徒一覧を読込</h3>
    <div class="import-hint">
      ・1行目はヘッダー（生徒ID,名前,クラス,...）<br>
      ・生徒IDが既存なら名前・クラスを<strong>上書き更新</strong><br>
      ・存在しない場合は<strong>新規追加</strong><br>
      ・chat_system のCSVファイルをそのまま使えます<br>
      ・ふりがな・出席番号は5列目・6列目（省略可）<br>
      ・文字コード: UTF-8 または UTF-8 BOM（Excelで保存したもの可）
    </div>
    <div class="drop-zone" id="dropZone" onclick="document.getElementById('csvFile').click()">
      <input type="file" id="csvFile" accept=".csv,text/csv" onchange="onFileSelected(this.files[0])">
      <span id="dropText">ここをクリック or ファイルをドロップ</span>
    </div>
    <div class="import-result" id="importResult" style="display:none;"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeImportModal()">閉じる</button>
      <button class="btn-ok" id="importBtn" onclick="doImport()" style="display:none;">読込実行</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF = '<?= generateCsrfToken() ?>';
let allStudents = [], allClasses = [], currentFilter = 'all', selectedIds = new Set();
let pendingDeleteClass = null, pendingImportFile = null;

const CLASS_COLORS = [
    ['#ede9fe','#7c3aed'],['#dcfce7','#16a34a'],['#fef3c7','#d97706'],
    ['#fce7f3','#be185d'],['#dbeafe','#2563eb'],['#ffedd5','#ea580c'],
    ['#e0f2fe','#0284c7'],['#f0fdf4','#15803d']
];
function classColor(cls){
    const i = allClasses.indexOf(cls);
    const pair = CLASS_COLORS[(i<0?0:i) % CLASS_COLORS.length];
    return `background:${pair[0]};color:${pair[1]}`;
}

async function loadData(){
    const res  = await fetch('/karte/api/students.php?action=list');
    const data = await res.json();
    if (!data.success) return;
    allStudents = data.students;
    allClasses  = data.classes;
    renderClassTags();
    renderFilterTabs();
    renderTable('all');
}

/* クラスタグ */
function renderClassTags(){
    const wrap = document.getElementById('classTags');
    if (!allClasses.length){ wrap.innerHTML='<span style="color:#94a3b8;font-size:.88rem;">クラスがありません。「＋ クラス追加」から登録してください。</span>'; return; }
    wrap.innerHTML = allClasses.map(cls=>{
        const cnt = allStudents.filter(s=>s.class_name===cls).length;
        const cs  = classColor(cls);
        return `<div class="class-tag">
            <span class="class-badge" style="${cs}">${esc(cls)}</span>
            <div>
              <div class="class-tag-name">${esc(cls)}</div>
              <div class="class-tag-count">${cnt}人</div>
            </div>
            <div class="class-tag-actions">
              <button class="btn-icon" title="名前変更" onclick="openRenameModal('${esc2(cls)}')">✏️</button>
              <button class="btn-icon del" title="削除" onclick="openDeleteClassModal('${esc2(cls)}')">🗑</button>
            </div>
          </div>`;
    }).join('');
}

/* フィルタタブ */
function renderFilterTabs(){
    const tabs = document.getElementById('filterTabs');
    const extra = allClasses.map(c=>`<button class="filter-tab" onclick="filterClass('${esc2(c)}',this)">${esc(c)}</button>`).join('');
    tabs.innerHTML = `<button class="filter-tab active" onclick="filterClass('all',this)">すべて</button>${extra}`;
}

/* テーブル */
function renderTable(cls){
    const body = document.getElementById('tbody');
    const list = cls==='all' ? allStudents : allStudents.filter(s=>s.class_name===cls);
    if (!list.length){ body.innerHTML='<tr class="empty-row"><td colspan="7">生徒がいません</td></tr>'; updateCheckAll(); return; }
    const opts = allClasses.map(c=>`<option value="${esc(c)}">${esc(c)}</option>`).join('');
    body.innerHTML = list.map(s=>{
        const sel = selectedIds.has(s.student_id);
        const clsOpts = allClasses.map(c=>`<option value="${esc(c)}" ${c===s.class_name?'selected':''}>${esc(c)}</option>`).join('');
        return `<tr id="row-${s.student_id}" ${sel?'class="row-selected"':''}>
          <td class="td-chk"><input type="checkbox" class="row-chk" value="${esc(s.student_id)}" ${sel?'checked':''} onchange="toggleSelect('${esc2(s.student_id)}',this.checked)"></td>
          <td><span class="sid-text">${esc(s.student_id)}</span></td>
          <td>${esc(s.name||'—')}</td>
          <td style="color:#94a3b8;font-size:.82rem">${esc(s.furigana||'')}</td>
          <td>
            <select class="class-select" onchange="changeClass('${esc2(s.student_id)}',this.value)">
              <option value="">—</option>${clsOpts}
            </select>
          </td>
          <td style="text-align:center;color:#64748b">${s.seat_number||'—'}</td>
          <td>
            <button class="btn-act btn-karte" onclick="location.href='/karte/karte_detail.php?id=${encodeURIComponent(s.student_id)}'">カルテ</button>
            <button class="btn-act btn-del-s" onclick="deleteStudent('${esc2(s.student_id)}','${esc2(s.name||s.student_id)}')">削除</button>
          </td>
        </tr>`;
    }).join('');
    updateCheckAll();
}

function filterClass(cls,btn){
    currentFilter=cls; selectedIds.clear(); updateBulkBtn();
    document.querySelectorAll('.filter-tab').forEach(t=>t.classList.remove('active'));
    btn.classList.add('active');
    renderTable(cls);
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function esc2(s){return String(s||'').replace(/'/g,"\\'");}

/* チェックボックス */
function toggleSelect(sid,checked){
    if(checked) selectedIds.add(sid); else selectedIds.delete(sid);
    const row=document.getElementById('row-'+sid);
    if(row) row.className=checked?'row-selected':'';
    updateBulkBtn(); updateCheckAll();
}
function selectAll(checked){
    document.querySelectorAll('.row-chk').forEach(cb=>{
        if(cb.id!=='checkAll'){ cb.checked=checked; toggleSelect(cb.value,checked); }
    });
}
function updateCheckAll(){
    const boxes=[...document.querySelectorAll('.row-chk:not(#checkAll)')];
    document.getElementById('checkAll').checked = boxes.length && boxes.every(b=>b.checked);
}
function updateBulkBtn(){
    const n=selectedIds.size;
    document.getElementById('btnBulkDel').disabled=n===0;
    document.getElementById('bulkCount').textContent=n;
}

/* モーダル共通 */
function closeModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));

/* 生徒追加 */
function openAddStudentModal(){
    document.getElementById('ns-sid').value='';
    document.getElementById('ns-name').value='';
    document.getElementById('ns-furi').value='';
    const sel=document.getElementById('ns-cls');
    sel.innerHTML='<option value="">— 未設定 —</option>'+allClasses.map(c=>`<option>${esc(c)}</option>`).join('');
    document.getElementById('addStudentModal').classList.add('show');
}
async function addStudentConfirm(){
    const sid=document.getElementById('ns-sid').value.trim();
    const name=document.getElementById('ns-name').value.trim();
    const cls=document.getElementById('ns-cls').value;
    const furi=document.getElementById('ns-furi').value.trim();
    if(!sid){alert('学籍番号を入力してください');return;}
    const res=await fetch('/karte/api/students.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'add',student_id:sid,name,class_name:cls,furigana:furi,csrf_token:CSRF})
    });
    const data=await res.json();
    if(data.success){closeModal('addStudentModal');loadData();showToast(`生徒 ${sid} を追加しました`);}
    else alert(data.error||'エラー');
}

/* クラス追加 */
function openAddClassModal(){document.getElementById('addClassName').value='';document.getElementById('addClassModal').classList.add('show');}
async function addClassConfirm(){
    const name=document.getElementById('addClassName').value.trim();
    if(!name){alert('クラス名を入力してください');return;}
    if(allClasses.includes(name)){alert('そのクラス名は既に存在します');return;}
    allClasses.push(name);
    closeModal('addClassModal');
    renderClassTags(); renderFilterTabs(); renderTable(currentFilter);
    showToast(`クラス「${name}」を追加しました`);
}

/* クラス名変更 */
function openRenameModal(cls){
    document.getElementById('renameOld').value=cls;
    document.getElementById('renameNew').value=cls;
    document.getElementById('renameClassModal').classList.add('show');
}
async function renameClassConfirm(){
    const old=document.getElementById('renameOld').value;
    const nw=document.getElementById('renameNew').value.trim();
    if(!nw){alert('新しいクラス名を入力してください');return;}
    const res=await fetch('/karte/api/students.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'rename_class',old_name:old,new_name:nw})
    });
    const data=await res.json();
    if(data.success){closeModal('renameClassModal');loadData();showToast(`${old} → ${nw} に変更しました`);}
    else alert(data.error||'エラー');
}

/* クラス削除 */
function openDeleteClassModal(cls){
    pendingDeleteClass=cls;
    const cnt=allStudents.filter(s=>s.class_name===cls).length;
    document.getElementById('deleteClassMsg').innerHTML=
        `クラス「<strong>${esc(cls)}</strong>」を削除します。<br>`+
        (cnt>0?`このクラスの生徒 ${cnt} 名は「クラス未設定」になります（<strong>生徒データは削除されません</strong>）。`:'このクラスには生徒がいません。');
    document.getElementById('deleteClassModal').classList.add('show');
}
async function deleteClassConfirm(){
    if(!pendingDeleteClass) return;
    const res=await fetch('/karte/api/students.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'delete_class',class_name:pendingDeleteClass})
    });
    const data=await res.json();
    if(data.success){closeModal('deleteClassModal');pendingDeleteClass=null;loadData();showToast('クラスを削除しました');}
    else alert(data.error||'エラー');
}

/* クラス変更（ドロップダウン） */
async function changeClass(sid,cls){
    const res=await fetch('/karte/api/students.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'change_class',student_id:sid,class_name:cls})
    });
    const data=await res.json();
    if(data.success){const s=allStudents.find(x=>x.student_id===sid);if(s)s.class_name=cls;renderClassTags();showToast(`${sid} を ${cls||'未設定'} に変更`);}
}

/* 生徒削除 */
async function deleteStudent(sid,name){
    if(!confirm(`生徒「${name}」(${sid}) を削除しますか？\nカルテデータ（指導記録・出欠・面談）もすべて削除されます。`)) return;
    const res=await fetch('/karte/api/students.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'delete',student_id:sid})
    });
    const data=await res.json();
    if(data.success){loadData();showToast(`生徒 ${sid} を削除しました`);}
    else alert(data.error||'エラー');
}

/* 一括削除 */
function openBulkDeleteModal(){
    document.getElementById('bulkDeleteMsg').innerHTML=
        `選択した <strong>${selectedIds.size}件</strong> の生徒を削除します。<br>カルテデータもすべて削除されます。この操作は取り消せません。`;
    document.getElementById('bulkDeleteModal').classList.add('show');
}
async function bulkDeleteConfirm(){
    const ids=[...selectedIds];
    const res=await fetch('/karte/api/students.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'bulk_delete',ids})
    });
    const data=await res.json();
    if(data.success){selectedIds.clear();updateBulkBtn();closeModal('bulkDeleteModal');loadData();showToast(`${data.deleted||ids.length}件削除しました`);}
    else alert(data.error||'エラー');
}

/* CSV エクスポート */
function exportCSV(){
    window.location.href='/karte/api/students.php?action=export';
}

/* CSV インポート */
function openImportModal(){
    pendingImportFile=null;
    document.getElementById('dropText').textContent='ここをクリック or ファイルをドロップ';
    document.getElementById('importResult').style.display='none';
    document.getElementById('importBtn').style.display='none';
    document.getElementById('csvFile').value='';
    document.getElementById('importModal').classList.add('show');
}
function closeImportModal(){closeModal('importModal');}
function onFileSelected(file){
    if(!file) return;
    pendingImportFile=file;
    document.getElementById('dropText').textContent=`📄 ${file.name}`;
    document.getElementById('importResult').style.display='none';
    document.getElementById('importBtn').style.display='inline-block';
}
async function doImport(){
    if(!pendingImportFile) return;
    const fd=new FormData();
    fd.append('action','import');
    fd.append('csrf_token',CSRF);
    fd.append('csv',pendingImportFile);
    const res=await fetch('/karte/api/students.php',{method:'POST',body:fd});
    const data=await res.json();
    const div=document.getElementById('importResult');
    div.style.display='';
    if(data.success){
        let html=`<div class="ok">✓ 新規追加: ${data.created}件　更新: ${data.updated}件</div>`;
        if(data.errors&&data.errors.length) html+=data.errors.map(e=>`<div class="err">⚠ ${esc(e)}</div>`).join('');
        div.innerHTML=html;
        document.getElementById('importBtn').style.display='none';
        loadData();
    } else {
        div.innerHTML=`<div class="err">❌ ${esc(data.error)}</div>`;
    }
}

/* ドラッグ＆ドロップ */
const dz=document.getElementById('dropZone');
dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('over');});
dz.addEventListener('dragleave',()=>dz.classList.remove('over'));
dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('over');const f=e.dataTransfer.files[0];if(f){onFileSelected(f);}});

/* トースト */
function showToast(msg){
    const t=document.getElementById('toast');
    t.textContent=msg;t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'),2500);
}

loadData();
</script>
</body>
</html>
