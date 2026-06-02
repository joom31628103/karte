<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
startSession();
$isLocal   = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
$isTeacher = (($_SESSION['teacher_id'] ?? 0) > 0);
$hasToken  = (($_GET['token'] ?? '') === 'karte2026setup');
if (!$isLocal && !$isTeacher && !$hasToken) {
    http_response_code(403);
    die('このページへのアクセスは許可されていません。');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) die('DB接続エラー: ' . $conn->connect_error);
$conn->query("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db(DB_NAME);
$conn->set_charset('utf8mb4');

$sqls = [
"CREATE TABLE IF NOT EXISTS teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL DEFAULT '',
    furigana VARCHAR(100) DEFAULT '',
    class_name VARCHAR(50) DEFAULT '',
    seat_number INT DEFAULT NULL,
    gender VARCHAR(10) DEFAULT '',
    birthday DATE DEFAULT NULL,
    address TEXT DEFAULT '',
    parent_name VARCHAR(100) DEFAULT '',
    phone VARCHAR(50) DEFAULT '',
    notes TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS karte_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    record_date DATE NOT NULL,
    record_type VARCHAR(30) NOT NULL DEFAULT '',
    content TEXT NOT NULL DEFAULT '',
    teacher VARCHAR(100) DEFAULT '',
    next_action TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_date (record_date)
)",
"CREATE TABLE IF NOT EXISTS karte_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    att_date DATE NOT NULL,
    att_type VARCHAR(20) NOT NULL DEFAULT '',
    reason TEXT DEFAULT '',
    parent_contacted VARCHAR(10) DEFAULT '未',
    notes TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id)
)",
"CREATE TABLE IF NOT EXISTS karte_interviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(10) NOT NULL,
    interview_date DATE NOT NULL,
    interview_type VARCHAR(50) DEFAULT '',
    participants VARCHAR(200) DEFAULT '',
    content TEXT DEFAULT '',
    next_action TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id)
)",
];

$errors = []; $ok = 0;
foreach ($sqls as $sql) {
    if ($conn->query($sql)) $ok++;
    else $errors[] = $conn->error;
}

// デフォルト教師アカウントがなければ作成
$existing = $conn->query("SELECT COUNT(*) AS c FROM teachers")->fetch_assoc()['c'];
$defaultCreated = false;
if ($existing == 0) {
    $hash = password_hash('teacher123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO teachers (username,password,display_name) VALUES ('admin','$hash','管理者')");
    $defaultCreated = true;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>生徒カルテ セットアップ</title>
<style>
body { font-family: 'Hiragino Sans','Yu Gothic UI',sans-serif; background:#0f0a1e; color:#fff; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
.box { background:rgba(255,255,255,.96); color:#1e293b; border-radius:20px; padding:40px; max-width:480px; width:90%; box-shadow:0 40px 100px rgba(0,0,0,.4); }
h1 { font-size:1.4rem; margin-bottom:20px; }
.ok { color:#16a34a; font-weight:700; }
.err { color:#dc2626; font-weight:700; }
.info { background:#f1f5f9; border-radius:12px; padding:16px; margin:16px 0; font-size:.88rem; line-height:1.7; }
.btn { display:inline-block; margin-top:20px; padding:12px 28px; background:linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff; border-radius:12px; text-decoration:none; font-weight:700; }
</style>
</head>
<body>
<div class="box">
<h1>生徒カルテ セットアップ</h1>
<?php if (empty($errors)): ?>
<p class="ok">✓ テーブルを <?= $ok ?> 件作成・確認しました。</p>
<?php if ($defaultCreated): ?>
<div class="info">
<strong>初期アカウントを作成しました</strong><br>
ユーザー名: <code>admin</code><br>
パスワード: <code>teacher123</code><br>
<span style="color:#dc2626">※ログイン後すぐにパスワードを変更してください。</span>
</div>
<?php else: ?>
<p style="color:#64748b;font-size:.88rem">既存のアカウントは変更されていません。</p>
<?php endif; ?>
<?php else: ?>
<p class="err">エラーが発生しました:</p>
<ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
<?php endif; ?>
<a href="/karte/index.php" class="btn">ログイン画面へ →</a>
</div>
</body>
</html>
