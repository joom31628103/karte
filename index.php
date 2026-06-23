<?php
require_once 'config.php';
sendSecurityHeaders();
startSession();
if (isset($_SESSION['teacher_id'])) { header('Location: /karte/home.php'); exit; }

$error   = '';
$locked  = false;
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'ユーザー名とパスワードを入力してください。';
    } else {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        $conn->set_charset('utf8mb4');
        $conn->select_db(DB_NAME);

        // ── レート制限チェック ──
        if (!checkLoginRateLimit($conn)) {
            $wait    = ceil(getRemainingLockout($conn) / 60);
            $locked  = true;
            $error   = "ログイン試行回数が上限に達しました。約 {$wait} 分後に再試行してください。";
        } else {
            // ── Prepared Statement でユーザー検索 ──
            $stmt = $conn->prepare('SELECT id, username, display_name, password FROM teachers WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($password, $row['password'])) {
                recordLoginAttempt($conn, true);
                $conn->close();

                // セッション固定化攻撃対策
                session_regenerate_id(true);

                $_SESSION['teacher_id']      = $row['id'];
                $_SESSION['teacher_name']    = $row['display_name'] ?: $row['username'];
                $_SESSION['_last_activity']  = time();

                header('Location: /karte/home.php');
                exit;
            } else {
                recordLoginAttempt($conn, false);
                // 残り試行回数を計算
                $remaining = LOGIN_MAX_ATTEMPTS - 1;
                $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SEC);
                $r = $conn->query("SELECT COUNT(*) AS c FROM login_attempts WHERE ip_address='".$conn->real_escape_string(getClientIp())."' AND attempted_at>'$cutoff' AND success=0");
                if ($r) {
                    $cnt = (int)$r->fetch_assoc()['c'];
                    $left = LOGIN_MAX_ATTEMPTS - $cnt;
                    $error = 'ユーザー名またはパスワードが正しくありません。';
                    if ($left <= 3 && $left > 0) {
                        $error .= "（あと {$left} 回失敗するとロックされます）";
                    }
                } else {
                    $error = 'ユーザー名またはパスワードが正しくありません。';
                }
                $conn->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>生徒カルテ ログイン</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI','Meiryo','Noto Sans JP',sans-serif;background:#d0d4dc;min-height:100vh;display:flex;flex-direction:column;font-size:13px;color:#1a2240;}

/* トップバー */
.fm-topbar{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);padding:4px 16px;display:flex;align-items:center;gap:8px;border-bottom:2px solid #0f1e40;min-height:44px;}
.fm-topbar-title{font-size:1.05rem;font-weight:900;letter-spacing:.04em;color:#e8ecff;display:flex;align-items:center;gap:8px;}
.fm-topbar-title .dot{width:8px;height:8px;border-radius:50%;background:#6ee7b7;display:inline-block;}
.fm-topbar-sub{color:#c4d4ff;font-size:.78rem;}

/* メインエリア */
.fm-body{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 16px;}

/* ログインパネル */
.login-panel{background:#f0f2f8;border:2px solid #aab0cc;border-radius:4px;width:100%;max-width:360px;box-shadow:0 8px 32px rgba(0,0,0,.25);}
.login-panel-head{background:linear-gradient(180deg,#2c3e6b 0%,#1a2a55 100%);padding:14px 20px;text-align:center;border-radius:2px 2px 0 0;}
.login-panel-head h1{font-size:1.1rem;font-weight:800;color:#e8ecff;letter-spacing:.04em;}
.login-panel-head p{font-size:.74rem;color:#8aa0d0;margin-top:3px;}
.login-panel-body{padding:22px 20px;}

/* メッセージ */
.msg-error{background:#f8ecec;border:1px solid #c08080;color:#7a2020;padding:8px 12px;border-radius:3px;font-size:.82rem;margin-bottom:14px;}
.msg-warn{background:#fef9e0;border:1px solid #c8b060;color:#6a5010;padding:8px 12px;border-radius:3px;font-size:.82rem;margin-bottom:14px;}

/* フォーム */
.field{margin-bottom:13px;}
.field label{display:block;font-size:.72rem;font-weight:700;color:#5a6080;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;}
.field input{width:100%;padding:8px 10px;border:1px solid #aab0cc;border-radius:3px;font-size:.9rem;font-family:inherit;color:#1a2240;background:#fff;outline:none;transition:border-color .15s;}
.field input:focus{border-color:#546099;box-shadow:0 0 0 2px rgba(84,96,153,.2);}
.field input:disabled{background:#e4e7f0;color:#7a82a0;}

/* ボタン */
.btn-login{width:100%;padding:9px;background:linear-gradient(180deg,#546099 0%,#3b4f8a 100%);border:1px solid #263570;border-radius:3px;color:#fff;font-size:.92rem;font-weight:700;cursor:pointer;font-family:inherit;margin-top:4px;transition:background .15s;}
.btn-login:hover:not(:disabled){background:linear-gradient(180deg,#7b90d4 0%,#546099 100%);}
.btn-login:disabled{background:#9aa0c0;border-color:#7a80a0;cursor:not-allowed;}

/* フッター */
.fm-footer{background:#1a2a55;border-top:1px solid #0f1e40;padding:6px 16px;text-align:center;}
.fm-footer p{font-size:.7rem;color:#6a7a9a;}
</style>
</head>
<body>

<div class="fm-topbar">
  <div class="fm-topbar-title"><span class="dot"></span>生徒カルテ システム</div>
  <span class="fm-topbar-sub">担任専用・生徒記録管理</span>
</div>

<div class="fm-body">
  <div class="login-panel">
    <div class="login-panel-head">
      <h1>📋 ログイン</h1>
      <p>担任ID とパスワードを入力してください</p>
    </div>
    <div class="login-panel-body">

      <?php if ($timeout): ?>
      <div class="msg-warn">セッションの有効期限が切れました。再度ログインしてください。</div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="<?= $locked ? 'msg-warn' : 'msg-error' ?>"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <div class="field">
          <label>ユーザー名</label>
          <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 autofocus autocomplete="username" maxlength="50"
                 <?= $locked ? 'disabled' : '' ?>>
        </div>
        <div class="field">
          <label>パスワード</label>
          <input type="password" name="password" autocomplete="current-password" maxlength="128"
                 <?= $locked ? 'disabled' : '' ?>>
        </div>
        <button type="submit" class="btn-login" <?= $locked ? 'disabled' : '' ?>>
          <?= $locked ? 'ロック中...' : 'ログイン' ?>
        </button>
      </form>

    </div>
  </div>
</div>

<div class="fm-footer">
  <p>生徒カルテ — 担任専用システム</p>
</div>

</body>
</html>
