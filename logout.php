<?php
require_once 'includes/config.php';
$_SESSION = [];
session_destroy();
header('Location: login.php?logout=1');
exit;
