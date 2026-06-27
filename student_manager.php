<?php
require_once 'config.php';
requireLogin();
$teacher = htmlspecialchars($_SESSION['teacher_name']);
$conn = getDB();
$firstSid = $conn->query("SELECT student_id FROM students ORDER BY class_name,seat_number,student_id LIMIT 1")->fetch_assoc()['student_id'] ?? '';
$conn->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/karte/favicon.php">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>生徒管理 — 生徒カルテ</title>
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

.container{max-width:1020px;margin:0 auto;padding:14px 16px 48px;}

/* パネル */
.fm-panel-wrap{background:#f0f2f8;border:2px solid #aab0cc;border-radius:4px;margin-bottom:10px;}
.fm-panel-header{background:#3b4f8a;padding:6px 12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.fm-panel-header-title{color:#dce4ff;font-size:.83rem;font-weight:700;white-space:nowrap;}
.fm-add-btn{padding:4px 12px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:4px;color:#fff;font-size:.76rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;}
.fm-add-btn:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}
.fm-add-btn.green{background:linear-gradient(180deg,#4a9a6a 0%,#2d7a52 100%);border-color:#1d5c3a;}
.fm-add-btn.green:hover{background:linear-gradient(180deg,#5cb87e 0%,#4a9a6a 100%);}

/* クラスタグエリア */
.class-tags-area{padding:10px 12px;display:flex;flex-wrap:wrap;gap:7px;}
.class-tag{display:flex;align-items:center;gap:6px;border:1px solid #aab0cc;border-radius:4px;padding:5px 10px;background:#fff;}
.class-tag-name{font-weight:700;font-size:.84rem;color:#1a2240;}
.class-tag-count{font-size:.73rem;color:#7a82a0;}
.class-tag-actions{display:flex;gap:3px;margin-left:2px;}
.btn-icon{width:20px;height:20px;border-radius:3px;border:1px solid #aab0cc;background:#e8ecf8;cursor:pointer;font-size:.7rem;display:flex;align-items:center;justify-content:center;}
.btn-icon:hover{background:#d4d8e8;} .btn-icon.del:hover{background:#fee2e2;border-color:#fca5a5;}

/* フィルタ行 */
.list-bar{background:#ebedf5;border-top:1px solid #aab0cc;border-bottom:1px solid #aab0cc;padding:6px 12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;}
.filter-tabs{display:flex;gap:4px;flex-wrap:wrap;}
.filter-tab{padding:3px 11px;border-radius:3px;font-size:.77rem;font-weight:600;border:1px solid #aab0cc;cursor:pointer;background:#fff;color:#3b4f8a;font-family:inherit;}
.filter-tab.active{background:#3b4f8a;color:#fff;border-color:#263570;}
.filter-tab:hover:not(.active){background:#dde3f5;}
.btn-bulk-del{padding:4px 12px;border-radius:3px;font-size:.76rem;font-weight:700;border:1px solid #c0a0a0;color:#9b3030;cursor:pointer;background:#f8ecec;font-family:inherit;}
.btn-bulk-del:hover:not(:disabled){background:#fce0e0;}
.btn-bulk-del:disabled{border-color:#ccc;color:#aaa;background:#f0f0f0;cursor:not-allowed;}

/* テーブル */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.82rem;}
thead tr{background:#3b4f8a;}
th{padding:7px 10px;text-align:left;font-size:.71rem;font-weight:700;color:#dce4ff;border:1px solid #263570;white-space:nowrap;letter-spacing:.03em;}
tbody tr{background:#fff;border-bottom:1px solid #d0d4e0;transition:background .1s;}
tbody tr:nth-child(even){background:#f5f6fb;}
tbody tr:hover td{background:#e8ecff;}
tr.row-selected td{background:#dde3f5!important;}
td{padding:7px 10px;color:#1a2240;vertical-align:middle;border-right:1px solid #e4e6f0;}
td:last-child{border-right:none;}
.th-chk,.td-chk{width:34px;text-align:center;padding-left:6px!important;padding-right:4px!important;}
input[type=checkbox].row-chk{width:14px;height:14px;cursor:pointer;accent-color:#3b4f8a;vertical-align:middle;}
.sid-text{font-weight:700;color:#3b4f8a;font-size:.8rem;}
.class-badge{display:inline-block;padding:2px 8px;border-radius:3px;font-size:.74rem;font-weight:700;background:#dde3f5;color:#2c3e6b;border:1px solid #aab0cc;}
.class-select{padding:3px 7px;border:1px solid #aab0cc;border-radius:3px;font-size:.8rem;background:#fff;cursor:pointer;font-family:inherit;color:#1a2240;}
.btn-act{padding:3px 9px;border-radius:3px;font-size:.75rem;font-weight:600;cursor:pointer;border:1px solid;background:#e8ecf8;transition:background .1s;font-family:inherit;margin-right:3px;}
.btn-del-s{border-color:#c0a0a0;color:#9b3030;} .btn-del-s:hover{background:#fce0e0;}
.btn-karte{border-color:#aab0cc;color:#2c3e6b;} .btn-karte:hover{background:#dde3f5;}
.empty-row td{text-align:center;color:#7a82a0;padding:40px;}

/* モーダル */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.modal-overlay.show{display:flex;}
.modal{background:#f0f2f8;border:2px solid #aab0cc;border-radius:6px;width:90%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.4);animation:mi .15s ease;}
@keyframes mi{from{transform:scale(.95);opacity:0}to{transform:scale(1);opacity:1}}
.modal-head{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);padding:8px 14px;color:#e8ecff;font-size:.92rem;font-weight:700;border-radius:4px 4px 0 0;}
.modal-body{padding:16px 14px;}
.f-label{font-size:.72rem;font-weight:700;color:#5a6080;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;display:block;}
.f-input,.f-select{width:100%;padding:7px 10px;border:1px solid #aab0cc;border-radius:4px;font-size:.88rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;margin-bottom:12px;}
.f-input:focus,.f-select:focus{border-color:#546099;}
.f-2col{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;padding:8px 14px 12px;border-top:1px solid #d0d4e0;}
.btn-cancel{padding:6px 14px;border:1px solid #aab0cc;border-radius:4px;background:#e4e7f0;cursor:pointer;font-size:.82rem;font-family:inherit;color:#3a4060;}
.btn-cancel:hover{background:#d4d8e8;}
.btn-ok{padding:6px 16px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:4px;color:#fff;cursor:pointer;font-weight:700;font-size:.82rem;font-family:inherit;}
.btn-ok:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}
.btn-ok.danger{background:linear-gradient(180deg,#c04040 0%,#902020 100%);border-color:#700000;}

/* CSV */
.drop-zone{border:2px dashed #7a8ab0;border-radius:4px;padding:24px 16px;text-align:center;cursor:pointer;transition:background .15s;margin-bottom:12px;color:#3b4f8a;font-size:.86rem;background:#e8ecf8;}
.drop-zone.over{background:#dde3f5;}
.drop-zone input[type=file]{display:none;}
.import-hint{font-size:.78rem;color:#5a6080;margin-bottom:12px;line-height:1.7;background:#e4e7f0;padding:9px 12px;border-radius:4px;border:1px solid #aab0cc;}
.import-result{font-size:.82rem;margin-bottom:10px;}
.import-result .ok{color:#2d7a52;font-weight:600;}
.import-result .err{color:#9b3030;}

/* トースト */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(60px);background:#1a2a55;color:#e8ecff;padding:9px 22px;border-radius:4px;border:1px solid #263570;font-size:.86rem;opacity:0;transition:all .3s;z-index:999;pointer-events:none;}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1;}

/* 検索 */
.search-input{padding:5px 10px;border:1px solid #6a7ab0;border-radius:4px;font-size:.82rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;flex:1;min-width:160px;}
.search-input:focus{border-color:#8ba4ff;}

.table-wrap{-webkit-overflow-scrolling:touch;}

@media(max-width:768px){
  .fm-topbar-name{display:none;}
  .container{padding:10px 12px 40px;}
  .filter-tabs{flex-wrap:wrap;}
}
@media(max-width:480px){
  body{font-size:12px;}
  .fm-topbar{padding:4px 8px;}
  .fm-topbar-title{font-size:.95rem;}
  .fm-btn-top{font-size:.7rem;padding:4px 8px;}
  .fm-panel-header{flex-wrap:wrap;padding:6px 8px;gap:5px;}
  .search-input{width:100%;}
  .class-tags-area{padding:8px;}
  .class-tag{padding:5px 8px;}
  table{font-size:.76rem;}
  th,td{padding:6px 7px;}
  .filter-tab{font-size:.74rem;padding:3px 9px;}
  .btn-act{font-size:.72rem;padding:3px 7px;}
  .f-2col{grid-template-columns:1fr;}
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
        <a href="/karte/gakuseki.php">📚 学籍管理</a>
        <a class="current-page">👥 生徒管理</a>
        <a href="/karte/backup.php">🗄️ バックアップ</a>
        <a href="/karte/account.php">⚙ アカウント</a>
        <a href="/karte/logout.php">🚪 ログアウト</a>
      </div>
    </div>
  </div>
</div>

<div class="container">

  <!-- クラス管理パネル -->
  <div class="fm-panel-wrap">
    <div class="fm-panel-header">
      <span class="fm-panel-header-title">🏫 クラス管理</span>
      <button class="fm-add-btn green" onclick="openAddClassModal()">＋ クラス追加</button>
      <button class="fm-add-btn" onclick="exportCSV()">↓ CSV出力</button>
      <button class="fm-add-btn" onclick="openImportModal()">↑ CSV読込</button>
    </div>
    <div class="class-tags-area" id="classTags">
      <span style="color:#7a82a0;font-size:.84rem;">読み込み中…</span>
    </div>
  </div>

  <!-- 生徒一覧パネル -->
  <div class="fm-panel-wrap">
    <div class="fm-panel-header">
      <span class="fm-panel-header-title">生徒一覧</span>
      <input class="search-input" id="searchInput" type="text" placeholder="氏名・学籍番号で検索…" oninput="applySearch()">
      <button class="fm-add-btn green" onclick="openAddStudentModal()">＋ 生徒追加</button>
    </div>
    <div class="list-bar">
      <div class="filter-tabs" id="filterTabs">
        <button class="filter-tab active" onclick="filterClass('all',this)">すべて</button>
      </div>
      <button class="btn-bulk-del" id="btnBulkDel" onclick="openBulkDeleteModal()" disabled>
        🗑 一括削除（<span id="bulkCount">0</span>件）
      </button>
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
    <div class="modal-head">👤 生徒を追加</div>
    <div class="modal-body">
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
    </div><!-- /.modal-body -->
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('addStudentModal')">キャンセル</button>
      <button class="btn-ok" onclick="addStudentConfirm()">追加</button>
    </div>
  </div>
</div>

<!-- クラス追加モーダル -->
<div class="modal-overlay" id="addClassModal">
  <div class="modal">
    <div class="modal-head">＋ クラスを追加</div>
    <div class="modal-body">
      <label class="f-label">新しいクラス名</label>
      <input class="f-input" id="addClassName" placeholder="例: 1年1組">
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('addClassModal')">キャンセル</button>
      <button class="btn-ok" onclick="addClassConfirm()">追加</button>
    </div>
  </div>
</div>

<!-- クラス名変更モーダル -->
<div class="modal-overlay" id="renameClassModal">
  <div class="modal">
    <div class="modal-head">✏️ クラス名を変更</div>
    <div class="modal-body">
      <label class="f-label">現在のクラス名</label>
      <input class="f-input" id="renameOld" readonly style="background:#e4e7f0;color:#5a6080;">
      <label class="f-label">新しいクラス名</label>
      <input class="f-input" id="renameNew" placeholder="新しいクラス名">
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('renameClassModal')">キャンセル</button>
      <button class="btn-ok" onclick="renameClassConfirm()">変更</button>
    </div>
  </div>
</div>

<!-- クラス削除確認モーダル -->
<div class="modal-overlay" id="deleteClassModal">
  <div class="modal">
    <div class="modal-head">🗑️ クラスを削除</div>
    <div class="modal-body">
      <p id="deleteClassMsg" style="color:#3a4060;font-size:.88rem;"></p>
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('deleteClassModal')">キャンセル</button>
      <button class="btn-ok danger" onclick="deleteClassConfirm()">削除</button>
    </div>
  </div>
</div>

<!-- 一括削除モーダル -->
<div class="modal-overlay" id="bulkDeleteModal">
  <div class="modal">
    <div class="modal-head">🗑️ 一括削除</div>
    <div class="modal-body">
      <p id="bulkDeleteMsg" style="color:#3a4060;font-size:.88rem;"></p>
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('bulkDeleteModal')">キャンセル</button>
      <button class="btn-ok danger" onclick="bulkDeleteConfirm()">削除する</button>
    </div>
  </div>
</div>

<!-- CSV インポートモーダル -->
<div class="modal-overlay" id="importModal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-head">📂 CSV で生徒一覧を読込</div>
    <div class="modal-body">
      <div class="import-hint">
        ・1行目はヘッダー（生徒ID,名前,クラス,...）<br>
        ・生徒IDが既存なら名前・クラスを<strong>上書き更新</strong><br>
        ・存在しない場合は<strong>新規追加</strong><br>
        ・ふりがな・出席番号は5列目・6列目（省略可）<br>
        ・文字コード: UTF-8 または UTF-8 BOM
      </div>
      <div class="drop-zone" id="dropZone" onclick="document.getElementById('csvFile').click()">
        <input type="file" id="csvFile" accept=".csv,text/csv" onchange="onFileSelected(this.files[0])">
        <span id="dropText">ここをクリック or ファイルをドロップ</span>
      </div>
      <div class="import-result" id="importResult" style="display:none;"></div>
    </div>
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
    if (!allClasses.length){ wrap.innerHTML='<span style="color:#7a82a0;font-size:.84rem;">クラスがありません。「＋ クラス追加」から登録してください。</span>'; return; }
    wrap.innerHTML = allClasses.map(cls=>{
        const cnt = allStudents.filter(s=>s.class_name===cls).length;
        return `<div class="class-tag">
            <div>
              <div class="class-tag-name">${esc(cls)}</div>
              <div class="class-tag-count">${cnt}人</div>
            </div>
            <div class="class-tag-actions">
              <button class="btn-icon" title="名前変更" onclick="openRenameModal('${esc2(cls)}')">✏</button>
              <button class="btn-icon del" title="削除" onclick="openDeleteClassModal('${esc2(cls)}')">×</button>
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
function applySearch(){renderTable(currentFilter);}
function renderTable(cls){
    const q = (document.getElementById('searchInput')?.value||'').trim().toLowerCase();
    const body = document.getElementById('tbody');
    let list = cls==='all' ? allStudents : allStudents.filter(s=>s.class_name===cls);
    if(q) list=list.filter(s=>(s.name||'').toLowerCase().includes(q)||(s.furigana||'').toLowerCase().includes(q)||(s.student_id||'').toLowerCase().includes(q));
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

function toggleKebab(e){e.stopPropagation();document.getElementById('kebabDropdown').classList.toggle('open');}
document.addEventListener('click',function(){const d=document.getElementById('kebabDropdown');if(d)d.classList.remove('open');});
</script>
</body>
</html>
