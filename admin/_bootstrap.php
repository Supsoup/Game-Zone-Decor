<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/helpers.php';

if (function_exists('expireStaleOrders')) {
  expireStaleOrders($pdo);
}

function admin_logged_in() {
  return !empty($_SESSION['admin']);
}
function require_admin() {
  if (!admin_logged_in()) {
    header('Location: login.php'); exit;
  }
}
function admin_csrf_token() {
  if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['admin_csrf'];
}
function admin_csrf_check($token) {
  return hash_equals($_SESSION['admin_csrf'] ?? '', $token ?? '');
}
