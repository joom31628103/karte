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
  <link rel="icon" type="image/png" sizes="32x32" href="/karte/icon-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/karte/icon-180.png">
  <link rel="manifest" href="/karte/manifest.json">
  <meta name="theme-color" content="#1a2a55">
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
.app-switcher{position:relative;}
.app-switcher-btn{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);color:#e8ecff;border-radius:6px;padding:6px 10px;cursor:pointer;line-height:1;font-family:inherit;display:flex;align-items:center;justify-content:center;width:38px;height:34px;}
.app-switcher-btn:hover{background:rgba(255,255,255,.25);}
.app-switcher-dropdown{display:none;position:absolute;top:calc(100% + 6px);right:0;background:linear-gradient(180deg,#2c3e6b,#1a2a55);border:1px solid rgba(255,255,255,.2);border-radius:8px;min-width:170px;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,.4);overflow:hidden;}
.app-switcher-dropdown.open{display:block;}
.app-switcher-dropdown a,.app-switcher-dropdown span{display:block;width:100%;padding:10px 16px;color:#e8ecff;text-decoration:none;font-size:.85rem;border-bottom:1px solid rgba(255,255,255,.08);box-sizing:border-box;}
.app-switcher-dropdown a:last-child,.app-switcher-dropdown span:last-child{border-bottom:none;}
.app-switcher-dropdown a:hover{background:rgba(255,255,255,.15);}
.app-switcher-dropdown .current-page{color:#6a7a99;cursor:default;}
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
    <div class="app-switcher">
      <button class="app-switcher-btn" onclick="toggleAppSwitcher(event)" title="アプリ切替">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/></svg>
      </button>
      <div class="app-switcher-dropdown" id="appSwitcherDropdown">
        <a href="https://opened.sakura.ne.jp/mytube/home.php"><svg width="16" height="16" viewBox="0 0 24 24" style="vertical-align:-3px"><rect x="1" y="4" width="22" height="16" rx="5" fill="#FF0000"/><path d="M10 8.5v7l6-3.5z" fill="#fff"/></svg> MyTube</a>
        <span class="current-page"><svg width="16" height="16" viewBox="0 0 1024 1024" style="vertical-align:-3px"><defs><linearGradient id="karteg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#2F83FF"/><stop offset="100%" stop-color="#0E67E8"/></linearGradient><clipPath id="karteclip"><circle cx="512" cy="432" r="300"/></clipPath></defs><rect x="32" y="32" width="960" height="960" rx="176" fill="url(#karteg)"/><circle cx="512" cy="432" r="300" fill="#fff"/><g clip-path="url(#karteclip)"><circle cx="512" cy="382" r="142" fill="url(#karteg)"/><rect x="292" y="556" width="440" height="320" rx="150" fill="url(#karteg)"/></g><rect x="262" y="792" width="500" height="66" rx="33" fill="#fff"/><rect x="262" y="900" width="500" height="66" rx="33" fill="#fff"/></svg> 生徒カルテ</span>
        <a href="https://opened.sakura.ne.jp/diary/"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAABmJLR0QA/wD/AP+gvaeTAAAD8UlEQVRIibWWXWwUVRTHz92Z3em2hS6koNkW+XKFFLCEiHykiGhqFGugTy3wQPCFGGP0RY2JiTEqCT5oiPoAiQnRRKM2Kr74kTa6JRKglFZbLLVJyy40dBXs6q67Oztz79+HmZ25M7vbGmNvJjuTnbm///mac4YBoIVcgQWlE5HqXAlQ7xiNp2AKIhARQNYFoezkeG0HAFSr0Z71yoao32KGEueZj9E/Ye+GtdH9ITgnS7nSLYXRG51K17YaWcAWPD9JfjqVI2gOOkCGwKtfmbMZvYLA5K0yOsrp1d0CgaBf65uJH2s7cPybs6N+AS6q0Wl+OhEIxm8jerJfFP++cTN1+IVTv/+R8QhIuXPCTK7lVYNW+gfQk3EIE5yT4Plc/uerCb+ABCJXAhWF4RYQiEDG7XGRnSHBSXAIHiCsXxv1Csh0ybz5isq+KCb7Ibhz7H9kS9MdS30e/Cc6AUQ8PWWmp0p0E4I/d2SvExe15ICHHmB0pC3QEGaOcKlKnRMR4btRPnjN1JNxBw3BWzfGNres8gu4dhEACmt4fJOySHpjyhoWCJQt4OKVGePWmByfzo498nOq9Lxb0Zk87XtPl0q2atD0xA+O7RA8sGjFppaYLBBwDUQZYj66KKSNmWELTYKTMLU17T5nPUmuQK92iwiAnvgevGihITgLN6rLN/tCKTc/F2Mz5nILMHPF6QvWy2U5EVrdDsZ8AnKSXT6A3euU2mCpguATJxCNXRq8bOSd6FOoXm3aXj6+VInu9Es0hNk7B4J1mt8ceb2UygxKxaOtfJgxtXw6uh7Ir1I6Jza+kp8j7U8/FLp3e/s98aGrEwkIk7TFoZW7ne0VBao2YW8XsYvq857PmpeId187Ops13v7ownBhC6lhaWq4S+qm89Pte2r6l/07oydf7uzpHTnxQe+xpx6INjdL7bVKDv4lHYZ+Z3HoxSePBlXlref3nekb6npzJLtkp7vduzweyAPEHZDeLlJ/u+/4s+0DI4n3e84S0ekzA5naFpLKpLqAnOdSOfndyk7fd5d4cGvs+nRq/Ncpw+QTyT+ZFpE9rhwi5vRNuH2pPGiRv851H9xGRF0dO7o6dnwdH54NrfHF01fatgd3L2PyAJFLVm7/eaVxdHxKL5rWrpOf/FhY3CrTFYbmCKsg0BZTdq0lqbN541MKWy60+noqe+mnCQBffDtwZXKW1USk2ODQVjTUq7IAczJvcJzqzVxOiiL3DF2364EAfvP86e7HWj/88qK6NLZsXZsS1Cx6nUb3r6JHN7Do8kiNFqwgYK1coagXzUr1Zq/XT3y6oqmx+4ldmkSx86kE6mo1JeD5evQL/O9rwb+u/wH6/11CAv6mWwAAAABJRU5ErkJggg==" alt="" width="16" height="16" style="vertical-align:-3px;border-radius:3px"> 日記カレンダー</a>
        <a href="https://opened.sakura.ne.jp/tasks/">✅ Googleタスク</a>
        <a href="https://opened.sakura.ne.jp/golf/"><svg width="16" height="16" viewBox="0 0 100 100" style="vertical-align:-3px"><rect x="3" y="3" width="94" height="94" rx="22" fill="#16a34a"/><rect x="46" y="18" width="4" height="54" rx="2" fill="#fff"/><path d="M50 20 L50 38 L70 29 Z" fill="#fff"/><ellipse cx="48" cy="76" rx="20" ry="6" fill="#fff"/></svg> ゴルフ</a>
        <a href="https://opened.sakura.ne.jp/keepme/home.php"><svg width="16" height="16" viewBox="0 0 32 32" style="vertical-align:-3px"><rect width="32" height="32" rx="6" fill="#f9ab00"/><path d="M16 6c-3.3 0-6 2.7-6 6 0 2 1 3.7 2.4 4.8V19h7.2v-2.2C21 15.7 22 14 22 12c0-3.3-2.7-6-6-6z" fill="#ffffff"/><rect x="13.5" y="20" width="5" height="1.6" rx="0.8" fill="#ffffff"/><rect x="14.2" y="22.2" width="3.6" height="1.4" rx="0.7" fill="#ffffff"/></svg> KeepMe</a>
      </div>
    </div>
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
function toggleKebab(e){e.stopPropagation();document.getElementById('appSwitcherDropdown').classList.remove('open');document.getElementById('kebabDropdown').classList.toggle('open');}
function toggleAppSwitcher(e){e.stopPropagation();document.getElementById('kebabDropdown').classList.remove('open');document.getElementById('appSwitcherDropdown').classList.toggle('open');}
document.addEventListener('click',function(){
  const d=document.getElementById('kebabDropdown');if(d)d.classList.remove('open');
  const a=document.getElementById('appSwitcherDropdown');if(a)a.classList.remove('open');
});
</script>
</body>
</html>
