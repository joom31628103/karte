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
    define('DB_HOST', 'mysql3115.db.sakura.ne.jp');
    define('DB_USER', 'opened_karte_db');
    define('DB_PASS', 'Yatto_2026');
    define('DB_NAME', 'opened_karte_db');
}

/* ── Gemini API ── */
// Gemini APIキーは config.local.php に記載（gitignore済み）
define('GEMINI_API_KEY', file_exists(__DIR__.'/config.local.php')
    ? (require __DIR__.'/config.local.php')
    : '');

/* ── セキュリティヘッダー送信 ── */
function sendSecurityHeaders(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header_remove('X-Powered-By');
}

/* ── DB接続（PDO + 文字コード） ── */
function getDB(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        http_response_code(500);
        die(_isAjax() ? json_encode(['success'=>false,'error'=>'DB接続エラー']) : 'DB接続エラー');
    }
    $conn->set_charset('utf8mb4');
    try { $selected = $conn->select_db(DB_NAME); } catch (Exception $e) { $selected = false; }
    if (!$selected) {
        $conn->close();
        if (_isAjax()) {
            http_response_code(500);
            die(json_encode(['success'=>false,'error'=>'DBが未初期化です']));
        } else {
            header('Location: /karte/setup.php'); exit;
        }
    }
    return $conn;
}

function _isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
           (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json');
}

/* ── セッション開始（セキュア設定） ── */
function startSession(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 7200); // 2時間

    session_name('karte_session');
    session_start();

    // セッション固定化攻撃対策：ログイン後にIDを再生成するので
    // ここでは初回アクセスから一定時間後にも再生成
    if (!isset($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

/* ── CSRFトークン生成 ── */
function generateCsrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/* ── CSRFトークン検証 ── */
function verifyCsrfToken(?string $token): bool {
    startSession();
    return !empty($_SESSION['csrf_token'])
        && !empty($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ── ログイン必須チェック ── */
function requireLogin(): void {
    startSession();
    if (empty($_SESSION['teacher_id'])) {
        if (_isAjax()) {
            http_response_code(401);
            echo json_encode(['success'=>false,'error'=>'認証が必要です']);
            exit;
        }
        header('Location: /karte/index.php');
        exit;
    }
    // セッションタイムアウトチェック（2時間）
    if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > 7200) {
        session_unset();
        session_destroy();
        header('Location: /karte/index.php?timeout=1');
        exit;
    }
    $_SESSION['_last_activity'] = time();
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   ログイン試行回数制限（ブルートフォース対策）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
define('LOGIN_MAX_ATTEMPTS', 10);   // 最大試行回数
define('LOGIN_LOCKOUT_SEC',  900);  // ロックアウト時間（秒）= 15分

function getClientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        $ip = $_SERVER[$key] ?? '';
        if ($ip) {
            // カンマ区切りの先頭を取得
            $ip = trim(explode(',', $ip)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function checkLoginRateLimit(mysqli $conn): bool {
    $ip      = $conn->real_escape_string(getClientIp());
    $cutoff  = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SEC);
    $result  = $conn->query(
        "SELECT COUNT(*) AS cnt FROM login_attempts
         WHERE ip_address='$ip' AND attempted_at > '$cutoff' AND success=0"
    );
    if (!$result) return true; // テーブル未作成なら通過
    $row = $result->fetch_assoc();
    return (int)$row['cnt'] < LOGIN_MAX_ATTEMPTS;
}

function recordLoginAttempt(mysqli $conn, bool $success): void {
    $ip  = $conn->real_escape_string(getClientIp());
    $suc = $success ? 1 : 0;
    $conn->query(
        "INSERT INTO login_attempts (ip_address, success, attempted_at)
         VALUES ('$ip', $suc, NOW())"
    );
    // 古いレコードを定期削除（1日以上前）
    if (rand(1, 20) === 1) {
        $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }
}

function getRemainingLockout(mysqli $conn): int {
    $ip     = $conn->real_escape_string(getClientIp());
    $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SEC);
    $result = $conn->query(
        "SELECT MAX(UNIX_TIMESTAMP(attempted_at)) AS last_t FROM login_attempts
         WHERE ip_address='$ip' AND attempted_at > '$cutoff' AND success=0"
    );
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    if (!$row['last_t']) return 0;
    $remaining = LOGIN_LOCKOUT_SEC - (time() - (int)$row['last_t']);
    return max(0, $remaining);
}
