<?php
require_once __DIR__.'/config.php';

$size = (int)($_GET['size'] ?? 48);
$size = in_array($size, [48, 180]) ? $size : 48;

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=3600');

$env = defined('ENV_NAME') ? ENV_NAME : 'local';
if (!$_is_local) $env = 'sakura';

$colors = [
    'work'   => ['bg' => '#2d6a10', 'body' => '#f0fff4', 'clip' => '#1a4a08', 'line' => '#2d6a10'],
    'home'   => ['bg' => '#1244a0', 'body' => '#eef4ff', 'clip' => '#0a2d6e', 'line' => '#1244a0'],
    'sakura' => ['bg' => '#a02050', 'body' => '#fff0f5', 'clip' => '#6e1035', 'line' => '#a02050'],
    'local'  => ['bg' => '#444444', 'body' => '#f5f5f5', 'clip' => '#222222', 'line' => '#444444'],
];
$c = $colors[$env] ?? $colors['local'];
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="<?= $size ?>" height="<?= $size ?>">
  <!-- 背景（角丸なし・枠いっぱい） -->
  <rect width="48" height="48" fill="<?= $c['bg'] ?>"/>
  <!-- クリップボード本体（左右上下0px） -->
  <rect x="0" y="7" width="48" height="41" fill="<?= $c['body'] ?>"/>
  <!-- クリップ（上部タブ）-->
  <rect x="13" y="0" width="22" height="14" rx="3" fill="<?= $c['clip'] ?>"/>
  <!-- ライン3本 -->
  <line x1="6"  y1="22" x2="42" y2="22" stroke="<?= $c['line'] ?>" stroke-width="5" stroke-linecap="square"/>
  <line x1="6"  y1="31" x2="42" y2="31" stroke="<?= $c['line'] ?>" stroke-width="5" stroke-linecap="square"/>
  <line x1="6"  y1="40" x2="28" y2="40" stroke="<?= $c['line'] ?>" stroke-width="5" stroke-linecap="square"/>
</svg>
