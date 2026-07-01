<?php
// This is a one-time migration script - delete after use
$conn = new mysqli('mysql3115.db.sakura.ne.jp','opened_karte_db','Yatto_2026','opened_karte_db');
$conn->set_charset('utf8mb4');

$sqls = [
  "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS gakuseki (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gakno VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL DEFAULT '',
    furigana VARCHAR(100) DEFAULT '',
    seibetu VARCHAR(10) DEFAULT '',
    birthday DATE DEFAULT NULL,
    yuubin VARCHAR(10) DEFAULT '',
    jyusyo TEXT,
    hogosya VARCHAR(100) DEFAULT '',
    hogokana VARCHAR(100) DEFAULT '',
    zokugara VARCHAR(20) DEFAULT '',
    tel1 VARCHAR(50) DEFAULT '',
    tel2 VARCHAR(50) DEFAULT '',
    nyunendo INT DEFAULT NULL,
    nyugaku DATE DEFAULT NULL,
    sotsugyo DATE DEFAULT NULL,
    gakuseki_status VARCHAR(20) DEFAULT '',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "CREATE TABLE IF NOT EXISTS student_nendo (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gakno VARCHAR(20) NOT NULL,
    nendo INT NOT NULL,
    gakunen INT DEFAULT NULL,
    class_no VARCHAR(10) DEFAULT '',
    bango INT DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    sinkyu VARCHAR(20) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_gakno_nendo (gakno, nendo),
    INDEX idx_nendo (nendo)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

  "ALTER TABLE students ADD COLUMN IF NOT EXISTS gakno VARCHAR(20) DEFAULT NULL",
  "ALTER TABLE students ADD COLUMN IF NOT EXISTS memo_posi TEXT",
  "ALTER TABLE students ADD COLUMN IF NOT EXISTS memo_nega TEXT",
  "ALTER TABLE students ADD COLUMN IF NOT EXISTS memo_main TEXT",
  "ALTER TABLE karte_records ADD COLUMN IF NOT EXISTS nendo INT DEFAULT NULL",
  "ALTER TABLE karte_attendance ADD COLUMN IF NOT EXISTS nendo INT DEFAULT NULL",
  "ALTER TABLE karte_interviews ADD COLUMN IF NOT EXISTS nendo INT DEFAULT NULL",
];

echo "<pre>\n";
foreach ($sqls as $sql) {
    $label = substr(trim($sql), 0, 60);
    if ($conn->query($sql)) {
        echo "OK: $label\n";
    } else {
        echo "ERR [{$conn->errno}]: {$conn->error}\n  SQL: $label\n";
    }
}
echo "\nDONE\n</pre>\n";
// Self-delete
@unlink(__FILE__);
echo "<p>スクリプトを削除しました。</p>\n";
