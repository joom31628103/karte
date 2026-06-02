<?php
require_once 'config.php';
requireLogin();
$sid = $_GET['id'] ?? '';
if (!$sid) { header('Location: /karte/home.php'); exit; }
$conn = getDB();
$s = $conn->query("SELECT * FROM students WHERE student_id='".$conn->real_escape_string($sid)."'")->fetch_assoc();
if (!$s) { $conn->close(); header('Location: /karte/home.php'); exit; }
$conn->close();
$teacher = htmlspecialchars($_SESSION['teacher_name']);

$RECORD_TYPES = ['面談','保護者連絡','欠席連絡','遅刻','早退','生活指導','進路','学習','体調','部活動','その他'];
$ATT_TYPES    = ['欠席','遅刻','早退'];
$INT_TYPES    = ['三者面談','個人面談','保護者面談','進路面談','その他'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($s['name']) ?> のカルテ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Noto Sans JP',sans-serif;background:#0f0a1e;min-height:100vh;color:#1e293b;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 15% 0%,#4c1d95 0%,transparent 55%),radial-gradient(ellipse at 85% 0%,#1e3a8a 0%,transparent 55%),radial-gradient(ellipse at 50% 110%,#312e81 0%,transparent 60%);z-index:0;pointer-events:none;}
.topbar{position:sticky;top:0;z-index:100;background:rgba(15,10,30,.78);backdrop-filter:blur(14px);border-bottom:1px solid rgba(255,255,255,.08);padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;}
.topbar-left{display:flex;align-items:center;gap:12px;}
.topbar-badge{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;padding:4px 13px;border-radius:20px;font-size:.74rem;font-weight:700;}
.topbar-name{color:#fff;font-size:.95rem;font-weight:600;}
.btn-back{padding:7px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);border-radius:8px;cursor:pointer;font-size:.8rem;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn-back:hover{background:rgba(255,255,255,.16);color:#fff;}
.btn-logout{padding:7px 18px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);border-radius:8px;cursor:pointer;font-size:.8rem;font-family:inherit;text-decoration:none;}
.btn-logout:hover{background:rgba(255,255,255,.16);color:#fff;}
.container{position:relative;z-index:1;max-width:1000px;margin:0 auto;padding:0 20px 64px;}
/* Student header */
.student-header{margin:28px 0 20px;background:rgba(255,255,255,.96);border-radius:20px;padding:24px 28px;box-shadow:0 20px 60px rgba(0,0,0,.3);display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
.student-avatar{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0;}
.student-info{flex:1;min-width:0;}
.student-name{font-size:1.45rem;font-weight:800;color:#1e293b;}
.student-furi{font-size:.82rem;color:#94a3b8;margin-top:2px;}
.student-meta{display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;}
.meta-chip{background:#f1f5f9;color:#64748b;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:600;}
.meta-chip.purple{background:#ede9fe;color:#6d28d9;}
.student-actions{display:flex;gap:8px;flex-wrap:wrap;}
.btn-card{padding:9px 16px;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;color:#7c3aed;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn-card:hover{background:#f5f3ff;border-color:#c4b5fd;}
/* Tabs */
.tabs{display:flex;gap:4px;margin-bottom:16px;background:rgba(255,255,255,.07);border-radius:14px;padding:5px;}
.tab{flex:1;padding:10px;text-align:center;border-radius:10px;cursor:pointer;font-size:.85rem;font-weight:600;color:rgba(255,255,255,.55);transition:all .2s;border:none;background:none;font-family:inherit;}
.tab.active{background:rgba(255,255,255,.96);color:#1e293b;box-shadow:0 4px 12px rgba(0,0,0,.15);}
.tab:hover:not(.active){color:rgba(255,255,255,.85);}
.tab-panel{display:none;}
.tab-panel.active{display:block;}
/* Panel card */
.panel-card{background:rgba(255,255,255,.96);border-radius:20px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.panel-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.panel-title{font-size:1rem;font-weight:800;color:#1e293b;}
.btn-add{padding:9px 16px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:.84rem;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:5px;}
.btn-add:hover{opacity:.9;}
/* Tables */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.86rem;}
thead tr{border-bottom:2px solid #e2e8f0;}
th{padding:9px 12px;text-align:left;font-size:.71rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;}
td{padding:11px 12px;color:#1e293b;vertical-align:top;}
.type-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700;}
.type-面談{background:#dbeafe;color:#1d4ed8;}
.type-保護者連絡{background:#fce7f3;color:#9d174d;}
.type-欠席連絡{background:#fee2e2;color:#dc2626;}
.type-遅刻{background:#fef3c7;color:#92400e;}
.type-早退{background:#fef9c3;color:#854d0e;}
.type-生活指導{background:#fee2e2;color:#991b1b;}
.type-進路{background:#dcfce7;color:#15803d;}
.type-学習{background:#e0f2fe;color:#0369a1;}
.type-体調{background:#fce7f3;color:#9d174d;}
.type-部活動{background:#f3e8ff;color:#7e22ce;}
.type-その他{background:#f1f5f9;color:#64748b;}
.type-欠席{background:#fee2e2;color:#dc2626;}
.type-三者面談,.type-個人面談,.type-保護者面談,.type-進路面談{background:#dbeafe;color:#1d4ed8;}
.contact-badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:.74rem;font-weight:600;}
.contact-済{background:#dcfce7;color:#15803d;}
.contact-未{background:#fef3c7;color:#92400e;}
.btn-del{padding:4px 10px;background:#fff;border:1px solid #fca5a5;color:#dc2626;border-radius:8px;font-size:.75rem;cursor:pointer;font-family:inherit;}
.btn-del:hover{background:#fef2f2;}
.btn-edit{padding:4px 10px;background:#fff;border:1px solid #bfdbfe;color:#2563eb;border-radius:8px;font-size:.75rem;cursor:pointer;font-family:inherit;margin-right:4px;}
.btn-edit:hover{background:#eff6ff;}
.empty{text-align:center;padding:36px;color:#94a3b8;font-size:.88rem;}
td.content-cell{max-width:280px;word-break:break-all;white-space:pre-wrap;}
/* Basic info form */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.info-group{display:flex;flex-direction:column;gap:5px;}
.info-group.full{grid-column:1/-1;}
.info-group label{font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;}
.info-input{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;color:#1e293b;outline:none;transition:border-color .2s;width:100%;}
.info-input:focus{border-color:#7c3aed;}
.info-textarea{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;color:#1e293b;outline:none;resize:vertical;min-height:80px;width:100%;transition:border-color .2s;}
.info-textarea:focus{border-color:#7c3aed;}
.btn-save-info{padding:11px 24px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;margin-top:6px;}
.btn-save-info:hover{opacity:.9;}
.save-ok{color:#16a34a;font-size:.85rem;font-weight:600;margin-left:12px;display:none;}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-overlay.show{display:flex;}
.modal{background:#fff;border-radius:20px;padding:32px 28px;max-width:520px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 40px 100px rgba(0,0,0,.4);animation:mi .18s ease;}
@keyframes mi{from{transform:scale(.94);opacity:0}to{transform:scale(1);opacity:1}}
.modal h3{font-size:1.1rem;color:#1e293b;margin-bottom:20px;font-weight:800;}
.form-row{margin-bottom:14px;}
.form-row label{display:block;font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
.form-row input,.form-row select,.form-row textarea{width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-family:inherit;color:#1e293b;outline:none;transition:border-color .2s;}
.form-row input:focus,.form-row select:focus,.form-row textarea:focus{border-color:#7c3aed;}
.form-row textarea{resize:vertical;min-height:80px;}
.form-2col{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal-btns{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
.btn-cancel{padding:10px 20px;border:1.5px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;font-size:.88rem;font-family:inherit;}
.btn-cancel:hover{background:#f8fafc;}
.btn-m-save{padding:10px 22px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem;font-family:inherit;}
.btn-m-save:hover{opacity:.9;}
@media(max-width:640px){
.student-header{flex-direction:column;align-items:flex-start;}
.tabs{flex-wrap:wrap;}
.tab{flex:none;font-size:.78rem;}
.info-grid{grid-template-columns:1fr;}
.info-group.full{grid-column:1}
.form-2col{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-badge">📋 生徒カルテ</span>
    <span class="topbar-name"><?= $teacher ?> 先生</span>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <a href="/karte/home.php" class="btn-back">← 一覧へ</a>
    <a href="/karte/logout.php" class="btn-logout">ログアウト</a>
  </div>
</div>

<div class="container">
  <!-- Student header -->
  <div class="student-header">
    <div class="student-avatar">🎓</div>
    <div class="student-info">
      <div class="student-name" id="hdr-name"><?= htmlspecialchars($s['name']) ?></div>
      <div class="student-furi" id="hdr-furi"><?= htmlspecialchars($s['furigana'] ?? '') ?></div>
      <div class="student-meta">
        <span class="meta-chip"><?= htmlspecialchars($s['student_id']) ?></span>
        <?php if($s['class_name']): ?>
        <span class="meta-chip purple" id="hdr-class"><?= htmlspecialchars($s['class_name']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="student-actions">
      <a href="/karte/karte_card.php?id=<?= urlencode($sid) ?>" target="_blank" class="btn-card">🖨 カード印刷</a>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('records',this)">📝 指導記録</button>
    <button class="tab" onclick="switchTab('attendance',this)">📅 出欠記録</button>
    <button class="tab" onclick="switchTab('interviews',this)">💬 面談記録</button>
    <button class="tab" onclick="switchTab('basic',this)">👤 基本情報</button>
  </div>

  <!-- 指導記録 -->
  <div class="tab-panel active" id="panel-records">
    <div class="panel-card">
      <div class="panel-header">
        <span class="panel-title">担任メモ・指導記録</span>
        <button class="btn-add" onclick="openRecordModal()">＋ 記録を追加</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>日付</th><th>種類</th><th>内容</th><th>対応者</th><th>次回対応</th><th></th></tr></thead>
          <tbody id="tbody-records"></tbody>
        </table>
      </div>
      <div class="empty" id="empty-records" style="display:none">指導記録がありません。「記録を追加」から登録してください。</div>
    </div>
  </div>

  <!-- 出欠記録 -->
  <div class="tab-panel" id="panel-attendance">
    <div class="panel-card">
      <div class="panel-header">
        <span class="panel-title">出欠・遅刻・早退メモ</span>
        <button class="btn-add" onclick="openAttModal()">＋ 記録を追加</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>日付</th><th>区分</th><th>理由</th><th>保護者連絡</th><th>備考</th><th></th></tr></thead>
          <tbody id="tbody-att"></tbody>
        </table>
      </div>
      <div class="empty" id="empty-att" style="display:none">出欠記録がありません。</div>
    </div>
  </div>

  <!-- 面談記録 -->
  <div class="tab-panel" id="panel-interviews">
    <div class="panel-card">
      <div class="panel-header">
        <span class="panel-title">面談記録</span>
        <button class="btn-add" onclick="openIntModal()">＋ 記録を追加</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>日付</th><th>種別</th><th>参加者</th><th>内容</th><th>今後の対応</th><th></th></tr></thead>
          <tbody id="tbody-int"></tbody>
        </table>
      </div>
      <div class="empty" id="empty-int" style="display:none">面談記録がありません。</div>
    </div>
  </div>

  <!-- 基本情報 -->
  <div class="tab-panel" id="panel-basic">
    <div class="panel-card">
      <div class="panel-header">
        <span class="panel-title">基本情報</span>
      </div>
      <div class="info-grid" id="basicForm">
        <div class="info-group">
          <label>学籍番号</label>
          <input class="info-input" id="b-sid" value="<?= htmlspecialchars($s['student_id']) ?>" readonly style="background:#f8fafc;color:#94a3b8;">
        </div>
        <div class="info-group">
          <label>クラス</label>
          <input class="info-input" id="b-class" value="<?= htmlspecialchars($s['class_name'] ?? '') ?>">
        </div>
        <div class="info-group">
          <label>氏名</label>
          <input class="info-input" id="b-name" value="<?= htmlspecialchars($s['name'] ?? '') ?>">
        </div>
        <div class="info-group">
          <label>ふりがな</label>
          <input class="info-input" id="b-furi" value="<?= htmlspecialchars($s['furigana'] ?? '') ?>">
        </div>
        <div class="info-group">
          <label>出席番号</label>
          <input class="info-input" id="b-seat" type="number" value="<?= htmlspecialchars($s['seat_number'] ?? '') ?>">
        </div>
        <div class="info-group">
          <label>性別</label>
          <input class="info-input" id="b-gender" value="<?= htmlspecialchars($s['gender'] ?? '') ?>" placeholder="男・女・その他">
        </div>
        <div class="info-group">
          <label>生年月日</label>
          <input class="info-input" id="b-bday" type="date" value="<?= htmlspecialchars($s['birthday'] ?? '') ?>">
        </div>
        <div class="info-group">
          <label>電話番号（保護者）</label>
          <input class="info-input" id="b-phone" value="<?= htmlspecialchars($s['phone'] ?? '') ?>">
        </div>
        <div class="info-group">
          <label>保護者名</label>
          <input class="info-input" id="b-parent" value="<?= htmlspecialchars($s['parent_name'] ?? '') ?>">
        </div>
        <div class="info-group full">
          <label>住所</label>
          <input class="info-input" id="b-addr" value="<?= htmlspecialchars($s['address'] ?? '') ?>">
        </div>
        <div class="info-group full">
          <label>備考・家庭状況など</label>
          <textarea class="info-textarea" id="b-notes"><?= htmlspecialchars($s['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div style="margin-top:16px;display:flex;align-items:center;">
        <button class="btn-save-info" id="btnSaveBasic">保存する</button>
        <span class="save-ok" id="saveOk">✓ 保存しました</span>
      </div>
    </div>
  </div>
</div>

<!-- 指導記録モーダル -->
<div class="modal-overlay" id="recModal">
  <div class="modal">
    <h3 id="recModalTitle">指導記録を追加</h3>
    <input type="hidden" id="rec-id" value="">
    <div class="form-2col">
      <div class="form-row">
        <label>日付</label>
        <input type="date" id="rec-date">
      </div>
      <div class="form-row">
        <label>種類</label>
        <select id="rec-type">
          <?php foreach($RECORD_TYPES as $t) echo "<option>$t</option>"; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <label>内容</label>
      <textarea id="rec-content" rows="4" placeholder="記録内容を入力…"></textarea>
    </div>
    <div class="form-2col">
      <div class="form-row">
        <label>対応者</label>
        <input type="text" id="rec-teacher" value="<?= htmlspecialchars($_SESSION['teacher_name']) ?>" placeholder="担当者名">
      </div>
      <div class="form-row">
        <label>次回対応</label>
        <input type="text" id="rec-next" placeholder="例: 6月末に再確認">
      </div>
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('recModal')">キャンセル</button>
      <button class="btn-m-save" id="btnSaveRec">保存する</button>
    </div>
  </div>
</div>

<!-- 出欠記録モーダル -->
<div class="modal-overlay" id="attModal">
  <div class="modal">
    <h3>出欠・遅刻・早退を追加</h3>
    <div class="form-2col">
      <div class="form-row">
        <label>日付</label>
        <input type="date" id="att-date">
      </div>
      <div class="form-row">
        <label>区分</label>
        <select id="att-type">
          <?php foreach($ATT_TYPES as $t) echo "<option>$t</option>"; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <label>理由</label>
      <input type="text" id="att-reason" placeholder="例: 体調不良、寝坊など">
    </div>
    <div class="form-2col">
      <div class="form-row">
        <label>保護者連絡</label>
        <select id="att-contact">
          <option>未</option>
          <option>済</option>
          <option>不要</option>
        </select>
      </div>
      <div class="form-row">
        <label>備考</label>
        <input type="text" id="att-notes" placeholder="例: 母より連絡">
      </div>
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('attModal')">キャンセル</button>
      <button class="btn-m-save" id="btnSaveAtt">保存する</button>
    </div>
  </div>
</div>

<!-- 面談記録モーダル -->
<div class="modal-overlay" id="intModal">
  <div class="modal">
    <h3>面談記録を追加</h3>
    <div class="form-2col">
      <div class="form-row">
        <label>日付</label>
        <input type="date" id="int-date">
      </div>
      <div class="form-row">
        <label>面談種別</label>
        <select id="int-type">
          <?php foreach($INT_TYPES as $t) echo "<option>$t</option>"; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <label>参加者</label>
      <input type="text" id="int-parti" placeholder="例: 本人・母">
    </div>
    <div class="form-row">
      <label>内容</label>
      <textarea id="int-content" rows="4" placeholder="面談内容を入力…"></textarea>
    </div>
    <div class="form-row">
      <label>今後の対応</label>
      <input type="text" id="int-next" placeholder="例: 夏休みに再確認">
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('intModal')">キャンセル</button>
      <button class="btn-m-save" id="btnSaveInt">保存する</button>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= generateCsrfToken() ?>';
const SID  = '<?= htmlspecialchars($sid) ?>';

function switchTab(name, btn) {
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('panel-'+name).classList.add('active');
  if (name==='records') loadRecords();
  else if (name==='attendance') loadAtt();
  else if (name==='interviews') loadInt();
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));

const today = new Date().toISOString().split('T')[0];

/* ── 指導記録 ── */
let editingRecId = null;
async function loadRecords() {
  const res = await fetch(`/karte/api/karte.php?action=list_records&student_id=${SID}`);
  const data = await res.json();
  const tbody = document.getElementById('tbody-records');
  const empty = document.getElementById('empty-records');
  if (!data.rows.length) { tbody.innerHTML=''; empty.style.display=''; return; }
  empty.style.display='none';
  tbody.innerHTML = data.rows.map(r => `
    <tr>
      <td style="white-space:nowrap">${esc(r.record_date)}</td>
      <td><span class="type-badge type-${esc(r.record_type)}">${esc(r.record_type)}</span></td>
      <td class="content-cell">${esc(r.content)}</td>
      <td style="white-space:nowrap">${esc(r.teacher)}</td>
      <td class="content-cell">${esc(r.next_action)}</td>
      <td style="white-space:nowrap">
        <button class="btn-edit" onclick="openRecordModal(${r.id},'${esc2(r.record_date)}','${esc2(r.record_type)}',\`${esc3(r.content)}\`,'${esc2(r.teacher)}',\`${esc3(r.next_action)}\`)">編集</button>
        <button class="btn-del" onclick="delRecord(${r.id})">削除</button>
      </td>
    </tr>
  `).join('');
}

function esc2(s){return String(s||'').replace(/'/g,"\\'");}
function esc3(s){return String(s||'').replace(/`/g,'\\`').replace(/\$/g,'\\$');}

function openRecordModal(id, date, type, content, teacher, next) {
  editingRecId = id || null;
  document.getElementById('recModalTitle').textContent = id ? '指導記録を編集' : '指導記録を追加';
  document.getElementById('rec-id').value = id || '';
  document.getElementById('rec-date').value = date || today;
  document.getElementById('rec-type').value = type || '面談';
  document.getElementById('rec-content').value = content || '';
  document.getElementById('rec-teacher').value = teacher || '<?= htmlspecialchars($_SESSION['teacher_name']) ?>';
  document.getElementById('rec-next').value = next || '';
  document.getElementById('recModal').classList.add('show');
}

document.getElementById('btnSaveRec').onclick = async () => {
  const content = document.getElementById('rec-content').value.trim();
  if (!content) { alert('内容を入力してください。'); return; }
  const fd = new FormData();
  const isEdit = !!editingRecId;
  fd.append('action', isEdit ? 'update_record' : 'add_record');
  fd.append('csrf_token', CSRF);
  if (isEdit) fd.append('id', editingRecId);
  else fd.append('student_id', SID);
  fd.append('record_date',  document.getElementById('rec-date').value);
  fd.append('record_type',  document.getElementById('rec-type').value);
  fd.append('content',      content);
  fd.append('teacher',      document.getElementById('rec-teacher').value);
  fd.append('next_action',  document.getElementById('rec-next').value);
  const res = await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) { closeModal('recModal'); loadRecords(); }
  else alert(data.error||'エラー');
};

async function delRecord(id) {
  if (!confirm('この指導記録を削除しますか？')) return;
  const fd = new FormData();
  fd.append('action','delete_record'); fd.append('csrf_token',CSRF); fd.append('id',id);
  await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  loadRecords();
}

/* ── 出欠記録 ── */
function openAttModal() {
  document.getElementById('att-date').value = today;
  document.getElementById('att-type').value = '欠席';
  document.getElementById('att-reason').value = '';
  document.getElementById('att-contact').value = '未';
  document.getElementById('att-notes').value = '';
  document.getElementById('attModal').classList.add('show');
}

document.getElementById('btnSaveAtt').onclick = async () => {
  const fd = new FormData();
  fd.append('action','add_attendance'); fd.append('csrf_token',CSRF); fd.append('student_id',SID);
  fd.append('att_date',         document.getElementById('att-date').value);
  fd.append('att_type',         document.getElementById('att-type').value);
  fd.append('reason',           document.getElementById('att-reason').value);
  fd.append('parent_contacted', document.getElementById('att-contact').value);
  fd.append('notes',            document.getElementById('att-notes').value);
  const res = await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) { closeModal('attModal'); loadAtt(); }
  else alert(data.error||'エラー');
};

async function loadAtt() {
  const res = await fetch(`/karte/api/karte.php?action=list_attendance&student_id=${SID}`);
  const data = await res.json();
  const tbody = document.getElementById('tbody-att');
  const empty = document.getElementById('empty-att');
  if (!data.rows.length) { tbody.innerHTML=''; empty.style.display=''; return; }
  empty.style.display='none';
  tbody.innerHTML = data.rows.map(r=>`
    <tr>
      <td style="white-space:nowrap">${esc(r.att_date)}</td>
      <td><span class="type-badge type-${esc(r.att_type)}">${esc(r.att_type)}</span></td>
      <td>${esc(r.reason)}</td>
      <td><span class="contact-badge contact-${esc(r.parent_contacted)}">${esc(r.parent_contacted)}</span></td>
      <td>${esc(r.notes)}</td>
      <td><button class="btn-del" onclick="delAtt(${r.id})">削除</button></td>
    </tr>
  `).join('');
}

async function delAtt(id) {
  if (!confirm('この出欠記録を削除しますか？')) return;
  const fd = new FormData();
  fd.append('action','delete_attendance'); fd.append('csrf_token',CSRF); fd.append('id',id);
  await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  loadAtt();
}

/* ── 面談記録 ── */
function openIntModal() {
  document.getElementById('int-date').value = today;
  document.getElementById('int-type').value = '三者面談';
  document.getElementById('int-parti').value = '';
  document.getElementById('int-content').value = '';
  document.getElementById('int-next').value = '';
  document.getElementById('intModal').classList.add('show');
}

document.getElementById('btnSaveInt').onclick = async () => {
  const content = document.getElementById('int-content').value.trim();
  if (!content) { alert('内容を入力してください。'); return; }
  const fd = new FormData();
  fd.append('action','add_interview'); fd.append('csrf_token',CSRF); fd.append('student_id',SID);
  fd.append('interview_date',  document.getElementById('int-date').value);
  fd.append('interview_type',  document.getElementById('int-type').value);
  fd.append('participants',    document.getElementById('int-parti').value);
  fd.append('content',         content);
  fd.append('next_action',     document.getElementById('int-next').value);
  const res = await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) { closeModal('intModal'); loadInt(); }
  else alert(data.error||'エラー');
};

async function loadInt() {
  const res = await fetch(`/karte/api/karte.php?action=list_interviews&student_id=${SID}`);
  const data = await res.json();
  const tbody = document.getElementById('tbody-int');
  const empty = document.getElementById('empty-int');
  if (!data.rows.length) { tbody.innerHTML=''; empty.style.display=''; return; }
  empty.style.display='none';
  tbody.innerHTML = data.rows.map(r=>`
    <tr>
      <td style="white-space:nowrap">${esc(r.interview_date)}</td>
      <td><span class="type-badge type-${esc(r.interview_type)}">${esc(r.interview_type)}</span></td>
      <td>${esc(r.participants)}</td>
      <td class="content-cell">${esc(r.content)}</td>
      <td class="content-cell">${esc(r.next_action)}</td>
      <td><button class="btn-del" onclick="delInt(${r.id})">削除</button></td>
    </tr>
  `).join('');
}

async function delInt(id) {
  if (!confirm('この面談記録を削除しますか？')) return;
  const fd = new FormData();
  fd.append('action','delete_interview'); fd.append('csrf_token',CSRF); fd.append('id',id);
  await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  loadInt();
}

/* ── 基本情報保存 ── */
document.getElementById('btnSaveBasic').onclick = async () => {
  const fd = new FormData();
  fd.append('action','save_basic'); fd.append('csrf_token',CSRF); fd.append('student_id',SID);
  fd.append('name',       document.getElementById('b-name').value);
  fd.append('furigana',   document.getElementById('b-furi').value);
  fd.append('class_name', document.getElementById('b-class').value);
  fd.append('seat_number',document.getElementById('b-seat').value);
  fd.append('gender',     document.getElementById('b-gender').value);
  fd.append('birthday',   document.getElementById('b-bday').value);
  fd.append('phone',      document.getElementById('b-phone').value);
  fd.append('parent_name',document.getElementById('b-parent').value);
  fd.append('address',    document.getElementById('b-addr').value);
  fd.append('notes',      document.getElementById('b-notes').value);
  const res = await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) {
    const ok = document.getElementById('saveOk');
    ok.style.display='inline';
    // ヘッダー更新
    document.getElementById('hdr-name').textContent = document.getElementById('b-name').value;
    document.getElementById('hdr-furi').textContent = document.getElementById('b-furi').value;
    const chip = document.getElementById('hdr-class');
    if (chip) chip.textContent = document.getElementById('b-class').value;
    setTimeout(()=>ok.style.display='none', 2500);
  } else alert(data.error||'エラー');
};

// 初期ロード
loadRecords();
</script>
</body>
</html>
