<?php
require_once 'config.php';
requireLogin();
$sid = $_GET['id'] ?? '';
if (!$sid) { header('Location: /karte/home.php'); exit; }
$conn = getDB();
$s = $conn->query("SELECT * FROM students WHERE student_id='".$conn->real_escape_string($sid)."'")->fetch_assoc();
if (!$s) { $conn->close(); header('Location: /karte/home.php'); exit; }

// 学籍台帳参照
$gakno = $s['gakno'] ?? '';
$gak   = null;
$nendo_list = [];
if ($gakno) {
    $gak = $conn->query("SELECT * FROM gakuseki WHERE gakno='".$conn->real_escape_string($gakno)."'")->fetch_assoc();
    $nr  = $conn->query("SELECT sn.*, t.display_name AS tanninmei FROM student_nendo sn LEFT JOIN teachers t ON sn.teacher_id=t.id WHERE sn.gakno='".$conn->real_escape_string($gakno)."' ORDER BY sn.nendo");
    while ($row=$nr->fetch_assoc()) $nendo_list[]=$row;
}
// 最新年度情報
$latestNendo = end($nendo_list) ?: null;
reset($nendo_list);

// 前後の生徒
$prevNext = $conn->query("SELECT student_id FROM students ORDER BY class_name, seat_number, student_id");
$idList = [];
while ($r=$prevNext->fetch_assoc()) $idList[]=$r['student_id'];
$pos     = array_search($sid, $idList);
$prevId  = $pos > 0 ? $idList[$pos-1] : null;
$nextId  = $pos < count($idList)-1 ? $idList[$pos+1] : null;

$conn->close();

$RECORD_TYPES = ['面談','保護者連絡','欠席連絡','遅刻','早退','生活指導','進路','学習','体調','部活動','その他'];
$ATT_TYPES    = ['欠席','遅刻','早退'];
$INT_TYPES    = ['三者面談','個人面談','保護者面談','進路面談','その他'];

// 表示用値の取得（学籍>students の優先順位）
$dispName    = $gak['name']     ?? $s['name']         ?? '';
$dispFuri    = $gak['furigana'] ?? $s['furigana']      ?? '';
$dispBday    = $gak['birthday'] ?? $s['birthday']      ?? '';
$dispTel     = $gak['tel1']     ?? $s['phone']         ?? '';
$dispJyusyo  = ($gak ? trim(($gak['yuubin']?' 〒'.$gak['yuubin'].' ':'').$gak['jyusyo']) : $s['address']) ?? '';
$dispHogosya = $gak['hogosya']  ?? $s['parent_name']   ?? '';
$dispSeibetu = $gak['seibetu']  ?? $s['gender']        ?? '';
$dispGakunen = $latestNendo['gakunen']  ?? '';
$dispClass   = $latestNendo['class_no'] ?? $s['class_name'] ?? '';
$dispBango   = $latestNendo['bango']    ?? $s['seat_number'] ?? '';
$dispNendo   = $latestNendo['nendo']    ?? '';
$dispTannin  = $latestNendo['tanninmei'] ?? '';
$dispStatus  = $gak['gakuseki_status'] ?? '';
$dispNyunendo = $gak['nyunendo'] ?? '';
$dispPhoto   = $gak['photo'] ?? $s['photo'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($dispName) ?> — 生徒情報</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo','Noto Sans JP',sans-serif;background:#d0d4dc;min-height:100vh;font-size:13px;}

/* ── トップバー（FileMaker風濃紺） ── */
.fm-topbar{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);color:#fff;padding:4px 10px;display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:2px solid #0f1e40;min-height:42px;}
.fm-topbar-title{font-size:1.2rem;font-weight:900;letter-spacing:.04em;color:#e8ecff;display:flex;align-items:center;gap:8px;}
.fm-topbar-title .dot{width:8px;height:8px;border-radius:50%;background:#6ee7b7;display:inline-block;}
.fm-nav-arrows{display:flex;gap:3px;}
.fm-arrow{width:28px;height:28px;border-radius:6px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#fff;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:background .15s;}
.fm-arrow:hover{background:rgba(255,255,255,.25);}
.fm-arrow.disabled{opacity:.3;pointer-events:none;}
.fm-topbar-student{color:#c4d4ff;font-size:.88rem;font-weight:700;letter-spacing:.03em;}
.fm-topbar-right{display:flex;gap:6px;align-items:center;}
.fm-btn-top{padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#e8ecff;cursor:pointer;font-size:.78rem;font-family:inherit;text-decoration:none;transition:background .15s;white-space:nowrap;}
.fm-btn-top:hover{background:rgba(255,255,255,.25);}
.fm-btn-top.active{background:rgba(255,255,255,.3);border-color:rgba(255,255,255,.6);}

/* ── ナビゲーションボタン行 ── */
.fm-navbar{background:#3b4f8a;padding:4px 10px;display:flex;gap:4px;align-items:center;border-bottom:2px solid #263570;}
.fm-navbtn{padding:5px 13px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:5px;color:#dce4ff;font-size:.78rem;cursor:pointer;font-family:inherit;transition:all .15s;white-space:nowrap;}
.fm-navbtn:hover,.fm-navbtn.active{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);color:#fff;}
.fm-navbtn.active{border-color:#8ba4ff;}

/* ── 生徒情報ヘッダー ── */
.fm-student-header{background:#f0f2f8;border-bottom:2px solid #aab0cc;padding:10px 14px;}
.fm-header-row1{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;}
.fm-header-fields{flex:1;min-width:0;}
.fm-field-row{display:flex;gap:0;align-items:stretch;margin-bottom:5px;flex-wrap:wrap;}
.fm-field{display:flex;flex-direction:column;flex:1;min-width:80px;}
.fm-field-label{font-size:.68rem;color:#5a6080;font-weight:700;padding:2px 5px;background:#dde0ee;border:1px solid #aab0cc;border-bottom:none;text-align:center;letter-spacing:.03em;}
.fm-field-value{padding:4px 8px;background:#fff;border:1px solid #aab0cc;font-size:.85rem;font-weight:600;color:#1a2240;min-height:26px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fm-field-value.wide{white-space:normal;}
.fm-field-value.placeholder{color:#9aa0c0;font-weight:400;}
.fm-photo{width:80px;height:96px;background:#e4e7f0;border:2px solid #aab0cc;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#9aa0c0;font-size:.75rem;text-align:center;flex-shrink:0;overflow:hidden;transition:border-color .2s;}
.fm-photo:hover{border-color:#546099;}
.fm-photo img{width:100%;height:100%;object-fit:cover;display:block;}
.photo-wrap{position:relative;flex-shrink:0;}
.photo-del-btn{position:absolute;top:-6px;right:-6px;width:18px;height:18px;border-radius:50%;background:#dc2626;color:#fff;border:none;cursor:pointer;font-size:.7rem;display:none;align-items:center;justify-content:center;line-height:1;z-index:10;}
.photo-wrap:hover .photo-del-btn{display:flex;}

/* ── タブ ── */
.fm-tabs{background:#4a5a96;display:flex;gap:2px;padding:6px 10px 0;border-bottom:3px solid #2c3e6b;overflow-x:auto;}
.fm-tab{padding:7px 14px 6px;background:linear-gradient(180deg,#6a7bb5 0%,#4a5a96 100%);border:1px solid #3b4f8a;border-bottom:none;border-radius:5px 5px 0 0;color:#c4d0ff;font-size:.82rem;font-weight:700;cursor:pointer;white-space:nowrap;transition:all .15s;font-family:inherit;}
.fm-tab:hover{background:linear-gradient(180deg,#8a9fd5 0%,#6a7bb5 100%);color:#fff;}
.fm-tab.active{background:#f0f2f8;color:#1a2240;border-color:#aab0cc;border-bottom:3px solid #f0f2f8;margin-bottom:-3px;padding-bottom:9px;}

/* ── タブパネル ── */
.fm-panel-wrap{background:#f0f2f8;border:2px solid #aab0cc;border-top:none;min-height:400px;}
.fm-panel{display:none;padding:14px;}
.fm-panel.active{display:block;}

/* ── パネル内ツールバー ── */
.fm-panel-toolbar{display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap;}
.fm-panel-title{font-size:.9rem;font-weight:800;color:#1a2240;flex:1;}
.fm-add-btn{padding:6px 14px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:5px;color:#fff;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;}
.fm-add-btn:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}

/* ── テーブル ── */
.fm-table-wrap{overflow-x:auto;}
.fm-table{width:100%;border-collapse:collapse;font-size:.82rem;}
.fm-table thead tr{background:#3b4f8a;color:#dce4ff;}
.fm-table th{padding:7px 10px;text-align:left;font-size:.72rem;font-weight:700;letter-spacing:.04em;border:1px solid #263570;white-space:nowrap;}
.fm-table tbody tr{background:#fff;border-bottom:1px solid #d0d4e0;}
.fm-table tbody tr:nth-child(even){background:#f5f6fb;}
.fm-table tbody tr:hover{background:#e8ecff;}
.fm-table td{padding:7px 10px;border:1px solid #d0d4e0;vertical-align:top;color:#1a2240;}
.fm-table td.date-cell{white-space:nowrap;color:#3b4f8a;font-weight:700;}
.fm-table td.content-cell{max-width:300px;white-space:pre-wrap;word-break:break-word;}
.type-badge{display:inline-block;padding:2px 8px;border-radius:3px;font-size:.72rem;font-weight:700;border:1px solid;}
.type-面談{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd;}
.type-保護者連絡{background:#fce7f3;color:#9d174d;border-color:#f9a8d4;}
.type-欠席連絡,.type-欠席{background:#fee2e2;color:#dc2626;border-color:#fca5a5;}
.type-遅刻{background:#fef3c7;color:#92400e;border-color:#fde68a;}
.type-早退{background:#fef9c3;color:#854d0e;border-color:#fef08a;}
.type-生活指導{background:#ffe4e6;color:#9f1239;border-color:#fda4af;}
.type-進路{background:#dcfce7;color:#15803d;border-color:#86efac;}
.type-学習{background:#e0f2fe;color:#0369a1;border-color:#7dd3fc;}
.type-体調{background:#fce7f3;color:#9d174d;border-color:#f9a8d4;}
.type-部活動{background:#f3e8ff;color:#7e22ce;border-color:#d8b4fe;}
.type-その他{background:#f1f5f9;color:#64748b;border-color:#cbd5e1;}
.type-三者面談,.type-個人面談,.type-保護者面談,.type-進路面談{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd;}
.contact-badge{padding:2px 7px;border-radius:3px;font-size:.72rem;font-weight:600;border:1px solid;}
.contact-済{background:#dcfce7;color:#15803d;border-color:#86efac;}
.contact-未{background:#fef3c7;color:#92400e;border-color:#fde68a;}
.btn-sm{padding:3px 9px;border-radius:4px;font-size:.72rem;cursor:pointer;font-family:inherit;border:1px solid;}
.btn-edit-sm{background:#fff;border-color:#93c5fd;color:#1d4ed8;} .btn-edit-sm:hover{background:#dbeafe;}
.btn-del-sm{background:#fff;border-color:#fca5a5;color:#dc2626;} .btn-del-sm:hover{background:#fee2e2;}
.empty-msg{padding:36px;text-align:center;color:#9aa0c0;font-size:.85rem;}

/* ── 基本情報フォーム ── */
.fm-info-section{font-size:.72rem;font-weight:700;color:#3b4f8a;text-transform:uppercase;letter-spacing:.05em;margin:14px 0 8px;padding-bottom:3px;border-bottom:2px solid #aab0cc;}
.fm-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:4px;}
.fm-info-group{display:flex;flex-direction:column;gap:2px;}
.fm-info-group.full{grid-column:1/-1;}
.fm-info-group label{font-size:.68rem;font-weight:700;color:#5a6080;text-transform:uppercase;letter-spacing:.04em;}
.fm-info-input{padding:6px 9px;border:1px solid #aab0cc;border-radius:3px;font-size:.85rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;}
.fm-info-input:focus{border-color:#3b4f8a;background:#f5f7ff;}
.fm-info-input[readonly]{background:#f0f2f8;color:#5a6080;}
.fm-info-textarea{padding:6px 9px;border:1px solid #aab0cc;border-radius:3px;font-size:.85rem;font-family:inherit;color:#1a2240;background:#fff;resize:vertical;min-height:72px;outline:none;width:100%;}
.fm-info-textarea:focus{border-color:#3b4f8a;}
.fm-save-row{margin-top:12px;display:flex;align-items:center;gap:10px;}
.fm-save-btn{padding:7px 18px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:5px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;}
.fm-save-btn:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}
.save-ok{color:#15803d;font-size:.82rem;font-weight:700;display:none;}

/* 学籍参照エリア */
.gak-ref-box{background:#fff;border:1px solid #aab0cc;border-radius:6px;padding:12px;margin-bottom:14px;}
.gak-ref-box h4{font-size:.78rem;font-weight:800;color:#3b4f8a;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.gak-link-box{background:#fef9c3;border:1px solid #fde047;border-radius:5px;padding:10px 12px;margin-bottom:12px;font-size:.82rem;color:#713f12;}
.gak-link-form{display:flex;gap:6px;align-items:center;margin-top:8px;flex-wrap:wrap;}
.gak-link-input{padding:5px 9px;border:1px solid #aab0cc;border-radius:4px;font-size:.82rem;font-family:inherit;outline:none;width:150px;}
.gak-link-input:focus{border-color:#3b4f8a;}

/* 地図パネル */
.map-layout{display:grid;grid-template-columns:280px 1fr;gap:14px;height:100%;}
.map-sidebar{display:flex;flex-direction:column;gap:10px;}
.map-addr-card{background:#fff;border:1px solid #aab0cc;border-radius:6px;padding:12px;}
.map-addr-card h4{font-size:.75rem;font-weight:800;color:#3b4f8a;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:5px;}
.map-addr-line{font-size:.85rem;color:#1a2240;margin-bottom:4px;line-height:1.5;}
.map-addr-label{font-size:.68rem;font-weight:700;color:#5a6080;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;}
.map-btn{display:block;width:100%;padding:8px 12px;border-radius:5px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit;text-align:center;text-decoration:none;transition:all .15s;border:1px solid;}
.map-btn-primary{background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border-color:#263570;color:#fff;}
.map-btn-primary:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}
.map-btn-google{background:#fff;border-color:#ea4335;color:#ea4335;}
.map-btn-google:hover{background:#fff5f5;}
.map-btn-search{background:#fff;border-color:#aab0cc;color:#3b4f8a;}
.map-btn-search:hover{background:#f0f2f8;}
.map-search-row{display:flex;gap:6px;margin-top:6px;}
.map-search-input{flex:1;padding:6px 9px;border:1px solid #aab0cc;border-radius:4px;font-size:.82rem;font-family:inherit;outline:none;}
.map-search-input:focus{border-color:#3b4f8a;}
.map-frame-wrap{position:relative;border:2px solid #aab0cc;border-radius:6px;overflow:hidden;background:#e4e7f0;min-height:480px;}
.map-frame-wrap iframe{display:block;width:100%;height:100%;min-height:480px;border:none;}
.map-loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#e4e7f0;flex-direction:column;gap:10px;color:#5a6080;font-size:.85rem;}
.map-no-addr{display:flex;align-items:center;justify-content:center;height:200px;color:#9aa0c0;font-size:.88rem;text-align:center;flex-direction:column;gap:8px;}
@media(max-width:760px){.map-layout{grid-template-columns:1fr;} .map-frame-wrap{min-height:300px;} .map-frame-wrap iframe{min-height:300px;}}

/* 年度テーブル */
.nendo-table{width:100%;border-collapse:collapse;font-size:.8rem;margin-top:8px;}
.nendo-table th{background:#3b4f8a;color:#dce4ff;padding:5px 8px;border:1px solid #263570;font-size:.7rem;}
.nendo-table td{padding:5px 8px;border:1px solid #d0d4e0;background:#fff;}
.nendo-table tr:nth-child(even) td{background:#f5f6fb;}

/* メモパネル */
.memo-area{width:100%;min-height:200px;padding:10px;border:1px solid #aab0cc;border-radius:4px;font-size:.9rem;font-family:inherit;background:#fff;resize:vertical;color:#1a2240;outline:none;}
.memo-area:focus{border-color:#3b4f8a;}

/* 人物詳細 */
.posineg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.pos-box{background:#f0fdf4;border:1.5px solid #86efac;border-radius:6px;padding:10px;}
.pos-box h4{color:#15803d;font-size:.78rem;font-weight:800;margin-bottom:6px;}
.neg-box{background:#fff7f7;border:1.5px solid #fca5a5;border-radius:6px;padding:10px;}
.neg-box h4{color:#dc2626;font-size:.78rem;font-weight:800;margin-bottom:6px;}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:500;align-items:center;justify-content:center;}
.modal-overlay.show{display:flex;}
.modal{background:#f0f2f8;border:2px solid #aab0cc;border-radius:8px;padding:0;max-width:520px;width:92%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.4);}
.modal-header{background:linear-gradient(180deg,#3b4f8a 0%,#263570 100%);padding:10px 16px;border-radius:6px 6px 0 0;}
.modal-header h3{color:#fff;font-size:.92rem;font-weight:800;}
.modal-body{padding:16px;}
.modal-form-row{margin-bottom:10px;}
.modal-form-row label{display:block;font-size:.7rem;font-weight:700;color:#5a6080;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px;}
.modal-form-row input,.modal-form-row select,.modal-form-row textarea{width:100%;padding:7px 10px;border:1px solid #aab0cc;border-radius:4px;font-size:.88rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;}
.modal-form-row input:focus,.modal-form-row select:focus,.modal-form-row textarea:focus{border-color:#3b4f8a;}
.modal-form-row textarea{resize:vertical;min-height:72px;}
.modal-2col{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;padding:12px 16px;border-top:1px solid #d0d4e0;}
.modal-cancel{padding:7px 16px;border:1px solid #aab0cc;border-radius:5px;background:#fff;cursor:pointer;font-size:.82rem;font-family:inherit;color:#3b4f8a;}
.modal-save{padding:7px 18px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:5px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;}
.modal-save:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}

/* ── タッチスクロール ── */
.fm-navbar,.fm-tabs,.fm-table-wrap,.fm-panel-wrap{-webkit-overflow-scrolling:touch;}
.fm-navbar::-webkit-scrollbar,.fm-tabs::-webkit-scrollbar{height:3px;}
.fm-navbar::-webkit-scrollbar-thumb,.fm-tabs::-webkit-scrollbar-thumb{background:rgba(255,255,255,.3);border-radius:2px;}

/* ── iPad（〜1024px） ── */
@media(max-width:1024px){
  .fm-topbar-student{max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
}

/* ── iPad縦 / 大型スマホ（〜768px） ── */
@media(max-width:768px){
  .fm-navbar{overflow-x:auto;flex-wrap:nowrap;scrollbar-width:thin;}
  .fm-navbtn{font-size:.74rem;padding:5px 10px;}
  .map-layout{grid-template-columns:1fr;}
  .map-frame-wrap{min-height:280px;}
  .map-frame-wrap iframe{min-height:280px;}
  .fm-info-grid{grid-template-columns:1fr 1fr;}
  .modal-2col{grid-template-columns:1fr 1fr;}
  .posineg-grid{grid-template-columns:1fr 1fr;}
}

/* ── iPhone / 小型スマホ（〜480px） ── */
@media(max-width:480px){
  body{font-size:12px;}
  .fm-topbar{padding:4px 8px;gap:4px;}
  .fm-topbar-title{font-size:1rem;}
  .fm-topbar-student{display:none;}
  .fm-btn-top{font-size:.7rem;padding:4px 8px;}
  .fm-arrow{width:32px;height:32px;font-size:1.1rem;}
  .fm-navbar{padding:4px 6px;gap:3px;}
  .fm-navbtn{font-size:.72rem;padding:5px 8px;}
  .fm-student-header{padding:8px;}
  .fm-field{min-width:90px;}
  .fm-field-label{font-size:.64rem;}
  .fm-field-value{font-size:.78rem;}
  .fm-photo{width:60px;height:72px;}
  .fm-tabs{padding:5px 6px 0;gap:1px;}
  .fm-tab{padding:6px 8px;font-size:.73rem;}
  .fm-panel{padding:10px;}
  .fm-panel-toolbar{gap:6px;}
  .fm-add-btn{font-size:.74rem;padding:5px 10px;}
  .fm-table th,.fm-table td{padding:6px 7px;font-size:.76rem;}
  .fm-info-grid,.modal-2col,.posineg-grid{grid-template-columns:1fr;}
  .fm-info-group.full,.modal-2col>*{grid-column:1;}
  .fm-header-row1{flex-direction:column;}
  .modal{width:96%;}
  .modal-body{padding:12px;}
  .modal-2col{gap:8px;}
}
</style>
</head>
<body>

<!-- ── トップバー ── -->
<div class="fm-topbar">
  <div style="display:flex;align-items:center;gap:10px;">
    <div class="fm-topbar-title"><span class="dot"></span>生徒情報</div>
    <div class="fm-nav-arrows">
      <?php if($prevId): ?>
        <a href="/karte/karte_detail.php?id=<?= urlencode($prevId) ?>" class="fm-arrow" title="前の生徒">◀</a>
      <?php else: ?>
        <span class="fm-arrow disabled">◀</span>
      <?php endif; ?>
      <a href="/karte/home.php" class="fm-arrow" title="一覧へ">⌂</a>
      <?php if($nextId): ?>
        <a href="/karte/karte_detail.php?id=<?= urlencode($nextId) ?>" class="fm-arrow" title="次の生徒">▶</a>
      <?php else: ?>
        <span class="fm-arrow disabled">▶</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="fm-topbar-student">
    <?= $dispNendo ? htmlspecialchars($dispNendo).'年度 ' : '' ?>
    <?= $dispGakunen ? htmlspecialchars($dispGakunen).'年 ' : '' ?>
    <?= $dispClass ? htmlspecialchars($dispClass).' ' : '' ?>
    <?= $dispBango ? htmlspecialchars($dispBango).'番 ' : '' ?>
    <strong><?= htmlspecialchars($dispName) ?></strong>
  </div>
  <div class="fm-topbar-right">
    <a href="/karte/karte_card.php?id=<?= urlencode($sid) ?>" target="_blank" class="fm-btn-top">🖨 個人カード</a>
    <a href="/karte/gakuseki.php" class="fm-btn-top">📚 学籍管理</a>
    <a href="/karte/home.php" class="fm-btn-top">← 一覧</a>
    <a href="/karte/logout.php" class="fm-btn-top">ログアウト</a>
  </div>
</div>

<!-- ── ナビゲーションボタン行 ── -->
<div class="fm-navbar">
  <button class="fm-navbtn active" onclick="switchNav(this,'panel-records')">📝 指導記録</button>
  <button class="fm-navbtn" onclick="switchNav(this,'panel-att')">📅 出欠・勤怠</button>
  <button class="fm-navbtn" onclick="switchNav(this,'panel-interview')">💬 面談記録</button>
  <button class="fm-navbtn" onclick="switchNav(this,'panel-memo')">📋 メモ・所見</button>
  <button class="fm-navbtn" onclick="switchNav(this,'panel-family')">🏠 家庭環境</button>
  <button class="fm-navbtn" onclick="switchNav(this,'panel-basic')">👤 基本情報</button>
  <button class="fm-navbtn" onclick="switchNav(this,'panel-map')">🗺 地図</button>
</div>

<!-- ── 生徒情報ヘッダー ── -->
<div class="fm-student-header">
  <div class="fm-header-row1">
    <div class="fm-header-fields" style="flex:1">
      <!-- 行1: 年度・学年・組・番号・学籍状態 -->
      <div class="fm-field-row">
        <div class="fm-field" style="max-width:90px">
          <div class="fm-field-label">年度</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispNendo ?: '—') ?></div>
        </div>
        <div class="fm-field" style="max-width:70px">
          <div class="fm-field-label">学年</div>
          <div class="fm-field-value"><?= $dispGakunen ? htmlspecialchars($dispGakunen).'年' : '—' ?></div>
        </div>
        <div class="fm-field" style="max-width:70px">
          <div class="fm-field-label">組</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispClass ?: '—') ?></div>
        </div>
        <div class="fm-field" style="max-width:60px">
          <div class="fm-field-label">番号</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispBango ?: '—') ?></div>
        </div>
        <div class="fm-field" style="max-width:80px">
          <div class="fm-field-label">学籍状態</div>
          <div class="fm-field-value" style="<?= $dispStatus==='退学'?'color:#dc2626':($dispStatus==='卒業'?'color:#1d4ed8':'') ?>"><?= htmlspecialchars($dispStatus ?: '—') ?></div>
        </div>
        <div class="fm-field">
          <div class="fm-field-label">担任</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispTannin ?: '—') ?></div>
        </div>
      </div>
      <!-- 行2: ふりがな・氏名・電話 -->
      <div class="fm-field-row">
        <div class="fm-field" style="max-width:200px">
          <div class="fm-field-label">ふりがな</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispFuri ?: '—') ?></div>
        </div>
        <div class="fm-field" style="max-width:180px">
          <div class="fm-field-label">氏名</div>
          <div class="fm-field-value" style="font-size:1rem;font-weight:900;color:#1a2240"><?= htmlspecialchars($dispName ?: '—') ?></div>
        </div>
        <div class="fm-field" style="max-width:160px">
          <div class="fm-field-label">電話（保護者）</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispTel ?: '—') ?></div>
        </div>
        <div class="fm-field">
          <div class="fm-field-label">保護者名</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispHogosya ?: '—') ?></div>
        </div>
      </div>
      <!-- 行3: 生年月日・性別・住所 -->
      <div class="fm-field-row">
        <div class="fm-field" style="max-width:120px">
          <div class="fm-field-label">生年月日</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispBday ?: '—') ?></div>
        </div>
        <div class="fm-field" style="max-width:60px">
          <div class="fm-field-label">性別</div>
          <div class="fm-field-value"><?= htmlspecialchars($dispSeibetu ?: '—') ?></div>
        </div>
        <div class="fm-field">
          <div class="fm-field-label">住所</div>
          <div class="fm-field-value wide"><?= htmlspecialchars($dispJyusyo ?: '—') ?></div>
        </div>
      </div>
    </div>
    <!-- 写真欄 -->
    <div class="photo-wrap">
      <div class="fm-photo" id="photoBox" onclick="document.getElementById('photoInput').click()" title="クリックして写真をアップロード" style="cursor:pointer;">
        <?php if ($dispPhoto): ?>
          <img id="photoImg" src="<?= htmlspecialchars($dispPhoto) ?>" alt="生徒写真">
        <?php else: ?>
          <span id="photoPlaceholder" style="font-size:.65rem;color:#9aa0c0;text-align:center;line-height:1.5;">📷<br>写真<br>タップ</span>
        <?php endif; ?>
        <input type="file" id="photoInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="uploadPhoto(this)">
      </div>
      <button class="photo-del-btn" id="photoDelBtn" onclick="deletePhoto(event)" title="写真を削除" <?= $dispPhoto ? '' : 'style="display:none"' ?>>×</button>
    </div>
  </div>
</div>

<!-- ── タブ ── -->
<div class="fm-tabs" id="fmTabs">
  <button class="fm-tab active" data-panel="panel-records">📝 指導記録</button>
  <button class="fm-tab" data-panel="panel-att">📅 出欠・勤怠</button>
  <button class="fm-tab" data-panel="panel-interview">💬 面談記録</button>
  <button class="fm-tab" data-panel="panel-memo">📋 メモ・所見</button>
  <button class="fm-tab" data-panel="panel-family">🏠 家庭環境</button>
  <button class="fm-tab" data-panel="panel-basic">👤 基本情報</button>
  <button class="fm-tab" data-panel="panel-map">🗺 地図</button>
</div>

<!-- ── パネルラッパー ── -->
<div class="fm-panel-wrap">

  <!-- 指導記録 -->
  <div class="fm-panel active" id="panel-records">
    <div class="fm-panel-toolbar">
      <span class="fm-panel-title">担任メモ・指導記録</span>
      <button class="fm-add-btn" onclick="openRecordModal()">＋ 記録を追加</button>
    </div>
    <div class="fm-table-wrap">
      <table class="fm-table">
        <thead><tr><th>日付</th><th>種類</th><th>内容</th><th>対応者</th><th>次回対応</th><th></th></tr></thead>
        <tbody id="tbody-records"></tbody>
      </table>
    </div>
    <div class="empty-msg" id="empty-records" style="display:none">指導記録がありません。「記録を追加」から登録してください。</div>
  </div>

  <!-- 出欠・勤怠 -->
  <div class="fm-panel" id="panel-att">
    <div class="fm-panel-toolbar">
      <span class="fm-panel-title">出欠・遅刻・早退記録</span>
      <button class="fm-add-btn" onclick="openAttModal()">＋ 記録を追加</button>
    </div>
    <div class="fm-table-wrap">
      <table class="fm-table">
        <thead><tr><th>日付</th><th>区分</th><th>理由</th><th>保護者連絡</th><th>備考</th><th></th></tr></thead>
        <tbody id="tbody-att"></tbody>
      </table>
    </div>
    <div class="empty-msg" id="empty-att" style="display:none">出欠記録がありません。</div>
  </div>

  <!-- 面談記録 -->
  <div class="fm-panel" id="panel-interview">
    <div class="fm-panel-toolbar">
      <span class="fm-panel-title">面談記録</span>
      <button class="fm-add-btn" onclick="openIntModal()">＋ 面談を追加</button>
    </div>
    <div class="fm-table-wrap">
      <table class="fm-table">
        <thead><tr><th>日付</th><th>種別</th><th>参加者</th><th>内容</th><th>今後の対応</th><th></th></tr></thead>
        <tbody id="tbody-int"></tbody>
      </table>
    </div>
    <div class="empty-msg" id="empty-int" style="display:none">面談記録がありません。</div>
  </div>

  <!-- メモ・所見 -->
  <div class="fm-panel" id="panel-memo">
    <div class="fm-panel-toolbar">
      <span class="fm-panel-title">メモ・所見（自由記述）</span>
      <button class="fm-add-btn" id="btnSaveMemo">保存する</button>
    </div>
    <div class="posineg-grid" style="margin-bottom:14px;">
      <div class="pos-box">
        <h4>ポジ所見（良い面・強み）</h4>
        <textarea class="memo-area" id="memo-posi" rows="5" placeholder="生徒の良い面、強み、頑張っていること…"></textarea>
      </div>
      <div class="neg-box">
        <h4>ネガ所見（課題・注意点）</h4>
        <textarea class="memo-area" id="memo-nega" rows="5" placeholder="課題点、注意が必要なこと、支援が必要な事項…"></textarea>
      </div>
    </div>
    <div style="font-size:.78rem;font-weight:700;color:#3b4f8a;margin-bottom:6px;">担任メモ（その他）</div>
    <textarea class="memo-area" id="memo-main" rows="5" placeholder="自由メモ・家庭状況・その他の所見など…"></textarea>
    <div id="memo-save-ok" class="save-ok" style="margin-top:8px;">✓ 保存しました</div>
  </div>

  <!-- 家庭環境 -->
  <div class="fm-panel" id="panel-family">
    <?php if($gak): ?>
    <div class="fm-info-section">保護者・家庭情報（学籍台帳より）</div>
    <div class="fm-info-grid">
      <div class="fm-info-group"><label>保護者名</label><input class="fm-info-input" value="<?= htmlspecialchars($gak['hogosya']??'') ?>" readonly></div>
      <div class="fm-info-group"><label>ふりがな</label><input class="fm-info-input" value="<?= htmlspecialchars($gak['hogokana']??'') ?>" readonly></div>
      <div class="fm-info-group"><label>続柄</label><input class="fm-info-input" value="<?= htmlspecialchars($gak['zokugara']??'') ?>" readonly></div>
      <div class="fm-info-group"><label>電話1</label><input class="fm-info-input" value="<?= htmlspecialchars($gak['tel1']??'') ?>" readonly></div>
      <div class="fm-info-group"><label>電話2</label><input class="fm-info-input" value="<?= htmlspecialchars($gak['tel2']??'') ?>" readonly></div>
      <div class="fm-info-group full"><label>住所</label><input class="fm-info-input" value="<?= htmlspecialchars(($gak['yuubin']?'〒'.$gak['yuubin'].' ':'').$gak['jyusyo']) ?>" readonly></div>
    </div>
    <div style="margin-top:10px;font-size:.72rem;color:#5a6080;background:#e8ecff;padding:7px 10px;border-radius:4px;border:1px solid #aab0cc;">
      ※ 学籍台帳にリンクされています。変更は <a href="/karte/gakuseki.php" style="color:#3b4f8a">学籍管理</a> から行ってください。
    </div>
    <?php else: ?>
    <div style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:14px;font-size:.84rem;color:#713f12;margin-bottom:14px;">
      学籍台帳が未連携です。「基本情報」タブから学籍番号をリンクすると、保護者・家庭情報が自動表示されます。
    </div>
    <?php endif; ?>
    <div class="fm-info-section">家庭状況メモ（担任記入）</div>
    <textarea class="fm-info-textarea" id="family-notes" rows="6" placeholder="家庭状況・保護者との関係・支援状況など"><?= htmlspecialchars($s['notes']??'') ?></textarea>
    <div class="fm-save-row">
      <button class="fm-save-btn" id="btnSaveFamily">保存する</button>
      <span class="save-ok" id="family-save-ok">✓ 保存しました</span>
    </div>
  </div>

  <!-- 基本情報 -->
  <div class="fm-panel" id="panel-basic">
    <!-- 学籍リンク -->
    <?php if($gak): ?>
    <div class="gak-ref-box">
      <h4>📚 学籍台帳リンク済み — 学籍番号: <span style="color:#3b4f8a"><?= htmlspecialchars($gakno) ?></span>
        <?php if($latestNendo): ?>
          <span style="font-size:.75rem;color:#6d8fd0;font-weight:400">
            （最新: <?= htmlspecialchars($latestNendo['nendo']) ?>年度 <?= $latestNendo['gakunen'] ?>年<?= htmlspecialchars($latestNendo['class_no']??'') ?><?= $latestNendo['bango'] ? ' '.$latestNendo['bango'].'番' : '' ?>）
          </span>
        <?php endif; ?>
      </h4>
      <?php if($nendo_list): ?>
      <div style="margin-bottom:10px;">
        <div style="font-size:.72rem;font-weight:700;color:#3b4f8a;margin-bottom:5px;">年度別クラス情報</div>
        <table class="nendo-table">
          <thead><tr><th>年度</th><th>学年</th><th>組</th><th>番号</th><th>担任</th><th>進級状態</th></tr></thead>
          <tbody>
            <?php foreach($nendo_list as $n): ?>
            <tr>
              <td style="font-weight:700;color:#3b4f8a"><?= htmlspecialchars($n['nendo']) ?>年度</td>
              <td><?= $n['gakunen'] ? htmlspecialchars($n['gakunen']).'年' : '—' ?></td>
              <td><?= htmlspecialchars($n['class_no']??'—') ?></td>
              <td><?= $n['bango'] ? htmlspecialchars($n['bango']).'番' : '—' ?></td>
              <td><?= htmlspecialchars($n['tanninmei']??'—') ?></td>
              <td><?= htmlspecialchars($n['sinkyu']??'—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;">
        <span style="font-size:.75rem;color:#5a6080">学籍番号を変更:</span>
        <input type="text" class="gak-link-input" id="gaknoInput" value="<?= htmlspecialchars($gakno) ?>" placeholder="学籍番号">
        <button class="fm-save-btn" style="padding:5px 12px;font-size:.78rem;" id="btnLinkGakno">変更</button>
        <button class="fm-save-btn" style="padding:5px 12px;font-size:.78rem;background:linear-gradient(180deg,#aab0cc 0%,#8890b0 100%);border-color:#6a7090;" id="btnUnlinkGakno">リンク解除</button>
      </div>
    </div>
    <?php else: ?>
    <div class="gak-link-box">
      <strong>学籍台帳と未連携</strong> — 学籍番号を入力してリンクするか、<a href="/karte/gakuseki.php" style="color:#92400e">学籍管理</a>で登録してください。
      <div class="gak-link-form">
        <input type="text" class="gak-link-input" id="gaknoInput2" placeholder="学籍番号を入力">
        <button class="fm-save-btn" style="padding:5px 12px;font-size:.78rem;" id="btnLinkGakno2">リンク</button>
      </div>
    </div>
    <?php endif; ?>

    <div class="fm-info-section">カルテ内情報（編集可）</div>
    <div class="fm-info-grid">
      <div class="fm-info-group"><label>学籍番号（内部ID）</label>
        <input class="fm-info-input" id="b-sid" value="<?= htmlspecialchars($s['student_id']) ?>" readonly></div>
      <div class="fm-info-group"><label>クラス（手動）</label>
        <input class="fm-info-input" id="b-class" value="<?= htmlspecialchars($s['class_name']??'') ?>"></div>
      <div class="fm-info-group"><label>氏名（手動）</label>
        <input class="fm-info-input" id="b-name" value="<?= htmlspecialchars($s['name']??'') ?>"></div>
      <div class="fm-info-group"><label>ふりがな（手動）</label>
        <input class="fm-info-input" id="b-furi" value="<?= htmlspecialchars($s['furigana']??'') ?>"></div>
      <div class="fm-info-group"><label>出席番号</label>
        <input class="fm-info-input" id="b-seat" type="number" value="<?= htmlspecialchars($s['seat_number']??'') ?>"></div>
      <div class="fm-info-group"><label>性別</label>
        <input class="fm-info-input" id="b-gender" value="<?= htmlspecialchars($s['gender']??'') ?>" placeholder="男・女・その他"></div>
      <div class="fm-info-group"><label>生年月日</label>
        <input class="fm-info-input" id="b-bday" type="date" value="<?= htmlspecialchars($s['birthday']??'') ?>"></div>
      <div class="fm-info-group"><label>電話（手動）</label>
        <input class="fm-info-input" id="b-phone" value="<?= htmlspecialchars($s['phone']??'') ?>"></div>
      <div class="fm-info-group"><label>保護者名（手動）</label>
        <input class="fm-info-input" id="b-parent" value="<?= htmlspecialchars($s['parent_name']??'') ?>"></div>
      <div class="fm-info-group full"><label>住所（手動）</label>
        <input class="fm-info-input" id="b-addr" value="<?= htmlspecialchars($s['address']??'') ?>"></div>
      <div class="fm-info-group full"><label>備考</label>
        <textarea class="fm-info-textarea" id="b-notes"><?= htmlspecialchars($s['notes']??'') ?></textarea></div>
    </div>
    <div class="fm-save-row">
      <button class="fm-save-btn" id="btnSaveBasic">保存する</button>
      <span class="save-ok" id="saveOk">✓ 保存しました</span>
    </div>
  </div>

  <!-- 地図 -->
  <div class="fm-panel" id="panel-map">
    <div class="fm-panel-toolbar">
      <span class="fm-panel-title">自宅地図</span>
    </div>
    <div class="map-layout">
      <!-- サイドバー -->
      <div class="map-sidebar">
        <div class="map-addr-card">
          <h4>📍 登録住所</h4>
          <?php
            $mapAddr = '';
            if ($gak) {
                $mapAddr = trim(($gak['yuubin'] ? '〒'.$gak['yuubin'].' ' : '').$gak['jyusyo']);
            } elseif ($s['address']) {
                $mapAddr = $s['address'];
            }
          ?>
          <?php if($mapAddr): ?>
            <?php if($gak && $gak['yuubin']): ?>
            <div style="margin-bottom:6px;">
              <div class="map-addr-label">郵便番号</div>
              <div class="map-addr-line">〒<?= htmlspecialchars($gak['yuubin']) ?></div>
            </div>
            <?php endif; ?>
            <div style="margin-bottom:10px;">
              <div class="map-addr-label">住所</div>
              <div class="map-addr-line" id="displayAddr"><?= htmlspecialchars($gak ? $gak['jyusyo'] : $s['address']) ?></div>
            </div>
            <a href="https://www.google.com/maps/search/<?= urlencode($mapAddr) ?>" target="_blank" class="map-btn map-btn-google" style="margin-bottom:6px;">
              🔗 Google マップで開く
            </a>
            <button class="map-btn map-btn-primary" id="btnShowMap" onclick="showMap(document.getElementById('displayAddr').textContent)">
              🗺 地図を表示
            </button>
          <?php else: ?>
            <div class="map-no-addr">
              <div style="font-size:1.5rem">📭</div>
              <div>住所が登録されていません</div>
              <div style="font-size:.76rem;color:#aab0cc">基本情報タブまたは学籍管理から住所を登録してください</div>
            </div>
          <?php endif; ?>
        </div>

        <!-- 住所検索 -->
        <div class="map-addr-card">
          <h4>🔍 住所を検索</h4>
          <div style="font-size:.76rem;color:#5a6080;margin-bottom:6px;">別の住所を地図に表示する場合</div>
          <textarea class="map-search-input" id="mapSearchInput" rows="3" placeholder="住所を入力…&#10;例: 沖縄県浦添市沢岻1-9-27" style="width:100%;resize:vertical;"><?= htmlspecialchars($gak ? $gak['jyusyo'] : $s['address'] ?? '') ?></textarea>
          <button class="map-btn map-btn-search" style="margin-top:6px;" onclick="showMap(document.getElementById('mapSearchInput').value)">この住所を表示</button>
        </div>

        <!-- 生徒情報サマリー -->
        <div class="map-addr-card">
          <h4>👤 生徒情報</h4>
          <div style="font-size:.82rem;line-height:1.8;color:#1a2240;">
            <div><strong><?= htmlspecialchars($dispName) ?></strong></div>
            <?php if($dispFuri): ?><div style="color:#5a6080;font-size:.76rem"><?= htmlspecialchars($dispFuri) ?></div><?php endif; ?>
            <?php if($dispGakunen): ?><div><?= htmlspecialchars($dispGakunen) ?>年<?= htmlspecialchars($dispClass) ?> <?= htmlspecialchars($dispBango) ?>番</div><?php endif; ?>
            <?php if($dispTel): ?><div>📞 <?= htmlspecialchars($dispTel) ?></div><?php endif; ?>
            <?php if($dispHogosya): ?><div>👨‍👩‍👦 <?= htmlspecialchars($dispHogosya) ?><?= $gak && $gak['zokugara'] ? '（'.$gak['zokugara'].'）' : '' ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- 地図フレーム -->
      <div class="map-frame-wrap" id="mapFrameWrap">
        <?php if($mapAddr): ?>
        <div class="map-loading" id="mapLoading">
          <div style="font-size:2rem">🗺</div>
          <div>「地図を表示」をクリックしてください</div>
        </div>
        <iframe id="mapFrame" src="" style="display:none;width:100%;height:100%;min-height:480px;border:none;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <?php else: ?>
        <div class="map-no-addr" style="height:480px;">
          <div style="font-size:3rem">🗺</div>
          <div style="font-size:1rem;font-weight:700;color:#5a6080">住所が未登録です</div>
          <div style="font-size:.82rem">基本情報または学籍管理から住所を登録してください</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /fm-panel-wrap -->

<!-- 指導記録モーダル -->
<div class="modal-overlay" id="recModal">
  <div class="modal">
    <div class="modal-header"><h3 id="recModalTitle">指導記録を追加</h3></div>
    <div class="modal-body">
      <input type="hidden" id="rec-id">
      <div class="modal-2col">
        <div class="modal-form-row"><label>日付</label><input type="date" id="rec-date"></div>
        <div class="modal-form-row"><label>種類</label><select id="rec-type">
          <?php foreach($RECORD_TYPES as $t) echo "<option>$t</option>"; ?>
        </select></div>
      </div>
      <div class="modal-form-row"><label>内容</label><textarea id="rec-content" rows="4" placeholder="記録内容…"></textarea></div>
      <div class="modal-2col">
        <div class="modal-form-row"><label>対応者</label><input type="text" id="rec-teacher" value="<?= htmlspecialchars($_SESSION['teacher_name']) ?>"></div>
        <div class="modal-form-row"><label>次回対応</label><input type="text" id="rec-next" placeholder="例: 6月末に再確認"></div>
      </div>
    </div>
    <div class="modal-btns">
      <button class="modal-cancel" onclick="closeModal('recModal')">キャンセル</button>
      <button class="modal-save" id="btnSaveRec">保存する</button>
    </div>
  </div>
</div>

<!-- 出欠記録モーダル -->
<div class="modal-overlay" id="attModal">
  <div class="modal">
    <div class="modal-header"><h3>出欠・遅刻・早退を追加</h3></div>
    <div class="modal-body">
      <div class="modal-2col">
        <div class="modal-form-row"><label>日付</label><input type="date" id="att-date"></div>
        <div class="modal-form-row"><label>区分</label><select id="att-type">
          <?php foreach($ATT_TYPES as $t) echo "<option>$t</option>"; ?>
        </select></div>
      </div>
      <div class="modal-form-row"><label>理由</label><input type="text" id="att-reason" placeholder="例: 体調不良"></div>
      <div class="modal-2col">
        <div class="modal-form-row"><label>保護者連絡</label><select id="att-contact">
          <option>未</option><option>済</option><option>不要</option>
        </select></div>
        <div class="modal-form-row"><label>備考</label><input type="text" id="att-notes" placeholder="例: 母より連絡"></div>
      </div>
    </div>
    <div class="modal-btns">
      <button class="modal-cancel" onclick="closeModal('attModal')">キャンセル</button>
      <button class="modal-save" id="btnSaveAtt">保存する</button>
    </div>
  </div>
</div>

<!-- 面談記録モーダル -->
<div class="modal-overlay" id="intModal">
  <div class="modal">
    <div class="modal-header"><h3>面談記録を追加</h3></div>
    <div class="modal-body">
      <div class="modal-2col">
        <div class="modal-form-row"><label>日付</label><input type="date" id="int-date"></div>
        <div class="modal-form-row"><label>面談種別</label><select id="int-type">
          <?php foreach($INT_TYPES as $t) echo "<option>$t</option>"; ?>
        </select></div>
      </div>
      <div class="modal-form-row"><label>参加者</label><input type="text" id="int-parti" placeholder="例: 本人・母"></div>
      <div class="modal-form-row"><label>内容</label><textarea id="int-content" rows="4" placeholder="面談内容…"></textarea></div>
      <div class="modal-form-row"><label>今後の対応</label><input type="text" id="int-next" placeholder="例: 夏休みに再確認"></div>
    </div>
    <div class="modal-btns">
      <button class="modal-cancel" onclick="closeModal('intModal')">キャンセル</button>
      <button class="modal-save" id="btnSaveInt">保存する</button>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= generateCsrfToken() ?>';
const SID  = '<?= htmlspecialchars($sid) ?>';
const today = new Date().toISOString().split('T')[0];

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');}
function esc2(s){return String(s||'').replace(/'/g,"\\'");}
function esc3(s){return String(s||'').replace(/`/g,'\\`').replace(/\$/g,'\\$');}

// タブ切り替え
document.querySelectorAll('.fm-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.fm-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.fm-panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    const panel = document.getElementById(tab.dataset.panel);
    if (panel) panel.classList.add('active');
    if (tab.dataset.panel === 'panel-records') loadRecords();
    else if (tab.dataset.panel === 'panel-att') loadAtt();
    else if (tab.dataset.panel === 'panel-interview') loadInt();
  });
});

// ナビボタンからタブ切り替え
function switchNav(btn, panelId) {
  document.querySelectorAll('.fm-navbtn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const tab = document.querySelector(`.fm-tab[data-panel="${panelId}"]`);
  if (tab) tab.click();
}

function closeModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('show'); }));

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
      <td class="date-cell">${esc(r.record_date)}</td>
      <td><span class="type-badge type-${esc(r.record_type)}">${esc(r.record_type)}</span></td>
      <td class="content-cell">${esc(r.content)}</td>
      <td style="white-space:nowrap">${esc(r.teacher)}</td>
      <td class="content-cell">${esc(r.next_action)}</td>
      <td style="white-space:nowrap">
        <button class="btn-sm btn-edit-sm" onclick="openRecordModal(${r.id},'${esc2(r.record_date)}','${esc2(r.record_type)}',\`${esc3(r.content)}\`,'${esc2(r.teacher)}',\`${esc3(r.next_action)}\`)">編集</button>
        <button class="btn-sm btn-del-sm" onclick="delRecord(${r.id})">削除</button>
      </td>
    </tr>
  `).join('');
}

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
  if (isEdit) fd.append('id', editingRecId); else fd.append('student_id', SID);
  fd.append('record_date', document.getElementById('rec-date').value);
  fd.append('record_type', document.getElementById('rec-type').value);
  fd.append('content', content);
  fd.append('teacher', document.getElementById('rec-teacher').value);
  fd.append('next_action', document.getElementById('rec-next').value);
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
  fd.append('att_date', document.getElementById('att-date').value);
  fd.append('att_type', document.getElementById('att-type').value);
  fd.append('reason',   document.getElementById('att-reason').value);
  fd.append('parent_contacted', document.getElementById('att-contact').value);
  fd.append('notes',    document.getElementById('att-notes').value);
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
  tbody.innerHTML = data.rows.map(r => `
    <tr>
      <td class="date-cell">${esc(r.att_date)}</td>
      <td><span class="type-badge type-${esc(r.att_type)}">${esc(r.att_type)}</span></td>
      <td>${esc(r.reason)}</td>
      <td><span class="contact-badge contact-${esc(r.parent_contacted)}">${esc(r.parent_contacted)}</span></td>
      <td>${esc(r.notes)}</td>
      <td><button class="btn-sm btn-del-sm" onclick="delAtt(${r.id})">削除</button></td>
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
  fd.append('interview_date', document.getElementById('int-date').value);
  fd.append('interview_type', document.getElementById('int-type').value);
  fd.append('participants',   document.getElementById('int-parti').value);
  fd.append('content',        content);
  fd.append('next_action',    document.getElementById('int-next').value);
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
  tbody.innerHTML = data.rows.map(r => `
    <tr>
      <td class="date-cell">${esc(r.interview_date)}</td>
      <td><span class="type-badge type-${esc(r.interview_type)}">${esc(r.interview_type)}</span></td>
      <td>${esc(r.participants)}</td>
      <td class="content-cell">${esc(r.content)}</td>
      <td class="content-cell">${esc(r.next_action)}</td>
      <td><button class="btn-sm btn-del-sm" onclick="delInt(${r.id})">削除</button></td>
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

/* ── メモ・所見 ── */
(async () => {
  const res = await fetch(`/karte/api/karte.php?action=get_memos&student_id=${SID}`);
  const data = await res.json();
  if (data.success) {
    document.getElementById('memo-posi').value = data.posi || '';
    document.getElementById('memo-nega').value = data.nega || '';
    document.getElementById('memo-main').value = data.main || '';
  }
})();

document.getElementById('btnSaveMemo').onclick = async () => {
  const fd = new FormData();
  fd.append('action','save_memos'); fd.append('csrf_token',CSRF); fd.append('student_id',SID);
  fd.append('posi', document.getElementById('memo-posi').value);
  fd.append('nega', document.getElementById('memo-nega').value);
  fd.append('main', document.getElementById('memo-main').value);
  const res = await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) {
    const ok = document.getElementById('memo-save-ok');
    ok.style.display='block'; setTimeout(()=>ok.style.display='none',2500);
  } else alert(data.error||'エラー');
};

/* ── 家庭環境メモ保存 ── */
document.getElementById('btnSaveFamily').onclick = async () => {
  const fd = new FormData();
  fd.append('action','save_basic'); fd.append('csrf_token',CSRF); fd.append('student_id',SID);
  ['name','furigana','class_name','gender','phone','parent_name','address'].forEach(f => fd.append(f,''));
  fd.append('notes', document.getElementById('family-notes').value);
  const res = await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) {
    const ok = document.getElementById('family-save-ok');
    ok.style.display='inline'; setTimeout(()=>ok.style.display='none',2500);
  }
};

/* ── 基本情報保存 ── */
document.getElementById('btnSaveBasic').onclick = async () => {
  const fd = new FormData();
  fd.append('action','save_basic'); fd.append('csrf_token',CSRF); fd.append('student_id',SID);
  fd.append('name',        document.getElementById('b-name').value);
  fd.append('furigana',    document.getElementById('b-furi').value);
  fd.append('class_name',  document.getElementById('b-class').value);
  fd.append('seat_number', document.getElementById('b-seat').value);
  fd.append('gender',      document.getElementById('b-gender').value);
  fd.append('birthday',    document.getElementById('b-bday').value);
  fd.append('phone',       document.getElementById('b-phone').value);
  fd.append('parent_name', document.getElementById('b-parent').value);
  fd.append('address',     document.getElementById('b-addr').value);
  fd.append('notes',       document.getElementById('b-notes').value);
  const res = await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) {
    const ok = document.getElementById('saveOk');
    ok.style.display='inline'; setTimeout(()=>ok.style.display='none',2500);
  } else alert(data.error||'エラー');
};

/* ── 学籍リンク ── */
async function linkGakno(gakno) {
  if (!gakno) { alert('学籍番号を入力してください'); return; }
  const fd = new FormData();
  fd.append('action','save_gakno'); fd.append('csrf_token',CSRF);
  fd.append('student_id',SID); fd.append('gakno',gakno);
  const res = await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) location.reload();
  else alert(data.error||'エラー');
}
document.getElementById('btnLinkGakno')  && document.getElementById('btnLinkGakno').addEventListener('click', () => linkGakno(document.getElementById('gaknoInput').value.trim()));
document.getElementById('btnLinkGakno2') && document.getElementById('btnLinkGakno2').addEventListener('click', () => linkGakno(document.getElementById('gaknoInput2').value.trim()));
document.getElementById('btnUnlinkGakno') && document.getElementById('btnUnlinkGakno').addEventListener('click', async () => {
  if (!confirm('学籍台帳へのリンクを解除しますか？')) return;
  const fd = new FormData();
  fd.append('action','save_gakno'); fd.append('csrf_token',CSRF);
  fd.append('student_id',SID); fd.append('gakno','');
  await fetch('/karte/api/karte.php',{method:'POST',body:fd});
  location.reload();
});

/* ── 地図表示 ── */
function showMap(addr) {
  addr = (addr || '').trim();
  if (!addr) { alert('住所を入力してください'); return; }
  const frame  = document.getElementById('mapFrame');
  const loading = document.getElementById('mapLoading');
  if (!frame) return;
  // Google Maps Embed（APIキー不要の検索URL形式）
  const encoded = encodeURIComponent(addr);
  const src = `https://maps.google.com/maps?q=${encoded}&t=m&z=16&ie=UTF8&iwloc=&output=embed`;
  if (loading) loading.style.display = 'none';
  frame.style.display = 'block';
  frame.src = src;
  // 「Google マップで開く」リンクも更新
  document.querySelectorAll('.map-btn-google').forEach(a => {
    a.href = `https://www.google.com/maps/search/${encoded}`;
  });
}

// 地図タブを開いたとき自動で住所を表示
document.querySelector('.fm-tab[data-panel="panel-map"]').addEventListener('click', () => {
  const addr = <?= json_encode($mapAddr) ?>;
  if (addr) {
    setTimeout(() => showMap(addr), 200);
  }
});

// 初期ロード
loadRecords();

/* ── 生徒切替：マウスホイール ── */
(function(){
  const PREV = <?= $prevId ? "'".addslashes(urlencode($prevId))."'" : 'null' ?>;
  const NEXT = <?= $nextId ? "'".addslashes(urlencode($nextId))."'" : 'null' ?>;
  let busy = false;

  function go(id) {
    if (!id || busy) return;
    busy = true;
    location.href = '/karte/karte_detail.php?id=' + id;
  }

  // マウスホイール（デスクトップ・Chromebook）
  let wheelAcc = 0, wheelTimer = null;
  document.addEventListener('wheel', e => {
    // テキストエリア・スクロール可能要素の中はスキップ
    if (e.target.closest('textarea,select,.fm-table-wrap,.fm-tabs,iframe')) return;
    wheelAcc += e.deltaY;
    clearTimeout(wheelTimer);
    wheelTimer = setTimeout(() => {
      if (Math.abs(wheelAcc) < 60) { wheelAcc = 0; return; }
      go(wheelAcc > 0 ? NEXT : PREV);
      wheelAcc = 0;
    }, 120);
  }, { passive: true });

  // タッチスワイプ（iPhone・iPad）
  let tx = 0, ty = 0;
  document.addEventListener('touchstart', e => {
    tx = e.touches[0].clientX;
    ty = e.touches[0].clientY;
  }, { passive: true });
  document.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - tx;
    const dy = e.changedTouches[0].clientY - ty;
    // 横方向スワイプが縦より大きく、かつ50px以上
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50) {
      // 左スワイプ→次、右スワイプ→前
      go(dx < 0 ? NEXT : PREV);
    }
  }, { passive: true });
})();

/* ── 写真アップロード ── */
async function uploadPhoto(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 4 * 1024 * 1024) { alert('4MB以下のファイルを選択してください'); input.value=''; return; }

  const fd = new FormData();
  fd.append('action',     'upload');
  fd.append('csrf_token', CSRF);
  fd.append('photo',      file);
  <?php if ($gakno): ?>
  fd.append('gakno', '<?= htmlspecialchars($gakno) ?>');
  <?php else: ?>
  fd.append('student_id', '<?= htmlspecialchars($sid) ?>');
  <?php endif; ?>

  // プレビュー（即時表示）
  const reader = new FileReader();
  reader.onload = e => {
    let img = document.getElementById('photoImg');
    if (!img) {
      img = document.createElement('img');
      img.id = 'photoImg';
      document.getElementById('photoBox').innerHTML = '';
      document.getElementById('photoBox').appendChild(img);
      // input を再追加
      const inp = document.createElement('input');
      inp.type='file'; inp.id='photoInput'; inp.accept='image/jpeg,image/png,image/gif,image/webp';
      inp.style.display='none'; inp.onchange = function(){uploadPhoto(this);};
      document.getElementById('photoBox').appendChild(inp);
    }
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);

  const res  = await fetch('/karte/api/photo.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.success) {
    document.getElementById('photoDelBtn').style.display = 'flex';
    const ph = document.getElementById('photoPlaceholder');
    if (ph) ph.remove();
  } else {
    alert('アップロードエラー: ' + (data.error || '不明'));
  }
  input.value = '';
}

async function deletePhoto(e) {
  e.stopPropagation();
  if (!confirm('写真を削除しますか？')) return;
  const fd = new FormData();
  fd.append('action',     'delete');
  fd.append('csrf_token', CSRF);
  <?php if ($gakno): ?>
  fd.append('gakno', '<?= htmlspecialchars($gakno) ?>');
  <?php else: ?>
  fd.append('student_id', '<?= htmlspecialchars($sid) ?>');
  <?php endif; ?>
  const res  = await fetch('/karte/api/photo.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.success) {
    const box = document.getElementById('photoBox');
    box.innerHTML = '<span style="font-size:.65rem;color:#9aa0c0;text-align:center;line-height:1.5;">📷<br>写真<br>タップ</span>';
    const inp = document.createElement('input');
    inp.type='file'; inp.id='photoInput'; inp.accept='image/jpeg,image/png,image/gif,image/webp';
    inp.style.display='none'; inp.onchange = function(){uploadPhoto(this);};
    box.appendChild(inp);
    document.getElementById('photoDelBtn').style.display = 'none';
  } else {
    alert('削除エラー: ' + (data.error || '不明'));
  }
}
</script>
</body>
</html>
