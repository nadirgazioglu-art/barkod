<?php
require_once __DIR__ . '/inc/auth.php';
$user = requireLogin();
requireSuper($user);
$pdo = db();
$pdo->exec('DELETE FROM verification_logs; DELETE FROM labels; DELETE FROM customers;');
flash('success', 'Test verileri temizlendi.');
header('Location: /dashboard.php');
exit;
