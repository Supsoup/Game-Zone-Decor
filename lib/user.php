<?php
// lib/user.php
if (!function_exists('current_user')) {
  function current_user(): ?array {
    return $_SESSION['user'] ?? null;
  }
}

if (!function_exists('require_user')) {
  function require_user(?string $redirectTo = null): void {
    if (empty($_SESSION['user'])) {
      $uri = $redirectTo ?? ($_SERVER['REQUEST_URI'] ?? 'index.php');
      $_SESSION['redirect_to'] = $uri;
      header("Location: auth/login.php");
      exit;
    }
  }
}
