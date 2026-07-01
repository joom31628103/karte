<?php
$conn = new mysqli('mysql3115.db.sakura.ne.jp','opened_karte_db','Yatto_2026','opened_karte_db');
$conn->set_charset('utf8mb4');

$alters = [
  "ALTER TABLE students ADD COLUMN IF NOT EXISTS gakno VARCHAR(20) DEFAULT NULL",
  "ALTER TABLE students ADD COLUMN IF NOT EXISTS memo_posi TEXT",
  "ALTER TABLE students ADD COLUMN IF NOT EXISTS memo_nega TEXT",
  "ALTER TABLE students ADD COLUMN IF NOT EXISTS memo_main TEXT",
  "ALTER TABLE karte_records ADD COLUMN IF NOT EXISTS nendo INT DEFAULT NULL",
  "ALTER TABLE karte_attendance ADD COLUMN IF NOT EXISTS nendo INT DEFAULT NULL",
  "ALTER TABLE karte_interviews ADD COLUMN IF NOT EXISTS nendo INT DEFAULT NULL",
];

echo "<pre>\n";
foreach ($alters as $sql) {
    $label = trim($sql);
    if ($conn->query($sql)) {
        echo "OK: $label\n";
    } else {
        echo "ERR [{$conn->errno}]: {$conn->error}\n";
    }
}

// Check columns exist
$r = $conn->query("SHOW COLUMNS FROM students");
echo "\nstudents columns:\n";
while ($row = $r->fetch_assoc()) echo "  {$row['Field']}\n";

echo "\nDONE\n</pre>\n";
@unlink(__FILE__);
