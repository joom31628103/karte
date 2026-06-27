<?php
require_once __DIR__.'/config.php';

$size = (int)($_GET['size'] ?? 48);
$size = in_array($size, [48, 180]) ? $size : 48;

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=3600');

$env = defined('ENV_NAME') ? ENV_NAME : 'local';
if (!$_is_local) $env = 'sakura';

$colors = [
    'work'   => '#2d6a10',
    'home'   => '#a02050',
    'sakura' => '#1244a0',
    'local'  => '#a02050',
];
$bg = $colors[$env] ?? $colors['local'];
?>
<svg xmlns="http://www.w3.org/2000/svg"
     viewBox="0 0 32 32" width="<?= $size ?>" height="<?= $size ?>">
  <!-- 背景 -->
  <rect x="0" y="0" width="32" height="32" fill="<?= $bg ?>"/>
  <!-- 頭（円） -->
  <circle cx="16" cy="11" r="7" fill="#ffffff"/>
  <!-- 肩・胴体（扇形） -->
  <ellipse cx="16" cy="32" rx="13" ry="11" fill="#ffffff"/>
</svg>
