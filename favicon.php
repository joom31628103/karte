<?php
require_once __DIR__.'/config.php';

$size = (int)($_GET['size'] ?? 48);
$size = in_array($size, [48, 180]) ? $size : 48;

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=3600');

$env = defined('ENV_NAME') ? ENV_NAME : 'local';
if (!$_is_local) $env = 'sakura';

/* 本番(sakura)は青、それ以外(職場PC・自宅PC・未設定のローカル)はピンクで統一 */
$gradients = [
    'sakura' => ['#2F83FF', '#0E67E8'],
    'work'   => ['#FF2F83', '#E80E67'],
    'home'   => ['#FF2F83', '#E80E67'],
    'local'  => ['#FF2F83', '#E80E67'],
];
[$c1, $c2] = $gradients[$env] ?? $gradients['local'];
?>
<svg xmlns="http://www.w3.org/2000/svg"
     width="<?= $size ?>" height="<?= $size ?>" viewBox="0 0 1024 1024" role="img" aria-label="生徒カルテアプリのアイコン">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="<?= $c1 ?>"/>
      <stop offset="100%" stop-color="<?= $c2 ?>"/>
    </linearGradient>
    <clipPath id="avatarClip">
      <circle cx="512" cy="432" r="300"/>
    </clipPath>
  </defs>

  <!-- background -->
  <rect x="32" y="32" width="960" height="960" rx="176" fill="url(#bg)"/>

  <!-- avatar frame -->
  <circle cx="512" cy="432" r="300" fill="#FFFFFF"/>

  <!-- person cutout -->
  <g clip-path="url(#avatarClip)">
    <circle cx="512" cy="382" r="142" fill="url(#bg)"/>
    <rect x="292" y="556" width="440" height="320" rx="150" fill="url(#bg)"/>
  </g>

  <!-- info lines -->
  <rect x="262" y="792" width="500" height="66" rx="33" fill="#FFFFFF"/>
  <rect x="262" y="900" width="500" height="66" rx="33" fill="#FFFFFF"/>
</svg>
