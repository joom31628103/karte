<?php
require_once 'config.php';
requireLogin();
$sid = $_GET['id'] ?? '';
if (!$sid) { header('Location: /karte/home.php'); exit; }
$conn = getDB();
$s = $conn->query("SELECT * FROM students WHERE student_id='".$conn->real_escape_string($sid)."'")->fetch_assoc();
if (!$s) { $conn->close(); header('Location: /karte/home.php'); exit; }
// 直近の面談記録・指導記録を取得
$records  = []; $r = $conn->query("SELECT * FROM karte_records WHERE student_id='".$conn->real_escape_string($sid)."' ORDER BY record_date DESC LIMIT 10");
while ($row=$r->fetch_assoc()) $records[]=$row;
$interviews=[]; $r2=$conn->query("SELECT * FROM karte_interviews WHERE student_id='".$conn->real_escape_string($sid)."' ORDER BY interview_date DESC LIMIT 5");
while ($row=$r2->fetch_assoc()) $interviews[]=$row;
$conn->close();

function fv($val) { return htmlspecialchars($val ?? ''); }
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title><?= fv($s['name']) ?> カード印刷</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'MS Mincho','Yu Mincho','Hiragino Mincho ProN',serif;background:#fff;color:#000;font-size:10pt;}
@media screen{
  body{background:#e5e7eb;padding:20px;}
  .page{background:#fff;margin:0 auto;padding:20px;width:210mm;min-height:297mm;box-shadow:0 4px 20px rgba(0,0,0,.15);}
  .print-btn{position:fixed;top:16px;right:16px;padding:10px 20px;background:#7c3aed;color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;font-family:'Hiragino Sans','Yu Gothic UI',sans-serif;z-index:999;}
  .print-btn:hover{background:#6d28d9;}
  .back-btn{position:fixed;top:16px;left:16px;padding:10px 16px;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.85rem;font-weight:600;cursor:pointer;font-family:'Hiragino Sans','Yu Gothic UI',sans-serif;color:#64748b;text-decoration:none;z-index:999;}
}
@media print{
  .print-btn,.back-btn{display:none;}
  body{background:#fff;}
  .page{padding:10mm;width:100%;}
  @page{size:A4;margin:10mm;}
}
.page{font-size:9pt;}
/* Title */
.card-title{text-align:center;font-size:16pt;font-weight:bold;letter-spacing:.15em;margin-bottom:2mm;border-bottom:2px solid #000;padding-bottom:3mm;}
.card-year{font-size:11pt;margin-right:8mm;}
/* Two columns layout */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:4mm;margin-top:3mm;}
/* Tables */
table.karte{width:100%;border-collapse:collapse;}
table.karte td,table.karte th{border:1px solid #000;padding:2mm 3mm;vertical-align:top;}
table.karte th{background:#e8e8e8;font-weight:bold;white-space:nowrap;font-size:8.5pt;}
table.karte td.label{background:#f0f0f0;font-weight:bold;white-space:nowrap;width:22mm;font-size:8.5pt;}
.cell-value{min-height:6mm;display:block;}
/* Section heading */
.sec-head{background:#333;color:#fff;font-weight:bold;padding:1.5mm 3mm;font-size:9pt;margin:4mm 0 1mm;}
/* Records table */
table.rec{width:100%;border-collapse:collapse;font-size:8pt;}
table.rec th{border:1px solid #000;background:#ddd;padding:1.5mm 2mm;font-weight:bold;white-space:nowrap;}
table.rec td{border:1px solid #000;padding:1.5mm 2mm;vertical-align:top;}
table.rec td.date-col{white-space:nowrap;width:16mm;}
table.rec td.type-col{white-space:nowrap;width:14mm;}
.row-empty td{color:#999;text-align:center;}
.stamp-area{display:flex;gap:4mm;justify-content:flex-end;margin-top:3mm;}
.stamp-box{border:1px solid #000;width:18mm;height:18mm;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.stamp-label{font-size:7pt;text-align:center;}
</style>
</head>
<body>
<?php if(php_sapi_name()!=='cli'): ?>
<button class="print-btn" onclick="window.print()">🖨 印刷する</button>
<a href="/karte/karte_detail.php?id=<?= urlencode($sid) ?>" class="back-btn">← 戻る</a>
<?php endif; ?>

<div class="page">
  <div class="card-title">
    <span class="card-year"><?= $year ?>年度</span>
    生　徒　カ　ル　テ
  </div>

  <div class="two-col">
    <!-- 左列: 基本情報 -->
    <div>
      <table class="karte">
        <tr>
          <td class="label">学籍番号</td>
          <td><span class="cell-value"><?= fv($s['student_id']) ?></span></td>
          <td class="label">出席番号</td>
          <td><span class="cell-value"><?= fv($s['seat_number']) ?></span></td>
        </tr>
        <tr>
          <td class="label">クラス</td>
          <td colspan="3"><span class="cell-value"><?= fv($s['class_name']) ?></span></td>
        </tr>
        <tr>
          <td class="label" rowspan="2">氏&nbsp;名</td>
          <td colspan="3" style="font-size:7.5pt;color:#555">ふりがな：<?= fv($s['furigana']) ?></td>
        </tr>
        <tr>
          <td colspan="3" style="font-size:13pt;font-weight:bold;padding:3mm 3mm;">
            <?= fv($s['name']) ?>
          </td>
        </tr>
        <tr>
          <td class="label">性&nbsp;別</td>
          <td><span class="cell-value"><?= fv($s['gender']) ?></span></td>
          <td class="label">生年月日</td>
          <td><span class="cell-value"><?= fv($s['birthday']) ?></span></td>
        </tr>
        <tr>
          <td class="label" colspan="1">保護者名</td>
          <td colspan="3"><span class="cell-value"><?= fv($s['parent_name']) ?></span></td>
        </tr>
        <tr>
          <td class="label">電話番号</td>
          <td colspan="3"><span class="cell-value"><?= fv($s['phone']) ?></span></td>
        </tr>
        <tr>
          <td class="label">住&nbsp;所</td>
          <td colspan="3"><span class="cell-value" style="min-height:10mm"><?= fv($s['address']) ?></span></td>
        </tr>
        <tr>
          <td class="label">備&nbsp;考</td>
          <td colspan="3"><span class="cell-value" style="min-height:12mm;white-space:pre-wrap"><?= fv($s['notes']) ?></span></td>
        </tr>
      </table>
      <div class="stamp-area">
        <div class="stamp-box"><div class="stamp-label">担任印</div></div>
        <div class="stamp-box"><div class="stamp-label">学年主任</div></div>
        <div class="stamp-box"><div class="stamp-label">管理職</div></div>
      </div>
    </div>

    <!-- 右列: 出欠サマリー -->
    <div>
      <div class="sec-head">出欠・遅刻・早退（記録サマリー）</div>
      <table class="karte">
        <tr>
          <th>区分</th><th>件数</th>
        </tr>
        <?php
        $conn2 = getDB();
        $esc = function($v) use($conn2){return $conn2->real_escape_string($v);};
        foreach(['欠席','遅刻','早退'] as $at) {
            $cnt = $conn2->query("SELECT COUNT(*) AS c FROM karte_attendance WHERE student_id='".$esc($sid)."' AND att_type='$at'")->fetch_assoc()['c'];
            echo "<tr><td class='label'>$at</td><td>$cnt 件</td></tr>";
        }
        $conn2->close();
        ?>
      </table>
    </div>
  </div>

  <!-- 指導記録 -->
  <div class="sec-head">担任メモ・指導記録（直近10件）</div>
  <table class="rec">
    <thead>
      <tr><th>日付</th><th>種類</th><th>内容</th><th>対応者</th><th>次回対応</th></tr>
    </thead>
    <tbody>
      <?php if (empty($records)): ?>
      <tr class="row-empty"><td colspan="5">記録がありません</td></tr>
      <?php else: foreach ($records as $rec): ?>
      <tr>
        <td class="date-col"><?= fv($rec['record_date']) ?></td>
        <td class="type-col"><?= fv($rec['record_type']) ?></td>
        <td style="white-space:pre-wrap"><?= fv($rec['content']) ?></td>
        <td style="white-space:nowrap"><?= fv($rec['teacher']) ?></td>
        <td style="white-space:pre-wrap"><?= fv($rec['next_action']) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <!-- 面談記録 -->
  <div class="sec-head">面談記録（直近5件）</div>
  <table class="rec">
    <thead>
      <tr><th>日付</th><th>種別</th><th>参加者</th><th>内容</th><th>今後の対応</th></tr>
    </thead>
    <tbody>
      <?php if (empty($interviews)): ?>
      <tr class="row-empty"><td colspan="5">記録がありません</td></tr>
      <?php else: foreach ($interviews as $iv): ?>
      <tr>
        <td class="date-col"><?= fv($iv['interview_date']) ?></td>
        <td class="type-col"><?= fv($iv['interview_type']) ?></td>
        <td><?= fv($iv['participants']) ?></td>
        <td style="white-space:pre-wrap"><?= fv($iv['content']) ?></td>
        <td style="white-space:pre-wrap"><?= fv($iv['next_action']) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <div style="text-align:right;margin-top:4mm;font-size:7.5pt;color:#555;">
    印刷日：<?= date('Y年m月d日') ?>　担任：<?= fv($_SESSION['teacher_name']) ?> 先生
  </div>
</div>
</body>
</html>
