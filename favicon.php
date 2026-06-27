<?php
require_once __DIR__.'/config.php';

$size = (int)($_GET['size'] ?? 48);
$size = in_array($size, [48, 180]) ? $size : 48;

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=3600');

$env = defined('ENV_NAME') ? ENV_NAME : 'local';
if (!$_is_local) $env = 'sakura';

$colors = [
    'work'   => ['bg' => '#e8f5e0', 'stroke' => '#2d5a0e', 'clip' => '#2d5a0e', 'fill' => '#4a8a1a'],
    'home'   => ['bg' => '#dbeafe', 'stroke' => '#1244a0', 'clip' => '#1244a0', 'fill' => '#1a6fd8'],
    'sakura' => ['bg' => '#fce7f0', 'stroke' => '#7a2040', 'clip' => '#7a2040', 'fill' => '#c03060'],
    'local'  => ['bg' => '#e8e8e8', 'stroke' => '#444444', 'clip' => '#444444', 'fill' => '#666666'],
];
$c = $colors[$env] ?? $colors['local'];
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="<?= $size ?>" height="<?= $size ?>">
  <!-- 背景 -->
  <rect width="48" height="48" rx="9" fill="<?= $c['bg'] ?>"/>
  <!-- クリップボード本体 -->
  <rect x="10" y="13" width="28" height="29" rx="3.5" fill="white" stroke="<?= $c['stroke'] ?>" stroke-width="3"/>
  <!-- クリップ部分（上部） -->
  <rect x="17" y="8" width="14" height="9" rx="3" fill="<?= $c['fill'] ?>"/>
  <!-- ライン3本 -->
  <line x1="15" y1="25" x2="33" y2="25" stroke="<?= $c['stroke'] ?>" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="15" y1="31" x2="33" y2="31" stroke="<?= $c['stroke'] ?>" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="15" y1="37" x2="25" y2="37" stroke="<?= $c['stroke'] ?>" stroke-width="2.5" stroke-linecap="round"/>
</svg>
