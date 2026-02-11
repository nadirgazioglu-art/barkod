<?php
require_once __DIR__ . '/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
header('Location: /login.php');
exit;
