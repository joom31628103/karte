<?php
require_once __DIR__.'/config.php';
header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=3600');

$env = defined('ENV_NAME') ? ENV_NAME : 'local';
if (!$_is_local) $env = 'sakura';

$colors = [
    'work'   => ['bg' => '#f0fff4', 'stroke' => '#3B6D11', 'clip' => '#3B6D11'],
    'home'   => ['bg' => '#eff6ff', 'stroke' => '#185FA5', 'clip' => '#185FA5'],
    'sakura' => ['bg' => '#fff7f9', 'stroke' => '#993556', 'clip' => '#993556'],
    'local'  => ['bg' => '#f5f5f5', 'stroke' => '#555555', 'clip' => '#555555'],
];
$c = $colors[$env] ?? $colors['local'];
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48">
  <rect width="48" height="48" rx="10" fill="<?= $c['bg'] ?>"/>
  <rect x="12" y="14" width="24" height="26" rx="3" fill="none" stroke="<?= $c['stroke'] ?>" stroke-width="2.5"/>
  <rect x="18" y="10" width="12" height="7" rx="2" fill="<?= $c['clip'] ?>"/>
  <line x1="17" y1="24" x2="31" y2="24" stroke="<?= $c['stroke'] ?>" stroke-width="1.5"/>
  <line x1="17" y1="29" x2="31" y2="29" stroke="<?= $c['stroke'] ?>" stroke-width="1.5"/>
  <line x1="17" y1="34" x2="26" y2="34" stroke="<?= $c['stroke'] ?>" stroke-width="1.5"/>
</svg>
