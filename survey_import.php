<?php
require_once 'config.php';
requireLogin();
$conn = getDB();

// クラス一覧と生徒一覧を取得
$classRes = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name IS NOT NULL AND class_name != '' ORDER BY class_name");
$classes = [];
while ($r = $classRes->fetch_assoc()) $classes[] = $r['class_name'];

$studentsRes = $conn->query("SELECT s.student_id, s.name, s.seat_number, s.class_name, g.name AS gak_name, g.gakno, sn.gakunen, sn.class_no, sn.bango FROM students s LEFT JOIN gakuseki g ON s.gakno=g.gakno LEFT JOIN student_nendo sn ON sn.gakno=g.gakno ORDER BY s.class_name, CAST(s.seat_number AS UNSIGNED), s.student_id");
$allStudents = [];
while ($r = $studentsRes->fetch_assoc()) {
    $r['display_name'] = $r['gak_name'] ?: $r['name'];
    $allStudents[] = $r;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>環境調査票PDF一括取り込み — 生徒カルテ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo',sans-serif;background:#d0d4dc;min-height:100vh;font-size:13px;}
.fm-topbar{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);color:#fff;padding:4px 14px;display:flex;align-items:center;justify-content:space-between;min-height:42px;border-bottom:2px solid #0f1e40;}
.fm-topbar-title{font-size:1.1rem;font-weight:900;color:#e8ecff;}
.fm-btn-top{padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#e8ecff;cursor:pointer;font-size:.78rem;text-decoration:none;transition:background .15s;}
.fm-btn-top:hover{background:rgba(255,255,255,.25);}
.main{max-width:1200px;margin:24px auto;padding:0 16px;}
.card{background:#f0f2f8;border:2px solid #aab0cc;border-radius:8px;padding:20px;margin-bottom:20px;}
.card-title{font-size:.88rem;font-weight:800;color:#1a2240;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid #aab0cc;}
.drop-zone{border:2px dashed #7b90d4;border-radius:8px;background:#f5f7ff;padding:40px;text-align:center;cursor:pointer;transition:all .2s;position:relative;}
.drop-zone:hover,.drop-zone.drag-over{border-color:#3b4f8a;background:#e8ecff;}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.drop-zone-icon{font-size:3rem;margin-bottom:10px;}
.drop-zone-text{font-size:.9rem;font-weight:700;color:#3b4f8a;margin-bottom:4px;}
.drop-zone-sub{font-size:.76rem;color:#6a7090;}
.setting-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-top:14px;}
.setting-group{display:flex;flex-direction:column;gap:4px;}
.setting-group label{font-size:.72rem;font-weight:700;color:#5a6080;}
.setting-select,.setting-input{padding:7px 10px;border:1px solid #aab0cc;border-radius:4px;font-size:.85rem;font-family:inherit;outline:none;background:#fff;}
.setting-select:focus,.setting-input:focus{border-color:#3b4f8a;}
.btn-primary{padding:9px 22px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:6px;color:#fff;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit;}
.btn-primary:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;}
.btn-success{padding:9px 22px;background:linear-gradient(180deg,#16a34a 0%,#15803d 100%);border:1px solid #166534;border-radius:6px;color:#fff;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit;}
.btn-success:hover{background:linear-gradient(180deg,#22c55e 0%,#16a34a 100%);}
.btn-success:disabled{opacity:.5;cursor:not-allowed;}
.loading-box{display:none;text-align:center;padding:30px;color:#3b4f8a;}
.spinner{display:inline-block;width:32px;height:32px;border:4px solid #d0d4e0;border-top-color:#3b4f8a;border-radius:50%;animation:spin .8s linear infinite;margin-bottom:8px;}
@keyframes spin{to{transform:rotate(360deg)}}
.progress-bar-wrap{width:100%;max-width:400px;margin:10px auto;background:#d0d4e0;border-radius:10px;height:10px;overflow:hidden;}
.progress-bar{height:100%;background:#3b4f8a;border-radius:10px;transition:width .2s;}
.progress-text{font-size:.82rem;color:#3b4f8a;margin-top:6px;}
.summary-bar{background:#e8ecff;border:1px solid #aab0cc;border-radius:6px;padding:10px 14px;margin-bottom:12px;font-size:.82rem;color:#3b4f8a;display:flex;gap:16px;flex-wrap:wrap;}
.results-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;}
.result-card{background:#fff;border:2px solid #86efac;border-radius:8px;overflow:hidden;}
.result-card.no-student{border-color:#fca5a5;}
.result-canvas-wrap{width:100%;background:#e4e7f0;display:flex;align-items:center;justify-content:center;overflow:hidden;max-height:280px;}
.result-canvas-wrap canvas{width:100%;height:auto;display:block;}
.result-info{padding:8px;}
.result-page{font-size:.7rem;color:#5a6080;margin-bottom:3px;}
.result-select{width:100%;padding:4px 6px;border:1px solid #aab0cc;border-radius:4px;font-size:.75rem;font-family:inherit;outline:none;}
.error-box{background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;padding:10px 12px;color:#dc2626;font-size:.82rem;display:none;margin-top:10px;}
.save-result{background:#dcfce7;border:1px solid #86efac;border-radius:6px;padding:12px 14px;color:#15803d;font-size:.9rem;font-weight:700;display:none;margin-top:10px;}
</style>
</head>
<body>
<div class="fm-topbar">
  <div class="fm-topbar-title">📋 環境調査票PDF一括取り込み</div>
  <div style="display:flex;gap:6px;">
    <a href="/karte/home.php" class="fm-btn-top">← 一覧に戻る</a>
    <a href="/karte/logout.php" class="fm-btn-top">ログアウト</a>
  </div>
</div>

<div class="main">
  <!-- Step1 -->
  <div class="card" id="stepUpload">
    <div class="card-title">📄 Step 1 — PDFをアップロードして設定</div>
    <div class="drop-zone" id="dropZone">
      <input type="file" id="pdfInput" accept="application/pdf">
      <div class="drop-zone-icon">📄</div>
      <div class="drop-zone-text">ここをクリックまたはPDFをドロップ</div>
      <div class="drop-zone-sub">1ページ＝1名分・出席番号順（最大50ページ）</div>
    </div>
    <div class="setting-row" style="margin-top:16px;">
      <div class="setting-group">
        <label>対象クラス</label>
        <select class="setting-select" id="selClass">
          <option value="">— クラスを選択 —</option>
          <?php foreach($classes as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
          <option value="__manual__">手動で番号割り当て</option>
        </select>
      </div>
      <div class="setting-group">
        <label>開始出席番号</label>
        <input type="number" class="setting-input" id="startNo" value="1" min="1" max="99" style="width:80px;">
      </div>
      <button class="btn-primary" id="btnRender" disabled>🖼 ページを展開する</button>
    </div>
    <div class="error-box" id="uploadError"></div>
  </div>

  <!-- ローディング -->
  <div class="loading-box" id="loadingBox">
    <div class="spinner"></div>
    <div class="progress-bar-wrap"><div class="progress-bar" id="progressBar" style="width:0%"></div></div>
    <div class="progress-text" id="progressText">PDFを読み込み中…</div>
  </div>

  <!-- Step2 確認 -->
  <div class="card" id="stepConfirm" style="display:none;">
    <div class="card-title">✅ Step 2 — 確認・修正して保存</div>
    <div class="summary-bar" id="summaryBar"></div>
    <div style="font-size:.76rem;color:#5a6080;margin-bottom:12px;">
      ※ プルダウンで生徒を変更できます。「— スキップ —」にした生徒は保存されません。
    </div>
    <div class="results-grid" id="resultsGrid"></div>
    <div style="margin-top:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <button class="btn-success" id="btnSave">💾 調査票として保存する</button>
      <button class="btn-primary" style="background:linear-gradient(180deg,#6a7090 0%,#4a5070 100%);border-color:#3a4060;" onclick="resetAll()">🔄 やり直す</button>
    </div>
    <div class="error-box" id="confirmError"></div>
    <div class="save-result" id="saveResult"></div>
  </div>
</div>

<!-- PDF.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs" type="module"></script>
<script type="module">
import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs';
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs';

const CSRF = '<?= generateCsrfToken() ?>';
const allStudents = <?= json_encode($allStudents, JSON_UNESCAPED_UNICODE) ?>;

let pdfFile = null;
let pageCanvases = []; // {canvas, blob}
let assignments  = []; // {pageIdx, student_id, canvas}

const pdfInput  = document.getElementById('pdfInput');
const dropZone  = document.getElementById('dropZone');
const btnRender = document.getElementById('btnRender');

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('drag-over');
  const f = e.dataTransfer.files[0];
  if (f && f.type === 'application/pdf') { pdfFile = f; btnRender.disabled = false; dropZone.querySelector('.drop-zone-text').textContent = f.name; hideErr('uploadError'); }
});
pdfInput.addEventListener('change', () => {
  if (pdfInput.files[0]) { pdfFile = pdfInput.files[0]; btnRender.disabled = false; dropZone.querySelector('.drop-zone-text').textContent = pdfFile.name; hideErr('uploadError'); }
});

btnRender.addEventListener('click', async () => {
  if (!pdfFile) return;
  const cls = document.getElementById('selClass').value;
  const startNo = parseInt(document.getElementById('startNo').value) || 1;

  document.getElementById('stepUpload').style.display = 'none';
  document.getElementById('loadingBox').style.display = 'block';
  setProgress(0, 'PDFを読み込み中…');

  try {
    const arrayBuf = await pdfFile.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({data: arrayBuf}).promise;
    const totalPages = pdf.numPages;
    pageCanvases = [];

    for (let p = 1; p <= totalPages; p++) {
      setProgress(Math.round(p/totalPages*100), `ページ ${p}/${totalPages} を変換中…`);
      const page = await pdf.getPage(p);
      const scale = 1.5;
      const vp = page.getViewport({scale});
      const canvas = document.createElement('canvas');
      canvas.width  = vp.width;
      canvas.height = vp.height;
      await page.render({canvasContext: canvas.getContext('2d'), viewport: vp}).promise;
      pageCanvases.push(canvas);
    }

    // 生徒マッチング
    const classStudents = cls && cls !== '__manual__'
      ? allStudents.filter(s => s.class_name === cls || s.class_no === cls)
      : allStudents;

    assignments = pageCanvases.map((canvas, i) => {
      const seatNo = startNo + i;
      const student = classStudents.find(s => parseInt(s.seat_number) === seatNo || parseInt(s.bango) === seatNo) || null;
      return { pageIdx: i, student_id: student?.student_id || null, canvas };
    });

    document.getElementById('loadingBox').style.display = 'none';
    renderResults();
    document.getElementById('stepConfirm').style.display = '';
  } catch(e) {
    document.getElementById('loadingBox').style.display = 'none';
    document.getElementById('stepUpload').style.display = '';
    showErr('uploadError', 'PDFの読み込みに失敗しました: ' + e.message);
  }
});

function renderResults() {
  const grid = document.getElementById('resultsGrid');
  grid.innerHTML = '';
  assignments.forEach((a, i) => {
    const card = document.createElement('div');
    card.className = 'result-card' + (a.student_id ? '' : ' no-student');
    card.id = 'rcard-' + i;

    const wrap = document.createElement('div');
    wrap.className = 'result-canvas-wrap';
    const thumb = a.canvas.cloneNode();
    const ctx = thumb.getContext('2d');
    ctx.drawImage(a.canvas, 0, 0);
    wrap.appendChild(thumb);

    const info = document.createElement('div');
    info.className = 'result-info';
    const options = `<option value="">— スキップ —</option>` +
      allStudents.map(s => `<option value="${s.student_id}" ${s.student_id === a.student_id ? 'selected' : ''}>${s.display_name}（${s.seat_number || s.bango || '?'}番）</option>`).join('');
    info.innerHTML = `<div class="result-page">ページ ${a.pageIdx+1}</div>
      <select class="result-select" onchange="window._onSel(${i},this.value)">${options}</select>`;

    card.appendChild(wrap);
    card.appendChild(info);
    grid.appendChild(card);
  });
  updateSummary();
}

window._onSel = (i, val) => {
  assignments[i].student_id = val || null;
  const card = document.getElementById('rcard-' + i);
  card.className = 'result-card' + (val ? '' : ' no-student');
  updateSummary();
};

function updateSummary() {
  const total = assignments.length;
  const matched = assignments.filter(a => a.student_id).length;
  document.getElementById('summaryBar').innerHTML =
    `<span>総ページ: <strong>${total}</strong></span>` +
    `<span>保存予定: <strong style="color:#15803d">${matched}名</strong></span>` +
    `<span>スキップ: <strong style="color:#dc2626">${total-matched}名</strong></span>`;
}

document.getElementById('btnSave').addEventListener('click', async () => {
  const targets = assignments.filter(a => a.student_id);
  if (!targets.length) { showErr('confirmError','保存対象がありません'); return; }
  if (!confirm(`${targets.length}名の環境調査票を保存します。既存の調査票は上書きされます。よろしいですか？`)) return;

  document.getElementById('btnSave').disabled = true;
  document.getElementById('loadingBox').style.display = '';
  document.getElementById('stepConfirm').style.display = 'none';

  let saved = 0;
  for (let i = 0; i < targets.length; i++) {
    const a = targets[i];
    setProgress(Math.round((i+1)/targets.length*100), `保存中 ${i+1}/${targets.length}…`);

    const blob = await new Promise(r => a.canvas.toBlob(r, 'image/jpeg', 0.92));
    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('csrf_token', CSRF);
    fd.append('survey', blob, 'survey.jpg');
    <?php
    // student_idで識別（gaknoはJS側から渡せないのでAPI側で解決）
    ?>
    fd.append('student_id', a.student_id);

    const res = await fetch('/karte/api/survey.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) saved++;
  }

  document.getElementById('loadingBox').style.display = 'none';
  document.getElementById('stepConfirm').style.display = '';
  document.getElementById('btnSave').disabled = false;
  document.getElementById('saveResult').textContent = `✓ ${saved}名の環境調査票を保存しました。`;
  document.getElementById('saveResult').style.display = 'block';
});

function setProgress(pct, text) {
  document.getElementById('progressBar').style.width = pct + '%';
  document.getElementById('progressText').textContent = text;
}
function showErr(id, msg) { const e=document.getElementById(id); e.textContent='⚠ '+msg; e.style.display='block'; }
function hideErr(id) { document.getElementById(id).style.display='none'; }

window.resetAll = () => {
  pdfFile = null; pageCanvases = []; assignments = [];
  pdfInput.value = '';
  dropZone.querySelector('.drop-zone-text').textContent = 'ここをクリックまたはPDFをドロップ';
  btnRender.disabled = true;
  document.getElementById('stepUpload').style.display = '';
  document.getElementById('stepConfirm').style.display = 'none';
  document.getElementById('loadingBox').style.display = 'none';
  document.getElementById('saveResult').style.display = 'none';
  hideErr('uploadError'); hideErr('confirmError');
};
</script>
</body>
</html>
