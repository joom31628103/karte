<?php
require_once 'config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/karte/favicon.php">
  <link rel="icon" type="image/png" sizes="32x32" href="/karte/icon-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/karte/icon-180.png">
  <link rel="manifest" href="/karte/manifest.json">
  <meta name="theme-color" content="#1a2a55">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>顔写真一括取り込み — 生徒カルテ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo',sans-serif;background:#d0d4dc;min-height:100vh;font-size:13px;}
.fm-topbar{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);color:#fff;padding:4px 14px;display:flex;align-items:center;justify-content:space-between;min-height:42px;border-bottom:2px solid #0f1e40;}
.fm-topbar-title{font-size:1.1rem;font-weight:900;color:#e8ecff;}
.fm-btn-top{padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#e8ecff;cursor:pointer;font-size:.78rem;text-decoration:none;transition:background .15s;}
.fm-btn-top:hover{background:rgba(255,255,255,.25);}

.main{max-width:1100px;margin:24px auto;padding:0 16px;}

.card{background:#f0f2f8;border:2px solid #aab0cc;border-radius:8px;padding:20px;margin-bottom:20px;}
.card-title{font-size:.88rem;font-weight:800;color:#1a2240;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid #aab0cc;display:flex;align-items:center;gap:8px;}

/* アップロードゾーン */
.drop-zone{border:2px dashed #7b90d4;border-radius:8px;background:#f5f7ff;padding:40px;text-align:center;cursor:pointer;transition:all .2s;position:relative;}
.drop-zone:hover,.drop-zone.drag-over{border-color:#3b4f8a;background:#e8ecff;}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.drop-zone-icon{font-size:3rem;margin-bottom:10px;}
.drop-zone-text{font-size:.9rem;font-weight:700;color:#3b4f8a;margin-bottom:4px;}
.drop-zone-sub{font-size:.76rem;color:#6a7090;}
.preview-img{max-width:100%;max-height:300px;border-radius:6px;border:1px solid #aab0cc;margin-top:12px;display:none;}

/* ボタン */
.btn-primary{padding:10px 24px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:6px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;}
.btn-primary:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;}
.btn-success{padding:10px 24px;background:linear-gradient(180deg,#16a34a 0%,#15803d 100%);border:1px solid #166534;border-radius:6px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;}
.btn-success:hover{background:linear-gradient(180deg,#22c55e 0%,#16a34a 100%);}
.btn-success:disabled{opacity:.5;cursor:not-allowed;}

/* API KEY 設定 */
.apikey-row{display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap;}
.apikey-row label{font-size:.75rem;font-weight:700;color:#5a6080;white-space:nowrap;}
.apikey-input{flex:1;min-width:200px;padding:7px 10px;border:1px solid #aab0cc;border-radius:4px;font-size:.85rem;font-family:inherit;outline:none;}
.apikey-input:focus{border-color:#3b4f8a;}
.apikey-note{font-size:.72rem;color:#6a7090;}

/* ローディング */
.loading-box{display:none;text-align:center;padding:30px;color:#3b4f8a;}
.spinner{display:inline-block;width:36px;height:36px;border:4px solid #d0d4e0;border-top-color:#3b4f8a;border-radius:50%;animation:spin .8s linear infinite;margin-bottom:10px;}
@keyframes spin{to{transform:rotate(360deg)}}
.loading-text{font-size:.88rem;font-weight:700;}

/* 結果グリッド */
.results-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-top:14px;}
.result-card{background:#fff;border:2px solid #d0d4e0;border-radius:8px;overflow:hidden;transition:border-color .2s;}
.result-card.matched{border-color:#86efac;}
.result-card.unmatched{border-color:#fca5a5;}
.result-card.skipped{border-color:#d0d4e0;opacity:.5;}
.result-photo{width:100%;aspect-ratio:3/4;object-fit:cover;display:block;background:#e4e7f0;}
.result-info{padding:8px;}
.result-detected{font-size:.72rem;color:#5a6080;margin-bottom:4px;}
.result-detected span{font-weight:700;color:#1a2240;}
.result-student{font-size:.78rem;font-weight:700;color:#15803d;margin-bottom:6px;}
.result-unmatched-label{font-size:.78rem;font-weight:700;color:#dc2626;margin-bottom:6px;}
.result-select{width:100%;padding:4px 6px;border:1px solid #aab0cc;border-radius:4px;font-size:.75rem;font-family:inherit;outline:none;}
.result-select:focus{border-color:#3b4f8a;}
.match-badge{display:inline-block;font-size:.65rem;padding:1px 6px;border-radius:10px;font-weight:700;}
.badge-ok{background:#dcfce7;color:#15803d;}
.badge-ng{background:#fee2e2;color:#dc2626;}
.skip-btn{font-size:.68rem;color:#9aa0c0;cursor:pointer;text-decoration:underline;background:none;border:none;padding:0;margin-top:4px;}

/* サマリー */
.summary-bar{background:#e8ecff;border:1px solid #aab0cc;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:.82rem;color:#3b4f8a;display:flex;gap:16px;flex-wrap:wrap;}
.summary-bar strong{font-weight:800;}

/* エラー */
.error-box{background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;padding:12px 14px;color:#dc2626;font-size:.84rem;display:none;}
</style>
</head>
<body>

<div class="fm-topbar">
  <div class="fm-topbar-title">📸 顔写真一括取り込み（Gemini AI）</div>
  <div style="display:flex;gap:6px;">
    <a href="/karte/home.php" class="fm-btn-top">🏠 HOME</a>
    <a href="/karte/logout.php" class="fm-btn-top">ログアウト</a>
  </div>
</div>

<div class="main">

  <!-- Step 1: アップロード -->
  <div class="card" id="stepUpload">
    <div class="card-title">📄 Step 1 — 顔写真一覧（B4）をアップロード</div>

    <div class="apikey-row">
      <label>Gemini API キー</label>
      <input type="password" class="apikey-input" id="apiKeyInput" placeholder="AIza...">
      <span class="apikey-note">※ Google AI Studio で取得。config.phpに設定済みの場合は空欄でOK</span>
    </div>

    <div class="drop-zone" id="dropZone">
      <input type="file" id="sheetInput" accept="image/jpeg,image/png,image/gif,image/webp">
      <div class="drop-zone-icon">🖼</div>
      <div class="drop-zone-text">ここをクリックまたは画像をドロップ</div>
      <div class="drop-zone-sub">B4サイズの顔写真一覧（JPEG・PNG・最大20MB）</div>
    </div>
    <img class="preview-img" id="previewImg" alt="プレビュー">

    <!-- 画像縮小設定 -->
    <div id="resizePanel" style="display:none;margin-top:12px;background:#eef0fb;border:1px solid #aab0cc;border-radius:6px;padding:12px 16px;">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span style="font-size:.78rem;font-weight:700;color:#3b4f8a;white-space:nowrap;">🗜 画像を縮小して送信</span>
        <label style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:#3b4f8a;">
          <input type="checkbox" id="chkResize" checked> 縮小する
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:#3b4f8a;">
          最大幅
          <select id="selMaxW" style="padding:3px 6px;border:1px solid #aab0cc;border-radius:4px;font-size:.78rem;">
            <option value="3000">3000px（高品質）</option>
            <option value="2000" selected>2000px（推奨）</option>
            <option value="1500">1500px（軽量）</option>
            <option value="1200">1200px（最軽量）</option>
          </select>
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:#3b4f8a;">
          画質
          <input type="range" id="rngQuality" min="50" max="95" value="85" style="width:80px;">
          <span id="lblQuality">85%</span>
        </label>
        <span id="lblFileSize" style="font-size:.75rem;color:#6a7090;"></span>
      </div>
    </div>

    <div style="margin-top:14px;text-align:center;">
      <button class="btn-primary" id="btnAnalyze" disabled>🤖 Gemini で解析する</button>
    </div>
    <div class="error-box" id="uploadError"></div>
  </div>

  <!-- ローディング -->
  <div class="loading-box" id="loadingBox">
    <div class="spinner"></div>
    <div class="loading-text" id="loadingText">Gemini AI で解析中…</div>
    <div id="loadingTimer" style="font-size:.8rem;color:#6a7090;margin-top:6px;">経過: 0秒</div>
    <button id="btnCancel" onclick="cancelAnalyze()" style="margin-top:14px;padding:6px 18px;background:#dc2626;border:none;border-radius:6px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;">✕ キャンセル</button>
  </div>

  <!-- Step 2: 確認・修正 -->
  <div class="card" id="stepConfirm" style="display:none;">
    <div class="card-title">✅ Step 2 — マッチング確認・修正</div>
    <div class="summary-bar" id="summaryBar"></div>
    <div style="font-size:.78rem;color:#5a6080;margin-bottom:10px;">
      ※ 赤枠 = 自動マッチング失敗。プルダウンで正しい生徒を選択してください。「スキップ」にした生徒は保存されません。
    </div>
    <div class="results-grid" id="resultsGrid"></div>
    <div style="margin-top:16px;text-align:center;">
      <button class="btn-success" id="btnSave" disabled>💾 写真を保存する</button>
      <button class="btn-primary" style="margin-left:8px;background:linear-gradient(180deg,#6a7090 0%,#4a5070 100%);border-color:#3a4060;" onclick="resetAll()">🔄 やり直す</button>
    </div>
    <div class="error-box" id="confirmError"></div>
  </div>

  <!-- 完了 -->
  <div class="card" id="stepDone" style="display:none;">
    <div class="card-title">🎉 完了</div>
    <div id="doneMsg" style="font-size:.9rem;color:#15803d;font-weight:700;margin-bottom:12px;"></div>
    <button class="btn-primary" onclick="resetAll()">続けて取り込む</button>
    <a href="/karte/home.php" class="btn-primary" style="display:inline-block;margin-left:8px;text-decoration:none;">🏠 HOME</a>
  </div>

</div>

<script>
const CSRF = '<?= generateCsrfToken() ?>';
let allStudents = [];
let analysisResults = [];
let selectedFile = null;
let savedTempId  = sessionStorage.getItem('photo_import_temp_id') || null;
let abortCtrl    = null;
let timerInterval = null;

function startTimer() {
  let sec = 0;
  const el = document.getElementById('loadingTimer');
  timerInterval = setInterval(() => {
    sec++;
    const m = Math.floor(sec/60), s = sec%60;
    el.textContent = `経過: ${m>0?m+'分':''}${s}秒`;
    if (sec === 60) document.getElementById('loadingText').textContent = 'Gemini AI で解析中… しばらくお待ちください';
    if (sec === 120) document.getElementById('loadingText').textContent = 'Gemini AI で解析中… もうしばらくお待ちください（混雑中の可能性）';
  }, 1000);
}

function stopTimer() {
  clearInterval(timerInterval);
  timerInterval = null;
  document.getElementById('loadingTimer').textContent = '';
  document.getElementById('loadingText').textContent = 'Gemini AI で解析中…';
}

function cancelAnalyze() {
  if (abortCtrl) abortCtrl.abort();
  stopTimer();
  document.getElementById('loadingBox').style.display = 'none';
  document.getElementById('stepUpload').style.display = '';
  showError('uploadError', 'キャンセルしました。再度「Geminiで解析する」を押してください。');
}

const sheetInput  = document.getElementById('sheetInput');
const dropZone    = document.getElementById('dropZone');
const previewImg  = document.getElementById('previewImg');
const btnAnalyze  = document.getElementById('btnAnalyze');
const loadingBox  = document.getElementById('loadingBox');
const stepUpload  = document.getElementById('stepUpload');
const stepConfirm = document.getElementById('stepConfirm');
const stepDone    = document.getElementById('stepDone');

// ページロード時：一時保存済み画像を復元
if (savedTempId) {
  previewImg.src = '/karte/api/photo_import.php?action=get_temp_preview&temp_id=' + encodeURIComponent(savedTempId);
  previewImg.style.display = 'block';
  previewImg.onerror = () => {
    // 一時ファイルが消えていたらクリア
    sessionStorage.removeItem('photo_import_temp_id');
    savedTempId = null;
    previewImg.style.display = 'none';
    btnAnalyze.disabled = true;
  };
  btnAnalyze.disabled = false;
}

// ドロップゾーン
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('drag-over');
  const f = e.dataTransfer.files[0];
  if (f) handleFile(f);
});

sheetInput.addEventListener('change', () => {
  if (sheetInput.files[0]) handleFile(sheetInput.files[0]);
});

// 画像をCanvasで縮小してBlobを返す
function resizeImage(file, maxW, quality) {
  return new Promise((resolve) => {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = () => {
      URL.revokeObjectURL(url);
      let w = img.width, h = img.height;
      if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      canvas.toBlob(blob => resolve(blob), 'image/jpeg', quality / 100);
    };
    img.src = url;
  });
}

function formatSize(bytes) {
  if (bytes >= 1024*1024) return (bytes/1024/1024).toFixed(1) + ' MB';
  return Math.round(bytes/1024) + ' KB';
}

const resizePanel  = document.getElementById('resizePanel');
const chkResize    = document.getElementById('chkResize');
const selMaxW      = document.getElementById('selMaxW');
const rngQuality   = document.getElementById('rngQuality');
const lblQuality   = document.getElementById('lblQuality');
const lblFileSize  = document.getElementById('lblFileSize');

rngQuality.addEventListener('input', () => { lblQuality.textContent = rngQuality.value + '%'; });

async function handleFile(file) {
  if (file.size > 20 * 1024 * 1024) { showError('uploadError','20MB以下のファイルを選択してください'); return; }

  // プレビュー表示（オリジナル）
  const reader = new FileReader();
  reader.onload = e => {
    previewImg.src = e.target.result;
    previewImg.style.display = 'block';
  };
  reader.readAsDataURL(file);
  btnAnalyze.disabled = false;
  hideError('uploadError');

  // 縮小パネル表示
  resizePanel.style.display = '';
  lblFileSize.textContent = '元のサイズ: ' + formatSize(file.size);

  // selectedFile は解析時に縮小処理して確定
  selectedFile = file;
  savedTempId  = null;
  sessionStorage.removeItem('photo_import_temp_id');
}

// 縮小＋一時保存して解析用ファイルを返す
async function prepareFile() {
  let file = selectedFile;
  if (!file) return null;
  if (chkResize.checked) {
    const maxW    = parseInt(selMaxW.value);
    const quality = parseInt(rngQuality.value);
    const resized = await resizeImage(file, maxW, quality);
    lblFileSize.textContent = `元: ${formatSize(file.size)} → 縮小後: ${formatSize(resized.size)}`;
    file = new File([resized], 'sheet.jpg', {type:'image/jpeg'});
  }
  return file;
}

btnAnalyze.addEventListener('click', async () => {
  if (!selectedFile && !savedTempId) { showError('uploadError','画像を選択してください'); return; }

  stepUpload.style.display = 'none';
  loadingBox.style.display = 'block';
  startTimer();

  abortCtrl = new AbortController();
  const fd = new FormData();
  fd.append('action', 'analyze');
  fd.append('csrf_token', CSRF);

  if (selectedFile) {
    const file = await prepareFile();
    fd.append('sheet', file);
  } else {
    fd.append('temp_id', savedTempId);
  }
  const apiKey = document.getElementById('apiKeyInput').value.trim();
  if (apiKey) fd.append('api_key_override', apiKey);

  try {
    const res  = await fetch('/karte/api/photo_import.php', {method:'POST', body:fd, signal: abortCtrl.signal});
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(_) {
      throw new Error('サーバーの応答が不正です（タイムアウトの可能性）。もう一度お試しください。');
    }
    stopTimer();
    loadingBox.style.display = 'none';
    if (!data.success) { stepUpload.style.display = ''; showError('uploadError', data.error); return; }
    allStudents     = data.students || [];
    analysisResults = data.results || [];
    renderResults();
    stepConfirm.style.display = '';
  } catch(e) {
    stopTimer();
    loadingBox.style.display = 'none';
    if (e.name === 'AbortError') return; // キャンセル時は何もしない
    stepUpload.style.display = '';
    showError('uploadError', 'エラー: ' + e.message);
  }
});

function renderResults() {
  const grid = document.getElementById('resultsGrid');
  grid.innerHTML = '';
  analysisResults.forEach((r, i) => {
    const card = document.createElement('div');
    card.className = 'result-card ' + (r.student_id ? 'matched' : 'unmatched');
    card.id = 'card-' + i;

    const matchBadge = r.student_id
      ? `<span class="match-badge badge-ok">✓ ${r.match_score}%</span>`
      : `<span class="match-badge badge-ng">未マッチ</span>`;

    const options = `<option value="">— スキップ —</option>` +
      allStudents.map(s =>
        `<option value="${s.student_id}" ${s.student_id === r.student_id ? 'selected' : ''}>${s.name}</option>`
      ).join('');

    card.innerHTML = `
      <img class="result-photo" src="${r.crop_url}" alt="${r.detected_name}">
      <div class="result-info">
        <div class="result-detected">検出名: <span>${r.detected_name || '不明'}</span> ${matchBadge}</div>
        <select class="result-select" id="sel-${i}" onchange="onSelectChange(${i})">
          ${options}
        </select>
      </div>
    `;
    grid.appendChild(card);
  });
  updateSummary();
  document.getElementById('btnSave').disabled = false;
}

function onSelectChange(i) {
  const sel = document.getElementById('sel-' + i);
  const card = document.getElementById('card-' + i);
  analysisResults[i].student_id = sel.value || null;
  card.className = 'result-card ' + (sel.value ? 'matched' : 'skipped');
  updateSummary();
}

function updateSummary() {
  const total   = analysisResults.length;
  const matched = analysisResults.filter(r => r.student_id).length;
  const skipped = total - matched;
  document.getElementById('summaryBar').innerHTML =
    `<span>検出: <strong>${total}名</strong></span>` +
    `<span>保存予定: <strong style="color:#15803d">${matched}名</strong></span>` +
    `<span>スキップ: <strong style="color:#dc2626">${skipped}名</strong></span>`;
}

document.getElementById('btnSave').addEventListener('click', async () => {
  const assignments = analysisResults
    .filter(r => r.student_id)
    .map(r => ({student_id: r.student_id, crop_file: r.crop_file || r.crop_url.split('/').pop()}));

  if (!assignments.length) { showError('confirmError','保存する写真がありません'); return; }
  if (!confirm(`${assignments.length}名の写真を保存します。既存の写真は上書きされます。よろしいですか？`)) return;

  document.getElementById('btnSave').disabled = true;
  document.getElementById('loadingText').textContent = '写真を保存中…';
  loadingBox.style.display = '';
  stepConfirm.style.display = 'none';

  const fd = new FormData();
  fd.append('action', 'save');
  fd.append('csrf_token', CSRF);
  fd.append('assignments', JSON.stringify(assignments));

  try {
    const res  = await fetch('/karte/api/photo_import.php', {method:'POST', body:fd});
    const data = await res.json();
    loadingBox.style.display = 'none';
    if (!data.success) { stepConfirm.style.display = ''; showError('confirmError', data.error); return; }
    document.getElementById('doneMsg').textContent = `✓ ${data.saved}名の写真を保存しました。`;
    stepDone.style.display = '';
  } catch(e) {
    loadingBox.style.display = 'none';
    stepConfirm.style.display = '';
    showError('confirmError', 'エラー: ' + e.message);
  }
});

function resetAll() {
  // 一時ファイルをサーバーから削除
  if (savedTempId) {
    const fd = new FormData();
    fd.append('action', 'clear_temp');
    fd.append('csrf_token', CSRF);
    fd.append('temp_id', savedTempId);
    fetch('/karte/api/photo_import.php', {method:'POST', body:fd}).catch(()=>{});
    sessionStorage.removeItem('photo_import_temp_id');
    savedTempId = null;
  }
  selectedFile = null;
  sheetInput.value = '';
  previewImg.style.display = 'none';
  previewImg.src = '';
  resizePanel.style.display = 'none';
  lblFileSize.textContent = '';
  btnAnalyze.disabled = true;
  stepUpload.style.display = '';
  stepConfirm.style.display = 'none';
  stepDone.style.display = 'none';
  loadingBox.style.display = 'none';
  hideError('uploadError');
  hideError('confirmError');
  analysisResults = [];
}

function showError(id, msg) {
  const el = document.getElementById(id);
  el.textContent = '⚠ ' + msg;
  el.style.display = 'block';
}
function hideError(id) {
  document.getElementById(id).style.display = 'none';
}
</script>
</body>
</html>
