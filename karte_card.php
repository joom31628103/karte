<?php
require_once 'config.php';
requireLogin();
$sid = $_GET['id'] ?? '';
if (!$sid) { header('Location: /karte/home.php'); exit; }
$conn = getDB();
$s = $conn->query("SELECT * FROM students WHERE student_id='".$conn->real_escape_string($sid)."'")->fetch_assoc();
if (!$s) { $conn->close(); header('Location: /karte/home.php'); exit; }

$records=[]; $r=$conn->query("SELECT * FROM karte_records WHERE student_id='".$conn->real_escape_string($sid)."' ORDER BY record_date DESC LIMIT 20");
while ($row=$r->fetch_assoc()) $records[]=$row;
$interviews=[]; $r2=$conn->query("SELECT * FROM karte_interviews WHERE student_id='".$conn->real_escape_string($sid)."' ORDER BY interview_date DESC LIMIT 10");
while ($row=$r2->fetch_assoc()) $interviews[]=$row;

$att=[];
foreach(['欠席','遅刻','早退'] as $at) {
    $cnt=$conn->query("SELECT COUNT(*) AS c FROM karte_attendance WHERE student_id='".$conn->real_escape_string($sid)."' AND att_type='$at'")->fetch_assoc()['c'];
    $att[$at]=$cnt;
}
$conn->close();

function fv($v){return htmlspecialchars($v??'');}
$year=date('Y'); $teacher=htmlspecialchars($_SESSION['teacher_name']??'');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/svg+xml" href="/karte/favicon.php">
  <link rel="apple-touch-icon" href="/karte/favicon.php?size=180">
<title>印刷・PDF — <?= fv($s['name']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#1a2a55;--navy2:#2c3e6b;}

@media screen{
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo',sans-serif;background:#cdd1dc;min-height:100vh;font-size:13px;}
.topbar{background:linear-gradient(180deg,var(--navy2),var(--navy));color:#e8ecff;padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:2px solid #0f1e40;height:46px;position:sticky;top:0;z-index:300;}
.topbar-title{font-size:.9rem;font-weight:900;white-space:nowrap;}
.topbar-name{font-size:.82rem;color:#c4d4ff;white-space:nowrap;}
.topbar-right{display:flex;align-items:center;gap:8px;margin-left:auto;}
.tab-bar{background:var(--navy);display:flex;gap:2px;padding:0 14px;border-bottom:2px solid #0f1e40;position:sticky;top:46px;z-index:299;}
.tab-btn{padding:8px 18px;color:#a0b0d0;font-size:.82rem;font-weight:700;border:none;background:none;cursor:pointer;font-family:inherit;border-bottom:3px solid transparent;transition:.15s;}
.tab-btn.active{color:#fff;border-bottom-color:#7eb8ff;}
.tab-btn:hover{color:#e0e8ff;}
.act-btn{padding:5px 13px;border-radius:6px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.12);color:#e8ecff;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;}
.act-btn:hover{background:rgba(255,255,255,.25);}
.act-btn.pdf{background:rgba(220,50,50,.35);border-color:rgba(255,120,120,.4);}
.act-btn.pdf:hover{background:rgba(220,50,50,.55);}
.act-btn.print{background:rgba(60,130,230,.35);border-color:rgba(100,180,255,.4);}
.act-btn.print:hover{background:rgba(60,130,230,.55);}
.page-wrap{padding:20px;display:flex;justify-content:center;}
.page{background:#fff;width:210mm;min-height:297mm;box-shadow:0 4px 20px rgba(0,0,0,.2);padding:14mm 14mm 10mm;}
.tab-content{display:none;}
.tab-content.active{display:block;}
.kebab-menu{position:relative;}
.kebab-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);color:#e8ecff;border-radius:6px;padding:6px 10px;cursor:pointer;line-height:1;font-family:inherit;display:flex;flex-direction:column;gap:4px;align-items:center;justify-content:center;width:38px;height:34px;}
.kebab-btn span{display:block;width:18px;height:2px;background:#e8ecff;border-radius:1px;}
.kebab-btn:hover{background:rgba(255,255,255,.25);}
.kebab-dropdown{display:none;position:absolute;top:calc(100% + 6px);right:0;background:linear-gradient(180deg,var(--navy2),var(--navy));border:1px solid rgba(255,255,255,.2);border-radius:8px;min-width:175px;z-index:400;box-shadow:0 8px 24px rgba(0,0,0,.4);overflow:hidden;}
.kebab-dropdown.open{display:block;}
.kebab-dropdown a,.kebab-dropdown button{display:block;width:100%;padding:10px 16px;color:#e8ecff;text-decoration:none;font-size:.85rem;border:none;border-bottom:1px solid rgba(255,255,255,.08);background:none;text-align:left;cursor:pointer;font-family:inherit;box-sizing:border-box;}
.kebab-dropdown a:last-child,.kebab-dropdown button:last-child{border-bottom:none;}
.kebab-dropdown a:hover,.kebab-dropdown button:hover{background:rgba(255,255,255,.15);}
.kebab-dropdown .current-page{color:#6a7a99;cursor:default;pointer-events:none;}
.kebab-dropdown .current-page:hover{background:none;}
}

@media print{
body{background:#fff;font-family:'MS Mincho','Yu Mincho','Hiragino Mincho ProN',serif;}
.topbar,.tab-bar,.no-print{display:none!important;}
.page-wrap{padding:0;}
.page{width:100%;min-height:auto;box-shadow:none;padding:0;}
.tab-content{display:none!important;}
.tab-content.active{display:block!important;}
@page{size:A4;margin:10mm;}
}

.page{font-size:9pt;color:#000;font-family:'MS Mincho','Yu Mincho','Hiragino Mincho ProN',serif;}
.card-title{text-align:center;font-size:15pt;font-weight:bold;letter-spacing:.2em;border-bottom:2.5px solid #000;padding-bottom:3mm;margin-bottom:3mm;}
.card-subtitle{font-size:9pt;color:#444;text-align:right;margin-bottom:2mm;}
table.kt{width:100%;border-collapse:collapse;}
table.kt td,table.kt th{border:1px solid #333;padding:1.5mm 2.5mm;vertical-align:top;font-size:8.5pt;}
table.kt th{background:#ddd;font-weight:bold;white-space:nowrap;}
td.lbl{background:#ebebeb;font-weight:bold;white-space:nowrap;width:20mm;}
.cell{min-height:6mm;display:block;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:4mm;margin-top:3mm;}
.sec{background:#333;color:#fff;font-weight:bold;padding:1.5mm 3mm;font-size:9pt;margin:4mm 0 1.5mm;}
.stamp-row{display:flex;gap:4mm;justify-content:flex-end;margin-top:3mm;}
.stamp{border:1px solid #000;width:18mm;height:18mm;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.stamp-lbl{font-size:7pt;text-align:center;}
table.rec{width:100%;border-collapse:collapse;font-size:8pt;}
table.rec th{border:1px solid #333;background:#ddd;padding:1.5mm 2mm;font-weight:bold;white-space:nowrap;}
table.rec td{border:1px solid #333;padding:1.5mm 2mm;vertical-align:top;}
.dt{white-space:nowrap;width:16mm;}
.ty{white-space:nowrap;width:14mm;}
.empty td{color:#999;text-align:center;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:3mm;margin-top:3mm;}
.info-box{border:1px solid #bbb;border-radius:1.5mm;padding:2mm 3mm;}
.info-box-title{font-size:7.5pt;font-weight:bold;color:#444;border-bottom:1px solid #ccc;margin-bottom:1.5mm;padding-bottom:1mm;}
.info-row{display:flex;gap:2mm;margin-bottom:1mm;font-size:8.5pt;}
.info-lbl{color:#555;white-space:nowrap;min-width:18mm;}
.info-val{font-weight:bold;}
.rec-header{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:2mm;}
.rec-period{font-size:8pt;color:#555;}
</style>
</head>
<body>

<div class="topbar no-print">
  <div class="topbar-title">🖨 印刷・PDF</div>
  <div class="topbar-name"><?= fv($s['name']) ?> さん</div>
  <div class="topbar-right">
    <button class="act-btn print" onclick="window.print()">🖨 印刷</button>
    <button class="act-btn pdf" onclick="downloadPdf()">⬇ PDF</button>
    <div class="kebab-menu">
      <button class="kebab-btn" onclick="toggleKebab(event)" title="メニュー"><span></span><span></span><span></span></button>
      <div class="kebab-dropdown" id="kebabDropdown">
        <a href="/karte/karte_detail.php?id=<?= urlencode($sid) ?>">🏫 生徒情報</a>
        <a href="/karte/karte_detail.php?id=<?= urlencode($sid) ?>&list=1">📋 一覧表示</a>
        <a href="/karte/home.php">🏠 HOME</a>
        <a class="current-page">🖨 印刷・PDF</a>
        <a href="/karte/gakuseki.php">📚 学籍管理</a>
        <a href="/karte/student_manager.php">👥 生徒管理</a>
        <a href="/karte/backup.php">🗄️ バックアップ</a>
        <a href="/karte/account.php">⚙ アカウント</a>
        <a href="/karte/logout.php">🚪 ログアウト</a>
      </div>
    </div>
  </div>
</div>

<div class="tab-bar no-print">
  <button class="tab-btn active" onclick="switchTab(0,this)">📄 生徒カルテ</button>
  <button class="tab-btn" onclick="switchTab(1,this)">📋 基本情報カード</button>
  <button class="tab-btn" onclick="switchTab(2,this)">📝 記録シート</button>
</div>

<div class="page-wrap">
<div class="page">

<!-- タブ1: 生徒カルテ（総合） -->
<div class="tab-content active" id="tab0">
  <div class="card-title"><?= $year ?>年度　生　徒　カ　ル　テ</div>
  <div class="card-subtitle">出力日：<?= date('Y年m月d日') ?>　担任：<?= $teacher ?> 先生</div>
  <div class="two-col">
    <div>
      <table class="kt">
        <tr>
          <td class="lbl">学籍番号</td><td><?= fv($s['student_id']) ?></td>
          <td class="lbl">出席番号</td><td><?= fv($s['seat_number']) ?></td>
        </tr>
        <tr><td class="lbl">クラス</td><td colspan="3"><?= fv($s['class_name']) ?></td></tr>
        <tr>
          <td class="lbl" rowspan="2">氏　名</td>
          <td colspan="3" style="font-size:7.5pt;color:#555">ふりがな：<?= fv($s['furigana']) ?></td>
        </tr>
        <tr><td colspan="3" style="font-size:13pt;font-weight:bold;padding:2.5mm 3mm;"><?= fv($s['name']) ?></td></tr>
        <tr>
          <td class="lbl">性　別</td><td><?= fv($s['gender']) ?></td>
          <td class="lbl">生年月日</td><td><?= fv($s['birthday']) ?></td>
        </tr>
        <tr><td class="lbl">保護者名</td><td colspan="3"><?= fv($s['parent_name']) ?></td></tr>
        <tr><td class="lbl">家庭電話</td><td colspan="3"><?= fv($s['phone']) ?></td></tr>
        <tr><td class="lbl">生徒携帯</td><td colspan="3"><?= fv($s['student_phone'] ?? '') ?></td></tr>
        <tr><td class="lbl">住　所</td><td colspan="3"><span class="cell" style="min-height:10mm"><?= fv($s['address']) ?></span></td></tr>
        <tr><td class="lbl">備　考</td><td colspan="3"><span class="cell" style="min-height:12mm;white-space:pre-wrap"><?= fv($s['notes']) ?></span></td></tr>
      </table>
      <div class="stamp-row">
        <div class="stamp"><div class="stamp-lbl">担任印</div></div>
        <div class="stamp"><div class="stamp-lbl">学年主任</div></div>
        <div class="stamp"><div class="stamp-lbl">管理職</div></div>
      </div>
    </div>
    <div>
      <div class="sec">出欠サマリー</div>
      <table class="kt">
        <tr><th>区分</th><th>件数</th></tr>
        <?php foreach($att as $k=>$v): ?><tr><td class="lbl"><?= $k ?></td><td><?= $v ?> 件</td></tr><?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="sec">担任メモ・指導記録（直近20件）</div>
  <table class="rec">
    <thead><tr><th>日付</th><th>種類</th><th>内容</th><th>対応者</th><th>次回対応</th></tr></thead>
    <tbody>
      <?php if(empty($records)): ?><tr class="empty"><td colspan="5">記録がありません</td></tr>
      <?php else: foreach($records as $rec): ?>
      <tr><td class="dt"><?= fv($rec['record_date']) ?></td><td class="ty"><?= fv($rec['record_type']) ?></td><td style="white-space:pre-wrap"><?= fv($rec['content']) ?></td><td style="white-space:nowrap"><?= fv($rec['teacher']) ?></td><td style="white-space:pre-wrap"><?= fv($rec['next_action']) ?></td></tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <div class="sec">面談記録（直近10件）</div>
  <table class="rec">
    <thead><tr><th>日付</th><th>種別</th><th>参加者</th><th>内容</th><th>今後の対応</th></tr></thead>
    <tbody>
      <?php if(empty($interviews)): ?><tr class="empty"><td colspan="5">記録がありません</td></tr>
      <?php else: foreach($interviews as $iv): ?>
      <tr><td class="dt"><?= fv($iv['interview_date']) ?></td><td class="ty"><?= fv($iv['interview_type']) ?></td><td><?= fv($iv['participants']) ?></td><td style="white-space:pre-wrap"><?= fv($iv['content']) ?></td><td style="white-space:pre-wrap"><?= fv($iv['next_action']) ?></td></tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- タブ2: 基本情報カード -->
<div class="tab-content" id="tab1">
  <div class="card-title"><?= $year ?>年度　基本情報カード</div>
  <div class="card-subtitle">出力日：<?= date('Y年m月d日') ?>　担任：<?= $teacher ?> 先生</div>
  <table class="kt" style="margin-bottom:4mm;">
    <tr>
      <td class="lbl" style="width:18mm;">クラス</td><td style="width:40mm;"><?= fv($s['class_name']) ?></td>
      <td class="lbl" style="width:18mm;">出席番号</td><td style="width:25mm;"><?= fv($s['seat_number']) ?></td>
      <td class="lbl" style="width:18mm;">性別</td><td><?= fv($s['gender']) ?></td>
    </tr>
    <tr><td class="lbl" rowspan="2">氏名</td><td colspan="5" style="font-size:7.5pt;color:#555">ふりがな：<?= fv($s['furigana']) ?></td></tr>
    <tr><td colspan="5" style="font-size:15pt;font-weight:bold;padding:2mm 3mm;letter-spacing:.05em;"><?= fv($s['name']) ?></td></tr>
    <tr><td class="lbl">生年月日</td><td colspan="5"><?= fv($s['birthday']) ?></td></tr>
  </table>
  <div class="info-grid">
    <div class="info-box">
      <div class="info-box-title">📞 連絡先</div>
      <div class="info-row"><span class="info-lbl">家庭代表電話</span><span class="info-val"><?= fv($s['phone']) ?></span></div>
      <div class="info-row"><span class="info-lbl">生徒携帯</span><span class="info-val"><?= fv($s['student_phone'] ?? '') ?></span></div>
      <div class="info-row"><span class="info-lbl">保護者名</span><span class="info-val"><?= fv($s['parent_name']) ?></span></div>
    </div>
    <div class="info-box">
      <div class="info-box-title">🏠 住所</div>
      <div style="font-size:8.5pt;white-space:pre-wrap;min-height:14mm;"><?= fv($s['address']) ?></div>
    </div>
    <div class="info-box" style="grid-column:1/-1;">
      <div class="info-box-title">📊 出欠サマリー</div>
      <div style="display:flex;gap:8mm;">
        <?php foreach($att as $k=>$v): ?><div style="font-size:9pt;"><?= $k ?>：<strong><?= $v ?></strong> 件</div><?php endforeach; ?>
      </div>
    </div>
    <div class="info-box" style="grid-column:1/-1;">
      <div class="info-box-title">📝 備考・特記事項</div>
      <div style="font-size:8.5pt;white-space:pre-wrap;min-height:18mm;"><?= fv($s['notes']) ?></div>
    </div>
  </div>
  <div style="margin-top:5mm;">
    <table class="kt">
      <tr><th style="width:22%">記入日</th><th style="width:26%">担任署名</th><th style="width:22%">保護者確認日</th><th style="width:30%">保護者署名</th></tr>
      <tr><td style="height:14mm;"></td><td></td><td></td><td></td></tr>
    </table>
  </div>
  <div class="stamp-row" style="margin-top:4mm;">
    <div class="stamp"><div class="stamp-lbl">担任印</div></div>
    <div class="stamp"><div class="stamp-lbl">学年主任</div></div>
    <div class="stamp"><div class="stamp-lbl">管理職</div></div>
  </div>
</div>

<!-- タブ3: 記録シート -->
<div class="tab-content" id="tab2">
  <div class="card-title"><?= $year ?>年度　指導・面談記録シート</div>
  <div class="card-subtitle"><?= fv($s['class_name']) ?>　<?= fv($s['name']) ?>（<?= fv($s['furigana']) ?>）　出力日：<?= date('Y年m月d日') ?>　担任：<?= $teacher ?> 先生</div>
  <div class="rec-header">
    <div class="sec" style="margin:0;flex:1;">担任メモ・指導記録</div>
    <div class="rec-period">（全<?= count($records) ?>件）</div>
  </div>
  <table class="rec" style="margin-top:1.5mm;">
    <thead><tr><th>日付</th><th>種類</th><th style="width:35%">内容</th><th>対応者</th><th style="width:25%">次回対応・メモ</th></tr></thead>
    <tbody>
      <?php if(empty($records)): ?><tr class="empty"><td colspan="5">記録がありません</td></tr>
      <?php else: foreach($records as $rec): ?>
      <tr><td class="dt"><?= fv($rec['record_date']) ?></td><td class="ty"><?= fv($rec['record_type']) ?></td><td style="white-space:pre-wrap"><?= fv($rec['content']) ?></td><td style="white-space:nowrap"><?= fv($rec['teacher']) ?></td><td style="white-space:pre-wrap"><?= fv($rec['next_action']) ?></td></tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <div style="height:6mm;"></div>
  <div class="rec-header">
    <div class="sec" style="margin:0;flex:1;">面談記録</div>
    <div class="rec-period">（全<?= count($interviews) ?>件）</div>
  </div>
  <table class="rec" style="margin-top:1.5mm;">
    <thead><tr><th>日付</th><th>種別</th><th>参加者</th><th style="width:35%">内容</th><th style="width:20%">今後の対応</th></tr></thead>
    <tbody>
      <?php if(empty($interviews)): ?><tr class="empty"><td colspan="5">記録がありません</td></tr>
      <?php else: foreach($interviews as $iv): ?>
      <tr><td class="dt"><?= fv($iv['interview_date']) ?></td><td class="ty"><?= fv($iv['interview_type']) ?></td><td><?= fv($iv['participants']) ?></td><td style="white-space:pre-wrap"><?= fv($iv['content']) ?></td><td style="white-space:pre-wrap"><?= fv($iv['next_action']) ?></td></tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

</div><!-- .page -->
</div><!-- .page-wrap -->


<script src="/karte/lib/html2pdf.bundle.min.js"></script>
<script>
let currentTab = 0;
function switchTab(idx, btn) {
  document.querySelectorAll('.tab-content').forEach((el,i) => el.classList.toggle('active', i===idx));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentTab = idx;
}
function downloadPdf() {
  const tabNames = ['生徒カルテ','基本情報カード','記録シート'];
  const name = <?= json_encode($s['name']) ?>;
  const filename = name + '_' + tabNames[currentTab] + '_<?= $year ?>年度.pdf';
  const el = document.getElementById('tab' + currentTab);
  const btn = document.querySelector('.act-btn.pdf');
  btn.textContent = '⏳ 生成中...';
  btn.disabled = true;
  html2pdf().set({
    margin: 10,
    filename: filename,
    image: { type: 'jpeg', quality: 0.97 },
    html2canvas: { scale: 2, useCORS: true, logging: false },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  }).from(el).save().then(() => {
    btn.textContent = '⬇ PDF';
    btn.disabled = false;
  });
}
function toggleKebab(e) {
  e.stopPropagation();
  document.getElementById('kebabDropdown').classList.toggle('open');
}
document.addEventListener('click', function() {
  const d = document.getElementById('kebabDropdown');
  if (d) d.classList.remove('open');
});
</script>
</body>
</html>
