<?php
$_sv_name = $_SERVER['SERVER_NAME'] ?? '';
$_sv_addr = $_SERVER['SERVER_ADDR'] ?? '';
$_is_local = (
    $_sv_name === 'localhost' ||
    $_sv_addr === '127.0.0.1' ||
    $_sv_addr === '::1' ||
    filter_var($_sv_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
);

if ($_is_local) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'karte_db');
} else {
    // さくらインターネット本番環境
    define('DB_HOST', 'mysql3115.db.sakura.ne.jp');
    define('DB_USER', 'opened_karte_db');
    define('DB_PASS', 'Yatto_2026');
    define('DB_NAME', 'opened_karte_db');
}

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        http_response_code(500);
        die('MySQLに接続できません: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    try { $selected = $conn->select_db(DB_NAME); } catch (Exception $e) { $selected = false; }
    if (!$selected) {
        $conn->close();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(500);
            die(json_encode(['success'=>false,'error'=>'DBが未初期化です。setup.phpを実行してください']));
        } else {
            header('Location: /karte/setup.php'); exit;
        }
    }
    return $conn;
}

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }
        session_name('karte_session');
        session_start();
    }
}

function generateCsrfToken() {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    startSession();
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireLogin() {
    startSession();
    if (!isset($_SESSION['teacher_id'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(401);
            echo json_encode(['success'=>false,'error'=>'認証が必要です']); exit;
        } else {
            header('Location: /karte/index.php'); exit;
        }
    }
}
