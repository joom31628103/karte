<?php
/**
 * 生徒カルテ セットアップ / メンテナンス
 * アクセス条件: localhost OR ログイン済み OR ?token=karte2026setup
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
startSession();

$isLocal   = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
$isTeacher = (($_SESSION['teacher_id'] ?? 0) > 0);

// トークンは環境変数またはランダム生成（固定文字列は使用しない）
$setupToken = getenv('KARTE_SETUP_TOKEN') ?: '';
$hasToken   = $setupToken && hash_equals($setupToken, ($_GET['token'] ?? ''));

if (!$isLocal && !$isTeacher && !$hasToken) {
    http_response_code(403);
    die('このページへのアクセスは許可されていません。ログイン後にアクセスしてください。');
}

/* ── DB 接続（DB が未存在でも接続） ── */
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) die('DB接続エラー: ' . $conn->connect_error);
$conn->query("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db(DB_NAME);
$conn->set_charset('utf8mb4');

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   1. テーブル作成 / 確認
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
$tables = [

    'teachers' => "CREATE TABLE IF NOT EXISTS teachers (
        id           INT PRIMARY KEY AUTO_INCREMENT,
        username     VARCHAR(50)  UNIQUE NOT NULL,
        password     VARCHAR(255) NOT NULL,
        display_name VARCHAR(100) DEFAULT '',
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'students' => "CREATE TABLE IF NOT EXISTS students (
        id           INT PRIMARY KEY AUTO_INCREMENT,
        student_id   VARCHAR(10) UNIQUE NOT NULL,
        name         VARCHAR(100) NOT NULL DEFAULT '',
        furigana     VARCHAR(100) DEFAULT '',
        class_name   VARCHAR(50)  DEFAULT '',
        seat_number  INT DEFAULT NULL,
        gender       VARCHAR(10)  DEFAULT '',
        birthday     DATE DEFAULT NULL,
        address      TEXT,
        parent_name  VARCHAR(100) DEFAULT '',
        phone        VARCHAR(50)  DEFAULT '',
        notes        TEXT,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'karte_records' => "CREATE TABLE IF NOT EXISTS karte_records (
        id          INT PRIMARY KEY AUTO_INCREMENT,
        student_id  VARCHAR(10) NOT NULL,
        record_date DATE NOT NULL,
        record_type VARCHAR(30) NOT NULL DEFAULT '',
        content     TEXT NOT NULL,
        teacher     VARCHAR(100) DEFAULT '',
        next_action TEXT,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_date    (record_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'karte_attendance' => "CREATE TABLE IF NOT EXISTS karte_attendance (
        id               INT PRIMARY KEY AUTO_INCREMENT,
        student_id       VARCHAR(10) NOT NULL,
        att_date         DATE NOT NULL,
        att_type         VARCHAR(20) NOT NULL DEFAULT '',
        reason           TEXT,
        parent_contacted VARCHAR(10) DEFAULT '未',
        notes            TEXT,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'login_attempts' => "CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT PRIMARY KEY AUTO_INCREMENT,
        ip_address   VARCHAR(45) NOT NULL,
        success      TINYINT(1)  NOT NULL DEFAULT 0,
        attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'karte_interviews' => "CREATE TABLE IF NOT EXISTS karte_interviews (
        id             INT PRIMARY KEY AUTO_INCREMENT,
        student_id     VARCHAR(10) NOT NULL,
        interview_date DATE NOT NULL,
        interview_type VARCHAR(50) DEFAULT '',
        participants   VARCHAR(200) DEFAULT '',
        content        TEXT,
        next_action    TEXT,
        nendo          INT DEFAULT NULL,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'gakuseki' => "CREATE TABLE IF NOT EXISTS gakuseki (
        id              INT PRIMARY KEY AUTO_INCREMENT,
        gakno           VARCHAR(20) UNIQUE NOT NULL,
        name            VARCHAR(100) NOT NULL DEFAULT '',
        furigana        VARCHAR(100) DEFAULT '',
        seibetu         VARCHAR(10) DEFAULT '',
        birthday        DATE DEFAULT NULL,
        yuubin          VARCHAR(10) DEFAULT '',
        jyusyo          TEXT,
        hogosya         VARCHAR(100) DEFAULT '',
        hogokana        VARCHAR(100) DEFAULT '',
        zokugara        VARCHAR(20) DEFAULT '',
        tel1            VARCHAR(50) DEFAULT '',
        tel2            VARCHAR(50) DEFAULT '',
        nyunendo        INT DEFAULT NULL,
        nyugaku         DATE DEFAULT NULL,
        sotsugyo        DATE DEFAULT NULL,
        gakuseki_status  VARCHAR(20) DEFAULT '',
        shusshin_chugaku VARCHAR(100) DEFAULT '',
        hogosya_yuubin   VARCHAR(10) DEFAULT '',
        hogosya_jyusyo   TEXT,
        hogosya_addr1    VARCHAR(200) DEFAULT '',
        hogosya_addr2    VARCHAR(200) DEFAULT '',
        notes            TEXT,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'activity_log' => "CREATE TABLE IF NOT EXISTS activity_log (
        id           INT PRIMARY KEY AUTO_INCREMENT,
        teacher_id   INT NOT NULL DEFAULT 0,
        teacher_name VARCHAR(100) DEFAULT '',
        student_id   VARCHAR(10) NOT NULL,
        action_type  VARCHAR(50) NOT NULL,
        detail       TEXT,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'student_nendo' => "CREATE TABLE IF NOT EXISTS student_nendo (
        id          INT PRIMARY KEY AUTO_INCREMENT,
        gakno       VARCHAR(20) NOT NULL,
        nendo       INT NOT NULL,
        gakunen     INT DEFAULT NULL,
        class_no    VARCHAR(10) DEFAULT '',
        bango       INT DEFAULT NULL,
        teacher_id  INT DEFAULT NULL,
        sinkyu      VARCHAR(20) DEFAULT '',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_gakno_nendo (gakno, nendo),
        INDEX idx_nendo (nendo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

$tableResults = [];
foreach ($tables as $tname => $sql) {
    if ($conn->query($sql)) {
        $tableResults[$tname] = 'ok';
    } else {
        $tableResults[$tname] = 'error: ' . $conn->error;
    }
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   2. ALTER TABLE（後付カラム追加）
   ※ MySQL 5.x は ADD COLUMN IF NOT EXISTS 非対応のため
     INFORMATION_SCHEMA で存在確認してから追加
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function addColumnIfMissing(mysqli $conn, string $table, string $col, string $definition): string {
    $db = DB_NAME;
    $r  = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table' AND COLUMN_NAME='$col' LIMIT 1");
    if ($r && $r->num_rows > 0) return 'already exists';
    $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
    return $conn->error ?: 'added';
}

$alters = [
    ['students',         'gakno',    'VARCHAR(20) DEFAULT NULL'],
    ['students',         'memo_posi','TEXT'],
    ['students',         'memo_nega','TEXT'],
    ['students',         'memo_main','TEXT'],
    ['karte_records',    'nendo',    'INT DEFAULT NULL'],
    ['karte_attendance', 'nendo',    'INT DEFAULT NULL'],
    ['karte_interviews', 'nendo',    'INT DEFAULT NULL'],
    ['students',         'photo',    'VARCHAR(255) DEFAULT NULL'],
];
$alterResults = [];
foreach ($alters as [$tbl, $col, $def]) {
    $alterResults[] = "$tbl.$col: " . addColumnIfMissing($conn, $tbl, $col, $def);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   3. デフォルト教師アカウント作成
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
$existing      = (int)$conn->query("SELECT COUNT(*) AS c FROM teachers")->fetch_assoc()['c'];
$defaultCreated = false;
if ($existing === 0) {
    $hash = password_hash('teacher123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO teachers (username,password,display_name) VALUES ('admin','$hash','管理者')");
    $defaultCreated = true;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   4. パスワードリセット（POST）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
$resetMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $uname   = trim($_POST['username'] ?? 'admin');
    $newpass = trim($_POST['new_password'] ?? '');
    if (strlen($newpass) < 6) {
        $resetMsg = 'error:パスワードは6文字以上で入力してください。';
    } else {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $u    = $conn->real_escape_string($uname);
        $exists = (int)$conn->query("SELECT COUNT(*) AS c FROM teachers WHERE username='$u'")->fetch_assoc()['c'];
        if ($exists) {
            $conn->query("UPDATE teachers SET password='$hash' WHERE username='$u'");
            $resetMsg = "ok:{$uname} のパスワードを変更しました。";
        } else {
            // ユーザーが存在しなければ作成
            $conn->query("INSERT INTO teachers (username,password,display_name) VALUES ('$u','$hash','$u')");
            $resetMsg = "ok:{$uname} を新規作成しました（パスワード: {$newpass}）。";
        }
    }
}

/* ── 教師アカウント一覧 ── */
$teachers = $conn->query("SELECT id, username, display_name, created_at FROM teachers ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/karte/favicon.php">
  <link rel="apple-touch-icon" href="/karte/favicon.php?size=180">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>生徒カルテ セットアップ</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Yu Gothic UI',sans-serif;background:#0f0a1e;color:#fff;min-height:100vh;padding:32px 16px;}
.wrap{max-width:640px;margin:0 auto;display:flex;flex-direction:column;gap:20px;}
h1{font-size:1.4rem;font-weight:800;margin-bottom:4px;}
.sub{font-size:.82rem;color:rgba(255,255,255,.45);}
.card{background:rgba(255,255,255,.96);color:#1e293b;border-radius:18px;padding:24px 22px;}
.card h2{font-size:1rem;font-weight:700;margin-bottom:14px;color:#1e293b;display:flex;align-items:center;gap:7px;}
.badge-ok  {background:#dcfce7;color:#16a34a;font-size:.72rem;font-weight:700;padding:2px 9px;border-radius:20px;}
.badge-err {background:#fee2e2;color:#dc2626;font-size:.72rem;font-weight:700;padding:2px 9px;border-radius:20px;}
table{width:100%;border-collapse:collapse;font-size:.85rem;}
th{background:#f1f5f9;padding:7px 10px;text-align:left;font-weight:700;color:#475569;font-size:.78rem;}
td{padding:7px 10px;border-top:1px solid #f1f5f9;color:#1e293b;}
.ok  {color:#16a34a;font-weight:700;}
.err {color:#dc2626;font-weight:700;}
.info-box{background:#eff6ff;border:1px solid #dbeafe;border-radius:10px;padding:14px 16px;font-size:.85rem;line-height:1.8;}
.info-box code{background:#dbeafe;border-radius:4px;padding:1px 6px;font-size:.9em;}
.warn-box{background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:12px 16px;font-size:.82rem;color:#713f12;margin-top:10px;}
form .row{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}
form input[type=text],form input[type=password]{flex:1;min-width:120px;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.88rem;font-family:inherit;color:#1e293b;outline:none;}
form input:focus{border-color:#7c3aed;}
.btn{padding:9px 20px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:none;border-radius:9px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;}
.btn:hover{opacity:.88;}
.btn-link{display:inline-block;padding:11px 26px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border-radius:12px;text-decoration:none;font-weight:700;font-size:.9rem;}
.msg-ok  {color:#16a34a;font-weight:700;margin-top:10px;font-size:.88rem;}
.msg-err {color:#dc2626;font-weight:700;margin-top:10px;font-size:.88rem;}
@media(max-width:480px){.card{padding:18px 14px;}}
</style>
</head>
<body>
<div class="wrap">
  <div>
    <h1>🗂 生徒カルテ セットアップ</h1>
    <p class="sub">DB: <?= $isLocal ? DB_NAME.' @ '.DB_HOST : '*** (非表示)' ?></p>
  </div>

  <!-- テーブル作成結果 -->
  <div class="card">
    <h2>📋 テーブル確認</h2>
    <table>
      <tr><th>テーブル名</th><th>状態</th></tr>
      <?php foreach ($tableResults as $tname => $res): ?>
      <tr>
        <td><code><?= $tname ?></code></td>
        <td class="<?= $res === 'ok' ? 'ok' : 'err' ?>">
          <?= $res === 'ok' ? '✓ OK' : '✗ ' . htmlspecialchars($res) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- 教師アカウント一覧 -->
  <div class="card">
    <h2>👤 教師アカウント</h2>
    <?php if (empty($teachers)): ?>
      <p style="color:#94a3b8;font-size:.88rem;">アカウントがありません（下のフォームで作成してください）</p>
    <?php else: ?>
    <table>
      <tr><th>ID</th><th>ユーザー名</th><th>表示名</th><th>作成日</th></tr>
      <?php foreach ($teachers as $t): ?>
      <tr>
        <td><?= $t['id'] ?></td>
        <td><strong><?= htmlspecialchars($t['username']) ?></strong></td>
        <td><?= htmlspecialchars($t['display_name']) ?></td>
        <td style="color:#94a3b8;font-size:.78rem"><?= substr($t['created_at'],0,10) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if ($defaultCreated): ?>
    <div class="info-box" style="margin-top:14px;">
      <strong>初期アカウントを作成しました</strong><br>
      ユーザー名: <code>admin</code><br>
      パスワード: <code>teacher123</code>
    </div>
    <div class="warn-box">⚠ ログイン後すぐにパスワードを変更してください。</div>
    <?php endif; ?>
  </div>

  <!-- パスワードリセット -->
  <div class="card">
    <h2>🔑 パスワードリセット</h2>
    <?php
      if ($resetMsg) {
          [$type, $text] = explode(':', $resetMsg, 2);
          echo '<p class="msg_'.($type==='ok'?'ok':'err').'">'.($type==='ok'?'✓ ':'✗ ').htmlspecialchars($text).'</p>';
      }
    ?>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <div class="row">
        <input type="text"     name="username"     placeholder="ユーザー名（例: admin）" value="admin">
        <input type="password" name="new_password" placeholder="新しいパスワード（6文字以上）">
        <button type="submit" class="btn">リセット</button>
      </div>
    </form>
    <p style="margin-top:10px;font-size:.78rem;color:#94a3b8;">
      ※ユーザー名が存在しない場合は新規作成されます。
    </p>
  </div>

  <!-- ログインへ -->
  <div style="text-align:center;padding-bottom:32px;">
    <a href="/karte/index.php" class="btn-link">ログイン画面へ →</a>
  </div>
</div>
</body>
</html>
