<?php
require_once 'config.php';
startSession();
session_destroy();
header('Location: /karte/index.php');
exit;
