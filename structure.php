<?php
require_once 'config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>システム構造図 - 生徒カルテ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo',sans-serif;background:#0d1117;color:#e2e8f0;min-height:100vh;font-size:13px;}

/* トップバー */
.topbar{background:linear-gradient(180deg,#2c3e6b,#1a2a55);color:#fff;padding:4px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:2px solid #0f1e40;min-height:44px;position:sticky;top:0;z-index:100;}
.topbar-title{font-size:1rem;font-weight:900;color:#e8ecff;display:flex;align-items:center;gap:8px;}
.topbar-title .dot{width:8px;height:8px;border-radius:50%;background:#6ee7b7;display:inline-block;}
.back-btn{padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#e8ecff;font-size:.8rem;text-decoration:none;display:inline-flex;align-items:center;gap:4px;}
.back-btn:hover{background:rgba(255,255,255,.25);}

/* タブ */
.tab-bar{background:#161b22;border-bottom:1px solid #30363d;display:flex;gap:0;overflow-x:auto;position:sticky;top:44px;z-index:90;}
.tab-btn{padding:10px 18px;font-size:.82rem;font-weight:700;color:#8b949e;background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;white-space:nowrap;font-family:inherit;transition:color .15s;}
.tab-btn.active{color:#58a6ff;border-bottom-color:#58a6ff;}
.tab-btn:hover{color:#c9d1d9;}
.tab-pane{display:none;padding:20px 16px 60px;max-width:1200px;margin:0 auto;}
.tab-pane.active{display:block;}

/* セクション見出し */
.sec{margin-bottom:28px;}
.sec-title{font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#58a6ff;margin-bottom:10px;padding-bottom:5px;border-bottom:1px solid #21262d;}

/* ファイルカード */
.file-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:10px;}
.file-card{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px 16px;}
.file-card:hover{border-color:#58a6ff;}
.file-header{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;}
.file-icon{font-size:1.1rem;flex-shrink:0;margin-top:1px;}
.file-name{font-family:'Courier New',monospace;font-weight:700;color:#e6edf3;font-size:.88rem;}
.file-badge{display:inline-block;border-radius:4px;padding:1px 7px;font-size:.68rem;font-weight:700;margin-left:6px;vertical-align:middle;}
.b-php{background:#1f3a7a;color:#93c5fd;}
.b-api{background:#0d3321;color:#6ee7b7;}
.b-cfg{background:#2d1a50;color:#d8b4fe;}
.b-dir{background:#0d2d3a;color:#67e8f9;}
.b-dep{background:#3a0d0d;color:#fca5a5;}
.b-ign{background:#1a2a1a;color:#86efac;}
.file-desc{font-size:.8rem;color:#8b949e;margin-bottom:8px;}
.file-detail{font-size:.75rem;color:#6e7681;line-height:1.6;}
.file-detail strong{color:#c9d1d9;}
.kv{display:grid;grid-template-columns:auto 1fr;gap:2px 8px;font-size:.75rem;}
.kv dt{color:#6e7681;white-space:nowrap;}
.kv dd{color:#c9d1d9;font-family:'Courier New',monospace;word-break:break-all;}

/* APIエンドポイントテーブル */
.api-table{width:100%;border-collapse:collapse;font-size:.78rem;margin-top:6px;}
.api-table th{background:#21262d;color:#8b949e;padding:5px 8px;text-align:left;font-size:.7rem;letter-spacing:.06em;border:1px solid #30363d;}
.api-table td{padding:5px 8px;border:1px solid #21262d;vertical-align:top;color:#c9d1d9;}
.api-table tr:nth-child(even) td{background:#0d1117;}
.method{display:inline-block;border-radius:3px;padding:1px 6px;font-size:.68rem;font-weight:700;font-family:monospace;}
.m-get{background:#0d3321;color:#6ee7b7;}
.m-post{background:#1f3a7a;color:#93c5fd;}
.action-name{font-family:'Courier New',monospace;color:#f0883e;}

/* DBテーブル */
.db-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;}
.db-card{background:#161b22;border:1px solid #30363d;border-radius:8px;overflow:hidden;}
.db-card-head{background:#21262d;padding:8px 12px;display:flex;align-items:center;gap:8px;border-bottom:1px solid #30363d;}
.db-card-name{font-family:'Courier New',monospace;font-weight:700;color:#f0883e;font-size:.88rem;}
.db-card-label{font-size:.72rem;color:#8b949e;}
.db-cols{padding:6px 0;}
.db-col{display:grid;grid-template-columns:auto 1fr auto;gap:4px 8px;padding:3px 12px;font-size:.73rem;border-bottom:1px solid #0d1117;}
.db-col:last-child{border-bottom:none;}
.db-col-name{font-family:'Courier New',monospace;color:#e6edf3;}
.db-col-type{color:#6e7681;font-size:.68rem;white-space:nowrap;}
.db-col-note{color:#8b949e;font-size:.68rem;}
.db-key{color:#f59e0b;font-size:.72rem;}
.db-fk{color:#c084fc;font-size:.68rem;}
.rel-note{font-size:.73rem;color:#8b949e;background:#161b22;border:1px solid #30363d;border-radius:6px;padding:8px 12px;margin-top:8px;}
.rel-note strong{color:#c9d1d9;}

/* フロー */
.flow-wrap{display:flex;flex-direction:column;gap:14px;}
.flow-item{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:12px 14px;}
.flow-title{font-size:.78rem;font-weight:700;color:#8b949e;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.flow-title span{font-size:.9rem;}
.flow-nodes{display:flex;align-items:stretch;gap:0;flex-wrap:wrap;}
.fn{background:#0d1117;border:1px solid #30363d;border-radius:6px;padding:8px 12px;font-size:.75rem;text-align:center;min-width:100px;flex-shrink:0;}
.fn-icon{font-size:1rem;display:block;margin-bottom:3px;}
.fn-label{font-weight:700;color:#c9d1d9;display:block;}
.fn-sub{font-size:.65rem;color:#6e7681;display:block;margin-top:2px;}
.fn-arrow{display:flex;align-items:center;padding:0 4px;color:#30363d;font-size:1.2rem;flex-shrink:0;}
.fn.hi{border-color:#58a6ff;background:#0d1b2a;}
.fn.hi .fn-label{color:#93c5fd;}
.fn.gem{border-color:#7c3aed;background:#1a0d2e;}
.fn.gem .fn-label{color:#c4b5fd;}

/* セキュリティ */
.sec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;}
.sec-card{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:12px 14px;}
.sec-head{font-size:.82rem;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
.sec-body{font-size:.75rem;color:#8b949e;line-height:1.65;}
.sec-body code{font-family:'Courier New',monospace;color:#f0883e;background:#21262d;padding:1px 5px;border-radius:3px;}

/* 凡例 */
.legend{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;}
.leg{display:flex;align-items:center;gap:5px;font-size:.75rem;color:#8b949e;}
.leg-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0;}

@media(max-width:600px){
  .file-grid,.db-grid,.sec-grid{grid-template-columns:1fr;}
  .tab-btn{padding:8px 12px;font-size:.76rem;}
  .flow-nodes{flex-direction:column;align-items:flex-start;}
  .fn-arrow{transform:rotate(90deg);}
}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-title"><span class="dot"></span>生徒カルテ — システム構造図</div>
  <a href="/karte/home.php" class="back-btn">← ホームへ戻る</a>
</div>

<div class="tab-bar">
  <button class="tab-btn active" onclick="showTab('files')">📂 ファイル構成</button>
  <button class="tab-btn" onclick="showTab('api')">⚙ APIエンドポイント</button>
  <button class="tab-btn" onclick="showTab('db')">🗄 データベース</button>
  <button class="tab-btn" onclick="showTab('flow')">↔ データフロー</button>
  <button class="tab-btn" onclick="showTab('security')">🔐 セキュリティ</button>
</div>

<!-- ===== TAB: FILES ===== -->
<div class="tab-pane active" id="tab-files">

  <div class="sec">
    <div class="sec-title">画面ファイル（PHP）</div>
    <div class="legend">
      <div class="leg"><div class="leg-dot" style="background:#1f3a7a;"></div>PHP画面</div>
      <div class="leg"><div class="leg-dot" style="background:#0d3321;"></div>APIエンドポイント</div>
      <div class="leg"><div class="leg-dot" style="background:#2d1a50;"></div>設定ファイル</div>
    </div>
    <div class="file-grid">

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">🔑</div>
          <div>
            <div class="file-name">index.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">ログイン画面</div>
          </div>
        </div>
        <dl class="kv">
          <dt>認証方式</dt><dd>teachers.username + password (password_verify)</dd>
          <dt>失敗対策</dt><dd>checkLoginRateLimit() → 10回失敗で15分ロック</dd>
          <dt>成功時</dt><dd>session_regenerate_id(true) → home.php へリダイレクト</dd>
          <dt>セッション変数</dt><dd>$_SESSION['teacher_id'], ['teacher_name']</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">🏠</div>
          <div>
            <div class="file-name">home.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">生徒一覧ホーム画面</div>
          </div>
        </div>
        <dl class="kv">
          <dt>表示データ</dt><dd>students テーブル全件（クラス・出席順）</dd>
          <dt>統計表示</dt><dd>生徒総数 / カルテ記録数 / 出欠記録数</dd>
          <dt>機能</dt><dd>検索（名前/ふりがな）、クラスフィルター、生徒追加モーダル</dd>
          <dt>生徒行クリック</dt><dd>karte_detail.php?id={student_id} へ遷移</dd>
          <dt>前回の続きバー</dt><dd>localStorage['karte_last_state'] を読み込み、24時間以内なら「{氏名}さんのカルテを XX分前に閲覧」バーを表示。クリックで直接前回タブへ遷移</dd>
          <dt>モバイル対応</dt><dd>≤480px でハンバーガーメニュー（ドロワー）</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">📋</div>
          <div>
            <div class="file-name">karte_detail.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">生徒カルテ詳細（タブ式）</div>
          </div>
        </div>
        <dl class="kv">
          <dt>URLパラメータ</dt><dd>?id={student_id}</dd>
          <dt>タブ一覧</dt><dd>指導記録 / 出欠・勤怠 / 面談記録 / メモ・所見 / 家庭環境 / 📄家庭調査票 / 基本情報 / 🗺地図 / 📜履歴</dd>
          <dt>前後切替</dt><dd>◀▶ボタン + マウスホイール + 横スワイプ（タッチ）</dd>
          <dt>生徒切替方式</dt><dd>AJAX（フルリロードなし）。ALL_IDSをJSに埋め込み、prev/next・スライダーをクライアント側で計算。切替時は全タブ内容をJS更新</dd>
          <dt>スライダー</dt><dd>recSlider(input[type=range]) + recNumInput(番号入力)。goTo(n)がALL_IDS[n-1]を参照しgo()でAJAX遷移。updateHeader()でvalue/maxを更新</dd>
          <dt>キャッシュ戦略</dt><dd>studentCache（生徒ヘッダー）+ tabCache（タブJSON）をPromiseキャッシュ。adjacentIds(pos,3)で前後各3件をrequestIdleCallbackで先読み</dd>
          <dt>タブ連動更新</dt><dd>go()関数が全タブを生徒切替に連動。地図=住所リセット・調査票=loadSurvey()・家庭環境/基本情報=フォーム値書換</dd>
          <dt>トップバー</dt><dd>クラス枠(100px固定)と氏名枠(120px固定)を別ボックスで表示。生徒切替で位置がずれない</dd>
          <dt>情報を隠す</dt><dd>「情報を隠す」ボタン(fm-header-toggle)でfm-student-headerをmax-height CSSアニメで折りたたみ。状態をlocalStorage['karteHeaderCollapsed']に保存し次回アクセス時に復元。展開時はinlineスタイルをリセットしてからcollapsedクラスを除去</dd>
          <dt>データ優先順位</dt><dd>gakuseki > students（名前・住所・電話等）</dd>
          <dt>写真表示</dt><dd>requestAnimationFrameで遅延適用（ヘッダー更新後に非同期）</dd>
          <dt>履歴タブ</dt><dd>activity_log テーブルから取得。操作種別ごとにアイコン色分け（追加=緑/編集=青/削除=赤/メモ=紫/基本情報=黄）。日付セパレータ付き時系列表示</dd>
          <dt>最終閲覧記憶</dt><dd>saveLastState() → localStorage['karte_last_state']（student_id, panel, tab_label, ts）。再ロード時に同一生徒なら前回タブを自動復元</dd>
          <dt>モバイル対応</dt><dd>≤480px ハンバーガーメニュー、タブ横スクロール</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">📚</div>
          <div>
            <div class="file-name">gakuseki.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">学籍簿管理画面</div>
          </div>
        </div>
        <dl class="kv">
          <dt>データソース</dt><dd>gakuseki + student_nendo + teachers テーブル</dd>
          <dt>機能</dt><dd>学籍一覧、個人詳細編集、年度別クラス・担任管理</dd>
          <dt>API</dt><dd>api/gakuseki.php（GET list/get, POST save/add/delete）</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">👥</div>
          <div>
            <div class="file-name">student_manager.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">生徒登録・一括管理</div>
          </div>
        </div>
        <dl class="kv">
          <dt>機能</dt><dd>生徒追加・削除・クラス変更・クラス名変更・一括削除</dd>
          <dt>CSVインポート</dt><dd>列順: 生徒ID, 名前, クラス, ふりがな, 出席番号</dd>
          <dt>CSVエクスポート</dt><dd>api/students.php?action=export（UTF-8 BOM付）</dd>
          <dt>API</dt><dd>api/students.php（add / delete / bulk_delete / change_class / rename_class / delete_class / import）</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">📸</div>
          <div>
            <div class="file-name">photo_import.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">顔写真一括取込（Gemini Vision）</div>
          </div>
        </div>
        <dl class="kv">
          <dt>入力</dt><dd>B4集合写真（JPEG/PNG/GIF/WebP, 最大20MB）</dd>
          <dt>API呼び出し</dt><dd>Gemini 2.0 Flash → 顔座標+氏名JSON</dd>
          <dt>マッチング</dt><dd>similar_text() ≥70% で自動紐付け</dd>
          <dt>切り出し</dt><dd>PHP GDライブラリで顔領域をJPEGで切り出し</dd>
          <dt>一時保存</dt><dd>uploads/tmp_crops/{session_id}_crop_{i}.jpg</dd>
          <dt>APIキー</dt><dd>GEMINI_API_KEY or フォームの api_key_override</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">📋</div>
          <div>
            <div class="file-name">survey_import.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">家庭調査票PDFの一括取込</div>
          </div>
        </div>
        <dl class="kv">
          <dt>PDF変換</dt><dd>PDF.js v4.4.168（ブラウザ側・CDN）でCanvas描画</dd>
          <dt>マッチング方式</dt><dd>出席番号順（開始番号を画面で指定）</dd>
          <dt>アップロード</dt><dd>Canvas → Blob(JPEG) → api/survey.php?action=upload</dd>
          <dt>クラス選択</dt><dd>DBから取得したクラス一覧をセレクト</dd>
          <dt>GD/Imagick不要</dt><dd>サーバー側PDF処理なし（ブラウザのみ）</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">💾</div>
          <div>
            <div class="file-name">backup.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">カルテデータのバックアップ・復元管理画面</div>
          </div>
        </div>
        <dl class="kv">
          <dt>機能</dt><dd>全生徒一括バックアップ / 全件復元 / 生徒単体バックアップ / 世代履歴表示</dd>
          <dt>保存先</dt><dd>data/students/{student_id}.json（最新世代）</dd>
          <dt>世代管理</dt><dd>data/students/history/{student_id}/{timestamp}.json（最大20世代）</dd>
          <dt>バックアップ内容</dt><dd>students行 + karte_records + attendance_records + interview_records + memos</dd>
          <dt>ライブラリ</dt><dd>lib/backup.php（karteBackupStudent / karteBackupAll / karteRestoreStudent）</dd>
          <dt>自動バックアップ</dt><dd>karteBackupStudent() は各種POST保存時に自動呼び出し</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">📊</div>
          <div>
            <div class="file-name">api/export_excel.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">全データをExcel(SpreadsheetML)形式でダウンロード</div>
          </div>
        </div>
        <dl class="kv">
          <dt>出力形式</dt><dd>SpreadsheetML (.xls) — ZipArchive・PhpSpreadsheet不要</dd>
          <dt>シート構成</dt><dd>生徒一覧 / 指導記録 / 出欠記録 / 面談記録</dd>
          <dt>ライブラリ</dt><dd>lib/excel.php（KarteXlsxクラス）</dd>
          <dt>認証</dt><dd>requireLogin() 必須</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">📥</div>
          <div>
            <div class="file-name">api/import_excel.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">ExcelファイルからDBへインポート</div>
          </div>
        </div>
        <dl class="kv">
          <dt>入力形式</dt><dd>SpreadsheetML (.xls) — このシステムでエクスポートしたファイルのみ対応</dd>
          <dt>解析方式</dt><dd>simplexml_load_file() でXMLパース、名前空間 urn:schemas-microsoft-com:office:spreadsheet</dd>
          <dt>上限</dt><dd>20MB以下</dd>
          <dt>認証</dt><dd>requireLogin() + CSRF検証</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">🗺</div>
          <div>
            <div class="file-name">structure.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">このページ（システム構造図）</div>
          </div>
        </div>
        <dl class="kv">
          <dt>認証</dt><dd>requireLogin() 必須</dd>
          <dt>用途</dt><dd>開発者・Claude向けシステム概要リファレンス</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">🖨</div>
          <div>
            <div class="file-name">karte_card.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">カルテカード印刷</div>
          </div>
        </div>
        <dl class="kv">
          <dt>URLパラメータ</dt><dd>?id={student_id}</dd>
          <dt>内容</dt><dd>生徒基本情報＋カルテ記録の印刷用レイアウト</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">⚙</div>
          <div>
            <div class="file-name">setup.php <span class="file-badge b-php">PHP</span></div>
            <div class="file-desc">DB初期化・テーブル作成</div>
          </div>
        </div>
        <dl class="kv">
          <dt>アクセス条件</dt><dd>localhost OR ログイン済み OR ?token={KARTE_SETUP_TOKEN}</dd>
          <dt>作成テーブル</dt><dd>teachers, students, karte_records, karte_attendance, login_attempts, karte_interviews, gakuseki, student_nendo, activity_log</dd>
          <dt>ALTER TABLE</dt><dd>後付カラム追加（gakno, memo_posi, memo_nega, memo_main, photo 等）</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">🚀</div>
          <div>
            <div class="file-name">deploy.php <span class="file-badge b-dep">DEP</span></div>
            <div class="file-desc">さくらデプロイツール（gitignore済み）</div>
          </div>
        </div>
        <dl class="kv">
          <dt>認証</dt><dd>?token=karte2026deploy（URLトークン）</dd>
          <dt>actions</dt><dd>status / clone / pull / log / delete</dd>
          <dt>pull方式</dt><dd>git fetch origin &amp;&amp; git reset --hard origin/master（バックグラウンド実行）</dd>
          <dt>ログ</dt><dd>sys_get_temp_dir()/karte_deploy.log</dd>
          <dt>注意</dt><dd>.gitignore で除外→FTP等で手動アップロード</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">🔧</div>
          <div>
            <div class="file-name">config.php <span class="file-badge b-cfg">CFG</span></div>
            <div class="file-desc">全ファイルが require_once する共通設定</div>
          </div>
        </div>
        <dl class="kv">
          <dt>DB切替</dt><dd>SERVER_ADDRで自動判定（local: root/なし / sakura: opened_karte_db）</dd>
          <dt>Gemini API</dt><dd>GEMINI_API_KEY ← config.local.php（gitignore済み）</dd>
          <dt>主要関数</dt><dd>getDB() / requireLogin() / startSession() / generateCsrfToken() / verifyCsrfToken() / sendSecurityHeaders() / checkLoginRateLimit() / recordLoginAttempt()</dd>
        </dl>
      </div>

      <div class="file-card">
        <div class="file-header">
          <div class="file-icon">🔑</div>
          <div>
            <div class="file-name">config.local.php <span class="file-badge b-cfg">CFG</span></div>
            <div class="file-desc">Gemini APIキー（gitignore済み・本番は手動配置）</div>
          </div>
        </div>
        <dl class="kv">
          <dt>内容</dt><dd>&lt;?php return 'AIzaS...';</dd>
          <dt>読込方法</dt><dd>require __DIR__.'/config.local.php' → define('GEMINI_API_KEY', ...)</dd>
          <dt>さくら本番</dt><dd>FTPで手動アップロード（GitHubには含めない）</dd>
        </dl>
      </div>

    </div>
  </div>

  <div class="sec">
    <div class="sec-title">アップロードフォルダ</div>
    <div class="file-grid">
      <div class="file-card">
        <div class="file-header"><div class="file-icon">📁</div><div><div class="file-name">uploads/photos/ <span class="file-badge b-dir">DIR</span></div><div class="file-desc">生徒顔写真</div></div></div>
        <dl class="kv">
          <dt>命名規則</dt><dd>{gakno or s_{student_id}}_{timestamp}.{ext}</dd>
          <dt>インポート時</dt><dd>import_{student_id}_{timestamp}.jpg</dd>
          <dt>DBへの参照</dt><dd>gakuseki.photo または students.photo に /karte/uploads/photos/xxx を保存</dd>
          <dt>保護</dt><dd>.htaccess でPHP実行禁止、gitignore済み</dd>
        </dl>
      </div>
      <div class="file-card">
        <div class="file-header"><div class="file-icon">📁</div><div><div class="file-name">uploads/survey/ <span class="file-badge b-dir">DIR</span></div><div class="file-desc">家庭調査票画像</div></div></div>
        <dl class="kv">
          <dt>命名規則</dt><dd>{surveyKey}.{ext}（surveyKey = gakno or s_{student_id} を正規化）</dd>
          <dt>上書き方式</dt><dd>同一キーの旧ファイルを削除してから新規保存</dd>
          <dt>保護</dt><dd>.htaccess でPHP実行禁止、gitignore済み</dd>
        </dl>
      </div>
      <div class="file-card">
        <div class="file-header"><div class="file-icon">📁</div><div><div class="file-name">uploads/tmp_crops/ <span class="file-badge b-dir">DIR</span></div><div class="file-desc">写真取込の一時切り出し</div></div></div>
        <dl class="kv">
          <dt>命名規則</dt><dd>{session_id}_crop_{i}.jpg</dd>
          <dt>自動削除</dt><dd>api/photo_import.php の save アクション時に1時間超過ファイルを削除</dd>
          <dt>保護</dt><dd>.htaccess でPHP実行禁止、gitignore済み</dd>
        </dl>
      </div>
      <div class="file-card">
        <div class="file-header"><div class="file-icon">📁</div><div><div class="file-name">data/students/ <span class="file-badge b-dir">DIR</span></div><div class="file-desc">生徒カルテJSONバックアップ</div></div></div>
        <dl class="kv">
          <dt>最新バックアップ</dt><dd>data/students/{student_id}.json（保存のたびに上書き）</dd>
          <dt>世代バックアップ</dt><dd>data/students/history/{student_id}/{timestamp}.json（最大20世代保持）</dd>
          <dt>JSON内容</dt><dd>students / karte_records / attendance_records / interview_records / memos の全データ</dd>
          <dt>gitignore</dt><dd>data/ はGit管理外（scpでSakuraに直接転送）</dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="sec">
    <div class="sec-title">lib/ — ライブラリ</div>
    <div class="file-grid">
      <div class="file-card">
        <div class="file-header"><div class="file-icon">📚</div><div><div class="file-name">lib/backup.php <span class="file-badge b-php">PHP</span></div><div class="file-desc">バックアップ・復元ライブラリ</div></div></div>
        <dl class="kv">
          <dt>主要関数</dt><dd>karteBackupStudent(conn, sid) / karteBackupAll(conn) / karteRestoreStudent(conn, sid)</dd>
          <dt>定数</dt><dd>KARTE_BACKUP_DIR / KARTE_BACKUP_HIST / KARTE_BACKUP_VERSION=2 / KARTE_BACKUP_KEEP=20</dd>
          <dt>エラー処理</dt><dd>例外をキャッチしてerror_log出力、呼び出し元の処理を止めない</dd>
        </dl>
      </div>
      <div class="file-card">
        <div class="file-header"><div class="file-icon">📊</div><div><div class="file-name">lib/excel.php <span class="file-badge b-php">PHP</span></div><div class="file-desc">Excel書き出しライブラリ</div></div></div>
        <dl class="kv">
          <dt>クラス</dt><dd>KarteXlsx — addSheet(name, rows) / download(filename)</dd>
          <dt>形式</dt><dd>SpreadsheetML XML (.xls) — ZipArchive・外部ライブラリ不要</dd>
          <dt>ヘッダー行</dt><dd>1行目を太字＋背景色スタイルで自動適用</dd>
        </dl>
      </div>
      <div class="file-card">
        <div class="file-header"><div class="file-icon">📄</div><div><div class="file-name">lib/pdfjs/ <span class="file-badge b-dir">DIR</span></div><div class="file-desc">PDF.js v4.4.168（ローカルホスト用）</div></div></div>
        <dl class="kv">
          <dt>用途</dt><dd>survey_import.php でPDFをCanvas描画するために使用</dd>
          <dt>ファイル</dt><dd>pdf.min.js / pdf.min.mjs / pdf.worker.min.js / pdf.worker.min.mjs</dd>
          <dt>備考</dt><dd>ローカル環境ではCDN不要。さくら本番はCDN経由も可</dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<!-- ===== TAB: API ===== -->
<div class="tab-pane" id="tab-api">

  <div class="sec">
    <div class="sec-title">api/karte.php — カルテ記録・出欠・面談</div>
    <div class="file-card" style="max-width:100%;">
      <div class="file-desc" style="margin-bottom:8px;">全エンドポイント: requireLogin() + verifyCsrfToken()（POST）+ Prepared Statement</div>
      <table class="api-table">
        <thead><tr><th>メソッド</th><th>action</th><th>パラメータ（主要）</th><th>処理・返却</th></tr></thead>
        <tbody>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">student_summary</span></td><td>-</td><td>全生徒 + rec_count / att_count / last_record を1クエリで取得</td></tr>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">list_records</span></td><td>student_id</td><td>karte_records（記録日DESC）</td></tr>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">list_attendance</span></td><td>student_id</td><td>karte_attendance（att_date DESC）</td></tr>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">list_interviews</span></td><td>student_id</td><td>karte_interviews（interview_date DESC）</td></tr>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">get_gakno</span></td><td>student_id</td><td>students.gakno を返却</td></tr>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">get_memos</span></td><td>student_id</td><td>memo_posi / memo_nega / memo_main</td></tr>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">list_history</span></td><td>student_id</td><td>activity_log から最新300件をDESC取得。テーブル未存在時は空配列返却（エラーにならない）</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">add_student</span></td><td>student_id, name, class_name, furigana</td><td>students に INSERT（重複は1062エラー）</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">save_basic</span></td><td>student_id, name, furigana, class_name, seat_number, gender, birthday, phone, parent_name, address, notes</td><td>students を UPDATE</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">add_record</span></td><td>student_id, record_date, record_type, content, teacher, next_action</td><td>karte_records に INSERT</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">update_record</span></td><td>id, record_date, record_type, content, teacher, next_action</td><td>karte_records を UPDATE</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">delete_record</span></td><td>id</td><td>karte_records を DELETE</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">add_attendance</span></td><td>student_id, att_date, att_type, reason, parent_contacted, notes</td><td>karte_attendance に INSERT</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">delete_attendance</span></td><td>id</td><td>karte_attendance を DELETE</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">add_interview</span></td><td>student_id, interview_date, interview_type, participants, content, next_action</td><td>karte_interviews に INSERT</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">delete_interview</span></td><td>id</td><td>karte_interviews を DELETE</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">save_gakno</span></td><td>student_id, gakno</td><td>students.gakno を UPDATE。gakno紐付け時にstudents.photo→gakuseki.photoへ移行（gakusekiに写真がない場合のみ）</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">save_memos</span></td><td>student_id, posi, nega, main</td><td>students の memo_posi / nega / main を UPDATE</td></tr>
        </tbody>
        <tfoot><tr><td colspan="4" style="padding:6px 8px;font-size:.72rem;color:#8b949e;">※ 書き込み系アクション（add/update/delete/save_memos/save_basic）はすべて <code style="color:#f0883e;">logActivity()</code> を呼び出し activity_log に記録。logActivity() は実行時にテーブルを自動作成（CREATE TABLE IF NOT EXISTS）するため setup.php 不要。</td></tr></tfoot>
      </table>
    </div>
  </div>

  <div class="sec">
    <div class="sec-title">api/students.php — 生徒CRUD・CSV</div>
    <div class="file-card" style="max-width:100%;">
      <table class="api-table">
        <thead><tr><th>メソッド</th><th>action</th><th>パラメータ・形式</th><th>処理・返却</th></tr></thead>
        <tbody>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">list</span></td><td>-</td><td>{students[], classes[]} を返却</td></tr>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">export</span></td><td>-</td><td>UTF-8 BOM付きCSVファイルをダウンロード</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">add</span></td><td>JSON or FormData: student_id, name, class_name, furigana</td><td>students に INSERT</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">delete</span></td><td>JSON: student_id</td><td>karte_records / attendance / interviews / students を全削除</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">bulk_delete</span></td><td>JSON: ids[]</td><td>複数生徒の全データ削除</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">change_class</span></td><td>JSON: student_id, class_name</td><td>students.class_name を UPDATE</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">rename_class</span></td><td>JSON: old_name, new_name</td><td>クラス名一括変更</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">delete_class</span></td><td>JSON: class_name</td><td>生徒のclass_nameを''に（生徒自体は削除しない）</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">import</span></td><td>multipart: csv（CSV列順: 生徒ID, 名前, クラス, ふりがな, 出席番号）</td><td>既存→UPDATE、新規→INSERT。{created, updated, errors[]}を返却</td></tr>
        </tbody>
      </table>
      <div class="file-detail" style="margin-top:8px;">※ JSON body POSTはCSRF検証スキップ（セッション認証のみ）。フォームPOSTはcsrf_token検証あり。</div>
    </div>
  </div>

  <div class="sec">
    <div class="sec-title">api/photo.php — 顔写真アップロード</div>
    <div class="file-card" style="max-width:100%;">
      <div class="file-desc" style="margin-bottom:8px;">POSTのみ。CSRF検証あり。exif_imagetype()で実際のMIMEを検証（JPEG/PNG/GIF/WebP、最大4MB）</div>
      <table class="api-table">
        <thead><tr><th>action</th><th>パラメータ</th><th>処理</th></tr></thead>
        <tbody>
          <tr><td><span class="action-name">upload</span></td><td>gakno or student_id, photo（ファイル）</td><td>旧ファイル削除→uploads/photos/に保存→gakuseki.photo or students.photoにパスをUPDATE</td></tr>
          <tr><td><span class="action-name">delete</span></td><td>gakno or student_id</td><td>ファイル削除→DB nullクリア</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="sec">
    <div class="sec-title">api/survey.php — 家庭調査票</div>
    <div class="file-card" style="max-width:100%;">
      <div class="file-desc" style="margin-bottom:8px;">surveyKey() でgakno or "s_{student_id}"をファイル名に使用。findSurveyFile()で拡張子を自動検索。</div>
      <table class="api-table">
        <thead><tr><th>メソッド</th><th>action</th><th>パラメータ</th><th>処理</th></tr></thead>
        <tbody>
          <tr><td><span class="method m-get">GET</span></td><td>-</td><td>gakno or student_id</td><td>現在の調査票画像URLを返却（なければurl:null）</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">upload</span></td><td>gakno or student_id, survey（ファイル、最大10MB）</td><td>旧ファイル削除→{key}.{ext}で保存</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">delete</span></td><td>gakno or student_id</td><td>ファイル削除</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="sec">
    <div class="sec-title">api/photo_import.php — Gemini Vision顔認識</div>
    <div class="file-card" style="max-width:100%;">
      <div class="file-desc" style="margin-bottom:8px;">POSTのみ。CSRF検証あり。GDライブラリで顔を切り出し。</div>
      <table class="api-table">
        <thead><tr><th>action</th><th>パラメータ</th><th>処理</th></tr></thead>
        <tbody>
          <tr><td><span class="action-name">analyze</span></td><td>sheet（B4画像, 最大20MB）, api_key_override（任意）</td><td>①base64エンコード→②Gemini 2.0 Flash API呼び出し（顔座標+氏名JSON取得）→③GDで顔を切り出しtmp_cropsに保存→④similar_text()で生徒名マッチング（≥70%で自動）→⑤{results[], students[]}返却</td></tr>
          <tr><td><span class="action-name">save</span></td><td>assignments（JSON: [{student_id, crop_file}]）</td><td>tmp_cropsからphotosにコピー→gakuseki.photo・students.photoをUPDATE→1時間超過の一時ファイルを掃除</td></tr>
        </tbody>
      </table>
      <div class="file-detail" style="margin-top:8px;">
        Gemini API URL: <code style="font-size:.73rem;font-family:'Courier New',monospace;color:#f0883e;">https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={GEMINI_API_KEY}</code><br>
        プロンプト: 日本語で顔写真グリッドの座標(x,y,w,h)+氏名をJSONで返すよう指示。temperature=0。
      </div>
    </div>
  </div>

  <div class="sec">
    <div class="sec-title">api/gakuseki.php — 学籍台帳</div>
    <div class="file-card" style="max-width:100%;">
      <table class="api-table">
        <thead><tr><th>メソッド</th><th>action</th><th>主なパラメータ・処理</th></tr></thead>
        <tbody>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">list</span></td><td>?nendo= でフィルター。gakuseki + student_nendo + teachers をJOINして返却</td></tr>
          <tr><td><span class="method m-get">GET</span></td><td><span class="action-name">get</span></td><td>?gakno= で1件取得 + nendo_list[]</td></tr>
          <tr><td><span class="method m-post">POST</span></td><td><span class="action-name">save / add / delete</span></td><td>gakuseki・student_nendo の CRUD</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ===== TAB: DB ===== -->
<div class="tab-pane" id="tab-db">

  <div class="sec">
    <div class="sec-title">DB接続情報</div>
    <div class="file-card" style="max-width:600px;">
      <dl class="kv">
        <dt>ローカル (XAMPP)</dt><dd>localhost / root / （パスワードなし） / karte_db</dd>
        <dt>さくら本番</dt><dd>mysql3115.db.sakura.ne.jp / opened_karte_db / Yatto_2026 / opened_karte_db</dd>
        <dt>判定方法</dt><dd>SERVER_ADDR が private/loopback IP か否か（config.php:8行目）</dd>
        <dt>文字コード</dt><dd>utf8mb4 / utf8mb4_unicode_ci</dd>
        <dt>接続方法</dt><dd>mysqli（PDOは不使用）</dd>
      </dl>
    </div>
  </div>

  <div class="sec">
    <div class="sec-title">テーブル一覧</div>
    <div class="db-grid">

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">teachers</span><span class="db-card-label">教員マスタ</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑 PK</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span></span><span class="db-col-name">username</span><span class="db-col-type">VARCHAR(50) UNIQUE</span></div>
          <div class="db-col"><span></span><span class="db-col-name">password</span><span class="db-col-type">VARCHAR(255) ← password_hash()</span></div>
          <div class="db-col"><span></span><span class="db-col-name">display_name</span><span class="db-col-type">VARCHAR(100)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">created_at</span><span class="db-col-type">TIMESTAMP</span></div>
        </div>
      </div>

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">students</span><span class="db-card-label">生徒マスタ</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑 PK</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span class="db-key">UQ</span><span class="db-col-name">student_id</span><span class="db-col-type">VARCHAR(10) ← URLパラメータ</span></div>
          <div class="db-col"><span></span><span class="db-col-name">name / furigana</span><span class="db-col-type">VARCHAR(100)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">class_name</span><span class="db-col-type">VARCHAR(50)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">seat_number</span><span class="db-col-type">INT（出席番号）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">gender / birthday</span><span class="db-col-type">VARCHAR / DATE</span></div>
          <div class="db-col"><span></span><span class="db-col-name">address / parent_name / phone / notes</span><span class="db-col-type">TEXT / VARCHAR</span></div>
          <div class="db-col"><span></span><span class="db-col-name">photo</span><span class="db-col-type">VARCHAR ← /karte/uploads/photos/xxx</span></div>
          <div class="db-col"><span class="db-fk">FK?</span><span class="db-col-name">gakno</span><span class="db-col-type">VARCHAR(20) → gakuseki.gakno</span></div>
          <div class="db-col"><span></span><span class="db-col-name">memo_posi / memo_nega / memo_main</span><span class="db-col-type">TEXT（後付ALTER）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">created_at / updated_at</span><span class="db-col-type">TIMESTAMP</span></div>
        </div>
      </div>

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">karte_records</span><span class="db-card-label">カルテ記録</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span class="db-fk">FK</span><span class="db-col-name">student_id</span><span class="db-col-type">VARCHAR(10) → students.student_id</span></div>
          <div class="db-col"><span></span><span class="db-col-name">record_date</span><span class="db-col-type">DATE（INDEX）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">record_type</span><span class="db-col-type">VARCHAR(30)（面談/保護者連絡/欠席連絡/etc）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">content</span><span class="db-col-type">TEXT（必須）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">teacher</span><span class="db-col-type">VARCHAR(100)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">next_action</span><span class="db-col-type">TEXT</span></div>
        </div>
      </div>

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">karte_attendance</span><span class="db-card-label">出欠記録</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span class="db-fk">FK</span><span class="db-col-name">student_id</span><span class="db-col-type">VARCHAR(10)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">att_date</span><span class="db-col-type">DATE</span></div>
          <div class="db-col"><span></span><span class="db-col-name">att_type</span><span class="db-col-type">VARCHAR(20)（欠席/遅刻/早退）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">reason</span><span class="db-col-type">TEXT</span></div>
          <div class="db-col"><span></span><span class="db-col-name">parent_contacted</span><span class="db-col-type">VARCHAR(10)（未/済）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">notes</span><span class="db-col-type">TEXT</span></div>
        </div>
      </div>

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">karte_interviews</span><span class="db-card-label">面談記録</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span class="db-fk">FK</span><span class="db-col-name">student_id</span><span class="db-col-type">VARCHAR(10)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">interview_date</span><span class="db-col-type">DATE</span></div>
          <div class="db-col"><span></span><span class="db-col-name">interview_type</span><span class="db-col-type">VARCHAR(50)（三者面談/個人面談/etc）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">participants</span><span class="db-col-type">VARCHAR(200)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">content</span><span class="db-col-type">TEXT（必須）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">next_action</span><span class="db-col-type">TEXT</span></div>
          <div class="db-col"><span></span><span class="db-col-name">nendo</span><span class="db-col-type">INT DEFAULT NULL</span></div>
        </div>
      </div>

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">gakuseki</span><span class="db-card-label">学籍台帳</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span class="db-key">UQ</span><span class="db-col-name">gakno</span><span class="db-col-type">VARCHAR(20)（学籍番号）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">name / furigana / seibetu</span><span class="db-col-type">VARCHAR</span></div>
          <div class="db-col"><span></span><span class="db-col-name">birthday</span><span class="db-col-type">DATE</span></div>
          <div class="db-col"><span></span><span class="db-col-name">yuubin / jyusyo</span><span class="db-col-type">郵便番号 / 住所TEXT</span></div>
          <div class="db-col"><span></span><span class="db-col-name">hogosya / hogokana / zokugara</span><span class="db-col-type">保護者情報</span></div>
          <div class="db-col"><span></span><span class="db-col-name">tel1 / tel2</span><span class="db-col-type">VARCHAR(50)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">nyunendo / nyugaku / sotsugyo</span><span class="db-col-type">INT / DATE / DATE</span></div>
          <div class="db-col"><span></span><span class="db-col-name">gakuseki_status</span><span class="db-col-type">VARCHAR(20)</span></div>
          <div class="db-col"><span></span><span class="db-col-name">photo</span><span class="db-col-type">VARCHAR ← /karte/uploads/photos/xxx</span></div>
        </div>
      </div>

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">student_nendo</span><span class="db-card-label">年度別クラス情報</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span class="db-fk">FK</span><span class="db-col-name">gakno</span><span class="db-col-type">VARCHAR(20) → gakuseki.gakno</span></div>
          <div class="db-col"><span class="db-key">UQ</span><span class="db-col-name">nendo + gakno</span><span class="db-col-type">UNIQUE KEY uk_gakno_nendo</span></div>
          <div class="db-col"><span></span><span class="db-col-name">gakunen</span><span class="db-col-type">INT（学年）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">class_no</span><span class="db-col-type">VARCHAR(10)（クラス）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">bango</span><span class="db-col-type">INT（出席番号）</span></div>
          <div class="db-col"><span class="db-fk">FK</span><span class="db-col-name">teacher_id</span><span class="db-col-type">INT → teachers.id</span></div>
          <div class="db-col"><span></span><span class="db-col-name">sinkyu</span><span class="db-col-type">VARCHAR(20)（進級区分）</span></div>
        </div>
      </div>

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">activity_log</span><span class="db-card-label">操作履歴ログ</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span class="db-fk">FK</span><span class="db-col-name">teacher_id</span><span class="db-col-type">INT → teachers.id（0=不明）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">teacher_name</span><span class="db-col-type">VARCHAR(100)（操作者表示名）</span></div>
          <div class="db-col"><span class="db-fk">FK</span><span class="db-col-name">student_id</span><span class="db-col-type">VARCHAR(10) → students.student_id</span></div>
          <div class="db-col"><span></span><span class="db-col-name">action_type</span><span class="db-col-type">VARCHAR(50)（指導記録を追加/削除, 出欠, 面談, メモ, 基本情報等）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">detail</span><span class="db-col-type">TEXT（最大300文字 mb_strimwidth）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">created_at</span><span class="db-col-type">TIMESTAMP DEFAULT CURRENT_TIMESTAMP</span></div>
          <div class="db-col"><span></span><span class="db-col-name">INDEX</span><span class="db-col-type">idx_student(student_id), idx_created(created_at)</span></div>
        </div>
        <div style="padding:4px 12px 8px;font-size:.7rem;color:#6e7681;">logActivity() が初回呼び出し時に自動作成（setup.php 不要）</div>
      </div>

      <div class="db-card">
        <div class="db-card-head"><span class="db-card-name">login_attempts</span><span class="db-card-label">ログイン試行記録</span></div>
        <div class="db-cols">
          <div class="db-col"><span class="db-key">🔑</span><span class="db-col-name">id</span><span class="db-col-type">INT AUTO_INCREMENT</span></div>
          <div class="db-col"><span></span><span class="db-col-name">ip_address</span><span class="db-col-type">VARCHAR(45)（IPv6対応）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">success</span><span class="db-col-type">TINYINT(1)（0=失敗, 1=成功）</span></div>
          <div class="db-col"><span></span><span class="db-col-name">attempted_at</span><span class="db-col-type">TIMESTAMP（INDEX: ip+time）</span></div>
        </div>
      </div>

    </div>

    <div class="rel-note" style="margin-top:12px;">
      <strong>テーブル関係まとめ：</strong><br>
      students.student_id → karte_records / karte_attendance / karte_interviews の外部キー（外部キー制約なし・アプリ側で整合性管理）<br>
      students.gakno → gakuseki.gakno（任意紐付け。紐付け時に photo が gakuseki 側に移行）<br>
      gakuseki.gakno → student_nendo.gakno（年度別クラス・担任情報）<br>
      student_nendo.teacher_id → teachers.id
    </div>
  </div>

</div>

<!-- ===== TAB: FLOW ===== -->
<div class="tab-pane" id="tab-flow">
  <div class="flow-wrap">

    <div class="flow-item">
      <div class="flow-title"><span>🔑</span> ログイン・セッション確立</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">🌐</span><span class="fn-label">ブラウザ</span><span class="fn-sub">index.php</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🔍</span><span class="fn-label">checkLoginRateLimit()</span><span class="fn-sub">IPごと10回/15分</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">✅</span><span class="fn-label">password_verify()</span><span class="fn-sub">teachers テーブル</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">🔐</span><span class="fn-label">session_regenerate_id()</span><span class="fn-sub">$_SESSION['teacher_id']</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🏠</span><span class="fn-label">home.php</span><span class="fn-sub">生徒一覧</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>📋</span> 通常カルテ操作（karte_detail.php）</div>
      <div class="flow-nodes">
        <div class="fn hi"><span class="fn-icon">📋</span><span class="fn-label">karte_detail.php</span><span class="fn-sub">?id={student_id}</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">📡</span><span class="fn-label">fetch (JS)</span><span class="fn-sub">CSRF token付き</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">⚙</span><span class="fn-label">api/karte.php</span><span class="fn-sub">requireLogin()+CSRF</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🗄</span><span class="fn-label">MySQL</span><span class="fn-sub">Prepared Statement</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">📊</span><span class="fn-label">JSON返却</span><span class="fn-sub">{success:true, rows:[]}</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>↔</span> 生徒切替（karte_detail.php）</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">🖱</span><span class="fn-label">◀▶ / ホイール</span><span class="fn-sub">スライダー / スワイプ</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">📋</span><span class="fn-label">ALL_IDS[n]</span><span class="fn-sub">JSに埋め込み済み全ID列</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">⚡</span><span class="fn-label">go(id)</span><span class="fn-sub">studentCache hit→即時</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🔄</span><span class="fn-label">updateHeader()</span><span class="fn-sub">全タブDOM更新</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🔮</span><span class="fn-label">prefetchAdjacent()</span><span class="fn-sub">前後3件先読み</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>🎚</span> スライダー・番号入力による生徒ジャンプ</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">🎚</span><span class="fn-label">recSlider</span><span class="fn-sub">input[type=range]</span></div>
        <div class="fn-arrow">→ change</div>
        <div class="fn"><span class="fn-icon">🔢</span><span class="fn-label">goTo(n)</span><span class="fn-sub">ALL_IDS[n-1]</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">⚡</span><span class="fn-label">go(id)</span><span class="fn-sub">AJAXで即時遷移</span></div>
      </div>
      <div class="flow-nodes" style="margin-top:6px;">
        <div class="fn"><span class="fn-icon">🔢</span><span class="fn-label">recNumInput</span><span class="fn-sub">番号入力ボックス</span></div>
        <div class="fn-arrow">→ Enter</div>
        <div class="fn"><span class="fn-icon">🔢</span><span class="fn-label">goTo(n)</span><span class="fn-sub">範囲クランプ後</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">⚡</span><span class="fn-label">go(id)</span><span class="fn-sub">キャッシュ活用</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>🔮</span> キャッシュ先読み戦略</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">🔮</span><span class="fn-label">prefetchAdjacent()</span><span class="fn-sub">requestIdleCallback</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">📐</span><span class="fn-label">adjacentIds(pos,3)</span><span class="fn-sub">前後各3件のID列</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">👤</span><span class="fn-label">fetchStudent(id)</span><span class="fn-sub">studentCache[sid]</span></div>
        <div class="fn-arrow">+</div>
        <div class="fn hi"><span class="fn-icon">📑</span><span class="fn-label">prefetchTab(id,tab)</span><span class="fn-sub">tabCache[sid:action]</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>📸</span> 顔写真一括取込（Gemini Vision）</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">🖼</span><span class="fn-label">B4集合写真</span><span class="fn-sub">D&amp;Dまたは選択</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">⚙</span><span class="fn-label">api/photo_import.php</span><span class="fn-sub">action=analyze</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn gem"><span class="fn-icon">✨</span><span class="fn-label">Gemini 2.0 Flash</span><span class="fn-sub">顔座標+氏名JSON</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">✂</span><span class="fn-label">GD imagecopy()</span><span class="fn-sub">tmp_cropsに保存</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🔗</span><span class="fn-label">similar_text()</span><span class="fn-sub">≥70%で自動紐付</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">✅</span><span class="fn-label">画面で確認・修正</span><span class="fn-sub">action=save</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">💾</span><span class="fn-label">uploads/photos/</span><span class="fn-sub">DB更新</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>📄</span> 家庭調査票PDF一括取込</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">📄</span><span class="fn-label">PDF（26ページ）</span><span class="fn-sub">1ページ=1生徒</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">🌐</span><span class="fn-label">PDF.js v4.4.168</span><span class="fn-sub">ブラウザ側描画</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🖼</span><span class="fn-label">&lt;canvas&gt;→JPEG Blob</span><span class="fn-sub">ページ→画像変換</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">📡</span><span class="fn-label">fetch × 生徒数</span><span class="fn-sub">出席番号順でマッチ</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">⚙</span><span class="fn-label">api/survey.php</span><span class="fn-sub">action=upload</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">💾</span><span class="fn-label">uploads/survey/</span><span class="fn-sub">{surveyKey}.jpg</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>🚀</span> ローカル → GitHub → さくら デプロイ</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">💻</span><span class="fn-label">ローカルPC</span><span class="fn-sub">C:\xampp\htdocs\karte</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">📝</span><span class="fn-label">git add . &amp;&amp; commit</span><span class="fn-sub">PowerShell</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🐙</span><span class="fn-label">git push origin master</span><span class="fn-sub">github.com/joom31628103/karte</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">🌸</span><span class="fn-label">SSH git pull</span><span class="fn-sub">opened@opened.sakura.ne.jp</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">✅</span><span class="fn-label">即時反映</span><span class="fn-sub">~/www/karte</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>📜</span> 操作履歴の記録・表示</div>
      <div class="flow-nodes">
        <div class="fn hi"><span class="fn-icon">✏</span><span class="fn-label">書き込み操作</span><span class="fn-sub">add/update/delete/save</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">📝</span><span class="fn-label">logActivity()</span><span class="fn-sub">api/karte.php</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">🗄</span><span class="fn-label">activity_log</span><span class="fn-sub">自動CREATE IF NOT EXISTS</span></div>
        <div class="fn-arrow">→ 履歴タブ開く</div>
        <div class="fn"><span class="fn-icon">📡</span><span class="fn-label">list_history API</span><span class="fn-sub">GET action=list_history</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">📜</span><span class="fn-label">時系列表示</span><span class="fn-sub">日付セパレータ+アイコン</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>🙈</span> 情報を隠す / 展開（生徒ヘッダー折りたたみ）</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">👆</span><span class="fn-label">「情報を隠す」ボタン</span><span class="fn-sub">fm-header-toggle</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">🎞</span><span class="fn-label">max-height CSSアニメ</span><span class="fn-sub">.collapsed → 0</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">💾</span><span class="fn-label">localStorage保存</span><span class="fn-sub">karteHeaderCollapsed</span></div>
      </div>
      <div class="flow-nodes" style="margin-top:6px;">
        <div class="fn"><span class="fn-icon">🔄</span><span class="fn-label">DOMContentLoaded</span><span class="fn-sub">initStudentHeader()</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn"><span class="fn-icon">📦</span><span class="fn-label">localStorage読み込み</span><span class="fn-sub">'1'なら折りたたみ復元</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">✅</span><span class="fn-label">ボタンラベル同期</span><span class="fn-sub">updateHeaderBtn()</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>💾</span> 最終閲覧状態の記憶・復元</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">👆</span><span class="fn-label">タブ切替</span><span class="fn-sub">karte_detail.php</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">💾</span><span class="fn-label">saveLastState()</span><span class="fn-sub">localStorage に保存</span></div>
        <div class="fn-arrow">→ 再ログイン後</div>
        <div class="fn"><span class="fn-icon">🏠</span><span class="fn-label">home.php</span><span class="fn-sub">前回の続きバー表示</span></div>
        <div class="fn-arrow">→ クリック</div>
        <div class="fn hi"><span class="fn-icon">📋</span><span class="fn-label">karte_detail.php</span><span class="fn-sub">前回タブを自動復元</span></div>
      </div>
    </div>

    <div class="flow-item">
      <div class="flow-title"><span>🖼</span> 顔写真表示の優先順位（karte_detail.php）</div>
      <div class="flow-nodes">
        <div class="fn"><span class="fn-icon">🔍</span><span class="fn-label">students.gakno あり？</span><span class="fn-sub">gakuseki紐付確認</span></div>
        <div class="fn-arrow">→</div>
        <div class="fn hi"><span class="fn-icon">1️⃣</span><span class="fn-label">gakuseki.photo</span><span class="fn-sub">優先</span></div>
        <div class="fn-arrow">→ なければ</div>
        <div class="fn hi"><span class="fn-icon">2️⃣</span><span class="fn-label">students.photo</span><span class="fn-sub">フォールバック</span></div>
        <div class="fn-arrow">→ なければ</div>
        <div class="fn"><span class="fn-icon">👤</span><span class="fn-label">デフォルトアイコン</span><span class="fn-sub">プレースホルダー</span></div>
      </div>
    </div>

  </div>
</div>

<!-- ===== TAB: SECURITY ===== -->
<div class="tab-pane" id="tab-security">
  <div class="sec-grid">

    <div class="sec-card">
      <div class="sec-head" style="color:#93c5fd;">🔐 セッション管理 <small style="font-weight:400;font-size:.72rem;color:#6e7681;">config.php: startSession()</small></div>
      <div class="sec-body">
        Cookie設定: <code>HttpOnly=true</code>, <code>SameSite=Strict</code>, HTTPS時は <code>Secure=true</code><br>
        <code>use_strict_mode=1</code>, <code>use_only_cookies=1</code><br>
        セッション名: <code>karte_session</code><br>
        初回アクセス時: <code>session_regenerate_id(true)</code><br>
        タイムアウト: <code>gc_maxlifetime=7200</code>（2時間）<br>
        タイムアウト検出: <code>requireLogin()</code> 内で <code>_last_activity</code> チェック
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-head" style="color:#86efac;">🛡 CSRF対策 <small style="font-weight:400;font-size:.72rem;color:#6e7681;">config.php</small></div>
      <div class="sec-body">
        生成: <code>generateCsrfToken()</code> → <code>bin2hex(random_bytes(32))</code><br>
        検証: <code>verifyCsrfToken($token)</code> → <code>hash_equals()</code>（タイミング攻撃対策）<br>
        適用範囲: 全POSTエンドポイント（api/students.phpのJSON POSTを除く）<br>
        画面での送信: <code>&lt;input type="hidden" name="csrf_token"&gt;</code> または fetchのFormData
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-head" style="color:#fcd34d;">🚦 ログイン試行制限 <small style="font-weight:400;font-size:.72rem;color:#6e7681;">config.php</small></div>
      <div class="sec-body">
        上限: <code>LOGIN_MAX_ATTEMPTS = 10</code>回<br>
        ロック時間: <code>LOGIN_LOCKOUT_SEC = 900</code>（15分）<br>
        記録テーブル: <code>login_attempts</code>（ip_address, success, attempted_at）<br>
        IP取得: CF-Connecting-IP → X-Forwarded-For → REMOTE_ADDR の順<br>
        レコード整理: ランダム（1/20確率）で1日以上前のレコードを削除
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-head" style="color:#f9a8d4;">📤 セキュリティヘッダー <small style="font-weight:400;font-size:.72rem;color:#6e7681;">config.php: sendSecurityHeaders()</small></div>
      <div class="sec-body">
        <code>X-Frame-Options: SAMEORIGIN</code><br>
        <code>X-Content-Type-Options: nosniff</code><br>
        <code>X-XSS-Protection: 1; mode=block</code><br>
        <code>Referrer-Policy: strict-origin-when-cross-origin</code><br>
        <code>X-Powered-By</code> を削除
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-head" style="color:#d8b4fe;">🔑 Gemini APIキー保護</div>
      <div class="sec-body">
        <code>config.local.php</code> に分離（<code>.gitignore</code> で除外）<br>
        GitHubには <code>config.php</code> のみコミット（<code>GEMINI_API_KEY</code> は空文字）<br>
        さくら本番へは FTP で手動アップロード<br>
        GitHub Secret Scanning: コミット時にキーが含まれていると自動検出・ブロック
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-head" style="color:#67e8f9;">📁 ファイルアップロード保護</div>
      <div class="sec-body">
        MIMEチェック: <code>exif_imagetype()</code> で実際の中身を検証（拡張子詐称対策）<br>
        uploads/ 配下の <code>.htaccess</code>: PHPファイル実行禁止<br>
        ファイル名正規化: <code>preg_replace('/[^a-zA-Z0-9_\-]/', '_', ...)</code><br>
        サイズ上限: 写真=4MB, 調査票=10MB, B4集合=20MB<br>
        gitignore: uploads/photos/*, uploads/survey/*, uploads/tmp_crops/*
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-head" style="color:#fca5a5;">💉 SQLインジェクション対策</div>
      <div class="sec-body">
        主要API: <code>Prepared Statement</code>（mysqli prepare + bind_param）<br>
        <code>ps()</code> ヘルパー関数: api/karte.php・gakuseki.php に実装<br>
        一部（api/students.php）: <code>real_escape_string()</code> を使用<br>
        ユーザー入力のHTMLエスケープ: 表示時に <code>htmlspecialchars()</code>
      </div>
    </div>

    <div class="sec-card">
      <div class="sec-head" style="color:#fdba74;">🌐 環境別DB切替（config.php）</div>
      <div class="sec-body">
        判定: <code>SERVER_ADDR</code> がプライベートIP / ループバック → ローカル<br>
        ローカル: <code>localhost / root / (空) / karte_db</code><br>
        本番 (さくら): <code>mysql3115.db.sakura.ne.jp / opened_karte_db / Yatto_2026 / opened_karte_db</code><br>
        DB未初期化時: <code>setup.php</code> へリダイレクト（AJAXはJSONでエラー返却）
      </div>
    </div>

  </div>
</div>

<script>
function showTab(id) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  event.currentTarget.classList.add('active');
}
</script>
</body>
</html>
