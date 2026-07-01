<?php
require_once 'config.php';
requireLogin();
sendSecurityHeaders();

$conn = getDB();
$tid  = (int)$_SESSION['teacher_id'];

$msg     = '';
$msgType = '';

/* ── POST処理 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'トークンエラー。ページを再読み込みしてください。'; $msgType = 'error';
    } else {
        $act = $_POST['action'] ?? '';

        /* ── ID変更 ── */
        if ($act === 'change_username') {
            $new_un  = trim($_POST['new_username'] ?? '');
            $cur_pw  = $_POST['current_password_u'] ?? '';
            if ($new_un === '') {
                $msg = '新しいIDを入力してください。'; $msgType = 'error';
            } elseif (mb_strlen($new_un) > 64) {
                $msg = 'IDは64文字以内で入力してください。'; $msgType = 'error';
            } else {
                $row = $conn->query("SELECT password FROM teachers WHERE id=$tid")->fetch_assoc();
                if (!password_verify($cur_pw, $row['password'])) {
                    $msg = '現在のパスワードが正しくありません。'; $msgType = 'error';
                } else {
                    // 重複チェック
                    $stmt = $conn->prepare('SELECT id FROM teachers WHERE username=? AND id<>?');
                    $stmt->bind_param('si', $new_un, $tid);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $msg = 'そのIDはすでに使用されています。'; $msgType = 'error';
                    } else {
                        $stmt2 = $conn->prepare('UPDATE teachers SET username=? WHERE id=?');
                        $stmt2->bind_param('si', $new_un, $tid);
                        $stmt2->execute();
                        $stmt2->close();
                        $_SESSION['teacher_username'] = $new_un;
                        $msg = 'IDを変更しました。次回ログインから新しいIDを使用してください。'; $msgType = 'ok';
                    }
                    $stmt->close();
                }
            }
        }

        /* ── パスワード変更 ── */
        elseif ($act === 'change_password') {
            $cur_pw  = $_POST['current_password_p'] ?? '';
            $new_pw  = $_POST['new_password'] ?? '';
            $conf_pw = $_POST['confirm_password'] ?? '';
            if ($new_pw === '') {
                $msg = '新しいパスワードを入力してください。'; $msgType = 'error';
            } elseif (mb_strlen($new_pw) < 6) {
                $msg = 'パスワードは6文字以上で設定してください。'; $msgType = 'error';
            } elseif ($new_pw !== $conf_pw) {
                $msg = '新しいパスワードと確認用が一致しません。'; $msgType = 'error';
            } else {
                $row = $conn->query("SELECT password FROM teachers WHERE id=$tid")->fetch_assoc();
                if (!password_verify($cur_pw, $row['password'])) {
                    $msg = '現在のパスワードが正しくありません。'; $msgType = 'error';
                } else {
                    $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
                    $stmt   = $conn->prepare('UPDATE teachers SET password=? WHERE id=?');
                    $stmt->bind_param('si', $hashed, $tid);
                    $stmt->execute();
                    $stmt->close();
                    $msg = 'パスワードを変更しました。'; $msgType = 'ok';
                }
            }
        }
    }
}

/* ── 現在の情報を取得 ── */
$me = $conn->query("SELECT username, display_name FROM teachers WHERE id=$tid")->fetch_assoc();
$firstSid = $conn->query("SELECT student_id FROM students ORDER BY class_name,seat_number,student_id LIMIT 1")->fetch_assoc()['student_id'] ?? '';
$conn->close();
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/karte/favicon.php">
  <link rel="apple-touch-icon" href="/karte/favicon.php?size=180">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>アカウント設定 — 生徒カルテ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo','Noto Sans JP',sans-serif;background:#d0d4dc;min-height:100vh;display:flex;flex-direction:column;font-size:13px;color:#1a2240;}
.fm-topbar{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);color:#fff;padding:4px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:2px solid #0f1e40;min-height:44px;}
.fm-topbar-title{font-size:1.05rem;font-weight:900;letter-spacing:.04em;color:#e8ecff;display:flex;align-items:center;gap:8px;}
.fm-topbar-title .dot{width:8px;height:8px;border-radius:50%;background:#6ee7b7;display:inline-block;}
.fm-topbar-right{display:flex;gap:8px;align-items:center;}
.fm-btn-top{font-size:.78rem;color:#c4d4ff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);border-radius:4px;padding:4px 10px;cursor:pointer;text-decoration:none;font-family:inherit;}
.fm-btn-top:hover{background:rgba(255,255,255,.18);}
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
.fm-body{flex:1;display:flex;justify-content:center;padding:32px 16px;}
.panel-wrap{width:100%;max-width:480px;display:flex;flex-direction:column;gap:20px;}
.panel{background:#f0f2f8;border:1.5px solid #aab0cc;border-radius:6px;overflow:hidden;}
.panel-head{background:linear-gradient(180deg,#3b4f8a 0%,#2c3e6b 100%);padding:11px 18px;color:#e8ecff;font-size:.9rem;font-weight:800;letter-spacing:.03em;}
.panel-body{padding:20px 18px;}
.current-info{background:#e8ecff;border:1px solid #aab0cc;border-radius:4px;padding:10px 14px;margin-bottom:18px;font-size:.85rem;color:#3b4f8a;}
.current-info span{font-weight:700;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:.72rem;font-weight:700;color:#5a6080;letter-spacing:.05em;margin-bottom:4px;}
.field input{width:100%;padding:8px 10px;border:1px solid #aab0cc;border-radius:3px;font-size:.88rem;color:#1a2240;background:#fff;outline:none;transition:border-color .15s;font-family:inherit;}
.field input:focus{border-color:#546099;box-shadow:0 0 0 2px rgba(84,96,153,.2);}
.hint{font-size:.72rem;color:#7a82a0;margin-top:3px;}
.btn-save{padding:9px 24px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:3px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;}
.btn-save:hover{background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}
.msg{padding:10px 14px;border-radius:4px;font-size:.84rem;margin-bottom:16px;}
.msg.ok   {background:#dcfce7;border:1px solid #86efac;color:#166534;}
.msg.error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;}
.fm-footer{background:#1a2a55;border-top:1px solid #0f1e40;padding:6px 16px;text-align:center;}
.fm-footer p{font-size:.7rem;color:#6a7a9a;}
@media(max-width:480px){.fm-body{padding:16px 12px;}.panel-body{padding:16px 14px;}}
</style>
</head>
<body>

<div class="fm-topbar">
  <div class="fm-topbar-title"><span class="dot"></span>生徒カルテ — アカウント設定</div>
  <div class="fm-topbar-right">
    <div class="kebab-menu">
      <button class="kebab-btn" onclick="toggleKebab(event)" title="メニュー"><span></span><span></span><span></span></button>
      <div class="kebab-dropdown" id="kebabDropdown">
        <?php if($firstSid):?><a href="/karte/karte_detail.php?id=<?= urlencode($firstSid) ?>">🏫 生徒情報</a><?php endif;?>
        <?php if($firstSid):?><a href="/karte/karte_detail.php?id=<?= urlencode($firstSid) ?>&list=1">📋 一覧表示</a><?php endif;?>
        <a href="/karte/home.php">🏠 HOME</a>
        <?php if($firstSid):?><a href="/karte/karte_card.php?id=<?= urlencode($firstSid) ?>">🖨 印刷・PDF</a><?php endif;?>
        <a href="/karte/gakuseki.php">📚 学籍管理</a>
        <a href="/karte/student_manager.php">👥 生徒管理</a>
        <a href="/karte/photo_import.php">📸 写真取込</a>
      <a href="/karte/survey_import.php">📋 調査票取込</a>
      <a href="/karte/structure.php">🗺 構造図</a>
      <a href="/karte/backup.php">🗄️ バックアップ</a>
      <a href="/karte/sync.php">🔄 DB同期</a>
        <a class="current-page">⚙ アカウント</a>
        <a href="/karte/logout.php">🚪 ログアウト</a>
      </div>
    </div>
  </div>
</div>

<div class="fm-body">
  <div class="panel-wrap">

    <?php if ($msg): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ── ID変更 ── -->
    <div class="panel">
      <div class="panel-head">👤 ログインID を変更</div>
      <div class="panel-body">
        <div class="current-info">現在のID：<span><?= htmlspecialchars($me['username']) ?></span>
          <?php if ($me['display_name']): ?>
          　表示名：<span><?= htmlspecialchars($me['display_name']) ?></span>
          <?php endif; ?>
        </div>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="change_username">
          <div class="field">
            <label>新しいID</label>
            <input type="text" name="new_username" required maxlength="64" autocomplete="username"
                   placeholder="新しいログインIDを入力">
          </div>
          <div class="field">
            <label>現在のパスワード（確認用）</label>
            <input type="password" name="current_password_u" required autocomplete="current-password">
            <div class="hint">IDを変更するには現在のパスワードが必要です</div>
          </div>
          <button type="submit" class="btn-save">IDを変更する</button>
        </form>
      </div>
    </div>

    <!-- ── パスワード変更 ── -->
    <div class="panel">
      <div class="panel-head">🔑 パスワードを変更</div>
      <div class="panel-body">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="change_password">
          <div class="field">
            <label>現在のパスワード</label>
            <input type="password" name="current_password_p" required autocomplete="current-password">
          </div>
          <div class="field">
            <label>新しいパスワード</label>
            <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
            <div class="hint">6文字以上で設定してください</div>
          </div>
          <div class="field">
            <label>新しいパスワード（確認）</label>
            <input type="password" name="confirm_password" required autocomplete="new-password">
          </div>
          <button type="submit" class="btn-save">パスワードを変更する</button>
        </form>
      </div>
    </div>

  </div>
</div>

<div class="fm-footer"><p>生徒カルテ システム</p></div>
<script>
function toggleKebab(e){e.stopPropagation();document.getElementById('kebabDropdown').classList.toggle('open');}
document.addEventListener('click',function(){const d=document.getElementById('kebabDropdown');if(d)d.classList.remove('open');});
</script>
</body>
</html>
