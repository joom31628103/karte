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
body{font-family:'Hiragino Sans','Yu Gothic UI','Noto Sans JP',sans-serif;background:#0f0a1e;min-height:100vh;display:flex;align-items:center;justify-content:center;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 15% 0%,#4c1d95 0%,transparent 55%),radial-gradient(ellipse at 85% 0%,#1e3a8a 0%,transparent 55%),radial-gradient(ellipse at 50% 110%,#312e81 0%,transparent 60%);z-index:0;pointer-events:none;}
.card{position:relative;z-index:1;background:rgba(255,255,255,.96);border-radius:24px;padding:44px 40px;width:100%;max-width:400px;box-shadow:0 40px 100px rgba(0,0,0,.4);}
.logo{text-align:center;margin-bottom:28px;}
.logo-icon{width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#7c3aed,#4f46e5);display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:12px;}
.logo h1{font-size:1.35rem;font-weight:800;color:#1e293b;}
.logo p{font-size:.8rem;color:#94a3b8;margin-top:4px;}
.field{margin-bottom:16px;}
label{display:block;font-size:.78rem;font-weight:700;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;}
input[type=text],input[type=password]{width:100%;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:.95rem;font-family:inherit;color:#1e293b;outline:none;transition:border-color .2s;}
input:focus{border-color:#7c3aed;}
.error{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:10px 14px;border-radius:10px;font-size:.84rem;margin-bottom:16px;}
.warn{background:#fef9c3;border:1px solid #fde047;color:#713f12;padding:10px 14px;border-radius:10px;font-size:.84rem;margin-bottom:16px;}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:12px;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .2s;}
.btn:hover:not(:disabled){opacity:.9;}
.btn:disabled{opacity:.5;cursor:not-allowed;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">📋</div>
    <h1>生徒カルテ</h1>
    <p>担任専用・生徒記録管理システム</p>
  </div>

  <?php if ($timeout): ?>
  <div class="warn">セッションの有効期限が切れました。再度ログインしてください。</div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="<?= $locked ? 'warn' : 'error' ?>"><?= htmlspecialchars($error) ?></div>
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
    <button type="submit" class="btn" <?= $locked ? 'disabled' : '' ?>>
      <?= $locked ? 'ロック中...' : 'ログイン →' ?>
    </button>
  </form>
</div>
</body>
</html>
